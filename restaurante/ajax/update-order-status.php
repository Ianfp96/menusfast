<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['order_id']) || !isset($data['status']) || !isset($data['action']) || !isset($data['csrf_token'])) {
        throw new Exception('Datos incompletos');
    }

    // Verificar token CSRF
    if (!verifyCSRFToken($data['csrf_token'])) {
        throw new Exception('Token inválido');
    }

    $order_id = $data['order_id'];
    $status = $data['status'];
    $action = $data['action'];
    $notes = $data['notes'] ?? null;

    // Verificar que la orden pertenece al restaurante
    $stmt = $conn->prepare("SELECT restaurant_id FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || $order['restaurant_id'] != $_SESSION['restaurant_id']) {
        throw new Exception('Orden no encontrada o no autorizada');
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Actualizar estado de la orden
    $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$status, $order_id]);

    // Registrar en el historial
    $stmt = $conn->prepare("
        INSERT INTO order_history (order_id, status, action, notes) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$order_id, $status, $action, $notes]);

    // Confirmar transacción
    $conn->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en update-order-status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 
