<?php
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$cod_producto = $_GET["cod"] ?? null;
$mensaje = $_GET["mensaje"] ?? null; // Para mensajes de éxito/error

if (!$cod_producto) {
    // Redirigir si no hay código de producto, o mostrar error.
    header("Location: /ERP/modules/home/empleado_home.php?pagina=productos&error=noproducto");
    exit;
}

$producto_actual = null;
$errores = [];

// Obtener datos actuales del producto y su proveedor asociado
// No necesitamos obtener el almacén aquí ya que no se gestionará desde esta pantalla.
$query_producto = "
    SELECT 
        ps.cod_producto, 
        ps.nombre, 
        ps.iva, 
        ps.precio_venta,
        pp.precio_compra,
        pp.cod_actor AS cod_proveedor_actual,
        pc.nombre AS nombre_proveedor_actual
    FROM producto_servicio ps
    LEFT JOIN producto_proveedor pp ON ps.cod_producto = pp.cod_producto
    LEFT JOIN proveedores_clientes pc ON pp.cod_actor = pc.cod_actor AND pc.tipo = 'proveedor'
    WHERE ps.cod_producto = ?
";
$stmt_producto = $connection->prepare($query_producto);
if (!$stmt_producto) {
    die("Error al preparar la consulta del producto: " . $connection->error);
}
$stmt_producto->bind_param("i", $cod_producto);
$stmt_producto->execute();
$resultado_producto = $stmt_producto->get_result();
$producto_actual = $resultado_producto->fetch_assoc();
$stmt_producto->close();

if (!$producto_actual) {
    header("Location: /ERP/modules/home/empleado_home.php?pagina=productos&error=productonofound");
    exit;
}

// Obtener lista de todos los proveedores para el selector
$lista_proveedores = [];
$stmt_lista_prov = $connection->prepare("SELECT cod_actor, nombre FROM proveedores_clientes WHERE tipo = 'proveedor' ORDER BY nombre ASC");
if ($stmt_lista_prov) {
    $stmt_lista_prov->execute();
    $res_lista_prov = $stmt_lista_prov->get_result();
    if ($res_lista_prov->num_rows > 0) {
        while ($row = $res_lista_prov->fetch_assoc()) {
            $lista_proveedores[] = $row;
        }
    }
    $stmt_lista_prov->close();
} else {
    $errores[] = "Error al cargar la lista de proveedores.";
}

// --- Lógica de Eliminación ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_producto"])) {
    $connection->begin_transaction();
    try {
        // El trigger 'desactivar_producto_sin_stock' se encargará del campo 'activo'
        // si se eliminan todas las entradas de 'almacen_producto_servicio'.
        // Como ahora el stock se maneja en facturas, la eliminación directa aquí
        // podría dejar el producto sin stock y el trigger lo desactivaría.
        // Las FK con ON DELETE CASCADE en producto_proveedor y almacen_producto_servicio (si existiera)
        // se encargarían de las tablas relacionadas.

        // Primero eliminar de producto_proveedor (si la FK no es CASCADE desde producto_servicio)
        // Asumiendo que la FK de producto_proveedor.cod_producto a producto_servicio.cod_producto es ON DELETE CASCADE,
        // este paso no sería estrictamente necesario, pero es más explícito.
        $stmt_del_pp = $connection->prepare("DELETE FROM producto_proveedor WHERE cod_producto = ?");
        if(!$stmt_del_pp) throw new Exception("Error preparando delete producto_proveedor: " . $connection->error);
        $stmt_del_pp->bind_param("i", $cod_producto);
        if(!$stmt_del_pp->execute()) throw new Exception("Error eliminando de producto_proveedor: " . $stmt_del_pp->error);
        $stmt_del_pp->close();

        // Luego eliminar de almacen_producto_servicio (si la FK no es CASCADE)
        $stmt_del_aps = $connection->prepare("DELETE FROM almacen_producto_servicio WHERE cod_producto = ?");
         if(!$stmt_del_aps) throw new Exception("Error preparando delete almacen_producto_servicio: " . $connection->error);
        $stmt_del_aps->bind_param("i", $cod_producto);
        if(!$stmt_del_aps->execute()) throw new Exception("Error eliminando de almacen_producto_servicio: " . $stmt_del_aps->error);
        $stmt_del_aps->close();


        // Finalmente eliminar el producto de producto_servicio
        $stmt_del_ps = $connection->prepare("DELETE FROM producto_servicio WHERE cod_producto = ?");
        if (!$stmt_del_ps) throw new Exception("Error preparando delete producto_servicio: " . $connection->error);
        $stmt_del_ps->bind_param("i", $cod_producto);
        if (!$stmt_del_ps->execute()) throw new Exception("Error eliminando de producto_servicio: " . $stmt_del_ps->error);
        $stmt_del_ps->close();
        
        $connection->commit();
        header("Location: /ERP/modules/home/empleado_home.php?pagina=productos&mensaje=producto_eliminado");
        exit;
    } catch (Exception $e) {
        $connection->rollback();
        $errores[] = "Error al eliminar el producto: " . $e->getMessage();
    }
}

