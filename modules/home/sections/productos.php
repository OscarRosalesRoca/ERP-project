<?php
require_once("../../includes/connection.php");
require_once("../../includes/auth.php");

// Consulta solo clientes
$query = "
    SELECT cod_producto, nombre, iva, precio_compra, precio_venta 
    FROM producto_servicio
    ORDER BY cod_producto ASC
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
        <a href="crear_general.php">+ Nuevo producto</a>
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
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <tr>
                    cod_producto, nombre, iva, precio_compra, precio_venta 
                        <td><?= htmlspecialchars($row["cod_producto"]) ?></td>
                        <td><?= htmlspecialchars($row["nombre"]) ?></td>
                        <td><?= htmlspecialchars($row["iva"]) ?></td>
                        <td><?= htmlspecialchars($row["precio_compra"]) ?></td>
                        <td><?= htmlspecialchars($row["precio_venta"]) ?></td>
                        <td class="editar"><a href="editar_producto.php?cod=<?= urlencode($row["cod_producto"]) ?>">Editar</a></td>
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