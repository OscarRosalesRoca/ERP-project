<?php
// Ruta: /ERP/includes/functions/facturas/procesar_factura_compra.php
require_once("../../connection.php"); // Ajusta si es necesario, debe apuntar a /ERP/includes/connection.php
require_once("../../auth.php");       // Ajusta si es necesario, debe apuntar a /ERP/includes/auth.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$errores_factura = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['guardar_factura_compra'])) {

    // 1. Recoger datos generales de la factura
    $cod_empleado_actual = isset($_POST['cod_empleado_actual']) ? intval($_POST['cod_empleado_actual']) : 0;
    $nombre_empleado_snapshot = isset($_POST['nombre_empleado_snapshot']) ? trim($_POST['nombre_empleado_snapshot']) : 'Empleado Desconocido';
    $proveedor_cod_actor = isset($_POST['proveedor_cod_actor']) ? intval($_POST['proveedor_cod_actor']) : 0;
    $total_factura_compra = isset($_POST['total_factura_compra']) ? floatval($_POST['total_factura_compra']) : 0.00;
    
    $lineas_factura = isset($_POST['lineas']) && is_array($_POST['lineas']) ? $_POST['lineas'] : [];

    // 2. Validaciones básicas
    if ($cod_empleado_actual <= 0) {
        $errores_factura[] = "No se pudo identificar al empleado.";
    }
    if ($proveedor_cod_actor <= 0) {
        $errores_factura[] = "Debe seleccionar un proveedor.";
    }
    if (empty($lineas_factura)) {
        $errores_factura[] = "La factura debe tener al menos una línea de producto.";
    }
    if ($total_factura_compra <= 0 && !empty($lineas_factura)) { // Permitir total 0 si no hay líneas (aunque ya validamos arriba)
        // Esta validación podría ser más compleja, asegurando que el total coincida con la suma de líneas.
        // Por ahora, solo verificamos que no sea negativo si hay líneas.
        $errores_factura[] = "El total de la factura parece incorrecto.";
    }

    // Validar cada línea
    foreach ($lineas_factura as $num_linea_form => $linea) {
        if (empty($linea['cod_producto']) || intval($linea['cod_producto']) <= 0) {
            $errores_factura[] = "Línea " . htmlspecialchars($num_linea_form) . ": Debe seleccionar un producto.";
        }
        if (empty($linea['cantidad']) || intval($linea['cantidad']) <= 0) {
            $errores_factura[] = "Línea " . htmlspecialchars($num_linea_form) . ": La cantidad debe ser mayor a 0.";
        }
        if (empty($linea['cod_almacen']) || intval($linea['cod_almacen']) <= 0) {
            $errores_factura[] = "Línea " . htmlspecialchars($num_linea_form) . ": Debe seleccionar un almacén de destino.";
        }
        if (!isset($linea['precio_unitario']) || floatval($linea['precio_unitario']) < 0) {
            $errores_factura[] = "Línea " . htmlspecialchars($num_linea_form) . ": El precio unitario es incorrecto.";
        }
    }


    if (empty($errores_factura)) {
        $connection->begin_transaction();
        try {
            // 3. Obtener nombre del proveedor para snapshot
            $nombre_proveedor_snapshot = 'Proveedor Desconocido';
            $stmt_prov_snap = $connection->prepare("SELECT nombre FROM proveedores_clientes WHERE cod_actor = ? AND tipo = 'proveedor'");
            if ($stmt_prov_snap) {
                $stmt_prov_snap->bind_param("i", $proveedor_cod_actor);
                $stmt_prov_snap->execute();
                $res_prov_snap = $stmt_prov_snap->get_result();
                if ($row_snap = $res_prov_snap->fetch_assoc()) {
                    $nombre_proveedor_snapshot = $row_snap['nombre'];
                }
                $stmt_prov_snap->close();
            } else {
                throw new Exception("Error al obtener datos del proveedor.");
            }

            // 4. Insertar en la tabla 'facturas'
            $tipo_factura = 'compra';
            $actor_tipo_snapshot = 'proveedor';

            $stmt_insert_factura = $connection->prepare(
                "INSERT INTO facturas (cod_empleado, cod_actor, tipo, total_factura, empleado_nombre_snapshot, actor_nombre_snapshot, actor_tipo_snapshot, fecha_creacion) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
            );
            if (!$stmt_insert_factura) {
                throw new Exception("Error al preparar la inserción de factura: " . $connection->error);
            }
            $stmt_insert_factura->bind_param("iisdsss", 
                $cod_empleado_actual, 
                $proveedor_cod_actor, 
                $tipo_factura, 
                $total_factura_compra, 
                $nombre_empleado_snapshot, 
                $nombre_proveedor_snapshot,
                $actor_tipo_snapshot
            );
            if (!$stmt_insert_factura->execute()) {
                throw new Exception("Error al guardar la factura: " . $stmt_insert_factura->error);
            }
            $num_factura_generado = $connection->insert_id;
            $stmt_insert_factura->close();

            // 5. Iterar e insertar líneas, actualizar stock y producto.activo
            $linea_num_bd = 0;
            foreach ($lineas_factura as $num_linea_form => $linea_data) {
                $linea_num_bd++;
                $cod_producto = intval($linea_data['cod_producto']);
                $cantidad = intval($linea_data['cantidad']);
                $cod_almacen = intval($linea_data['cod_almacen']);
                $precio_unitario = floatval($linea_data['precio_unitario']);
                $precio_total_linea = $cantidad * $precio_unitario; // Recalcular por seguridad

                // Obtener snapshots para la línea
                $producto_nombre_snapshot = 'Producto Desconocido';
                $stmt_prod_snap = $connection->prepare("SELECT nombre FROM producto_servicio WHERE cod_producto = ?");
                if($stmt_prod_snap){
                    $stmt_prod_snap->bind_param("i", $cod_producto); $stmt_prod_snap->execute();
                    $res_prod_snap = $stmt_prod_snap->get_result();
                    if($r = $res_prod_snap->fetch_assoc()) $producto_nombre_snapshot = $r['nombre'];
                    $stmt_prod_snap->close();
                }

                $almacen_ubicacion_snapshot = 'Almacén Desconocido';
                $stmt_alm_snap = $connection->prepare("SELECT ubicacion FROM almacen WHERE cod_almacen = ?");
                if($stmt_alm_snap){
                    $stmt_alm_snap->bind_param("i", $cod_almacen); $stmt_alm_snap->execute();
                    $res_alm_snap = $stmt_alm_snap->get_result();
                    if($r = $res_alm_snap->fetch_assoc()) $almacen_ubicacion_snapshot = $r['ubicacion'];
                    $stmt_alm_snap->close();
                }
                
                // Insertar en 'lineas'
                $stmt_insert_linea = $connection->prepare(
                    "INSERT INTO lineas (num_linea, num_factura, cod_producto, cod_almacen, cantidad, precio_negociado_unitario, precio_total, producto_nombre_snapshot, almacen_ubicacion_snapshot)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if(!$stmt_insert_linea) throw new Exception("Error preparando inserción de línea: " . $connection->error);
                $stmt_insert_linea->bind_param("iiiiiddss", 
                    $linea_num_bd, $num_factura_generado, $cod_producto, $cod_almacen, $cantidad, 
                    $precio_unitario, $precio_total_linea, $producto_nombre_snapshot, $almacen_ubicacion_snapshot
                );
                if(!$stmt_insert_linea->execute()) throw new Exception("Error guardando línea " . $linea_num_bd . ": " . $stmt_insert_linea->error);
                $stmt_insert_linea->close();

                // Actualizar/Insertar stock en 'almacen_producto_servicio'
                $stmt_check_stock = $connection->prepare("SELECT cantidad FROM almacen_producto_servicio WHERE cod_almacen = ? AND cod_producto = ?");
                if(!$stmt_check_stock) throw new Exception("Error preparando consulta de stock: " . $connection->error);
                $stmt_check_stock->bind_param("ii", $cod_almacen, $cod_producto);
                $stmt_check_stock->execute();
                $res_stock = $stmt_check_stock->get_result();
                if ($row_stock = $res_stock->fetch_assoc()) { // Existe, actualizar
                    $stmt_update_stock = $connection->prepare("UPDATE almacen_producto_servicio SET cantidad = cantidad + ? WHERE cod_almacen = ? AND cod_producto = ?");
                    if(!$stmt_update_stock) throw new Exception("Error preparando update de stock: " . $connection->error);
                    $stmt_update_stock->bind_param("iii", $cantidad, $cod_almacen, $cod_producto);
                    if(!$stmt_update_stock->execute()) throw new Exception("Error actualizando stock: " . $stmt_update_stock->error);
                    $stmt_update_stock->close();
                } else { // No existe, insertar
                    $stmt_insert_stock = $connection->prepare("INSERT INTO almacen_producto_servicio (cod_almacen, cod_producto, cantidad) VALUES (?, ?, ?)");
                    if(!$stmt_insert_stock) throw new Exception("Error preparando insert de stock: " . $connection->error);
                    $stmt_insert_stock->bind_param("iii", $cod_almacen, $cod_producto, $cantidad);
                    if(!$stmt_insert_stock->execute()) throw new Exception("Error insertando stock: " . $stmt_insert_stock->error);
                    $stmt_insert_stock->close();
                }
                $stmt_check_stock->close();

                // Activar producto en 'producto_servicio'
                $stmt_activar_prod = $connection->prepare("UPDATE producto_servicio SET activo = TRUE WHERE cod_producto = ?");
                if(!$stmt_activar_prod) throw new Exception("Error preparando activación de producto: " . $connection->error);
                $stmt_activar_prod->bind_param("i", $cod_producto);
                if(!$stmt_activar_prod->execute()) throw new Exception("Error activando producto: " . $stmt_activar_prod->error);
                $stmt_activar_prod->close();
            }

            $connection->commit();
            $_SESSION['mensaje_exito_factura'] = "Factura de compra Nº " . $num_factura_generado . " creada exitosamente.";
            header("Location: /ERP/modules/home/empleado_home.php?pagina=historial&mensaje=factura_compra_creada");
            exit;

        } catch (Exception $e) {
            $connection->rollback();
            // Guardar el error en sesión para mostrarlo en facturas.php
            $_SESSION['errores_factura'] = [$e->getMessage()];
            // También puedes añadir los errores de validación si los hubo antes de la transacción
            // header("Location: /ERP/modules/home/empleado_home.php?pagina=facturas&error_compra=1"); // Redirigir de vuelta al formulario
            // Es mejor pasar los datos del formulario de vuelta para no perderlos, usando sesión.
            $_SESSION['form_data_compra'] = $_POST; // Guardar datos para rellenar el formulario
            header("Location: " . $_SERVER['HTTP_REFERER'] . "&error_guardado=1"); // Volver a la página anterior (facturas)
            exit;
        }
    } else {
        // Si hubo errores de validación inicial, guardarlos en sesión y redirigir
        $_SESSION['errores_factura'] = $errores_factura;
        $_SESSION['form_data_compra'] = $_POST; // Guardar datos para rellenar el formulario
        header("Location: " . $_SERVER['HTTP_REFERER'] . "&validation_error=1"); // Volver a la página anterior (facturas)
        exit;
    }

} else {
    // Si no es POST o no se presionó el botón correcto, redirigir a la página principal de facturas o al home.
    header("Location: /ERP/modules/home/empleado_home.php?pagina=facturas");
    exit;
}
?>
