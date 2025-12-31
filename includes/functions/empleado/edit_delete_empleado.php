<?php

require_once(__DIR__ . "/../../../config/config_path.php");
require_once(__DIR__ . "/../../connection.php");
require_once(__DIR__ . "/../../auth.php");

$nombre_usuario = $_SESSION["nombre_usuario"] ?? null;

if (!$nombre_usuario) {
    header("Location: " . BASE_URL . "/modules/login/login.php");
    exit;
}

$ruta_home = BASE_URL . "/modules/home/empleado_home.php";
if (isset($_SESSION["rol_id"]) && $_SESSION["rol_id"] == 1) {
    $ruta_home = BASE_URL . "/modules/home/admin_home.php";
}

// Obtener datos actuales
$query = "
    SELECT u.id AS usuario_id, u.nombre_usuario, u.foto_perfil, 
    e.cod_empleado, e.mail, e.telefono, e.dni
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

// Función para procesar imagen (sin cambios)
function procesarImagen($file, $userId) {
    $target_dir = __DIR__ . "/../../../uploads/fotos_perfil/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
    $newFileName = "perfil_" . $userId . "_" . time() . ".jpg"; 
    $target_file = $target_dir . $newFileName;
    $check = getimagesize($file["tmp_name"]);
    if($check === false) return ["error" => "El archivo no es una imagen válida."];
    $imageType = $check[2];
    $src_image = null;
    switch ($imageType) {
        case IMAGETYPE_JPEG: $src_image = imagecreatefromjpeg($file["tmp_name"]); break;
        case IMAGETYPE_PNG: $src_image = imagecreatefrompng($file["tmp_name"]); break;
        case IMAGETYPE_WEBP: $src_image = imagecreatefromwebp($file["tmp_name"]); break;
        default: return ["error" => "Formato no soportado. Solo JPG, PNG o WEBP."];
    }
    if (!$src_image) return ["error" => "Error al procesar la imagen."];
    $width = imagesx($src_image);
    $height = imagesy($src_image);
    $min = min($width, $height); 
    $x = ($width - $min) / 2;
    $y = ($height - $min) / 2;
    $new_size = 500;
    $dst_image = imagecreatetruecolor($new_size, $new_size);
    if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_WEBP) {
        $white = imagecolorallocate($dst_image, 255, 255, 255);
        imagefilledrectangle($dst_image, 0, 0, $new_size, $new_size, $white);
    }
    imagecopyresampled($dst_image, $src_image, 0, 0, $x, $y, $new_size, $new_size, $min, $min);
    if(imagejpeg($dst_image, $target_file, 90)) {
        imagedestroy($src_image);
        imagedestroy($dst_image);
        return ["success" => $newFileName];
    } else {
        return ["error" => "Error al guardar la imagen en el servidor."];
    }
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nuevo_nombre = trim($_POST["nombre"]);
    $nuevo_email = trim($_POST["email"]);
    $nuevo_telefono = trim($_POST["telefono"]);
    $nuevo_dni = trim($_POST["dni"]);
    $nueva_contrasenia = trim($_POST["contrasenia"]);
    
    // Validación de formato email
    if (!empty($nuevo_email) && !filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido.";
    }

    // Procesar foto
    $foto_final = null;
    if (isset($_FILES["foto_perfil"]) && $_FILES["foto_perfil"]["error"] == 0) {
        $resultado_img = procesarImagen($_FILES["foto_perfil"], $usuario_id);
        if (isset($resultado_img["error"])) {
            $errores[] = $resultado_img["error"];
        } else {
            $foto_final = $resultado_img["success"];
        }
    }

    if (empty($errores)) {
        $campos_repetidos = [];

        // 1. Comprobar tabla EMPLEADO (mail, telefono, dni)
        // IMPORTANTE: AND usuario_id != ? para que no detecte mis propios datos como duplicados
        $sql = "SELECT mail, telefono, dni FROM empleado WHERE (mail = ? OR telefono = ? OR dni = ?) AND usuario_id != ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("sssi", $nuevo_email, $nuevo_telefono, $nuevo_dni, $usuario_id);
        $stmt->execute();
        $res_emp = $stmt->get_result();

        while ($fila = $res_emp->fetch_assoc()) {
            if (strtolower($fila['mail']) === strtolower($nuevo_email)) {
                $campos_repetidos[] = "correo electrónico";
            }
            if ($fila['telefono'] === $nuevo_telefono) {
                $campos_repetidos[] = "teléfono";
            }
            if (strtolower($fila['dni']) === strtolower($nuevo_dni)) {
                $campos_repetidos[] = "DNI";
            }
        }
        $stmt->close();

        // Comprobar tabla usuarios
        // También excluimos al usuario actual con AND id != ?
        $sql2 = "SELECT nombre_usuario FROM usuarios WHERE nombre_usuario = ? AND id != ?";
        $stmt2 = $connection->prepare($sql2);
        $stmt2->bind_param("si", $nuevo_nombre, $usuario_id);
        $stmt2->execute();
        $res_usr = $stmt2->get_result();

        if ($res_usr->num_rows > 0) {
            $campos_repetidos[] = "nombre de usuario";
        }
        $stmt2->close();

        // Generar mensaje de error
        if (count($campos_repetidos) > 0) {
            $campos_unicos = array_unique($campos_repetidos);
            $lista_errores = implode(" y ", $campos_unicos);
            $errores[] = "Los datos que ha introducido ($lista_errores) ya están registrados y por lo tanto no son válidos.";
        }
    }

    // Si NO hay errores tras la validación, procedemos a guardar
    if (empty($errores)) {
        $connection->begin_transaction();
        try {
            // Actualizar nombre
            if (!empty($nuevo_nombre)) {
                // Aquí ya sabemos que es único gracias a la validación anterior
                $connection->query("UPDATE usuarios SET nombre_usuario = '$nuevo_nombre' WHERE id = $usuario_id");
                $connection->query("UPDATE empleado SET nombre = '$nuevo_nombre' WHERE usuario_id = $usuario_id");
                $_SESSION["nombre_usuario"] = $nuevo_nombre;
            }

            // Actualizar foto
            if ($foto_final) {
                $stmt = $connection->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
                $stmt->bind_param("si", $foto_final, $usuario_id);
                $stmt->execute();
                $_SESSION["foto_perfil"] = $foto_final;
            }

            // Actualizar contraseña
            if (!empty($nueva_contrasenia)) {
                $hash = password_hash($nueva_contrasenia, PASSWORD_DEFAULT);
                $stmt = $connection->prepare("UPDATE usuarios SET contrasenia = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $usuario_id);
                $stmt->execute();
                $stmt = $connection->prepare("UPDATE empleado SET contrasenia = ? WHERE usuario_id = ?");
                $stmt->bind_param("si", $hash, $usuario_id);
                $stmt->execute();
            }

            // Actualizar datos contacto
            $stmt = $connection->prepare("UPDATE empleado SET mail = ?, telefono = ?, dni = ? WHERE usuario_id = ?");
            $stmt->bind_param("sssi", $nuevo_email, $nuevo_telefono, $nuevo_dni, $usuario_id);
            $stmt->execute();

            $connection->commit();
            
            // Redirigimos
            header("Location: " . $ruta_home . "?pagina=personal&mensaje=actualizado");
            exit;

        } catch (Exception $e) {
            $connection->rollback();
            $errores[] = "Hubo un error interno al guardar los datos: " . $e->getMessage();
        }
    }
}

