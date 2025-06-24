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

// Validar y sanitizar datos de entrada
$branch_id = (int)($_GET['branch_id'] ?? 0);

if (empty($branch_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de sucursal inválido']);
    exit;
}

try {
    // Obtener datos de la sucursal
    $stmt = $conn->prepare("
        SELECT b.*, 
               0 as today_orders,
               0 as today_sales
        FROM branches b
        WHERE b.id = ? AND b.restaurant_id = ?
    ");
    $stmt->execute([$branch_id, $restaurant_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'branch' => $branch
    ]);
    
} catch (Exception $e) {
    error_log("Error al obtener datos de sucursal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener los datos de la sucursal']);
} 
