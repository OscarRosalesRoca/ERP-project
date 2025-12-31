<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

// Inicializamos variables
$nombre = $iva = $precio_compra = $precio_venta = $cod_proveedor_seleccionado = "";
$errores = [];
$proveedores = [];

// Cargar proveedores
$stmt_proveedores = $connection->prepare("SELECT cod_actor, nombre FROM proveedores_clientes WHERE tipo = 'proveedor' ORDER BY nombre ASC");
if ($stmt_proveedores) {
    $stmt_proveedores->execute();
    $resultado_proveedores = $stmt_proveedores->get_result();
    if ($resultado_proveedores->num_rows > 0) {
        while ($row = $resultado_proveedores->fetch_assoc()) {
            $proveedores[] = $row;
        }
    } else {
        $errores[] = "No hay proveedores registrados. Debe crear un proveedor antes de dar de alta productos.";
    }
    $stmt_proveedores->close();
} else {
    $errores[] = "Error técnico al cargar proveedores: " . $connection->error;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"]);
    $iva = $_POST["iva"];
    $precio_compra = $_POST["precio_compra"];
    $precio_venta = $_POST["precio_venta"];
    $cod_proveedor_seleccionado = $_POST["cod_proveedor"] ?? "";

    // Validaciones
    if (empty($nombre)) {
        $errores[] = "El nombre del producto es obligatorio.";
    }
    
    if ($iva === "" || !is_numeric($iva) || floatval($iva) < 0) {
        $errores[] = "El IVA debe ser un número igual o mayor a 0.";
    }
    if ($precio_compra === "" || !is_numeric($precio_compra) || floatval($precio_compra) < 0) {
        $errores[] = "El precio de compra debe ser un número igual o mayor a 0.";
    }
    if ($precio_venta === "" || !is_numeric($precio_venta) || floatval($precio_venta) < 0) {
        $errores[] = "El precio de venta debe ser un número igual o mayor a 0.";
    }
    
    if (is_numeric($precio_venta) && is_numeric($precio_compra) && floatval($precio_venta) < floatval($precio_compra)) {
        $errores[] = "El precio de venta no puede ser inferior al precio de compra.";
    }

    if (empty($cod_proveedor_seleccionado)) {
        $errores[] = "Debe seleccionar un proveedor principal.";
    }

    // Obtener Snapshot Proveedor
    $nombre_proveedor_snapshot = '';
    if (empty($errores)) {
        $stmt_prov = $connection->prepare("SELECT nombre FROM proveedores_clientes WHERE cod_actor = ? AND tipo = 'proveedor'");
        $stmt_prov->bind_param("i", $cod_proveedor_seleccionado);
        $stmt_prov->execute();
        $res_prov = $stmt_prov->get_result();
        if ($fila_prov = $res_prov->fetch_assoc()) {
            $nombre_proveedor_snapshot = $fila_prov['nombre'];
        } else {
            $errores[] = "El proveedor seleccionado no es válido.";
        }
        $stmt_prov->close();
    }

    // Insertar
    if (empty($errores)) {
        $connection->begin_transaction();
        try {
            // Activo = TRUE
            $stmt_prod = $connection->prepare("INSERT INTO producto_servicio (nombre, iva, precio_venta, activo) VALUES (?, ?, ?, TRUE)");
            $stmt_prod->bind_param("sdd", $nombre, $iva, $precio_venta);
            
            if (!$stmt_prod->execute()) {
                throw new Exception("Error al guardar el producto: " . $stmt_prod->error);
            }
            $cod_producto_nuevo = $connection->insert_id;
            $stmt_prod->close();

            $stmt_rel = $connection->prepare("INSERT INTO producto_proveedor (cod_producto, cod_actor, nombre_proveedor_snapshot, precio_compra) VALUES (?, ?, ?, ?)");
            $stmt_rel->bind_param("iisd", $cod_producto_nuevo, $cod_proveedor_seleccionado, $nombre_proveedor_snapshot, $precio_compra);
            
            if (!$stmt_rel->execute()) {
                throw new Exception("Error al asignar el proveedor: " . $stmt_rel->error);
            }
            $stmt_rel->close();

            $connection->commit();
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=productos&mensaje=producto_creado");
            exit;

        } catch (Exception $e) {
            $connection->rollback();
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Registrar Nuevo Producto</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label for="nombre">Nombre del Producto:</label>
            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>

            <label for="iva">IVA (%):</label>
            <input type="number" id="iva" name="iva" step="0.01" value="<?= htmlspecialchars($iva) ?>" required>

            <label for="precio_compra">Precio de Compra:</label>
            <input type="number" id="precio_compra" name="precio_compra" step="0.01" value="<?= htmlspecialchars($precio_compra) ?>" required>

            <label for="precio_venta">Precio de Venta:</label>
            <input type="number" id="precio_venta" name="precio_venta" step="0.01" value="<?= htmlspecialchars($precio_venta) ?>" required>

            <label for="cod_proveedor">Proveedor Principal:</label>
            <?php if (empty($proveedores)): ?>
                <p class="errores">No hay proveedores registrados.</p>
            <?php else: ?>
                <select name="cod_proveedor" id="cod_proveedor" required>
                    <option value="">-- Selecciona un proveedor --</option>
                    <?php foreach ($proveedores as $prov): ?>
                        <option value="<?= $prov['cod_actor']; ?>" <?= ($cod_proveedor_seleccionado == $prov['cod_actor']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($prov['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <div class="botones">
                <button type="submit" <?= (empty($proveedores)) ? 'disabled' : ''; ?>>Crear Producto</button>
            </div>
            <div style="margin-top: 10px;">
                <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=productos" class="back_button">Cancelar</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>