$ruta_foto = BASE_URL . "/assets/img/default_user.jpg"; 
if (!empty($empleado['foto_perfil']) && file_exists(__DIR__ . "/../../../uploads/fotos_perfil/" . $empleado['foto_perfil'])) {
    $ruta_foto = BASE_URL . "/uploads/fotos_perfil/" . $empleado['foto_perfil'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar perfil</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
    <style>
        .perfil-img-container { text-align: center; margin-bottom: 20px; }
        .perfil-img { 
            width: 120px; height: 120px; 
            border-radius: 50%; object-fit: cover; 
            border: 3px solid #ddd; 
        }
        .file-upload-label {
            display: block; margin-top: 10px; color: #007bff; cursor: pointer; font-size: 0.9em; text-decoration: underline;
        }
        input[type="file"] { display: none; } 
    </style>
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar perfil</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p style="color: red; text-align: center;"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="perfil-img-container">
                <img src="<?= $ruta_foto ?>" alt="Foto Perfil" class="perfil-img" id="previewImg">
                <label for="foto_input" class="file-upload-label">Cambiar foto de perfil</label>
                <input type="file" name="foto_perfil" id="foto_input" accept="image/*" onchange="previewFile()">
            </div>
            <label>Nombre de usuario</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($_POST["nombre"] ?? $empleado["nombre_usuario"]) ?>">
            <label>Correo electrónico</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST["email"] ?? $empleado["mail"]) ?>">
            <label>Teléfono</label>
            <input type="text" name="telefono" value="<?= htmlspecialchars($_POST["telefono"] ?? $empleado["telefono"]) ?>">
            <label>DNI</label>
            <input type="text" name="dni" value="<?= htmlspecialchars($_POST["dni"] ?? $empleado["dni"]) ?>">
            <label>Contraseña</label>
            <input type="password" name="contrasenia" placeholder="Nueva contraseña (opcional)">
            <div class="botones">
                <button type="submit">Guardar cambios</button>
            </div>
            <div style="margin-top: 10px;">
                <a href="<?php echo $ruta_home; ?>?pagina=personal" class="back_button">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    function previewFile() {
        const preview = document.getElementById('previewImg');
        const file = document.getElementById('foto_input').files[0];
        const reader = new FileReader();

        reader.addEventListener("load", function () {
            preview.src = reader.result;
        }, false);

        if (file) {
            reader.readAsDataURL(file);
        }
    }
</script>
</body>
</html>