<?php
require_once ("../../includes/connection.php");
require_once ("../../includes/auth.php");

$query = "
    SELECT 
        f.num_factura,
        f.tipo,
        f.cod_empleado,
        e.nombre AS nombre_empleado,
        f.cod_actor,
        pc.nombre AS nombre_actor,
        f.total_factura,
        f.fecha
    FROM facturas f
    LEFT JOIN empleado e ON f.cod_empleado = e.cod_empleado
    LEFT JOIN proveedores_clientes pc ON f.cod_actor = pc.cod_actor
    ORDER BY f.fecha DESC
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
    <title>Historial de actividad</title>
    <link rel="stylesheet" href="/ERP/assets/css/style_historial.css">
</head>
<body>
<div class="historial-container">
    <h2>Historial de actividad</h2>
    <?php if ($resultado && $resultado->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Número de factura</th>
                    <th>Tipo</th>
                    <th>Código de empleado</th>
                    <th>Nombre del empleado</th>
                    <th>Código de actor</th>
                    <th>Nombre del actor</th>
                    <th>Total</th>
                    <th>Fecha</th>
                    <th>Ver más</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row["num_factura"]) ?></td>
                        <td><?= htmlspecialchars($row["tipo"]) ?></td>
                        <td><?= htmlspecialchars($row["cod_empleado"]) ?></td>
                        <td><?= htmlspecialchars($row["nombre_empleado"]) ?></td>
                        <td><?= htmlspecialchars($row["cod_actor"]) ?></td>
                        <td><?= htmlspecialchars($row["nombre_actor"]) ?></td>
                        <td><?= htmlspecialchars($row["total_factura"]) ?> €</td>
                        <td><?= htmlspecialchars($row["fecha"]) ?></td>
                        <td class="ver-mas"><a href="#">Ver más</a></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="sin-resultados">
            <p>No hay actividad registrada aún.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>