<?php
// Ruta: /ERP/modules/home/sections/ver_detalle_factura.php
// o /ERP/includes/functions/facturas/ver_mas_facturas.php según donde lo hayas guardado finalmente.
// Asegúrate que los require_once apunten correctamente a /ERP/includes/
require_once("../../connection.php"); 
require_once("../../auth.php"); 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$num_factura_get = isset($_GET['num_factura']) ? intval($_GET['num_factura']) : 0;
$factura = null;
$lineas_factura = [];
$error_message = '';

if ($num_factura_get <= 0) {
    $error_message = "Número de factura no válido.";
} else {
    // 1. Obtener datos de la factura principal
    $query_factura = "
        SELECT 
            f.num_factura,
            f.tipo,
            f.cod_empleado,
            f.empleado_nombre_snapshot,
            f.cod_actor,
            f.actor_nombre_snapshot,
            f.actor_tipo_snapshot,
            f.total_factura,
            f.fecha_creacion
        FROM facturas f
        WHERE f.num_factura = ?
    ";
    $stmt_factura = $connection->prepare($query_factura);
    if ($stmt_factura) {
        $stmt_factura->bind_param("i", $num_factura_get);
        $stmt_factura->execute();
        $resultado_factura = $stmt_factura->get_result();
        $factura = $resultado_factura->fetch_assoc();
        $stmt_factura->close();

        if (!$factura) {
            $error_message = "Factura no encontrada.";
        } else {
            // 2. Obtener líneas de la factura
            $query_lineas = "
                SELECT 
                    l.num_linea,
                    l.cod_producto,
                    l.producto_nombre_snapshot,
                    l.cantidad,
                    l.precio_negociado_unitario, 
                    l.precio_total, 
                    l.cod_almacen,
                    l.almacen_ubicacion_snapshot,
                    ps.iva AS iva_producto_actual, 
                    ps.precio_venta AS precio_venta_actual_producto 
                FROM lineas l
                LEFT JOIN producto_servicio ps ON l.cod_producto = ps.cod_producto
                WHERE l.num_factura = ?
                ORDER BY l.num_linea ASC
            ";
            $stmt_lineas = $connection->prepare($query_lineas);
            if ($stmt_lineas) {
                $stmt_lineas->bind_param("i", $num_factura_get);
                $stmt_lineas->execute();
                $resultado_lineas = $stmt_lineas->get_result();
                while ($row = $resultado_lineas->fetch_assoc()) {
                    $lineas_factura[] = $row;
                }
                $stmt_lineas->close();
            } else {
                $error_message = "Error al cargar las líneas de la factura: " . $connection->error;
            }
        }
    } else {
        $error_message = "Error al preparar la consulta de la factura: " . $connection->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Factura - <?php echo htmlspecialchars($factura['num_factura'] ?? 'Error'); ?></title>
    <link rel="stylesheet" href="/ERP/assets/css/functions_style/style_ver_mas_facturas.css">
</head>
<body>

<div class="factura-container">
    <?php if ($error_message): ?>
        <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
    <?php elseif ($factura): ?>
        <div class="factura-header-top">
            <div class="factura-info-main">
                <h1>FACTURA</h1>
                <p><strong>Número de Factura:</strong> <?php echo htmlspecialchars($factura['num_factura']); ?></p>
                <p><strong>Fecha:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($factura['fecha_creacion']))); ?></p>
                <p><strong>Tipo:</strong> <?php echo htmlspecialchars(ucfirst($factura['tipo'])); ?></p>
            </div>
        </div>

        <div class="info-block"> 
            <h3><?php echo ($factura['actor_tipo_snapshot'] === 'cliente' ? 'Cliente:' : 'Proveedor:'); ?></h3>
            <p><strong>Código:</strong> <?php echo htmlspecialchars($factura['cod_actor'] ?? 'N/A'); ?></p>
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($factura['actor_nombre_snapshot'] ?? 'N/A'); ?></p>
        </div>

        <div class="info-block"> 
            <h3>Atendido por:</h3>
            <p><strong>Código Empleado:</strong> <?php echo htmlspecialchars($factura['cod_empleado'] ?? 'N/A'); ?></p>
            <p><strong>Nombre Empleado:</strong> <?php echo htmlspecialchars($factura['empleado_nombre_snapshot'] ?? 'N/A'); ?></p>
        </div>

        <h3 class="detalle-productos-titulo">Detalle de Productos/Servicios:</h3>
        <table class="tabla-lineas-factura">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cód. Prod.</th>
                    <th>Descripción</th>
                    <th>Almacén</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <?php if ($factura['tipo'] === 'venta'): ?>
                        <th>% IVA</th>
                    <?php endif; ?>
                    <th>Total Línea</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $subtotal_general_sin_iva = 0;
                $total_iva_general = 0;
                foreach ($lineas_factura as $linea): 
                    $precio_unitario_mostrado = $linea['precio_negociado_unitario'];
                    $total_linea_mostrado = $linea['precio_total'];
                    $iva_linea_porcentaje = 0;

                    if ($factura['tipo'] === 'venta') {
                        if ($precio_unitario_mostrado > 0 && $linea['cantidad'] > 0) {
                            $subtotal_linea_sin_iva_calc = $precio_unitario_mostrado * $linea['cantidad'];
                            $valor_iva_linea_calc = $total_linea_mostrado - $subtotal_linea_sin_iva_calc;
                            if ($subtotal_linea_sin_iva_calc > 0) { // Evitar división por cero
                                $iva_linea_porcentaje = round(($valor_iva_linea_calc / $subtotal_linea_sin_iva_calc) * 100, 2);
                            }
                            $subtotal_general_sin_iva += $subtotal_linea_sin_iva_calc;
                            $total_iva_general += $valor_iva_linea_calc;
                        } else { 
                            $subtotal_general_sin_iva += $total_linea_mostrado; 
                        }
                    } else { // Compra
                        $subtotal_general_sin_iva += $total_linea_mostrado;
                    }
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($linea['num_linea']); ?></td>
                        <td><?php echo htmlspecialchars($linea['cod_producto'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($linea['producto_nombre_snapshot'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($linea['almacen_ubicacion_snapshot'] ?? 'N/A'); ?></td>
                        <td class="numero"><?php echo htmlspecialchars($linea['cantidad']); ?></td>
                        <td class="numero"><?php echo htmlspecialchars(number_format($precio_unitario_mostrado, 2, ',', '.')); ?> €</td>
                        <?php if ($factura['tipo'] === 'venta'): ?>
                            <td class="numero"><?php echo htmlspecialchars($iva_linea_porcentaje); ?>%</td>
                        <?php endif; ?>
                        <td class="numero"><?php echo htmlspecialchars(number_format($total_linea_mostrado, 2, ',', '.')); ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="factura-totales">
            <table>
                <?php if ($factura['tipo'] === 'venta'): ?>
                    <tr>
                        <td class="label">Subtotal (sin IVA):</td>
                        <td class="valor"><?php echo htmlspecialchars(number_format($subtotal_general_sin_iva, 2, ',', '.')); ?> €</td>
                    </tr>
                    <tr>
                        <td class="label">Total IVA:</td>
                        <td class="valor"><?php echo htmlspecialchars(number_format($total_iva_general, 2, ',', '.')); ?> €</td>
                    </tr>
                <?php endif; ?>
                <tr class="gran-total">
                    <td class="label">TOTAL FACTURA:</td>
                    <td class="valor"><?php echo htmlspecialchars(number_format($factura['total_factura'], 2, ',', '.')); ?> €</td>
                </tr>
            </table>
        </div>

        <div class="botones-accion-factura no-print">
            <button onclick="window.print();">Imprimir Factura</button>
            <a href="/ERP/modules/home/empleado_home.php?pagina=historial">Volver al Historial</a>
        </div>

    <?php else: ?>
        <p class="error-message">No se pudo cargar la información de la factura.</p>
    <?php endif; ?>
</div>

</body>
</html>