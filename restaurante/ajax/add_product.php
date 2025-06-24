<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

// Habilitar logging de errores
error_log("Iniciando add_product.php");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    error_log("Usuario no autorizado");
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Método no permitido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token'])) {
    error_log("Token CSRF no presente en la solicitud");
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token CSRF no presente']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'])) {
    error_log("Token CSRF inválido");
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

// Obtener y validar datos del formulario
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$category_id = intval($_POST['category_id'] ?? 0);
$price = floatval($_POST['price'] ?? 0);
$is_available = isset($_POST['is_available']) ? 1 : 0;
$is_featured = isset($_POST['is_featured']) ? 1 : 0;
$restaurant_id = $_SESSION['restaurant_id'] ?? null;

error_log("Datos recibidos - Nombre: $name, Categoría: $category_id, Precio: $price, Restaurant ID: $restaurant_id");

// Validar datos requeridos
if (empty($name)) {
    error_log("Nombre de producto vacío");
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'El nombre del producto es requerido']);
    exit;
}

if (!$category_id) {
    error_log("Categoría no seleccionada");
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Debes seleccionar una categoría']);
    exit;
}

if ($price <= 0) {
    error_log("Precio inválido");
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'El precio debe ser mayor a 0']);
    exit;
}

if (!$restaurant_id) {
    error_log("ID de restaurante no encontrado en la sesión");
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de restaurante no encontrado']);
    exit;
}

try {
    // Verificar que la categoría pertenece al restaurante
    $stmt = $conn->prepare("SELECT id FROM menu_categories WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$category_id, $restaurant_id]);
    if (!$stmt->fetch()) {
        error_log("Categoría no encontrada o no pertenece al restaurante");
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Categoría no válida']);
        exit;
    }

    // Verificar límite de productos según el plan
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(p.max_products, 20) as max_products,
            (SELECT COUNT(*) FROM products WHERE restaurant_id = ?) as current_products,
            p.name as plan_name
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        WHERE r.id = ?
    ");
    $stmt->execute([$restaurant_id, $restaurant_id]);
    $limits = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("Límites encontrados: " . print_r($limits, true));

    if ($limits['current_products'] >= $limits['max_products']) {
        error_log("Límite de productos alcanzado");
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => "Has alcanzado el límite de productos permitidos en tu plan actual ({$limits['plan_name']}). <a href='" . BASE_URL . "/restaurante/plan.php'>Actualiza tu plan</a> para agregar más productos."
        ]);
        exit;
    }

    // Obtener el siguiente sort_order
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(sort_order), 0) + 1 
        FROM products 
        WHERE restaurant_id = ? AND category_id = ?
    ");
    $stmt->execute([$restaurant_id, $category_id]);
    $next_order = $stmt->fetchColumn();

    // Procesar la imagen si se subió una
    $image_path = null;
    $upload_dir = __DIR__ . '/../../uploads/';
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    // Verificar que el directorio de uploads existe y tiene permisos de escritura
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("No se pudo crear el directorio de uploads: $upload_dir");
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error del servidor: No se pudo crear el directorio de uploads']);
            exit;
        }
    }

    if (!is_writable($upload_dir)) {
        error_log("El directorio de uploads no tiene permisos de escritura: $upload_dir");
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error del servidor: El directorio de uploads no tiene permisos de escritura']);
        exit;
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        // Validar tipo de archivo
        if (!in_array($file_extension, $allowed_extensions)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido']);
            exit;
        }

        // Validar tamaño (5MB máximo)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'La imagen es demasiado grande']);
            exit;
        }

        // Generar nombre único para el archivo
        $new_filename = uniqid('product_') . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        // Mover el archivo
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $image_path = $new_filename;
        } else {
            error_log("Error al mover archivo: " . error_get_last()['message']);
            throw new Exception('Error al subir la imagen: ' . error_get_last()['message']);
        }
    }

    // Insertar el producto
    $stmt = $conn->prepare("
        INSERT INTO products 
        (restaurant_id, category_id, name, description, price, image, is_available, is_featured, sort_order, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");

    if ($stmt->execute([
        $restaurant_id, 
        $category_id, 
        $name, 
        $description, 
        $price, 
        $image_path, 
        $is_available, 
        $is_featured, 
        $next_order
    ])) {
        $product_id = $conn->lastInsertId();
        
        // Registrar la actividad
        logActivity($restaurant_id, 'product_create', "Producto creado: $name");
        
        // Enviar headers antes del contenido JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Producto agregado exitosamente',
            'product_id' => $product_id
        ]);
    } else {
        throw new Exception('Error al crear el producto');
    }

} catch (Exception $e) {
    error_log("Error al crear producto: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Si hubo un error y se subió un archivo, eliminarlo
    if (isset($image_path) && file_exists($upload_dir . $image_path)) {
        unlink($upload_dir . $image_path);
    }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear el producto: ' . $e->getMessage()
    ]);
}
?> 
