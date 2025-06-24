<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
require_once '../../classes/Restaurant.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];

try {
    // Verificar que se proporcione el ID de la sucursal
    if (!isset($_POST['branch_id']) || empty($_POST['branch_id'])) {
        throw new Exception('ID de sucursal no proporcionado');
    }

    $branch_id = intval($_POST['branch_id']);

    // Verificar que el restaurante principal existe y tiene permisos
    $stmt = $conn->prepare("
        SELECT r.*, p.max_branches 
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        WHERE r.id = ? AND r.is_branch = 0
    ");
    $stmt->execute([$restaurant_id]);
    $parent_restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$parent_restaurant) {
        throw new Exception('Restaurante principal no encontrado');
    }

    // Verificar que la sucursal existe y pertenece al restaurante principal
    $stmt = $conn->prepare("
        SELECT * FROM restaurants 
        WHERE id = ? AND parent_restaurant_id = ? AND is_branch = 1
    ");
    $stmt->execute([$branch_id, $restaurant_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        throw new Exception('Sucursal no encontrada o no tienes permisos para eliminarla');
    }

    // Iniciar transacción
    $conn->beginTransaction();

    try {
        // Eliminar productos de la sucursal
        $stmt = $conn->prepare("DELETE FROM products WHERE restaurant_id = ?");
        $stmt->execute([$branch_id]);

        // Eliminar categorías de la sucursal
        $stmt = $conn->prepare("DELETE FROM menu_categories WHERE restaurant_id = ?");
        $stmt->execute([$branch_id]);

        // Eliminar pedidos de la sucursal
        $stmt = $conn->prepare("DELETE FROM orders WHERE restaurant_id = ?");
        $stmt->execute([$branch_id]);

        // Eliminar logs de actividad de la sucursal
        $stmt = $conn->prepare("DELETE FROM activity_logs WHERE restaurant_id = ?");
        $stmt->execute([$branch_id]);

        // Eliminar vistas de página de la sucursal
        $stmt = $conn->prepare("DELETE FROM page_views WHERE restaurant_id = ?");
        $stmt->execute([$branch_id]);

        // Eliminar vistas de productos de la sucursal
        $stmt = $conn->prepare("DELETE FROM product_views WHERE restaurant_id = ?");
        $stmt->execute([$branch_id]);

        // Eliminar códigos QR de la sucursal
        $stmt = $conn->prepare("DELETE FROM qr_codes WHERE restaurant_id = ?");
        $stmt->execute([$branch_id]);

        // Eliminar la sucursal
        $stmt = $conn->prepare("DELETE FROM restaurants WHERE id = ?");
        $stmt->execute([$branch_id]);

        // Confirmar transacción
        $conn->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Sucursal "' . htmlspecialchars($branch['name']) . '" eliminada exitosamente'
        ]);

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error al eliminar sucursal: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error al eliminar la sucursal: ' . $e->getMessage()
    ]);
}
?> 
