<?php
session_start();

// Comprobamos si hay sesión activa
if (!isset($_SESSION["usuario_id"])) {
    header("Location: /ERP/modules/login/login.php");
    exit;
}

$pagina = $_GET["pagina"] ?? "personal"; // Página por defecto
$permitidas = ["personal", "historial", "clientes", "proveedores", "almacenes", "productos", "facturas"];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Empleado</title>
    <link rel="stylesheet" href="/ERP/assets/css/style_empleado_home.css">
</head>
<div class="dashboard-container">
        <div class="sidebar">
            <!-- Contenedor de la foto y el nombre del usuario -->
            <div class="user-info-container">
                <img src="/ERP/assets/img/default_user.jpg" class="user-photo" alt="Foto de usuario">
                <span><?php echo $_SESSION["nombre_usuario"] ?? 'Usuario'; ?></span>
            </div>

            <!-- Menú lateral -->
            <ul class="menu">
                <li><a href="/ERP/modules/home/empleado_home.php?pagina=personal" class="<?= $pagina == 'personal' ? 'active' : '' ?>">Área personal</a></li>
                <li><a href="/ERP/modules/home/empleado_home.php?pagina=historial" class="<?= $pagina == 'historial' ? 'active' : '' ?>">Historial de actividad</a></li>
                <li><a href="/ERP/modules/home/empleado_home.php?pagina=clientes" class="<?= $pagina == 'clientes' ? 'active' : '' ?>">Clientes</a></li>
                <li><a href="/ERP/modules/home/empleado_home.php?pagina=proveedores" class="<?= $pagina == 'proveedores' ? 'active' : '' ?>">Proveedores</a></li>
                <li><a href="/ERP/modules/home/empleado_home.php?pagina=almacenes" class="<?= $pagina == 'almacenes' ? 'active' : '' ?>">Almacenes</a></li>
                <li><a href="/ERP/modules/home/empleado_home.php?pagina=productos" class="<?= $pagina == 'productos' ? 'active' : '' ?>">Productos</a></li>
                <li><a href="/ERP/modules/home/empleado_home.php?pagina=facturas" class="<?= $pagina == 'facturas' ? 'active' : '' ?>">Facturas</a></li>
            </ul>

            <!-- Botón de logout -->
            <div class="logout-container">
                <a class="logout-button" href="/ERP/modules/login/logout.php">Cerrar sesión</a>
            </div>
        </div>

        <!-- Contenedor de contenido principal -->
        <div class="main-content">
            <?php
            // Verificamos si la página solicitada es permitida
            if (in_array($pagina, $permitidas)) {
                include "sections/$pagina.php"; // Cargar la sección correspondiente
            } else {
                echo "<p>Bienvenido al panel de empleado.</p>"; // Página por defecto si no se encuentra la sección
            }
            ?>
        </div>
    </div>
</body>
</html>