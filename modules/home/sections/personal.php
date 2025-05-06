<?php
require_once ("../../includes/connection.php");
require_once ("../../includes/auth.php");

// Consulta con JOIN entre usuarios y empleado
$query = "
    SELECT 
        u.nombre_usuario,
        u.rol_id,
        u.fecha_creacion,
        u.contrasenia,
        e.dni,
        e.mail,
        e.telefono
    FROM usuarios u
    LEFT JOIN empleado e ON u.id = e.usuario_id
    WHERE u.id = ?
";

$stmt = $connection->prepare($query);
if (!$stmt) {
    die("Error al preparar la consulta: " . $connection->error);
}

$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
$datos = $resultado->fetch_assoc();

if (!$datos) {
    echo "<p>No se encontraron datos para este usuario.</p>";
    exit;
}

// Determinar el rol en texto
$roles = [
    1 => "Administrador",
    2 => "Empleado",
    3 => "Cliente"
];

$rol_texto = $roles[$datos["rol_id"]] ?? "Desconocido";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Área personal</title>
    <link rel="stylesheet" href="/ERP/assets/css/style_personal.css">
</head>
<body>
<div class="personal-container">
    <h2>Área personal</h2>
    <ul class="personal-info">
        <li><strong>Nombre de usuario:</strong> <?php echo htmlspecialchars($datos["nombre_usuario"]); ?></li>
        <li><strong>Rol:</strong> <?php echo $rol_texto; ?></li>
        <li><strong>DNI:</strong> <?php echo htmlspecialchars($datos["dni"] ?? "No disponible"); ?></li>
        <li><strong>Correo electrónico:</strong> <?php echo htmlspecialchars($datos["mail"] ?? "No disponible"); ?></li>
        <li><strong>Teléfono:</strong> <?php echo htmlspecialchars($datos["telefono"] ?? "No disponible"); ?></li>
        <li><strong>Fecha de creación:</strong> <?php echo htmlspecialchars($datos["fecha_creacion"]); ?></li>
        <li>
            <a href="/ERP/includes/functions/empleado/edit_delete_empleado.php" class="editar-link">Editar contraseña o perfil</a>
        </li>
    </ul>
</div>
</body>
