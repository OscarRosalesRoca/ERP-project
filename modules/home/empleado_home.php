<?php
session_start();

require_once(__DIR__ . "/../../config/config_path.php"); 


// Comprobamos si hay sesión activa
if (!isset($_SESSION["usuario_id"])) {
    header("Location: " . BASE_URL . "/modules/login/login.php");
    exit;
}

$pagina = $_GET["pagina"] ?? "personal"; // Página por defecto
$permitidas = ["personal", "historial", "clientes", "proveedores", "almacenes", "productos", "facturas"];

// MODIFICACIÓN: Ruta corregida a "fotos_perfil"
$foto_perfil_src = BASE_URL . "/assets/img/default_user.jpg"; 
if (isset($_SESSION['foto_perfil']) && !empty($_SESSION['foto_perfil'])) {
    if ($_SESSION['foto_perfil'] == 'default_user.jpg') {
        $foto_perfil_src = BASE_URL . "/assets/img/default_user.jpg";
    } else {
        // AQUÍ EL CAMBIO:
        $foto_perfil_src = BASE_URL . "/uploads/fotos_perfil/" . $_SESSION['foto_perfil'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Empleado</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/style_empleado_home.css">
</head>
<body>
<div class="dashboard_container">
        <div class="sidebar">
            <div class="user_info_container">
                <img src="<?php echo $foto_perfil_src; ?>" class="user_photo" alt="Foto de usuario" style="object-fit: cover;">
                <span><?php echo $_SESSION["nombre_usuario"] ?? 'Usuario'; ?></span>
            </div>

            <ul class="menu">
                <li><a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=personal" class="<?= $pagina == 'personal' ? 'active' : '' ?>">Área personal</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=historial" class="<?= $pagina == 'historial' ? 'active' : '' ?>">Historial de actividad</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=clientes" class="<?= $pagina == 'clientes' ? 'active' : '' ?>">Clientes</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=proveedores" class="<?= $pagina == 'proveedores' ? 'active' : '' ?>">Proveedores</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=almacenes" class="<?= $pagina == 'almacenes' ? 'active' : '' ?>">Almacenes</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=productos" class="<?= $pagina == 'productos' ? 'active' : '' ?>">Productos</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=facturas" class="<?= $pagina == 'facturas' ? 'active' : '' ?>">Facturas</a></li>
            </ul>

            <div class="logout_container">
                <a class="logout_button" href="<?php echo BASE_URL; ?>/modules/login/logout.php">Cerrar sesión</a>
            </div>
        </div>

        <div class="main_content">
            <?php
            if (in_array($pagina, $permitidas)) {
                include "sections/$pagina.php"; 
            } else {
                echo "<p>Bienvenido al panel de empleado.</p>"; 
            }
            ?>
        </div>
    </div>
</body>
</html>