<?php
require_once(__DIR__ . "/../../../config/config_path.php");
require_once(__DIR__ . "/../../../includes/connection.php");

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['eliminar_empleado_id'])) {
    $id_emp_borrar = intval($_POST['eliminar_empleado_id']);
    $id_usu_borrar = intval($_POST['usuario_asociado_id']);

    if ($id_usu_borrar == $_SESSION['usuario_id']) {
        echo "<script>alert('No puedes eliminar tu propia cuenta desde aquí.');</script>";
    } else {
        $connection->begin_transaction();
        try {
            $stmt = $connection->prepare("DELETE FROM empleado WHERE cod_empleado = ?");
            $stmt->bind_param("i", $id_emp_borrar);
            $stmt->execute();
            $stmt->close();
            $stmt = $connection->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $id_usu_borrar);
            $stmt->execute();
            $stmt->close();
            $connection->commit();
            echo "<script>window.location.href = window.location.href;</script>";
            exit;
        } catch (Exception $e) {
            $connection->rollback();
            echo "<script>alert('Error al eliminar: " . $e->getMessage() . "');</script>";
        }
    }
}

$campos_busqueda_config_personal = [
    'nombre' => ['display' => 'Nombre', 'column' => 'e.nombre'],
    'dni' => ['display' => 'DNI', 'column' => 'e.dni'],
    'nombre_usuario' => ['display' => 'Usuario', 'column' => 'u.nombre_usuario'],
    'mail' => ['display' => 'Email', 'column' => 'e.mail']
];

$campo_seleccionado_key_personal = 'nombre';
$termino_busqueda_personal = '';
$empleados = [];
$busqueda_activa_personal = false;

$sql_base_personal = "
    SELECT e.cod_empleado, e.nombre, e.dni, e.mail, e.telefono, u.id AS usuario_id, u.nombre_usuario, u.foto_perfil, r.nombre_rol
    FROM empleado e
    JOIN usuarios u ON e.usuario_id = u.id
    JOIN roles r ON u.rol_id = r.id
";

$sql_conditions_personal = [];
$params_personal = [];
$types_personal = "";

if (isset($_GET['buscar']) && isset($_GET['termino']) && trim($_GET['termino']) !== '') {
    $busqueda_activa_personal = true;
    if (isset($_GET['campo']) && array_key_exists($_GET['campo'], $campos_busqueda_config_personal)) {
        $campo_seleccionado_key_personal = $_GET['campo'];
    }
    $termino_busqueda_personal = trim($_GET['termino']);
    $columna_a_buscar = $campos_busqueda_config_personal[$campo_seleccionado_key_personal]['column'];
    $sql_conditions_personal[] = $columna_a_buscar . " LIKE ?";
    $params_personal[] = "%" . $termino_busqueda_personal . "%";
    $types_personal .= "s";
}

$sql_final_personal = $sql_base_personal;
if (!empty($sql_conditions_personal)) {
    $sql_final_personal .= " WHERE " . implode(" AND ", $sql_conditions_personal);
}
$sql_final_personal .= " ORDER BY e.nombre ASC";

if (!isset($connection) || $connection === null) { die("Error DB"); }

$stmt_personal = $connection->prepare($sql_final_personal);
if ($stmt_personal) {
    if (!empty($params_personal)) { $stmt_personal->bind_param($types_personal, ...$params_personal); }
    if ($stmt_personal->execute()) {
        $resultado = $stmt_personal->get_result();
        if ($resultado) { $empleados = $resultado->fetch_all(MYSQLI_ASSOC); }
    }
    $stmt_personal->close();
}
?>

<div class="general_container">
    <h2>Listado de Personal</h2>
    <div class="cabecera_acciones">
        <div class="contenedor_busqueda">
            <form action="<?php echo BASE_URL; ?>/modules/home/admin_home.php" method="GET" class="formulario_busqueda">
                <input type="hidden" name="pagina" value="personal_list">
                <label for="campo_busqueda_personal">Buscar por:</label>
                <select name="campo" id="campo_busqueda_personal">
                    <?php foreach ($campos_busqueda_config_personal as $key => $config): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php if ($campo_seleccionado_key_personal == $key) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($config['display']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="termino" value="<?php echo htmlspecialchars($termino_busqueda_personal); ?>" placeholder="Introduce término...">
                <input type="submit" name="buscar" value="Buscar">
                <a href="<?php echo BASE_URL; ?>/modules/home/admin_home.php?pagina=personal_list" class="boton_limpiar">Limpiar</a>
            </form>
        </div>
        <div class="nuevo_general">
            <a href="<?php echo BASE_URL; ?>/modules/register/register.php">+ Nuevo Empleado</a>
        </div>
    </div>

    <?php if (!empty($empleados)): ?>
        <table class="tabla_general">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>DNI</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empleados as $emp): ?>
                    <?php 
                        // MODIFICACIÓN: Ruta cambiada a "fotos_perfil"
                        $ruta_foto_row = BASE_URL . "/assets/img/default_user.jpg";
                        if (!empty($emp['foto_perfil']) && $emp['foto_perfil'] !== 'default_user.jpg') {
                            $ruta_foto_row = BASE_URL . "/uploads/fotos_perfil/" . $emp['foto_perfil'];
                        }
                    ?>
                    <tr>
                        <td style="display: flex; align-items: center;">
                            <img src="<?php echo $ruta_foto_row; ?>" alt="Foto" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; margin-right: 10px; border: 1px solid #ddd;">
                            <?php echo htmlspecialchars($emp['nombre']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($emp['nombre_usuario']); ?></td>
                        <td>
                            <?php if ($emp['nombre_rol'] === 'admin'): ?>
                                <strong style="color: #c0392b;">Admin</strong>
                            <?php else: ?>
                                <span style="color: #27ae60;">Empleado</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($emp['dni']); ?></td>
                        <td><?php echo htmlspecialchars($emp['mail']); ?></td>
                        <td><?php echo htmlspecialchars($emp['telefono']); ?></td>
                        
                        <td class="acciones_celda">
                            <a href="<?php echo BASE_URL; ?>/includes/functions/empleado/edit_delete_empleado_admin.php?cod=<?php echo $emp['cod_empleado']; ?>" style="color: blue; font-weight: bold; margin-right: 10px;">Editar</a>
                            <?php if ($emp['usuario_id'] != $_SESSION['usuario_id']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar permanentemente a <?php echo htmlspecialchars($emp['nombre']); ?>?');">
                                    <input type="hidden" name="eliminar_empleado_id" value="<?php echo $emp['cod_empleado']; ?>">
                                    <input type="hidden" name="usuario_asociado_id" value="<?php echo $emp['usuario_id']; ?>">
                                    <button type="submit" style="background: none; border: none; color: red; cursor: pointer; text-decoration: underline; font-weight: bold;">Borrar</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #aaa; font-style: italic;">(Tú)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="sin_resultados">
            <p>No hay empleados registrados aún.</p>
        </div>
    <?php endif; ?>
</div>