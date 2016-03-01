<?php

define("BASEDIR", __DIR__);
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once(BASEDIR . "/config/config.php");
require_once(BASEDIR . "/vendor/autoload.php");

$app = new \Slim\Slim($config["slim"]);
$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware());
$app->view(new \Slim\Views\Twig());

// Load libraries
foreach(glob(BASEDIR . "/libraries/*.php") as $lib)
    require_once($lib);

// Routes
$app->get("/", function() use ($app) {
    $app->render("index.twig", array("crestURL" => "https://login.eveonline.com/oauth/authorize?response_type=code&redirect_uri=http://auth.karbowiak.dk/auth/&client_id=ef14fbdff95c4a24b5f2dfb20375f34d&scope=publicData"));
});

$app->get("/auth/", function() use ($app, $config) {
    $code = $app->request->get("code");
    $state = $app->request->get("state");

    $tokenURL = "https://login.eveonline.com/oauth/token";
    $base64 = base64_encode($config["sso"]["clientID"] . ":" . $config["sso"]["secretKey"]);

    $data = json_decode(sendData($tokenURL, array(
        "grant_type" => "authorization_code",
        "code" => $code
    ), array("Authorization: Basic {$base64}")));

    $accessToken = $data->access_token;
    $refreshToken = $data->refresh_token;


    // Verify Token
    $verifyURL = "https://login.eveonline.com/oauth/verify";
    $data = json_decode(sendData($verifyURL, array(), array("Authorization: Bearer {$accessToken}")));

    $characterID = $data->CharacterID;
    $characterName = $data->CharacterName;
    $characterOwnerHash = $data->CharacterOwnerHash;
    $characterData = json_decode(json_encode(new SimpleXMLElement(getData("https://api.eveonline.com/eve/CharacterInfo.xml.aspx?characterID={$characterID}"))));
    $corporationID = $characterData->result->corporationID;
    $allianceID = $characterData->result->allianceID;

    // Now check if the person is in a corp or alliance on the blue / allowed list
    // Whatever ID matches whatever group, they get added to. Discord role ordering decides what they can and can't see
    $access = array();
    $allowances = $config["groups"];
    foreach($allowances as $groupName => $groupData) {
        foreach($groupData as $type => $id) {
            switch($type) {
                case "character":
                    if($id == $characterID)
                        $access[] = $groupName;
                    break;

                case "corporation":
                    if($id == $corporationID)
                        $access[] = $groupName;
                    break;

                case "alliance":
                    if($id == $allianceID)
                        $access[] = $groupName;
                    break;
            }
        }
    }

    // Generate an invite link
    $discord = new \Discord\Discord($config["discord"]["email"], $config["discord"]["pass"]);
    $inviteData = $discord->api("channel")->invites()->create($config["discord"]["inviteChannel"], 60 * (mt_rand(1, 500) + 30), 1);
    $inviteLink = "https://discord.gg/" . $inviteData["code"];

    // Make the json access list
    $accessList = json_encode($access);

    // Generate an auth string
    $authString = uniqid();

    // Insert it all into the db
    dbExecute("REPLACE INTO registrations (characterID, corporationID, allianceID, groups, authString, active) VALUES (:characterID, :corporationID, :allianceID, :groups, :authString, 1)", array(":characterID" => $characterID, ":corporationID" => $corporationID, ":allianceID" => $allianceID, ":groups" => $accessList, ":authString" => $authString));

    $app->render("authed.twig", array("inviteLink" => $inviteLink, "authString" => $authString));
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