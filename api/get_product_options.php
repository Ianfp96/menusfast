<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar que se proporcionó un ID de producto
if (!isset($_GET['product_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'ID de producto no proporcionado'
    ]);
    exit;
}

$product_id = intval($_GET['product_id']);

try {
    // Obtener el producto y sus opciones
    $stmt = $conn->prepare("SELECT id, menu_options FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception("Producto no encontrado");
    }
    
    // Decodificar las opciones
    $menu_options = json_decode($product['menu_options'] ?? '{"options":[]}', true);
    
    // Verificar que el JSON es válido
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error al decodificar las opciones del producto");
    }
    
    // Devolver las opciones
    echo json_encode([
        'success' => true,
        'options' => $menu_options['options'] ?? []
    ]);
    
} catch (Exception $e) {
    error_log("Error al obtener opciones del producto: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 
