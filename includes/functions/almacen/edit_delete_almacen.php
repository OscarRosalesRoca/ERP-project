<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$cod_almacen = $_GET["cod"] ?? null;

if (!$cod_almacen) {
    echo "<p>Error: almacén no especificado.</p>";
    exit;
}

// Obtener datos actuales del almacén
$query = "SELECT * FROM almacen WHERE cod_almacen = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $cod_almacen);
$stmt->execute();
$resultado = $stmt->get_result();
$almacen = $resultado->fetch_assoc();

if (!$almacen) {
    echo "<p>Error: almacén no encontrado.</p>";
    exit;
}

$errores = [];

// Borrado
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_almacen"])) {
    // Nota: Aquí sería ideal verificar si hay productos en este almacén antes de borrar (Integridad Referencial)
    // De momento lo dejamos como borrado simple.
    $stmt = $connection->prepare("DELETE FROM almacen WHERE cod_almacen = ?");
    $stmt->bind_param("i", $cod_almacen);
    
    if ($stmt->execute()) {
        header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=almacenes&mensaje=eliminado");
        exit;
    } else {
        $errores[] = "Error al eliminar: " . $connection->error;
    }
}

// Edición
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["eliminar_almacen"])) {
    $ubicacion = trim($_POST["ubicacion"]);

    if (empty($ubicacion)) {
        $errores[] = "La ubicación es obligatoria.";
    }

    // Validación de duplicados (excluyendo el propio almacén)
    if (empty($errores)) {
        $campos_repetidos = [];
        
        $sql_check = "SELECT cod_almacen FROM almacen WHERE ubicacion = ? AND cod_almacen != ?";
        $stmt_check = $connection->prepare($sql_check);
        $stmt_check->bind_param("si", $ubicacion, $cod_almacen);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $campos_repetidos[] = "ubicación";
        }
        $stmt_check->close();

        if (count($campos_repetidos) > 0) {
            $lista = implode(" y ", $campos_repetidos);
            $errores[] = "Los datos que ha introducido ($lista) ya están registrados y por lo tanto no son válidos.";
        }
    }

    // Actualizar si no hay errores
    if (empty($errores)) {
        // Solo actualizamos si ha cambiado
        $stmt = $connection->prepare("UPDATE almacen SET ubicacion = ? WHERE cod_almacen = ?");
        $stmt->bind_param("si", $ubicacion, $cod_almacen);
        
        if ($stmt->execute()) {
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=almacenes&mensaje=actualizado");
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
    <title>Editar Almacén</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar almacén</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Ubicación</label>
            <input type="text" name="ubicacion" value="<?= htmlspecialchars($_POST["ubicacion"] ?? $almacen["ubicacion"]) ?>" required>

            <div class="botones">
                <button type="submit">Guardar cambios</button>
                <p>o</p>
                <button type="submit" name="eliminar_almacen" class="eliminar_boton" onclick="return confirm('¿Eliminar almacén? Esta acción no se puede deshacer.')">
                    <p>Eliminar almacén</p>
                </button>
            </div>
            <div style="margin-top: 10px;">
                <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=almacenes" class="back_button">Cancelar</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>