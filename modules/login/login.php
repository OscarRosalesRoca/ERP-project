<?php
session_start();

if (isset($_SESSION["usuario_id"])) {
    header("Location: /ERP/modules/home/empleado_home.php"); 
    exit;
}

require_once ("../../includes/connection.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $contrasenia = trim($_POST["contrasenia"]);

    $stmt = $connection->prepare("SELECT id, contrasenia, rol_id, nombre_usuario FROM usuarios WHERE nombre_usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $fila = $resultado->fetch_assoc();

        if (password_verify($contrasenia, $fila["contrasenia"])) {
            // Login correcto
            $_SESSION["nombre_usuario"] = $fila["nombre_usuario"];
            $_SESSION["usuario_id"] = $fila["id"];
            $_SESSION["rol_id"] = $fila["rol_id"];
            $_SESSION["ultimo_acceso"] = time();

            // Redirección por rol
            switch ($fila["rol_id"]) {
                case 1:
                    header("Location: /ERP/modules/home/admin_home.php");
                    break;
                case 2:
                    header("Location: /ERP/modules/home/empleado_home.php");
                    break;
                case 3:
                    header("Location: /ERP/modules/home/cliente_home.php");
                    break;
                default:
                    $error = "Rol de usuario desconocido.";
                    break;
            }
            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Usuario no encontrado.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="/ERP/assets/css/modules_style/general_login_register_styles.css">
    <link rel="stylesheet" href="/ERP/assets/css/modules_style/login_style/style_login.css">
</head>
<body>
    <div class="card">
        <h2>Iniciar Sesión</h2>
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="post">
            <label for="usuario">Usuario:</label>
            <input type="text" name="usuario" id="usuario" required>

            <label for="contrasenia">Contraseña:</label>
            <div class="password_wrapper">
                <input type="password" name="contrasenia" id="contrasenia" required>
                <input type="checkbox" id="togglePassword"> Mostrar
            </div>

            <button class="button" type="submit">Iniciar sesión</button>
        </form>
        <p class="registro_link">¿No tienes una cuenta? <a class="link" href="/ERP/modules/register/register.php">Regístrate</a></p>
    </div>

    <script>
        const toggle = document.getElementById("togglePassword");
        const password = document.getElementById("contrasenia");
        toggle.addEventListener("change", () => {
            password.type = toggle.checked ? "text" : "password";
        });
    </script>
</body>
</html>