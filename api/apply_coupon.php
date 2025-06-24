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
    if (empty($_POST['coupon_id']) || empty($_POST['order_id']) || !isset($_POST['discount_amount'])) {
        throw new Exception('Datos requeridos faltantes');
    }

    $coupon_id = intval($_POST['coupon_id']);
    $order_id = intval($_POST['order_id']);
    $discount_amount = floatval($_POST['discount_amount']);
    $customer_name = $_POST['customer_name'] ?? null;
    $customer_phone = $_POST['customer_phone'] ?? null;
    $order_total = floatval($_POST['order_total'] ?? 0);

    if ($discount_amount < 0) {
        throw new Exception('El descuento no puede ser negativo');
    }

    // Verificar que el cupón existe y está activo
    $stmt = $conn->prepare("
        SELECT c.*, 
               COUNT(cu.id) as total_uses
        FROM coupons c
        LEFT JOIN coupon_usage cu ON c.id = cu.coupon_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$coupon_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        throw new Exception('Cupón no encontrado');
    }

    if (!$coupon['is_active']) {
        throw new Exception('Cupón no está activo');
    }

    // Verificar fecha de validez
    $now = time();
    $valid_from = strtotime($coupon['valid_from']);
    $valid_until = strtotime($coupon['valid_until']);

    if ($now < $valid_from || $now > $valid_until) {
        throw new Exception('Cupón no válido en este momento');
    }

    // Verificar límite de usos
    if ($coupon['usage_limit'] && $coupon['total_uses'] >= $coupon['usage_limit']) {
        throw new Exception('Cupón ya no disponible (límite alcanzado)');
    }

    // Verificar que la orden existe
    $stmt = $conn->prepare("SELECT id, restaurant_id FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Orden no encontrada');
    }

    // Verificar que el cupón pertenece al restaurante de la orden
    if ($coupon['restaurant_id'] != $order['restaurant_id']) {
        throw new Exception('Cupón no válido para este restaurante');
    }

    // Verificar que no se haya usado este cupón en esta orden
    $stmt = $conn->prepare("SELECT id FROM coupon_usage WHERE coupon_id = ? AND order_id = ?");
    $stmt->execute([$coupon_id, $order_id]);
    if ($stmt->fetch()) {
        throw new Exception('Este cupón ya fue aplicado a esta orden');
    }

    // Iniciar transacción
    $conn->beginTransaction();

    try {
        // Registrar el uso del cupón
        $stmt = $conn->prepare("
            INSERT INTO coupon_usage (
                coupon_id, order_id, customer_name, customer_phone, 
                discount_amount, order_total
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $coupon_id, $order_id, $customer_name, $customer_phone,
            $discount_amount, $order_total
        ]);

        // Actualizar el contador de usos del cupón
        $stmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
        $stmt->execute([$coupon_id]);

        // Actualizar el total de la orden con el descuento
        $stmt = $conn->prepare("UPDATE orders SET total = total - ? WHERE id = ?");
        $stmt->execute([$discount_amount, $order_id]);

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Cupón aplicado exitosamente',
            'discount_amount' => $discount_amount
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
