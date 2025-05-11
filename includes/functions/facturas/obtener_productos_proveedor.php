<?php
// Ruta al archivo de conexión, ajusta según sea necesario.
// Si este script está en /ERP/includes/functions/facturas/
// y connection.php está en /ERP/includes/
require_once("../../connection.php"); 
// No necesitas auth.php aquí a menos que quieras restringir el acceso a este endpoint.

header('Content-Type: application/json'); // Indicar que la respuesta será JSON

$cod_proveedor = isset($_GET['cod_proveedor']) ? intval($_GET['cod_proveedor']) : 0;
$productos = [];

if ($cod_proveedor > 0) {
    if ($connection) {
        // Consultar productos activos (o todos, según tu lógica) asociados al proveedor
        // y que tengan un precio de compra definido en producto_proveedor.
        // También nos aseguramos de que el producto esté activo en producto_servicio
        // (aunque para compras, podrías querer comprar incluso si está inactivo para reactivarlo)
        // Por ahora, asumimos que solo se pueden comprar productos que ya existen y están asociados al proveedor.
        $query = "
            SELECT 
                ps.cod_producto, 
                ps.nombre,
                pp.precio_compra 
            FROM producto_servicio ps
            JOIN producto_proveedor pp ON ps.cod_producto = pp.cod_producto
            WHERE pp.cod_actor = ? 
            ORDER BY ps.nombre ASC
        ";
        // Si quieres filtrar por ps.activo = TRUE, añade: AND ps.activo = TRUE

        $stmt = $connection->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $cod_proveedor);
            if ($stmt->execute()) {
                $resultado = $stmt->get_result();
                while ($row = $resultado->fetch_assoc()) {
                    // Asegurarse de que precio_compra no sea null o convertirlo a 0.00 si lo es
                    $row['precio_compra'] = $row['precio_compra'] !== null ? floatval($row['precio_compra']) : 0.00;
                    $productos[] = $row;
                }
            } else {
                $productos = ['error' => 'Error al ejecutar la consulta de productos: ' . $stmt->error];
            }
            $stmt->close();
        } else {
            $productos = ['error' => 'Error al preparar la consulta de productos: ' . $connection->error];
        }
        // $connection->close(); // No cierres la conexión si otros scripts la pueden necesitar.
    } else {
        $productos = ['error' => 'No se pudo establecer la conexión a la base de datos.'];
    }
} else {
    $productos = ['error' => 'Código de proveedor no proporcionado o inválido.'];
}

echo json_encode($productos);
exit;
?>
