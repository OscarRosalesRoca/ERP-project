<?php

require_once(__DIR__ . "/../../../config/config_path.php");
require_once(__DIR__ . "/../../connection.php");

function createEmpleado($nombre, $mail, $telefono, $dni, $contrasenia) {
    global $connection;

    // Encriptar la contraseña con password_hash
    $contraseniaHash = password_hash($contrasenia, PASSWORD_DEFAULT);

    // Transacción para asegurar que ambos inserts se completan
    $connection->begin_transaction();

    try {
        // Insertar en la tabla usuarios con rol 2 (empleado)
        $stmt = $connection->prepare("INSERT INTO usuarios (nombre_usuario, contrasenia, rol_id) VALUES (?, ?, 2)");
        if (!$stmt) {
            die("Error en prepare (usuarios): " . $connection->error);
        }
        $stmt->bind_param("ss", $nombre, $contraseniaHash);
        $stmt->execute();
        $usuario_id = $stmt->insert_id;  // Obtiene el último id insertado
        $stmt->close();

        // Insertar en la tabla empleado
        $stmt = $connection->prepare("INSERT INTO empleado (nombre, mail, telefono, dni, contrasenia, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            die("Error en prepare (usuarios): " . $connection->error);
        }
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