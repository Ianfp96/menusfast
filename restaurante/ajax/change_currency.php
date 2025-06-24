<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';
require_once '../../config/currency_converter.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $currency = $_POST['currency'] ?? 'USD';
    
    // Validar que la moneda sea válida
    if (!CurrencyConverter::isValidCurrency($currency)) {
        echo json_encode(['success' => false, 'message' => 'Moneda no válida: ' . $currency]);
        exit;
    }
    
    // Obtener todos los planes activos
    $stmt = $conn->prepare("
        SELECT * FROM plans 
        WHERE is_active = 1 
        ORDER BY base_price ASC
    ");
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $convertedPlans = [];
    
    foreach ($plans as $plan) {
        if ($plan['id'] == 1) continue; // Saltar el plan gratuito
        
        $basePriceCLP = $plan['base_price'];
        
        // Convertir precios
        $monthlyPrice = CurrencyConverter::formatPriceInCurrency($basePriceCLP, $currency);
        $annualPrice = CurrencyConverter::formatPriceInCurrency($basePriceCLP * 12 * 0.6, $currency); // 40% descuento
        
        $convertedPlans[] = [
            'id' => $plan['id'],
            'name' => $plan['name'],
            'monthly_price' => $monthlyPrice,
            'annual_price' => $annualPrice,
            'max_categories' => $plan['max_categories'],
            'max_products' => $plan['max_products'],
            'max_branches' => $plan['max_branches'],
            'features' => json_decode($plan['features'], true) ?? []
        ];
    }
    
    // Obtener información del restaurante actual
    $restaurant_id = $_SESSION['restaurant_id'];
    $stmt = $conn->prepare("
        SELECT r.*, 
               p.name as plan_name,
               p.base_price,
               p.max_categories,
               p.max_products,
               p.max_branches
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        WHERE r.id = ?
    ");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $currentPlanPrice = '0';
    if ($restaurant && $restaurant['base_price']) {
        $currentPlanPrice = CurrencyConverter::formatPriceInCurrency($restaurant['base_price'], $currency);
    }
    
    echo json_encode([
        'success' => true,
        'plans' => $convertedPlans,
        'current_plan' => [
            'name' => $restaurant['plan_name'] ?? 'Sin plan',
            'price' => $currentPlanPrice,
            'max_categories' => $restaurant['max_categories'] ?? 0,
            'max_products' => $restaurant['max_products'] ?? 0,
            'max_branches' => $restaurant['max_branches'] ?? 0
        ],
        'currency' => $currency
    ]);
    
} catch (Exception $e) {
    error_log("Error en change_currency.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Error al convertir monedas: ' . $e->getMessage()]);
} 
