<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['error' => 'No autorizado']));
}

// Verificar que se proporcionó un ID de producto
$product_id = intval($_GET['product_id'] ?? 0);
if ($product_id <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'ID de producto inválido']));
}

try {
    // Verificar que el producto pertenece al restaurante
    $stmt = $conn->prepare("
        SELECT p.id 
        FROM products p
        JOIN restaurants r ON p.restaurant_id = r.id
        WHERE p.id = ? AND r.id = ?
    ");
    $stmt->execute([$product_id, $_SESSION['restaurant_id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        die(json_encode(['error' => 'Producto no encontrado']));
    }
    
    // Obtener las opciones del producto
    $stmt = $conn->prepare("
        SELECT id, name, description, type, is_required, min_selections, max_selections, sort_order
        FROM product_options
        WHERE product_id = ? AND is_active = 1
        ORDER BY sort_order ASC, name ASC
    ");
    $stmt->execute([$product_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada opción, obtener sus valores
    foreach ($options as &$option) {
        $stmt = $conn->prepare("
            SELECT id, name, description, price, sort_order
            FROM product_option_values
            WHERE option_id = ? AND is_active = 1
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt->execute([$option['id']]);
        $option['values'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Devolver las opciones en formato JSON
    header('Content-Type: application/json');
    echo json_encode($options);
    
} catch (PDOException $e) {
    error_log("Error al obtener opciones del producto: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'Error al obtener las opciones del producto']));
} 
