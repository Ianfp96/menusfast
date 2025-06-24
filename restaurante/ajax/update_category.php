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
$category_id = intval($_POST['category_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$current_image = $_POST['current_image'] ?? '';
$current_banner = $_POST['current_banner'] ?? '';
$restaurant_id = $_SESSION['restaurant_id'];

// Validar datos requeridos
if (!$category_id || empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre de la categoría es requerido']);
    exit;
}

try {
    // Verificar que la categoría pertenece al restaurante
    $stmt = $conn->prepare("SELECT id, image, Banner_categoria FROM menu_categories WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$category_id, $restaurant_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Categoría no encontrada']);
        exit;
    }

    // Procesar la imagen si se subió una nueva
    $image_path = $current_image;
    $banner_path = $current_banner;
    $upload_dir = __DIR__ . '/../../uploads/';
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    // Procesar imagen principal
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        // Validar tipo de archivo
        if (!in_array($file_extension, $allowed_extensions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido para la imagen']);
            exit;
        }

        // Validar tamaño (5MB máximo)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La imagen es demasiado grande']);
            exit;
        }

        // Generar nombre único para el archivo
        $new_filename = uniqid('category_') . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        // Mover el archivo
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            // Eliminar imagen anterior si existe
            if ($category['image'] && file_exists($upload_dir . $category['image'])) {
                unlink($upload_dir . $category['image']);
            }
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
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido para el banner']);
            exit;
        }

        // Validar tamaño (5MB máximo)
        if ($_FILES['banner_categoria']['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El banner es demasiado grande']);
            exit;
        }

        // Generar nombre único para el archivo
        $new_filename = uniqid('category_banner_') . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        // Mover el archivo
        if (move_uploaded_file($_FILES['banner_categoria']['tmp_name'], $upload_path)) {
            // Eliminar banner anterior si existe
            if ($category['Banner_categoria'] && file_exists($upload_dir . $category['Banner_categoria'])) {
                unlink($upload_dir . $category['Banner_categoria']);
            }
            $banner_path = $new_filename;
        } else {
            throw new Exception('Error al subir el banner');
        }
    }

    // Actualizar la categoría
    $stmt = $conn->prepare("
        UPDATE menu_categories 
        SET name = ?, 
            description = ?, 
            image = ?, 
            Banner_categoria = ?
        WHERE id = ? AND restaurant_id = ?
    ");

    if ($stmt->execute([
        $name, 
        $description, 
        $image_path, 
        $banner_path,
        $category_id,
        $restaurant_id
    ])) {
        // Registrar la actividad
        logActivity($restaurant_id, 'category_update', "Categoría actualizada: $name");
        
        echo json_encode([
            'success' => true,
            'message' => 'Categoría actualizada exitosamente'
        ]);
    } else {
        throw new Exception('Error al actualizar la categoría');
    }

} catch (Exception $e) {
    error_log("Error al actualizar categoría: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Si hubo un error y se subieron archivos, eliminarlos
    if (isset($new_filename) && file_exists($upload_dir . $new_filename)) {
        unlink($upload_dir . $new_filename);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la categoría: ' . $e->getMessage()
    ]);
}

// Asegurarse de que siempre se envíe el header de Content-Type
header('Content-Type: application/json');
?> 
