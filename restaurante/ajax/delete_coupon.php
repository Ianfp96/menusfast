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

    // Verificar que el cupón pertenece al restaurante
    $stmt = $conn->prepare("SELECT id, name, code FROM coupons WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$coupon_id, $restaurant_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        throw new Exception('Cupón no encontrado');
    }

    // Eliminar el cupón (esto también eliminará automáticamente los registros de uso por la FK)
    $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$coupon_id, $restaurant_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('No se pudo eliminar el cupón');
    }

    // Registrar en el log de actividad
    logActivity($restaurant_id, 'coupon_delete', "Cupón eliminado: {$coupon['name']} (Código: {$coupon['code']})");

    echo json_encode([
        'success' => true,
        'message' => 'Cupón eliminado exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error al eliminar cupón: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
