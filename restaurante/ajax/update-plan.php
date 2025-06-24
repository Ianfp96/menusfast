<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar si el usuario está autenticado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido']);
    exit;
}

// Verificar que se proporcionó un plan_id
if (!isset($_POST['plan_id']) || !is_numeric($_POST['plan_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de plan inválido']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];
$new_plan_id = (int)$_POST['plan_id'];

try {
    // Iniciar transacción
    $conn->beginTransaction();

    // Verificar que el plan existe y está activo
    $query = "SELECT * FROM plans WHERE id = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute([$new_plan_id]);
    $new_plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$new_plan) {
        throw new Exception('Plan no encontrado o inactivo');
    }

    // Obtener información del restaurante y su plan actual
    $query = "SELECT r.*, p.price as current_price 
              FROM restaurants r 
              LEFT JOIN plans p ON r.current_plan_id = p.id 
              WHERE r.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$restaurant) {
        throw new Exception('Restaurante no encontrado');
    }

    // Verificar si el restaurante está en período de prueba
    $is_in_trial = $restaurant['subscription_status'] === 'trial';
    $trial_ends_at = $restaurant['trial_ends_at'];

    // Actualizar el plan del restaurante
    $query = "UPDATE restaurants SET 
              current_plan_id = ?,
              subscription_status = CASE 
                  WHEN subscription_status = 'trial' THEN 'trial'
                  ELSE 'active'
              END,
              updated_at = NOW()
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$new_plan_id, $restaurant_id]);

    // Registrar el cambio de plan
    $query = "INSERT INTO plan_changes (restaurant_id, old_plan_id, new_plan_id, change_type, created_at) 
              VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        $restaurant_id,
        $restaurant['current_plan_id'],
        $new_plan_id,
        $is_in_trial ? 'trial_upgrade' : 'plan_change'
    ]);

    // Confirmar transacción
    $conn->commit();

    // Enviar respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Plan actualizado exitosamente',
        'is_trial' => $is_in_trial,
        'trial_ends_at' => $trial_ends_at
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollBack();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al actualizar el plan: ' . $e->getMessage()
    ]);
}
?> 
