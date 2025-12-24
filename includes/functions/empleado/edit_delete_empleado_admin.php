<?php

require_once(__DIR__ . "/../../../config/config_path.php");
require_once(__DIR__ . "/../../connection.php");
require_once(__DIR__ . "/../../auth.php");

// Verificamos que sea Admin
if (!isset($_SESSION["rol_id"]) || $_SESSION["rol_id"] !== 1) {
    die("Acceso denegado.");
}

// Recibimos la id del empleado a editar
$cod_empleado_target = $_GET['cod'] ?? null;

if (!$cod_empleado_target) {
    echo "<p>Error: No se ha especificado un empleado.</p>";
    exit;
}

$query = "
    SELECT u.id AS usuario_id, u.nombre_usuario, u.foto_perfil,
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

// Procesamos formulario
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
            // Actualizar Nombre 
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

            // Actualizar Contraseña
            if (!empty($nueva_contrasenia)) {
                $hash = password_hash($nueva_contrasenia, PASSWORD_DEFAULT);
                $stmt = $connection->prepare("UPDATE usuarios SET contrasenia = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $usuario_id_target);
                $stmt->execute();

                $stmt = $connection->prepare("UPDATE empleado SET contrasenia = ? WHERE usuario_id = ?");
                $stmt->bind_param("si", $hash, $usuario_id_target);
                $stmt->execute();
            }

            // Actualizar Datos
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
            
            header("Location: " . BASE_URL . "/modules/home/admin_home.php?pagina=personal_list&mensaje=editado");
            exit;

        } catch (Exception $e) {
            $connection->rollback();
            $errores[] = "Error: " . $e->getMessage();
        }
    }
}

// Lógica para determinar la ruta de la foto a mostrar
$ruta_foto = BASE_URL . "/assets/img/default_user.jpg"; 
if (!empty($empleado['foto_perfil']) && file_exists(__DIR__ . "/../../../uploads/fotos_perfil/" . $empleado['foto_perfil'])) {
    $ruta_foto = BASE_URL . "/uploads/fotos_perfil/" . $empleado['foto_perfil'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Empleado (Admin)</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
    <style>
        .perfil-img-container { text-align: center; margin-bottom: 20px; }
        .perfil-img { 
            width: 120px; height: 120px; 
            border-radius: 50%; object-fit: cover; 
            border: 3px solid #ddd; 
        }
        .admin-note {
            display: block; font-size: 0.8em; color: #666; margin-top: 5px; font-style: italic;
        }
    </style>
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
            <div class="perfil-img-container">
                <img src="<?= $ruta_foto ?>" alt="Foto Perfil" class="perfil-img">
                <span class="admin-note">(La foto solo puede cambiarla el usuario)</span>
            </div>

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