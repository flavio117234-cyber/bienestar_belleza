<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "bd_bienestar_belleza";
$port = 3307;

$conexion = new mysqli($host, $user, $password, $database, $port);

if ($conexion ->connect_error){
    die("Error en la conexión a la base de datos: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");
?>