<?php
require_once ("../../../includes/connection.php");

function createEmpleado($nombre, $mail, $telefono, $dni, $contrasenia) {
    global $connection;

    // Encriptar la contraseña con password_hash
    $contraseniaHash = password_hash($contrasenia, PASSWORD_DEFAULT);

    // Transacción para asegurar que ambos inserts se completan
    $connection->begin_transaction();

    try {
        // Insertar en la tabla usuarios con rol 2 (empleado)
        $stmt = $connection->prepare("INSERT INTO usuarios (nombre_usuario, contrasenia, rol_id) VALUES (?, ?, 2)");
        $stmt->bind_param("ss", $nombre, $contraseniaHash);
        $stmt->execute();
        $usuario_id = $stmt->insert_id;  // Obtiene el último id insertado
        $stmt->close();

        // Insertar en la tabla empleado
        $stmt = $connection->prepare("INSERT INTO empleado (nombre, mail, telefono, dni, contrasenia, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $nombre, $mail, $telefono, $dni, $contraseniaHash, $usuario_id);
        $stmt->execute();
        $stmt->close();

        // Confirmar la transacción
        $connection->commit();

        return true;
    } catch (Exception $e) {
        // Cancelar la transacción en caso de error
        $connection->rollback();
        return false;
    }
}
?>