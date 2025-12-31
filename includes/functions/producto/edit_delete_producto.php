<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$cod_producto = $_GET["cod"] ?? null;

if (!$cod_producto) {
    header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=productos&error=noproducto");
    exit;
}

$producto_actual = null;
$errores = [];

// Obtener datos actuales del producto
// NOTA: Usamos LEFT JOIN para que cargue el producto aunque no tenga proveedor (caso: huérfano)
$query_producto = "
    SELECT 
        ps.cod_producto, 
        ps.nombre, 
        ps.iva, 
        ps.precio_venta,
        pp.precio_compra,
        pp.cod_actor AS cod_proveedor_actual
    FROM producto_servicio ps
    LEFT JOIN producto_proveedor pp ON ps.cod_producto = pp.cod_producto
    WHERE ps.cod_producto = ?
";
$stmt_producto = $connection->prepare($query_producto);
$stmt_producto->bind_param("i", $cod_producto);
$stmt_producto->execute();
$resultado_producto = $stmt_producto->get_result();
$producto_actual = $resultado_producto->fetch_assoc();
$stmt_producto->close();

if (!$producto_actual) {
    header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=productos&error=productonofound");
    exit;
}

// Obtener proveedores para el select
$lista_proveedores = [];
$stmt_lista_prov = $connection->prepare("SELECT cod_actor, nombre FROM proveedores_clientes WHERE tipo = 'proveedor' ORDER BY nombre ASC");
if ($stmt_lista_prov) {
    $stmt_lista_prov->execute();
    $res_lista_prov = $stmt_lista_prov->get_result();
    while ($row = $res_lista_prov->fetch_assoc()) {
        $lista_proveedores[] = $row;
    }
    $stmt_lista_prov->close();
}

// Borrado
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_producto"])) {
    $connection->begin_transaction();
    try {
        
        $stmt_del = $connection->prepare("DELETE FROM producto_servicio WHERE cod_producto = ?");
        $stmt_del->bind_param("i", $cod_producto);
        
        if ($stmt_del->execute()) {
            $connection->commit();
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=productos&mensaje=producto_eliminado");
            exit;
        } else {
            throw new Exception($stmt_del->error);
        }
        $stmt_del->close();

    } catch (Exception $e) {
        $connection->rollback();
        $errores[] = "Error al eliminar: " . $e->getMessage();
    }
}

