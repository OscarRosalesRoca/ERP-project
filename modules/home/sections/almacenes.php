<?php

require_once("../../config/config_path.php");
require_once("../../includes/connection.php");
require_once("../../includes/auth.php");

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configuración de campos de búsqueda para almacenes
$campos_busqueda_config_almacen = [
    'cod_almacen' => ['display' => 'Código', 'column' => 'cod_almacen'],
    'ubicacion'   => ['display' => 'Ubicación', 'column' => 'ubicacion']
];

// Valores iniciales
$campo_seleccionado_key_almacen = 'cod_almacen'; // Campo por defecto
$termino_busqueda_almacen = '';
$almacenes = []; // Array para almacenar los almacenes recuperados
$busqueda_activa_almacen = false; // Para saber si se está realizando una búsqueda

// Construcción de la consulta SQL base
$sql_base_almacen = "SELECT cod_almacen, ubicacion FROM almacen";
$sql_final_almacen = ""; // Se inicializará después
$params_almacen = [];
$types_almacen = "";

// Verificar si se envió el formulario de búsqueda
if (isset($_GET['buscar']) && isset($_GET['termino']) && trim($_GET['termino']) !== '') {
    $busqueda_activa_almacen = true;
    if (isset($_GET['campo']) && array_key_exists($_GET['campo'], $campos_busqueda_config_almacen)) {
        $campo_seleccionado_key_almacen = $_GET['campo'];
    }
    $termino_busqueda_almacen = trim($_GET['termino']);
    $columna_a_buscar_almacen = $campos_busqueda_config_almacen[$campo_seleccionado_key_almacen]['column'];

    // Modificar la consulta SQL para la búsqueda
    if ($columna_a_buscar_almacen == 'cod_almacen') { // Búsqueda exacta para código
        $sql_final_almacen = $sql_base_almacen . " WHERE " . $columna_a_buscar_almacen . " = ?";
        $params_almacen[] = $termino_busqueda_almacen;
        $types_almacen .= "i";
    } else { // Búsqueda parcial (LIKE) para ubicación
        $sql_final_almacen = $sql_base_almacen . " WHERE " . $columna_a_buscar_almacen . " LIKE ?";
        $params_almacen[] = "%" . $termino_busqueda_almacen . "%";
        $types_almacen .= "s";
    }
    // Ordenación cuando la búsqueda está activa
    $sql_final_almacen .= " ORDER BY " . $columna_a_buscar_almacen . " ASC";
} else {
    // Consulta y ordenación por defecto (carga inicial o después de limpiar)
    $sql_final_almacen = $sql_base_almacen . " ORDER BY cod_almacen ASC";
    if (isset($_GET['buscar']) && trim($_GET['termino']) === '') { // Si se hizo clic en buscar con término vacío
        $termino_busqueda_almacen = '';
        $campo_seleccionado_key_almacen = 'cod_almacen';
    }
}

// Preparar y ejecutar la consulta
if (!isset($connection) || $connection === null) {
    die("<p>Error crítico: La conexión a la base de datos no está disponible en almacenes.php.</p>");
}
$stmt_almacen = $connection->prepare($sql_final_almacen);

if ($stmt_almacen) {
    if (!empty($params_almacen)) {
        $stmt_almacen->bind_param($types_almacen, ...$params_almacen);
    }
    if ($stmt_almacen->execute()) {
        $resultado = $stmt_almacen->get_result(); // Sobrescribe $resultado original, lo cual está bien aquí
        if ($resultado) {
            $almacenes = $resultado->fetch_all(MYSQLI_ASSOC);
        } else {
            die("<p>Error al obtener resultados de almacenes: " . $connection->error . "</p>");
        }
        $stmt_almacen->close();
    } else {
        die("<p>Error al ejecutar la consulta de almacenes: " . $stmt_almacen->error . "</p>");
    }
} else {
    die("<p>Error al preparar la consulta de almacenes: " . $connection->error . "</p>");
}

?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Almacenes</title>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/sections_style/general_sections_style.css">
    </head>
    <body>
        <div class="general_container">
            <h2>Almacenes</h2>

            <div class="cabecera_acciones">
                <div class="contenedor_busqueda">
                    <form action="<?php echo BASE_URL; ?>/modules/home/empleado_home.php" method="GET" class="formulario_busqueda">
                        <input type="hidden" name="pagina" value="almacenes">
                        
                        <label for="campo_busqueda_almacen">Buscar por:</label>
                        <select name="campo" id="campo_busqueda_almacen">
                            <?php foreach ($campos_busqueda_config_almacen as $key => $config): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php if ($campo_seleccionado_key_almacen == $key) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($config['display']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="termino" value="<?php echo htmlspecialchars($termino_busqueda_almacen); ?>" placeholder="Introduce término...">
                        <input type="submit" name="buscar" value="Buscar">
                        <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=almacenes" class="boton_limpiar">Limpiar</a>
                    </form>
                </div>

                <div class="nuevo_general">
                    <a href="<?php echo BASE_URL; ?>/includes/functions/almacen/create_almacen.php">+ Nuevo almacén</a>
                </div>
            </div>

            <?php if (!empty($almacenes)): ?>
                <table class="tabla_general">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Ubicación</th>
                            <th>Editar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($almacenes as $almacen_row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($almacen_row["cod_almacen"]); ?></td>
                                <td><?php echo htmlspecialchars($almacen_row["ubicacion"]); ?></td>
                                <td class="editar">
                                    <a href="<?php echo BASE_URL; ?>/includes/functions/almacen/edit_delete_almacen.php?cod=<?php echo urlencode($almacen_row["cod_almacen"]); ?>">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="sin_resultados">
                    <?php if ($busqueda_activa_almacen): ?>
                        <p style="color: red;">No hay almacenes que coincidan con la búsqueda "<?php echo htmlspecialchars($termino_busqueda_almacen); ?>" en el campo "<?php echo htmlspecialchars($campos_busqueda_config_almacen[$campo_seleccionado_key_almacen]['display']); ?>".</p>
                    <?php else: ?>
                        <p>No hay almacenes registrados aún. Puedes <a href="<?php echo BASE_URL; ?>/includes/functions/almacen/create_almacen.php">crear uno nuevo</a>.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </body>
</html>