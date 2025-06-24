<?php
class Plan {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getPlan($id) {
        $stmt = $this->db->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllPlans() {
        $stmt = $this->db->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function calculatePrice($planId, $durationMonths) {
        $plan = $this->getPlan($planId);
        if (!$plan) return false;
        
        $basePrice = $plan['base_price'];
        $discount = 0;
        
        // Aplicar descuentos según duración
        switch ($durationMonths) {
            case 3:
                $discount = 0.10; // 10% de descuento
                break;
            case 6:
                $discount = 0.15; // 15% de descuento
                break;
            case 12:
                $discount = 0.20; // 20% de descuento
                break;
        }
        
        $totalPrice = $basePrice * $durationMonths;
        $discountedPrice = $totalPrice * (1 - $discount);
        
        return [
            'base_price' => $basePrice,
            'duration_months' => $durationMonths,
            'total_price' => $totalPrice,
            'discount' => $discount * 100,
            'final_price' => $discountedPrice
        ];
    }
    
    public function canAddBranch($restaurantId) {
        $stmt = $this->db->prepare("
            SELECT p.max_branches, COUNT(b.id) as current_branches 
            FROM restaurants r 
            JOIN plans p ON r.current_plan_id = p.id 
            LEFT JOIN branches b ON b.restaurant_id = r.id 
            WHERE r.id = ? 
            GROUP BY r.id, p.max_branches
        ");
        $stmt->execute([$restaurantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['current_branches'] < $result['max_branches'];
    }
} 
