<?php
// Configuración de la base de datos
define('DB_HOST', 'web_saasmenu');
define('DB_USER', 'user_menufast');
define('DB_PASS', '.Santiago10');
define('DB_NAME', 'tumenufast');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');
define('DB_PORT', '3306');

// Configuración de la aplicación (producción)
// Detectar automáticamente la URL base de manera más robusta
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detectar el directorio base de la aplicación
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$request_uri = $_SERVER['REQUEST_URI'] ?? '';

// Obtener el directorio base de la aplicación
$base_path = '';
if (strpos($script_name, '/restaurante/') !== false) {
    // Si estamos en el directorio restaurante
    $base_path = dirname(dirname($script_name));
} elseif (strpos($script_name, '/super_admin/') !== false) {
    // Si estamos en el directorio super_admin
    $base_path = dirname(dirname($script_name));
} else {
    // Para otros directorios
    $base_path = dirname($script_name);
}

// Limpiar el path base
$base_path = rtrim($base_path, '/');
if ($base_path === '') {
    $base_path = '';
}

// Asegurar que la URL base sea válida
$site_url = $protocol . '://' . $host . $base_path;
if (filter_var($site_url, FILTER_VALIDATE_URL)) {
    define('SITE_URL', $site_url);
    define('BASE_URL', $site_url);
} else {
    // Fallback a una URL por defecto
    define('SITE_URL', 'https://' . $host);
    define('BASE_URL', 'https://' . $host);
}

// URL alternativa para el dominio personalizado
define('SITE_URL_ALT', 'https://www.tumenusas.com');
define('BASE_URL_ALT', 'https://www.tumenusas.com');
define('APP_NAME', 'Tumenufast');
define('APP_VERSION', '1.0.0');

// Configuración de zona horaria
date_default_timezone_set('America/Santiago');

// Función para configurar sesión de manera segura
function configureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    }
}

// Configurar sesión solo si no se ha configurado antes
if (!function_exists('sessionConfigured')) {
    configureSession();
}

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de rutas
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CLASSES_PATH', ROOT_PATH . '/classes');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Configuración de seguridad
define('HASH_COST', 12); // Para password_hash()

// Configuración de planes
define('DEFAULT_PLAN_ID', 1); // ID del plan gratuito por defecto
define('MAX_FREE_PRODUCTS', 10);
define('MAX_FREE_CATEGORIES', 5);
define('MAX_FREE_BRANCHES', 1); 
