<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    if (empty($_POST['code']) || empty($_POST['restaurant_id']) || !isset($_POST['order_total'])) {
        throw new Exception('Datos requeridos faltantes');
    }

    $code = strtoupper(trim($_POST['code']));
    $restaurant_id = intval($_POST['restaurant_id']);
    $order_total = floatval($_POST['order_total']);

    if ($order_total < 0) {
        throw new Exception('El total de la orden no puede ser negativo');
    }

    // Buscar el cupón
    $stmt = $conn->prepare("
        SELECT c.*, 
               COUNT(cu.id) as total_uses
        FROM coupons c
        LEFT JOIN coupon_usage cu ON c.id = cu.coupon_id
        WHERE c.code = ? AND c.restaurant_id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$code, $restaurant_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        throw new Exception('Cupón no encontrado');
    }

    // Verificar si está activo
    if (!$coupon['is_active']) {
        throw new Exception('Este cupón no está activo');
    }

    // Verificar fecha de validez
    $now = time();
    $valid_from = strtotime($coupon['valid_from']);
    $valid_until = strtotime($coupon['valid_until']);

    if ($now < $valid_from) {
        throw new Exception('Este cupón aún no está disponible');
    }

    if ($now > $valid_until) {
        throw new Exception('Este cupón ha expirado');
    }

    // Verificar monto mínimo
    if ($order_total < $coupon['minimum_order_amount']) {
        $min_amount = number_format($coupon['minimum_order_amount'], 0, ',', '.');
        throw new Exception("Monto mínimo requerido: $min_amount");
    }

    // Verificar límite de usos
    if ($coupon['usage_limit'] && $coupon['total_uses'] >= $coupon['usage_limit']) {
        throw new Exception('Este cupón ya no está disponible (límite de usos alcanzado)');
    }

    // Calcular descuento
    $discount_amount = 0;
    if ($coupon['discount_type'] === 'percentage') {
        $discount_amount = ($order_total * $coupon['discount_value']) / 100;
        
        // Aplicar descuento máximo si está configurado
        if ($coupon['maximum_discount'] && $discount_amount > $coupon['maximum_discount']) {
            $discount_amount = $coupon['maximum_discount'];
        }
    } else {
        $discount_amount = $coupon['discount_value'];
    }

    // Verificar que el descuento no sea mayor al total de la orden
    if ($discount_amount > $order_total) {
        $discount_amount = $order_total;
    }

    $final_total = $order_total - $discount_amount;

    echo json_encode([
        'success' => true,
        'message' => 'Cupón válido',
        'coupon' => [
            'id' => $coupon['id'],
            'code' => $coupon['code'],
            'name' => $coupon['name'],
            'description' => $coupon['description'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => $coupon['discount_value'],
            'discount_amount' => $discount_amount,
            'order_total' => $order_total,
            'final_total' => $final_total
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
