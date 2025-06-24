<?php
require_once '../config/database.php';
require_once '../classes/Database.php';
requireLogin('super_admin');

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$plan_filter = $_GET['plan'] ?? '';
$type_filter = $_GET['type'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    // Construir la consulta base sin parámetros
    $base_query = "FROM restaurants r 
                   LEFT JOIN plans p ON r.current_plan_id = p.id 
                   LEFT JOIN subscriptions s ON r.id = s.restaurant_id AND s.status = 'active'
                   LEFT JOIN restaurants parent ON r.parent_restaurant_id = parent.id";
    $where_parts = [];
    $params = [];

    // Agregar condiciones de búsqueda
    if (!empty($search)) {
        $where_parts[] = "(r.name LIKE ? OR r.email LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if ($status_filter !== '') {
        $where_parts[] = "r.is_active = ?";
        $params[] = (int)$status_filter;
    }

    if ($plan_filter !== '') {
        $where_parts[] = "r.current_plan_id = ?";
        $params[] = (int)$plan_filter;
    }

    // Filtro por tipo de restaurante
    if ($type_filter === 'main') {
        // Solo restaurantes principales (que tienen sucursales)
        $where_parts[] = "(SELECT COUNT(*) FROM restaurants WHERE parent_restaurant_id = r.id AND is_branch = 1) > 0";
    } elseif ($type_filter === 'branch') {
        // Solo sucursales
        $where_parts[] = "r.parent_restaurant_id IS NOT NULL AND r.is_branch = 1";
    } elseif ($type_filter === 'independent') {
        // Solo restaurantes independientes (sin padre ni sucursales)
        $where_parts[] = "r.parent_restaurant_id IS NULL AND (SELECT COUNT(*) FROM restaurants WHERE parent_restaurant_id = r.id AND is_branch = 1) = 0";
    }

    // Construir la cláusula WHERE
    $where_clause = !empty($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

    // Consulta principal con información completa de suscripciones y sucursales
    $query = "SELECT r.*, 
                     p.name as plan_name, 
                     p.base_price as plan_price, 
                     p.max_branches, 
                     p.max_products, 
                     p.max_categories,
                     s.id as subscription_id,
                     s.duration_months,
                     s.price as subscription_price,
                     s.start_date as subscription_start_date,
                     s.end_date as subscription_end_date,
                     s.status as subscription_status,
                     COALESCE(s.status, r.subscription_status) as final_subscription_status,
                     COALESCE(s.start_date, r.created_at) as final_subscription_start_date,
                     COALESCE(s.end_date, r.subscription_ends_at) as final_subscription_end_date,
                     r.trial_ends_at,
                     r.subscription_ends_at,
                     r.created_at,
                     parent.name as parent_restaurant_name,
                     parent.slug as parent_restaurant_slug,
                     COALESCE((SELECT COUNT(*) FROM restaurants WHERE parent_restaurant_id = r.id AND is_branch = 1), 0) as branches_count
              {$base_query} 
              {$where_clause} 
              ORDER BY r.created_at DESC 
              LIMIT ? OFFSET ?";

    // Agregar parámetros de paginación
    $params[] = $per_page;
    $params[] = $offset;

    // Preparar y ejecutar la consulta principal
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Consulta de conteo (remover parámetros de paginación)
    array_pop($params); // Remover OFFSET
    array_pop($params); // Remover LIMIT
    
    $count_query = "SELECT COUNT(*) as total {$base_query} {$where_clause}";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_restaurants = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_restaurants / $per_page);

    // Preparar la respuesta
    $response = [
        'success' => true,
        'restaurants' => $restaurants,
        'total' => $total_restaurants,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'debug' => [
            'search' => $search,
            'status' => $status_filter,
            'plan' => $plan_filter,
            'type' => $type_filter,
            'where_clause' => $where_clause,
            'params' => $params,
            'query' => $query,
            'count_query' => $count_query
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Error en ajax_search_restaurants.php: " . $e->getMessage());
    error_log("Query: " . ($query ?? 'No query'));
    error_log("Count Query: " . ($count_query ?? 'No count query'));
    error_log("Params: " . print_r($params ?? [], true));
    
    echo json_encode([
        'success' => false,
        'error' => 'Error al buscar restaurantes',
        'debug' => [
            'message' => $e->getMessage(),
            'query' => $query ?? null,
            'count_query' => $count_query ?? null,
            'params' => $params ?? []
        ]
    ]);
} 
