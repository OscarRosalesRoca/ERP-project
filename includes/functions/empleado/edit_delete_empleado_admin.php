<?php

require_once(__DIR__ . "/../../../config/config_path.php");
require_once(__DIR__ . "/../../connection.php");
require_once(__DIR__ . "/../../auth.php");

// Verificamos que sea Admin
if (!isset($_SESSION["rol_id"]) || $_SESSION["rol_id"] !== 1) {
    die("Acceso denegado.");
}

// RECIBIR EL ID DEL EMPLEADO A EDITAR
$cod_empleado_target = $_GET['cod'] ?? null;

if (!$cod_empleado_target) {
    echo "<p>Error: No se ha especificado un empleado.</p>";
    exit;
}

// Obtener datos del empleado OBJETIVO (no del usuario logueado)
$query = "
    SELECT u.id AS usuario_id, u.nombre_usuario,
    e.cod_empleado, e.mail, e.telefono, e.dni
    FROM empleado e
    JOIN usuarios u ON e.usuario_id = u.id
    WHERE e.cod_empleado = ?
";

$stmt = $connection->prepare($query);
$stmt->bind_param("i", $cod_empleado_target);
$stmt->execute();
$result = $stmt->get_result();
$empleado = $result->fetch_assoc();

if (!$empleado) {
    echo "<p>Error: Empleado no encontrado.</p>";
    exit;
}

$usuario_id_target = $empleado["usuario_id"];
$errores = [];

// --- PROCESAR FORMULARIO ---
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

    if (empty($errores)) {
        $connection->begin_transaction();
        try {
            // 1. Actualizar Nombre (Usuarios y Empleado)
            if (!empty($nuevo_nombre) && $nuevo_nombre !== $empleado["nombre_usuario"]) {
                // Verificar duplicados
                $check = $connection->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ? AND id != ?");
                $check->bind_param("si", $nuevo_nombre, $usuario_id_target);
                $check->execute();
                if($check->get_result()->num_rows > 0){
                     throw new Exception("El nombre de usuario ya está en uso.");
                }

                $stmt = $connection->prepare("UPDATE usuarios SET nombre_usuario = ? WHERE id = ?");
                $stmt->bind_param("si", $nuevo_nombre, $usuario_id_target);
                $stmt->execute();
        
                $stmt = $connection->prepare("UPDATE empleado SET nombre = ? WHERE usuario_id = ?");
                $stmt->bind_param("si", $nuevo_nombre, $usuario_id_target);
                $stmt->execute();
            }

            // 2. Actualizar Contraseña (si se escribe algo)
            if (!empty($nueva_contrasenia)) {
                $hash = password_hash($nueva_contrasenia, PASSWORD_DEFAULT);
                $stmt = $connection->prepare("UPDATE usuarios SET contrasenia = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $usuario_id_target);
                $stmt->execute();

                $stmt = $connection->prepare("UPDATE empleado SET contrasenia = ? WHERE usuario_id = ?");
                $stmt->bind_param("si", $hash, $usuario_id_target);
                $stmt->execute();
            }

            // 3. Actualizar Datos Empleado
            if (!empty($nuevo_email) && $nuevo_email !== $empleado["mail"]) {
                $stmt = $connection->prepare("UPDATE empleado SET mail = ? WHERE usuario_id = ?");
                $stmt->bind_param("si", $nuevo_email, $usuario_id_target);
                $stmt->execute();
            }

            if (!empty($nuevo_telefono) && $nuevo_telefono !== $empleado["telefono"]) {
                $stmt = $connection->prepare("UPDATE empleado SET telefono = ? WHERE usuario_id = ?");
                $stmt->bind_param("si", $nuevo_telefono, $usuario_id_target);
                $stmt->execute();
            }

            if (!empty($nuevo_dni) && $nuevo_dni !== $empleado["dni"]) {
                $stmt = $connection->prepare("UPDATE empleado SET dni = ? WHERE usuario_id = ?");
                $stmt->bind_param("si", $nuevo_dni, $usuario_id_target);
                $stmt->execute();
            }

            $connection->commit();
            
            // REDIRECCIÓN: Volver al listado de admin
            header("Location: " . BASE_URL . "/modules/home/admin_home.php?pagina=personal_list&mensaje=editado");
            exit;

        } catch (Exception $e) {
            $connection->rollback();
            $errores[] = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Empleado (Admin)</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar Empleado: <?= htmlspecialchars($empleado["nombre_usuario"]) ?></h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Nombre de usuario</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($_POST["nombre"] ?? $empleado["nombre_usuario"]) ?>">

            <label>Correo electrónico</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST["email"] ?? $empleado["mail"]) ?>">

            <label>Teléfono</label>
            <input type="text" name="telefono" value="<?= htmlspecialchars($_POST["telefono"] ?? $empleado["telefono"]) ?>">

            <label>DNI</label>
            <input type="text" name="dni" value="<?= htmlspecialchars($_POST["dni"] ?? $empleado["dni"]) ?>">

            <label>Nueva Contraseña (Dejar vacío para no cambiar)</label>
            <input type="password" name="contrasenia" placeholder="Escribe para cambiar...">

            <div class="botones">
                <button type="submit">Guardar cambios</button>
            </div>
            
            <div style="margin-top: 10px;">
                <a href="<?php echo BASE_URL; ?>/modules/home/admin_home.php?pagina=personal_list" class="back_button">Cancelar</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>