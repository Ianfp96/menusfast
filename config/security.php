<?php
/**
 * Configuración de Seguridad
 * 
 * Este archivo contiene todas las configuraciones relacionadas con la seguridad
 * del sistema. Modificar estos valores puede afectar la seguridad general.
 */

// ========== CONFIGURACIÓN DE RATE LIMITING ==========

// Máximo número de intentos de registro por IP
define('MAX_REGISTRATION_ATTEMPTS', 5);

// Máximo número de intentos de login por IP
define('MAX_LOGIN_ATTEMPTS', 5);

// Máximo número de intentos de recuperación de contraseña por IP
define('MAX_PASSWORD_RESET_ATTEMPTS', 3);

// Tiempo de bloqueo para rate limiting (en segundos)
define('RATE_LIMIT_TIMEOUT', 3600); // 1 hora

// ========== CONFIGURACIÓN DE CONTRASEÑAS ==========

// Longitud mínima de contraseña
define('MIN_PASSWORD_LENGTH', 8);

// Longitud máxima de contraseña
define('MAX_PASSWORD_LENGTH', 128);

// Requerir contraseña fuerte (mayúsculas, minúsculas, números, símbolos)
define('REQUIRE_STRONG_PASSWORD', true);

// Tiempo de expiración de contraseña (en días, 0 = sin expiración)
define('PASSWORD_EXPIRY_DAYS', 0);

// ========== CONFIGURACIÓN DE SESIONES ==========

// Tiempo de vida de la sesión (en segundos)
define('SESSION_LIFETIME', 86400); // 24 horas

// Tiempo de inactividad antes de cerrar sesión (en segundos)
define('SESSION_TIMEOUT', 3600); // 1 hora

// Regenerar ID de sesión en cada login
define('REGENERATE_SESSION_ID', true);

// Permitir múltiples sesiones simultáneas
define('ALLOW_MULTIPLE_SESSIONS', true);

// Máximo número de sesiones simultáneas por usuario
define('MAX_SIMULTANEOUS_SESSIONS', 5);

// ========== CONFIGURACIÓN DE BLOQUEO DE CUENTAS ==========

// Máximo intentos fallidos antes de bloquear cuenta
define('MAX_FAILED_LOGIN_ATTEMPTS', 4);

// Duración del bloqueo de cuenta (en segundos)
define('ACCOUNT_LOCKOUT_DURATION', 3600); // 1 hora

// Bloquear cuenta después de intentos fallidos consecutivos
define('LOCK_ACCOUNT_ON_FAILED_ATTEMPTS', true);

// ========== CONFIGURACIÓN DE TOKENS ==========

// Duración de tokens "recordarme" (en segundos)
define('REMEMBER_TOKEN_LIFETIME', 2592000); // 30 días

// Duración de tokens de recuperación de contraseña (en segundos)
define('PASSWORD_RESET_TOKEN_LIFETIME', 3600); // 1 hora

// Duración de tokens de verificación de email (en segundos)
define('EMAIL_VERIFICATION_TOKEN_LIFETIME', 86400); // 24 horas

// ========== CONFIGURACIÓN DE VALIDACIÓN ==========

// Límites de longitud para campos
define('MAX_NAME_LENGTH', 100);
define('MAX_EMAIL_LENGTH', 254);
define('MAX_PHONE_LENGTH', 20);
define('MAX_ADDRESS_LENGTH', 255);
define('MAX_DESCRIPTION_LENGTH', 500);

// Tamaño máximo de archivos (en bytes)
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Tipos de archivo permitidos para imágenes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);

// Extensiones de archivo permitidas
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Dimensiones mínimas de imagen (en píxeles)
define('MIN_IMAGE_WIDTH', 100);
define('MIN_IMAGE_HEIGHT', 100);

// Dimensiones máximas de imagen (en píxeles)
define('MAX_IMAGE_WIDTH', 4000);
define('MAX_IMAGE_HEIGHT', 4000);

// ========== CONFIGURACIÓN DE LOGGING ==========

// Habilitar logging de seguridad
define('ENABLE_SECURITY_LOGGING', true);

// Nivel de logging (1 = básico, 2 = detallado, 3 = completo)
define('SECURITY_LOG_LEVEL', 2);

// Retención de logs de seguridad (en días)
define('SECURITY_LOG_RETENTION', 30);

// Retención de logs de actividad (en días)
define('ACTIVITY_LOG_RETENTION', 90);

// ========== CONFIGURACIÓN DE LIMPIEZA ==========

// Habilitar limpieza automática
define('ENABLE_AUTO_CLEANUP', true);

// Frecuencia de limpieza (en horas)
define('CLEANUP_FREQUENCY', 1);

