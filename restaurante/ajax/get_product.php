<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Verificar que sea una petición AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['success' => false, 'message' => 'Acceso no permitido']));
}

// Verificar que el usuario esté autenticado
if (!isLoggedIn()) {
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

// Verificar que se haya proporcionado un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'ID de producto inválido']));
}

$product_id = (int)$_GET['id'];

try {
    $db = getDBConnection();
    
    // Obtener los datos del producto
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        die(json_encode(['success' => false, 'message' => 'Producto no encontrado']));
    }
    
    // Devolver los datos del producto
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
    
} catch (PDOException $e) {
    error_log("Error al obtener producto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener los datos del producto']);
} 
