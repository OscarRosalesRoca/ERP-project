<?php
$host = "localhost";
$usuario = "root";
$password = "";
$base_datos = "erp";

$connection = new mysqli($host, $usuario, $password, $base_datos);

if ($connection->connect_error) {
    die("Conexión fallida: " . $connection->connect_error);
}
?>