// --- Lógica de Guardar Cambios ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["eliminar_producto"])) {
    $nombre_nuevo = trim($_POST["nombre"]);
    $iva_nuevo = isset($_POST["iva"]) && $_POST["iva"] !== "" ? floatval($_POST["iva"]) : $producto_actual["iva"];
    $precio_compra_nuevo = isset($_POST["precio_compra"]) && $_POST["precio_compra"] !== "" ? floatval($_POST["precio_compra"]) : $producto_actual["precio_compra"];
    $precio_venta_nuevo = isset($_POST["precio_venta"]) && $_POST["precio_venta"] !== "" ? floatval($_POST["precio_venta"]) : $producto_actual["precio_venta"];
    $cod_proveedor_nuevo = isset($_POST["cod_proveedor"]) ? intval($_POST["cod_proveedor"]) : $producto_actual["cod_proveedor_actual"];

    // Validaciones
    if (empty($nombre_nuevo)) {
        $errores[] = "El nombre del producto no puede estar vacío.";
    }
    if ($iva_nuevo < 0) {
        $errores[] = "El IVA no puede ser negativo.";
    }
    if ($precio_compra_nuevo < 0) {
        $errores[] = "El precio de compra no puede ser negativo.";
    }
    if ($precio_venta_nuevo < 0) {
        $errores[] = "El precio de venta no puede ser negativo.";
    }
    if ($precio_venta_nuevo < $precio_compra_nuevo) {
        $errores[] = "El precio de venta no puede ser inferior al precio de compra.";
    }
    if ($cod_proveedor_nuevo <= 0) {
        $errores[] = "Debe seleccionar un proveedor válido.";
    }
    
    $nombre_proveedor_snapshot_nuevo = '';
    if($cod_proveedor_nuevo > 0){
        $stmt_snap = $connection->prepare("SELECT nombre FROM proveedores_clientes WHERE cod_actor = ? AND tipo = 'proveedor'");
        if($stmt_snap){
            $stmt_snap->bind_param("i", $cod_proveedor_nuevo);
            $stmt_snap->execute();
            $res_snap = $stmt_snap->get_result();
            if($res_snap->num_rows > 0){
                $nombre_proveedor_snapshot_nuevo = $res_snap->fetch_assoc()['nombre'];
            } else {
                $errores[] = "El nuevo proveedor seleccionado no es válido.";
            }
            $stmt_snap->close();
        } else {
            $errores[] = "Error al obtener el nombre del nuevo proveedor.";
        }
    }


    if (empty($errores)) {
        $connection->begin_transaction();
        try {
            // Actualizar producto_servicio
            $stmt_update_ps = $connection->prepare("UPDATE producto_servicio SET nombre = ?, iva = ?, precio_venta = ? WHERE cod_producto = ?");
            if(!$stmt_update_ps) throw new Exception("Error preparando update producto_servicio: " . $connection->error);
            $stmt_update_ps->bind_param("sddi", $nombre_nuevo, $iva_nuevo, $precio_venta_nuevo, $cod_producto);
            if(!$stmt_update_ps->execute()) throw new Exception("Error actualizando producto_servicio: " . $stmt_update_ps->error);
            $stmt_update_ps->close();

            // Actualizar producto_proveedor
            // Si el proveedor ha cambiado o el precio de compra ha cambiado
            if ($cod_proveedor_nuevo != $producto_actual["cod_proveedor_actual"] || $precio_compra_nuevo != $producto_actual["precio_compra"]) {
                // Podría ser un UPDATE o un DELETE + INSERT si el producto puede cambiar de proveedor principal.
                // Por simplicidad, asumimos que un producto tiene un proveedor principal y se actualiza.
                // Si un producto pudiera tener múltiples proveedores, la lógica sería más compleja aquí.
                // Para este ERP, parece que un producto está ligado a un proveedor para su precio de compra.
                $stmt_update_pp = $connection->prepare("UPDATE producto_proveedor SET cod_actor = ?, nombre_proveedor_snapshot = ?, precio_compra = ? WHERE cod_producto = ?");
                if(!$stmt_update_pp) throw new Exception("Error preparando update producto_proveedor: " . $connection->error);
                $stmt_update_pp->bind_param("isdi", $cod_proveedor_nuevo, $nombre_proveedor_snapshot_nuevo, $precio_compra_nuevo, $cod_producto);
                if(!$stmt_update_pp->execute()) throw new Exception("Error actualizando producto_proveedor: " . $stmt_update_pp->error);
                $stmt_update_pp->close();
            }
            
            // La gestión de almacén y stock (tabla almacen_producto_servicio y producto_servicio.activo)
            // se realiza a través de las facturas de compra/venta. No se toca aquí.

            $connection->commit();
            // Actualizar $producto_actual para reflejar los cambios en el formulario si no hay redirección inmediata
            $producto_actual['nombre'] = $nombre_nuevo;
            $producto_actual['iva'] = $iva_nuevo;
            $producto_actual['precio_venta'] = $precio_venta_nuevo;
            $producto_actual['precio_compra'] = $precio_compra_nuevo;
            $producto_actual['cod_proveedor_actual'] = $cod_proveedor_nuevo;
            $producto_actual['nombre_proveedor_actual'] = $nombre_proveedor_snapshot_nuevo;
            $mensaje = "Producto actualizado correctamente."; // Mensaje de éxito

        } catch (Exception $e) {
            $connection->rollback();
            $errores[] = "Error al guardar los cambios: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <link rel="stylesheet" href="/ERP/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar Producto: <?php echo htmlspecialchars($producto_actual['nombre'] ?? 'N/A'); ?></h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($mensaje): ?>
            <div class="exito">
                <p><?php echo htmlspecialchars($mensaje); ?></p>
            </div>
        <?php endif; ?>


        <form method="POST" action="edit_delete_producto.php?cod=<?php echo $cod_producto; ?>">
            <label for="nombre">Nombre del Producto:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($producto_actual['nombre'] ?? ''); ?>" required>

            <label for="iva">IVA (%):</label>
            <input type="number" id="iva" name="iva" step="0.01" value="<?php echo htmlspecialchars($producto_actual['iva'] ?? ''); ?>" required>

            <label for="precio_compra">Precio de Compra:</label>
            <input type="number" id="precio_compra" name="precio_compra" step="0.01" value="<?php echo htmlspecialchars($producto_actual['precio_compra'] ?? ''); ?>" required>

            <label for="precio_venta">Precio de Venta:</label>
            <input type="number" id="precio_venta" name="precio_venta" step="0.01" value="<?php echo htmlspecialchars($producto_actual['precio_venta'] ?? ''); ?>" required>

            <label for="cod_proveedor">Proveedor:</label>
            <?php if (empty($lista_proveedores)): ?>
                <p class="errores">⚠ No hay proveedores registrados.</p>
            <?php else: ?>
                <select name="cod_proveedor" id="cod_proveedor" required>
                    <option value="">-- Selecciona un proveedor --</option>
                    <?php foreach ($lista_proveedores as $prov): ?>
                        <option value="<?php echo $prov['cod_actor']; ?>" <?php echo ($prov['cod_actor'] == $producto_actual['cod_proveedor_actual']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <div class="botones">
                <button type="submit" name="guardar_cambios">Guardar Cambios</button>
                <a href="/ERP/modules/home/empleado_home.php?pagina=productos" class="boton_cancelar">Volver a Productos</a>
            </div>
        </form>

        <form method="POST" action="edit_delete_producto.php?cod=<?php echo $cod_producto; ?>" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este producto? Esta acción no se puede deshacer.');">
            <div class="botones">
                <button type="submit" name="eliminar_producto" class="eliminar_boton">Eliminar Producto</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>