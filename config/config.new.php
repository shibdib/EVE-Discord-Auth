<?php
$config = array();

// CREST
$config["sso"] = array(
    "clientID" => "",
    "secretKey" => ""
);

// Site
$config["site"] = array(
    "debug" => true,
    "userAgent" => null, // Use pre-defined user agents
    "apiRequestsPrMinute" => 1800,
);

// Cookies
$config["cookies"] = array(
    "name" => "rena",
    "ssl" => true,
    "time" => (3600 * 24 * 30),
    "secret" => "",
);

// Slim
$config["slim"] = array(
    "mode" => $config["site"]["debug"] ? "development" : "production",
    "debug" => $config["site"]["debug"],
    "cookies.secret_key" => $config["cookies"]["secret"],
    "templates.path" => BASEDIR . "/view/",
);

// Groups (Keys must match Discord group names, exactly)
// Inside the name, you must use the types alliance, corporation or character, with an ID for each
$config["groups"] = array(
    "Blues" => array(
        //"alliance" => 1234, // an alliance with the id 1234
        //"corporation" => 1234, // a corporation with the id 1234
        //"character" => 1234 // a character with the id 1234
    ),
    "The-Culture" => array(
        "alliance" => 99005805 // Just the culture alliance, no need to add all corporations
    )
);

// Make sure the user has access to the channel, and has allownace to create invites
$config["discord"] = array(
    "email" => "",
    "pass" => "",
    "inviteChannel" => 154084366409007104
);