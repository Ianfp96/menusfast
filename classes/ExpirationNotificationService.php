<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/EmailService.php';

class ExpirationNotificationService {
    private $db;
    private $emailService;
    
    public function __construct($db) {
        $this->db = $db->getConnection();
        $this->emailService = new EmailService($this->db);
    }
    
    /**
     * Busca suscripciones que expiran en 7 días
     */
    public function getSubscriptionsExpiringIn7Days() {
        $stmt = $this->db->prepare("
            SELECT 
                s.id as subscription_id,
                s.restaurant_id,
                s.plan_id,
                s.end_date,
                s.status,
                r.name as restaurant_name,
                r.email as restaurant_email,
                r.slug as restaurant_slug,
                p.name as plan_name,
                p.base_price
            FROM subscriptions s
            JOIN restaurants r ON s.restaurant_id = r.id
            JOIN plans p ON s.plan_id = p.id
            WHERE s.status = 'active'
            AND s.end_date BETWEEN DATE_ADD(NOW(), INTERVAL 7 DAY) AND DATE_ADD(NOW(), INTERVAL 8 DAY)
            AND s.id NOT IN (
                SELECT subscription_id 
                FROM email_logs 
                WHERE email_type = 'expiration_7_days' 
                AND DATE(sent_at) = CURDATE()
            )
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca suscripciones que expiran en 1 día
     */
    public function getSubscriptionsExpiringIn1Day() {
        $stmt = $this->db->prepare("
            SELECT 
                s.id as subscription_id,
                s.restaurant_id,
                s.plan_id,
                s.end_date,
                s.status,
                r.name as restaurant_name,
                r.email as restaurant_email,
                r.slug as restaurant_slug,
                p.name as plan_name,
                p.base_price
            FROM subscriptions s
            JOIN restaurants r ON s.restaurant_id = r.id
            JOIN plans p ON s.plan_id = p.id
            WHERE s.status = 'active'
            AND s.end_date BETWEEN DATE_ADD(NOW(), INTERVAL 1 DAY) AND DATE_ADD(NOW(), INTERVAL 2 DAY)
            AND s.id NOT IN (
                SELECT subscription_id 
                FROM email_logs 
                WHERE email_type = 'expiration_1_day' 
                AND DATE(sent_at) = CURDATE()
            )
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca suscripciones que expiraron hace X días (1, 2 o 3 días)
     */
    public function getExpiredSubscriptions($daysAgo) {
        $stmt = $this->db->prepare("
            SELECT 
                s.id as subscription_id,
                s.restaurant_id,
                s.plan_id,
                s.end_date,
                s.status,
                r.name as restaurant_name,
                r.email as restaurant_email,
                r.slug as restaurant_slug,
                p.name as plan_name,
                p.base_price,
                DATEDIFF(NOW(), s.end_date) as days_expired
            FROM subscriptions s
            JOIN restaurants r ON s.restaurant_id = r.id
            JOIN plans p ON s.plan_id = p.id
            WHERE s.status = 'expired'
            AND s.end_date BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY) AND DATE_SUB(NOW(), INTERVAL ? - 1 DAY)
            AND s.id NOT IN (
                SELECT subscription_id 
                FROM email_logs 
                WHERE email_type = 'post_expiration_' . ? . '_days' 
                AND DATE(sent_at) = CURDATE()
            )
        ");
        $stmt->execute([$daysAgo, $daysAgo, $daysAgo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Envía notificaciones de expiración en 7 días
     */
    public function send7DaysExpirationNotifications() {
        $subscriptions = $this->getSubscriptionsExpiringIn7Days();
        $sentCount = 0;
        
        foreach ($subscriptions as $subscription) {
            try {
                $emailData = [
                    'restaurant_id' => $subscription['restaurant_id'],
                    'name' => $subscription['restaurant_name'],
                    'email' => $subscription['restaurant_email'],
                    'plan_name' => $subscription['plan_name'],
                    'expiration_date' => $subscription['end_date'],
                    'days_remaining' => 7,
                    'subscription_id' => $subscription['subscription_id']
                ];
                
                $success = $this->emailService->sendExpirationWarningEmail($emailData, 7);
                
                if ($success) {
                    $sentCount++;
                    $this->logNotificationSent($subscription['subscription_id'], 'expiration_7_days', $subscription['restaurant_email']);
                }
                
            } catch (Exception $e) {
                error_log("Error enviando notificación de 7 días para suscripción {$subscription['subscription_id']}: " . $e->getMessage());
            }
        }
        
        return $sentCount;
    }
    
    /**
     * Envía notificaciones de expiración en 1 día
     */
    public function send1DayExpirationNotifications() {
        $subscriptions = $this->getSubscriptionsExpiringIn1Day();
        $sentCount = 0;
        
        foreach ($subscriptions as $subscription) {
            try {
                $emailData = [
                    'restaurant_id' => $subscription['restaurant_id'],
                    'name' => $subscription['restaurant_name'],
                    'email' => $subscription['restaurant_email'],
                    'plan_name' => $subscription['plan_name'],
                    'expiration_date' => $subscription['end_date'],
                    'days_remaining' => 1,
                    'subscription_id' => $subscription['subscription_id']
                ];
                
                $success = $this->emailService->sendExpirationWarningEmail($emailData, 1);
                
                if ($success) {
                    $sentCount++;
                    $this->logNotificationSent($subscription['subscription_id'], 'expiration_1_day', $subscription['restaurant_email']);
                }
                
            } catch (Exception $e) {
                error_log("Error enviando notificación de 1 día para suscripción {$subscription['subscription_id']}: " . $e->getMessage());
            }
        }
        
        return $sentCount;
    }
    
    /**
     * Envía notificaciones post-expiración (días 1, 2 y 3 después de expirar)
     */
    public function sendPostExpirationNotifications($daysAgo) {
        $subscriptions = $this->getExpiredSubscriptions($daysAgo);
        $sentCount = 0;
        
        foreach ($subscriptions as $subscription) {
            try {
                $emailData = [
                    'restaurant_id' => $subscription['restaurant_id'],
                    'name' => $subscription['restaurant_name'],
                    'email' => $subscription['restaurant_email'],
                    'plan_name' => $subscription['plan_name'],
                    'expiration_date' => $subscription['end_date'],
                    'days_expired' => $daysAgo,
                    'subscription_id' => $subscription['subscription_id']
                ];
                
                $success = $this->emailService->sendPostExpirationEmail($emailData, $daysAgo);
                
                if ($success) {
                    $sentCount++;
                    $this->logNotificationSent($subscription['subscription_id'], 'post_expiration_' . $daysAgo . '_days', $subscription['restaurant_email']);
                }
                
            } catch (Exception $e) {
                error_log("Error enviando notificación post-expiración día {$daysAgo} para suscripción {$subscription['subscription_id']}: " . $e->getMessage());
            }
        }
        
        return $sentCount;
    }
    
    /**
     * Registra que se envió una notificación
     */
    private function logNotificationSent($subscriptionId, $emailType, $email) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs 
                (email_type, recipient_email, restaurant_id, subscription_id, sent_at, success) 
                VALUES (?, ?, ?, ?, NOW(), 1)
            ");
            
            // Obtener restaurant_id de la suscripción
            $stmt2 = $this->db->prepare("SELECT restaurant_id FROM subscriptions WHERE id = ?");
            $stmt2->execute([$subscriptionId]);
            $subscription = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            if ($subscription) {
                $stmt->execute([$emailType, $email, $subscription['restaurant_id'], $subscriptionId]);
            }
            
        } catch (Exception $e) {
            error_log("Error registrando notificación enviada: " . $e->getMessage());
        }
    }
    
    /**
     * Ejecuta todas las notificaciones de expiración
     */
    public function runExpirationNotifications() {
        $results = [
            '7_days' => 0,
            '1_day' => 0,
            'post_expiration_1_day' => 0,
            'post_expiration_2_day' => 0,
            'post_expiration_3_day' => 0,
            'total' => 0
        ];
        
        try {
            // Enviar notificaciones de 7 días
            $results['7_days'] = $this->send7DaysExpirationNotifications();
            
            // Enviar notificaciones de 1 día
            $results['1_day'] = $this->send1DayExpirationNotifications();
            
            // Enviar notificaciones post-expiración (días 1, 2 y 3)
            $results['post_expiration_1_day'] = $this->sendPostExpirationNotifications(1);
            $results['post_expiration_2_day'] = $this->sendPostExpirationNotifications(2);
            $results['post_expiration_3_day'] = $this->sendPostExpirationNotifications(3);
            
            $results['total'] = $results['7_days'] + $results['1_day'] + 
                               $results['post_expiration_1_day'] + $results['post_expiration_2_day'] + 
                               $results['post_expiration_3_day'];
            
        } catch (Exception $e) {
            error_log("Error ejecutando notificaciones de expiración: " . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Obtiene estadísticas de notificaciones enviadas
     */
    public function getNotificationStats($days = 7) {
        $stmt = $this->db->prepare("
            SELECT 
                email_type,
                COUNT(*) as count,
                DATE(sent_at) as date
            FROM email_logs 
            WHERE email_type IN ('expiration_7_days', 'expiration_1_day', 'post_expiration_1_days', 'post_expiration_2_days', 'post_expiration_3_days')
            AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY email_type, DATE(sent_at)
            ORDER BY date DESC, email_type
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 
