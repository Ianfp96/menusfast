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

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];

// Validar y sanitizar datos de entrada
$branch_id = (int)($_POST['branch_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if (empty($branch_id) || empty($name) || empty($address)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre y la dirección son campos obligatorios']);
    exit;
}

try {
    // Verificar que la sucursal pertenece al restaurante
    $stmt = $conn->prepare("SELECT id FROM branches WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$branch_id, $restaurant_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada']);
        exit;
    }
    
    // Generar nuevo slug si el nombre cambió
    $stmt = $conn->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([$branch_id]);
    $current_branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $slug = null;
    if ($current_branch['name'] !== $name) {
        $base_slug = createSlug($name);
        $slug = $base_slug;
        $counter = 1;
        
        // Verificar si el nuevo slug ya existe
        while (true) {
            $stmt = $conn->prepare("SELECT id FROM branches WHERE slug = ? AND restaurant_id = ? AND id != ?");
            $stmt->execute([$slug, $restaurant_id, $branch_id]);
            if (!$stmt->fetch()) {
                break;
            }
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }
    }
    
    // Actualizar la sucursal
    $sql = "UPDATE branches SET 
            name = :name,
            address = :address,
            phone = :phone";
    
    $params = [
        ':branch_id' => $branch_id,
        ':name' => $name,
        ':address' => $address,
        ':phone' => $phone
    ];
    
    if ($slug) {
        $sql .= ", slug = :slug";
        $params[':slug'] = $slug;
    }
    
    $sql .= " WHERE id = :branch_id AND restaurant_id = :restaurant_id";
    $params[':restaurant_id'] = $restaurant_id;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("No se pudo actualizar la sucursal");
    }
    
    // Registrar la acción en el log
    error_log("Sucursal actualizada - ID: $branch_id, Restaurante: $restaurant_id");
    
    $_SESSION['message'] = 'Sucursal actualizada exitosamente';
    echo json_encode(['success' => true, 'message' => 'Sucursal actualizada exitosamente']);
    
} catch (Exception $e) {
    error_log("Error al actualizar sucursal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la sucursal']);
} 
