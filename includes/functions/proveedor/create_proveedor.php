<?php
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$errores = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"]);
    $nif_dni = trim($_POST["nif_dni"]);
    $poblacion = trim($_POST["poblacion"]);
    $direccion = trim($_POST["direccion"]);
    $mail = trim($_POST["mail"]);
    $telefono = trim($_POST["telefono"]);

    // Validaciones
    if (empty($nombre) || empty($nif_dni) || empty($direccion) || empty($mail) || empty($telefono)) {
        $errores[] = "Nombre, DNI, dirección, correo electrónico y teléfono son obligatorios.";
    }

    if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido.";
    }

    if (empty($errores)) {
        $stmt = $connection->prepare("
            INSERT INTO proveedores_clientes (nombre, nif_dni, poblacion, direccion, mail, telefono, tipo)
            VALUES (?, ?, ?, ?, ?, ?, 'proveedor')
        ");
        $stmt->bind_param("ssssss", $nombre, $nif_dni, $poblacion, $direccion, $mail, $telefono);

        if ($stmt->execute()) {
            header("Location: /ERP/modules/home/empleado_home.php?pagina=proveedores&mensaje=proveedor_creado");
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
    <title>Nuevo Cliente</title>
    <link rel="stylesheet" href="/ERP/assets/css/style_edit_empleado.css">
</head>
<body>
<div class="fondo">
    <div class="tarjeta">
        <h2>Registrar nuevo cliente</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Nombre</label>
            <input type="text" name="nombre" required>

            <label>NIF</label>
            <input type="text" name="nif_dni" required>

            <label>Población</label>
            <input type="text" name="poblacion">

            <label>Dirección</label>
            <input type="text" name="direccion" required>

            <label>Correo electrónico</label required>
            <input type="email" name="mail">

            <label>Teléfono</label>
            <input type="text" name="telefono" required>

            <div class="botones">
                <button type="submit">Crear proveedor</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>