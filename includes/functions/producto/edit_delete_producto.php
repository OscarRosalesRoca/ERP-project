<?php
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$cod_producto = $_GET["cod"] ?? null;

if (!$cod_producto) {
    echo "<p>Error: producto no especificado.</p>";
    exit;
}

// Obtener datos actuales del producto y proveedor
$query = "
    SELECT 
        ps.cod_producto, 
        ps.nombre, 
        ps.iva, 
        pp.precio_compra, 
        ps.precio_venta,
        pc.nombre AS nombre_proveedor,
        pc.cod_actor AS cod_proveedor
    FROM producto_servicio ps
    JOIN producto_proveedor pp ON ps.cod_producto = pp.cod_producto
    JOIN proveedores_clientes pc ON pp.cod_actor = pc.cod_actor
    WHERE ps.cod_producto = ?
";
$stmt = $connection->prepare($query);
if (!$stmt) {
    die("Error al preparar la consulta: " . $connection->error);
}
$stmt->bind_param("i", $cod_producto);
$stmt->execute();
$resultado = $stmt->get_result();
$producto = $resultado->fetch_assoc();

if (!$producto) {
    echo "<p>Error: producto no encontrado.</p>";
    exit;
}

// Obtener lista de proveedores
$proveedores = [];
$res = $connection->query("SELECT cod_actor, nombre FROM proveedores_clientes WHERE tipo = 'proveedor'");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $proveedores[] = $row;
    }
}

$errores = [];

// Verifica si se está intentando eliminar el producto
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_producto"])) {
    // Eliminar producto
    $stmt = $connection->prepare("DELETE FROM producto_servicio WHERE cod_producto = ?");
    if (!$stmt) {
        die("Error al preparar la consulta de eliminación: " . $connection->error);
    }
    $stmt->bind_param("i", $cod_producto);
    $stmt->execute();

    header("Location: /ERP/modules/home/empleado_home.php?pagina=productos");
    exit;
}

// Guardar cambios
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["eliminar_producto"])) {
    $nombre = trim($_POST["nombre"]);
    $iva = isset($_POST["iva"]) && $_POST["iva"] !== "" ? floatval($_POST["iva"]) : null;
    $precio_compra = isset($_POST["precio_compra"]) && $_POST["precio_compra"] !== "" ? floatval($_POST["precio_compra"]) : null;
    $precio_venta = isset($_POST["precio_venta"]) && $_POST["precio_venta"] !== "" ? floatval($_POST["precio_venta"]) : null;
    $nuevo_cod_proveedor = $_POST["proveedor"];

    if (empty($errores)) {
        // Actualizar producto_servicio
        if (!empty($nombre) && $nombre !== $producto["nombre"]) {
            $stmt = $connection->prepare("UPDATE producto_servicio SET nombre = ? WHERE cod_producto = ?");
            if (!$stmt) {
                die("Error al preparar la consulta de actualización de nombre: " . $connection->error);
            }
            $stmt->bind_param("si", $nombre, $cod_producto);
            $stmt->execute();
        }

        if ($iva !== null && $iva !== floatval($producto["iva"])) {
            $stmt = $connection->prepare("UPDATE producto_servicio SET iva = ? WHERE cod_producto = ?");
            if (!$stmt) {
                die("Error al preparar la consulta de actualización de IVA: " . $connection->error);
            }
            $stmt->bind_param("di", $iva, $cod_producto);
            $stmt->execute();
        }

        if ($precio_venta !== null && $precio_venta !== floatval($producto["precio_venta"])) {
            $stmt = $connection->prepare("UPDATE producto_servicio SET precio_venta = ? WHERE cod_producto = ?");
            if (!$stmt) {
                die("Error al preparar la consulta de actualización de precio de venta: " . $connection->error);
            }
            $stmt->bind_param("di", $precio_venta, $cod_producto);
            $stmt->execute();
        }


        if ($precio_compra !== null && $precio_compra !== floatval($producto["precio_compra"])) {
            $stmt = $connection->prepare("UPDATE producto_proveedor SET precio_compra = ? WHERE cod_producto = ? AND cod_actor = ?");
            if (!$stmt) {
                die("Error al preparar la consulta de actualización de precio_compra: " . $connection->error);
            }
            $stmt->bind_param("dii", $precio_compra, $cod_producto, $producto["cod_proveedor"]);
            $stmt->execute();
        }

        // Si se ha seleccionado un nuevo proveedor, actualizamos el proveedor y el nombre_proveedor_snapshot
        if ($nuevo_cod_proveedor !== $producto["cod_proveedor"]) {
            // Obtener el nombre del nuevo proveedor
            $stmt = $connection->prepare("SELECT nombre FROM proveedores_clientes WHERE cod_actor = ?");
            if (!$stmt) {
                die("Error al preparar la consulta para obtener el nombre del proveedor: " . $connection->error);
            }
            $stmt->bind_param("i", $nuevo_cod_proveedor);
            $stmt->execute();
            $res = $stmt->get_result();
            $nuevo_proveedor = $res->fetch_assoc();

            if ($nuevo_proveedor) {
                // Actualizamos cod_actor y nombre_proveedor_snapshot
                $stmt = $connection->prepare("UPDATE producto_proveedor SET cod_actor = ?, nombre_proveedor_snapshot = ? WHERE cod_producto = ?");
                if (!$stmt) {
                    die("Error al preparar la consulta para actualizar proveedor y nombre_proveedor_snapshot: " . $connection->error);
                }
                $stmt->bind_param("isi", $nuevo_cod_proveedor, $nuevo_proveedor["nombre"], $cod_producto);
                $stmt->execute();
            }
        }

        header("Location: /ERP/modules/home/empleado_home.php?pagina=productos");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar producto</title>
    <link rel="stylesheet" href="/ERP/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar producto</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Nombre</label>
            <input type="text" name="nombre" placeholder="<?= htmlspecialchars($producto["nombre"]) ?>">

            <label>IVA</label>
            <input type="number" step="0.01" name="iva" placeholder="<?= htmlspecialchars($producto["iva"]) ?>">

            <label>Precio de compra</label>
            <input type="number" step="0.01" name="precio_compra" placeholder="<?= htmlspecialchars($producto["precio_compra"]) ?>">

            <label>Precio de venta</label>
            <input type="number" step="0.01" name="precio_venta" placeholder="<?= htmlspecialchars($producto["precio_venta"]) ?>">

            <label>Proveedor</label>
            <?php if (count($proveedores) > 0): ?>
                <select name="proveedor" required>
                    <?php foreach ($proveedores as $prov): ?>
                        <option value="<?= $prov['cod_actor'] ?>" <?= $prov['cod_actor'] == $producto["cod_proveedor"] ? "selected" : "" ?>>
                            <?= htmlspecialchars($prov['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <p style="color: red;">No hay proveedores registrados en el sistema.</p>
            <?php endif; ?>

            <div class="botones">
                <button type="submit">Guardar cambios</button>
                <p>o</p>
                <button type="submit" name="eliminar_producto" class="eliminar_boton" onclick="return confirm('¿Eliminar producto? Esta acción no se puede deshacer.')">
                    <p>Eliminar producto</p>
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>