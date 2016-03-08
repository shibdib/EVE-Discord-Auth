<?php

function insertUser($db, $user, $pass, $dbName, $characterID, $corporationID, $allianceID, $groups, $authString, $active)
{

    $conn = new mysqli($db, $user, $pass, $dbName);

    $sql = "INSERT INTO pendingUsers (characterID, corporationID, allianceID, groups, authString, active) VALUES ('$characterID','$corporationID','$allianceID','$groups','$authString','$active')";

    if ($conn->query($sql) === TRUE) {
        return null;
    } else {
        return null;
    }
}