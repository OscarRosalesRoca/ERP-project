<?php
require_once("../../connection.php");
require_once("../../auth.php");

$errores = [];
$proveedores = [];
$almacenes = [];

// Obtener los proveedores desde la base de datos
$stmt = $connection->prepare("SELECT nombre FROM proveedores_clientes WHERE tipo = 'proveedor'");
$stmt->execute();
$resultado_proveedores = $stmt->get_result();

if ($resultado_proveedores->num_rows > 0) {
    while ($row = $resultado_proveedores->fetch_assoc()) {
        $proveedores[] = $row["nombre"];
    }
    $stmt->close();
} else {
    $errores[] = "No hay proveedores disponibles.";
}

// Obtener los almacenes desde la base de datos
$stmt = $connection->prepare("SELECT cod_almacen, ubicacion FROM almacen");
$stmt->execute();
$resultado_almacenes = $stmt->get_result();

if ($resultado_almacenes->num_rows > 0) {
    while ($row = $resultado_almacenes->fetch_assoc()) {
        $almacenes[] = $row;
    }
    $stmt->close();
} else {
    $errores[] = "No hay almacenes disponibles.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"]);
    $iva = floatval($_POST["iva"]);
    $precio_compra = floatval($_POST["precio_compra"]);
    $precio_venta = floatval($_POST["precio_venta"]);
    $nombre_proveedor = trim($_POST["nombre_proveedor"]);
    $cod_almacen = intval($_POST["cod_almacen"]);
    $cantidad = intval($_POST["cantidad"]);

    if (
        empty($nombre) || $iva < 0 || $precio_compra < 0 || $precio_venta < 0 ||
        empty($nombre_proveedor) || $cod_almacen <= 0 || $cantidad < 0
    ) {
        $errores[] = "Todos los campos son obligatorios y deben tener valores válidos.";
    }

    if (empty($errores)) {
        // Consultar si el proveedor existe
        $stmt = $connection->prepare("SELECT cod_actor FROM proveedores_clientes WHERE nombre = ? AND tipo = 'proveedor'");
        $stmt->bind_param("s", $nombre_proveedor);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 0) {
            $errores[] = "El proveedor '$nombre_proveedor' no existe o no es un proveedor.";
        } else {
            $row = $resultado->fetch_assoc();
            $cod_proveedor = $row["cod_actor"];

            $activo = $cantidad > 0 ? 1 : 0;

            // Insertar el producto
            $stmt = $connection->prepare("INSERT INTO producto_servicio (nombre, iva, precio_venta, activo) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                die("Error al preparar la inserción en producto_servicio: " . $connection->error);
            }
            $stmt->bind_param("sddi", $nombre, $iva, $precio_venta, $activo);
            if ($stmt->execute()) {

                $cod_producto = $connection->insert_id;

                // Insertar en la tabla producto_proveedor
                $stmt = $connection->prepare("INSERT INTO producto_proveedor (cod_producto, cod_actor, nombre_proveedor_snapshot, precio_compra) VALUES (?, ?, ?, ?)");

                if (!$stmt) {
                    die("Error al preparar la inserción en producto_proveedor: " . $connection->error);
                }
                $stmt->bind_param("iisd", $cod_producto, $cod_proveedor, $nombre_proveedor, $precio_compra);

                if ($stmt->execute()) {

                    // Insertar en la tabla almacen_producto_servicio
                    $stmt = $connection->prepare("INSERT INTO almacen_producto_servicio (cod_almacen, cod_producto, cantidad) VALUES (?, ?, ?)");

                    if (!$stmt) {
                        die("Error al preparar la inserción en almacen_producto_servicio: " . $connection->error);
                    }

                    $stmt->bind_param("iii", $cod_almacen, $cod_producto, $cantidad);

                    if ($stmt->execute()) {
                        header("Location: /ERP/modules/home/empleado_home.php?pagina=productos&mensaje=producto_creado");
                        exit;
                    } else {
                        $errores[] = "Error al insertar en almacen_producto_servicio: " . $connection->error;
                    }
                } else {
                    $errores[] = "Error al insertar en producto_proveedor: " . $connection->error;
                }
            } else {
                $errores[] = "Error al insertar el producto: " . $connection->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Producto</title>
    <link rel="stylesheet" href="/ERP/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Registrar nuevo producto</h2>

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

            <label>IVA</label>
            <input type="number" step="0.01" name="iva" required>

            <label>Precio de compra</label>
            <input type="number" step="0.01" name="precio_compra" required>

            <label>Precio de venta</label>
            <input type="number" step="0.01" name="precio_venta" required>

            <label>Nombre del proveedor</label>
            <?php if (empty($proveedores)): ?>
                <p class="errores">⚠ No hay proveedores registrados en el sistema.</p>
            <?php else: ?>
                <select name="nombre_proveedor" required>
                    <option value="">-- Selecciona un proveedor --</option>
                    <?php foreach ($proveedores as $prov): ?>
                        <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label>Almacén</label>
            <?php if (empty($almacenes)): ?>
                <p class="errores">⚠ No hay almacenes registrados en el sistema.</p>
            <?php else: ?>
                <select name="cod_almacen" required>
                    <option value="">-- Selecciona un almacén --</option>
                    <?php foreach ($almacenes as $almacen): ?>
                        <option value="<?= $almacen['cod_almacen'] ?>"><?= htmlspecialchars($almacen['ubicacion']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label>Cantidad</label>
            <input type="number" name="cantidad" min="0" required>

            <div class="botones">
                <button type="submit" <?php echo (empty($proveedores) || empty($almacenes)) ? 'disabled' : ''; ?>>Crear producto</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>