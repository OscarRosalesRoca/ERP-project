<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$errores = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ubicacion = trim($_POST["ubicacion"]);

    // Validaciones
    if (empty($ubicacion)) {
        $errores[] = "La ubicación es obligatoria.";
    }

    if (empty($errores)) {
        $stmt = $connection->prepare("
            INSERT INTO almacen (ubicacion)
            VALUES (?)
        ");
        $stmt->bind_param("s", $ubicacion);

        if ($stmt->execute()) {
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=almacenes&mensaje=almacen_creado");
            exit;
        } else {
            $errores[] = "Error al insertar el almacen: " . $connection->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Almacen</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Registrar nuevo almacen</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Ubicación</label>
            <input type="text" name="ubicacion" required>

            <div class="botones">
                <button type="submit">Crear Almacen</button>
            </div>
        </form>
        </div>
</div>
</body>
</html>