<?php

require_once("../../config/config_path.php");
require_once("../../includes/connection.php");
require_once("../../includes/auth.php");

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Recuperar errores o datos de formulario de la sesión para COMPRAS
$errores_sesion_compra = $_SESSION['errores_factura_compra'] ?? [];
$form_data_sesion_compra = $_SESSION['form_data_factura_compra'] ?? [];
unset($_SESSION['errores_factura_compra'], $_SESSION['form_data_factura_compra']);
$mensaje_exito_sesion_compra = $_SESSION['mensaje_exito_factura_compra'] ?? '';
unset($_SESSION['mensaje_exito_factura_compra']);

// Recuperar errores o datos de formulario de la sesión para VENTAS
$errores_sesion_venta = $_SESSION['errores_factura_venta'] ?? [];
$form_data_sesion_venta = $_SESSION['form_data_factura_venta'] ?? [];
unset($_SESSION['errores_factura_venta'], $_SESSION['form_data_factura_venta']);
$mensaje_exito_sesion_venta = $_SESSION['mensaje_exito_factura_venta'] ?? '';
unset($_SESSION['mensaje_exito_factura_venta']);


$cod_empleado_actual = $_SESSION['usuario_id'] ?? 0;
$nombre_empleado_snapshot = '';
if ($cod_empleado_actual > 0) {
    // Asumiendo que 'usuario_id' en sesión es el 'cod_empleado' o se puede mapear.
    // Ajusta esta consulta según tu estructura de BD para obtener el nombre del empleado.
    $stmt_empleado = $connection->prepare("SELECT nombre FROM empleado WHERE usuario_id = ?");
    if ($stmt_empleado) {
        $stmt_empleado->bind_param("i", $cod_empleado_actual);
        $stmt_empleado->execute();
        $res_empleado = $stmt_empleado->get_result();
        if ($row_empleado = $res_empleado->fetch_assoc()) {
            $nombre_empleado_snapshot = $row_empleado['nombre'];
        }
        $stmt_empleado->close();
    }
}

// Cargar datos para los selects
$proveedores_compra = [];
$clientes_venta = [];
$almacenes_disponibles = []; // Para ambos formularios, aunque en ventas se filtrará más

// Obtener Proveedores (para Compras)
$stmt_proveedores = $connection->prepare("SELECT cod_actor, nombre FROM proveedores_clientes WHERE tipo = 'proveedor' ORDER BY nombre ASC");
if ($stmt_proveedores) {
    $stmt_proveedores->execute();
    $resultado_proveedores = $stmt_proveedores->get_result();
    while ($row = $resultado_proveedores->fetch_assoc()) {
        $proveedores_compra[] = $row;
    }
    $stmt_proveedores->close();
}

// Obtener Clientes (para Ventas)
$stmt_clientes = $connection->prepare("SELECT cod_actor, nombre FROM proveedores_clientes WHERE tipo = 'cliente' ORDER BY nombre ASC");
if ($stmt_clientes) {
    $stmt_clientes->execute();
    $resultado_clientes = $stmt_clientes->get_result();
    while ($row = $resultado_clientes->fetch_assoc()) {
        $clientes_venta[] = $row;
    }
    $stmt_clientes->close();
}

// Obtener Almacenes (para Compras y como base para Ventas)
$stmt_almacenes = $connection->prepare("SELECT cod_almacen, ubicacion FROM almacen ORDER BY ubicacion ASC");
if ($stmt_almacenes) {
    $stmt_almacenes->execute();
    $resultado_almacenes = $stmt_almacenes->get_result();
    while ($row = $resultado_almacenes->fetch_assoc()) {
        $almacenes_disponibles[] = $row;
    }
    $stmt_almacenes->close();
}

// Determinar qué pestaña está activa
$tab_activa_default = 'compra'; // Por defecto
if (isset($_GET['error_guardado_venta']) || isset($_GET['validation_error_venta']) || isset($_GET['mensaje_venta_creada'])) {
    $tab_activa_default = 'venta';
} elseif (isset($_GET['error_guardado_compra']) || isset($_GET['validation_error_compra']) || isset($_GET['mensaje_compra_creada'])) {
    $tab_activa_default = 'compra';
}

