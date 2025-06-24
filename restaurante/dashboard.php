<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    redirect(BASE_URL . '/restaurante/login.php');
}

$restaurant_id = $_SESSION['restaurant_id'];
$message = '';
$error = '';
$profile_completion = 0;
$restaurant = null;
$is_first_login = false;
$show_welcome_modal = false;

// Agregar esta función después de los requires iniciales
function traducirEstadoOrden($estado) {
    $estados = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'preparing' => 'Preparando',
        'ready' => 'Listo',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado'
    ];
    return $estados[$estado] ?? ucfirst($estado);
}

// Función para verificar si debe mostrar el modal de bienvenida
function shouldShowWelcomeModal($restaurant_id) {
    $cookie_name = 'welcome_modal_' . $restaurant_id;
    $cookie_value = $_COOKIE[$cookie_name] ?? null;
    
    if ($cookie_value === null) {
        // Primera vez, mostrar modal y establecer cookie
        setcookie($cookie_name, time(), time() + (5 * 24 * 60 * 60), '/'); // 5 días
        return true;
    }
    
    // Verificar si han pasado 5 días desde la última vez
    $last_shown = (int)$cookie_value;
    $current_time = time();
    $days_passed = ($current_time - $last_shown) / (24 * 60 * 60);
    
    // Si han pasado más de 10 años, significa que el usuario desactivó el modal
    if ($days_passed > (3650 * 24 * 60 * 60)) {
        return false;
    }
    
    if ($days_passed >= 5) {
        // Han pasado 5 días o más, mostrar modal y actualizar cookie
        setcookie($cookie_name, $current_time, time() + (5 * 24 * 60 * 60), '/'); // 5 días
        return true;
    }
    
    return false;
}

// Función para verificar si el modal ya se mostró en esta sesión
function hasModalBeenShownThisSession($restaurant_id) {
    $session_key = 'welcome_modal_shown_' . $restaurant_id;
    return isset($_SESSION[$session_key]);
}

// Función para marcar que el modal se mostró en esta sesión
function markModalAsShownThisSession($restaurant_id) {
    $session_key = 'welcome_modal_shown_' . $restaurant_id;
    $_SESSION[$session_key] = true;
}

