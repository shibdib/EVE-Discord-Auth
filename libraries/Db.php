<?php

function insertUser($characterID, $discordID, $accessList)
{
    dbExecute('REPLACE INTO authed (`characterID`, `discordID`, `groups`) VALUES (:characterID,:discordID,:groups)', array(':characterID' => $characterID, ':discordID' => $discordID, ':groups' => $accessList));
    return null;
}

function openDB()
{
    $db = __DIR__ . '/../config/database/auth.sqlite';

    $dsn = "sqlite:$db";
    try {
        $pdo = new PDO($dsn, '', '', array(
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            )
        );
    } catch (Exception $e) {
        $pdo = null;
        return $pdo;
    }

    return $pdo;
}