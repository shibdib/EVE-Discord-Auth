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
$app->get("/", function() use ($app, $config) {
    $app->render("index.twig", array("crestURL" => "https://login.eveonline.com/oauth/authorize?response_type=code&redirect_uri=" . $config['sso']['callbackURL'] . "&client_id=" . $config['sso']['clientID']));
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


    // Verify Token
    $verifyURL = "https://login.eveonline.com/oauth/verify";
    $data = json_decode(sendData($verifyURL, array(), array("Authorization: Bearer {$accessToken}")));

    $characterID = $data->CharacterID;
    $characterData = json_decode(json_encode(new SimpleXMLElement(getData("https://api.eveonline.com/eve/CharacterInfo.xml.aspx?characterID={$characterID}"))));
    $corporationID = $characterData->result->corporationID;
    if (!isset($characterData->result->allianceID)) { $allianceID = 1; } else { $allianceID = $characterData->result->allianceID; }

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

    $inviteLink = $config["discord"]["inviteLink"];

    // Make the json access list
    $accessList = json_encode($access);

    // Generate an auth string
    $authString = uniqid();
	
	// Set active to yes
	$active = '1';

    // Insert it all into the db
    insertUser($config["db"]["url"], $config["db"]["user"], $config["db"]["pass"], $config["db"]["dbname"], $characterID, $corporationID, $allianceID, $accessList, $authString, $active);

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
