<?php
class Subscription {
    private $db;
    private $plan;
    
    public function __construct($db) {
        $this->db = $db->getConnection();
        $this->plan = new Plan($db);
    }
    
    public function createSubscription($restaurantId, $planId, $durationMonths) {
        try {
            $this->db->beginTransaction();
            
            // Calcular fechas
            $startDate = date('Y-m-d H:i:s');
            $endDate = date('Y-m-d H:i:s', strtotime("+{$durationMonths} months"));
            
            // Obtener el plan y calcular el precio usando la instancia de Plan ya creada
            $priceCalculation = $this->plan->calculatePrice($planId, $durationMonths);
            
            if (!$priceCalculation) {
                throw new Exception("Error al calcular el precio del plan");
            }
            
            // Crear la suscripción
            $stmt = $this->db->prepare("
                INSERT INTO subscriptions 
                (restaurant_id, plan_id, duration_months, price, start_date, end_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([
                $restaurantId,
                $planId,
                $durationMonths,
                $priceCalculation['final_price'],
                $startDate,
                $endDate
            ]);
            
            $subscriptionId = $this->db->lastInsertId();
            
            // Actualizar el restaurante
            $stmt = $this->db->prepare("
                UPDATE restaurants 
                SET current_plan_id = ?, 
                    subscription_id = ?,
                    subscription_status = 'active',
                    subscription_ends_at = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $planId,
                $subscriptionId,
                $endDate,
                $restaurantId
            ]);
            
            // Registrar el cambio de plan
            $stmt = $this->db->prepare("
                INSERT INTO plan_changes 
                (restaurant_id, old_plan_id, new_plan_id, change_type) 
                VALUES (?, (SELECT plan_id FROM restaurants WHERE id = ?), ?, 'plan_change')
            ");
            
            $stmt->execute([$restaurantId, $restaurantId, $planId]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al crear suscripción: " . $e->getMessage());
            return false;
        }
    }
    
    public function getActiveSubscription($restaurantId) {
        $stmt = $this->db->prepare("
            SELECT s.*, p.name as plan_name, p.slug as plan_slug, p.features
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.restaurant_id = ? AND s.status = 'active'
            ORDER BY s.end_date DESC
            LIMIT 1
        ");
        $stmt->execute([$restaurantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function cancelSubscription($subscriptionId) {
        try {
            $this->db->beginTransaction();
            
            // Obtener información de la suscripción
            $stmt = $this->db->prepare("
                SELECT restaurant_id, plan_id 
                FROM subscriptions 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$subscriptionId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$subscription) {
                throw new Exception("Suscripción no encontrada o ya cancelada");
            }
            
            // Cancelar la suscripción
            $stmt = $this->db->prepare("
                UPDATE subscriptions 
                SET status = 'cancelled' 
                WHERE id = ?
            ");
            $stmt->execute([$subscriptionId]);
            
            // Actualizar el restaurante
            $stmt = $this->db->prepare("
                UPDATE restaurants 
                SET subscription_status = 'cancelled',
                    subscription_id = NULL
                WHERE id = ?
            ");
            $stmt->execute([$subscription['restaurant_id']]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al cancelar suscripción: " . $e->getMessage());
            return false;
        }
    }
    
    public function checkExpiredSubscriptions() {
        $stmt = $this->db->prepare("
            SELECT s.id, s.restaurant_id
            FROM subscriptions s
            WHERE s.status = 'active' 
            AND s.end_date < NOW()
        ");
        $stmt->execute();
        $expiredSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($expiredSubscriptions as $subscription) {
            // Marcar como expirada
            $stmt = $this->db->prepare("
                UPDATE subscriptions 
                SET status = 'expired' 
                WHERE id = ?
            ");
            $stmt->execute([$subscription['id']]);
            
            // Actualizar el restaurante
            $stmt = $this->db->prepare("
                UPDATE restaurants 
                SET subscription_status = 'expired',
                    subscription_id = NULL
                WHERE id = ?
            ");
            $stmt->execute([$subscription['restaurant_id']]);
        }
        
        return count($expiredSubscriptions);
    }
} 
