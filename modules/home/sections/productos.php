<?php
require_once("../../config/config_path.php");

require_once("../../includes/connection.php");
require_once("../../includes/auth.php");

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configuración de campos de búsqueda para productos
$campos_busqueda_config_producto = [
    'cod_producto'      => ['display' => 'Código', 'column' => 'ps.cod_producto', 'table_alias' => 'ps'],
    'nombre_producto'   => ['display' => 'Nombre Producto', 'column' => 'ps.nombre', 'table_alias' => 'ps'],
    'nombre_proveedor'  => ['display' => 'Proveedor', 'column' => 'pc.nombre', 'table_alias' => 'pc'],
    'ubicacion_almacen' => ['display' => 'Almacén', 'column' => 'a.ubicacion', 'table_alias' => 'a']
];

// Valores iniciales
$campo_seleccionado_key_producto = 'cod_producto';
$termino_busqueda_producto = '';
$productos = [];
$busqueda_activa_producto = false;

// Construcción de la consulta SQL base
// Se usa DISTINCT para evitar filas duplicadas si un producto está en múltiples almacenes
// y la búsqueda es por almacén, o si tiene múltiples proveedores.
$sql_base_producto = "
    SELECT DISTINCT
        ps.cod_producto, 
        ps.nombre, 
        ps.iva, 
        pp.precio_compra, 
        ps.precio_venta,
        pc.nombre AS nombre_proveedor
    FROM producto_servicio ps
    LEFT JOIN producto_proveedor pp ON ps.cod_producto = pp.cod_producto
    LEFT JOIN proveedores_clientes pc ON pp.cod_actor = pc.cod_actor AND pc.tipo = 'proveedor'
    LEFT JOIN almacen_producto_servicio aps ON ps.cod_producto = aps.cod_producto
    LEFT JOIN almacen a ON aps.cod_almacen = a.cod_almacen
";

// La cláusula WHERE se construirá dinámicamente
$sql_conditions_producto = [];
$sql_final_producto = ""; // Se inicializará después
$params_producto = [];
$types_producto = "";

// Verificar si se envió el formulario de búsqueda
if (isset($_GET['buscar']) && isset($_GET['termino']) && trim($_GET['termino']) !== '') {
    $busqueda_activa_producto = true;
    if (isset($_GET['campo']) && array_key_exists($_GET['campo'], $campos_busqueda_config_producto)) {
        $campo_seleccionado_key_producto = $_GET['campo'];
    }
    $termino_busqueda_producto = trim($_GET['termino']);
    $columna_config = $campos_busqueda_config_producto[$campo_seleccionado_key_producto];
    $columna_a_buscar_producto = $columna_config['column'];

    if ($campo_seleccionado_key_producto == 'cod_producto') { // Búsqueda exacta para código
        $sql_conditions_producto[] = $columna_a_buscar_producto . " = ?";
        $params_producto[] = $termino_busqueda_producto;
        $types_producto .= "i";
    } else { // Búsqueda parcial (LIKE) para otros campos
        $sql_conditions_producto[] = $columna_a_buscar_producto . " LIKE ?";
        $params_producto[] = "%" . $termino_busqueda_producto . "%";
        $types_producto .= "s";
    }
    // Ordenación cuando la búsqueda está activa
    $orderByClause = " ORDER BY " . $columna_a_buscar_producto . " ASC, ps.nombre ASC";
} else {
    // Ordenación por defecto (carga inicial o después de limpiar)
    $orderByClause = " ORDER BY ps.cod_producto ASC";
    if (isset($_GET['buscar']) && trim($_GET['termino']) === '') {
        $termino_busqueda_producto = '';
        $campo_seleccionado_key_producto = 'cod_producto';
    }
}

// Construir la consulta final
$sql_final_producto = $sql_base_producto;
if (!empty($sql_conditions_producto)) {
    $sql_final_producto .= " WHERE " . implode(" AND ", $sql_conditions_producto);
}
$sql_final_producto .= $orderByClause;

// Preparar y ejecutar la consulta principal de productos
if (!isset($connection) || $connection === null) {
    die("<p>Error crítico: La conexión a la base de datos no está disponible en productos.php.</p>");
}
$stmt_producto = $connection->prepare($sql_final_producto);

