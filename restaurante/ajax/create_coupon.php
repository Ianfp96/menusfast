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
    $required_fields = ['code', 'name', 'discount_type', 'discount_value', 'valid_until'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo '$field' es requerido");
        }
    }

    $code = strtoupper(trim($_POST['code']));
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');
    $discount_type = $_POST['discount_type'];
    $discount_value = floatval($_POST['discount_value']);
    $minimum_order_amount = floatval($_POST['minimum_order_amount'] ?? 0);
    $maximum_discount = !empty($_POST['maximum_discount']) ? floatval($_POST['maximum_discount']) : null;
    $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
    $valid_until = $_POST['valid_until'];

    // Validaciones
    if (!in_array($discount_type, ['percentage', 'fixed'])) {
        throw new Exception('Tipo de descuento inválido');
    }

    if ($discount_value <= 0) {
        throw new Exception('El valor del descuento debe ser mayor a 0');
    }

    if ($discount_type === 'percentage' && $discount_value > 100) {
        throw new Exception('El porcentaje de descuento no puede ser mayor al 100%');
    }

    if ($minimum_order_amount < 0) {
        throw new Exception('El monto mínimo no puede ser negativo');
    }

    if ($maximum_discount !== null && $maximum_discount <= 0) {
        throw new Exception('El descuento máximo debe ser mayor a 0');
    }

    if ($usage_limit !== null && $usage_limit <= 0) {
        throw new Exception('El límite de usos debe ser mayor a 0');
    }

    $valid_until_timestamp = strtotime($valid_until);
    if ($valid_until_timestamp === false || $valid_until_timestamp <= time()) {
        throw new Exception('La fecha de expiración debe ser futura');
    }

    // Verificar que el código no exista para este restaurante
    $stmt = $conn->prepare("SELECT id FROM coupons WHERE code = ? AND restaurant_id = ?");
    $stmt->execute([$code, $restaurant_id]);
    if ($stmt->fetch()) {
        throw new Exception('Ya existe un cupón con este código');
    }

    // Insertar el cupón
    $stmt = $conn->prepare("
        INSERT INTO coupons (
            restaurant_id, code, name, description, discount_type, discount_value,
            minimum_order_amount, maximum_discount, usage_limit, valid_from, valid_until
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");

    $stmt->execute([
        $restaurant_id, $code, $name, $description, $discount_type, $discount_value,
        $minimum_order_amount, $maximum_discount, $usage_limit, $valid_until
    ]);

    $coupon_id = $conn->lastInsertId();

    // Registrar en el log de actividad
    logActivity($restaurant_id, 'coupon_create', "Cupón creado: $name (Código: $code)");

    echo json_encode([
        'success' => true,
        'message' => 'Cupón creado exitosamente',
        'coupon_id' => $coupon_id
    ]);

} catch (Exception $e) {
    error_log("Error al crear cupón: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
