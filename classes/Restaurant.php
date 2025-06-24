<?php
require_once __DIR__ . '/Plan.php';

class Restaurant {
    private $db;
    private $plan;
    
    public function __construct($db) {
        $this->db = $db;
        $this->plan = new Plan($db);
    }
    
    public function getRestaurant($id) {
        $stmt = $this->db->prepare("
            SELECT r.*, p.name as plan_name, p.max_branches, p.max_products, p.max_categories
            FROM restaurants r
            LEFT JOIN plans p ON r.current_plan_id = p.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function canAddBranch($restaurantId) {
        return $this->plan->canAddBranch($restaurantId);
    }
    
    public function canAddProduct($restaurantId) {
        $stmt = $this->db->prepare("
            SELECT p.max_products, COUNT(pr.id) as current_products 
            FROM restaurants r 
            JOIN plans p ON r.current_plan_id = p.id 
            LEFT JOIN products pr ON pr.restaurant_id = r.id 
            WHERE r.id = ? 
            GROUP BY r.id, p.max_products
        ");
        $stmt->execute([$restaurantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['current_products'] < $result['max_products'];
    }
    
    public function canAddCategory($restaurantId) {
        $stmt = $this->db->prepare("
            SELECT p.max_categories, COUNT(mc.id) as current_categories 
            FROM restaurants r 
            JOIN plans p ON r.current_plan_id = p.id 
            LEFT JOIN menu_categories mc ON mc.restaurant_id = r.id 
            WHERE r.id = ? 
            GROUP BY r.id, p.max_categories
        ");
        $stmt->execute([$restaurantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['current_categories'] < $result['max_categories'];
    }
    
    public function getUsageStats($restaurantId) {
        $stmt = $this->db->prepare("
            SELECT 
                p.max_branches,
                p.max_products,
                p.max_categories,
                COUNT(DISTINCT b.id) as current_branches,
                COUNT(DISTINCT pr.id) as current_products,
                COUNT(DISTINCT mc.id) as current_categories
            FROM restaurants r
            JOIN plans p ON r.current_plan_id = p.id
            LEFT JOIN branches b ON b.restaurant_id = r.id
            LEFT JOIN products pr ON pr.restaurant_id = r.id
            LEFT JOIN menu_categories mc ON mc.restaurant_id = r.id
            WHERE r.id = ?
            GROUP BY r.id, p.max_branches, p.max_products, p.max_categories
        ");
        $stmt->execute([$restaurantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        return [
            'branches' => [
                'current' => $result['current_branches'],
                'max' => $result['max_branches'],
                'percentage' => min(100, ($result['current_branches'] / $result['max_branches']) * 100)
            ],
            'products' => [
                'current' => $result['current_products'],
                'max' => $result['max_products'],
                'percentage' => min(100, ($result['current_products'] / $result['max_products']) * 100)
            ],
            'categories' => [
                'current' => $result['current_categories'],
                'max' => $result['max_categories'],
                'percentage' => min(100, ($result['current_categories'] / $result['max_categories']) * 100)
            ]
        ];
    }
    
    public function checkSubscriptionStatus($restaurantId) {
        $stmt = $this->db->prepare("
            SELECT subscription_status, trial_ends_at, subscription_ends_at
            FROM restaurants
            WHERE id = ?
        ");
        $stmt->execute([$restaurantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        $now = new DateTime();
        
        if ($result['subscription_status'] === 'trial') {
            $trialEnds = new DateTime($result['trial_ends_at']);
            if ($now > $trialEnds) {
                // Actualizar estado a expirado
                $this->updateSubscriptionStatus($restaurantId, 'expired');
                return 'expired';
            }
            return 'trial';
        }
        
        if ($result['subscription_status'] === 'active') {
            $subscriptionEnds = new DateTime($result['subscription_ends_at']);
            if ($now > $subscriptionEnds) {
                // Actualizar estado a expirado
                $this->updateSubscriptionStatus($restaurantId, 'expired');
                return 'expired';
            }
            return 'active';
        }
        
        return $result['subscription_status'];
    }
    
    private function updateSubscriptionStatus($restaurantId, $status) {
        $stmt = $this->db->prepare("
            UPDATE restaurants 
            SET subscription_status = ?
            WHERE id = ?
        ");
        return $stmt->execute([$status, $restaurantId]);
    }
} 
