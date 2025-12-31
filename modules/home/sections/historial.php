<?php

require_once("../../config/config_path.php");
require_once("../../includes/connection.php");
require_once("../../includes/auth.php");

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configuración de paginación
$registros_por_pagina = 10; 
$pagina_actual = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

// Calculamos el OFFSET (Lo que nos saltamos)
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$campos_busqueda_config_historial = [
    'num_factura'       => ['display' => 'Nº Factura', 'column' => 'f.num_factura', 'type' => 'number'],
    'cod_empleado'      => ['display' => 'Cód. Empleado', 'column' => 'f.cod_empleado', 'type' => 'number'],
    'nombre_empleado'   => ['display' => 'Nombre Empleado', 'column' => 'e.nombre', 'type' => 'text'],
    'cod_actor'         => ['display' => 'Cód. Actor', 'column' => 'f.cod_actor', 'type' => 'number'],
    'actor_nombre_snapshot' => ['display' => 'Nombre Actor (Factura)', 'column' => 'f.actor_nombre_snapshot', 'type' => 'text'],
    'fecha_creacion'    => ['display' => 'Fecha (YYYY-MM-DD)', 'column' => 'DATE(f.fecha_creacion)', 'type' => 'date'],
    'tipo'              => ['display' => 'Tipo Factura', 'column' => 'f.tipo', 'type' => 'select', 'options' => ['compra' => 'Compra', 'venta' => 'Venta']]
];

$campo_seleccionado_key_historial = 'num_factura';
$termino_busqueda_historial = '';
$facturas = [];
$busqueda_activa_historial = false;

// Consulta base
$sql_base_historial = "
    SELECT 
        f.num_factura,
        f.tipo,
        f.cod_empleado,
        f.empleado_nombre_snapshot,
        f.cod_actor,
        f.actor_nombre_snapshot,
        f.total_factura,
        f.fecha_creacion
    FROM facturas f
    LEFT JOIN empleado e ON f.cod_empleado = e.cod_empleado
";

// Consulta para contar el total de resultados
$sql_conteo_base = "
    SELECT COUNT(*) as total 
    FROM facturas f
    LEFT JOIN empleado e ON f.cod_empleado = e.cod_empleado
";

$sql_conditions_historial = [];
$params_historial = [];
$types_historial = "";

if (isset($_GET['buscar']) && isset($_GET['termino']) && trim($_GET['termino']) !== '') {
    $busqueda_activa_historial = true;
    if (isset($_GET['campo']) && array_key_exists($_GET['campo'], $campos_busqueda_config_historial)) {
        $campo_seleccionado_key_historial = $_GET['campo'];
    }
    $termino_busqueda_historial = trim($_GET['termino']);
    $columna_config = $campos_busqueda_config_historial[$campo_seleccionado_key_historial];
    $columna_a_buscar = $columna_config['column'];

    if ($columna_config['type'] === 'number' || $campo_seleccionado_key_historial === 'tipo') {
        $sql_conditions_historial[] = $columna_a_buscar . " = ?";
        $params_historial[] = $termino_busqueda_historial;
        $types_historial .= ($columna_config['type'] === 'number' ? "i" : "s");
    } elseif ($columna_config['type'] === 'date') {
        $sql_conditions_historial[] = $columna_a_buscar . " = ?"; 
        $params_historial[] = $termino_busqueda_historial;
        $types_historial .= "s";
    } else {
        $sql_conditions_historial[] = $columna_a_buscar . " LIKE ?";
        $params_historial[] = "%" . $termino_busqueda_historial . "%";
        $types_historial .= "s";
    }
    $orderByClause = " ORDER BY f.fecha_creacion DESC, " . $columna_a_buscar . " ASC";
} else {
    $orderByClause = " ORDER BY f.fecha_creacion DESC";
    if (isset($_GET['buscar']) && trim($_GET['termino']) === '') {
        $termino_busqueda_historial = '';
        $campo_seleccionado_key_historial = 'num_factura';
    }
}

