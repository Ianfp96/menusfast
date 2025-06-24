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

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

// Obtener y validar datos del formulario
$product_id = intval($_POST['product_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$category_id = intval($_POST['category_id'] ?? 0);
$price = floatval($_POST['price'] ?? 0);
$is_available = isset($_POST['is_available']) ? 1 : 0;
$is_featured = isset($_POST['is_featured']) ? 1 : 0;
$current_image = $_POST['current_image'] ?? '';
$restaurant_id = $_SESSION['restaurant_id'];

// Validar datos requeridos
if (!$product_id || !$name || !$category_id || $price <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Todos los campos marcados con * son requeridos']);
    exit;
}

try {
    // Verificar que el producto pertenece al restaurante
    $stmt = $conn->prepare("SELECT id, image FROM products WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$product_id, $restaurant_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit;
    }

    // Verificar que la categoría pertenece al restaurante
    $stmt = $conn->prepare("SELECT id FROM menu_categories WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$category_id, $restaurant_id]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Categoría no válida']);
        exit;
    }

    // Procesar la imagen si se subió una nueva
    $image_path = $current_image;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/';
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        // Validar tipo de archivo
        if (!in_array($file_extension, $allowed_extensions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido']);
            exit;
        }

        // Validar tamaño (5MB máximo)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande']);
            exit;
        }

        // Generar nombre único para el archivo
        $new_filename = uniqid('product_') . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        // Mover el archivo
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            // Eliminar imagen anterior si existe
            if ($product['image'] && file_exists($upload_dir . $product['image'])) {
                unlink($upload_dir . $product['image']);
            }
            $image_path = $new_filename;
        } else {
            throw new Exception('Error al subir la imagen');
        }
    }

    // Actualizar el producto
    $stmt = $conn->prepare("
        UPDATE products 
        SET name = ?, 
            description = ?, 
            category_id = ?, 
            price = ?, 
            is_available = ?, 
            is_featured = ?,
            image = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND restaurant_id = ?
    ");

    if ($stmt->execute([
        $name, 
        $description, 
        $category_id, 
        $price, 
        $is_available, 
        $is_featured,
        $image_path,
        $product_id,
        $restaurant_id
    ])) {
        // Registrar la actividad
        logActivity($restaurant_id, 'product_update', "Producto actualizado: $name");
        
        echo json_encode([
            'success' => true,
            'message' => 'Producto actualizado exitosamente'
        ]);
    } else {
        throw new Exception('Error al actualizar el producto');
    }

} catch (Exception $e) {
    error_log("Error al actualizar producto: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar el producto: ' . $e->getMessage()
    ]);
} 