?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Facturas</title>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/sections_style/general_sections_style.css">
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/sections_style/style_facturas.css">
    </head>
    <body>
        <div class="general_container">
            <div class="card">
                <div class="factura_header">
                    <div id="tabCompra" class="factura_tab <?php echo ($tab_activa_default === 'compra' ? 'activo' : ''); ?>">Compras</div>
                    <div id="tabVenta" class="factura_tab <?php echo ($tab_activa_default === 'venta' ? 'activo' : ''); ?>">Ventas</div>
                </div>

                <div class="factura_body">
                    <?php if (!empty($errores_sesion_compra)): ?>
                        <div class="errores_factura_contenedor">
                            <h4>Error al procesar Factura de Compra:</h4>
                            <ul>
                                <?php foreach ($errores_sesion_compra as $error): echo '<li>' . htmlspecialchars($error) . '</li>'; endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($mensaje_exito_sesion_compra): ?>
                        <div class="mensaje_exito_factura_contenedor">
                            <p><?php echo htmlspecialchars($mensaje_exito_sesion_compra); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errores_sesion_venta)): ?>
                        <div class="errores_factura_contenedor">
                            <h4>Error al procesar Factura de Venta:</h4>
                            <ul>
                                <?php foreach ($errores_sesion_venta as $error): echo '<li>' . htmlspecialchars($error) . '</li>'; endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($mensaje_exito_sesion_venta): ?>
                        <div class="mensaje_exito_factura_contenedor">
                            <p><?php echo htmlspecialchars($mensaje_exito_sesion_venta); ?></p>
                        </div>
                    <?php endif; ?>

                    <div id="formCompra" class="formulario_factura" style="display: <?php echo ($tab_activa_default === 'compra' ? 'block' : 'none'); ?>;">
                        <h2>Nueva Factura de Compra</h2>
                        <form id="formFacturaCompra" method="POST" action="<?php echo BASE_URL; ?>/includes/functions/facturas/procesar_factura_compra.php">
                            <input type="hidden" name="cod_empleado_actual" value="<?php echo htmlspecialchars($cod_empleado_actual); ?>">
                            <input type="hidden" name="nombre_empleado_snapshot" value="<?php echo htmlspecialchars($nombre_empleado_snapshot); ?>">

                            <div class="factura_seccion_superior">
                                <div class="form_grupo">
                                    <label for="proveedorFactura">Proveedor:</label>
                                    <select name="proveedor_cod_actor" id="proveedorFactura" required>
                                        <option value="">-- Seleccione un Proveedor --</option>
                                        <?php foreach ($proveedores_compra as $proveedor): ?>
                                            <option value="<?php echo htmlspecialchars($proveedor['cod_actor']); ?>" 
                                                data-nombre-proveedor="<?php echo htmlspecialchars($proveedor['nombre']); ?>"
                                                <?php if (!empty($form_data_sesion_compra['proveedor_cod_actor']) && $form_data_sesion_compra['proveedor_cod_actor'] == $proveedor['cod_actor']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($proveedor['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form_grupo">
                                    <label for="totalFacturaCompra">Total Factura (€):</label>
                                    <input type="text" name="total_factura_compra" id="totalFacturaCompra" 
                                        value="<?php echo htmlspecialchars($form_data_sesion_compra['total_factura_compra'] ?? '0.00'); ?>" readonly class="total_grande">
                                </div>
                            </div>
                            
                            <hr class="separador_secciones_factura">
                            <h3>Líneas de la Factura de Compra</h3>
                            
                            <div id="contenedorLineasFacturaCompra">
                                <?php
                                $lineas_compra_a_mostrar = $form_data_sesion_compra['lineas'] ?? [['cod_producto' => '', 'cantidad' => 1, 'cod_almacen' => '', 'precio_unitario' => '']];
                                $linea_idx_compra = 0;
                                foreach ($lineas_compra_a_mostrar as $num_linea_form => $linea_guardada):
                                    $linea_idx_compra++;
                                ?>
                                    <div class="linea_factura" data-tipo-linea="compra" data-linea-num="<?php echo $linea_idx_compra; ?>">
                                        <span class="numero_linea"><?php echo $linea_idx_compra; ?>.</span>
                                        <div class="form_grupo">
                                            <label for="producto_linea_compra_<?php echo $linea_idx_compra; ?>">Producto:</label>
                                            <select name="lineas[<?php echo $linea_idx_compra; ?>][cod_producto]" id="producto_linea_compra_<?php echo $linea_idx_compra; ?>" class="select_producto_linea" required>
                                                <option value="">-- Seleccione Proveedor Primero --</option>
                                            </select>
                                            <input type="hidden" class="selected_product_on_error" value="<?php echo htmlspecialchars($linea_guardada['cod_producto'] ?? ''); ?>">
                                        </div>
                                        <div class="form_grupo">
                                            <label for="cantidad_linea_compra_<?php echo $linea_idx_compra; ?>">Cantidad:</label>
                                            <input type="number" name="lineas[<?php echo $linea_idx_compra; ?>][cantidad]" id="cantidad_linea_compra_<?php echo $linea_idx_compra; ?>" class="input_cantidad_linea" min="1" 
                                                value="<?php echo htmlspecialchars($linea_guardada['cantidad'] ?? 1); ?>" required>
                                        </div>
                                        <div class="form_grupo">
                                            <label for="almacen_linea_compra_<?php echo $linea_idx_compra; ?>">Almacén Destino:</label>
                                            <select name="lineas[<?php echo $linea_idx_compra; ?>][cod_almacen]" id="almacen_linea_compra_<?php echo $linea_idx_compra; ?>" class="select_almacen_linea" required>
                                                <option value="">-- Seleccione Almacén --</option>
                                                <?php foreach ($almacenes_disponibles as $almacen): ?>
                                                    <option value="<?php echo htmlspecialchars($almacen['cod_almacen']); ?>" 
                                                        data-nombre-almacen="<?php echo htmlspecialchars($almacen['ubicacion']); ?>"
                                                        <?php if (!empty($linea_guardada['cod_almacen']) && $linea_guardada['cod_almacen'] == $almacen['cod_almacen']) echo 'selected'; ?>>
                                                        <?php echo htmlspecialchars($almacen['ubicacion']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form_grupo">
                                            <label for="precio_unitario_linea_compra_<?php echo $linea_idx_compra; ?>">Precio Unit. (€):</label>
                                            <input type="text" name="lineas[<?php echo $linea_idx_compra; ?>][precio_unitario]" id="precio_unitario_linea_compra_<?php echo $linea_idx_compra; ?>" class="input_precio_unitario_linea" 
                                                value="<?php echo htmlspecialchars($linea_guardada['precio_unitario'] ?? ''); ?>" readonly>
                                        </div>
                                        <div class="form_grupo">
                                            <label for="precio_total_linea_compra_<?php echo $linea_idx_compra; ?>">Total Línea (€):</label>
                                            <input type="text" name="lineas[<?php echo $linea_idx_compra; ?>][precio_total]" id="precio_total_linea_compra_<?php echo $linea_idx_compra; ?>" class="input_precio_total_linea" readonly>
                                        </div>
                                        <button type="button" class="boton_eliminar_linea" style="display:<?php echo ($linea_idx_compra > 1 || count($lineas_compra_a_mostrar) > 1) ? 'inline-block' : 'none'; ?>;">Eliminar</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" id="botonAnadirLineaCompra" class="boton_accion">Añadir Línea de Compra</button>
                            <hr class="separador_secciones_factura">
                            <div class="botones_guardar_factura">
                                <button type="submit" name="guardar_factura_compra" class="boton_primario">Guardar Factura de Compra</button>
                            </div>
                        </form>
                    </div>

                    <div id="formVenta" class="formulario_factura" style="display: <?php echo ($tab_activa_default === 'venta' ? 'block' : 'none'); ?>;">
                        <h2>Nueva Factura de Venta</h2>
                        <form id="formFacturaVenta" method="POST" action="<?php echo BASE_URL; ?>/includes/functions/facturas/procesar_factura_venta.php">
                            <input type="hidden" name="cod_empleado_actual" value="<?php echo htmlspecialchars($cod_empleado_actual); ?>">
                            <input type="hidden" name="nombre_empleado_snapshot" value="<?php echo htmlspecialchars($nombre_empleado_snapshot); ?>">

                            <div class="factura_seccion_superior">
                                <div class="form_grupo">
                                    <label for="clienteFactura">Cliente:</label>
                                    <select name="cliente_cod_actor" id="clienteFactura" required>
                                        <option value="">-- Seleccione un Cliente --</option>
                                        <?php foreach ($clientes_venta as $cliente): ?>
                                            <option value="<?php echo htmlspecialchars($cliente['cod_actor']); ?>" 
                                                data-nombre-cliente="<?php echo htmlspecialchars($cliente['nombre']); ?>"
                                                <?php if (!empty($form_data_sesion_venta['cliente_cod_actor']) && $form_data_sesion_venta['cliente_cod_actor'] == $cliente['cod_actor']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($cliente['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form_grupo">
                                    <label for="totalFacturaVenta">Total Factura (IVA incl.):</label>
                                    <input type="text" name="total_factura_venta" id="totalFacturaVenta" 
                                        value="<?php echo htmlspecialchars($form_data_sesion_venta['total_factura_venta'] ?? '0.00'); ?>" readonly class="total_grande">
                                </div>
                            </div>
                            
                            <hr class="separador_secciones_factura">
                            <h3>Líneas de la Factura de Venta</h3>
                            
                            <div id="contenedorLineasFacturaVenta">
                                <?php
                                $lineas_venta_a_mostrar = $form_data_sesion_venta['lineas'] ?? [['cod_producto' => '', 'cantidad' => 1, 'cod_almacen_origen' => '', 'precio_unitario_sin_iva' => '', 'iva_producto' => '']];
                                $linea_idx_venta = 0;
                                foreach ($lineas_venta_a_mostrar as $num_linea_form => $linea_guardada):
                                    $linea_idx_venta++;
                                ?>
                                    <div class="linea_factura" data-tipo-linea="venta" data-linea-num="<?php echo $linea_idx_venta; ?>">
                                        <span class="numero_linea"><?php echo $linea_idx_venta; ?>.</span>
                                        <div class="form_grupo">
                                            <label for="producto_linea_venta_<?php echo $linea_idx_venta; ?>">Producto:</label>
                                            <select name="lineas[<?php echo $linea_idx_venta; ?>][cod_producto]" id="producto_linea_venta_<?php echo $linea_idx_venta; ?>" class="select_producto_linea" required>
                                                <option value="">-- Seleccione Producto --</option>
                                            </select>
                                            <input type="hidden" class="selected_product_on_error" value="<?php echo htmlspecialchars($linea_guardada['cod_producto'] ?? ''); ?>">
                                            <input type="hidden" name="lineas[<?php echo $linea_idx_venta; ?>][iva_producto]" class="input_iva_producto" value="<?php echo htmlspecialchars($linea_guardada['iva_producto'] ?? '0'); ?>">
                                        </div>
                                        <div class="form_grupo">
                                            <label for="almacen_linea_venta_<?php echo $linea_idx_venta; ?>">Almacén Origen:</label>
                                            <select name="lineas[<?php echo $linea_idx_venta; ?>][cod_almacen_origen]" id="almacen_linea_venta_<?php echo $linea_idx_venta; ?>" class="select_almacen_origen_linea">
                                                <option value="">-- Seleccione Producto Primero --</option>
                                            </select>
                                            <input type="hidden" class="selected_almacen_on_error" value="<?php echo htmlspecialchars($linea_guardada['cod_almacen_origen'] ?? ''); ?>">
                                        </div>
                                        <div class="form_grupo">
                                            <label for="cantidad_linea_venta_<?php echo $linea_idx_venta; ?>">Cantidad:</label>
                                            <input type="number" name="lineas[<?php echo $linea_idx_venta; ?>][cantidad]" id="cantidad_linea_venta_<?php echo $linea_idx_venta; ?>" class="input_cantidad_linea" min="1" 
                                                value="<?php echo htmlspecialchars($linea_guardada['cantidad'] ?? 1); ?>" required>
                                        </div>
                                        <div class="form_grupo">
                                            <label for="precio_unitario_linea_venta_<?php echo $linea_idx_venta; ?>">Precio Unit. (sin IVA) (€):</label>
                                            <input type="text" name="lineas[<?php echo $linea_idx_venta; ?>][precio_unitario_sin_iva]" id="precio_unitario_linea_venta_<?php echo $linea_idx_venta; ?>" class="input_precio_unitario_linea" 
                                                value="<?php echo htmlspecialchars($linea_guardada['precio_unitario_sin_iva'] ?? ''); ?>" readonly>
                                        </div>
                                        <div class="form_grupo">
                                            <label for="precio_total_linea_venta_<?php echo $linea_idx_venta; ?>">Total Línea (IVA incl.) (€):</label>
                                            <input type="text" name="lineas[<?php echo $linea_idx_venta; ?>][precio_total_con_iva]" id="precio_total_linea_venta_<?php echo $linea_idx_venta; ?>" class="input_precio_total_linea" readonly>
                                        </div>
                                        <button type="button" class="boton_eliminar_linea" style="display:<?php echo ($linea_idx_venta > 1 || count($lineas_venta_a_mostrar) > 1) ? 'inline-block' : 'none'; ?>;">Eliminar</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" id="botonAnadirLineaVenta" class="boton_accion">Añadir Línea de Venta</button>
                            <hr class="separador_secciones_factura">
                            <div class="botones_guardar_factura">
                                <button type="submit" name="guardar_factura_venta" class="boton_primario">Guardar factura de Venta</button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        <script>
            const BASE_URL_JS = "<?php echo BASE_URL; ?>";

            const almacenesDataGlobal = <?php echo json_encode($almacenes_disponibles); ?>; // Para compras
            const proveedorSeleccionadoAlCargarCompra = document.getElementById('proveedorFactura').value;
            const lineasConErrorCompra = <?php echo json_encode($form_data_sesion_compra['lineas'] ?? null); ?>;

            // Para ventas, los productos y sus almacenes se cargarán dinámicamente
            const clienteSeleccionadoAlCargarVenta = document.getElementById('clienteFactura').value; // Aunque no afecta la carga inicial de productos de venta
            const lineasConErrorVenta = <?php echo json_encode($form_data_sesion_venta['lineas'] ?? null); ?>;
        </script>
        <script src="<?php echo BASE_URL; ?>/assets/js/facturas.js"></script>
    </body>
</html>