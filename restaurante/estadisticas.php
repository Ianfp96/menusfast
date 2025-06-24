<?php
// Prevenir errores de headers already sent
ob_start();

// Iniciar sesión al principio para evitar errores de headers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos de configuración en el orden correcto
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/tracking.php';

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    redirect(BASE_URL . '/restaurante/login.php');
}

$restaurant_id = $_SESSION['restaurant_id'];

// NO registrar visita a la página de estadísticas para evitar que se cuente en las estadísticas generales
// trackPageView($restaurant_id, 'stats');

// Debug: Mostrar información del restaurante
error_log("Estadísticas - Restaurant ID: $restaurant_id");

// Obtener información del restaurante
try {
    $stmt = $conn->prepare("SELECT * FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$restaurant) {
        error_log("Error: Restaurante no encontrado con ID: $restaurant_id");
        die("Error: Restaurante no encontrado");
    }
} catch (PDOException $e) {
    error_log("Error obteniendo información del restaurante: " . $e->getMessage());
    $restaurant = null;
}

// Verificar si las tablas de estadísticas existen
$tables_exist = true;
$required_tables = ['page_views', 'product_views', 'hourly_activity'];

foreach ($required_tables as $table) {
    try {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if (!$stmt->fetch()) {
            error_log("Tabla '$table' no existe");
            $tables_exist = false;
        }
    } catch (PDOException $e) {
        error_log("Error verificando tabla '$table': " . $e->getMessage());
        $tables_exist = false;
    }
}

// Obtener estadísticas
$stats = [];
$period = $_GET['period'] ?? '7d'; // 7d, 30d, 90d, 1y

