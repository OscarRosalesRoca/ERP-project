<?php

require_once(__DIR__ . "/../config/config_db.php");

$connection = new mysqli($host, $user, $pass, $db);

if ($connection->connect_error) {
    die("Conexión fallida: " . $connection->connect_error);
}
?>