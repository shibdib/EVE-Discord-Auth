<?php

define('BASEDIR', __DIR__);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once BASEDIR . '/config/config.php';
require_once BASEDIR . '/vendor/autoload.php';

$app = new \Slim\Slim($config['slim']);
$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware());
$app->view(new \Slim\Views\Twig());

// Load libraries
foreach (glob(BASEDIR . '/libraries/*.php') as $lib)
    require_once $lib;


// Routes
$app->get('/', function () use ($app, $config) {
    $app->render('index.twig', array('crestURL' => 'https://login.eveonline.com/oauth/authorize?response_type=code&redirect_uri=' . $config['sso']['callbackURL'] . '&client_id=' . $config['sso']['clientID']));
});

$app->get('/auth/', function () use ($app, $config) {
    $code = $app->request->get('code');
    $state = $app->request->get('state');

    $tokenURL = 'https://login.eveonline.com/oauth/token';
    $base64 = base64_encode($config['sso']['clientID'] . ':' . $config['sso']['secretKey']);

    $data = json_decode(sendData($tokenURL, array(
        'grant_type' => 'authorization_code',
        'code' => $code
    ), array("Authorization: Basic {$base64}")));

    $accessToken = $data->access_token;


    // Verify Token
    $verifyURL = 'https://login.eveonline.com/oauth/verify';
    $data = json_decode(sendData($verifyURL, array(), array("Authorization: Bearer {$accessToken}")));

    $characterID = $data->CharacterID;
    $characterData = json_decode(json_encode(new SimpleXMLElement(getData("https://api.eveonline.com/eve/CharacterInfo.xml.aspx?characterID={$characterID}"))));
    $corporationID = $characterData->result->corporationID;
    if (!isset($characterData->result->allianceID)) {
        $allianceID = 1;
    } else {
        $allianceID = $characterData->result->allianceID;
    }

    $inviteLink = $config['discord']['inviteLink'];

    // Generate an auth string
    $authString = uniqid('', true);

    // Insert it all into the db

    // Set active to yes
    $active = '1';

    //check which db you're using
    if (isset($config["db"]["url"])) {
        insertUser($config["db"]["url"], $config["db"]["user"], $config["db"]["pass"], $config["db"]["dbname"], $characterID, $corporationID, $allianceID, $authString, $active);
    }elseif (isset($config['sqlite']['dir'])){
        insertPending($config, $characterID, $corporationID, $allianceID, $authString);
    }

    $app->render('authed.twig', array('inviteLink' => $inviteLink, 'authString' => $authString));
});

$app->run();

function insertPending($config, $characterID, $corporationID, $allianceID, $authString)
{
    $dir = $config['sqlite']['dir'];

    // Set active to yes
    $active = '1';

    $query = 'REPLACE INTO pendingUsers (`characterID`, `corporationID`, `allianceID`, `authString`, `active`) VALUES (:characterID,:corporationID,:allianceID,:authString,:active)';
    $variables = array(':characterID' => $characterID, ':corporationID' => $corporationID, ':allianceID' => $allianceID, ':authString' => $authString, ':active' => $active);
    //Insert
    dbExecute($query, $dir, $variables);
}
