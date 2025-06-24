<?php
/**
 * Script de cron para enviar notificaciones de expiración de suscripciones
 * 
 * Este script debe ejecutarse diariamente para enviar emails de advertencia
 * 7 días y 1 día antes de que expire una suscripción, y también
 * 1, 2 y 3 días después de que expire una suscripción.
 * 
 * USO: php cron/send_expiration_notifications.php
 * 
 * Configurar en crontab para ejecutar diariamente a las 9:00 AM:
 * 0 9 * * * /usr/bin/php /path/to/webmenu/cron/send_expiration_notifications.php
 */

// Configurar zona horaria
date_default_timezone_set('America/Santiago');

// Incluir archivos necesarios
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ExpirationNotificationService.php';

// Configurar logging
$logFile = __DIR__ . '/expiration_notifications.log';
$startTime = microtime(true);

echo "=== Iniciando envío de notificaciones de expiración ===\n";
echo "Fecha y hora: " . date('Y-m-d H:i:s') . "\n\n";

// Función para logging
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";
    echo $logEntry;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    // Inicializar conexión a la base de datos
    logMessage("Inicializando conexión a la base de datos...");
    $db = new Database();
    
    // Crear instancia del servicio de notificaciones
    logMessage("Inicializando servicio de notificaciones...");
    $notificationService = new ExpirationNotificationService($db);
    
    // Ejecutar notificaciones
    logMessage("Ejecutando notificaciones de expiración...");
    $results = $notificationService->runExpirationNotifications();
    
    // Mostrar resultados
    logMessage("Resultados del envío:");
    logMessage("- Notificaciones de 7 días enviadas: {$results['7_days']}");
    logMessage("- Notificaciones de 1 día enviadas: {$results['1_day']}");
    logMessage("- Notificaciones post-expiración día 1: {$results['post_expiration_1_day']}");
    logMessage("- Notificaciones post-expiración día 2: {$results['post_expiration_2_day']}");
    logMessage("- Notificaciones post-expiración día 3: {$results['post_expiration_3_day']}");
    logMessage("- Total de notificaciones enviadas: {$results['total']}");
    
    // Obtener estadísticas de los últimos 7 días
    logMessage("\nEstadísticas de los últimos 7 días:");
    $stats = $notificationService->getNotificationStats(7);
    
    if ($stats) {
        foreach ($stats as $stat) {
            $type = $this->getNotificationTypeDescription($stat['email_type']);
            logMessage("- {$type}: {$stat['count']} emails el {$stat['date']}");
        }
    } else {
        logMessage("- No hay estadísticas disponibles");
    }
    
    // Calcular tiempo de ejecución
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    logMessage("\nTiempo de ejecución: {$executionTime} segundos");
    
    // Verificar si hay errores en los logs
    $errorLogFile = __DIR__ . '/../logs/email.log';
    if (file_exists($errorLogFile)) {
        $errorLogContent = file_get_contents($errorLogFile);
        $errorLines = array_filter(explode("\n", $errorLogContent), function($line) {
            return strpos($line, 'ERROR') !== false || strpos($line, 'Error') !== false;
        });
        
        if (!empty($errorLines)) {
            logMessage("\n⚠️ Errores encontrados en el log de emails:");
            $recentErrors = array_slice($errorLines, -5); // Últimos 5 errores
            foreach ($recentErrors as $error) {
                logMessage("- " . trim($error));
            }
        }
    }
    
    logMessage("=== Proceso completado exitosamente ===\n");
    
} catch (Exception $e) {
    $errorMessage = "❌ Error crítico: " . $e->getMessage();
    logMessage($errorMessage);
    logMessage("Stack trace: " . $e->getTraceAsString());
    logMessage("=== Proceso falló ===\n");
    exit(1);
}

// Verificar configuración de email
logMessage("Verificando configuración de email...");
if (!defined('SMTP_USERNAME') || SMTP_USERNAME === 'tumenufast@gmail.com') {
    logMessage("⚠️ ADVERTENCIA: La configuración de email no está completa");
    logMessage("   Edita config/email_config.php con tus credenciales de Gmail");
} else {
    logMessage("✅ Configuración de email: OK");
}

logMessage("=== Fin del script ===\n");

/**
 * Función para obtener descripción legible del tipo de notificación
 */
function getNotificationTypeDescription($emailType) {
    switch ($emailType) {
        case 'expiration_7_days':
            return '7 días antes de expirar';
        case 'expiration_1_day':
            return '1 día antes de expirar';
        case 'post_expiration_1_days':
            return '1 día después de expirar';
        case 'post_expiration_2_days':
            return '2 días después de expirar';
        case 'post_expiration_3_days':
            return '3 días después de expirar';
        default:
            return $emailType;
    }
} 
