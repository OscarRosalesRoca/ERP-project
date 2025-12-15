<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php"); 
require_once("../../../includes/auth.php");       

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$errores_factura = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['guardar_factura_venta'])) {

    //Recoger datos generales de la factura de venta
    $cod_empleado_actual = isset($_POST['cod_empleado_actual']) ? intval($_POST['cod_empleado_actual']) : 0;
    $nombre_empleado_snapshot = isset($_POST['nombre_empleado_snapshot']) ? trim($_POST['nombre_empleado_snapshot']) : 'Empleado Desconocido';
    $cliente_cod_actor = isset($_POST['cliente_cod_actor']) ? intval($_POST['cliente_cod_actor']) : 0;
    $total_factura_venta_con_iva = isset($_POST['total_factura_venta']) ? floatval($_POST['total_factura_venta']) : 0.00;
    
    $lineas_factura_venta = isset($_POST['lineas']) && is_array($_POST['lineas']) ? $_POST['lineas'] : [];

    //Validaciones básicas
    if ($cod_empleado_actual <= 0) {
        $errores_factura[] = "No se pudo identificar al empleado.";
    }
    if ($cliente_cod_actor <= 0) {
        $errores_factura[] = "Debe seleccionar un cliente.";
    }
    if (empty($lineas_factura_venta)) {
        $errores_factura[] = "La factura de venta debe tener al menos una línea de producto.";
    }
    // El total_factura_venta_con_iva se calcula en JS, pero lo validamos aquí
    if ($total_factura_venta_con_iva <= 0 && !empty($lineas_factura_venta)) {
        $errores_factura[] = "El total de la factura de venta parece incorrecto.";
    }

    // Validar cada línea de venta
    foreach ($lineas_factura_venta as $num_linea_form => $linea) {
        if (empty($linea['cod_producto']) || intval($linea['cod_producto']) <= 0) {
            $errores_factura[] = "Línea " . htmlspecialchars($num_linea_form) . ": Debe seleccionar un producto.";
        }
        if (empty($linea['cantidad']) || intval($linea['cantidad']) <= 0) {
            $errores_factura[] = "Línea " . htmlspecialchars($num_linea_form) . ": La cantidad debe ser mayor a 0.";
        }

        // Depuración para cod_almacen_origen 
        $valor_cod_almacen_origen = isset($linea['cod_almacen_origen']) ? $linea['cod_almacen_origen'] : 'NO ESTABLECIDO';
        error_log("Procesando Factura Venta - Línea " . $num_linea_form . ": valor de 'cod_almacen_origen' recibido = '" . $valor_cod_almacen_origen . "', intval = " . intval($valor_cod_almacen_origen));

        if (empty($linea['cod_almacen_origen']) || intval($linea['cod_almacen_origen']) <= 0) {
            $errores_factura[] = "Línea " . htmlspecialchars($num_linea_form) . ": Debe seleccionar un almacén de origen. (Valor recibido: '" . htmlspecialchars($valor_cod_almacen_origen) . "')";
        }
        if (!isset($linea['precio_unitario_sin_iva']) || floatval($linea['precio_unitario_sin_iva']) < 0) {
            $errores_factura[] = "Línea " . htmlspecialchars($num_linea_form) . ": El precio unitario es incorrecto.";
        }
        if (!isset($linea['iva_producto']) || floatval($linea['iva_producto']) < 0) {
            $errores_factura[] = "Línea " . htmlspecialchars($num_linea_form) . ": El IVA del producto es incorrecto.";
        }
    }

    if (empty($errores_factura)) {
        $connection->begin_transaction();
        try {
            //Obtener nombre del cliente para snapshot
            $nombre_cliente_snapshot = 'Cliente Desconocido';
            $stmt_cli_snap = $connection->prepare("SELECT nombre FROM proveedores_clientes WHERE cod_actor = ? AND tipo = 'cliente'");
            if ($stmt_cli_snap) {
                $stmt_cli_snap->bind_param("i", $cliente_cod_actor);
                $stmt_cli_snap->execute();
                $res_cli_snap = $stmt_cli_snap->get_result();
                if ($row_snap = $res_cli_snap->fetch_assoc()) {
                    $nombre_cliente_snapshot = $row_snap['nombre'];
                }
                $stmt_cli_snap->close();
            } else {
                throw new Exception("Error al obtener datos del cliente.");
            }

            //Insertar en la tabla 'facturas'
            $tipo_factura_db = 'venta';
            $actor_tipo_snapshot_db = 'cliente';

            $stmt_insert_factura = $connection->prepare(
                "INSERT INTO facturas (cod_empleado, cod_actor, tipo, total_factura, empleado_nombre_snapshot, actor_nombre_snapshot, actor_tipo_snapshot, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
            );
            if (!$stmt_insert_factura) {
                throw new Exception("Error al preparar la inserción de factura de venta: " . $connection->error);
            }
            $stmt_insert_factura->bind_param("iisdsss", 
                $cod_empleado_actual, 
                $cliente_cod_actor, 
                $tipo_factura_db, 
                $total_factura_venta_con_iva, 
                $nombre_empleado_snapshot, 
                $nombre_cliente_snapshot,
                $actor_tipo_snapshot_db
            );
            if (!$stmt_insert_factura->execute()) {
                throw new Exception("Error al guardar la factura de venta: " . $stmt_insert_factura->error);
            }
            $num_factura_generado = $connection->insert_id;
            $stmt_insert_factura->close();

            //Iterar e insertar líneas y actualizar stock
            $linea_num_bd = 0;
            foreach ($lineas_factura_venta as $num_linea_form => $linea_data) {
                $linea_num_bd++;
                $cod_producto = intval($linea_data['cod_producto']);
                $cantidad_vendida = intval($linea_data['cantidad']);
                $cod_almacen_origen = intval($linea_data['cod_almacen_origen']);
                $precio_unitario_sin_iva = floatval($linea_data['precio_unitario_sin_iva']);
                $iva_producto_porcentaje = floatval($linea_data['iva_producto']);
                
                //Recalcular precio total de la línea con IVA
                $subtotal_linea_sin_iva = $cantidad_vendida * $precio_unitario_sin_iva;
                $valor_iva_linea = $subtotal_linea_sin_iva * ($iva_producto_porcentaje / 100);
                $precio_total_linea_con_iva = $subtotal_linea_sin_iva + $valor_iva_linea;

                //Obtener snapshots para la línea
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
                    $stmt_alm_snap->bind_param("i", $cod_almacen_origen); $stmt_alm_snap->execute();
                    $res_alm_snap = $stmt_alm_snap->get_result();
                    if($r = $res_alm_snap->fetch_assoc()) $almacen_ubicacion_snapshot = $r['ubicacion'];
                    $stmt_alm_snap->close();
                }
                
                // Insertar en 'lineas'
                $stmt_insert_linea = $connection->prepare(
                    "INSERT INTO lineas (num_linea, num_factura, cod_producto, cod_almacen, cantidad, precio_negociado_unitario, precio_total, producto_nombre_snapshot, almacen_ubicacion_snapshot)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if(!$stmt_insert_linea) throw new Exception("Error preparando inserción de línea de venta: " . $connection->error);
                $stmt_insert_linea->bind_param("iiiiiddss", 
                    $linea_num_bd, $num_factura_generado, $cod_producto, $cod_almacen_origen, $cantidad_vendida, 
                    $precio_unitario_sin_iva, $precio_total_linea_con_iva, $producto_nombre_snapshot, $almacen_ubicacion_snapshot
                );
                if(!$stmt_insert_linea->execute()) throw new Exception("Error guardando línea de venta " . $linea_num_bd . ": " . $stmt_insert_linea->error);
                $stmt_insert_linea->close();

                // Restar stock en 'almacen_producto_servicio'
                $stmt_check_stock = $connection->prepare("SELECT cantidad FROM almacen_producto_servicio WHERE cod_almacen = ? AND cod_producto = ?");
                if(!$stmt_check_stock) throw new Exception("Error preparando verificación de stock: " . $connection->error);
                $stmt_check_stock->bind_param("ii", $cod_almacen_origen, $cod_producto);
                $stmt_check_stock->execute();
                $res_stock_check = $stmt_check_stock->get_result();
                $stock_actual_row = $res_stock_check->fetch_assoc();
                $stmt_check_stock->close();

                if (!$stock_actual_row || $stock_actual_row['cantidad'] < $cantidad_vendida) {
                    throw new Exception("Stock insuficiente para el producto '" . htmlspecialchars($producto_nombre_snapshot) . "' en el almacén '" . htmlspecialchars($almacen_ubicacion_snapshot) . "'. Solicitado: $cantidad_vendida, Disponible: " . ($stock_actual_row['cantidad'] ?? 0));
                }

                $nueva_cantidad_stock = $stock_actual_row['cantidad'] - $cantidad_vendida;
                if ($nueva_cantidad_stock > 0) {
                    $stmt_update_stock = $connection->prepare("UPDATE almacen_producto_servicio SET cantidad = ? WHERE cod_almacen = ? AND cod_producto = ?");
                    if(!$stmt_update_stock) throw new Exception("Error preparando update de stock (venta): " . $connection->error);
                    $stmt_update_stock->bind_param("iii", $nueva_cantidad_stock, $cod_almacen_origen, $cod_producto);
                    if(!$stmt_update_stock->execute()) throw new Exception("Error actualizando stock (venta): " . $stmt_update_stock->error);
                    $stmt_update_stock->close();
                } else { 
                    $stmt_delete_stock_entry = $connection->prepare("DELETE FROM almacen_producto_servicio WHERE cod_almacen = ? AND cod_producto = ?");
                    if(!$stmt_delete_stock_entry) throw new Exception("Error preparando delete de entrada de stock: " . $connection->error);
                    $stmt_delete_stock_entry->bind_param("ii", $cod_almacen_origen, $cod_producto);
                    if(!$stmt_delete_stock_entry->execute()) throw new Exception("Error eliminando entrada de stock (stock 0): " . $stmt_delete_stock_entry->error);
                    $stmt_delete_stock_entry->close();
                }
            }

            $connection->commit();
            $_SESSION['mensaje_exito_factura_venta'] = "Factura de venta Nº " . $num_factura_generado . " creada exitosamente.";
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=historial&mensaje_venta_creada=1");
            exit;

        } catch (Exception $e) {
            $connection->rollback();
            $_SESSION['errores_factura_venta'] = [$e->getMessage()];
            $_SESSION['form_data_factura_venta'] = $_POST;
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=facturas&error_guardado_venta=1");
            exit;
        }
    } else {
        //Añadir el valor problemático al mensaje de error para facilitar la depuración desde la UI
        foreach ($lineas_factura_venta as $num_linea_form_idx => $linea_con_error) {
            if (empty($linea_con_error['cod_almacen_origen']) || intval($linea_con_error['cod_almacen_origen']) <= 0) {
                //Buscar el error específico de esta línea para añadirle más contexto
                foreach($errores_factura as $key_error => $mensaje_error){
                    if(strpos($mensaje_error, "Línea " . htmlspecialchars($num_linea_form_idx)) !== false && strpos($mensaje_error, "almacén de origen") !== false){
                        $valor_recibido_almacen = isset($linea_con_error['cod_almacen_origen']) ? $linea_con_error['cod_almacen_origen'] : 'NO ENVIADO';
                        $errores_factura[$key_error] .= " (Valor recibido: '" . htmlspecialchars($valor_recibido_almacen) . "')";
                        break; 
                    }
                }
            }
        }
        $_SESSION['errores_factura_venta'] = $errores_factura;
        $_SESSION['form_data_factura_venta'] = $_POST;
        header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=facturas&validation_error_venta=1");
        exit;
    }

} else {
    header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=facturas");
    exit;
}
?>