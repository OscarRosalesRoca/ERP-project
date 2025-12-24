<?php
require_once("../../config/config_path.php");

require_once("../../includes/connection.php");
require_once("../../includes/auth.php");

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Dedinimos los campos por los que se puede buscar para proveedores
// La clave es el nombre que tiene en la base de datos
// En el array con "display" es lo que ve el usuario y "column" la columna real en la base de datos

$campos_busqueda_config_proveedor = [
    "cod_actor" => ["display" => "Código", "column" => "cod_actor"],
    "nombre"    => ["display" => "Nombre", "column" => "nombre"],
    "nif_dni"   => ["display" => "NIF", "column" => "nif_dni"],
    "poblacion" => ["display" => "Población", "column" => "poblacion"],
    "telefono"  => ["display" => "Teléfono", "column" => "telefono"],
    "mail"      => ["display" => "Email", "column" => "mail"]
];

// Valores iniciales para la búsqueda

$campo_seleccionado_key_proveedor = "cod_actor"; 
$termino_busqueda_proveedor = "";
$proveedores = []; // Array para almacenar los proveedores recuperados
$busqueda_activa_proveedor = false; // Para saber si se está realizando una búsqueda

$sql_base_proveedor = "SELECT cod_actor, nombre, nif_dni, poblacion, direccion, telefono, mail 
                        FROM proveedores_clientes 
                        WHERE tipo = 'proveedor'"; 
$sql_final_proveedor = $sql_base_proveedor;
$params_proveedor = []; 
$types_proveedor = "";  

// Verificar si se envió el formulario de búsqueda y si el término de búsqueda no está vacío

if (isset($_GET["buscar"]) && isset($_GET["termino"]) && trim($_GET["termino"]) !== "") {
    $busqueda_activa_proveedor = true;

    // Validar y obtener el campo de búsqueda seleccionado

    if (isset($_GET["campo"]) && array_key_exists($_GET["campo"], $campos_busqueda_config_proveedor)) {
        $campo_seleccionado_key_proveedor = $_GET["campo"];
    }

    // Obtener y limpiar el término de búsqueda

    $termino_busqueda_proveedor = trim($_GET["termino"]);
    $columna_a_buscar_proveedor = $campos_busqueda_config_proveedor[$campo_seleccionado_key_proveedor]["column"];

    // Modificar la consulta SQL para la búsqueda

    if ($columna_a_buscar_proveedor == "cod_actor") { 
        $sql_final_proveedor .= " AND " . $columna_a_buscar_proveedor . " = ?";
        $params_proveedor[] = $termino_busqueda_proveedor;
        $types_proveedor .= "i"; 
    } else { // Búsqueda parcial LIKE para otros campos de texto
        $sql_final_proveedor .= " AND " . $columna_a_buscar_proveedor . " LIKE ?";
        $params_proveedor[] = "%" . $termino_busqueda_proveedor . "%";
        $types_proveedor .= "s";
    }

    // Si hay búsqueda activa, ordenamos por el campo de búsqueda o nombre

    $sql_final_proveedor .= " ORDER BY " . $columna_a_buscar_proveedor . " ASC, nombre ASC";

} else {

    // Si no hay búsqueda activa (carga inicial o después de limpiar), ordenar por cod_actor

    $sql_final_proveedor .= " ORDER BY cod_actor ASC";

    // Si el usuario hizo clic en buscar pero el término estaba vacío, se considera "limpiar"

    if (isset($_GET['buscar']) && trim($_GET['termino']) === "") {
        $termino_busqueda_proveedor = ""; 
        $campo_seleccionado_key_proveedor = "cod_actor"; 
    }
}

if (!isset($connection) || $connection === null) {
    die("<p>Error crítico: La conexión a la base de datos no está disponible en proveedores.php.</p>");
}
$stmt_proveedor = $connection->prepare($sql_final_proveedor);

if ($stmt_proveedor) {

    // Si hay parámetros (es decir, si se está buscando y hay término), vincularlos

    if (!empty($params_proveedor)) {
        $stmt_proveedor->bind_param($types_proveedor, ...$params_proveedor);
    }

    if ($stmt_proveedor->execute()) {
        $resultado_proveedor = $stmt_proveedor->get_result();
        if ($resultado_proveedor) {

            // Obtener todos los proveedores como un array asociativo

            $proveedores = $resultado_proveedor->fetch_all(MYSQLI_ASSOC);
        } else {
            die("<p>Error al obtener resultados de proveedores: " . $connection->error . "</p>");
        }
        $stmt_proveedor->close(); 
    } else {
        die("<p>Error al ejecutar la consulta de proveedores: " . $stmt_proveedor->error . "</p>");
    }
} else {
    die("<p>Error al preparar la consulta de proveedores: " . $connection->error . "</p>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proveedores</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/home_style/sections_style/general_sections_style.css">
</head>
<body>
<div class="general_container">
    <h2>Proveedores</h2>

    <div class="cabecera_acciones">
        <div class="contenedor_busqueda">
            <form action="<?php echo BASE_URL; ?>/modules/home/empleado_home.php" method="GET" class="formulario_busqueda">
                <input type="hidden" name="pagina" value="proveedores"> <label for="campo_busqueda_proveedor">Buscar por:</label>
                <select name="campo" id="campo_busqueda_proveedor">
                    <?php foreach ($campos_busqueda_config_proveedor as $key => $config): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php if ($campo_seleccionado_key_proveedor == $key) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($config["display"]); // Mostrar 'display' al usuario ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="termino" value="<?php echo htmlspecialchars($termino_busqueda_proveedor); ?>" placeholder="Introduce término...">
                <input type="submit" name="buscar" value="Buscar">
                <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=proveedores" class="boton_limpiar">Limpiar</a>
            </form>
        </div>

        <div class="nuevo_general">
            <a href="<?php echo BASE_URL; ?>/includes/functions/proveedor/create_proveedor.php">+ Nuevo proveedor</a>
        </div>
    </div>

    <?php if (!empty($proveedores)): ?>
        <table class="tabla_general">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>NIF</th> <th>Población</th>
                    <th>Dirección</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Editar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proveedores as $proveedor): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($proveedor["cod_actor"]); ?></td>
                        <td><?php echo htmlspecialchars($proveedor["nombre"]); ?></td>
                        <td><?php echo htmlspecialchars($proveedor["nif_dni"]); ?></td>
                        <td><?php echo htmlspecialchars($proveedor["poblacion"]); ?></td>
                        <td><?php echo htmlspecialchars($proveedor["direccion"]); ?></td>
                        <td><?php echo htmlspecialchars($proveedor["telefono"]); ?></td>
                        <td><?php echo htmlspecialchars($proveedor["mail"]); ?></td>
                        <td class="editar">
                            <a href="<?php echo BASE_URL; ?>/includes/functions/proveedor/edit_delete_proveedor.php?cod=<?php echo urlencode($proveedor["cod_actor"]); ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="sin_resultados">
            <?php if ($busqueda_activa_proveedor): ?>
                <p style="color: red;">No hay proveedores que coincidan con la búsqueda "<?php echo htmlspecialchars($termino_busqueda_proveedor); ?>" en el campo "<?php echo htmlspecialchars($campos_busqueda_config_proveedor[$campo_seleccionado_key_proveedor]["display"]); ?>".</p>
            <?php else: ?>
                <p>No hay proveedores registrados aún. Puedes <a href="<?php echo BASE_URL; ?>/includes/functions/proveedor/create_proveedor.php">crear uno nuevo</a>.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>