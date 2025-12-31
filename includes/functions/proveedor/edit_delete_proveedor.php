<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$cod_actor = $_GET["cod"] ?? null;

if (!$cod_actor) {
    echo "<p>Error: proveedor no especificado.</p>";
    exit;
}

// Obtener datos actuales del proveedor
$query = "SELECT * FROM proveedores_clientes WHERE cod_actor = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $cod_actor);
$stmt->execute();
$resultado = $stmt->get_result();
$proveedor = $resultado->fetch_assoc();

if (!$proveedor) {
    echo "<p>Error: proveedor no encontrado.</p>";
    exit;
}

$errores = [];

// Borrado 
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_proveedor"])) {
    // Eliminar proveedor
    $stmt = $connection->prepare("DELETE FROM proveedores_clientes WHERE cod_actor = ?");
    $stmt->bind_param("i", $cod_actor);
    
    if ($stmt->execute()) {
        header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=proveedores&mensaje=eliminado");
        exit;
    } else {
        $errores[] = "Error al eliminar: " . $connection->error;
    }
}

// Edición
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["eliminar_proveedor"])) {
    $nombre = trim($_POST["nombre"]);
    $dni = trim($_POST["nif_dni"]);
    $poblacion = trim($_POST["poblacion"]);
    $direccion = trim($_POST["direccion"]);
    $mail = trim($_POST["mail"]);
    $telefono = trim($_POST["telefono"]);

    if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido.";
    }

    // Validar duplicados en otros registros
    // IMPORTANTE: AND cod_actor != ? para excluirse a sí mismo
    if (empty($errores)) {
        $campos_repetidos = [];
        
        $sql_check = "SELECT nif_dni, mail, telefono FROM proveedores_clientes WHERE (nif_dni = ? OR mail = ? OR telefono = ?) AND cod_actor != ?";
        $stmt_check = $connection->prepare($sql_check);
        $stmt_check->bind_param("sssi", $dni, $mail, $telefono, $cod_actor);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        while ($fila = $res_check->fetch_assoc()) {
            if (strtolower($fila['nif_dni']) === strtolower($dni)) {
                $campos_repetidos[] = "DNI";
            }
            if (strtolower($fila['mail']) === strtolower($mail)) {
                $campos_repetidos[] = "correo electrónico";
            }
            if ($fila['telefono'] === $telefono) {
                $campos_repetidos[] = "teléfono";
            }
        }
        $stmt_check->close();

        if (count($campos_repetidos) > 0) {
            $unique_errors = array_unique($campos_repetidos);
            $lista = implode(" y ", $unique_errors);
            
            // Mensaje estandarizado
            $errores[] = "Los datos que ha introducido ($lista) ya están registrados y por lo tanto no son válidos.";
        }
    }

    // Actualizar si no hay errores
    if (empty($errores)) {
        $sql_update = "UPDATE proveedores_clientes SET nombre=?, nif_dni=?, poblacion=?, direccion=?, mail=?, telefono=? WHERE cod_actor=?";
        $stmt_update = $connection->prepare($sql_update);
        $stmt_update->bind_param("ssssssi", $nombre, $dni, $poblacion, $direccion, $mail, $telefono, $cod_actor);
        
        if ($stmt_update->execute()) {
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=proveedores&mensaje=actualizado");
            exit;
        } else {
            $errores[] = "Error al actualizar: " . $connection->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar proveedores</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar proveedores</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Nombre</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($_POST["nombre"] ?? $proveedor["nombre"]) ?>" required>

            <label>NIF</label>
            <input type="text" name="nif_dni" value="<?= htmlspecialchars($_POST["nif_dni"] ?? $proveedor["nif_dni"]) ?>" required>

            <label>Población</label>
            <input type="text" name="poblacion" value="<?= htmlspecialchars($_POST["poblacion"] ?? $proveedor["poblacion"]) ?>">

            <label>Dirección</label>
            <input type="text" name="direccion" value="<?= htmlspecialchars($_POST["direccion"] ?? $proveedor["direccion"]) ?>" required>

            <label>Correo electrónico</label>
            <input type="email" name="mail" value="<?= htmlspecialchars($_POST["mail"] ?? $proveedor["mail"]) ?>" required>

            <label>Teléfono</label>
            <input type="text" name="telefono" value="<?= htmlspecialchars($_POST["telefono"] ?? $proveedor["telefono"]) ?>" required>

            <div class="botones">
                <button type="submit">Guardar cambios</button>
                <p>o</p>
                <button type="submit" name="eliminar_proveedor" class="eliminar_boton" onclick="return confirm('¿Eliminar proveedor? Esta acción no se puede deshacer.')">
                    <p>Eliminar proveedor</p>
                </button>
            </div>
            <div style="margin-top: 10px;">
                <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=proveedores" class="back_button">Cancelar</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>