<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

session_start();

// Habilitar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    error_log("Intento de eliminación de producto sin sesión activa");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Intento de eliminación de producto con método no permitido: " . $_SERVER['REQUEST_METHOD']);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    error_log("Token CSRF inválido o no proporcionado en eliminación de producto");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];
$product_id = (int)($_POST['product_id'] ?? 0);

error_log("Intentando eliminar producto - ID: $product_id, Restaurant ID: $restaurant_id");

if ($product_id <= 0) {
    error_log("ID de producto inválido: $product_id");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
    exit;
}

try {
    // Verificar que el producto pertenece al restaurante
    $stmt = $conn->prepare("SELECT name, image FROM products WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$product_id, $restaurant_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        error_log("Producto no encontrado o no pertenece al restaurante - ID: $product_id, Restaurant ID: $restaurant_id");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit;
    }

    // Iniciar transacción
    $conn->beginTransaction();

    try {
        // Eliminar opciones del producto primero (si existen)
        $stmt = $conn->prepare("DELETE FROM product_menu_options WHERE product_id = ?");
        $stmt->execute([$product_id]);
        
        // Eliminar el producto
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND restaurant_id = ?");
        $result = $stmt->execute([$product_id, $restaurant_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            // Eliminar imagen si existe
            if ($product['image'] && file_exists(__DIR__ . '/../../uploads/' . $product['image'])) {
                unlink(__DIR__ . '/../../uploads/' . $product['image']);
            }
            
            // Registrar la actividad
            logActivity($restaurant_id, 'product_delete', "Producto eliminado: {$product['name']}");
            
            // Confirmar transacción
            $conn->commit();
            
            error_log("Producto eliminado exitosamente - ID: $product_id, Nombre: {$product['name']}");
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Producto eliminado exitosamente']);
        } else {
            throw new Exception('No se pudo eliminar el producto');
        }
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Error al eliminar producto: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el producto: ' . $e->getMessage()]);
}
?> 
