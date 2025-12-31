<?php
// Verificamos quien registra
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/../../config/config_path.php"); 
require_once(__DIR__ . "/../../includes/connection.php");

$errores = "";
$exito = false;

// Inicializamos variables vacías
$nombre = "";
$mail = "";
$telefono = "";
$dni = "";

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
        
        $campos_repetidos = [];

        // Comprobar coincidencias en la tabla EMPLEADO (nombre, mail, telefono, dni)
        $sql = "SELECT nombre, mail, telefono, dni FROM empleado WHERE nombre = ? OR mail = ? OR telefono = ? OR dni = ?";
        $stmt = $connection->prepare($sql);
        if (!$stmt) {
            die("Error al preparar la consulta SQL: " . $connection->error);
        }
        $stmt->bind_param("ssss", $nombre, $mail, $telefono, $dni);
        $stmt->execute();
        $resultado_empleado = $stmt->get_result();
        
        // Analizamos qué campos coinciden
        while ($fila = $resultado_empleado->fetch_assoc()) {
            if (strtolower($fila['nombre']) === strtolower($nombre)) {
                $campos_repetidos[] = "nombre de usuario";
            }
            if (strtolower($fila['mail']) === strtolower($mail)) {
                $campos_repetidos[] = "correo electrónico";
            }
            if ($fila['telefono'] === $telefono) {
                $campos_repetidos[] = "teléfono";
            }
            if (strtolower($fila['dni']) === strtolower($dni)) {
                $campos_repetidos[] = "DNI";
            }
        }
        $stmt->close();

        // Comprobar coincidencias en la tabla USUARIOS (solo nombre)
        $sql2 = "SELECT nombre_usuario FROM usuarios WHERE nombre_usuario = ?";
        $stmt2 = $connection->prepare($sql2);
        if (!$stmt2) {
            die("Error al preparar la consulta SQL: " . $connection->error);
        }
        $stmt2->bind_param("s", $nombre);
        $stmt2->execute();
        $resultado_usuario = $stmt2->get_result();
        
        if ($resultado_usuario->num_rows > 0) {
            // Si ya detectamos el nombre en la tabla empleado, no lo duplicamos en el array
            if (!in_array("nombre de usuario", $campos_repetidos)) {
                $campos_repetidos[] = "nombre de usuario";
            }
        }
        $stmt2->close();

        // Evaluamos si hubo conflictos
        if (count($campos_repetidos) > 0) {
            // Eliminamos duplicados por seguridad y formamos el string
            $campos_unicos = array_unique($campos_repetidos);
            $lista_errores = implode(" y ", $campos_unicos); 
            
            $errores = "Los datos que ha introducido ($lista_errores) ya están registrados y por lo tanto no son válidos.";
            
        } else {

            require_once(__DIR__ ."/../../includes/functions/empleado/create_empleado.php");

            if (createEmpleado($nombre, $mail, $telefono, $dni, $contrasenia)) {
                if (isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == 1) {
                    header("Location: " . BASE_URL . "/modules/home/admin_home.php?pagina=personal_list&mensaje=empleado_creado");
                } else {
                    // Si es un registro normal, vamos al login
                    header("Location: " . BASE_URL . "/modules/login/login.php?registro=ok");
                }
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
            <input type="text" name="nombre" placeholder="Nombre completo" required value="<?php echo htmlspecialchars($nombre); ?>">
            <input type="email" name="mail" placeholder="Correo electrónico" required value="<?php echo htmlspecialchars($mail); ?>">
            <input type="text" name="telefono" placeholder="Teléfono" required value="<?php echo htmlspecialchars($telefono); ?>">
            <input type="text" name="dni" placeholder="DNI" required value="<?php echo htmlspecialchars($dni); ?>">
            
            <div class="password_wrapper">
                <input type="password" name="contrasenia" id="contrasenia" required placeholder="Contraseña">
                <label style="font-size: 0.9em; cursor: pointer;">
                    <input type="checkbox" id="togglePassword"> Mostrar
                </label>
            </div>
            <button class="button" type="submit">Registrar</button>
        </form>
        <?php if ($errores): ?>
            <p class="error"><?= $errores ?></p>
        <?php endif; ?>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#contrasenia');

        togglePassword.addEventListener('change', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
        });
    </script>
</body>
</html>