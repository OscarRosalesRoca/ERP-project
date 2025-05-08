<?php
require_once("../../../includes/connection.php");
require_once("../../../includes/auth.php");

$cod_actor = $_GET["cod"] ?? null;

if (!$cod_actor) {
    echo "<p>Error: proveedor no especificado.</p>";
    exit;
}

// Obtener datos actuales del cliente
$query = "SELECT * FROM proveedores_clientes WHERE cod_actor = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $cod_actor);
$stmt->execute();
$resultado = $stmt->get_result();
$proveedor = $resultado->fetch_assoc();

if (!$proveedor) {
    echo "<p>Error: proveedor no encontrado.</p>";
    exit;
}

$errores = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["eliminar_proveedor"])) {
    // Eliminar proveedor
    $stmt = $connection->prepare("DELETE FROM proveedores_clientes WHERE cod_actor = ?");
    $stmt->bind_param("i", $cod_actor);
    $stmt->execute();

    header("Location: /ERP/modules/home/empleado_home.php?pagina=proveedores");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"]);
    $dni = trim($_POST["nif_dni"]);
    $poblacion = trim($_POST["poblacion"]);
    $direccion = trim($_POST["direccion"]);
    $mail = trim($_POST["mail"]);
    $telefono = trim($_POST["telefono"]);

    // Validaciones básicas
    if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido.";
    }

    // Si no hay errores, procesamos actualizaciones
    if (empty($errores)) {
        // Nombre: solo actualizar si se ha cambiado
        if (!empty($nombre) && $nombre !== $proveedor["nombre"]) {
            $stmt = $connection->prepare("UPDATE proveedores_clientes SET nombre = ? WHERE cod_actor = ?");
            $stmt->bind_param("si", $nombre, $cod_actor);
            $stmt->execute();
        }

        // DNI: solo actualizar si se ha cambiado
        if (!empty($dni) && $dni !== $proveedor["nif_dni"]) {
            $stmt = $connection->prepare("UPDATE proveedores_clientes SET nif_dni = ? WHERE cod_actor = ?");
            $stmt->bind_param("si", $dni, $cod_actor);
            $stmt->execute();
        }

        // Población: solo actualizar si se ha cambiado
        if (!empty($poblacion) && $poblacion !== $proveedor["poblacion"]) {
            $stmt = $connection->prepare("UPDATE proveedores_clientes SET poblacion = ? WHERE cod_actor = ?");
            $stmt->bind_param("si", $poblacion, $cod_actor);
            $stmt->execute();
        }

        // Dirección: solo actualizar si se ha cambiado
        if (!empty($direccion) && $direccion !== $proveedor["direccion"]) {
            $stmt = $connection->prepare("UPDATE proveedores_clientes SET direccion = ? WHERE cod_actor = ?");
            $stmt->bind_param("si", $direccion, $cod_actor);
            $stmt->execute();
        }

        // Correo electrónico: solo actualizar si se ha cambiado
        if (!empty($mail) && $mail !== $proveedor["mail"]) {
            $stmt = $connection->prepare("UPDATE proveedores_clientes SET mail = ? WHERE cod_actor = ?");
            $stmt->bind_param("si", $mail, $cod_actor);
            $stmt->execute();
        }

        // Teléfono: solo actualizar si se ha cambiado
        if (!empty($telefono) && $telefono !== $proveedor["telefono"]) {
            $stmt = $connection->prepare("UPDATE proveedores_clientes SET telefono = ? WHERE cod_actor = ?");
            $stmt->bind_param("si", $telefono, $cod_actor);
            $stmt->execute();
        }
    
        header("Location: /ERP/modules/home/empleado_home.php?pagina=proveedores");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar proveedores</title>
    <link rel="stylesheet" href="/ERP/assets/css/functions_style/general_create_edit_delete_style.css">
</head>
<body>
<div class="fondo">
    <div class="card">
        <h2>Editar proveedores</h2>

        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Nombre</label>
            <input type="text" name="nombre" placeholder="<?= htmlspecialchars($proveedor["nombre"]) ?>">

            <label>DNI</label>
            <input type="text" name="nif_dni" placeholder="<?= htmlspecialchars($proveedor["nif_dni"]) ?>">

            <label>Población</label>
            <input type="text" name="poblacion" placeholder="<?= htmlspecialchars($proveedor["poblacion"]) ?>">

            <label>Dirección</label>
            <input type="text" name="direccion" placeholder="<?= htmlspecialchars($proveedor["direccion"]) ?>">

            <label>Correo electrónico</label>
            <input type="email" name="mail" placeholder="<?= htmlspecialchars($proveedor["mail"]) ?>">

            <label>Teléfono</label>
            <input type="text" name="telefono" placeholder="<?= htmlspecialchars($proveedor["telefono"]) ?>">

            <div class="botones">
                <button type="submit">Guardar cambios</button>
                <p>o</p>
                <button type="submit" name="eliminar_proveedor" class="eliminar_boton" onclick="return confirm('¿Eliminar proveedor? Esta acción no se puede deshacer.')">
                    <p>Eliminar proveedor</p>
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>