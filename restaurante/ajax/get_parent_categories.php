<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

// Establecer header para JSON
header('Content-Type: application/json');

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar token CSRF
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

try {
    // Verificar que sea una sucursal
    $stmt = $conn->prepare("
        SELECT r.*, parent.id as parent_id, parent.name as parent_name 
        FROM restaurants r
        LEFT JOIN restaurants parent ON r.parent_restaurant_id = parent.id
        WHERE r.id = ? AND r.is_branch = 1
    ");
    $stmt->execute([$restaurant_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch || !$branch['parent_id']) {
        echo json_encode(['success' => false, 'message' => 'Esta funcionalidad solo está disponible para sucursales']);
        exit;
    }
    
    $parent_restaurant_id = $branch['parent_id'];
    
    // Obtener categorías del restaurante padre
    $stmt = $conn->prepare("
        SELECT mc.*, COUNT(p.id) as product_count
        FROM menu_categories mc
        LEFT JOIN products p ON mc.id = p.category_id AND p.is_active = 1
        WHERE mc.restaurant_id = ? AND mc.is_active = 1
        GROUP BY mc.id
        ORDER BY mc.sort_order ASC, mc.name ASC
    ");
    $stmt->execute([$parent_restaurant_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener productos para cada categoría
    foreach ($categories as &$category) {
        $stmt = $conn->prepare("
            SELECT p.id, p.name, p.description, p.price, p.image, p.is_featured
            FROM products p
            WHERE p.category_id = ? AND p.restaurant_id = ? AND p.is_active = 1
            ORDER BY p.is_featured DESC, p.sort_order ASC, p.name ASC
        ");
        $stmt->execute([$category['id'], $parent_restaurant_id]);
        $category['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($category);
    
    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'parent_restaurant' => [
            'id' => $parent_restaurant_id,
            'name' => $branch['parent_name']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error en get_parent_categories.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar las categorías del restaurante principal']);
} catch (Exception $e) {
    error_log("Error inesperado en get_parent_categories.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error inesperado al cargar las categorías']);
}
?> 