try {
    // Obtener información del restaurante y su plan
    try {
        $stmt = $conn->prepare("
            SELECT r.*, 
                   p.name as plan_name,
                   p.max_categories,
                   p.max_products,
                   (SELECT COUNT(*) FROM menu_categories WHERE restaurant_id = r.id) as current_categories,
                   (SELECT COUNT(*) FROM products WHERE restaurant_id = r.id) as current_products,
                   (SELECT COUNT(*) FROM orders WHERE restaurant_id = r.id AND DATE(created_at) = CURDATE()) as today_orders,
                   (SELECT COUNT(*) FROM orders WHERE restaurant_id = r.id AND DATE(created_at) = CURDATE() AND status = 'pending') as pending_orders,
                   (SELECT COALESCE(SUM(total), 0) FROM orders WHERE restaurant_id = r.id AND DATE(created_at) = CURDATE()) as today_sales
            FROM restaurants r
            LEFT JOIN plans p ON r.current_plan_id = p.id
            WHERE r.id = ?
        ");
        $stmt->execute([$restaurant_id]);
        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en consulta principal del dashboard: " . $e->getMessage());
        throw new Exception("Error al obtener información del restaurante: " . $e->getMessage());
    }
    
    if (!$restaurant) {
        error_log("No se encontró el restaurante con ID: " . $restaurant_id);
        redirect(BASE_URL . '/restaurante/logout.php');
    }

    // Verificar si debe mostrar el modal de bienvenida (cada 5 días)
    $should_show_by_cookie = shouldShowWelcomeModal($restaurant_id);
    $has_been_shown_this_session = hasModalBeenShownThisSession($restaurant_id);
    
    // Solo mostrar si debe mostrarse por cookies Y no se ha mostrado en esta sesión
    $show_welcome_modal = $should_show_by_cookie && !$has_been_shown_this_session;
    
    // Si se va a mostrar, marcarlo en la sesión
    if ($show_welcome_modal) {
        markModalAsShownThisSession($restaurant_id);
    }

    // Verificar si es la primera vez que inicia sesión (para actualizar last_login_at)
    if ($restaurant['last_login_at'] === null) {
        $is_first_login = true;
        
        // Actualizar el campo last_login_at
        try {
            $stmt = $conn->prepare("UPDATE restaurants SET last_login_at = NOW() WHERE id = ?");
            $stmt->execute([$restaurant_id]);
        } catch (PDOException $e) {
            error_log("Error al actualizar last_login_at: " . $e->getMessage());
        }
    }

    // Obtener últimas órdenes
    try {
        // Debug para ver el valor de restaurant_id
        error_log("Valor de restaurant_id: " . $restaurant_id);
        
        // Asegurarnos que restaurant_id sea un número
        $restaurant_id = (int)$restaurant_id;
        
        $stmt = $conn->query("
            SELECT id, customer_name, total, status, created_at 
            FROM orders 
            WHERE restaurant_id = $restaurant_id 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        
        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug para ver los datos de las órdenes
        error_log("Datos de órdenes recientes: " . print_r($recent_orders, true));
    } catch (PDOException $e) {
        error_log("Error al obtener órdenes recientes: " . $e->getMessage());
        $recent_orders = [];
    }

    // Verificar si el perfil está completo
    $profile_fields = [
        'name' => 'Nombre del Restaurante',
        'description' => 'Descripción',
        'phone' => 'Teléfono',
        'address' => 'Dirección',
        'logo' => 'Logo',
        'banner' => 'Banner',
        'opening_hours' => 'Horarios'
    ];
    
    $completed_fields = 0;
    foreach ($profile_fields as $field => $label) {
        if (!empty($restaurant[$field])) {
            $completed_fields++;
        }
    }
    $profile_completion = round(($completed_fields / count($profile_fields)) * 100);

} catch (Exception $e) {
    error_log("Error en dashboard: " . $e->getMessage());
    $error = "Error al cargar los datos del dashboard: " . $e->getMessage();
}

// Asegurarse de que las variables críticas tengan valores por defecto si hay error
if (!$restaurant) {
    $restaurant = [
        'name' => 'Restaurante',
        'plan_name' => 'Plan Básico',
        'subscription_status' => 'inactive',
        'current_categories' => 0,
        'max_categories' => 0,
        'current_products' => 0,
        'max_products' => 0,
        'today_orders' => 0,
        'pending_orders' => 0,
        'today_sales' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($restaurant['name']) ?></title>
    
    <!-- Favicon dinámico -->
    <?php if ($restaurant['logo']): ?>
        <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($restaurant['logo']); ?>">
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($restaurant['logo']); ?>">
        <link rel="apple-touch-icon" href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($restaurant['logo']); ?>">
    <?php else: ?>
        <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/img/favicon.ico">
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/img/favicon.ico">
        <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/assets/img/favicon.ico">
    <?php endif; ?>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/restaurante/assets/css/admin.css" rel="stylesheet">
    <style>
        .sidebar {
            background: #2c3e50;
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: #bdc3c7;
            padding: 1rem 1.5rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #34495e;
            color: white;
        }
        .stat-card {
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .progress {
            height: 0.5rem;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .quick-action {
            text-decoration: none;
            color: inherit;
        }
        .quick-action:hover {
            color: inherit;
        }
        .quick-action-card {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            text-align: center;
            transition: all 0.3s;
        }
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .order-status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-pending { background-color: #ffc107; }
        .status-confirmed { background-color: #17a2b8; }
        .status-preparing { background-color: #007bff; }
        .status-ready { background-color: #28a745; }
        .status-delivered { background-color: #6c757d; }
        .status-cancelled { background-color: #dc3545; }

        /* Estilos para el modal de bienvenida */
        #welcomeModal .modal-content {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
            z-index: 9999;
        }
        
        #welcomeModal {
            z-index: 9998;
        }
        
        #welcomeModal .modal-backdrop {
            z-index: 9997;
        }
        
        #welcomeModal .modal-header {
            border-radius: 15px 15px 0 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        #welcomeModal .feature-item {
            padding: 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        #welcomeModal .feature-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        #welcomeModal .btn-close-white {
            filter: brightness(0) invert(1);
        }
        
        /* Asegurar que el modal esté visible */
        .modal.show {
            display: block !important;
            z-index: 9998 !important;
        }
        
        .modal-backdrop.show {
            z-index: 9997 !important;
        }

        /* Botón flotante de ayuda */
        .floating-help-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .floating-help-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            color: white;
        }
        
        .floating-help-btn:active {
            transform: translateY(-1px);
        }
        
        /* Animación de pulso */
        @keyframes pulse {
            0% {
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            }
            50% {
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }
            100% {
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            }
        }
        
        .floating-help-btn.pulse {
            animation: pulse 2s infinite;
        }

        /* Quitar padding en dispositivos móviles */
        @media (max-width: 767.98px) {
            .p-4 {
                margin-top: 13px;
                padding: 0 !important;
            }
            
            /* Cambiar a 2 columnas en móviles para las tarjetas de estadísticas */
            .col-md-3 {
                flex: 0 0 50% !important;
                max-width: 50% !important;
            }
            
            /* Ajustar el tamaño de las tarjetas para móviles */
            .stat-card {
                padding: 1rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            .stat-card h3 {
                font-size: 1.5rem !important;
            }
            
            .stat-icon {
                font-size: 1.5rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Ajustar botón flotante en móviles */
            .floating-help-btn {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Plan Info -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <?php if (isset($_SESSION['is_branch']) && $_SESSION['is_branch']): ?>
                                    <!-- Información para sucursales -->
                                    <div class="col-md-6">
                                        <h5 class="mb-0">
                                            <i class="fas fa-store text-primary"></i> Sucursal
                                        </h5>
                                        <p class="text-muted mb-0">
                                            Sucursal #<?= $_SESSION['branch_number'] ?> - 
                                            <?= htmlspecialchars($restaurant['name']) ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <?php
                                        // Obtener información del restaurante padre
                                        $stmt = $conn->prepare("SELECT name, slug FROM restaurants WHERE id = ?");
                                        $stmt->execute([$_SESSION['parent_restaurant_id']]);
                                        $parent_restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
                                        ?>
                                        <p class="text-muted mb-1">
                                            <i class="fas fa-building"></i> Restaurante Principal: 
                                            <a href="/<?= $parent_restaurant['slug'] ?>" target="_blank" class="text-decoration-none">
                                                <?= htmlspecialchars($parent_restaurant['name']) ?>
                                            </a>
                                        </p>
                                        
                                    </div>
                                <?php else: ?>
                                    <!-- Información para restaurante principal -->
                                    <div class="col-md-6">
                                        <h5 class="mb-0">Plan Actual: <?= htmlspecialchars($restaurant['plan_name']) ?></h5>
                                        <p class="text-muted mb-0">
                                            <?php if ($restaurant['subscription_status'] === 'trial'): ?>
                                                Período de prueba hasta <?= date('d/m/Y', strtotime($restaurant['trial_ends_at'])) ?>
                                            <?php elseif ($restaurant['subscription_status'] === 'active'): ?>
                                                Suscripción activa
                                            <?php else: ?>
                                                Suscripción <?= $restaurant['subscription_status'] ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="/restaurante/planes.php" target="_blank" class="btn btn-outline-primary">
                                            <i class="fas fa-crown"></i> Cambiar Plan
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <a href="/restaurante/menu.php" class="quick-action">
                                <div class="quick-action-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                    <h6>Nuevo Producto</h6>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/restaurante/menu.php" class="quick-action">
                                <div class="quick-action-card" style="background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%); color: white;">
                                    <i class="fas fa-folder-plus fa-2x mb-2"></i>
                                    <h6>Nueva Categoría</h6>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/<?= htmlspecialchars($restaurant['slug']) ?>" class="quick-action" target="_blank">
                                <div class="quick-action-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: white;">
                                    <i class="fas fa-store fa-2x mb-2"></i>
                                    <h6>Ver Mi Menú</h6>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/restaurante/qr.php" target="_blank" class="quick-action">
                                <div class="quick-action-card" style="background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); color: white;">
                                    <i class="fas fa-qrcode fa-2x mb-2"></i>
                                    <h6>Generar QR</h6>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card bg-primary text-white">
                                <div class="stat-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <h3><?= number_format($restaurant['today_orders']) ?></h3>
                                <p class="mb-0">Ordenes Hoy</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-success text-white">
                                <div class="stat-icon">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <h3>$<?= number_format($restaurant['today_sales'], 0) ?></h3>
                                <p class="mb-0">Ventas Hoy</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-warning text-white">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3><?= number_format($restaurant['pending_orders']) ?></h3>
                                <p class="mb-0">Ordenes Pendientes Hoy</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <a href="/restaurante/perfil.php" class="quick-action">
                            <div class="stat-card bg-info text-white">
                                <div class="stat-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h3><?= number_format($profile_completion) ?>%</h3>
                                <p class="mb-0">Perfil Completado</p>
                            </div>
                            </a>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Plan Usage -->
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Uso del Plan</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Categorías</label>
                                        <div class="progress mb-2">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= ($restaurant['current_categories'] / $restaurant['max_categories']) * 100 ?>%">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?= $restaurant['current_categories'] ?> de <?= $restaurant['max_categories'] ?>
                                        </small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Productos</label>
                                        <div class="progress mb-2">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= ($restaurant['current_products'] / $restaurant['max_products']) * 100 ?>%">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?= $restaurant['current_products'] ?> de <?= $restaurant['max_products'] ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Orders -->
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Órdenes Recientes</h5>
                                    <a href="/restaurante/ordenes.php" class="btn btn-sm btn-primary">
                                        Ver Todas
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_orders)): ?>
                                        <p class="text-muted text-center my-3">No hay órdenes recientes</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Cliente</th>
                                                        <th>Total</th>
                                                        <th>Estado</th>
                                                        <th>Fecha</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recent_orders as $order): ?>
                                                        <tr>
                                                            <td>#<?= $order['id'] ?></td>
                                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                                            <td>
                                                                <?php 
                                                                if (isset($order['total'])) {
                                                                    echo '$' . number_format((float)$order['total'], 0);
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <span class="order-status status-<?= $order['status'] ?>"></span>
                                                                <?= traducirEstadoOrden($order['status']) ?>
                                                            </td>
                                                            <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Bienvenida -->
    <div class="modal fade" id="welcomeModal" tabindex="-1" aria-labelledby="welcomeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="welcomeModalLabel">
                        <i class="fas fa-utensils me-2"></i>¡Bienvenido de vuelta a Tumenufast!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeModal()" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <h4 class="mb-3">
                            ¡Comienza a digitalizar tu restaurante!
                        </h4>
                        <p class="text-muted mb-4">
                            Te damos la bienvenida a Tumenufast. Aquí tienes un resumen de las principales funcionalidades disponibles.
                        </p>
                    </div>
                    
                    <!-- Video Tutorial de Bienvenida -->
                    <div class="row justify-content-center mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-0">
                                    <div class="ratio ratio-16x9">
                                        <iframe 
                                            src="https://www.youtube.com/embed/dQw4w9WgXcQ" 
                                            title="Tutorial de Bienvenida - Tumenufast" 
                                            frameborder="0" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                            allowfullscreen
                                            class="rounded">
                                        </iframe>
                                    </div>
                                </div>
                                <div class="card-footer bg-light text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-play-circle me-1"></i>
                                        Tutorial de bienvenida - Aprende a usar Tumenufast en 5 minutos
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botones de Redes Sociales -->
                    <div class="text-center mb-4">
                        <h6 class="mb-3 text-muted">
                            <i class="fas fa-share-alt me-2"></i>Síguenos en nuestras redes sociales
                        </h6>
                        <div class="row justify-content-center">
                            <div class="col-4">
                                <a href="https://www.youtube.com/@tumenufast" target="_blank" class="btn btn-danger w-100 mb-2" style="border-radius: 25px;">
                                    <i class="fab fa-youtube me-2"></i>YouTube
                                </a>
                            </div>
                            <div class="col-4">
                                <a href="https://www.facebook.com/tumenufast" target="_blank" class="btn btn-primary w-100 mb-2" style="border-radius: 25px;">
                                    <i class="fab fa-facebook-f me-2"></i>Facebook
                                </a>
                            </div>
                            <div class="col-4">
                                <a href="https://www.instagram.com/tumenufast" target="_blank" class="btn btn-warning w-100 mb-2" style="border-radius: 25px; color: white;">
                                    <i class="fab fa-instagram me-2"></i>Instagram
                                </a>
                            </div>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-lightbulb me-1"></i>
                            Mantente actualizado con nuestros últimos consejos y novedades
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    
                    <button type="button" class="btn btn-primary" onclick="closeModal()">
                        <i class="fas fa-check me-2"></i>¡Entendido! Comenzar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón flotante de ayuda -->
    <button type="button" class="floating-help-btn pulse" onclick="openWelcomeModal()" title="Ver tutorial de bienvenida">
        <i class="fas fa-question"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Función para abrir el modal de bienvenida desde el botón flotante
        function openWelcomeModal() {
            const modalElement = document.getElementById('welcomeModal');
            if (modalElement) {
                const welcomeModal = new bootstrap.Modal(modalElement, {
                    backdrop: 'static',
                    keyboard: false
                });
                
                welcomeModal.show();
                
                // Asegurar que el modal esté visible
                modalElement.style.display = 'block';
                modalElement.classList.add('show');
                modalElement.style.zIndex = '9998';
                
                // Asegurar que el backdrop esté visible
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.style.zIndex = '9997';
                }
                
                // Detener la animación de pulso cuando se abre el modal
                const helpBtn = document.querySelector('.floating-help-btn');
                if (helpBtn) {
                    helpBtn.classList.remove('pulse');
                }
            } else {
                // Si no existe el modal, mostrar un mensaje alternativo
                alert('Tutorial de bienvenida no disponible en este momento.');
            }
        }
        
        // Función para cerrar el modal
        function closeModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('welcomeModal'));
            if (modal) {
                modal.hide();
            }
            
            // Reanudar la animación de pulso cuando se cierra el modal
            const helpBtn = document.querySelector('.floating-help-btn');
            if (helpBtn) {
                helpBtn.classList.add('pulse');
            }
        }
        
        // Función para desactivar el modal permanentemente
        function disableWelcomeModal() {
            const restaurantId = <?= $restaurant_id ?>;
            const cookieName = 'welcome_modal_' + restaurantId;
            
            // Establecer cookie para nunca más mostrar (expira en 10 años)
            document.cookie = cookieName + '=' + (Date.now() + (3650 * 24 * 60 * 60 * 1000)) + '; expires=' + new Date(Date.now() + (3650 * 24 * 60 * 60 * 1000)).toUTCString() + '; path=/';
            
            // Cerrar el modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('welcomeModal'));
            if (modal) {
                modal.hide();
            }
            
            // Ocultar el botón flotante también
            const helpBtn = document.querySelector('.floating-help-btn');
            if (helpBtn) {
                helpBtn.style.display = 'none';
            }
            
            // Mostrar mensaje de confirmación
            alert('Modal de bienvenida desactivado. No volverá a aparecer.');
        }
        
        <?php if ($show_welcome_modal): ?>
        // Mostrar modal de bienvenida automáticamente cada 5 días
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar que el modal existe
            const modalElement = document.getElementById('welcomeModal');
            if (!modalElement) {
                console.log('Modal no encontrado - no se debe mostrar');
                return;
            }
            
            // Esperar un poco para asegurar que todo esté cargado
            setTimeout(function() {
                const welcomeModal = new bootstrap.Modal(modalElement, {
                    backdrop: 'static',
                    keyboard: false
                });
                
                welcomeModal.show();
                
                // Asegurar que el modal esté visible
                if (modalElement) {
                    modalElement.style.display = 'block';
                    modalElement.classList.add('show');
                    modalElement.style.zIndex = '9998';
                }
                
                // Asegurar que el backdrop esté visible
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.style.zIndex = '9997';
                }
                
                // Detener la animación de pulso cuando se abre automáticamente
                const helpBtn = document.querySelector('.floating-help-btn');
                if (helpBtn) {
                    helpBtn.classList.remove('pulse');
                }
            }, 500);
        });
        <?php endif; ?>
    </script>
</body>
</html>
