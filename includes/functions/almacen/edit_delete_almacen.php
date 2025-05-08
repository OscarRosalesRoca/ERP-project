<?php
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$cod_almacen = $_GET["cod"] ?? null;

if (!$cod_almacen) {
    echo "<p>Error: almacen no especificado.</p>";
    exit;
}

// Obtener datos actuales del almacen
$query = "SELECT * FROM almacen WHERE cod_almacen = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $cod_almacen);
$stmt->execute();
$resultado = $stmt->get_result();
$almacen = $resultado->fetch_assoc();

if (!$almacen) {
    echo "<p>Error: almacen no encontrado.</p>";
    exit;
}

$errores = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_almacen"])) {
    // Eliminar almacen
    $stmt = $connection->prepare("DELETE FROM almacen WHERE cod_almacen = ?");
    $stmt->bind_param("i", $cod_almacen);
    $stmt->execute();

    header("Location: /ERP/modules/home/empleado_home.php?pagina=almacenes");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ubicacion = trim($_POST["ubicacion"]);

    // Si no hay errores, procesamos actualizaciones
    if (empty($errores)) {
        // Nombre: solo actualizar si se ha cambiado
        if (!empty($ubicacion) && $ubicacion !== $almacen["ubicacion"]) {
            $stmt = $connection->prepare("UPDATE almacen SET ubicacion = ? WHERE cod_almacen = ?");
            $stmt->bind_param("si", $ubicacion, $cod_almacen);
            $stmt->execute();
        }
    
        header("Location: /ERP/modules/home/empleado_home.php?pagina=almacenes");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar almacen</title>
    <link rel="stylesheet" href="/ERP/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar almacen</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Ubicación</label>
            <input type="text" name="ubicacion" placeholder="<?= htmlspecialchars($almacen["ubicacion"]) ?>">

            <div class="botones">
                <button type="submit">Guardar cambios</button>
                <p>o</p>
                <button type="submit" name="eliminar_almacen" class="eliminar_boton" onclick="return confirm('¿Eliminar almacen? Esta acción no se puede deshacer.')">
                    <p>Eliminar almacen</p>
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>