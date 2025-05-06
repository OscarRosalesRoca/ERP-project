<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: /ERP/modules/login/login.php");
    exit;
}

// Controlar expiración por inactividad (30 min = 1800 segundos)
if (isset($_SESSION["ultimo_acceso"]) && (time() - $_SESSION["ultimo_acceso"] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: /ERP/modules/login/login.php?mensaje=expirado");
    exit;
}

$_SESSION["ultimo_acceso"] = time();

// Exponemos el ID del usuario para los archivos que incluyan este archivo
$usuario_id = $_SESSION["usuario_id"];

?>