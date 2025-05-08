<?php
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$nombre_usuario = $_SESSION["nombre_usuario"] ?? null;

if (!$nombre_usuario) {
    echo "<p>Error: sesión no válida.</p>";
    exit;
}

// Obtener datos actuales
$query = "
    SELECT u.id AS usuario_id, u.nombre_usuario, u.contrasenia AS contrasenia_usuario,
    e.cod_empleado, e.mail, e.telefono, e.dni, e.contrasenia AS contrasenia_empleado
    FROM usuarios u
    LEFT JOIN empleado e ON u.id = e.usuario_id
    WHERE u.nombre_usuario = ?
";

$stmt = $connection->prepare($query);
$stmt->bind_param("s", $nombre_usuario);
$stmt->execute();
$result = $stmt->get_result();
$empleado = $result->fetch_assoc();

if (!$empleado) {
    echo "<p>Error: usuario no encontrado.</p>";
    exit;
}

$usuario_id = $empleado["usuario_id"];
$errores = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_usuario"])) {
    // Eliminar de 'empleado' primero por la restricción de clave foránea
    $stmt = $connection->prepare("DELETE FROM empleado WHERE usuario_id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();

    // Luego eliminar de 'usuarios'
    $stmt = $connection->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();

    // Cerrar sesión y redirigir a login
    session_unset();
    session_destroy();
    header("Location: /ERP/modules/login/login.php?mensaje=cuenta_eliminada");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nuevo_nombre = trim($_POST["nombre"]);
    $nuevo_email = trim($_POST["email"]);
    $nuevo_telefono = trim($_POST["telefono"]);
    $nuevo_dni = trim($_POST["dni"]);
    $nueva_contrasenia = trim($_POST["contrasenia"]);

    // Validaciones básicas
    if (!empty($nuevo_email) && !filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido.";
    }

    // Si no hay errores, procesamos actualizaciones
    if (empty($errores)) {
        // Actualizar usuarios si cambió nombre o contraseña
        if (!empty($nuevo_nombre) && $nuevo_nombre !== $empleado["nombre_usuario"]) {
            // Actualizar en usuarios
            $stmt = $connection->prepare("UPDATE usuarios SET nombre_usuario = ? WHERE id = ?");
            $stmt->bind_param("si", $nuevo_nombre, $usuario_id);
            $stmt->execute();
        
            // Actualizar en empleado
            $stmt = $connection->prepare("UPDATE empleado SET nombre = ? WHERE usuario_id = ?");
            $stmt->bind_param("si", $nuevo_nombre, $usuario_id);
            $stmt->execute();
        }

        if (!empty($nueva_contrasenia)) {
            $hash = password_hash($nueva_contrasenia, PASSWORD_DEFAULT);
            $stmt = $connection->prepare("UPDATE usuarios SET contrasenia = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $usuario_id);
            $stmt->execute();

            $stmt = $connection->prepare("UPDATE empleado SET contrasenia = ? WHERE usuario_id = ?");
            $stmt->bind_param("si", $hash, $usuario_id);
            $stmt->execute();
        }

        // Actualizar campos en empleado
        if (!empty($nuevo_email) && $nuevo_email !== $empleado["mail"]) {
            $stmt = $connection->prepare("UPDATE empleado SET mail = ? WHERE usuario_id = ?");
            $stmt->bind_param("si", $nuevo_email, $usuario_id);
            $stmt->execute();
        }

        if (!empty($nuevo_telefono) && $nuevo_telefono !== $empleado["telefono"]) {
            $stmt = $connection->prepare("UPDATE empleado SET telefono = ? WHERE usuario_id = ?");
            $stmt->bind_param("si", $nuevo_telefono, $usuario_id);
            $stmt->execute();
        }

        if (!empty($nuevo_dni) && $nuevo_dni !== $empleado["dni"]) {
            $stmt = $connection->prepare("UPDATE empleado SET dni = ? WHERE usuario_id = ?");
            $stmt->bind_param("si", $nuevo_dni, $usuario_id);
            $stmt->execute();
        }

        // Cerrar sesión tras actualización
        session_unset();
        session_destroy();
        header("Location: /ERP/modules/login/login.php?mensaje=actualizado");
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar perfil</title>
    <link rel="stylesheet" href="/ERP/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar perfil</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Nombre de usuario</label>
            <input type="text" name="nombre" placeholder="<?= htmlspecialchars($empleado["nombre_usuario"]) ?>">

            <label>Correo electrónico</label>
            <input type="email" name="email" placeholder="<?= htmlspecialchars($empleado["mail"]) ?>">

            <label>Teléfono</label>
            <input type="text" name="telefono" placeholder="<?= htmlspecialchars($empleado["telefono"]) ?>">

            <label>DNI</label>
            <input type="text" name="dni" placeholder="<?= htmlspecialchars($empleado["dni"]) ?>">

            <label>Contraseña</label>
            <input type="password" name="contrasenia" placeholder="Nueva contraseña (opcional)">

            <div class="botones">
                <button type="submit">Guardar cambios</button>
                <p>o</p>
                <button type="submit" name="eliminar_usuario" class="eliminar_boton" onclick="return confirm('¿Estás seguro de que deseas eliminar tu cuenta? Esta acción no se puede deshacer.')">
                    <p>Eliminar cuenta</p>
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>