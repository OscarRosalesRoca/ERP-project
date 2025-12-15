<?php
require_once("../../config/config_path.php");

require_once("../../includes/connection.php"); // Conexión a la BD
require_once("../../includes/auth.php");       // Autenticación y control de sesión

// Iniciar sesión si no está iniciada (auth.php ya debería hacerlo)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definir los campos por los que se puede buscar para clientes
$campos_busqueda_config = [
    'cod_actor' => ['display' => 'Código', 'column' => 'cod_actor'],
    'nombre'    => ['display' => 'Nombre', 'column' => 'nombre'],
    'nif_dni'   => ['display' => 'DNI', 'column' => 'nif_dni'],
    'poblacion' => ['display' => 'Población', 'column' => 'poblacion'],
    'telefono'  => ['display' => 'Teléfono', 'column' => 'telefono'],
    'mail'      => ['display' => 'Email', 'column' => 'mail']
];

// Valores iniciales para la búsqueda
$campo_seleccionado_key = 'cod_actor';
$termino_busqueda = '';
$clientes = [];
$busqueda_activa = false;

// Construir la consulta SQL base
$sql_base = "SELECT cod_actor, nombre, nif_dni, poblacion, direccion, telefono, mail 
            FROM proveedores_clientes 
            WHERE tipo = 'cliente'";
$sql_final = $sql_base;
$params = [];
$types = "";

// Verificar si se envió el formulario de búsqueda (parámetro 'buscar' en la URL)
// y si el término de búsqueda no está vacío.
if (isset($_GET['buscar']) && isset($_GET['termino']) && trim($_GET['termino']) !== '') {
    $busqueda_activa = true;
    if (isset($_GET['campo']) && array_key_exists($_GET['campo'], $campos_busqueda_config)) {
        $campo_seleccionado_key = $_GET['campo'];
    }
    $termino_busqueda = trim($_GET['termino']);
    $columna_a_buscar = $campos_busqueda_config[$campo_seleccionado_key]['column'];

    if ($columna_a_buscar == 'cod_actor') {
        $sql_final .= " AND " . $columna_a_buscar . " = ?";
        $params[] = $termino_busqueda;
        $types .= "i";
    } else {
        $sql_final .= " AND " . $columna_a_buscar . " LIKE ?";
        $params[] = "%" . $termino_busqueda . "%";
        $types .= "s";
    }
    $sql_final .= " ORDER BY " . $columna_a_buscar . " ASC, nombre ASC";

} else {
    $sql_final .= " ORDER BY cod_actor ASC";
    if (isset($_GET['buscar']) && trim($_GET['termino']) === '') {
        // Si se hizo clic en buscar con término vacío, se resetea
        $termino_busqueda = '';
        $campo_seleccionado_key = 'cod_actor';
    }
}

// Preparar y ejecutar la consulta
// Asegurarse de que $connection está disponible
if (!isset($connection) || $connection === null) {
    die("<p>Error: La conexión a la base de datos no está disponible. Revisa la inclusión de 'connection.php'.</p>");
}

$stmt = $connection->prepare($sql_final);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        if ($resultado) {
            $clientes = $resultado->fetch_all(MYSQLI_ASSOC);
        } else {
            die("<p>Error al obtener resultados: " . $connection->error . "</p>");
        }
        $stmt->close();
    } else {
        die("<p>Error al ejecutar la consulta: " . $stmt->error . "</p>");
    }
} else {
    die("<p>Error al preparar la consulta: " . $connection->error . "</p>");
}
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Clientes</title>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/sections_style/general_sections_style.css">
    </head>
    <body>
        <div class="general_container">
            <h2>Clientes</h2>

            <div class="cabecera_acciones">
                <div class="contenedor_busqueda">
                    <form action="<?php echo BASE_URL; ?>/modules/home/empleado_home.php" method="GET" class="formulario_busqueda">
                        <input type="hidden" name="pagina" value="clientes">
                        
                        <label for="campo_busqueda">Buscar por:</label>
                        <select name="campo" id="campo_busqueda">
                            <?php foreach ($campos_busqueda_config as $key => $config): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php if ($campo_seleccionado_key == $key) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($config['display']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="text" name="termino" value="<?php echo htmlspecialchars($termino_busqueda); ?>" placeholder="Introduce término...">
                        <input type="submit" name="buscar" value="Buscar">
                        <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=clientes" class="boton_limpiar">Limpiar</a>
                    </form>
                </div>

                <div class="nuevo_general">
                    <a href="<?php echo BASE_URL; ?>/includes/functions/cliente/create_cliente.php">+ Nuevo cliente</a>
                </div>
            </div>

            <?php if (!empty($clientes)): ?>
                <table class="tabla_general">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>DNI</th>
                            <th>Población</th>
                            <th>Dirección</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Editar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente["cod_actor"]); ?></td>
                                <td><?php echo htmlspecialchars($cliente["nombre"]); ?></td>
                                <td><?php echo htmlspecialchars($cliente["nif_dni"]); ?></td>
                                <td><?php echo htmlspecialchars($cliente["poblacion"]); ?></td>
                                <td><?php echo htmlspecialchars($cliente["direccion"]); ?></td>
                                <td><?php echo htmlspecialchars($cliente["telefono"]); ?></td>
                                <td><?php echo htmlspecialchars($cliente["mail"]); ?></td>
                                <td class="editar">
                                    <a href="<?php echo BASE_URL; ?>/includes/functions/cliente/edit_delete_cliente.php?cod=<?php echo urlencode($cliente["cod_actor"]); ?>">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="sin_resultados">
                    <?php if ($busqueda_activa): ?>
                        <p style="color: red;">No hay clientes que coincidan con la búsqueda "<?php echo htmlspecialchars($termino_busqueda); ?>" en el campo "<?php echo htmlspecialchars($campos_busqueda_config[$campo_seleccionado_key]['display']); ?>".</p>
                    <?php else: ?>
                        <p>No hay clientes registrados aún. Puedes <a href="<?php echo BASE_URL; ?>/includes/functions/cliente/create_cliente.php">crear uno nuevo</a>.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </body>
</html>