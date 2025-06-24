<?php
require_once __DIR__ . '/config.php';

// Configuración de la base de datos
if (!defined('DB_HOST')) define('DB_HOST', 'web_saasmenu');
if (!defined('DB_NAME')) define('DB_NAME', 'tumenufast');
if (!defined('DB_USER')) define('DB_USER', 'user_menufast');
if (!defined('DB_PASS')) define('DB_PASS', '.Santiago10');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
if (!defined('DB_PORT')) define('DB_PORT', '3306'); // Puerto por defecto de MySQL
if (!defined('DB_COLLATE')) define('DB_COLLATE', 'utf8mb4_unicode_ci');

// Ruta base para subida de archivos
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', __DIR__ . '/../uploads/');

try {
    // Crear conexión PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET . ";port=" . DB_PORT;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // Log del error
    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    
    // Mostrar mensaje de error amigable
    die("Lo sentimos, ha ocurrido un error al conectar con la base de datos. Por favor, intenta más tarde.");
}

// Función para verificar la conexión
function checkDatabaseConnection() {
    global $conn;
    try {
        $conn->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Verificar la conexión al cargar el archivo
if (!checkDatabaseConnection()) {
    die("Error: No se pudo establecer conexión con la base de datos. Por favor, verifica la configuración.");
}

// Incluir funciones auxiliares
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../includes/auth.php';
