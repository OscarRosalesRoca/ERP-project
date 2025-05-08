<?php
require_once("../../includes/connection.php");
require_once("../../includes/auth.php");

// Consulta solo clientes
$query = "
    SELECT cod_almacen, ubicacion 
    FROM almacen
    ORDER BY cod_almacen ASC
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
    <title>Almacenes</title>
    <link rel="stylesheet" href="/ERP/assets/css/modules_style/home_style/sections_style/general_sections_style.css">
</head>
<body>
<div class="general_container">
    <h2>Almacenes</h2>

    <div class="nuevo_general">
        <a href="/ERP/includes/functions/almacen/create_almacen.php">+ Nuevo almacen</a>
    </div>

    <?php if ($resultado && $resultado->num_rows > 0): ?>
        <table class="tabla_general">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Ubicación</th>
                    <th>Editar</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row["cod_almacen"]) ?></td>
                        <td><?= htmlspecialchars($row["ubicacion"]) ?></td>
                        <td class="editar"><a href="/ERP/includes/functions/almacen/edit_delete_almacen.php?cod=<?= urlencode($row["cod_almacen"]) ?>">Editar</a></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="sin_resultados">
            <p>No hay almacenes registrados aún.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>