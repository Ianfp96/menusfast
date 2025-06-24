<?php
require_once __DIR__ . '/../../config/config.php';
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

// Validar y sanitizar datos de entrada
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$description = trim($_POST['description'] ?? '');

// Validaciones básicas
if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Nombre, email y contraseña son obligatorios']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email no válido']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

try {
    // Verificar que NO sea una sucursal (solo restaurantes principales pueden crear sucursales)
    $stmt = $conn->prepare("SELECT is_branch FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant_check = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($restaurant_check && $restaurant_check['is_branch'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Las sucursales no pueden crear otras sucursales. Solo el restaurante principal puede gestionar sucursales.']);
        exit;
    }

    // Verificar que el restaurante tiene plan Premium o Premium Pro
    $stmt = $conn->prepare("SELECT r.current_plan_id, p.max_branches FROM restaurants r LEFT JOIN plans p ON r.current_plan_id = p.id WHERE r.id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$restaurant || ($restaurant['current_plan_id'] != 3 && $restaurant['current_plan_id'] != 4)) {
        echo json_encode(['success' => false, 'message' => 'Esta funcionalidad solo está disponible para los planes Premium y Premium Pro']);
        exit;
    }

    // Obtener el límite de sucursales desde la base de datos
    $max_branches = $restaurant['max_branches'];
    
    // Verificar el límite de sucursales
    $stmt = $conn->prepare("
        SELECT COUNT(*) as current_branches 
        FROM restaurants 
        WHERE parent_restaurant_id = ? AND is_branch = 1
    ");
    $stmt->execute([$restaurant_id]);
    $branch_count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($branch_count['current_branches'] >= $max_branches) {
        // Obtener el nombre del plan
        $stmt = $conn->prepare("SELECT name FROM plans WHERE id = ?");
        $stmt->execute([$restaurant['current_plan_id']]);
        $plan_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $plan_name = $plan_info['name'] ?? 'Premium';
        
        echo json_encode(['success' => false, 'message' => "Has alcanzado el límite de sucursales para tu plan {$plan_name} ({$max_branches} sucursal" . ($max_branches > 1 ? 'es' : '') . ")"]);
        exit;
    }
    
    // Verificar que el email no esté en uso
    $stmt = $conn->prepare("SELECT id FROM restaurants WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este email ya está en uso']);
        exit;
    }
    
    // Generar slug único
    $base_slug = generateSlug($name);
    $slug = $base_slug;
    $counter = 1;
    
    while (true) {
        $stmt = $conn->prepare("SELECT id FROM restaurants WHERE slug = ?");
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) {
            break;
        }
        $slug = $base_slug . '-' . $counter;
        $counter++;
    }
    
    // Obtener el siguiente número de sucursal
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(branch_number), 0) + 1 as next_branch_number 
        FROM restaurants 
        WHERE parent_restaurant_id = ? AND is_branch = 1
    ");
    $stmt->execute([$restaurant_id]);
    $branch_number = $stmt->fetch(PDO::FETCH_ASSOC)['next_branch_number'];
    
    // Encriptar contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Obtener información de suscripción del restaurante padre
    $stmt = $conn->prepare("
        SELECT subscription_status, trial_ends_at, subscription_ends_at 
        FROM restaurants 
        WHERE id = ?
    ");
    $stmt->execute([$restaurant_id]);
    $parent_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcular fechas de suscripción para la sucursal
    $now = new DateTime();
    $trial_ends_at = null;
    $subscription_ends_at = null;
    $subscription_status = 'active';
    
    if ($parent_subscription['subscription_status'] === 'trial' && $parent_subscription['trial_ends_at']) {
        $trial_ends_at = $parent_subscription['trial_ends_at'];
        $subscription_status = 'trial';
    } elseif ($parent_subscription['subscription_status'] === 'active' && $parent_subscription['subscription_ends_at']) {
        $subscription_ends_at = $parent_subscription['subscription_ends_at'];
        $subscription_status = 'active';
    } else {
        // Si no hay suscripción válida del padre, dar 30 días de prueba
        $trial_ends_at = $now->modify('+30 days')->format('Y-m-d H:i:s');
        $subscription_status = 'trial';
    }
    
    // Insertar la nueva sucursal con el mismo plan que el restaurante padre
    $stmt = $conn->prepare("
        INSERT INTO restaurants (
            parent_restaurant_id, is_branch, branch_number, name, slug, email, password,
            phone, address, description, current_plan_id, subscription_status, 
            trial_ends_at, subscription_ends_at, is_active, created_at
        ) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ");
    
    $stmt->execute([
        $restaurant_id,
        $branch_number,
        $name,
        $slug,
        $email,
        $hashed_password,
        $phone,
        $address,
        $description,
        $restaurant['current_plan_id'], // Heredar el plan del restaurante padre
        $subscription_status,
        $trial_ends_at,
        $subscription_ends_at
    ]);
    
    $branch_id = $conn->lastInsertId();
    
    // Registrar la acción
    error_log("Sucursal creada - ID: $branch_id, Restaurante padre: $restaurant_id, Nombre: $name");
    
    echo json_encode([
        'success' => true,
        'message' => 'Sucursal creada exitosamente. URL: ' . BASE_URL . '/' . $slug,
        'branch_id' => $branch_id,
        'slug' => $slug
    ]);
    
} catch (PDOException $e) {
    error_log("Error al crear sucursal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al crear la sucursal: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error inesperado al crear sucursal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error inesperado al crear la sucursal']);
}
?> 
