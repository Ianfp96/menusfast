<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

// Habilitar logging de errores
error_log("Iniciando add_category.php");
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
$restaurant_id = $_SESSION['restaurant_id'] ?? null;

error_log("Datos recibidos - Nombre: $name, Descripción: $description, Restaurant ID: $restaurant_id");

// Validar datos requeridos
if (empty($name)) {
    error_log("Nombre de categoría vacío");
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'El nombre de la categoría es requerido']);
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
    // Verificar límite de categorías según el plan
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(p.max_categories, 5) as max_categories,
            (SELECT COUNT(*) FROM menu_categories WHERE restaurant_id = ?) as current_categories,
            p.name as plan_name
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        WHERE r.id = ?
    ");
    $stmt->execute([$restaurant_id, $restaurant_id]);
    $limits = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("Límites encontrados: " . print_r($limits, true));

    if ($limits['current_categories'] >= $limits['max_categories']) {
        error_log("Límite de categorías alcanzado");
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => "Has alcanzado el límite de categorías permitidas en tu plan actual ({$limits['plan_name']}). <a href='" . BASE_URL . "/restaurante/plan.php'>Actualiza tu plan</a> para agregar más categorías."
        ]);
        exit;
    }

    // Obtener el siguiente sort_order
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(sort_order), 0) + 1 
        FROM menu_categories 
        WHERE restaurant_id = ?
    ");
    $stmt->execute([$restaurant_id]);
    $next_order = $stmt->fetchColumn();

    // Procesar la imagen si se subió una
    $image_path = null;
    $banner_path = null;
    $upload_dir = __DIR__ . '/../../uploads/';
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    // Procesar imagen principal
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        // Validar tipo de archivo
        if (!in_array($file_extension, $allowed_extensions)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido para la imagen']);
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
        $new_filename = uniqid('category_') . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        // Mover el archivo
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $image_path = $new_filename;
        } else {
            throw new Exception('Error al subir la imagen');
        }
    }

    // Procesar banner
    if (isset($_FILES['banner_categoria']) && $_FILES['banner_categoria']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['banner_categoria']['name'], PATHINFO_EXTENSION));

        // Validar tipo de archivo
        if (!in_array($file_extension, $allowed_extensions)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido para el banner']);
            exit;
        }

        // Validar tamaño (5MB máximo)
        if ($_FILES['banner_categoria']['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'El banner es demasiado grande']);
            exit;
        }

        // Generar nombre único para el archivo
        $new_filename = uniqid('category_banner_') . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        // Mover el archivo
        if (move_uploaded_file($_FILES['banner_categoria']['tmp_name'], $upload_path)) {
            $banner_path = $new_filename;
        } else {
            throw new Exception('Error al subir el banner');
        }
    }

    // Insertar la categoría
    $stmt = $conn->prepare("
        INSERT INTO menu_categories 
        (restaurant_id, name, description, image, Banner_categoria, sort_order, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)
    ");

    if ($stmt->execute([$restaurant_id, $name, $description, $image_path, $banner_path, $next_order])) {
        $category_id = $conn->lastInsertId();
        
        // Registrar la actividad
        logActivity($restaurant_id, 'category_create', "Categoría creada: $name");
        
        // Enviar headers antes del contenido JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Categoría agregada exitosamente',
            'category_id' => $category_id
        ]);
    } else {
        throw new Exception('Error al crear la categoría');
    }

} catch (Exception $e) {
    error_log("Error al crear categoría: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Si hubo un error y se subieron archivos, eliminarlos
    if (isset($image_path) && file_exists($upload_dir . $image_path)) {
        unlink($upload_dir . $image_path);
    }
    if (isset($banner_path) && file_exists($upload_dir . $banner_path)) {
        unlink($upload_dir . $banner_path);
    }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la categoría: ' . $e->getMessage()
    ]);
}
?> 
