<?php

function insertUser($db, $user, $pass, $dbName, $characterID, $groups)
{

    $conn = new mysqli($db, $user, $pass, $dbName);

    $sql = "INSERT INTO pendingUsers (characterID, groups) VALUES ('$characterID','$groups')";

    if ($conn->query($sql) === TRUE) {
        return null;
    } else {
        return null;
    }
}