<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

session_start();

header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    if (!isset($_GET['order_id'])) {
        throw new Exception('ID de pedido no proporcionado');
    }

    $order_id = intval($_GET['order_id']);
    
    if ($order_id <= 0) {
        throw new Exception('ID de pedido inválido');
    }

    // Verificar que la orden pertenece al restaurante
    $stmt = $conn->prepare("
        SELECT o.*, 
               COALESCE(o.customer_email, '') as customer_email
        FROM orders o
        WHERE o.id = ? AND o.restaurant_id = ?
    ");
    
    if (!$stmt->execute([$order_id, $_SESSION['restaurant_id']])) {
        throw new Exception('Error al consultar el pedido');
    }
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Pedido no encontrado o no autorizado');
    }

    // Obtener el historial de la orden
    $stmt = $conn->prepare("
        SELECT * FROM order_history 
        WHERE order_id = ? 
        ORDER BY created_at DESC
    ");
    
    if (!$stmt->execute([$order_id])) {
        throw new Exception('Error al consultar el historial del pedido');
    }
    
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Asegurarse de que los items sean un array JSON válido
    if (!empty($order['items'])) {
        $items = json_decode($order['items'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error al procesar los items del pedido');
        }
        $order['items'] = $items;
    } else {
        $order['items'] = [];
    }

    // Agregar el historial a la respuesta
    $order['history'] = $history;

    echo json_encode([
        'success' => true,
        'order' => $order
    ]);

} catch (Exception $e) {
    error_log("Error en get-order-details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Función auxiliar para obtener la clase del badge según el estado
function getStatusBadgeClass($status) {
    $classes = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'preparing' => 'primary',
        'ready' => 'success',
        'delivered' => 'secondary',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
} 
