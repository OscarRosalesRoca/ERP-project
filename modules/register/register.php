<?php

require_once(__DIR__ . "/../../config/config_path.php"); 
require_once(__DIR__ . "/../../includes/connection.php");

$errores = "";
$exito = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"]);
    $mail = trim($_POST["mail"]);
    $telefono = trim($_POST["telefono"]);
    $dni = trim($_POST["dni"]);
    $contrasenia = trim($_POST["contrasenia"]);

    // Validación básica
    if (empty($nombre) || empty($mail) || empty($telefono) || empty($dni) || empty($contrasenia)) {
        $errores = "Todos los campos son obligatorios.";
    } else {
        // Comprobar que nombre no exista ni en empleados ni en usuarios
        $sql = "SELECT * FROM empleado WHERE nombre = ?";
        $stmt = $connection->prepare($sql);
        if (!$stmt) {
            die("Error al preparar la consulta SQL: " . $connection->error);
        }
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $resultado_empleado = $stmt->get_result();
        $stmt->close();

        $sql2 = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
        $stmt2 = $connection->prepare($sql2);
        if (!$stmt2) {
            die("Error al preparar la consulta SQL: " . $connection->error);
        }
        $stmt2->bind_param("s", $nombre);
        $stmt2->execute();
        $resultado_usuario = $stmt2->get_result();
        $stmt2->close();

        if ($resultado_empleado->num_rows > 0 || $resultado_usuario->num_rows > 0) {
            $errores = "El nombre de usuario ya está en uso.";
        } else {

            require_once(__DIR__ ."/../../includes/functions/empleado/create_empleado.php");

            if (createEmpleado($nombre, $mail, $telefono, $dni, $contrasenia)) {
                header("Location: " . BASE_URL . "/modules/login/login.php?registro=ok");
                exit;
            } else {
                $errores = "Hubo un problema al registrar. Inténtalo de nuevo.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - ERP</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/general_login_register_styles.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/modules_style/register_style/style_register.css">
</head>
<body>
    <div class="card">
        <h2>Registro</h2>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nombre completo" required>
            <input type="email" name="mail" placeholder="Correo electrónico" required>
            <input type="text" name="telefono" placeholder="Teléfono" required>
            <input type="text" name="dni" placeholder="DNI" required>
            <div class="password_wrapper">
                <input type="password" name="contrasenia" id="contrasenia" required placeholder="Contraseña">
                <input type="checkbox" id="togglePassword"> Mostrar
            </div>
            <button class="button" type="submit">Registrar</button>
        </form>
        <?php if ($errores): ?>
            <p class="error"><?= $errores ?></p>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword() {
            const passInput = document.getElementById("contrasena");
            passInput.type = passInput.type === "password" ? "text" : "password";
        }
    </script>
</body>
</html>