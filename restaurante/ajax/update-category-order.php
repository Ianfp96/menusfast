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

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener y decodificar el cuerpo de la petición
$input = json_decode(file_get_contents('php://input'), true);

// Verificar token CSRF
if (!isset($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad']);
    exit;
}

// Verificar que se recibieron las categorías
if (!isset($input['categories']) || !is_array($input['categories'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];

try {
    // Iniciar transacción
    $conn->beginTransaction();
    
    // Preparar la consulta
    $stmt = $conn->prepare("
        UPDATE menu_categories 
        SET sort_order = ? 
        WHERE id = ? AND restaurant_id = ?
    ");
    
    // Actualizar cada categoría
    foreach ($input['categories'] as $category) {
        if (!isset($category['id']) || !isset($category['order'])) {
            throw new Exception('Datos de categoría inválidos');
        }
        
        $stmt->execute([
            $category['order'],
            $category['id'],
            $restaurant_id
        ]);
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Registrar la actividad
    logActivity($restaurant_id, 'category_order_update', 'Orden de categorías actualizado');
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error al actualizar orden de categorías: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el orden']);
} 
