<?php
require_once("../../includes/connection.php");
require_once("../../includes/auth.php");

// Consulta solo clientes
$query = "
    SELECT 
        ps.cod_producto, 
        ps.nombre, 
        ps.iva, 
        pp.precio_compra, 
        ps.precio_venta,
        pc.nombre AS nombre_proveedor
    FROM producto_servicio ps
    JOIN producto_proveedor pp ON ps.cod_producto = pp.cod_producto
    JOIN proveedores_clientes pc ON pp.cod_actor = pc.cod_actor
    ORDER BY ps.cod_producto ASC
";

$resultado = $connection->query($query);

if ($resultado === false) {
    die("<p>Error en la consulta SQL: " . $connection->error . "</p>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos</title>
    <link rel="stylesheet" href="/ERP/assets/css/modules_style/home_style/sections_style/general_sections_style.css">
</head>
<body>
<div class="general_container">
    <h2>Productos</h2>

    <div class="nuevo_general">
        <a href="/ERP/includes/functions/producto/create_producto.php">+ Nuevo producto</a>
    </div>

    <?php if ($resultado && $resultado->num_rows > 0): ?>
        <table class="tabla_general">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>IVA</th>
                    <th>Precio de compra</th>
                    <th>Precio de venta</th>
                    <th>Proveedor</th>
                    <th>Almacen</th> <!-- Añadido -->
                    <th>Stock</th> <!-- Añadido -->
                    <th>Editar</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <?php
                    // Consulta para ubicaciones y cantidades
                    $cod_producto_actual = $row["cod_producto"];
                    $almacen_query = "
                        SELECT a.ubicacion, aps.cantidad
                        FROM almacen_producto_servicio aps
                        JOIN almacen a ON aps.cod_almacen = a.cod_almacen
                        WHERE aps.cod_producto = $cod_producto_actual
                    ";
                    $almacen_result = $connection->query($almacen_query);

                    $ubicaciones = [];
                    $cantidades = [];

                    if ($almacen_result && $almacen_result->num_rows > 0) {
                        while ($almacen = $almacen_result->fetch_assoc()) {
                            $ubicaciones[] = $almacen["ubicacion"];
                            $cantidades[] = $almacen["cantidad"];
                        }
                    }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row["cod_producto"]) ?></td>
                        <td><?= htmlspecialchars($row["nombre"]) ?></td>
                        <td><?= htmlspecialchars($row["iva"]) ?></td>
                        <td><?= htmlspecialchars($row["precio_compra"]) ?></td>
                        <td><?= htmlspecialchars($row["precio_venta"]) ?></td>
                        <td><?= htmlspecialchars($row["nombre_proveedor"]) ?></td>
                        <td><?= htmlspecialchars(implode(", ", $ubicaciones)) ?></td>
                        <td><?= htmlspecialchars(implode(", ", $cantidades)) ?></td>
                        <td class="editar"><a href="/ERP/includes/functions/producto/edit_delete_producto.php?cod=<?= urlencode($row["cod_producto"]) ?>">Editar</a></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="sin_resultados">
            <p>No hay productos registrados aún.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>