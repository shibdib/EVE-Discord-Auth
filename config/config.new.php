<?php
$config = array();

// CREST
$config['sso'] = array(
    'clientID' => '', // https://developers.eveonline.com/
    'secretKey' => '',
    'callbackURL' => '', // Include trailing / (Will be the url_to_the_index.com/auth/)
);

$config['sqlite'] = array(
    'dir' => '........../Dramiel/database/authDB.sqlite' //Directory to your bots authDB.sqlite file (/Dramiel/database/authDB.sqlite)
);

$config['discord'] = array(
    'inviteLink' => '' //Make sure it's set to never expire and set to a public channel.
);

// Site IGNORE EVERYTHING BELOW THIS LINE
$config['site'] = array(
    'debug' => true,
    'userAgent' => null, // Use pre-defined user agents
    'apiRequestsPrMinute' => 1800,
);

// Cookies
$config['cookies'] = array(
    'name' => 'rena',
    'ssl' => true,
    'time' => 3600 * 24 * 30,
    'secret' => '',
);

// Slim
$config['slim'] = array(
    'mode' => $config['site']['debug'] ? 'development' : 'production',
    'debug' => $config['site']['debug'],
    'cookies.secret_key' => $config['cookies']['secret'],
    'templates.path' => BASEDIR . '/view/',
);



// IGNORE THIS SECTION FOR NOW!!!
$config['groups'] = array(
    'Blues' => array(
        //"alliance" => 1234, // an alliance with the id 1234
        //"corporation" => 1234, // a corporation with the id 1234
        //"character" => 1234 // a character with the id 1234
    ),
    'Black Serpent Technologies' => array(
        'corporation' => '1234' // Example
    )
);