if (!isset($connection) || $connection === null) {
    die("<p>Error crítico: La conexión a la base de datos no está disponible.</p>");
}

// Usamos las mismas condiciones WHERE para saber cuántos hay en total con esta búsqueda
$sql_final_conteo = $sql_conteo_base;
if (!empty($sql_conditions_historial)) {
    $sql_final_conteo .= " WHERE " . implode(" AND ", $sql_conditions_historial);
}

$stmt_conteo = $connection->prepare($sql_final_conteo);
if (!empty($params_historial)) {
    $stmt_conteo->bind_param($types_historial, ...$params_historial);
}
$stmt_conteo->execute();
$res_conteo = $stmt_conteo->get_result();
$fila_conteo = $res_conteo->fetch_assoc();
$total_registros = $fila_conteo['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina); // ceil redondea hacia arriba (ej: 11 reg / 10 = 1.1 -> 2 páginas)
$stmt_conteo->close();

// Consulta
$sql_final_historial = $sql_base_historial;
if (!empty($sql_conditions_historial)) {
    $sql_final_historial .= " WHERE " . implode(" AND ", $sql_conditions_historial);
}
$sql_final_historial .= $orderByClause;

// Añadimos limit y offset
$sql_final_historial .= " LIMIT ? OFFSET ?";

// Añadimos los parámetros de limit y offset al array de parámetros
$params_historial[] = $registros_por_pagina;
$params_historial[] = $offset;
$types_historial .= "ii"; // Dos enteros más

$stmt_historial = $connection->prepare($sql_final_historial);

if ($stmt_historial) {
    if (!empty($params_historial)) {
        $stmt_historial->bind_param($types_historial, ...$params_historial);
    }
    if ($stmt_historial->execute()) {
        $resultado_historial = $stmt_historial->get_result();
        if ($resultado_historial) {
            $facturas = $resultado_historial->fetch_all(MYSQLI_ASSOC);
        } else {
            die("<p>Error al obtener resultados: " . $connection->error . "</p>");
        }
        $stmt_historial->close();
    } else {
        die("<p>Error al ejecutar: " . $stmt_historial->error . "</p>");
    }
} else {
    die("<p>Error al preparar: " . $connection->error . "</p>");
}

// Función auxiliar para mantener los filtros al cambiar de página
function get_url_paginacion($pagina, $params_get) {
    $params_get['pag'] = $pagina;
    return '?' . http_build_query($params_get);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Actividad</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/sections_style/general_sections_style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/sections_style/style_historial.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/sections_style/paginacion.css">
</head>
<body>
<div class="general_container historial_container"> 
    <h2>Historial de Actividad</h2>

    <div class="cabecera_acciones"> 
        <div class="contenedor_busqueda">
            <form action="<?php echo BASE_URL; ?>/modules/home/empleado_home.php" method="GET" class="formulario_busqueda">
                <input type="hidden" name="pagina" value="historial">
                <label for="campo_busqueda_historial">Buscar por:</label>
                <select name="campo" id="campo_busqueda_historial">
                    <?php foreach ($campos_busqueda_config_historial as $key => $config): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php if ($campo_seleccionado_key_historial == $key) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($config['display']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php
                $current_field_config = $campos_busqueda_config_historial[$campo_seleccionado_key_historial];
                if ($current_field_config['type'] === 'select' && isset($current_field_config['options'])) :
                ?>
                    <select name="termino" id="termino_busqueda_historial">
                        <option value="">-- Todos --</option>
                        <?php foreach ($current_field_config['options'] as $value => $display): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php if ($termino_busqueda_historial == $value) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($display); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="<?php echo htmlspecialchars($current_field_config['type']); ?>" name="termino" id="termino_busqueda_historial" class="formulario_busqueda"
                            value="<?php echo htmlspecialchars($termino_busqueda_historial); ?>" 
                            placeholder="Introduce término...">
                <?php endif; ?>
                
                <input type="submit" name="buscar" value="Buscar">
                <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=historial" class="boton_limpiar">Limpiar</a>
            </form>
        </div>
    </div>

    <div class="info-paginacion">
        Mostrando <?php echo count($facturas); ?> de <?php echo $total_registros; ?> registros. (Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>)
    </div>

    <?php if (!empty($facturas)): ?>
        <table class="tabla_general"> 
            <thead>
                <tr>
                    <th>Nº Factura</th>
                    <th>Tipo</th>
                    <th>Cód. Empleado</th>
                    <th>Nombre Empleado (Factura)</th>
                    <th>Cód. Actor</th>
                    <th>Nombre Actor (Factura)</th>
                    <th>Total Factura</th>
                    <th>Fecha Creación</th>
                    <th>Ver Más</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($facturas as $factura): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($factura["num_factura"]); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($factura["tipo"])); ?></td>
                        <td><?php echo htmlspecialchars($factura["cod_empleado"] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($factura["empleado_nombre_snapshot"] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($factura["cod_actor"] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($factura["actor_nombre_snapshot"] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars(number_format($factura["total_factura"], 2, ',', '.')); ?> €</td>
                        <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($factura["fecha_creacion"]))); ?></td>
                        <td class="ver_mas">
                            <a href="<?php echo BASE_URL; ?>/includes/functions/facturas/ver_mas_facturas.php?num_factura=<?php echo urlencode($factura["num_factura"]); ?>">Ver más</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_paginas > 1): ?>
            <div class="paginacion">
                <?php if ($pagina_actual > 1): ?>
                    <a href="<?php echo get_url_paginacion($pagina_actual - 1, $_GET); ?>">&laquo; Anterior</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <?php if ($i == $pagina_actual): ?>
                        <span class="actual"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo get_url_paginacion($i, $_GET); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($pagina_actual < $total_paginas): ?>
                    <a href="<?php echo get_url_paginacion($pagina_actual + 1, $_GET); ?>">Siguiente &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="sin_resultados">
            <?php if ($busqueda_activa_historial): ?>
                <p>No se encontraron facturas que coincidan con tu búsqueda.</p>
            <?php else: ?>
                <p>No hay actividad registrada aún.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const campoBusquedaSelect = document.getElementById('campo_busqueda_historial');
        // Comprobar si el elemento existe antes de acceder a parentElement
        const terminoBusquedaInput = document.getElementById('termino_busqueda_historial');
        const terminoBusquedaContainer = terminoBusquedaInput ? terminoBusquedaInput.parentElement : null;
        const camposConfigJs = <?php echo json_encode($campos_busqueda_config_historial); ?>;
        if (campoBusquedaSelect && terminoBusquedaContainer) {
            campoBusquedaSelect.addEventListener('change', function() {
                const selectedKey = this.value;
                const config = camposConfigJs[selectedKey];
                let nuevoInputHtml = '';
                if (config.type === 'select' && config.options) {
                    nuevoInputHtml = `<select name="termino" id="termino_busqueda_historial">`;
                    nuevoInputHtml += `<option value="">-- Todos --</option>`;
                    for (const val in config.options) {
                        nuevoInputHtml += `<option value="${val}">${config.options[val]}</option>`;
                    }
                    nuevoInputHtml += `</select>`;
                } else {
                    nuevoInputHtml = `<input type="${config.type}" name="termino" id="termino_busqueda_historial" value="" placeholder="Introduce término...">`;
                }
                const oldTerminoInput = document.getElementById('termino_busqueda_historial');
                if (oldTerminoInput) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = nuevoInputHtml;

                    // Reemplazar el input/select de término actual
                    oldTerminoInput.parentNode.replaceChild(tempDiv.firstChild, oldTerminoInput);
                }
            });
        }
    });
</script>

</body>
</html>