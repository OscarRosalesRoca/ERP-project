<?php
require_once("../../includes/connection.php");
require_once("../../includes/auth.php");

// Consulta solo clientes
$query = "
    SELECT cod_actor, nombre, nif_dni, poblacion, direccion, telefono, mail 
    FROM proveedores_clientes 
    WHERE tipo = 'proveedor'
    ORDER BY nombre ASC
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
    <title>Proveedores</title>
    <link rel="stylesheet" href="/ERP/assets/css/general_style_side_bar_menu.css">
</head>
<body>
<div class="clientes-container">
    <h2>Proveedores</h2>

    <div class="nuevo-cliente">
        <a href="/ERP/includes/functions/proveedor/create_proveedor.php">+ Nuevo proveedor</a>
    </div>

    <?php if ($resultado && $resultado->num_rows > 0): ?>
        <table class="tabla-clientes">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>NIF/DNI</th>
                    <th>Población</th>
                    <th>Dirección</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Editar</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row["cod_actor"]) ?></td>
                        <td><?= htmlspecialchars($row["nombre"]) ?></td>
                        <td><?= htmlspecialchars($row["nif_dni"]) ?></td>
                        <td><?= htmlspecialchars($row["poblacion"]) ?></td>
                        <td><?= htmlspecialchars($row["direccion"]) ?></td>
                        <td><?= htmlspecialchars($row["telefono"]) ?></td>
                        <td><?= htmlspecialchars($row["mail"]) ?></td>
                        <td class="editar"><a href="/ERP/includes/functions/proveedor/edit_delete_proveedor.php?cod=<?= urlencode($row["cod_actor"]) ?>">Editar</a></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="sin-resultados">
            <p>No hay proveedores registrados aún.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>