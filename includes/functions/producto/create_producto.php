<?php
require_once("../../connection.php");
require_once("../../auth.php");

$errores = [];
$proveedores = [];

// Obtener los proveedores desde la base de datos
$stmt_proveedores = $connection->prepare("SELECT cod_actor, nombre FROM proveedores_clientes WHERE tipo = 'proveedor' ORDER BY nombre ASC");
if ($stmt_proveedores) {
    $stmt_proveedores->execute();
    $resultado_proveedores = $stmt_proveedores->get_result();
    if ($resultado_proveedores->num_rows > 0) {
        while ($row = $resultado_proveedores->fetch_assoc()) {
            $proveedores[] = $row; // Guardar cod_actor y nombre
        }
    } else {
        $errores[] = "No hay proveedores disponibles. Por favor, registre al menos un proveedor.";
    }
    $stmt_proveedores->close();
} else {
    $errores[] = "Error al preparar la consulta de proveedores: " . $connection->error;
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"]);
    $iva = isset($_POST["iva"]) ? floatval($_POST["iva"]) : null; // Permitir null si no se envía o está vacío
    $precio_compra = isset($_POST["precio_compra"]) ? floatval($_POST["precio_compra"]) : null;
    $precio_venta = isset($_POST["precio_venta"]) ? floatval($_POST["precio_venta"]) : null;
    $cod_proveedor_seleccionado = isset($_POST["cod_proveedor"]) ? intval($_POST["cod_proveedor"]) : 0;

    // Validaciones
    if (empty($nombre)) {
        $errores[] = "El nombre del producto es obligatorio.";
    }
    if ($iva === null || $iva < 0) {
        $errores[] = "El IVA debe ser un valor numérico igual o mayor a 0.";
    }
    if ($precio_compra === null || $precio_compra < 0) {
        $errores[] = "El precio de compra debe ser un valor numérico igual o mayor a 0.";
    }
    if ($precio_venta === null || $precio_venta < 0) {
        $errores[] = "El precio de venta debe ser un valor numérico igual o mayor a 0.";
    }
    if ($cod_proveedor_seleccionado <= 0) {
        $errores[] = "Debe seleccionar un proveedor.";
    }
    
    if ($precio_venta !== null && $precio_compra !== null && $precio_venta < $precio_compra) {
        $errores[] = "El precio de venta no puede ser inferior al precio de compra.";
    }

    $nombre_proveedor_snapshot = '';
    if ($cod_proveedor_seleccionado > 0) {
        $stmt_get_proveedor = $connection->prepare("SELECT nombre FROM proveedores_clientes WHERE cod_actor = ? AND tipo = 'proveedor'");
        if ($stmt_get_proveedor) {
            $stmt_get_proveedor->bind_param("i", $cod_proveedor_seleccionado);
            $stmt_get_proveedor->execute();
            $res_prov_snapshot = $stmt_get_proveedor->get_result();
            if ($res_prov_snapshot->num_rows > 0) {
                $nombre_proveedor_snapshot = $res_prov_snapshot->fetch_assoc()['nombre'];
            } else {
                $errores[] = "El proveedor seleccionado no es válido.";
            }
            $stmt_get_proveedor->close();
        } else {
            $errores[] = "Error al obtener nombre del proveedor.";
        }
    }


    if (empty($errores)) {
        $connection->begin_transaction(); // Iniciar transacción

        try {
            // Insertar el producto en producto_servicio
            // activo se establece en 0 (FALSE) por defecto, ya que no hay stock inicial
            // No se asigna almacén ni stock aquí
            $stmt_insert_producto = $connection->prepare("INSERT INTO producto_servicio (nombre, iva, precio_venta, activo) VALUES (?, ?, ?, FALSE)");
            if (!$stmt_insert_producto) {
                throw new Exception("Error al preparar la inserción en producto_servicio: " . $connection->error);
            }
            $stmt_insert_producto->bind_param("sdd", $nombre, $iva, $precio_venta);
            if (!$stmt_insert_producto->execute()) {
                throw new Exception("Error al insertar el producto: " . $stmt_insert_producto->error);
            }
            $cod_producto_nuevo = $connection->insert_id;
            $stmt_insert_producto->close();

            // Insertar en la tabla producto_proveedor
            $stmt_insert_prod_prov = $connection->prepare("INSERT INTO producto_proveedor (cod_producto, cod_actor, nombre_proveedor_snapshot, precio_compra) VALUES (?, ?, ?, ?)");
            if (!$stmt_insert_prod_prov) {
                throw new Exception("Error al preparar la inserción en producto_proveedor: " . $connection->error);
            }
            $stmt_insert_prod_prov->bind_param("iisd", $cod_producto_nuevo, $cod_proveedor_seleccionado, $nombre_proveedor_snapshot, $precio_compra);
            if (!$stmt_insert_prod_prov->execute()) {
                throw new Exception("Error al insertar en producto_proveedor: " . $stmt_insert_prod_prov->error);
            }
            $stmt_insert_prod_prov->close();

            $connection->commit();
            header("Location: /ERP/modules/home/empleado_home.php?pagina=productos&mensaje=producto_creado_exitosamente");
            exit;

        } catch (Exception $e) {
            $connection->rollback(); // Revertir transacción en caso de error
            $errores[] = $e->getMessage();
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
        <h2>Registrar Nuevo Producto</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="create_producto.php">
            <label for="nombre">Nombre del Producto:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>" required>

            <label for="iva">IVA (%):</label>
            <input type="number" id="iva" name="iva" step="0.01" value="<?php echo isset($_POST['iva']) ? htmlspecialchars($_POST['iva']) : ''; ?>" required>

            <label for="precio_compra">Precio de Compra:</label>
            <input type="number" id="precio_compra" name="precio_compra" step="0.01" value="<?php echo isset($_POST['precio_compra']) ? htmlspecialchars($_POST['precio_compra']) : ''; ?>" required>

            <label for="precio_venta">Precio de Venta:</label>
            <input type="number" id="precio_venta" name="precio_venta" step="0.01" value="<?php echo isset($_POST['precio_venta']) ? htmlspecialchars($_POST['precio_venta']) : ''; ?>" required>

            <label for="cod_proveedor">Proveedor:</label>
            <?php if (empty($proveedores) && empty($errores)): // Si no hay errores de carga pero sí de falta de proveedores ?>
                <p class="errores">⚠ No hay proveedores registrados. Por favor, registre uno antes de crear productos.</p>
            <?php elseif (!empty($proveedores)): ?>
                <select name="cod_proveedor" id="cod_proveedor" required>
                    <option value="">-- Selecciona un proveedor --</option>
                    <?php foreach ($proveedores as $prov): ?>
                        <option value="<?php echo $prov['cod_actor']; ?>" <?php echo (isset($_POST['cod_proveedor']) && $_POST['cod_proveedor'] == $prov['cod_actor']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <div class="botones">
                <button type="submit" <?php echo (empty($proveedores)) ? 'disabled' : ''; ?>>Crear Producto</button>
                <a href="/ERP/modules/home/empleado_home.php?pagina=productos" class="boton_cancelar">Cancelar</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>