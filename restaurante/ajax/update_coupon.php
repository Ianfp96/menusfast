<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Validar datos requeridos
    $required_fields = ['coupon_id', 'code', 'name', 'discount_type', 'discount_value', 'valid_until'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo '$field' es requerido");
        }
    }

    $coupon_id = intval($_POST['coupon_id']);
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');
    $discount_type = $_POST['discount_type'];
    $discount_value = floatval($_POST['discount_value']);
    $minimum_order_amount = floatval($_POST['minimum_order_amount'] ?? 0);
    $maximum_discount = !empty($_POST['maximum_discount']) ? floatval($_POST['maximum_discount']) : null;
    $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $valid_until = $_POST['valid_until'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validar que el cupón pertenece al restaurante
    $stmt = $conn->prepare("SELECT id FROM coupons WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$coupon_id, $restaurant_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Cupón no encontrado');
    }

    // Validar código único (excluyendo el cupón actual)
    $stmt = $conn->prepare("SELECT id FROM coupons WHERE code = ? AND restaurant_id = ? AND id != ?");
    $stmt->execute([$code, $restaurant_id, $coupon_id]);
    if ($stmt->fetch()) {
        throw new Exception('El código del cupón ya existe');
    }

    // Validar tipo de descuento
    if (!in_array($discount_type, ['percentage', 'fixed'])) {
        throw new Exception('Tipo de descuento inválido');
    }

    // Validar valor del descuento
    if ($discount_value <= 0) {
        throw new Exception('El valor del descuento debe ser mayor a 0');
    }

    if ($discount_type === 'percentage' && $discount_value > 100) {
        throw new Exception('El porcentaje de descuento no puede ser mayor al 100%');
    }

    // Validar fecha de expiración
    $valid_until_timestamp = strtotime($valid_until);
    if (!$valid_until_timestamp) {
        throw new Exception('Fecha de expiración inválida');
    }

    if ($valid_until_timestamp <= time()) {
        throw new Exception('La fecha de expiración debe ser futura');
    }

    // Validar límite de usos
    if ($usage_limit !== null && $usage_limit <= 0) {
        throw new Exception('El límite de usos debe ser mayor a 0');
    }

    // Validar descuento máximo
    if ($maximum_discount !== null && $maximum_discount <= 0) {
        throw new Exception('El descuento máximo debe ser mayor a 0');
    }

    // Actualizar cupón
    $stmt = $conn->prepare("
        UPDATE coupons SET 
            code = ?,
            name = ?,
            description = ?,
            discount_type = ?,
            discount_value = ?,
            minimum_order_amount = ?,
            maximum_discount = ?,
            usage_limit = ?,
            valid_until = ?,
            is_active = ?,
            updated_at = NOW()
        WHERE id = ? AND restaurant_id = ?
    ");

    $stmt->execute([
        $code,
        $name,
        $description,
        $discount_type,
        $discount_value,
        $minimum_order_amount,
        $maximum_discount,
        $usage_limit,
        $valid_until,
        $is_active,
        $coupon_id,
        $restaurant_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Cupón actualizado correctamente'
    ]);

} catch (Exception $e) {
    error_log("Error al actualizar cupón: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
