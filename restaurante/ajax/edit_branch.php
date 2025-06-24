<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];

try {
    // Verificar que NO sea una sucursal (solo restaurantes principales pueden gestionar sucursales)
    $stmt = $conn->prepare("SELECT is_branch FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant_check = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($restaurant_check && $restaurant_check['is_branch'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Las sucursales no pueden gestionar otras sucursales. Solo el restaurante principal puede gestionar sucursales.']);
        exit;
    }

    // Verificar que el restaurante tiene plan Premium o Premium Pro
    $stmt = $conn->prepare("SELECT current_plan_id FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$restaurant || ($restaurant['current_plan_id'] != 3 && $restaurant['current_plan_id'] != 4)) {
        echo json_encode(['success' => false, 'message' => 'Esta funcionalidad solo está disponible para los planes Premium y Premium Pro']);
        exit;
    }

    // Obtener datos del formulario
    $branch_id = $_POST['branch_id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validaciones
    if (empty($branch_id) || empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios deben estar completos']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'El formato del email no es válido']);
        exit;
    }

    // Verificar que la sucursal pertenece al restaurante
    $stmt = $conn->prepare("SELECT * FROM restaurants WHERE id = ? AND parent_restaurant_id = ? AND is_branch = 1");
    $stmt->execute([$branch_id, $restaurant_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada o no autorizada']);
        exit;
    }

    // Verificar si el email ya existe (excluyendo la sucursal actual)
    $stmt = $conn->prepare("SELECT id FROM restaurants WHERE email = ? AND id != ?");
    $stmt->execute([$email, $branch_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El email ya está registrado por otro restaurante']);
        exit;
    }

    // Generar slug único si el nombre cambió
    $slug = $branch['slug'];
    if ($name !== $branch['name']) {
        $base_slug = createSlug($name);
        $slug = $base_slug;
        $counter = 1;
        
        while (true) {
            $stmt = $conn->prepare("SELECT id FROM restaurants WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $branch_id]);
            if (!$stmt->fetch()) {
                break;
            }
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }
    }

    // Preparar datos para actualización
    $updateData = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'description' => $description,
        'slug' => $slug,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Si se proporcionó una nueva contraseña, actualizarla
    if (!empty($password)) {
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            exit;
        }
        $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    // Construir query de actualización
    $fields = [];
    $values = [];
    foreach ($updateData as $field => $value) {
        $fields[] = "$field = ?";
        $values[] = $value;
    }
    $values[] = $branch_id;

    $sql = "UPDATE restaurants SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute($values)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Sucursal actualizada correctamente',
            'branch' => [
                'id' => $branch_id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'description' => $description,
                'slug' => $slug
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la sucursal']);
    }

} catch (PDOException $e) {
    error_log("Error en edit_branch.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
} catch (Exception $e) {
    error_log("Error en edit_branch.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error inesperado']);
}
?> 
