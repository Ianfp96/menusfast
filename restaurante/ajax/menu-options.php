<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];
$action = $_POST['action'] ?? '';

// Verificar token CSRF
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
    exit;
}

try {
    switch ($action) {
        case 'get_options':
            $product_id = intval($_POST['product_id'] ?? 0);
            error_log("Obteniendo opciones para product_id: " . $product_id . ", restaurant_id: " . $restaurant_id);
            
            // Verificar que el producto pertenece al restaurante
            $stmt = $conn->prepare("
                SELECT p.id, p.name 
                FROM products p 
                WHERE p.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$product_id, $restaurant_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                error_log("Producto no encontrado - product_id: " . $product_id . ", restaurant_id: " . $restaurant_id);
                throw new Exception("Producto no encontrado");
            }
            
            error_log("Producto encontrado: " . $product['name']);

            // Obtener opciones con sus valores
            $stmt = $conn->prepare("
                SELECT 
                    o.id as option_id,
                    o.name as option_name,
                    o.type,
                    o.description as option_description,
                    o.is_required,
                    o.show_price,
                    o.sort_order as option_sort_order,
                    v.id as value_id,
                    v.name as value_name,
                    v.price as value_price,
                    v.sort_order as value_sort_order
                FROM product_menu_options o
                LEFT JOIN product_menu_option_values v ON o.id = v.option_id
                WHERE o.product_id = ?
                ORDER BY o.sort_order ASC, v.sort_order ASC
            ");
            $stmt->execute([$product_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Resultados de la consulta: " . print_r($results, true));

            // Procesar los resultados para agrupar valores por opción
            $options = [];
            foreach ($results as $row) {
                if (!isset($options[$row['option_id']])) {
                    $options[$row['option_id']] = [
                        'id' => $row['option_id'],
                        'name' => $row['option_name'],
                        'type' => $row['type'],
                        'description' => $row['option_description'],
                        'is_required' => $row['is_required'],
                        'show_price' => $row['show_price'],
                        'sort_order' => $row['option_sort_order'],
                        'values' => []
                    ];
                }
                
                // Agregar el valor si existe
                if ($row['value_id']) {
                    $options[$row['option_id']]['values'][] = [
                        'id' => $row['value_id'],
                        'name' => $row['value_name'],
                        'price' => $row['value_price'],
                        'sort_order' => $row['value_sort_order']
                    ];
                }
            }

            // Convertir el array asociativo a array indexado
            $options = array_values($options);
            error_log("Opciones procesadas: " . print_r($options, true));

            echo json_encode(['success' => true, 'options' => $options]);
            break;

        case 'add_option':
            $product_id = intval($_POST['product_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'single';
            $description = trim($_POST['description'] ?? '');
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $show_price = isset($_POST['show_price']) ? 1 : 0;

            if (empty($name)) {
                throw new Exception("El nombre de la opción es requerido");
            }

            // Verificar que el producto pertenece al restaurante
            $stmt = $conn->prepare("
                SELECT id 
                FROM products 
                WHERE id = ? AND restaurant_id = ?
            ");
            $stmt->execute([$product_id, $restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Producto no encontrado");
            }

            // Obtener el siguiente sort_order
            $stmt = $conn->prepare("
                SELECT COALESCE(MAX(sort_order), 0) + 1 
                FROM product_menu_options 
                WHERE product_id = ?
            ");
            $stmt->execute([$product_id]);
            $next_order = $stmt->fetchColumn();

            // Insertar la opción
            $stmt = $conn->prepare("
                INSERT INTO product_menu_options 
                (product_id, name, type, description, is_required, show_price, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $product_id, $name, $type, $description, 
                $is_required, $show_price, $next_order
            ])) {
                $option_id = $conn->lastInsertId();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Opción agregada exitosamente',
                    'option_id' => $option_id
                ]);
            } else {
                throw new Exception("Error al agregar la opción");
            }
            break;

        case 'add_value':
            $option_id = intval($_POST['option_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $price = floatval($_POST['price'] ?? 0);

            if (empty($name)) {
                throw new Exception("El nombre del valor es requerido");
            }

            // Verificar que la opción pertenece a un producto del restaurante
            $stmt = $conn->prepare("
                SELECT o.id 
                FROM product_menu_options o
                JOIN products p ON o.product_id = p.id
                WHERE o.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$option_id, $restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Opción no encontrada");
            }

            // Obtener el siguiente sort_order
            $stmt = $conn->prepare("
                SELECT COALESCE(MAX(sort_order), 0) + 1 
                FROM product_menu_option_values 
                WHERE option_id = ?
            ");
            $stmt->execute([$option_id]);
            $next_order = $stmt->fetchColumn();

            // Insertar el valor
            $stmt = $conn->prepare("
                INSERT INTO product_menu_option_values 
                (option_id, name, price, sort_order)
                VALUES (?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$option_id, $name, $price, $next_order])) {
                $value_id = $conn->lastInsertId();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Valor agregado exitosamente',
                    'value_id' => $value_id
                ]);
            } else {
                throw new Exception("Error al agregar el valor");
            }
            break;

        case 'update_option':
            $option_id = intval($_POST['option_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'single';
            $description = trim($_POST['description'] ?? '');
            $is_required = isset($_POST['is_required']) ? 1 : 0;
            $show_price = isset($_POST['show_price']) ? 1 : 0;

            if (empty($name)) {
                throw new Exception("El nombre de la opción es requerido");
            }

            // Verificar que la opción pertenece a un producto del restaurante
            $stmt = $conn->prepare("
                SELECT o.id 
                FROM product_menu_options o
                JOIN products p ON o.product_id = p.id
                WHERE o.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$option_id, $restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Opción no encontrada");
            }

            // Actualizar la opción
            $stmt = $conn->prepare("
                UPDATE product_menu_options 
                SET name = ?, type = ?, description = ?, 
                    is_required = ?, show_price = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([
                $name, $type, $description, 
                $is_required, $show_price, $option_id
            ])) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Opción actualizada exitosamente'
                ]);
            } else {
                throw new Exception("Error al actualizar la opción");
            }
            break;

        case 'update_value':
            $value_id = intval($_POST['value_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $price = floatval($_POST['price'] ?? 0);

            if (empty($name)) {
                throw new Exception("El nombre del valor es requerido");
            }

            // Verificar que el valor pertenece a una opción de un producto del restaurante
            $stmt = $conn->prepare("
                SELECT v.id 
                FROM product_menu_option_values v
                JOIN product_menu_options o ON v.option_id = o.id
                JOIN products p ON o.product_id = p.id
                WHERE v.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$value_id, $restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Valor no encontrado");
            }

            // Actualizar el valor
            $stmt = $conn->prepare("
                UPDATE product_menu_option_values 
                SET name = ?, price = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([$name, $price, $value_id])) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Valor actualizado exitosamente'
                ]);
            } else {
                throw new Exception("Error al actualizar el valor");
            }
            break;

        case 'delete_option':
            $option_id = intval($_POST['option_id'] ?? 0);

            // Verificar que la opción pertenece a un producto del restaurante
            $stmt = $conn->prepare("
                SELECT o.id 
                FROM product_menu_options o
                JOIN products p ON o.product_id = p.id
                WHERE o.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$option_id, $restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Opción no encontrada");
            }

            // Eliminar la opción (los valores se eliminarán por CASCADE)
            $stmt = $conn->prepare("DELETE FROM product_menu_options WHERE id = ?");
            if ($stmt->execute([$option_id])) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Opción eliminada exitosamente'
                ]);
            } else {
                throw new Exception("Error al eliminar la opción");
            }
            break;

        case 'delete_value':
            $value_id = intval($_POST['value_id'] ?? 0);

            // Verificar que el valor pertenece a una opción de un producto del restaurante
            $stmt = $conn->prepare("
                SELECT v.id 
                FROM product_menu_option_values v
                JOIN product_menu_options o ON v.option_id = o.id
                JOIN products p ON o.product_id = p.id
                WHERE v.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$value_id, $restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Valor no encontrado");
            }

            // Eliminar el valor
            $stmt = $conn->prepare("DELETE FROM product_menu_option_values WHERE id = ?");
            if ($stmt->execute([$value_id])) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Valor eliminado exitosamente'
                ]);
            } else {
                throw new Exception("Error al eliminar el valor");
            }
            break;

        case 'update_order':
            $orders = json_decode($_POST['orders'] ?? '[]', true);
            
            if (!is_array($orders)) {
                throw new Exception("Datos de orden inválidos");
            }

            $conn->beginTransaction();

            try {
                // Actualizar orden de opciones
                if (isset($orders['options'])) {
                    $stmt = $conn->prepare("
                        UPDATE product_menu_options 
                        SET sort_order = ? 
                        WHERE id = ? AND product_id IN (
                            SELECT id FROM products WHERE restaurant_id = ?
                        )
                    ");
                    
                    foreach ($orders['options'] as $order) {
                        $stmt->execute([
                            $order['order'], 
                            $order['id'], 
                            $restaurant_id
                        ]);
                    }
                }

                // Actualizar orden de valores
                if (isset($orders['values'])) {
                    $stmt = $conn->prepare("
                        UPDATE product_menu_option_values v
                        JOIN product_menu_options o ON v.option_id = o.id
                        JOIN products p ON o.product_id = p.id
                        SET v.sort_order = ?
                        WHERE v.id = ? AND p.restaurant_id = ?
                    ");
                    
                    foreach ($orders['values'] as $order) {
                        $stmt->execute([
                            $order['order'], 
                            $order['id'], 
                            $restaurant_id
                        ]);
                    }
                }

                $conn->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Orden actualizado exitosamente'
                ]);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;

        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    error_log("Error en menu-options.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?> 
