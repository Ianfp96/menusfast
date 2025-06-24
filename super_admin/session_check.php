<?php
/**
 * Verificación de sesión y tokens de "recordarme" para super admin
 * Incluir este archivo en todas las páginas del super admin
 */

session_start();

// Verificar si ya hay una sesión activa
if (!isset($_SESSION['super_admin_id'])) {
    // Verificar si hay un token de "recordarme"
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        try {
            require_once __DIR__ . '/../config/database.php';
            
            // Buscar el token en la base de datos
            $stmt = $conn->prepare("SELECT rt.*, sa.username, sa.email, sa.is_active 
                                   FROM remember_tokens rt 
                                   JOIN super_admins sa ON rt.user_id = sa.id 
                                   WHERE rt.token = ? AND rt.user_type = 'super_admin' 
                                   AND rt.expires_at > NOW()");
            $stmt->execute([$token]);
            $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($token_data && $token_data['is_active'] == 1) {
                // Token válido, restaurar sesión
                session_regenerate_id(true);
                
                $_SESSION['super_admin_id'] = $token_data['user_id'];
                $_SESSION['super_admin_username'] = $token_data['username'];
                $_SESSION['super_admin_email'] = $token_data['email'];
                $_SESSION['login_time'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                // Registrar actividad
                if (function_exists('logActivity')) {
                    logActivity($token_data['user_id'], 'super_admin_auto_login', 'Sesión restaurada con token de recordarme');
                }
                
            } else {
                // Token inválido o expirado, eliminar cookie
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                redirect(BASE_URL . '/super_admin/login.php');
            }
            
        } catch (PDOException $e) {
            error_log("Error al verificar token de recordarme: " . $e->getMessage());
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            redirect(BASE_URL . '/super_admin/login.php');
        }
    } else {
        // No hay sesión ni token, redirigir al login
        redirect(BASE_URL . '/super_admin/login.php');
    }
}

// Verificar timeout de sesión (1 hora)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 3600) {
    // Sesión expirada
    session_destroy();
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    redirect(BASE_URL . '/super_admin/login.php?expired=1');
}

// Verificar si la IP ha cambiado (opcional, para mayor seguridad)
if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
    // IP cambiada, pero no cerrar sesión automáticamente
    // Solo registrar para auditoría
    if (function_exists('logActivity')) {
        logActivity($_SESSION['super_admin_id'], 'super_admin_ip_change', 'Cambio de IP detectado: ' . $_SESSION['ip_address'] . ' -> ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    }
}

// Actualizar tiempo de última actividad
$_SESSION['last_activity'] = time();

// Configurar headers de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src \'self\' data: https:; connect-src \'self\';');
?> 
