<?php
    if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
    // LOCALHOST
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "foundit";
} else {
    // ONLINE
    $servername = "localhost"; 
    $username = "s22102131_foundit";
    $password = "fi_database123";
    $dbname = "s22102131_foundi";
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection Failed! " . $conn->connect_error);
}


?>