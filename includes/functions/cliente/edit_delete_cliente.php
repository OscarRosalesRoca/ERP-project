<?php

require_once("../../../config/config_path.php");
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$cod_actor = $_GET["cod"] ?? null;

if (!$cod_actor) {
    echo "<p>Error: cliente no especificado.</p>";
    exit;
}

// Obtener datos actuales del cliente para rellenar el formulario inicialmente
$query = "SELECT * FROM proveedores_clientes WHERE cod_actor = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $cod_actor);
$stmt->execute();
$resultado = $stmt->get_result();
$cliente = $resultado->fetch_assoc();

if (!$cliente) {
    echo "<p>Error: cliente no encontrado.</p>";
    exit;
}

$errores = [];

// Borrado
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_cliente"])) {
    // Nota: Aquí se podría añadir validación para no borrar si tiene facturas asociadas
    $stmt = $connection->prepare("DELETE FROM proveedores_clientes WHERE cod_actor = ?");
    $stmt->bind_param("i", $cod_actor);
    
    if ($stmt->execute()) {
        header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=clientes&mensaje=eliminado");
        exit;
    } else {
        $errores[] = "Error al eliminar: " . $connection->error;
    }
}

// Edición
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["eliminar_cliente"])) {
    $nombre = trim($_POST["nombre"]);
    $nif_dni = trim($_POST["nif_dni"]);
    $poblacion = trim($_POST["poblacion"]);
    $direccion = trim($_POST["direccion"]);
    $mail = trim($_POST["mail"]);
    $telefono = trim($_POST["telefono"]);

    // Validación básica de email
    if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido.";
    }

    // Validar duplicados (dni, mail y teléfono) en otros registros
    // La clave es "AND cod_actor != ?" para ignorar el propio registro que estamos editando
    if (empty($errores)) {
        $campos_repetidos = [];
        
        $sql_check = "SELECT nif_dni, mail, telefono FROM proveedores_clientes WHERE (nif_dni = ? OR mail = ? OR telefono = ?) AND cod_actor != ?";
        $stmt_check = $connection->prepare($sql_check);
        $stmt_check->bind_param("sssi", $nif_dni, $mail, $telefono, $cod_actor);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        while ($fila = $res_check->fetch_assoc()) {
            if (strtolower($fila['nif_dni']) === strtolower($nif_dni)) {
                $campos_repetidos[] = "DNI";
            }
            if (strtolower($fila['mail']) === strtolower($mail)) {
                $campos_repetidos[] = "correo electrónico";
            }
            if ($fila['telefono'] === $telefono) {
                $campos_repetidos[] = "teléfono";
            }
        }
        $stmt_check->close();

        if (count($campos_repetidos) > 0) {
            $unique_errors = array_unique($campos_repetidos);
            $lista = implode(" y ", $unique_errors);
            
            $errores[] = "Los datos que ha introducido ($lista) ya están registrados y por lo tanto no son válidos.";
        }
    }

    // Actualizar si no hay errores
    if (empty($errores)) {
        $sql_update = "UPDATE proveedores_clientes SET nombre=?, nif_dni=?, poblacion=?, direccion=?, mail=?, telefono=? WHERE cod_actor=?";
        $stmt_update = $connection->prepare($sql_update);
        $stmt_update->bind_param("ssssssi", $nombre, $nif_dni, $poblacion, $direccion, $mail, $telefono, $cod_actor);
        
        if ($stmt_update->execute()) {
            header("Location: " . BASE_URL . "/modules/home/empleado_home.php?pagina=clientes&mensaje=actualizado");
            exit;
        } else {
            $errores[] = "Error al actualizar: " . $connection->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar cliente</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar cliente</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Nombre</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($_POST["nombre"] ?? $cliente["nombre"]) ?>" required>

            <label>DNI</label>
            <input type="text" name="nif_dni" value="<?= htmlspecialchars($_POST["nif_dni"] ?? $cliente["nif_dni"]) ?>" required>

            <label>Población</label>
            <input type="text" name="poblacion" value="<?= htmlspecialchars($_POST["poblacion"] ?? $cliente["poblacion"]) ?>">

            <label>Dirección</label>
            <input type="text" name="direccion" value="<?= htmlspecialchars($_POST["direccion"] ?? $cliente["direccion"]) ?>" required>

            <label>Correo electrónico</label>
            <input type="email" name="mail" value="<?= htmlspecialchars($_POST["mail"] ?? $cliente["mail"]) ?>" required>

            <label>Teléfono</label>
            <input type="text" name="telefono" value="<?= htmlspecialchars($_POST["telefono"] ?? $cliente["telefono"]) ?>" required>

            <div class="botones">
                <button type="submit">Guardar cambios</button>
                <p>o</p>
                <button type="submit" name="eliminar_cliente" class="eliminar_boton" onclick="return confirm('¿Eliminar cliente? Esta acción no se puede deshacer.')">
                    <p>Eliminar cliente</p>
                </button>
            </div>
            <div style="margin-top: 10px;">
                <a href="<?php echo BASE_URL; ?>/modules/home/empleado_home.php?pagina=clientes" class="back_button">Cancelar</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>