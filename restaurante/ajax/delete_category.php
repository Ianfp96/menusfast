<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

session_start();

// Habilitar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    error_log("Intento de eliminación de categoría sin sesión activa");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Intento de eliminación de categoría con método no permitido: " . $_SERVER['REQUEST_METHOD']);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    error_log("Token CSRF inválido o no proporcionado en eliminación de categoría");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];
$category_id = (int)($_POST['category_id'] ?? 0);

error_log("Intentando eliminar categoría - ID: $category_id, Restaurant ID: $restaurant_id");

if ($category_id <= 0) {
    error_log("ID de categoría inválido: $category_id");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de categoría inválido']);
    exit;
}

try {
    // Verificar que la categoría pertenece al restaurante
    $stmt = $conn->prepare("SELECT name FROM menu_categories WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$category_id, $restaurant_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        error_log("Categoría no encontrada o no pertenece al restaurante - ID: $category_id, Restaurant ID: $restaurant_id");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Categoría no encontrada']);
        exit;
    }

    // Verificar si hay productos en la categoría
    $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND restaurant_id = ?");
    $stmt->execute([$category_id, $restaurant_id]);
    $product_count = $stmt->fetchColumn();

    // Iniciar transacción
    $conn->beginTransaction();

    try {
        // Si hay productos, eliminarlos primero
        if ($product_count > 0) {
            // Eliminar opciones de productos primero
            $stmt = $conn->prepare("
                DELETE pmo FROM product_menu_options pmo 
                INNER JOIN products p ON pmo.product_id = p.id 
                WHERE p.category_id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$category_id, $restaurant_id]);
            
            // Eliminar los productos
            $stmt = $conn->prepare("DELETE FROM products WHERE category_id = ? AND restaurant_id = ?");
            $stmt->execute([$category_id, $restaurant_id]);
            
            error_log("Productos eliminados de la categoría - ID: $category_id, Productos eliminados: $product_count");
        }

        // Eliminar la categoría
        $stmt = $conn->prepare("DELETE FROM menu_categories WHERE id = ? AND restaurant_id = ?");
        $result = $stmt->execute([$category_id, $restaurant_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            // Registrar la actividad
            $message = "Categoría eliminada: {$category['name']}";
            if ($product_count > 0) {
                $message .= " (con $product_count producto" . ($product_count > 1 ? 's' : '') . ")";
            }
            logActivity($restaurant_id, 'category_delete', $message);
            
            // Confirmar transacción
            $conn->commit();
            
            error_log("Categoría eliminada exitosamente - ID: $category_id, Nombre: {$category['name']}, Productos eliminados: $product_count");
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Categoría eliminada exitosamente' . ($product_count > 0 ? " (se eliminaron $product_count producto" . ($product_count > 1 ? 's' : '') . ")" : '')
            ]);
        } else {
            throw new Exception('No se pudo eliminar la categoría');
        }
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Error al eliminar categoría: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la categoría: ' . $e->getMessage()]);
} 
