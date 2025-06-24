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
    if (empty($_POST['coupon_id'])) {
        throw new Exception('ID del cupón requerido');
    }

    $coupon_id = intval($_POST['coupon_id']);

    // Obtener información del cupón
    $stmt = $conn->prepare("
        SELECT c.*
        FROM coupons c
        WHERE c.id = ? AND c.restaurant_id = ?
    ");
    $stmt->execute([$coupon_id, $restaurant_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        throw new Exception('Cupón no encontrado');
    }

    echo json_encode([
        'success' => true,
        'coupon' => $coupon
    ]);

} catch (Exception $e) {
    error_log("Error al obtener cupón: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
