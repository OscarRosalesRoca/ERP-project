<?php
session_start();

require_once(__DIR__ . "/config/config_path.php"); 

$base_url = BASE_URL;

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inicio | ERP</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style_index.css">
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
        header("Location: " . BASE_URL . "/modules/home/empleado_home.php");
    } else {
        header("Location: " . BASE_URL . "/modules/login/login.php");
    }
    exit;
}
?>

</body>
</html>