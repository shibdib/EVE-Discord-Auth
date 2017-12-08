<?php

define("BASEDIR", __DIR__);
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once(BASEDIR . "/config/config.php");
require_once(BASEDIR . "/vendor/autoload.php");
include 'discord.php';

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
    if (isset($_GET['code']) && !isset($_COOKIE["eveCode"])) {
        $cookie_name = "eveCode";
        $cookie_value = $_GET['code'];
        setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");
        $url = $config['sso']['callbackURL'];
        echo '<a href="'.$url.'">Click Me To Continue</a>';
        return;
    }
    $provider = new \League\OAuth2\Client\Provider\Discord([
        'clientId'     => $config['discord']['clientId'],
        'clientSecret' => $config['discord']['clientSecret'],
        'redirectUri'  => $config['discord']['redirectUri'],
    ]);
    if (!isset($_GET['code'])) {
        // If we don't have a code yet, we need to make the link
        $provider->addScopes(['guilds.join', 'identify']);
        $scopes = 'guilds.join%20identify%20guilds';
        $discordLink = url($config['discord']['clientId'],$config['discord']['redirectUri'],$scopes);
        $app->render("discord.twig", array("discordLink" => $discordLink));

    } else {
        // If we do have a code, use it to get a token
        init($_GET['code'],$config['discord']['redirectUri'],$config['discord']['clientId'],$config['discord']['clientSecret']);
        get_user();
        $guilds = get_guilds();

        $restcord = new DiscordClient(['token' => $config['discord']['botToken']]);
        $restcord->invite->acceptInvite(['invite.code' => $config['discord']['inviteLink']]);
        $code = $_COOKIE['eveCode'];

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
        $roles = $restcord->guild->getGuildRoles(['guild.id' => $config['discord']['guildId']]);
        foreach ($config["groups"] as $authGroup) {
            $id = $authGroup->id;
            $role = null;
            if ($id == $characterID) {
                foreach ($roles as $role) {
                    if ($role->name == $authGroup->role) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole(['guild.id' => $config['discord']['guildId'], 'user.id' => $_SESSION['user_id'], 'role.id' => $role->id]);
                $access[] = 'character';
                break;
            } else if ($id == $allianceID) {
                foreach ($roles as $role) {
                    if ($role->name == $authGroup->role) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole(['guild.id' => $config['discord']['guildId'], 'user.id' => $_SESSION['user_id'], 'role.id' => $role->id]);
                $access[] = 'alliance';
                break;
            } else if ($id == $corporationID)
                foreach ($roles as $role) {
                    if ($role->name == $authGroup->role) {
                        break;
                    }
                }
            if ($role) $restcord->guild->addGuildMemberRole(['guild.id' => $config['discord']['guildId'], 'user.id' => $_SESSION['user_id'], 'role.id' => $role->id]);
            $access[] = 'corp';
            break;
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
