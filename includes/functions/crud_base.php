<?php
require_once __DIR__ . "/../includes/conexion.php";

function insertar($tabla, $datos) {
    global $conn;
    $columnas = implode(", ", array_keys($datos));
    $valores = implode("', '", array_map([$conn, "real_escape_string"], array_values($datos)));
    $sql = "INSERT INTO $tabla ($columnas) VALUES ('$valores')";
    return $conn->query($sql);
}

function actualizar($tabla, $datos, $condicion) {
    global $conn;
    $set = [];
    foreach ($datos as $col => $val) {
        $val = $conn->real_escape_string($val);
        $set[] = "$col = '$val'";
    }
    $sql = "UPDATE $tabla SET " . implode(", ", $set) . " WHERE $condicion";
    return $conn->query($sql);
}

function eliminar($tabla, $condicion) {
    global $conn;
    $sql = "DELETE FROM $tabla WHERE $condicion";
    return $conn->query($sql);
}

function obtenerPorId($tabla, $columna, $valor) {
    global $conn;
    $valor = $conn->real_escape_string($valor);
    $sql = "SELECT * FROM $tabla WHERE $columna = '$valor' LIMIT 1";
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}