// Limpiar rate limits después de (en horas)
define('RATE_LIMIT_CLEANUP_HOURS', 24);

// Limpiar sesiones inactivas después de (en horas)
define('SESSION_CLEANUP_HOURS', 24);

// ========== CONFIGURACIÓN DE HEADERS DE SEGURIDAD ==========

// Headers de seguridad HTTP
define('SECURITY_HEADERS', [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
]);

// Content Security Policy
define('CSP_POLICY', "default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self' https:;");

// ========== CONFIGURACIÓN DE ENCRIPTACIÓN ==========

// Algoritmo de hashing de contraseñas
define('PASSWORD_HASH_ALGORITHM', PASSWORD_ARGON2ID);

// Parámetros de Argon2id
define('PASSWORD_HASH_OPTIONS', [
    'memory_cost' => 65536,  // 64MB
    'time_cost' => 4,        // 4 iteraciones
    'threads' => 3           // 3 hilos
]);

// Algoritmo para tokens
define('TOKEN_ALGORITHM', 'sha256');

// Longitud de tokens
define('TOKEN_LENGTH', 32);

// ========== CONFIGURACIÓN DE MONITORING ==========

// Habilitar monitoreo de seguridad
define('ENABLE_SECURITY_MONITORING', true);

// Alertar sobre intentos sospechosos
define('ALERT_SUSPICIOUS_ATTEMPTS', true);

// Umbral para considerar intentos sospechosos
define('SUSPICIOUS_ATTEMPTS_THRESHOLD', 3);

// Email para alertas de seguridad
define('SECURITY_ALERT_EMAIL', 'tumenufast@gmail.com');

// ========== CONFIGURACIÓN DE DESARROLLO ==========

// Modo debug (solo habilitar en desarrollo)
define('DEBUG_MODE', false);

// Mostrar errores detallados (solo en desarrollo)
define('SHOW_DETAILED_ERRORS', false);

// Log de errores detallados
define('LOG_DETAILED_ERRORS', true);

// ========== FUNCIONES DE UTILIDAD ==========

/**
 * Obtener configuración de seguridad
 */
function getSecurityConfig($key) {
    return defined($key) ? constant($key) : null;
}

/**
 * Verificar si una IP está en lista blanca
 */
function isIPWhitelisted($ip) {
    $whitelist = [
        '127.0.0.1',
        '::1'
        // Agregar IPs confiables aquí
    ];
    
    return in_array($ip, $whitelist);
}

/**
 * Verificar si una IP está en lista negra
 */
function isIPBlacklisted($ip) {
    // Implementar lógica de lista negra si es necesario
    return false;
}

/**
 * Obtener configuración de headers de seguridad
 */
function getSecurityHeaders() {
    $headers = getSecurityConfig('SECURITY_HEADERS');
    
    // Agregar CSP si está configurado
    if (getSecurityConfig('CSP_POLICY')) {
        $headers['Content-Security-Policy'] = getSecurityConfig('CSP_POLICY');
    }
    
    return $headers;
}

/**
 * Aplicar headers de seguridad
 */
function applySecurityHeaders() {
    $headers = getSecurityHeaders();
    
    foreach ($headers as $header => $value) {
        header("$header: $value");
    }
}

/**
 * Verificar si el sistema está en modo producción
 */
function isProduction() {
    return !getSecurityConfig('DEBUG_MODE');
}

/**
 * Log de seguridad con nivel
 */
function securityLog($message, $level = 1) {
    if (!getSecurityConfig('ENABLE_SECURITY_LOGGING')) {
        return;
    }
    
    $log_level = getSecurityConfig('SECURITY_LOG_LEVEL');
    
    if ($level <= $log_level) {
        $log_message = date('Y-m-d H:i:s') . " [Level $level] " . $message;
        error_log($log_message, 3, __DIR__ . '/../logs/security.log');
    }
}

/**
 * Alertar sobre eventos de seguridad
 */
function securityAlert($message, $severity = 'medium') {
    if (!getSecurityConfig('ALERT_SUSPICIOUS_ATTEMPTS')) {
        return;
    }
    
    $alert_message = date('Y-m-d H:i:s') . " [$severity] " . $message;
    error_log($alert_message, 3, __DIR__ . '/../logs/security_alerts.log');
    
    // Enviar email de alerta si está configurado
    $alert_email = getSecurityConfig('SECURITY_ALERT_EMAIL');
    if ($alert_email && function_exists('sendEmail')) {
        sendEmail($alert_email, "Alerta de Seguridad - $severity", $alert_message);
    }
}

// Aplicar headers de seguridad automáticamente
if (!headers_sent()) {
    applySecurityHeaders();
}
?> 
