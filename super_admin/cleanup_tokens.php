<?php
/**
 * Script para limpiar tokens de "recordarme" expirados
 * Este script debe ejecutarse periódicamente (cron job)
 */

require_once __DIR__ . '/../config/database.php';

try {
    // Limpiar tokens expirados
    $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
    $stmt->execute();
    
    $deleted_count = $stmt->rowCount();
    
    // Limpiar intentos de login antiguos (más de 24 horas)
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    
    $login_attempts_deleted = $stmt->rowCount();
    
    echo "Limpieza completada:\n";
    echo "- Tokens expirados eliminados: $deleted_count\n";
    echo "- Intentos de login antiguos eliminados: $login_attempts_deleted\n";
    
} catch (PDOException $e) {
    error_log("Error en limpieza de tokens: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?> 
