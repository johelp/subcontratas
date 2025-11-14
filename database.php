<?php
// =======================================================
// Configuración de Base de Datos para GitHub Codespaces
// HOST: 'db' es el nombre del servicio MySQL en docker-compose
// =======================================================
define('DB_HOST', 'db'); 
define('DB_USER', 'user'); 
define('DB_PASS', 'password');
define('DB_NAME', 'snow_subcontratas');

// Zona horaria
date_default_timezone_set('Europe/Madrid');

// Configuración de sesión
session_start();

// Conexión a la base de datos
try {
    // Intenta establecer la conexión usando las constantes ajustadas
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        // Si hay error de conexión (ej: DB no levantada, credenciales incorrectas)
        die("Error de conexión a la base de datos: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Captura otras excepciones
    die("Error inesperado: " . $e->getMessage());
}

// Función para verificar si el usuario está logueado
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Función para verificar si es admin
function esAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

// Función para limpiar inputs
function limpiar($data) {
    global $conn;
    // Utiliza real_escape_string y elimina etiquetas HTML
    return $conn->real_escape_string(strip_tags(trim($data)));
}
?>