<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php"); 

header('Content-Type: application/json');

$productos_venta = [];

if ($connection) {
    $query = "
        SELECT DISTINCT
            ps.cod_producto,
            ps.nombre,
            ps.precio_venta,
            ps.iva 
        FROM producto_servicio ps
        JOIN almacen_producto_servicio aps ON ps.cod_producto = aps.cod_producto
        WHERE ps.activo = TRUE AND aps.cantidad > 0
        ORDER BY ps.nombre ASC
    ";

    $stmt = $connection->prepare($query);

    if ($stmt) {
        if ($stmt->execute()) {
            $resultado_productos = $stmt->get_result();
            while ($producto = $resultado_productos->fetch_assoc()) {
                //Para cada producto obtener los almacenes donde hay stock y la cantidad
                $producto['almacenes_con_stock'] = [];
                $stmt_stock_almacen = $connection->prepare(
                    "SELECT a.cod_almacen, a.ubicacion, aps.cantidad 
                    FROM almacen_producto_servicio aps
                    JOIN almacen a ON aps.cod_almacen = a.cod_almacen
                    WHERE aps.cod_producto = ? AND aps.cantidad > 0
                    ORDER BY a.ubicacion ASC"
                );
                if($stmt_stock_almacen){
                    $stmt_stock_almacen->bind_param("i", $producto['cod_producto']);
                    $stmt_stock_almacen->execute();
                    $res_stock = $stmt_stock_almacen->get_result();
                    while($stock_info = $res_stock->fetch_assoc()){
                        $producto['almacenes_con_stock'][] = $stock_info;
                    }
                    $stmt_stock_almacen->close();
                }
                //Solo añadir el producto si tiene al menos un almacén con stock
                if (!empty($producto['almacenes_con_stock'])) {
                    $productos_venta[] = $producto;
                }
            }
        } else {
            $productos_venta = ['error' => 'Error al ejecutar la consulta de productos para venta: ' . $stmt->error];
        }
        $stmt->close();
    } else {
        $productos_venta = ['error' => 'Error al preparar la consulta de productos para venta: ' . $connection->error];
    }
} else {
    $productos_venta = ['error' => 'No se pudo establecer la conexión a la base de datos.'];
}

echo json_encode($productos_venta);
exit;
?>