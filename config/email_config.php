<?php
// Configuración de email usando Gmail SMTP (CORREGIDA)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'tumenufast@gmail.com'); // Cambiar por tu email de Gmail
define('SMTP_PASSWORD', 'rzmcrjqhmhnxnylr'); // Cambiar por tu contraseña de aplicación
define('SMTP_FROM_EMAIL', 'tumenufast@gmail.com');
define('SMTP_FROM_NAME', 'Tumenufast');

// Configuración SSL corregida
define('SMTP_VERIFY_PEER', false); // Deshabilitar verificación de certificado para desarrollo
define('SMTP_VERIFY_PEER_NAME', false); // Deshabilitar verificación de nombre para desarrollo
define('SMTP_ALLOW_SELF_SIGNED', true); // Permitir certificados auto-firmados

// Configuración de la aplicación para emails
define('APP_EMAIL_SUPPORT', 'tumenufast@gmail.com');
define('APP_EMAIL_NOREPLY', 'noreply@tumenufast.com');

// URLs para emails (se definirán dinámicamente si no están definidas)
if (!defined('EMAIL_BASE_URL')) {
    define('EMAIL_BASE_URL', '');
}
if (!defined('EMAIL_LOGO_URL')) {
    define('EMAIL_LOGO_URL', EMAIL_BASE_URL . '/assets/images/logo.png');
}

// Configuración de templates (se definirán dinámicamente)
if (!defined('EMAIL_TEMPLATE_PATH')) {
    define('EMAIL_TEMPLATE_PATH', dirname(__DIR__) . '/templates/emails');
}

// Configuración de límites
define('MAX_EMAILS_PER_HOUR', 100);
define('EMAIL_RETRY_ATTEMPTS', 3);
define('EMAIL_RETRY_DELAY', 300); // 5 minutos

// Configuración de logging (se definirán dinámicamente)
if (!defined('EMAIL_LOG_ENABLED')) {
    define('EMAIL_LOG_ENABLED', true);
}
if (!defined('EMAIL_LOG_PATH')) {
    define('EMAIL_LOG_PATH', dirname(__DIR__) . '/logs/email.log');
}

// Configuración de verificación de email
define('EMAIL_VERIFICATION_REQUIRED', false);
define('EMAIL_VERIFICATION_EXPIRY', 86400); // 24 horas en segundos

// Configuración de notificaciones
define('SEND_WELCOME_EMAIL', true);
define('SEND_REGISTRATION_CONFIRMATION', true);
define('SEND_PASSWORD_RESET', true);
define('SEND_ACCOUNT_UPDATES', true);

// Configuración adicional para mejorar la compatibilidad
define('SMTP_TIMEOUT', 30); // Timeout en segundos
define('SMTP_KEEPALIVE', false); // Mantener conexión viva
define('SMTP_AUTO_TLS', true); // Auto-detectar TLS

// Configuración de debug
define('EMAIL_DEBUG_MODE', true); // Habilitar debug para desarrollo
define('EMAIL_DEBUG_LEVEL', 2); // Nivel de debug (0-4)

// Configuración de reintentos
define('EMAIL_MAX_RETRIES', 3);
define('EMAIL_RETRY_DELAY_SECONDS', 5);

// Configuración de límites de tamaño
define('EMAIL_MAX_SIZE', 10485760); // 10MB en bytes
define('EMAIL_MAX_ATTACHMENTS', 5);

// Configuración de encabezados adicionales
define('EMAIL_ADD_HEADERS', true);
define('EMAIL_X_MAILER', 'WebMenu/1.0');
define('EMAIL_PRIORITY', 3); // 1=Alta, 3=Normal, 5=Baja

// Configuración de encoding
define('EMAIL_CHARSET', 'UTF-8');
define('EMAIL_ENCODING', '8bit');

// Configuración de autenticación
define('SMTP_AUTH_TYPE', 'LOGIN'); // LOGIN, PLAIN, CRAM-MD5, XOAUTH2
define('SMTP_REALM', '');
define('SMTP_WORKSTATION', '');

// Configuración de seguridad
define('SMTP_SECURE_OPTIONS', true);
define('SMTP_VERIFY_PEER_SSL', false);
define('SMTP_VERIFY_PEER_NAME_SSL', false);
define('SMTP_ALLOW_SELF_SIGNED_SSL', true);