// Edición
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["eliminar_producto"])) {
    $nombre_nuevo = trim($_POST["nombre"]);
    $iva_nuevo = ($_POST["iva"] !== "") ? floatval($_POST["iva"]) : -1;
    $precio_compra_nuevo = ($_POST["precio_compra"] !== "") ? floatval($_POST["precio_compra"]) : -1;
    $precio_venta_nuevo = ($_POST["precio_venta"] !== "") ? floatval($_POST["precio_venta"]) : -1;
    $cod_proveedor_nuevo = isset($_POST["cod_proveedor"]) ? intval($_POST["cod_proveedor"]) : 0;

    // Validaciones
    if (empty($nombre_nuevo)) $errores[] = "El nombre del producto no puede estar vacío.";
    if ($iva_nuevo < 0) $errores[] = "El IVA debe ser un valor numérico igual o mayor a 0.";
    if ($precio_compra_nuevo < 0) $errores[] = "El precio de compra debe ser un valor numérico igual o mayor a 0.";
    if ($precio_venta_nuevo < 0) $errores[] = "El precio de venta debe ser un valor numérico igual o mayor a 0.";
    
    if ($precio_venta_nuevo >= 0 && $precio_compra_nuevo >= 0 && $precio_venta_nuevo < $precio_compra_nuevo) {
        $errores[] = "El precio de venta no puede ser inferior al precio de compra.";
    }
    
    if ($cod_proveedor_nuevo <= 0) $errores[] = "Debe seleccionar un proveedor válido.";

    // Validar duplicado de nombre
    if (empty($errores)) {
        $campos_repetidos = [];
        $check = $connection->prepare("SELECT cod_producto FROM producto_servicio WHERE nombre = ? AND cod_producto != ?");
        $check->bind_param("si", $nombre_nuevo, $cod_producto);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $campos_repetidos[] = "nombre del producto";
        }
        $check->close();

        if (count($campos_repetidos) > 0) {
            $lista = implode(" y ", $campos_repetidos);
            $errores[] = "Los datos que ha introducido ($lista) ya están registrados y por lo tanto no son válidos.";
        }
    }

    if (empty($errores)) {
        // Obtener nombre del proveedor snapshot nuevo
        $nombre_prov_snapshot = '';
        $stmt_prov_name = $connection->prepare("SELECT nombre FROM proveedores_clientes WHERE cod_actor = ?");
        $stmt_prov_name->bind_param("i", $cod_proveedor_nuevo);
        $stmt_prov_name->execute();
        $res = $stmt_prov_name->get_result();
        if ($row = $res->fetch_assoc()) {
            $nombre_prov_snapshot = $row['nombre'];
        }
        $stmt_prov_name->close();

        $connection->begin_transaction();
        try {
            // Actualizar datos básicos en producto_servicio
            $stmt1 = $connection->prepare("UPDATE producto_servicio SET nombre = ?, iva = ?, precio_venta = ? WHERE cod_producto = ?");
            $stmt1->bind_param("sddi", $nombre_nuevo, $iva_nuevo, $precio_venta_nuevo, $cod_producto);
            if (!$stmt1->execute()) throw new Exception("Error al actualizar producto: " . $stmt1->error);
            $stmt1->close();

            // ACTUALIZACIÓN ROBUSTA DEL PROVEEDOR (borrar + insertar)
            // Esto soluciona el problema de los productos huérfanos o cambios de proveedor
            
            // Primero borramos la relación antigua (si existe)
            $stmt_del_prov = $connection->prepare("DELETE FROM producto_proveedor WHERE cod_producto = ?");
            $stmt_del_prov->bind_param("i", $cod_producto);
            if (!$stmt_del_prov->execute()) throw new Exception("Error al limpiar proveedor anterior: " . $stmt_del_prov->error);
            $stmt_del_prov->close();

            // Luego insertamos la nueva relación
            $stmt_ins_prov = $connection->prepare("INSERT INTO producto_proveedor (cod_producto, cod_actor, nombre_proveedor_snapshot, precio_compra) VALUES (?, ?, ?, ?)");
            $stmt_ins_prov->bind_param("iisd", $cod_producto, $cod_proveedor_nuevo, $nombre_prov_snapshot, $precio_compra_nuevo);
            if (!$stmt_ins_prov->execute()) throw new Exception("Error al asignar nuevo proveedor: " . $stmt_ins_prov->error);
            $stmt_ins_prov->close();

            $connection->commit();
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=productos&mensaje=actualizado");
            exit;

        } catch (Exception $e) {
            $connection->rollback();
            $errores[] = "Error interno al guardar: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar Producto</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label for="nombre">Nombre del Producto:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? $producto_actual['nombre']); ?>" required>

            <label for="iva">IVA (%):</label>
            <input type="number" id="iva" name="iva" step="0.01" value="<?php echo htmlspecialchars($_POST['iva'] ?? $producto_actual['iva']); ?>" required>

            <label for="precio_compra">Precio de Compra:</label>
            <input type="number" id="precio_compra" name="precio_compra" step="0.01" value="<?php echo htmlspecialchars($_POST['precio_compra'] ?? $producto_actual['precio_compra']); ?>" required>

            <label for="precio_venta">Precio de Venta:</label>
            <input type="number" id="precio_venta" name="precio_venta" step="0.01" value="<?php echo htmlspecialchars($_POST['precio_venta'] ?? $producto_actual['precio_venta']); ?>" required>

            <label for="cod_proveedor">Proveedor:</label>
            <select name="cod_proveedor" id="cod_proveedor" required>
                <option value="">-- Selecciona un proveedor --</option>
                <?php 
                $seleccionado = $_POST['cod_proveedor'] ?? $producto_actual['cod_proveedor_actual'];
                
                foreach ($lista_proveedores as $prov): ?>
                    <option value="<?php echo $prov['cod_actor']; ?>" <?php echo ($seleccionado == $prov['cod_actor']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($prov['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="botones">
                <button type="submit" name="guardar_cambios">Guardar Cambios</button>
                <p>o</p>
                <button type="submit" name="eliminar_producto" class="eliminar_boton" onclick="return confirm('¿Estás seguro de que quieres eliminar este producto? Al hacerlo desaparecerá del stock, pero se mantendrá en el historial de facturas antiguas.')">
                    Eliminar Producto
                </button>
            </div>
            <div style="margin-top: 10px;">
                <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=productos" class="back_button">Cancelar</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>