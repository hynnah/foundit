<?php
    // Database configuration
        if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) { // LOCALHOST
            $serverName = "localhost";
            $databaseUsername = "root";
            $databasePassword = "";
            $databaseName = "s22102131_foundit";
        } else { // ONLINE
            $serverName = "localhost";
            $databaseUsername = "s22102131_foundit";
            $databasePassword = "fi_database123";
            $databaseName = "s22102131_foundit";
        }

$connection = mysqli_connect($serverName, $databaseUsername, $databasePassword, $databaseName);

if (!$connection) {
    die("Connection failed: " . mysqli_connect_error());
}