<?php
// Configurar cabeceras para UTF-8
header('Content-Type: text/html; charset=UTF-8');
header('Content-Encoding: UTF-8');

// Configurar PHP para UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('POST');
mb_http_input('GET');
mb_language('uni');
mb_regex_encoding('UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/currency_converter.php';

// Verificar que la clase esté disponible
if (!class_exists('CurrencyConverter')) {
    error_log('Error: CurrencyConverter class not found');
}

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    redirect(BASE_URL . '/restaurante/login.php');
}

$restaurant_id = $_SESSION['restaurant_id'];
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';

// Limpiar mensajes de sesión después de usarlos
unset($_SESSION['message'], $_SESSION['error']);

try {
    // Verificar la conexión a la base de datos
    if (!$conn) {
        throw new PDOException("No hay conexión a la base de datos");
    }

    // Configurar la conexión para UTF-8
    $conn->exec("SET NAMES utf8mb4");
    $conn->exec("SET CHARACTER SET utf8mb4");
    $conn->exec("SET character_set_connection=utf8mb4");

    // Verificar que el restaurant_id existe
    if (!$restaurant_id) {
        throw new PDOException("ID de restaurante no válido");
    }

    error_log("Intentando obtener información del restaurante ID: " . $restaurant_id);

    // Obtener información del restaurante y su plan actual
    $stmt = $conn->prepare("
        SELECT r.*, 
               p.name as plan_name,
               p.base_price,
               p.max_categories,
               p.max_products,
               p.max_branches,
               p.features,
               p.is_active as plan_is_active,
               r.trial_ends_at,
               r.subscription_ends_at,
               r.created_at as plan_started_at,
               s.id as subscription_id,
               s.end_date as subscription_end_date,
               s.start_date as subscription_start_date,
               s.status as subscription_status,
               s.duration_months,
               s.price as subscription_price,
               COALESCE(s.end_date, r.subscription_ends_at) as final_end_date,
               COALESCE(s.status, r.subscription_status) as final_subscription_status,
               (SELECT COUNT(*) FROM menu_categories WHERE restaurant_id = r.id) as current_categories,
               (SELECT COUNT(*) FROM products WHERE restaurant_id = r.id) as current_products,
               (SELECT COUNT(*) FROM restaurants WHERE parent_restaurant_id = r.id AND is_branch = 1) as current_branches
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        LEFT JOIN subscriptions s ON r.id = s.restaurant_id AND s.status = 'active'
        WHERE r.id = ?
    ");
    
    if (!$stmt) {
        throw new PDOException("Error al preparar la consulta del restaurante: " . implode(" ", $conn->errorInfo()));
    }

    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("Resultado de la consulta del restaurante: " . print_r($restaurant, true));
    error_log("Información de suscripción - ID: " . ($restaurant['subscription_id'] ?? 'null') . ", End Date: " . ($restaurant['final_end_date'] ?? 'null') . ", Status: " . ($restaurant['final_subscription_status'] ?? 'null'));

    // Si no se encuentra el restaurante, redirigir al logout
    if (!$restaurant) {
        error_log("No se encontró el restaurante con ID: " . $restaurant_id);
        redirect(BASE_URL . '/restaurante/logout.php');
    }

    // Obtener todos los planes activos
    $stmt = $conn->prepare("
        SELECT * FROM plans 
        WHERE is_active = 1 
        ORDER BY base_price ASC
    ");

    if (!$stmt) {
        throw new PDOException("Error al preparar la consulta de planes: " . implode(" ", $conn->errorInfo()));
    }

    $stmt->execute();
    $available_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Planes disponibles encontrados: " . count($available_plans));

    // Obtener las sucursales existentes si el restaurante tiene plan Premium o Premium Pro
    $branches = [];
    if ($restaurant['current_plan_id'] == 3 || $restaurant['current_plan_id'] == 4) {
        $stmt = $conn->prepare("
            SELECT * FROM restaurants 
            WHERE parent_restaurant_id = ? AND is_branch = 1 
            ORDER BY branch_number ASC
        ");
        $stmt->execute([$restaurant_id]);
        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Error detallado en planes.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $error = "Error al cargar la información de planes: " . $e->getMessage();
    $restaurant = [
        'name' => 'Restaurante',
        'current_plan_id' => null,
        'plan_name' => 'No disponible',
        'base_price' => 0,
        'max_categories' => 0,
        'max_products' => 0,
        'max_branches' => 0,
        'current_categories' => 0,
        'current_products' => 0,
        'current_branches' => 0
    ];
    $available_plans = [];
}

// Función para verificar si una característica está incluida en el plan
function hasFeature($features, $feature) {
    $features_array = json_decode($features ?? '[]', true) ?? [];
    return in_array($feature, $features_array);
}

// Función para obtener el color del plan
function getPlanColor($planId) {
    $colors = [
        1 => ['primary' => '#6c757d', 'gradient' => 'linear-gradient(135deg, #6c757d 0%, #495057 100%)'],
        2 => ['primary' => '#17a2b8', 'gradient' => 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)'],
        3 => ['primary' => '#28a745', 'gradient' => 'linear-gradient(135deg, #28a745 0%, #1e7e34 100%)'],
        4 => ['primary' => '#dc3545', 'gradient' => 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)']
    ];
    return $colors[$planId] ?? ['primary' => '#007bff', 'gradient' => 'linear-gradient(135deg, #007bff 0%, #0056b3 100%)'];
}

// Función para calcular el tiempo restante del plan
function getPlanTimeRemaining($restaurant) {
    // Si no hay restaurante, retornar sin plan
    if (!$restaurant) {
        return ['status' => 'no_plan', 'message' => 'Sin plan activo'];
    }
    
    $subscription_status = $restaurant['final_subscription_status'] ?? $restaurant['subscription_status'] ?? 'trial';
    $trial_ends_at = $restaurant['trial_ends_at'] ?? null;
    $subscription_ends_at = $restaurant['final_end_date'] ?? $restaurant['subscription_ends_at'] ?? null;
    
    // Determinar qué fecha usar según el estado de suscripción
    $expires_at = null;
    
    switch ($subscription_status) {
        case 'trial':
            $expires_at = $trial_ends_at;
            break;
        case 'active':
            $expires_at = $subscription_ends_at;
            break;
        case 'expired':
            return ['status' => 'expired', 'message' => 'Plan expirado'];
        case 'cancelled':
            return ['status' => 'expired', 'message' => 'Plan cancelado'];
        default:
            return ['status' => 'no_plan', 'message' => 'Sin plan activo'];
    }
    
    // Si no hay fecha de expiración
    if (!$expires_at) {
        if ($subscription_status === 'trial') {
            return ['status' => 'info', 'message' => 'Trial activo (sin fecha de expiración)'];
        } elseif ($subscription_status === 'active') {
            return ['status' => 'success', 'message' => 'Plan activo (sin fecha de expiración)'];
        } else {
            return ['status' => 'no_plan', 'message' => 'Sin fecha de expiración'];
        }
    }
    
    $now = new DateTime();
    $expires = new DateTime($expires_at);
    $diff = $now->diff($expires);
    
    if ($now > $expires) {
        return ['status' => 'expired', 'message' => 'Plan expirado'];
    }
    
    if ($diff->days == 0) {
        if ($diff->h == 0) {
            return ['status' => 'urgent', 'message' => 'Expira en ' . $diff->i . ' minutos'];
        }
        return ['status' => 'urgent', 'message' => 'Expira en ' . $diff->h . ' horas'];
    }
    
    if ($diff->days == 1) {
        return ['status' => 'warning', 'message' => 'Expira mañana'];
    }
    
    if ($diff->days < 7) {
        return ['status' => 'warning', 'message' => 'Expira en ' . $diff->days . ' días'];
    }
    
    if ($diff->days < 30) {
        return ['status' => 'info', 'message' => 'Expira en ' . $diff->days . ' días'];
    }
    
    $months = floor($diff->days / 30);
    return ['status' => 'success', 'message' => 'Expira en ' . $months . ' meses'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planes y Suscripciones - <?= htmlspecialchars($restaurant['name'] ?? 'Restaurante') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <link href="/restaurante/assets/css/admin.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
            --card-shadow-hover: 0 20px 40px rgba(0,0,0,0.15);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .page-header {
            background: var(--primary-gradient);
            color: white;
            padding: 3rem 0;
            margin-bottom: 3rem;
            border-radius: 0 0 2rem 2rem;
            box-shadow: var(--card-shadow);
        }

        .plan-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            position: relative;
            background: white;
        }

        .plan-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--card-shadow-hover);
        }

        .plan-card.featured {
            transform: scale(1.05);
            z-index: 10;
        }

        .plan-card.featured:hover {
            transform: translateY(-10px) scale(1.07);
        }

        .plan-header {
            padding: 2.5rem 2rem;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .plan-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            z-index: 1;
        }

        .plan-header > * {
            position: relative;
            z-index: 2;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .plan-price {
            font-size: 3.5rem;
            font-weight: 900;
            margin: 1rem 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .plan-period {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .plan-features {
            padding: 2.5rem 2rem;
        }

        .feature-item {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            background: rgba(0,0,0,0.05);
            transform: translateX(5px);
        }

        .feature-item i {
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 1rem;
            font-size: 0.9rem;
            color: white;
        }

        .feature-item.limit i {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }

        .feature-item.basic i {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        .feature-item.premium i {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        }

        .feature-item.enterprise i {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .feature-text {
            flex: 1;
            font-weight: 500;
        }

        .feature-value {
            font-weight: 700;
            color: #495057;
        }

        .current-plan-badge {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 10;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .featured-badge {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 10;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .plan-button {
            width: 100%;
            padding: 1.25rem;
            border: none;
            border-radius: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .plan-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .plan-button:hover::before {
            left: 100%;
        }

        .plan-button.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .plan-button.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
        }

        .plan-button.secondary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #495057;
            border: 2px solid #dee2e6;
        }

        .plan-button.secondary:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        }

        .current-plan-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }

        .usage-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .usage-item {
            margin-bottom: 1.5rem;
        }

        .usage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .usage-title {
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }

        .usage-title i {
            margin-right: 0.5rem;
            color: #667eea;
            font-size: 0.85rem;
        }

        .usage-count {
            font-weight: 700;
            color: #667eea;
            font-size: 0.9rem;
        }

        .usage-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }

        .usage-bar-fill {
            height: 100%;
            border-radius: 8px;
            transition: width 1s ease-in-out;
            position: relative;
        }

        .usage-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }

        .current-plan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .current-plan-info h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #495057;
        }

        .current-plan-info h3 {
            font-size: 1.1rem;
            color: #667eea;
            margin-bottom: 0;
        }

        .current-plan-price {
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            min-width: 120px;
        }

        .current-plan-price h2 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
            color: white;
        }

        .current-plan-price p {
            font-size: 0.8rem;
            margin-bottom: 0;
            opacity: 0.9;
        }

        .current-plan-badge-small {
            display: inline-block;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .alert {
            border: none;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 5px solid #17a2b8;
        }

        @media (max-width: 768px) {
            .plan-card.featured {
                transform: none;
            }
            
            .plan-card.featured:hover {
                transform: translateY(-5px);
            }
            
            .plan-price {
                font-size: 2.5rem;
            }
            
            .current-plan-section {
                padding: 2rem 1rem;
            }
        }

        .fade-in {
            animation: fadeIn 0.8s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-in {
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(-50px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .restaurant-info-card {
            background-color: rgba(255, 255, 255, 0.2) !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Estilos para la tabla de sucursales */
        .table {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: white;
        }

        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .table tbody td {
            border-color: #e9ecef;
            vertical-align: middle;
        }

        .btn-group .btn {
            margin: 0 2px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-group .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
        }

        /* Estilos para el carrusel de planes en móviles */
        .plans-carousel {
            display: none;
        }

        .plans-grid {
            display: block;
        }

        @media (max-width: 768px) {
            .plans-carousel {
                display: block;
            }
            
            .plans-grid {
                display: none;
            }

            .swiper-slide {
                height: auto;
                display: flex;
                align-items: stretch;
            }

            .plan-card {
                width: 100%;
                margin: 0;
                transform: none;
            }

            .plan-card:hover {
                transform: none;
            }

            .plan-card.featured {
                transform: none;
            }

            .plan-card.featured:hover {
                transform: none;
            }

            .swiper-pagination {
                position: relative;
                margin-top: 2rem;
            }

            .swiper-pagination-bullet {
                background: #007bff;
                opacity: 0.5;
            }

            .swiper-pagination-bullet-active {
                opacity: 1;
                background: #007bff;
            }

            .swiper-button-next,
            .swiper-button-prev {
                color: #007bff;
                background: rgba(255, 255, 255, 0.8);
                border-radius: 50%;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .swiper-button-next:after,
            .swiper-button-prev:after {
                font-size: 18px;
            }
        }

        /* Estilos para la sección de plan actual y soporte */
        .current-plan-support-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 3rem;
            margin: 3rem 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }

        .plan-status-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .plan-status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .plan-status-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .plan-status-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            font-size: 1.5rem;
            color: white;
        }

        .plan-status-icon.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .plan-status-icon.warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
        }

        .plan-status-icon.danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .plan-status-icon.info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }

        .plan-status-icon.secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }

        .plan-status-info h3 {
            margin-bottom: 0.5rem;
            color: #495057;
            font-weight: 700;
        }

        .plan-status-info p {
            margin-bottom: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .plan-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .plan-detail-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }

        .plan-detail-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .plan-detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .support-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .support-button {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            border: none;
            border-radius: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .support-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .support-button:hover::before {
            left: 100%;
        }

        .support-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .support-button.contact {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .support-button.support {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }

        .support-button.feedback {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .support-button i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .time-remaining-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .time-remaining-badge.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #28a745;
        }

        .time-remaining-badge.warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 1px solid #ffc107;
        }

        .time-remaining-badge.danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #dc3545;
        }

        .time-remaining-badge.info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border: 1px solid #17a2b8;
        }

        .time-remaining-badge.secondary {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            color: #495057;
            border: 1px solid #6c757d;
        }

        @media (max-width: 768px) {
            .current-plan-support-section {
                padding: 2rem 1rem;
            }
            
            .plan-status-header {
                flex-direction: column;
                text-align: center;
            }
            
            .plan-status-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            .support-buttons {
                grid-template-columns: 1fr;
            }
        }

        /* Estilos para el selector de moneda */
        .currency-selector {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1rem 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .currency-selector:hover {
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .currency-selector .form-select {
            border: none;
            background: transparent;
            font-weight: 600;
            color: #495057;
            cursor: pointer;
        }

        .currency-selector .form-select:focus {
            box-shadow: none;
            border-color: transparent;
        }

        .currency-selector .form-select option {
            background: white;
            color: #495057;
        }

        .currency-icon {
            color: #667eea;
            font-size: 1.1rem;
        }

        /* Estilos para los controles de precios */
        .pricing-controls {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            margin-bottom: 3rem;
        }

        .pricing-controls .row {
            align-items: center;
        }

        .pricing-toggle-container {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1rem 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .pricing-toggle-container:hover {
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        /* Animación para el cambio de precios */
        .plan-price {
            transition: all 0.3s ease;
        }

        .plan-price.updating {
            opacity: 0.7;
            transform: scale(0.95);
        }

        /* Responsive para los controles de precios */
        @media (max-width: 768px) {
            .pricing-controls {
                padding: 1.5rem 1rem;
            }
            
            .pricing-controls .row {
                flex-direction: column;
                gap: 1rem;
            }
            
            .currency-selector,
            .pricing-toggle-container {
                padding: 0.75rem 1rem;
                width: 100%;
                justify-content: center;
            }
            
            .currency-selector .form-select {
                font-size: 0.9rem;
            }
        }

        /* Estilos para el modal de feedback mejorado */
        .feedback-modal .modal-content {
            border: none;
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }

        .feedback-modal .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .feedback-modal .modal-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .feedback-modal .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            z-index: 2;
        }

        .feedback-modal .btn-close {
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            padding: 0.5rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .feedback-modal .btn-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }

        .feedback-modal .modal-body {
            padding: 2.5rem;
        }

        .feedback-form-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .feedback-form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .feedback-form-group label i {
            margin-right: 0.5rem;
            color: #667eea;
            font-size: 1rem;
        }

        .feedback-form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .feedback-form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            transform: translateY(-2px);
        }

        .feedback-form-control:focus + .form-focus-indicator {
            opacity: 1;
            transform: scale(1);
        }

        .form-focus-indicator {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 2px solid #667eea;
            border-radius: 12px;
            opacity: 0;
            transform: scale(0.95);
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .feedback-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .feedback-textarea:focus {
            min-height: 140px;
        }

        .character-counter {
            position: absolute;
            bottom: -1.5rem;
            right: 0;
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
        }

        .feedback-modal .modal-footer {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            padding: 2rem;
            border-radius: 0 0 25px 25px;
        }

        .feedback-btn {
            padding: 1rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: none;
        }

        .feedback-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .feedback-btn:hover::before {
            left: 100%;
        }

        .feedback-btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }

        .feedback-btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
        }

        .feedback-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .feedback-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .feedback-btn-primary:disabled {
            opacity: 0.7;
            transform: none;
            box-shadow: none;
        }

        .feedback-btn-primary:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        .feedback-success-animation {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: successPopup 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .feedback-success-animation .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
            animation: successBounce 0.8s ease-in-out;
        }

        .feedback-success-animation h3 {
            color: #28a745;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .feedback-success-animation p {
            color: #6c757d;
            margin-bottom: 0;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(180deg); }
        }

        @keyframes successPopup {
            0% { 
                opacity: 0; 
                transform: translate(-50%, -50%) scale(0.5) rotate(-10deg); 
            }
            100% { 
                opacity: 1; 
                transform: translate(-50%, -50%) scale(1) rotate(0deg); 
            }
        }

        @keyframes successBounce {
            0%, 20%, 53%, 80%, 100% { transform: translateY(0); }
            40%, 43% { transform: translateY(-20px); }
            70% { transform: translateY(-10px); }
            90% { transform: translateY(-5px); }
        }

        .feedback-loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.5rem;
        }

        .feedback-emoji {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }

        /* Estilos adicionales para animaciones del feedback */
        .feedback-type-option .type-card.selected {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-color: #28a745;
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(40, 167, 69, 0.3);
        }

        .feedback-type-option .type-card.hover-effect {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        /* Estilos adicionales para animaciones del feedback */
        .feedback-form-group.focused .feedback-form-control {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .emoji-btn {
            transition: all 0.3s ease;
            border-radius: 20px;
            font-weight: 500;
        }

        .emoji-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .emoji-btn.btn-primary {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .character-counter.text-warning {
            color: #ffc107 !important;
            font-weight: 600;
        }

        .character-counter.text-danger {
            color: #dc3545 !important;
            font-weight: 700;
        }

        /* Animación de confeti para el éxito */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #667eea;
            animation: confetti-fall 3s linear infinite;
            z-index: 10000;
        }

        @keyframes confetti-fall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }

        /* Efecto de partículas para el modal */
        .modal-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255,255,255,0.6);
            border-radius: 50%;
            animation: particle-float 4s ease-in-out infinite;
        }

        @keyframes particle-float {
            0%, 100% {
                transform: translateY(0px) translateX(0px);
                opacity: 0;
            }
            50% {
                transform: translateY(-20px) translateX(10px);
                opacity: 1;
            }
        }

        /* Mejoras en la responsividad del modal */
        @media (max-width: 576px) {
            .feedback-modal .modal-dialog {
                margin: 0.5rem;
            }
            
            .feedback-modal .modal-content {
                border-radius: 15px;
            }
            
            .feedback-modal .modal-header {
                padding: 1.5rem;
            }
            
            .feedback-modal .modal-body {
                padding: 1.5rem;
            }
            
            .feedback-modal .modal-footer {
                padding: 1.5rem;
            }
            
            .feedback-type-selector {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .feedback-type-option .type-card {
                padding: 1rem 0.75rem;
            }
            
            .feedback-type-option .type-icon {
                font-size: 1.5rem;
            }
            
            .emoji-btn {
                font-size: 0.8rem;
                padding: 0.5rem 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-4 fw-bold mb-3">
                            <i class="fas fa-crown me-3"></i>
                            Planes y Suscripciones
                        </h1>
                        <p class="lead mb-0">
                            Elige el plan perfecto para hacer crecer tu restaurante
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-inline-block restaurant-info-card p-3 rounded-3">
                            <h5 class="mb-1"><?= htmlspecialchars($restaurant['name']) ?></h5>
                            <small>Plan actual: <?= htmlspecialchars($restaurant['plan_name']) ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i> 
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show fade-in">
                    <i class="fas fa-check-circle me-2"></i> 
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Planes Disponibles -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="text-center mb-5">
                        <i class="fas fa-rocket me-3"></i>
                        Planes Disponibles
                    </h2>
                    
                    <!-- Pricing Controls -->
                    <div class="pricing-controls">
                        <div class="row justify-content-center align-items-center">
                            <div class="col-md-4">
                                <!-- Currency Selector -->
                                <div class="d-inline-flex align-items-center currency-selector">
                                    <i class="fas fa-globe currency-icon me-2"></i>
                                    <label for="currencySelect" class="form-label me-2 mb-0 fw-bold">Moneda:</label>
                                    <select class="form-select form-select-sm" id="currencySelect" style="width: auto; min-width: 120px;">
                                        <option value="CLP" selected>CLP - Peso Chileno</option>
                                        <option value="USD">USD - Dólar</option>
                                        <option value="EUR">EUR - Euro</option>
                                        <option value="GBP">GBP - Libra</option>
                                        <option value="ARS">ARS - Peso Argentino</option>
                                        <option value="PEN">PEN - Sol Peruano</option>
                                        <option value="COP">COP - Peso Colombiano</option>
                                        <option value="MXN">MXN - Peso Mexicano</option>
                                        <option value="BRL">BRL - Real Brasileño</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Pricing Toggle -->
                                <div class="d-inline-flex align-items-center pricing-toggle-container">
                                    <span class="form-check-label me-3 fw-bold">Mensual</span>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="pricingToggle" style="width: 3rem; height: 1.5rem;">
                                    </div>
                                    <span class="form-check-label ms-3 fw-bold">Anual</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <span class="badge bg-success" id="annualSavings" style="display: none;">
                                <i class="fas fa-gift me-1"></i> ¡Ahorra 40% con plan anual!
                            </span>
                        </div>
                        
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Los precios se muestran en la moneda seleccionada. Las tasas de cambio son aproximadas.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Carrusel para móviles -->
            <div class="plans-carousel">
                <div class="swiper plans-swiper">
                    <div class="swiper-wrapper">
                        <?php 
                        $planCount = 0;
                        foreach ($available_plans as $plan): 
                            if ($plan['id'] == 1) continue; // Saltar el plan gratuito en la lista de planes disponibles
                            $is_current_plan = $restaurant['current_plan_id'] == $plan['id'];
                            $features = json_decode($plan['features'] ?? '[]', true) ?? [];
                            $planColors = getPlanColor($plan['id']);
                            $planCount++;
                            $is_featured = $plan['id'] == 3; // Marcar el plan Premium como destacado
                        ?>
                        <div class="swiper-slide">
                            <div class="card plan-card h-100 <?= $is_featured ? 'featured' : '' ?>">
                                <?php if ($is_current_plan): ?>
                                <div class="current-plan-badge">
                                    <i class="fas fa-check-circle me-1"></i> Plan Actual
                                </div>
                                <?php endif; ?>

                                <?php if ($is_featured): ?>
                                <div class="featured-badge">
                                    <i class="fas fa-star me-1"></i> Más Popular
                                </div>
                                <?php endif; ?>
                                
                                <div class="plan-header" style="background: <?= $planColors['gradient'] ?>">
                                    <h3 class="plan-name"><?= htmlspecialchars($plan['name']) ?></h3>
                                    <div class="plan-price monthly-price" data-plan-id="<?= $plan['id'] ?>" data-base-price="<?= $plan['base_price'] ?>">
                                        <?php 
                                        $price = $plan['base_price'];
                                        // Convertir a CLP por defecto
                                        $formatted_price = CurrencyConverter::formatPriceInCurrency($price, 'CLP', $conn);
                                        echo $formatted_price;
                                        ?>
                                    </div>
                                    <div class="plan-price annual-price" style="display: none;" data-plan-id="<?= $plan['id'] ?>" data-base-price="<?= $plan['base_price'] ?>">
                                        <?php 
                                        $annual_price = $price * 0.6; // 40% descuento
                                        $total_annual_price = $annual_price * 12; // Precio total anual
                                        $formatted_total_annual_price = CurrencyConverter::formatPriceInCurrency($total_annual_price, 'CLP', $conn);
                                        echo $formatted_total_annual_price;
                                        ?>
                                    </div>
                                    <p class="plan-period monthly-period">por mes</p>
                                    <p class="plan-period annual-period" style="display: none;">pago único anual</p>
                                    <div class="annual-total" style="display: none;">
                                        <small class="opacity-75">¡Ahorra 40% con plan anual!</small>
                                    </div>
                                </div>
                                
                                <div class="plan-features">
                                    <div class="mb-4">
                                        <div class="feature-item limit">
                                            <i class="fas fa-tags"></i>
                                            <div class="feature-text">
                                                <span class="feature-value"><?= $plan['max_categories'] ?></span> Categorías
                                            </div>
                                        </div>
                                        <div class="feature-item limit">
                                            <i class="fas fa-utensils"></i>
                                            <div class="feature-text">
                                                <span class="feature-value"><?= $plan['max_products'] ?></span> Productos
                                            </div>
                                        </div>
                                        <div class="feature-item limit">
                                            <i class="fas fa-store"></i>
                                            <div class="feature-text">
                                                <span class="feature-value"><?= $plan['max_branches'] ?></span> Sucursales
                                            </div>
                                        </div>
                                        <?php foreach ($features as $index => $feature): ?>
                                        <div class="feature-item <?= $plan['id'] == 2 ? 'basic' : ($plan['id'] == 3 ? 'premium' : 'enterprise') ?>">
                                            <i class="fas fa-check"></i>
                                            <div class="feature-text"><?= htmlspecialchars($feature) ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if (!$is_current_plan): ?>
                                    <!-- Botón eliminado: solo visualización -->
                                    <?php else: ?>
                                    <button class="plan-button secondary" disabled>
                                        <i class="fas fa-check-circle me-2"></i>
                                        Plan Actual
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>
            </div>

            <!-- Grid para desktop -->
            <div class="plans-grid">
                <div class="row justify-content-center">
                    <?php 
                    $planCount = 0;
                    foreach ($available_plans as $plan): 
                        if ($plan['id'] == 1) continue; // Saltar el plan gratuito en la lista de planes disponibles
                        $is_current_plan = $restaurant['current_plan_id'] == $plan['id'];
                        $features = json_decode($plan['features'] ?? '[]', true) ?? [];
                        $planColors = getPlanColor($plan['id']);
                        $planCount++;
                        $is_featured = $plan['id'] == 3; // Marcar el plan Premium como destacado
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4 slide-in" style="animation-delay: <?= $planCount * 0.1 ?>s">
                        <div class="card plan-card h-100 <?= $is_featured ? 'featured' : '' ?>">
                            <?php if ($is_current_plan): ?>
                            <div class="current-plan-badge">
                                <i class="fas fa-check-circle me-1"></i> Plan Actual
                            </div>
                            <?php endif; ?>

                            <?php if ($is_featured): ?>
                            <div class="featured-badge">
                                <i class="fas fa-star me-1"></i> Más Popular
                            </div>
                            <?php endif; ?>
                            
                            <div class="plan-header" style="background: <?= $planColors['gradient'] ?>">
                                <h3 class="plan-name"><?= htmlspecialchars($plan['name']) ?></h3>
                                <div class="plan-price monthly-price" data-plan-id="<?= $plan['id'] ?>" data-base-price="<?= $plan['base_price'] ?>">
                                    <?php 
                                    $price = $plan['base_price'];
                                    // Convertir a CLP por defecto
                                    $formatted_price = CurrencyConverter::formatPriceInCurrency($price, 'CLP', $conn);
                                    echo $formatted_price;
                                    ?>
                                </div>
                                <div class="plan-price annual-price" style="display: none;" data-plan-id="<?= $plan['id'] ?>" data-base-price="<?= $plan['base_price'] ?>">
                                    <?php 
                                    $annual_price = $price * 0.6; // 40% descuento
                                    $total_annual_price = $annual_price * 12; // Precio total anual
                                    $formatted_total_annual_price = CurrencyConverter::formatPriceInCurrency($total_annual_price, 'CLP', $conn);
                                    echo $formatted_total_annual_price;
                                    ?>
                                </div>
                                <p class="plan-period monthly-period">por mes</p>
                                <p class="plan-period annual-period" style="display: none;">pago único anual</p>
                                <div class="annual-total" style="display: none;">
                                    <small class="opacity-75">¡Ahorra 40% con plan anual!</small>
                                </div>
                            </div>
                            
                            <div class="plan-features">
                                <div class="mb-4">
                                    <div class="feature-item limit">
                                        <i class="fas fa-tags"></i>
                                        <div class="feature-text">
                                            <span class="feature-value"><?= $plan['max_categories'] ?></span> Categorías
                                        </div>
                                    </div>
                                    <div class="feature-item limit">
                                        <i class="fas fa-utensils"></i>
                                        <div class="feature-text">
                                            <span class="feature-value"><?= $plan['max_products'] ?></span> Productos
                                        </div>
                                    </div>
                                    <div class="feature-item limit">
                                        <i class="fas fa-store"></i>
                                        <div class="feature-text">
                                            <span class="feature-value"><?= $plan['max_branches'] ?></span> Sucursales
                                        </div>
                                    </div>
                                    <?php foreach ($features as $index => $feature): ?>
                                    <div class="feature-item <?= $plan['id'] == 2 ? 'basic' : ($plan['id'] == 3 ? 'premium' : 'enterprise') ?>">
                                        <i class="fas fa-check"></i>
                                        <div class="feature-text"><?= htmlspecialchars($feature) ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (!$is_current_plan): ?>
                                <!-- Botón eliminado: solo visualización -->
                                <?php else: ?>
                                <button class="plan-button secondary" disabled>
                                    <i class="fas fa-check-circle me-2"></i>
                                    Plan Actual
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Sección de Plan Actual y Soporte -->
        <div class="current-plan-support-section fade-in">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center mb-5">
                        <i class="fas fa-info-circle me-3"></i>
                        Tu Plan Actual
                    </h2>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="plan-status-card">
                        <?php 
                        $timeRemaining = getPlanTimeRemaining($restaurant);
                        $statusIcon = '';
                        $statusClass = '';
                        
                        switch ($timeRemaining['status']) {
                            case 'success':
                                $statusIcon = 'fas fa-check-circle';
                                $statusClass = 'success';
                                break;
                            case 'warning':
                                $statusIcon = 'fas fa-exclamation-triangle';
                                $statusClass = 'warning';
                                break;
                            case 'danger':
                            case 'urgent':
                                $statusIcon = 'fas fa-times-circle';
                                $statusClass = 'danger';
                                break;
                            case 'info':
                                $statusIcon = 'fas fa-info-circle';
                                $statusClass = 'info';
                                break;
                            default:
                                $statusIcon = 'fas fa-question-circle';
                                $statusClass = 'secondary';
                                break;
                        }
                        ?>
                        
                        <div class="plan-status-header">
                            <div class="plan-status-icon <?= $statusClass ?>">
                                <i class="<?= $statusIcon ?>"></i>
                            </div>
                            <div class="plan-status-info">
                                <h3><?= htmlspecialchars($restaurant['plan_name'] ?? 'Sin plan') ?></h3>
                                <p class="mb-2">Plan actual de tu restaurante</p>
                                <span class="time-remaining-badge <?= $timeRemaining['status'] ?>">
                                    <i class="fas fa-clock me-1"></i>
                                    <?= htmlspecialchars($timeRemaining['message']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="plan-details-grid">
                            <div class="plan-detail-item">
                                <div class="plan-detail-value" id="currentPlanPrice" data-base-price="<?= $restaurant['base_price'] ?? 0 ?>">
                                    <?= CurrencyConverter::formatPriceInCurrency($restaurant['base_price'] ?? 0, 'CLP', $conn) ?>
                                </div>
                                <div class="plan-detail-label">Precio Mensual</div>
                            </div>
                            <div class="plan-detail-item">
                                <div class="plan-detail-value"><?= $restaurant['current_categories'] ?? 0 ?>/<?= $restaurant['max_categories'] ?? 0 ?></div>
                                <div class="plan-detail-label">Categorías</div>
                            </div>
                            <div class="plan-detail-item">
                                <div class="plan-detail-value"><?= $restaurant['current_products'] ?? 0 ?>/<?= $restaurant['max_products'] ?? 0 ?></div>
                                <div class="plan-detail-label">Productos</div>
                            </div>
                            <?php if ($restaurant['final_end_date']): ?>
                            <div class="plan-detail-item">
                                <div class="plan-detail-value">
                                    <i class="fas fa-calendar-alt text-primary"></i>
                                    <?= date('d/m/Y', strtotime($restaurant['final_end_date'])) ?>
                                </div>
                                <div class="plan-detail-label">Fecha de Vencimiento</div>
                            </div>
                            <?php endif; ?>
                            <?php if ($restaurant['subscription_id']): ?>
                            <div class="plan-detail-item">
                                <div class="plan-detail-value">
                                    <i class="fas fa-clock text-info"></i>
                                    <?= $restaurant['duration_months'] ?? 1 ?> mes<?= ($restaurant['duration_months'] ?? 1) > 1 ? 'es' : '' ?>
                                </div>
                                <div class="plan-detail-label">Duración del Plan</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de Soporte -->
            <div class="row mt-5">
                <div class="col-12">
                    <h3 class="text-center mb-4">
                        <i class="fas fa-headset me-3"></i>
                        ¿Necesitas Ayuda?
                    </h3>
                    
                    <div class="support-buttons">
                        <a href="mailto:tumenufast@gmail.com" class="support-button contact">
                            <i class="fas fa-envelope"></i>
                            Contactar Soporte
                        </a>
                        
                        <a href="https://wa.me/56932094742" 
                           target="_blank" 
                           class="support-button support">
                            <i class="fab fa-whatsapp"></i>
                            WhatsApp Soporte
                        </a>
                        
                        <button type="button" 
                                class="support-button feedback" 
                                data-bs-toggle="modal" 
                                data-bs-target="#feedbackModal">
                            <i class="fas fa-comment-dots"></i>
                            Enviar Feedback
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Feedback Mejorado -->
        <div class="modal fade feedback-modal" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="feedbackModalLabel">
                            <i class="fas fa-comment-dots me-2"></i>
                            ¡Cuéntanos tu opinión!
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="feedbackForm">
                        <div class="modal-body">
                            <!-- Selector de tipo de feedback compacto -->
                            <div class="feedback-form-group">
                                <label for="feedbackType">
                                    <i class="fas fa-tag"></i>
                                    Tipo de feedback
                                </label>
                                <select class="form-control feedback-form-control" id="feedbackType" name="type" required>
                                        <option value="">Selecciona un tipo</option>
                                    <option value="suggestion">💡 Sugerencia</option>
                                    <option value="bug">🐛 Reportar Error</option>
                                    <option value="feature">➕ Nueva Función</option>
                                    <option value="compliment">❤️ Elogio</option>
                                    <option value="other">❓ Otro</option>
                                    </select>
                                <div class="form-focus-indicator"></div>
                                </div>
                                
                            <!-- Asunto -->
                            <div class="feedback-form-group">
                                <label for="feedbackSubject">
                                    <i class="fas fa-heading"></i>
                                    Asunto
                                </label>
                                <input type="text" 
                                       class="form-control feedback-form-control" 
                                       id="feedbackSubject" 
                                       name="subject" 
                                       required 
                                       placeholder="Resume tu feedback en pocas palabras..."
                                       maxlength="100">
                                <div class="form-focus-indicator"></div>
                                <div class="character-counter">
                                    <span id="subjectCounter">0</span>/100
                                </div>
                            </div>
                            
                            <!-- Mensaje -->
                            <div class="feedback-form-group">
                                <label for="feedbackMessage">
                                    <i class="fas fa-comment-alt"></i>
                                    Mensaje
                                </label>
                                <textarea class="form-control feedback-form-control feedback-textarea" 
                                          id="feedbackMessage" 
                                          name="message" 
                                          rows="5" 
                                          required 
                                          placeholder="Describe tu feedback en detalle... Nos encanta leer tus opiniones! 😊"
                                          maxlength="1000"></textarea>
                                <div class="form-focus-indicator"></div>
                                <div class="character-counter">
                                    <span id="messageCounter">0</span>/1000
                                </div>
                            </div>
                            
                            <!-- Email -->
                            <div class="feedback-form-group">
                                <label for="feedbackEmail">
                                    <i class="fas fa-envelope"></i>
                                    Email de contacto <span class="text-muted">(opcional)</span>
                                </label>
                                <input type="email" 
                                       class="form-control feedback-form-control" 
                                       id="feedbackEmail" 
                                       name="email" 
                                       value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>"
                                       placeholder="tu@email.com">
                                <div class="form-focus-indicator"></div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Solo si quieres que te contactemos para más detalles
                                </small>
                            </div>
                            
                            <!-- Emoji selector para el mensaje -->
                            <div class="feedback-form-group">
                                <label>
                                    <i class="fas fa-smile"></i>
                                    ¿Cómo te sientes con nuestra plataforma?
                                </label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm emoji-btn" data-emoji="😊">
                                        😊 Muy bien
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm emoji-btn" data-emoji="😍">
                                        😍 Me encanta
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm emoji-btn" data-emoji="😐">
                                        😐 Regular
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm emoji-btn" data-emoji="😞">
                                        😞 No me gusta
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm emoji-btn" data-emoji="🤔">
                                        🤔 No sé
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn feedback-btn feedback-btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </button>
                            <button type="submit" class="btn feedback-btn feedback-btn-primary" id="feedbackSubmitBtn">
                                <i class="fas fa-paper-plane me-2"></i>Enviar Feedback
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div> <br> <br>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Inicializar Swiper para el carrusel de planes
        const plansSwiper = new Swiper('.plans-swiper', {
            slidesPerView: 1,
            spaceBetween: 20,
            loop: false,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            breakpoints: {
                768: {
                    slidesPerView: 1,
                    spaceBetween: 20,
                }
            }
        });

        // Animación de entrada para las barras de uso
        $('.usage-bar-fill').each(function() {
            var width = $(this).css('width');
            $(this).css('width', '0%');
            setTimeout(() => {
                $(this).css('width', width);
            }, 500);
        });

        // Manejar el envío del formulario de actualización de plan
        $('.plan-upgrade-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $spinner = $button.find('.loading-spinner');
            var originalText = $button.text();
            
            // Mostrar spinner y deshabilitar botón
            $spinner.show();
            $button.prop('disabled', true).text('Procesando...');
            
            if (confirm('¿Estás seguro de que deseas cambiar tu plan? Esta acción puede afectar tu facturación.')) {
                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Mostrar mensaje de éxito
                            var alertHtml = `
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fas fa-check-circle me-2"></i> 
                                    ${response.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            `;
                            $('.container').prepend(alertHtml);
                            
                            // Recargar página después de 2 segundos
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            alert(response.error || 'Error al actualizar el plan');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        alert('Error al procesar la solicitud. Por favor, intenta nuevamente.');
                    },
                    complete: function() {
                        // Restaurar botón
                        $spinner.hide();
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            } else {
                // Restaurar botón si se cancela
                $spinner.hide();
                $button.prop('disabled', false).text(originalText);
            }
        });

        // Efecto hover para las tarjetas de plan
        $('.plan-card').hover(
            function() {
                $(this).find('.plan-button').addClass('pulse');
            },
            function() {
                $(this).find('.plan-button').removeClass('pulse');
            }
        );

        // Animación de scroll suave para enlaces internos
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            var target = $(this.getAttribute('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 800);
            }
        });

        // Tooltip para características
        $('[data-toggle="tooltip"]').tooltip();

        // Animación de contador para precios
        $('.plan-price').each(function() {
            var $this = $(this);
            var price = parseFloat($this.text().replace('$', '').replace(/\./g, '').replace(',', ''));
            var count = 0;
            var increment = price / 50;
            
            var timer = setInterval(function() {
                count += increment;
                if (count >= price) {
                    count = price;
                    clearInterval(timer);
                }
                $this.text('$' + Math.floor(count).toLocaleString('es-CL'));
            }, 20);
        });

        // Pricing toggle functionality
        const toggleButton = document.getElementById('pricingToggle');
        const monthlyPrices = document.querySelectorAll('.monthly-price');
        const annualPrices = document.querySelectorAll('.annual-price');
        const monthlyPeriods = document.querySelectorAll('.monthly-period');
        const annualPeriods = document.querySelectorAll('.annual-period');
        const annualTotals = document.querySelectorAll('.annual-total');
        const annualSavings = document.getElementById('annualSavings');
        const subscriptionLinks = document.querySelectorAll('.subscription-link');
        
        if (toggleButton) {
            toggleButton.addEventListener('change', function() {
                const isAnnual = this.checked;
                
                // Toggle price visibility
                monthlyPrices.forEach(price => {
                    price.style.display = isAnnual ? 'none' : 'block';
                });
                
                annualPrices.forEach(price => {
                    price.style.display = isAnnual ? 'block' : 'none';
                });
                
                // Toggle period visibility
                monthlyPeriods.forEach(period => {
                    period.style.display = isAnnual ? 'none' : 'block';
                });
                
                annualPeriods.forEach(period => {
                    period.style.display = isAnnual ? 'block' : 'none';
                });
                
                // Toggle annual total visibility
                annualTotals.forEach(total => {
                    total.style.display = isAnnual ? 'block' : 'none';
                });
                
                // Toggle savings badge
                if (annualSavings) {
                    annualSavings.style.display = isAnnual ? 'block' : 'none';
                }
                
                // Update subscription links
                updateSubscriptionLinks(isAnnual);
            });
        }
        
        function updateSubscriptionLinks(isAnnual) {
            const duration = isAnnual ? 'annual' : 'monthly';
            
            subscriptionLinks.forEach(link => {
                const currentHref = link.getAttribute('href');
                if (currentHref && currentHref.includes('/subscription/process.php')) {
                    // Update the duration parameter
                    const newHref = currentHref.replace(/duration=[^&]*/, `duration=${duration}`);
                    link.setAttribute('href', newHref);
                }
            });
        }
        
        // Initialize links with correct duration
        updateSubscriptionLinks(false);

        // Currency conversion functionality
        const currencySelect = document.getElementById('currencySelect');
        let currentCurrency = 'CLP';
        
        if (currencySelect) {
            currencySelect.addEventListener('change', function() {
                const newCurrency = this.value;
                changeCurrency(newCurrency);
            });
        }
        
        function changeCurrency(currency) {
            currentCurrency = currency;
            
            // Agregar clase de animación
            document.querySelectorAll('.plan-price').forEach(price => {
                price.classList.add('updating');
            });
            
            // Mostrar indicador de carga
            const loadingHtml = '<i class="fas fa-spinner fa-spin"></i>';
            document.querySelectorAll('.plan-price').forEach(price => {
                price.innerHTML = loadingHtml;
            });
            
            // Realizar petición AJAX para obtener precios convertidos
            $.ajax({
                url: '/restaurante/ajax/change_currency.php',
                method: 'POST',
                data: { currency: currency },
                dataType: 'json',
                timeout: 10000, // 10 segundos de timeout
                success: function(response) {
                    if (response.success) {
                        updatePrices(response.plans, response.current_plan);
                        
                        // Remover clase de animación después de actualizar
                        setTimeout(() => {
                            document.querySelectorAll('.plan-price').forEach(price => {
                                price.classList.remove('updating');
                            });
                        }, 300);
                    } else {
                        console.error('Error al cambiar moneda:', response.message);
                        // Restaurar precios originales en caso de error
                        restoreOriginalPrices();
                        document.querySelectorAll('.plan-price').forEach(price => {
                            price.classList.remove('updating');
                        });
                        
                        // Mostrar mensaje de error
                        alert('Error al cambiar la moneda: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error en la petición:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    
                    restoreOriginalPrices();
                    document.querySelectorAll('.plan-price').forEach(price => {
                        price.classList.remove('updating');
                    });
                    
                    // Mostrar mensaje de error
                    alert('Error al cambiar la moneda. Por favor, intenta nuevamente.');
                }
            });
        }
        
        function updatePrices(plans, currentPlan) {
            // Actualizar precios de los planes
            plans.forEach(plan => {
                const monthlyPriceElements = document.querySelectorAll(`.plan-price.monthly-price[data-plan-id="${plan.id}"]`);
                const annualPriceElements = document.querySelectorAll(`.plan-price.annual-price[data-plan-id="${plan.id}"]`);
                
                monthlyPriceElements.forEach(element => {
                    element.textContent = plan.monthly_price;
                });
                
                annualPriceElements.forEach(element => {
                    element.textContent = plan.annual_price;
                });
            });
            
            // Actualizar precio del plan actual
            const currentPlanPriceElement = document.getElementById('currentPlanPrice');
            if (currentPlanPriceElement && currentPlan.price) {
                currentPlanPriceElement.textContent = currentPlan.price;
            }
        }
        
        function restoreOriginalPrices() {
            // Restaurar precios originales en CLP
            document.querySelectorAll('.plan-price').forEach(price => {
                const basePrice = price.getAttribute('data-base-price');
                const isAnnual = price.classList.contains('annual-price');
                
                if (basePrice) {
                    let priceValue = parseFloat(basePrice);
                    if (isAnnual) {
                        priceValue = priceValue * 0.6 * 12; // 40% descuento anual
                    }
                    
                    // Formatear precio en CLP
                    const formattedPrice = new Intl.NumberFormat('es-CL', {
                        style: 'currency',
                        currency: 'CLP',
                        minimumFractionDigits: 0
                    }).format(priceValue);
                    
                    price.textContent = formattedPrice;
                }
            });
        }

        // Manejar el formulario de feedback
        $('#feedbackForm').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('button[type="submit"]');
            var originalText = $submitButton.html();
            
            // Mostrar indicador de carga
            $submitButton.html('<i class="fas fa-spinner fa-spin me-2"></i>Enviando...');
            $submitButton.prop('disabled', true);
            
            // Recopilar datos del formulario
            var formData = {
                type: $('#feedbackType').val(),
                subject: $('#feedbackSubject').val().trim(),
                message: $('#feedbackMessage').val().trim(),
                email: $('#feedbackEmail').val().trim(),
                restaurant_name: '<?= htmlspecialchars($restaurant['name'] ?? '') ?>'
            };
            
            // Enviar feedback usando AJAX
            $.ajax({
                url: '/restaurante/ajax/send_feedback.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Mostrar mensaje de éxito
                        $submitButton.html('<i class="fas fa-check me-2"></i>¡Enviado!');
                        
                        var alertHtml = `
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i> 
                                ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                        $('.container').prepend(alertHtml);
                        
                        // Cerrar modal después de 2 segundos
                        setTimeout(function() {
                            $('#feedbackModal').modal('hide');
                            $form[0].reset();
                            $submitButton.html(originalText);
                            $submitButton.prop('disabled', false);
                        }, 2000);
                    } else {
                        // Mostrar mensaje de error
                        $submitButton.html(originalText);
                        $submitButton.prop('disabled', false);
                        
                        var alertHtml = `
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i> 
                                ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                        $('.container').prepend(alertHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    
                    // Restaurar botón
                    $submitButton.html(originalText);
                    $submitButton.prop('disabled', false);
                    
                    // Mostrar mensaje de error genérico
                    var alertHtml = `
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i> 
                            Error al enviar el feedback. Por favor, intenta nuevamente.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                    $('.container').prepend(alertHtml);
                }
            });
        });

        // Resetear formulario cuando se cierra el modal
        $('#feedbackModal').on('hidden.bs.modal', function() {
            $('#feedbackForm')[0].reset();
            var $submitButton = $('#feedbackForm').find('button[type="submit"]');
            $submitButton.html('<i class="fas fa-paper-plane me-2"></i>Enviar Feedback');
            $submitButton.prop('disabled', false);
        });

        // Funciones para gestión de sucursales
        function editBranch(branchId) {
            // Redirigir a la página de sucursales para editar
            window.location.href = '/restaurante/sucursales.php';
        }

        function deleteBranch(branchId, branchName) {
            if (confirm('¿Estás seguro de que deseas eliminar la sucursal "' + branchName + '"? Esta acción no se puede deshacer.')) {
                // Mostrar indicador de carga
                const button = event.target.closest('button');
                const originalContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                button.disabled = true;

                // Realizar petición AJAX para eliminar
                $.ajax({
                    url: '/restaurante/ajax/delete_branch.php',
                    method: 'POST',
                    data: {
                        branch_id: branchId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Mostrar mensaje de éxito
                            const alertHtml = `
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fas fa-check-circle me-2"></i> 
                                    ${response.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            `;
                            $('.container').prepend(alertHtml);
                            
                            // Recargar página después de 2 segundos
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            alert('Error: ' + response.message);
                            // Restaurar botón
                            button.innerHTML = originalContent;
                            button.disabled = false;
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        alert('Error al eliminar la sucursal. Por favor, intenta nuevamente.');
                        // Restaurar botón
                        button.innerHTML = originalContent;
                        button.disabled = false;
                    }
                });
            }
        }

        // Funcionalidades mejoradas para el modal de feedback
        $(document).ready(function() {
            // Contadores de caracteres
            $('#feedbackSubject').on('input', function() {
                const maxLength = 100;
                const currentLength = $(this).val().length;
                $('#subjectCounter').text(currentLength);
                
                if (currentLength > maxLength * 0.8) {
                    $('#subjectCounter').addClass('text-warning');
                } else {
                    $('#subjectCounter').removeClass('text-warning');
                }
                
                if (currentLength > maxLength) {
                    $('#subjectCounter').addClass('text-danger');
                } else {
                    $('#subjectCounter').removeClass('text-danger');
                }
            });
            
            $('#feedbackMessage').on('input', function() {
                const maxLength = 1000;
                const currentLength = $(this).val().length;
                $('#messageCounter').text(currentLength);
                
                if (currentLength > maxLength * 0.8) {
                    $('#messageCounter').addClass('text-warning');
                } else {
                    $('#messageCounter').removeClass('text-warning');
                }
                
                if (currentLength > maxLength) {
                    $('#messageCounter').addClass('text-danger');
                } else {
                    $('#messageCounter').removeClass('text-danger');
                }
            });
            
            // Selector de emojis
            $('.emoji-btn').on('click', function() {
                const emoji = $(this).data('emoji');
                const textarea = $('#feedbackMessage');
                const currentValue = textarea.val();
                
                // Agregar emoji al final del mensaje
                textarea.val(currentValue + ' ' + emoji);
                
                // Trigger input event para actualizar contador
                textarea.trigger('input');
                
                // Efecto visual en el botón
                $(this).addClass('btn-primary').removeClass('btn-outline-primary btn-outline-success btn-outline-warning btn-outline-danger btn-outline-info');
                
                // Remover efecto de otros botones
                $('.emoji-btn').not(this).removeClass('btn-primary').addClass(function() {
                    const classes = $(this).attr('class').split(' ');
                    return classes.find(cls => cls.startsWith('btn-outline-'));
                });
                
                // Animación de confirmación
                $(this).addClass('animate__animated animate__pulse');
                setTimeout(() => {
                    $(this).removeClass('animate__animated animate__pulse');
                }, 500);
            });
            
            // Animación de entrada para las opciones de tipo
            $('.feedback-type-option').each(function(index) {
                $(this).css({
                    'animation-delay': (index * 0.1) + 's',
                    'animation': 'slideInUp 0.5s ease-out forwards'
                });
            });
            
            // Efecto hover mejorado para las opciones de tipo
            $('.feedback-type-option').hover(
                function() {
                    $(this).find('.type-card').addClass('hover-effect');
                },
                function() {
                    $(this).find('.type-card').removeClass('hover-effect');
                }
            );
            
            // Validación en tiempo real
            $('#feedbackForm').on('input change', function() {
                const subject = $('#feedbackSubject').val().trim();
                const message = $('#feedbackMessage').val().trim();
                const type = $('#feedbackType').val();
                const submitBtn = $('#feedbackSubmitBtn');
                
                if (subject && message && type) {
                    submitBtn.prop('disabled', false).removeClass('btn-secondary').addClass('btn-primary');
                } else {
                    submitBtn.prop('disabled', true).removeClass('btn-primary').addClass('btn-secondary');
                }
            });
            
            // Manejar el formulario de feedback mejorado
            $('#feedbackForm').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submitButton = $form.find('button[type="submit"]');
                var originalText = $submitButton.html();
                
                // Mostrar indicador de carga con animación
                $submitButton.html('<div class="feedback-loading"></div>Enviando...');
                $submitButton.prop('disabled', true);
                
                // Recopilar datos del formulario
                var formData = {
                    type: $('#feedbackType').val(),
                    subject: $('#feedbackSubject').val().trim(),
                    message: $('#feedbackMessage').val().trim(),
                    email: $('#feedbackEmail').val().trim(),
                    restaurant_name: '<?= htmlspecialchars($restaurant['name'] ?? '') ?>'
                };
                
                // Validación adicional
                if (!formData.type || !formData.subject || !formData.message) {
                    showFeedbackError('Por favor, completa todos los campos requeridos.');
                    $submitButton.html(originalText);
                    $submitButton.prop('disabled', false);
                    return;
                }
                
                // Enviar feedback usando AJAX
                $.ajax({
                    url: '/restaurante/ajax/send_feedback.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    timeout: 15000, // 15 segundos de timeout
                    success: function(response) {
                        if (response.success) {
                            // Mostrar animación de éxito
                            showFeedbackSuccess(response.message);
                            
                            // Cerrar modal después de mostrar la animación
                            setTimeout(function() {
                                $('#feedbackModal').modal('hide');
                                resetFeedbackForm();
                            }, 3000);
                        } else {
                            showFeedbackError(response.message || 'Error al enviar el feedback');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        console.error('Status:', status);
                        console.error('Response:', xhr.responseText);
                        
                        let errorMessage = 'Error al enviar el feedback. Por favor, intenta nuevamente.';
                        
                        if (status === 'timeout') {
                            errorMessage = 'La solicitud tardó demasiado. Verifica tu conexión e intenta nuevamente.';
                        } else if (xhr.status === 0) {
                            errorMessage = 'Error de conexión. Verifica tu internet e intenta nuevamente.';
                        }
                        
                        showFeedbackError(errorMessage);
                    },
                    complete: function() {
                        // Restaurar botón
                        $submitButton.html(originalText);
                        $submitButton.prop('disabled', false);
                    }
                });
            });
            
            // Función para mostrar éxito
            function showFeedbackSuccess(message) {
                // Crear efecto de confeti
                createConfetti();
                
                const successHtml = `
                    <div class="feedback-success-animation">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>¡Feedback Enviado!</h3>
                        <p>${message}</p>
                    </div>
                `;
                
                $('body').append(successHtml);
                
                // Remover después de la animación
                setTimeout(() => {
                    $('.feedback-success-animation').fadeOut(500, function() {
                        $(this).remove();
                    });
                    // Remover confeti
                    $('.confetti').remove();
                }, 2500);
            }
            
            // Función para crear confeti
            function createConfetti() {
                const colors = ['#667eea', '#764ba2', '#28a745', '#ffc107', '#dc3545', '#17a2b8'];
                const confettiCount = 50;
                
                for (let i = 0; i < confettiCount; i++) {
                    const confetti = $('<div class="confetti"></div>');
                    const color = colors[Math.floor(Math.random() * colors.length)];
                    const left = Math.random() * 100;
                    const animationDelay = Math.random() * 3;
                    const animationDuration = 3 + Math.random() * 2;
                    
                    confetti.css({
                        'background': color,
                        'left': left + '%',
                        'animation-delay': animationDelay + 's',
                        'animation-duration': animationDuration + 's'
                    });
                    
                    $('body').append(confetti);
                }
            }
            
            // Función para mostrar error
            function showFeedbackError(message) {
                const alertHtml = `
                    <div class="alert alert-danger alert-dismissible fade show" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="fas fa-exclamation-triangle me-2"></i> 
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                
                $('body').append(alertHtml);
                
                // Auto-remover después de 5 segundos
                setTimeout(() => {
                    $('.alert-danger').fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
            
            // Función para resetear el formulario
            function resetFeedbackForm() {
                $('#feedbackForm')[0].reset();
                $('#subjectCounter').text('0');
                $('#messageCounter').text('0');
                $('.emoji-btn').removeClass('btn-primary').addClass(function() {
                    const classes = $(this).attr('class').split(' ');
                    return classes.find(cls => cls.startsWith('btn-outline-'));
                });
                $('.feedback-type-option input[type="radio"]').prop('checked', false);
                $('.feedback-type-option .type-card').removeClass('selected');
            }
            
            // Resetear formulario cuando se cierra el modal
            $('#feedbackModal').on('hidden.bs.modal', function() {
                resetFeedbackForm();
            });
            
            // Animación de entrada del modal
            $('#feedbackModal').on('show.bs.modal', function() {
                $(this).find('.modal-content').css('transform', 'scale(0.7)');
                setTimeout(() => {
                    $(this).find('.modal-content').css({
                        'transform': 'scale(1)',
                        'transition': 'transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)'
                    });
                }, 100);
                
                // Crear partículas en el header
                createModalParticles();
            });
            
            // Función para crear partículas en el modal
            function createModalParticles() {
                const modalHeader = $('.feedback-modal .modal-header');
                const particlesContainer = $('<div class="modal-particles"></div>');
                
                modalHeader.append(particlesContainer);
                
                for (let i = 0; i < 15; i++) {
                    const particle = $('<div class="particle"></div>');
                    const left = Math.random() * 100;
                    const animationDelay = Math.random() * 4;
                    const animationDuration = 4 + Math.random() * 2;
                    
                    particle.css({
                        'left': left + '%',
                        'animation-delay': animationDelay + 's',
                        'animation-duration': animationDuration + 's'
                    });
                    
                    particlesContainer.append(particle);
                }
            }
            
            // Efecto de focus en los campos
            $('.feedback-form-control').on('focus', function() {
                $(this).parent().addClass('focused');
            }).on('blur', function() {
                $(this).parent().removeClass('focused');
            });
            
            // Animación de las opciones de tipo al hacer clic
            $('.feedback-type-option input[type="radio"]').on('change', function() {
                $('.feedback-type-option .type-card').removeClass('selected');
                $(this).closest('.feedback-type-option').find('.type-card').addClass('selected');
                
                // Efecto de confeti para el tipo seleccionado
                const $card = $(this).closest('.feedback-type-option').find('.type-card');
                $card.addClass('animate__animated animate__bounceIn');
                setTimeout(() => {
                    $card.removeClass('animate__animated animate__bounceIn');
                }, 600);
            });
        });
    });
    </script>
</body>
</html> 
