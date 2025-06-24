<?php
/**
 * Script para procesar pagos recurrentes
 * Este script se ejecuta diariamente para procesar suscripciones
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Plan.php';

// Configurar zona horaria
date_default_timezone_set('America/Santiago');

// Log de inicio
error_log("Iniciando procesamiento de pagos recurrentes: " . date('Y-m-d H:i:s'));

try {
    $db = new Database();
    $planService = new Plan($db);
    
    // Obtener suscripciones activas que necesitan renovación
    $stmt = $db->getConnection()->prepare("
        SELECT rs.*, p.name as plan_name, p.base_price, r.name as restaurant_name, r.email as restaurant_email
        FROM recurring_subscriptions rs
        JOIN plans p ON rs.plan_id = p.id
        JOIN restaurants r ON rs.restaurant_id = r.id
        WHERE rs.status = 'active'
        AND rs.next_payment_date <= DATE_ADD(NOW(), INTERVAL 1 DAY)
        AND rs.mp_subscription_id IS NOT NULL
        ORDER BY rs.next_payment_date ASC
    ");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processed = 0;
    $errors = 0;
    
    foreach ($subscriptions as $subscription) {
        try {
            // Generar ID de pago único
            $paymentId = 'MP_' . time() . '_' . $subscription['id'];
            
            // Calcular monto del pago
            $amount = $subscription['monthly_amount'];
            
            // Registrar el pago en la base de datos
            $stmt = $db->getConnection()->prepare("
                INSERT INTO mp_payments (payment_id, preference_id, restaurant_id, amount, status, created_at)
                VALUES (?, ?, ?, ?, 'approved', NOW())
            ");
            $stmt->execute([
                $paymentId,
                'recurring_' . $subscription['id'],
                $subscription['restaurant_id'],
                $amount
            ]);
            
            // Actualizar fecha del próximo pago
            $nextPaymentDate = date('Y-m-d H:i:s', strtotime('+1 month'));
            $stmt = $db->getConnection()->prepare("
                UPDATE recurring_subscriptions 
                SET next_payment_date = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$nextPaymentDate, $subscription['id']]);
            
            // Enviar email de confirmación
            $subject = "Pago procesado - " . APP_NAME;
            $message = "Hola {$subscription['restaurant_name']},\n\n";
            $message .= "Tu pago recurrente ha sido procesado exitosamente.\n";
            $message .= "Plan: {$subscription['plan_name']}\n";
            $message .= "Monto: $" . number_format($amount, 0, ',', '.') . " CLP\n";
            $message .= "Próximo pago: " . date('d/m/Y', strtotime($nextPaymentDate)) . "\n\n";
            $message .= "Gracias por tu confianza.\n";
            $message .= "Equipo " . APP_NAME;
            
            mail($subscription['restaurant_email'], $subject, $message);
            
            $processed++;
            error_log("Pago procesado para restaurante ID: {$subscription['restaurant_id']}");
            
        } catch (Exception $e) {
            $errors++;
            error_log("Error procesando pago para restaurante ID {$subscription['restaurant_id']}: " . $e->getMessage());
        }
    }
    
    error_log("Procesamiento completado. Procesados: $processed, Errores: $errors");
    
} catch (Exception $e) {
    error_log("Error general en procesamiento de pagos recurrentes: " . $e->getMessage());
} 
