<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php"); 

header('Content-Type: application/json'); // Indicar que la respuesta será JSON

$cod_proveedor = isset($_GET['cod_proveedor']) ? intval($_GET['cod_proveedor']) : 0;
$productos = [];

if ($cod_proveedor > 0) {
    if ($connection) {
        // Consultar productos activos asociados al proveedor 
        // y que tengan un precio de compra definido en producto_proveedor.
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
    } else {
        $productos = ['error' => 'No se pudo establecer la conexión a la base de datos.'];
    }
} else {
    $productos = ['error' => 'Código de proveedor no proporcionado o inválido.'];
}

echo json_encode($productos);
exit;
?>