<?php
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inicio | ERP</title>
    <link rel="stylesheet" href="/ERP/assets/css/style_index.css">
</head>
<body>

<div class="card">
    <h1>Bienvenidos a mi proyecto de ERP</h1>
    <h3>Hecho por Óscar Rosales Roca</h3>
    <form method="post">
        <button class="button" name="iniciar">Iniciar ERP</button>
    </form>
</div>

<?php
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["iniciar"])) {
    if (isset($_SESSION["usuario_id"])) {
        header("Location: dashboard.php");
    } else {
        header("Location: modules/login/login.php");
    }
    exit;
}
?>

</body>
</html>