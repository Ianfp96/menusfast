<?php
require_once '../config/database.php';

// Registrar actividad de logout si hay sesión activa
if (isset($_SESSION['super_admin_id'])) {
    try {
        if (function_exists('logActivity')) {
            logActivity($_SESSION['super_admin_id'], 'super_admin_logout', 'Cierre de sesión de super administrador');
        }
        
        // Eliminar token de "recordarme" si existe
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE token = ? AND user_type = 'super_admin'");
            $stmt->execute([$token]);
        }
    } catch (PDOException $e) {
        error_log("Error al procesar logout: " . $e->getMessage());
    }
}

// Destruir todas las variables de sesión del super admin
unset($_SESSION['super_admin_id']);
unset($_SESSION['super_admin_username']);
unset($_SESSION['super_admin_email']);
unset($_SESSION['login_time']);
unset($_SESSION['ip_address']);
unset($_SESSION['user_agent']);
unset($_SESSION['last_activity']);

// Destruir la sesión completamente
session_destroy();

// Eliminar cookie de "recordarme"
setcookie('remember_token', '', time() - 3600, '/', '', true, true);

// Limpiar todas las cookies de sesión
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        if (strpos($name, 'PHPSESSID') !== false) {
            setcookie($name, '', time() - 3600, '/');
        }
    }
}

// Redirigir al login con mensaje de logout exitoso
redirect(BASE_URL . '/super_admin/login.php?logout=1');
?>