try {
    // Calcular fechas según el período
    switch ($period) {
        case '7d':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30d':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90d':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            break;
        case '1y':
            $start_date = date('Y-m-d', strtotime('-1 year'));
            break;
        default:
            $start_date = date('Y-m-d', strtotime('-7 days'));
    }

    error_log("Período: $period, Fecha inicio: $start_date");

    if ($tables_exist) {
        // Estadísticas generales
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_views,
                COUNT(DISTINCT ip_address) as unique_visitors,
                COUNT(DISTINCT DATE(created_at)) as active_days
            FROM page_views 
            WHERE restaurant_id = ? AND created_at >= ?
        ");
        $stmt->execute([$restaurant_id, $start_date]);
        $stats['general'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Estadísticas generales: " . json_encode($stats['general']));

        // Visitas por día (últimos 7 días)
        $stmt = $conn->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as views,
                COUNT(DISTINCT ip_address) as unique_visitors
            FROM page_views 
            WHERE restaurant_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$restaurant_id]);
        $stats['daily_views'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Visitas diarias: " . count($stats['daily_views']) . " registros");

        // Productos más vistos
        $stmt = $conn->prepare("
            SELECT 
                p.name as product_name,
                p.id as product_id,
                COUNT(pv.id) as view_count,
                COUNT(DISTINCT pv.ip_address) as unique_viewers
            FROM product_views pv
            JOIN products p ON pv.product_id = p.id
            WHERE pv.restaurant_id = ? AND pv.last_view_at >= ?
            GROUP BY p.id, p.name
            ORDER BY view_count DESC
            LIMIT 10
        ");
        $stmt->execute([$restaurant_id, $start_date]);
        $stats['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Productos más vistos: " . count($stats['top_products']) . " registros");

        // Horas de mayor actividad - Usando tabla hourly_activity existente
        try {
            $stmt = $conn->prepare("
                WITH RECURSIVE hours AS (
                    SELECT 0 as hour
                    UNION ALL
                    SELECT hour + 1 FROM hours WHERE hour < 23
                ),
                hourly_stats AS (
                    SELECT 
                        hour_of_day as hour,
                        SUM(page_views) as views,
                        SUM(unique_visitors) as unique_visitors
                    FROM hourly_activity 
                    WHERE restaurant_id = ? AND activity_date >= ?
                    GROUP BY hour_of_day
                )
                SELECT 
                    h.hour,
                    COALESCE(hs.views, 0) as views,
                    COALESCE(hs.unique_visitors, 0) as unique_visitors
                FROM hours h
                LEFT JOIN hourly_stats hs ON h.hour = hs.hour
                ORDER BY h.hour ASC
            ");
            $stmt->execute([$restaurant_id, $start_date]);
            $stats['hourly_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error con consulta recursiva para hourly_activity, usando consulta simple: " . $e->getMessage());
            // Consulta de respaldo para versiones anteriores de MySQL
            $stmt = $conn->prepare("
                SELECT 
                    hour_of_day as hour,
                    SUM(page_views) as views,
                    SUM(unique_visitors) as unique_visitors
                FROM hourly_activity 
                WHERE restaurant_id = ? AND activity_date >= ?
                GROUP BY hour_of_day
                ORDER BY hour_of_day ASC
            ");
            $stmt->execute([$restaurant_id, $start_date]);
            $stats['hourly_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        error_log("Actividad por hora: " . count($stats['hourly_activity']) . " registros");
        
        // Debug: Mostrar datos de actividad por hora
        if (!empty($stats['hourly_activity'])) {
            error_log("Datos de actividad por hora: " . json_encode($stats['hourly_activity']));
        }

        // Categorías más visitadas
        $stmt = $conn->prepare("
            SELECT 
                mc.name as category_name,
                mc.id as category_id,
                COUNT(pv.id) as view_count
            FROM product_views pv
            JOIN products p ON pv.product_id = p.id
            JOIN menu_categories mc ON p.category_id = mc.id
            WHERE pv.restaurant_id = ? AND pv.last_view_at >= ?
            GROUP BY mc.id, mc.name
            ORDER BY view_count DESC
            LIMIT 5
        ");
        $stmt->execute([$restaurant_id, $start_date]);
        $stats['top_categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Categorías más visitadas: " . count($stats['top_categories']) . " registros");

        // Dispositivos más usados
        $stmt = $conn->prepare("
            SELECT 
                CASE 
                    WHEN user_agent LIKE '%Mobile%' OR user_agent LIKE '%Android%' OR user_agent LIKE '%iPhone%' THEN 'Móvil'
                    WHEN user_agent LIKE '%Tablet%' OR user_agent LIKE '%iPad%' THEN 'Tablet'
                    ELSE 'Desktop'
                END as device_type,
                COUNT(*) as count
            FROM page_views 
            WHERE restaurant_id = ? AND created_at >= ?
            GROUP BY device_type
            ORDER BY count DESC
        ");
        $stmt->execute([$restaurant_id, $start_date]);
        $stats['device_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Tipos de dispositivos: " . count($stats['device_types']) . " registros");

        // Tendencias de crecimiento
        $stmt = $conn->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as views
            FROM page_views 
            WHERE restaurant_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$restaurant_id]);
        $stats['growth_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Tendencias de crecimiento: " . count($stats['growth_trend']) . " registros");

        // Estadísticas de páginas específicas
        $stmt = $conn->prepare("
            SELECT 
                page_type,
                COUNT(*) as views,
                COUNT(DISTINCT ip_address) as unique_visitors
            FROM page_views 
            WHERE restaurant_id = ? AND created_at >= ?
            GROUP BY page_type
            ORDER BY views DESC
        ");
        $stmt->execute([$restaurant_id, $start_date]);
        $stats['page_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Tipos de página: " . count($stats['page_types']) . " registros");

    } else {
        error_log("Las tablas de estadísticas no existen, creando datos vacíos");
        $stats = [
            'general' => ['total_views' => 0, 'unique_visitors' => 0, 'active_days' => 0],
            'daily_views' => [],
            'top_products' => [],
            'hourly_activity' => [],
            'top_categories' => [],
            'device_types' => [],
            'growth_trend' => [],
            'page_types' => []
        ];
    }

} catch (PDOException $e) {
    error_log("Error obteniendo estadísticas: " . $e->getMessage());
    $stats = [
        'general' => ['total_views' => 0, 'unique_visitors' => 0, 'active_days' => 0],
        'daily_views' => [],
        'top_products' => [],
        'hourly_activity' => [],
        'top_categories' => [],
        'device_types' => [],
        'growth_trend' => [],
        'page_types' => []
    ];
}

// Función para formatear números
function formatNumber($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return $number;
}

// Función para obtener el color del período
function getPeriodColor($period) {
    switch ($period) {
        case '7d': return 'primary';
        case '30d': return 'success';
        case '90d': return 'warning';
        case '1y': return 'info';
        default: return 'primary';
    }
}

// Función para obtener el nombre del período
function getPeriodName($period) {
    switch ($period) {
        case '7d': return 'Últimos 7 días';
        case '30d': return 'Últimos 30 días';
        case '90d': return 'Últimos 90 días';
        case '1y': return 'Último año';
        default: return 'Últimos 7 días';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - <?php echo htmlspecialchars($restaurant['name']); ?></title>
    
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
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/restaurante/assets/css/admin.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --danger-color: #dc3545;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
            --text-color: #212529;
            --text-muted: #6c757d;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-color);
            line-height: 1;
        }

        .stats-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 4px;
        }

        .period-selector {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.05);
            min-height: 400px;
        }

        .chart-container canvas {
            max-height: 350px !important;
            width: 100% !important;
        }

        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .chart-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .product-item:hover {
            background-color: #f8f9fa;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-rank {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            margin-right: 15px;
            font-size: 0.9rem;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 2px;
        }

        .product-stats {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .device-badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin: 2px;
        }

        .device-mobile { background: #e3f2fd; color: #1976d2; }
        .device-tablet { background: #f3e5f5; color: #7b1fa2; }
        .device-desktop { background: #e8f5e8; color: #388e3c; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
            color: var(--text-muted);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 0;
        }

        .stats-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .stats-summary h3 {
            margin-bottom: 10px;
            font-weight: 600;
        }

        .stats-summary p {
            margin-bottom: 0;
            opacity: 0.9;
        }

        .export-btn {
            background: var(--success-color);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .export-btn:hover {
            background: #218838;
            transform: translateY(-1px);
        }

        /* Responsive */
        @media (max-width: 767.98px) {
            .p-4 {
                margin-top: 13px;
                padding: 0 !important;
            }
            
            .col-md-3 {
                flex: 0 0 50% !important;
                max-width: 50% !important;
            }
            
            .stats-card {
                padding: 1rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            .stats-number {
                font-size: 1.5rem !important;
            }
            
            .stats-icon {
                font-size: 1.5rem !important;
                width: 50px !important;
                height: 50px !important;
            }

            .chart-container {
                padding: 15px !important;
                min-height: 300px;
            }

            .period-selector {
                padding: 15px !important;
            }

            .btn-group .btn {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
        }

        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
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
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">
                                <i class="fas fa-chart-line me-2"></i>
                                Estadísticas Avanzadas
                            </h2>
                            <p class="text-muted mb-0">Análisis detallado del rendimiento de tu menú digital</p>
                        </div>
                        <div>
                            
                        </div>
                    </div>

                    <!-- Información de debug -->
                    <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                        <div class="alert alert-info fade-in-up" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle me-3" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h5 class="alert-heading mb-1">Información de Debug</h5>
                                    <p class="mb-1"><strong>Restaurant ID:</strong> <?php echo $restaurant_id; ?></p>
                                    <p class="mb-1"><strong>Período:</strong> <?php echo $period; ?></p>
                                    <p class="mb-1"><strong>Tablas existen:</strong> <?php echo $tables_exist ? 'Sí' : 'No'; ?></p>
                                    <p class="mb-1"><strong>Fecha inicio:</strong> <?php echo $start_date ?? 'N/A'; ?></p>
                                    <p class="mb-0"><strong>Total de vistas:</strong> <?php echo $stats['general']['total_views'] ?? 0; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Resumen del período -->
                    <div class="stats-summary fade-in-up">
                        <h3><i class="fas fa-calendar-alt me-2"></i><?php echo getPeriodName($period); ?></h3>
                        <p>Período de análisis: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y'); ?></p>
                    </div>

                    <!-- Selector de período -->
                    <div class="period-selector fade-in-up">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Período de análisis</h5>
                            <div class="btn-group" role="group">
                                <a href="?period=7d" class="btn btn-outline-<?php echo getPeriodColor('7d'); ?> <?php echo $period === '7d' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-week me-1"></i>
                                    7 días
                                </a>
                                <a href="?period=30d" class="btn btn-outline-<?php echo getPeriodColor('30d'); ?> <?php echo $period === '30d' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    30 días
                                </a>
                                <a href="?period=90d" class="btn btn-outline-<?php echo getPeriodColor('90d'); ?> <?php echo $period === '90d' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar me-1"></i>
                                    90 días
                                </a>
                                <a href="?period=1y" class="btn btn-outline-<?php echo getPeriodColor('1y'); ?> <?php echo $period === '1y' ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-check me-1"></i>
                                    1 año
                                </a>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($stats['general']) || $stats['general']['total_views'] == 0): ?>
                        <!-- Estado vacío -->
                        <div class="empty-state fade-in-up">
                            <i class="fas fa-chart-bar"></i>
                            <h3>No hay datos de estadísticas</h3>
                            <p>Las estadísticas aparecerán aquí una vez que tengas visitas en tu menú digital.</p>
                            
                        </div>
                    <?php else: ?>
                        <!-- Estadísticas generales -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <div class="stats-card p-4 fade-in-up">
                                    <div class="d-flex align-items-center">
                                        <div class="stats-icon bg-primary me-3">
                                            <i class="fas fa-eye"></i>
                                        </div>
                                        <div>
                                            <div class="stats-number"><?php echo formatNumber($stats['general']['total_views']); ?></div>
                                            <div class="stats-label">Vistas totales</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-card p-4 fade-in-up">
                                    <div class="d-flex align-items-center">
                                        <div class="stats-icon bg-success me-3">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div>
                                            <div class="stats-number"><?php echo formatNumber($stats['general']['unique_visitors']); ?></div>
                                            <div class="stats-label">Visitantes únicos</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-card p-4 fade-in-up">
                                    <div class="d-flex align-items-center">
                                        <div class="stats-icon bg-warning me-3">
                                            <i class="fas fa-calendar-day"></i>
                                        </div>
                                        <div>
                                            <div class="stats-number"><?php echo $stats['general']['active_days']; ?></div>
                                            <div class="stats-label">Días activos</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-card p-4 fade-in-up">
                                    <div class="d-flex align-items-center">
                                        <div class="stats-icon bg-info me-3">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <div>
                                            <div class="stats-number"><?php echo $stats['general']['active_days'] > 0 ? round($stats['general']['total_views'] / $stats['general']['active_days']) : 0; ?></div>
                                            <div class="stats-label">Promedio diario</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Gráfico de visitas diarias -->
                            <div class="col-lg-8 mb-4">
                                <div class="chart-container">
                                    <div class="chart-title">
                                        <i class="fas fa-chart-area"></i>
                                        Visitas Diarias
                                    </div>
                                    <canvas id="dailyViewsChart"></canvas>
                                </div>
                            </div>

                            <!-- Productos más vistos -->
                            <div class="col-lg-4 mb-4">
                                <div class="chart-container">
                                    <div class="chart-title">
                                        <i class="fas fa-star"></i>
                                        Más vistos
                                    </div>
                                    <?php if (!empty($stats['top_products'])): ?>
                                        <?php foreach ($stats['top_products'] as $index => $product): ?>
                                            <div class="product-item">
                                                <div class="product-rank" style="background: <?php echo $index < 3 ? ['#ffd700', '#c0c0c0', '#cd7f32'][$index] : '#6c757d'; ?>">
                                                    <?php echo $index + 1; ?>
                                                </div>
                                                <div class="product-info">
                                                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                                    <div class="product-stats">
                                                        <?php echo $product['view_count']; ?> vistas • 
                                                        <?php echo $product['unique_viewers']; ?> visitantes únicos
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-box-open"></i>
                                            <p>No hay datos de productos</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                </div>
                            </div>

                        <div class="row">
                            <!-- Gráfico de actividad por hora -->
                            <div class="col-lg-6 mb-4">
                                <div class="chart-container">
                                    <div class="chart-title">
                                        <i class="fas fa-clock"></i>
                                        Actividad por Hora
                                    </div>
                                    <canvas id="hourlyActivityChart"></canvas>
                                </div>
                            </div>

                            <!-- Gráfico de dispositivos -->
                            <div class="col-lg-6 mb-4">
                                <div class="chart-container">
                                    <div class="chart-title">
                                        <i class="fas fa-mobile-alt"></i>
                                        Dispositivos
                                    </div>
                                    <canvas id="deviceChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Gráfico de categorías -->
                            <div class="col-lg-6 mb-4">
                                <div class="chart-container">
                                    <div class="chart-title">
                                        <i class="fas fa-tags"></i>
                                        Categorías más visitadas
                                    </div>
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>

                            <!-- Gráfico de tendencias -->
                            <div class="col-lg-6 mb-4">
                                <div class="chart-container">
                                    <div class="chart-title">
                                        <i class="fas fa-trending-up"></i>
                                        Tendencias de Crecimiento
                                    </div>
                                    <canvas id="trendChart"></canvas>
                                </div>
                            </div>
                        </div>

                        
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.js"></script>
    
    <script>
        // Esperar a que Chart.js se cargue completamente
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar que Chart.js está disponible
            if (typeof Chart === 'undefined') {
                console.error('Chart.js no está disponible, intentando cargar...');
                // Intentar cargar Chart.js de forma alternativa
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';
                script.onload = function() {
                    console.log('Chart.js cargado correctamente desde CDN alternativo');
                    initializeCharts();
                };
                script.onerror = function() {
                    console.error('Error cargando Chart.js desde CDN alternativo');
                    // Intentar con otro CDN
                    const script2 = document.createElement('script');
                    script2.src = 'https://unpkg.com/chart.js@4.4.0/dist/chart.umd.js';
                    script2.onload = function() {
                        console.log('Chart.js cargado correctamente desde unpkg');
                        initializeCharts();
                    };
                    script2.onerror = function() {
                        console.error('Error cargando Chart.js desde todos los CDN');
                        alert('Error: No se pudo cargar Chart.js. Los gráficos no se mostrarán.');
                        // Mostrar mensajes de error en los canvas
                        showErrorMessages();
                    };
                    document.head.appendChild(script2);
                };
                document.head.appendChild(script);
            } else {
                console.log('Chart.js ya está disponible');
                initializeCharts();
            }
        });

        function showErrorMessages() {
            const canvasIds = ['dailyViewsChart', 'hourlyActivityChart', 'deviceChart', 'categoryChart', 'trendChart'];
            canvasIds.forEach(id => {
                const canvas = document.getElementById(id);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#dc3545';
                    ctx.font = '14px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText('Error: Chart.js no disponible', canvas.width / 2, canvas.height / 2 - 10);
                    ctx.fillText('Recarga la página', canvas.width / 2, canvas.height / 2 + 10);
                }
            });
        }

        function initializeCharts() {
            // Datos para los gráficos
            const dailyViewsData = <?php echo json_encode($stats['daily_views'] ?? []); ?>;
            const deviceTypesData = <?php echo json_encode($stats['device_types'] ?? []); ?>;
            const topCategoriesData = <?php echo json_encode($stats['top_categories'] ?? []); ?>;
            const growthTrendData = <?php echo json_encode($stats['growth_trend'] ?? []); ?>;
            const hourlyActivityData = <?php echo json_encode($stats['hourly_activity'] ?? []); ?>;

            // Debug: Mostrar datos en consola
            console.log('=== DEBUG ESTADÍSTICAS ===');
            console.log('Restaurant ID:', <?php echo $restaurant_id; ?>);
            console.log('Período:', '<?php echo $period; ?>');
            console.log('Tablas existen:', <?php echo $tables_exist ? 'true' : 'false'; ?>);
            console.log('Datos de estadísticas:', {
                dailyViews: dailyViewsData,
                deviceTypes: deviceTypesData,
                topCategories: topCategoriesData,
                growthTrend: growthTrendData,
                hourlyActivity: hourlyActivityData
            });

            // Verificar si hay datos
            const hasData = dailyViewsData.length > 0 || deviceTypesData.length > 0 || 
                           topCategoriesData.length > 0 || growthTrendData.length > 0 || 
                           hourlyActivityData.length > 0;
            
            console.log('¿Hay datos para mostrar?', hasData);

            // Configuración global de Chart.js
            if (typeof Chart !== 'undefined') {
                Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
                Chart.defaults.color = '#6c757d';
            }

            // Función para verificar si Chart.js está cargado
            function checkChartJS() {
                if (typeof Chart === 'undefined') {
                    console.error('Chart.js no está cargado');
                    alert('Error: Chart.js no se pudo cargar. Por favor, recarga la página.');
                    return false;
                }
                return true;
            }

            // Función para crear gráfico con manejo de errores mejorado
            function createChart(canvasId, config) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) {
                    console.error(`Canvas con ID '${canvasId}' no encontrado`);
                    return null;
                }

                if (!checkChartJS()) {
                    return null;
                }

                try {
                    console.log(`Creando gráfico para ${canvasId}:`, config);
                    
                    // Verificar que los datos son válidos
                    if (config.data && config.data.datasets) {
                        config.data.datasets.forEach((dataset, index) => {
                            if (dataset.data && dataset.data.length === 0) {
                                console.warn(`Dataset ${index} en ${canvasId} no tiene datos`);
                            }
                        });
                    }
                    
                    const chart = new Chart(canvas, config);
                    console.log(`Gráfico ${canvasId} creado exitosamente`);
                    return chart;
                } catch (error) {
                    console.error(`Error creando gráfico ${canvasId}:`, error);
                    console.error('Configuración que causó el error:', config);
                    return null;
                }
            }

            // Función para verificar datos antes de crear gráficos
            function validateData(data, dataName) {
                if (!Array.isArray(data)) {
                    console.error(`${dataName} no es un array:`, data);
                    return false;
                }
                
                if (data.length === 0) {
                    console.log(`${dataName} está vacío`);
                    return false;
                }
                
                console.log(`${dataName} tiene ${data.length} elementos`);
                return true;
            }

            // Gráfico de visitas diarias
            if (validateData(dailyViewsData, 'dailyViewsData')) {
                const chart1 = createChart('dailyViewsChart', {
                    type: 'line',
                    data: {
                        labels: dailyViewsData.map(item => {
                            const date = new Date(item.date);
                            return date.toLocaleDateString('es-ES', { 
                                day: '2-digit', 
                                month: 'short' 
                            });
                        }),
                        datasets: [{
                            label: 'Vistas totales',
                            data: dailyViewsData.map(item => item.views),
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#007bff',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }, {
                            label: 'Visitantes únicos',
                            data: dailyViewsData.map(item => item.unique_visitors),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#28a745',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#007bff',
                                borderWidth: 1,
                                cornerRadius: 8,
                                callbacks: {
                                    title: function(context) {
                                        return `Fecha: ${context[0].label}`;
                                    },
                                    label: function(context) {
                                        return `${context.dataset.label}: ${context.parsed.y}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Fecha',
                                    font: { weight: 'bold' }
                                },
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Número de visitas',
                                    font: { weight: 'bold' }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            } else {
                console.log('No hay datos de visitas diarias para mostrar');
                // Mostrar mensaje en el canvas
                const canvas = document.getElementById('dailyViewsChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#6c757d';
                    ctx.font = '16px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText('No hay datos disponibles', canvas.width / 2, canvas.height / 2);
                }
            }

            // Gráfico de actividad por hora
            if (validateData(hourlyActivityData, 'hourlyActivityData')) {
                console.log('Datos de actividad por hora recibidos:', hourlyActivityData);
                
                // Crear array completo de 24 horas
                const completeHoursData = [];
                for (let i = 0; i < 24; i++) {
                    const hourData = hourlyActivityData.find(item => parseInt(item.hour) === i);
                    completeHoursData.push({
                        hour: i,
                        views: hourData ? parseInt(hourData.views) : 0,
                        unique_visitors: hourData ? parseInt(hourData.unique_visitors) : 0
                    });
                }
                
                console.log('Datos completos de 24 horas:', completeHoursData);

                const chartHourly = createChart('hourlyActivityChart', {
                    type: 'bar',
                    data: {
                        labels: completeHoursData.map(item => `${item.hour.toString().padStart(2, '0')}:00`),
                        datasets: [{
                            label: 'Vistas totales',
                            data: completeHoursData.map(item => item.views),
                            backgroundColor: 'rgba(255, 99, 132, 0.8)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            borderRadius: 6,
                            borderSkipped: false,
                        }, {
                            label: 'Visitantes únicos',
                            data: completeHoursData.map(item => item.unique_visitors),
                            backgroundColor: 'rgba(54, 162, 235, 0.8)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            borderRadius: 6,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#ff6384',
                                borderWidth: 1,
                                cornerRadius: 8,
                                callbacks: {
                                    title: function(context) {
                                        return `Hora: ${context[0].label}`;
                                    },
                                    label: function(context) {
                                        return `${context.dataset.label}: ${context.parsed.y}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Hora del día',
                                    font: { weight: 'bold' }
                                },
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Número de visitas',
                                    font: { weight: 'bold' }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            } else {
                console.log('No hay datos de actividad por hora para mostrar');
                // Mostrar mensaje en el canvas
                const canvas = document.getElementById('hourlyActivityChart');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#6c757d';
                    ctx.font = '16px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText('No hay datos de actividad por hora', canvas.width / 2, canvas.height / 2);
                }
            }

            // Gráfico de dispositivos
            if (validateData(deviceTypesData, 'deviceTypesData')) {
                const chart2 = createChart('deviceChart', {
                    type: 'doughnut',
                    data: {
                        labels: deviceTypesData.map(item => item.device_type),
                        datasets: [{
                            data: deviceTypesData.map(item => item.count),
                            backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545'],
                            borderWidth: 3,
                            borderColor: '#fff',
                            hoverBorderWidth: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return `${context.label}: ${context.parsed} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            } else {
                console.log('No hay datos de dispositivos para mostrar');
            }

            // Gráfico de categorías
            if (validateData(topCategoriesData, 'topCategoriesData')) {
                const chart3 = createChart('categoryChart', {
                    type: 'bar',
                    data: {
                        labels: topCategoriesData.map(item => item.category_name),
                        datasets: [{
                            label: 'Vistas',
                            data: topCategoriesData.map(item => item.view_count),
                            backgroundColor: 'rgba(23, 162, 184, 0.8)',
                            borderColor: '#17a2b8',
                            borderWidth: 2,
                            borderRadius: 6,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#17a2b8',
                                borderWidth: 1,
                                cornerRadius: 8
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        }
                    }
                });
            } else {
                console.log('No hay datos de categorías para mostrar');
            }

            // Gráfico de tendencias
            if (validateData(growthTrendData, 'growthTrendData')) {
                const chart4 = createChart('trendChart', {
                    type: 'line',
                    data: {
                        labels: growthTrendData.map(item => {
                            const date = new Date(item.date);
                            return date.toLocaleDateString('es-ES', { 
                                day: '2-digit', 
                                month: 'short' 
                            });
                        }),
                        datasets: [{
                            label: 'Vistas',
                            data: growthTrendData.map(item => item.views),
                            borderColor: '#6f42c1',
                            backgroundColor: 'rgba(111, 66, 193, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#6f42c1',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#6f42c1',
                                borderWidth: 1,
                                cornerRadius: 8
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        }
                    }
                });
            } else {
                console.log('No hay datos de tendencias para mostrar');
            }

            // Función para exportar datos
            function exportData() {
                const data = {
                    dailyViews: dailyViewsData,
                    deviceTypes: deviceTypesData,
                    topCategories: topCategoriesData,
                    growthTrend: growthTrendData,
                    hourlyActivity: hourlyActivityData
                };
                
                const dataStr = JSON.stringify(data, null, 2);
                const dataBlob = new Blob([dataStr], {type: 'application/json'});
                const url = URL.createObjectURL(dataBlob);
                
                const link = document.createElement('a');
                link.href = url;
                link.download = `estadisticas_${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }

            // Función para generar datos de prueba
            function generateTestData() {
                if (confirm('¿Deseas generar datos de prueba? Esto agregará registros de ejemplo a las estadísticas.')) {
                    window.location.href = 'quick_fix.php?generate=1';
                }
            }

            // Hacer las funciones disponibles globalmente
            window.exportData = exportData;
            window.generateTestData = generateTestData;

            // Animación de entrada y verificación final
            console.log('=== VERIFICACIÓN FINAL ===');
            
            const cards = document.querySelectorAll('.fade-in-up');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Verificar que todos los canvas existen
            const canvasIds = ['dailyViewsChart', 'hourlyActivityChart', 'deviceChart', 'categoryChart', 'trendChart'];
            canvasIds.forEach(id => {
                const canvas = document.getElementById(id);
                if (!canvas) {
                    console.error(`Canvas '${id}' no encontrado en el DOM`);
                } else {
                    console.log(`Canvas '${id}' encontrado`);
                }
            });
            
            // Mostrar resumen final
            console.log('=== RESUMEN ===');
            console.log('Gráficos creados:', document.querySelectorAll('canvas').length);
            console.log('¿Hay datos?', hasData);
            console.log('Estado de las tablas:', <?php echo $tables_exist ? 'true' : 'false'; ?>);
        }
    </script>
</body>
</html>
<?php
// Cerrar el buffer de salida
ob_end_flush();
?> 