if ($stmt_producto) {
    if (!empty($params_producto)) {
        $stmt_producto->bind_param($types_producto, ...$params_producto);
    }
    if ($stmt_producto->execute()) {
        $resultado_principal = $stmt_producto->get_result();
        if ($resultado_principal) {
            $productos = $resultado_principal->fetch_all(MYSQLI_ASSOC);
        } else {
            die("<p>Error al obtener resultados de productos: " . $connection->error . "</p>");
        }
        $stmt_producto->close();
    } else {
        die("<p>Error al ejecutar la consulta de productos: " . $stmt_producto->error . "</p>");
    }
} else {
    die("<p>Error al preparar la consulta de productos: " . $connection->error . "</p>");
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/sections_style/general_sections_style.css">
</head>
<body>
<div class="general_container">
    <h2>Productos</h2>

    <div class="cabecera_acciones">
        <div class="contenedor_busqueda">
            <form action="<?php echo BASE_URL; ?>/modules/home/empleado_home.php" method="GET" class="formulario_busqueda">
                <input type="hidden" name="pagina" value="productos">
                
                <label for="campo_busqueda_producto">Buscar por:</label>
                <select name="campo" id="campo_busqueda_producto">
                    <?php foreach ($campos_busqueda_config_producto as $key => $config): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php if ($campo_seleccionado_key_producto == $key) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($config['display']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="termino" value="<?php echo htmlspecialchars($termino_busqueda_producto); ?>" placeholder="Introduce término...">
                <input type="submit" name="buscar" value="Buscar">
                <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=productos" class="boton_limpiar">Limpiar</a>
            </form>
        </div>

        <div class="nuevo_general">
            <a href="<?php echo BASE_URL; ?>/includes/functions/producto/create_producto.php">+ Nuevo producto</a>
        </div>
    </div>

    <?php if (!empty($productos)): ?>
        <table class="tabla_general">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>IVA</th>
                    <th>Precio de compra</th>
                    <th>Precio de venta</th>
                    <th>Proveedor</th>
                    <th>Almacén</th>
                    <th>Stock</th>
                    <th>Editar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $producto_row): ?>
                    <?php
                    // Consulta para ubicaciones y cantidades del producto actual
                    $cod_producto_actual = $producto_row["cod_producto"];
                    $almacen_query_detalle = "
                        SELECT a.ubicacion, aps.cantidad
                        FROM almacen_producto_servicio aps
                        JOIN almacen a ON aps.cod_almacen = a.cod_almacen
                        WHERE aps.cod_producto = ? 
                    "; 
                    
                    $stmt_almacen_detalle = $connection->prepare($almacen_query_detalle);
                    $ubicaciones_detalle = [];
                    $cantidades_detalle = [];

                    if ($stmt_almacen_detalle) {
                        $stmt_almacen_detalle->bind_param("i", $cod_producto_actual);
                        if ($stmt_almacen_detalle->execute()) {
                            $almacen_result_detalle = $stmt_almacen_detalle->get_result();
                            if ($almacen_result_detalle && $almacen_result_detalle->num_rows > 0) {
                                while ($almacen_detalle_row = $almacen_result_detalle->fetch_assoc()) {
                                    $ubicaciones_detalle[] = $almacen_detalle_row["ubicacion"];
                                    $cantidades_detalle[] = $almacen_detalle_row["cantidad"];
                                }
                            }
                        }
                        $stmt_almacen_detalle->close();
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($producto_row["cod_producto"]); ?></td>
                        <td><?php echo htmlspecialchars($producto_row["nombre"]); ?></td>
                        <td><?php echo htmlspecialchars($producto_row["iva"]); ?></td>
                        <td><?php echo htmlspecialchars($producto_row["precio_compra"]); ?></td>
                        <td><?php echo htmlspecialchars($producto_row["precio_venta"]); ?></td>
                        <td><?php echo htmlspecialchars($producto_row["nombre_proveedor"]); ?></td>
                        <td><?php echo htmlspecialchars(implode(", ", $ubicaciones_detalle)); ?></td>
                        <td><?php echo htmlspecialchars(implode(", ", $cantidades_detalle)); ?></td>
                        <td class="editar"><a href="<?php echo BASE_URL; ?>/includes/functions/producto/edit_delete_producto.php?cod=<?php echo urlencode($producto_row["cod_producto"]); ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="sin_resultados">
            <?php if ($busqueda_activa_producto): ?>
                <p style="color: red;">No hay productos que coincidan con la búsqueda "<?php echo htmlspecialchars($termino_busqueda_producto); ?>" en el campo "<?php echo htmlspecialchars($campos_busqueda_config_producto[$campo_seleccionado_key_producto]['display']); ?>".</p>
            <?php else: ?>
                <p>No hay productos registrados aún. Puedes <a href="<?php echo BASE_URL; ?>/includes/functions/producto/create_producto.php">crear uno nuevo</a>.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>