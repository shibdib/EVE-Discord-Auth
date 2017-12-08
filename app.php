<?php

define("BASEDIR", __DIR__);
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once(BASEDIR . "/config/config.php");
require_once(BASEDIR . "/vendor/autoload.php");

use psecio\DiscordClient;

$app = new \Slim\Slim($config["slim"]);
$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware());
$app->view(new \Slim\Views\Twig());

// Load libraries
foreach(glob(BASEDIR . "/libraries/*.php") as $lib)
    require_once($lib);



// Routes
$app->get("/", function() use ($app, $config) {
    $app->render("index.twig", array("crestURL" => "https://login.eveonline.com/oauth/authorize?response_type=code&redirect_uri=" . $config['sso']['callbackURL'] . "&client_id=" . $config['sso']['clientID']));
});

$app->get("/auth/", function() use ($app, $config) {
    $provider = new \League\OAuth2\Client\Provider\Discord([
        'clientId'     => $config['discord']['clientId'],
        'clientSecret' => $config['discord']['clientSecret'],
        'redirectUri'  => $config['discord']['redirectUri'],
    ]);
    if (!isset($_GET['code'])) {
        // If we don't have a code yet, we need to make the link
        $provider->addScopes(['guilds.join']);
        $discordLink = $provider->getAuthorizationUrl();
        $app->render("discord.twig", array("discordLink" => $discordLink));

    } else {
        // If we do have a code, use it to get a token
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        $user = $provider->getResourceOwner($accessToken);

        /**
         * User object contains:
         * - username
         * - verified
         * - mfa_enabled
         * - id
         * - avatar
         */

        $restcord = new DiscordClient(['token' => $config['discord']['botToken']]);
        $restcord->invite->acceptInvite(['invite.code' => $config['discord']['inviteLink']]);
        $code = $app->request->get("code");
        $state = $app->request->get("state");

        $tokenURL = "https://login.eveonline.com/oauth/token";
        $base64 = base64_encode($config["sso"]["clientID"] . ":" . $config["sso"]["secretKey"]);

        $data = json_decode(sendData($tokenURL, array(
            "grant_type" => "authorization_code",
            "code" => $code
        ), array("Authorization: Basic {$base64}")));

        $accessToken = $data->access_token;


        // Verify Token
        $verifyURL = "https://login.eveonline.com/oauth/verify";
        $data = json_decode(sendData($verifyURL, array(), array("Authorization: Bearer {$accessToken}")));

        $characterID = $data->CharacterID;
        $characterData = json_decode(json_encode(new SimpleXMLElement(getData("https://api.eveonline.com/eve/CharacterInfo.xml.aspx?characterID={$characterID}"))));
        $corporationID = $characterData->result->corporationID;
        if (!isset($characterData->result->allianceID)) {
            $allianceID = 1;
        } else {
            $allianceID = $characterData->result->allianceID;
        }

        // Now check if the person is in a corp or alliance on the blue / allowed list
        // Whatever ID matches whatever group, they get added to. Discord role ordering decides what they can and can't see
        $access = array();
        $allowances = $config["groups"];
        $roles = $restcord->guild->getGuildRoles(['guild.id' => $config['discord']['guildId']]);
        foreach ($allowances as $groupName => $groupData) {
            foreach ($groupData as $type => $id) {
                switch ($type) {
                    case "character":
                        if ($id == $characterID)
                            $access[] = $groupName;
                        break;

                    case "corporation":
                        if ($id == $corporationID)
                            foreach ($roles as $role) {
                                if ($role->name == $config['discord']['corpRole']) {
                                    break;
                                }
                            }
                            $restcord->guild->addGuildMemberRole(['guild.id' => $config['discord']['guildId'], 'user.id' => $user->id, 'role.id' => $role->id]);
                            $access[] = $groupName;
                        break;

                    case "alliance":
                        if ($id == $allianceID)
                            foreach ($roles as $role) {
                                if ($role->name == $config['discord']['allianceRole']) {
                                    break;
                                }
                            }
                            $restcord->guild->addGuildMemberRole(['guild.id' => $config['discord']['guildId'], 'user.id' => $user->id, 'role.id' => $role->id]);
                            $access[] = $groupName;
                        break;
                }
            }
        }

        // Make the json access list
        $accessList = json_encode($access);

        // Insert it all into the db
        insertUser($config["db"]["url"], $config["db"]["user"], $config["db"]["pass"], $config["db"]["dbname"], $characterID, $accessList);

        $app->render("authed.twig");
    }
});

$app->run();

/**
 * Var_dumps and dies, quicker than var_dump($input); die();
 *
 * @param $input
 */
function dd($input)
{
    var_dump($input);
    die();
}
