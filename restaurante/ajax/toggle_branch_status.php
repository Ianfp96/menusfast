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

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];

// Validar y sanitizar datos de entrada
$branch_id = (int)($_POST['branch_id'] ?? 0);
$is_active = (int)($_POST['is_active'] ?? 0);

if (empty($branch_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de sucursal inválido']);
    exit;
}

try {
    // Verificar que la sucursal pertenece al restaurante
    $stmt = $conn->prepare("SELECT id FROM branches WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$branch_id, $restaurant_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada']);
        exit;
    }
    
    // Actualizar el estado de la sucursal
    $stmt = $conn->prepare("
        UPDATE branches 
        SET is_active = :is_active 
        WHERE id = :branch_id AND restaurant_id = :restaurant_id
    ");
    
    $stmt->execute([
        ':branch_id' => $branch_id,
        ':restaurant_id' => $restaurant_id,
        ':is_active' => $is_active
    ]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("No se pudo actualizar el estado de la sucursal");
    }
    
    // Registrar la acción en el log
    $status = $is_active ? 'activada' : 'desactivada';
    error_log("Sucursal $status - ID: $branch_id, Restaurante: $restaurant_id");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Estado de la sucursal actualizado exitosamente'
    ]);
    
} catch (Exception $e) {
    error_log("Error al cambiar estado de sucursal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cambiar el estado de la sucursal']);
} 
