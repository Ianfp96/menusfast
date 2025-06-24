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

// Validar datos de entrada
$product_ids = $input['product_ids'] ?? [];
if (empty($product_ids) || !is_array($product_ids)) {
    echo json_encode(['success' => false, 'message' => 'No se seleccionaron productos para importar']);
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
    
    // Verificar límites del plan
    $stmt = $conn->prepare("
        SELECT p.max_products, p.max_categories,
               (SELECT COUNT(*) FROM products WHERE restaurant_id = ?) as current_products,
               (SELECT COUNT(*) FROM menu_categories WHERE restaurant_id = ?) as current_categories
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        WHERE r.id = ?
    ");
    $stmt->execute([$restaurant_id, $restaurant_id, $restaurant_id]);
    $limits = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$limits) {
        echo json_encode(['success' => false, 'message' => 'No se pudieron verificar los límites del plan']);
        exit;
    }
    
    // Obtener productos del restaurante padre
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT p.*, mc.name as category_name, mc.description as category_description
        FROM products p
        JOIN menu_categories mc ON p.category_id = mc.id
        WHERE p.id IN ($placeholders) AND p.restaurant_id = ? AND p.is_active = 1
    ");
    
    $params = array_merge($product_ids, [$parent_restaurant_id]);
    $stmt->execute($params);
    $parent_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($parent_products)) {
        echo json_encode(['success' => false, 'message' => 'No se encontraron productos válidos para importar']);
        exit;
    }
    
    // Iniciar transacción
    $conn->beginTransaction();
    
    $imported_products = 0;
    $imported_categories = 0;
    $category_mapping = []; // Mapeo de categorías padre -> categorías hijas
    
    try {
        // Procesar cada producto
        foreach ($parent_products as $parent_product) {
            $parent_category_id = $parent_product['category_id'];
            
            // Verificar si ya existe la categoría en la sucursal
            if (!isset($category_mapping[$parent_category_id])) {
                $stmt = $conn->prepare("
                    SELECT id FROM menu_categories 
                    WHERE restaurant_id = ? AND name = ?
                ");
                $stmt->execute([$restaurant_id, $parent_product['category_name']]);
                $existing_category = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_category) {
                    // Usar categoría existente
                    $category_mapping[$parent_category_id] = $existing_category['id'];
                } else {
                    // Verificar límite de categorías
                    if ($limits['current_categories'] >= $limits['max_categories']) {
                        throw new Exception("Has alcanzado el límite de categorías de tu plan. No se pueden importar más productos.");
                    }
                    
                    // Crear nueva categoría
                    $stmt = $conn->prepare("
                        INSERT INTO menu_categories (
                            restaurant_id, name, description, sort_order, is_active, created_at
                        ) VALUES (?, ?, ?, ?, 1, NOW())
                    ");
                    $stmt->execute([
                        $restaurant_id,
                        $parent_product['category_name'],
                        $parent_product['category_description'],
                        $limits['current_categories'] + 1
                    ]);
                    
                    $new_category_id = $conn->lastInsertId();
                    $category_mapping[$parent_category_id] = $new_category_id;
                    $limits['current_categories']++;
                    $imported_categories++;
                }
            }
            
            $new_category_id = $category_mapping[$parent_category_id];
            
            // Verificar límite de productos
            if ($limits['current_products'] >= $limits['max_products']) {
                throw new Exception("Has alcanzado el límite de productos de tu plan. No se pueden importar más productos.");
            }
            
            // Verificar si el producto ya existe en la sucursal
            $stmt = $conn->prepare("
                SELECT id FROM products 
                WHERE restaurant_id = ? AND name = ? AND category_id = ?
            ");
            $stmt->execute([$restaurant_id, $parent_product['name'], $new_category_id]);
            $existing_product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_product) {
                // Producto ya existe, saltar
                continue;
            }
            
            // Crear el producto en la sucursal
            $stmt = $conn->prepare("
                INSERT INTO products (
                    restaurant_id, category_id, name, description, price, image,
                    is_available, is_active, sort_order, is_featured, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $restaurant_id,
                $new_category_id,
                $parent_product['name'],
                $parent_product['description'],
                $parent_product['price'],
                $parent_product['image'], // Copiar la imagen
                $parent_product['is_available'],
                $parent_product['sort_order'],
                $parent_product['is_featured']
            ]);
            
            $limits['current_products']++;
            $imported_products++;
        }
        
        // Confirmar transacción
        $conn->commit();
        
        // Mensaje de éxito
        $message = "Importación completada exitosamente. ";
        if ($imported_categories > 0) {
            $message .= "Se crearon $imported_categories categorías nuevas. ";
        }
        $message .= "Se importaron $imported_products productos.";
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'imported_products' => $imported_products,
            'imported_categories' => $imported_categories
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Error en import_products.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al importar los productos: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error inesperado en import_products.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 
