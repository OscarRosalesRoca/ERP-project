<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

// Inicializamos para la persistencia
$ubicacion = "";
$errores = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ubicacion = trim($_POST["ubicacion"]);

    // Validaciones
    if (empty($ubicacion)) {
        $errores[] = "La ubicación es obligatoria.";
    }

    // Detección de duplicados
    if (empty($errores)) {
        $campos_repetidos = [];
        
        // Comprobamos si la ubicación ya existe
        $check = $connection->prepare("SELECT cod_almacen FROM almacen WHERE ubicacion = ?");
        $check->bind_param("s", $ubicacion);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $campos_repetidos[] = "ubicación";
        }
        $check->close();

        if (count($campos_repetidos) > 0) {
            $lista = implode(" y ", $campos_repetidos);
            $errores[] = "Los datos que ha introducido ($lista) ya están registrados y por lo tanto no son válidos.";
        }
    }

    // Insertar si no hay errores
    if (empty($errores)) {
        $stmt = $connection->prepare("INSERT INTO almacen (ubicacion) VALUES (?)");
        $stmt->bind_param("s", $ubicacion);

        if ($stmt->execute()) {
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=almacenes&mensaje=almacen_creado");
            exit;
        } else {
            $errores[] = "Error al insertar el almacén: " . $connection->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Almacén</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Registrar nuevo almacén</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Ubicación</label>
            <input type="text" name="ubicacion" value="<?= htmlspecialchars($ubicacion) ?>" required>

            <div class="botones">
                <button type="submit">Crear Almacén</button>
            </div>
            <div style="margin-top: 10px;">
                <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=almacenes" class="back_button">Cancelar</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>