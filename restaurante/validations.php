<?php
// Archivo de validaciones para límites de plan
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Plan.php';
require_once __DIR__ . '/../classes/Restaurant.php';

class PlanValidations {
    private $restaurant;
    private $restaurant_id;
    
    public function __construct($restaurant_id) {
        $db = new Database();
        $this->restaurant = new Restaurant($db);
        $this->restaurant_id = $restaurant_id;
    }
    
    /**
     * Validar antes de agregar una nueva sucursal
     */
    public function validateNewBranch() {
        if (!checkPlanLimits($this->restaurant_id, 'branches')) {
            return [
                'success' => false,
                'message' => 'Has alcanzado el límite de sucursales de tu plan actual. <a href="' . BASE_URL . '/restaurante/plan">Actualiza tu plan</a> para agregar más sucursales.',
                'redirect' => BASE_URL . '/restaurante/plan'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Validar antes de agregar un nuevo producto
     */
    public function validateNewProduct() {
        if (!checkPlanLimits($this->restaurant_id, 'products')) {
            return [
                'success' => false,
                'message' => 'Has alcanzado el límite de productos de tu plan actual. <a href="' . BASE_URL . '/restaurante/plan">Actualiza tu plan</a> para agregar más productos.',
                'redirect' => BASE_URL . '/restaurante/plan'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Validar antes de agregar una nueva categoría
     */
    public function validateNewCategory() {
        if (!checkPlanLimits($this->restaurant_id, 'categories')) {
            return [
                'success' => false,
                'message' => 'Has alcanzado el límite de categorías de tu plan actual. <a href="' . BASE_URL . '/restaurante/plan">Actualiza tu plan</a> para agregar más categorías.',
                'redirect' => BASE_URL . '/restaurante/plan'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Obtener mensaje de advertencia si está cerca del límite
     */
    public function getWarningMessage($type) {
        $usage_stats = $this->restaurant->getUsageStats($this->restaurant_id);
        if (!$usage_stats) {
            return [];
        }
        
        $warnings = [];
        
        switch ($type) {
            case 'branches':
                if ($usage_stats['branches']['max'] != -1) {
                    $percentage = $usage_stats['branches']['percentage'];
                    if ($percentage >= 80) {
                        $warnings[] = "Estás usando {$usage_stats['branches']['current']} de {$usage_stats['branches']['max']} sucursales disponibles.";
                    }
                }
                break;
                
            case 'products':
                if ($usage_stats['products']['max'] != -1) {
                    $percentage = $usage_stats['products']['percentage'];
                    if ($percentage >= 80) {
                        $warnings[] = "Estás usando {$usage_stats['products']['current']} de {$usage_stats['products']['max']} productos disponibles.";
                    }
                }
                break;
                
            case 'categories':
                if ($usage_stats['categories']['max'] != -1) {
                    $percentage = $usage_stats['categories']['percentage'];
                    if ($percentage >= 80) {
                        $warnings[] = "Estás usando {$usage_stats['categories']['current']} de {$usage_stats['categories']['max']} categorías disponibles.";
                    }
                }
                break;
        }
        
        return $warnings;
    }
}

// Función para mostrar alertas de límites
function showLimitAlert($restaurant_id, $type) {
    $validator = new PlanValidations($restaurant_id);
    $warnings = $validator->getWarningMessage($type);
    
    if (!empty($warnings)) {
        echo '<div class="alert alert-warning alert-dismissible fade show">';
        echo '<i class="fas fa-exclamation-triangle"></i> ';
        foreach ($warnings as $warning) {
            echo $warning . ' ';
        }
        echo '<a href="' . BASE_URL . '/restaurante/plan" class="alert-link">Considera actualizar tu plan.</a>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}
?>
