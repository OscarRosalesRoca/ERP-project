<?php
session_start();

require_once(__DIR__ . "/../../config/config_path.php"); 


if (!isset($_SESSION["usuario_id"])) {
    header("Location: " . BASE_URL . "/modules/login/login.php");
    exit;
}

// Si el usuario logueado NO es admin (rol_id 1), lo expulsamos al home de empleado.
// Esto evita que alguien escriba la URL a mano y entre.
if (!isset($_SESSION["rol_id"]) || $_SESSION["rol_id"] !== 1) {
    header("Location: " . BASE_URL . "/modules/home/empleado_home.php");
    exit;
}

$pagina = $_GET["pagina"] ?? "personal"; 
$permitidas = ["personal", "personal_list", "historial", "clientes", "proveedores", "almacenes", "productos", "facturas"];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administrador</title>
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/style_empleado_home.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/sections_style/general_sections_style.css">
</head>
<body>
<div class="dashboard_container">
        <div class="sidebar">
            <div class="user_info_container">
                <img src="<?php echo BASE_URL; ?>/assets/img/default_user.jpg" class="user_photo" alt="Foto de usuario">
                <span><?php echo $_SESSION["nombre_usuario"] ?? 'Admin'; ?></span>
                <br><small style="color: #ccc; font-size: 0.85em;">Administrador</small>
            </div>

            <ul class="menu">
                <li><a href="<?php echo BASE_URL; ?>/modules/home/admin_home.php?pagina=personal" class="<?= $pagina == 'personal' ? 'active' : '' ?>">Área personal</a></li>
                
                <li><a href="<?php echo BASE_URL; ?>/modules/home/admin_home.php?pagina=personal_list" class="<?= $pagina == 'personal_list' ? 'active' : '' ?>">Listado de personal</a></li>
                
                <li><a href="<?php echo BASE_URL; ?>/modules/home/admin_home.php?pagina=historial" class="<?= $pagina == 'historial' ? 'active' : '' ?>">Historial de actividad</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/admin_home.php?pagina=clientes" class="<?= $pagina == 'clientes' ? 'active' : '' ?>">Clientes</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/admin_home.php?pagina=proveedores" class="<?= $pagina == 'proveedores' ? 'active' : '' ?>">Proveedores</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/admin_home.php?pagina=almacenes" class="<?= $pagina == 'almacenes' ? 'active' : '' ?>">Almacenes</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/admin_home.php?pagina=productos" class="<?= $pagina == 'productos' ? 'active' : '' ?>">Productos</a></li>
                <li><a href="<?php echo BASE_URL; ?>/modules/home/admin_home.php?pagina=facturas" class="<?= $pagina == 'facturas' ? 'active' : '' ?>">Facturas</a></li>
            </ul>

            <div class="logout_container">
                <a class="logout_button" href="<?php echo BASE_URL; ?>/modules/login/logout.php">Cerrar sesión</a>
            </div>
        </div>

        <div class="main_content">
            <?php
            if (in_array($pagina, $permitidas)) {
                // Buscamos el archivo en la carpeta sections
                if (file_exists("sections/$pagina.php")) {
                    include "sections/$pagina.php";
                } else {
                    echo "<p>Error: El archivo de la sección '$pagina' no existe.</p>";
                }
            } else {
                echo "<p>Bienvenido al panel de administración.</p>";
            }
            ?>
        </div>
    </div>
</body>
</html>