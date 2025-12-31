<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

// Inicializamos variables para mantener los datos en el formulario (persistencia)
$nombre = $nif_dni = $poblacion = $direccion = $mail = $telefono = "";
$errores = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"]);
    $nif_dni = trim($_POST["nif_dni"]);
    $poblacion = trim($_POST["poblacion"]);
    $direccion = trim($_POST["direccion"]);
    $mail = trim($_POST["mail"]);
    $telefono = trim($_POST["telefono"]);

    // Validaciones básicas
    if (empty($nombre) || empty($nif_dni) || empty($direccion) || empty($mail) || empty($telefono)) {
        $errores[] = "Nombre, DNI, dirección, correo electrónico y teléfono son obligatorios.";
    }

    if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido.";
    }

    // Detección de duplicados
    if (empty($errores)) {
        $campos_repetidos = [];
        
        // Verificamos si ya existe un registro con ese dni, mail o teléfono
        $check = $connection->prepare("SELECT nif_dni, mail, telefono FROM proveedores_clientes WHERE nif_dni = ? OR mail = ? OR telefono = ?");
        $check->bind_param("sss", $nif_dni, $mail, $telefono);
        $check->execute();
        $res = $check->get_result();

        while ($fila = $res->fetch_assoc()) {
            if (strtolower($fila['nif_dni']) === strtolower($nif_dni)) {
                $campos_repetidos[] = "DNI";
            }
            if (strtolower($fila['mail']) === strtolower($mail)) {
                $campos_repetidos[] = "correo electrónico";
            }
            if ($fila['telefono'] === $telefono) {
                $campos_repetidos[] = "teléfono";
            }
        }
        $check->close();

        if (count($campos_repetidos) > 0) {
            $unique_errors = array_unique($campos_repetidos);
            $lista = implode(" y ", $unique_errors);
            
            // Mensaje estandarizado
            $errores[] = "Los datos que ha introducido ($lista) ya están registrados y por lo tanto no son válidos.";
        }
    }

    // Insertar si no hay errores
    if (empty($errores)) {
        $stmt = $connection->prepare("
            INSERT INTO proveedores_clientes (nombre, nif_dni, poblacion, direccion, mail, telefono, tipo)
            VALUES (?, ?, ?, ?, ?, ?, 'proveedor')
        ");
        $stmt->bind_param("ssssss", $nombre, $nif_dni, $poblacion, $direccion, $mail, $telefono);

        if ($stmt->execute()) {
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=proveedores&mensaje=proveedor_creado");
            exit;
        } else {
            $errores[] = "Error al insertar el proveedor: " . $connection->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Proveedor</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Registrar nuevo proveedor</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Nombre</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>

            <label>NIF</label>
            <input type="text" name="nif_dni" value="<?= htmlspecialchars($nif_dni) ?>" required>

            <label>Población</label>
            <input type="text" name="poblacion" value="<?= htmlspecialchars($poblacion) ?>">

            <label>Dirección</label>
            <input type="text" name="direccion" value="<?= htmlspecialchars($direccion) ?>" required>

            <label>Correo electrónico</label>
            <input type="email" name="mail" value="<?= htmlspecialchars($mail) ?>" required>

            <label>Teléfono</label>
            <input type="text" name="telefono" value="<?= htmlspecialchars($telefono) ?>" required>

            <div class="botones">
                <button type="submit">Crear proveedor</button>
            </div>
            <div style="margin-top: 10px;">
                <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=proveedores" class="back_button">Cancelar</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>