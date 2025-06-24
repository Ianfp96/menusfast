<?php
require_once __DIR__ . '/database.php';

/**
 * Sistema de tracking para estadísticas
 */

// Función para registrar una vista de página
function trackPageView($restaurant_id, $page_type, $page_id = null) {
    global $conn;
    
    try {
        // No registrar vistas de estadísticas para evitar que se cuenten en las estadísticas generales
        if ($page_type === 'stats') {
            return true;
        }
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $session_id = session_id() ?: 'session_' . uniqid();
        
        $stmt = $conn->prepare("
            INSERT INTO page_views (restaurant_id, page_type, page_id, ip_address, user_agent, referer, session_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$restaurant_id, $page_type, $page_id, $ip_address, $user_agent, $referer, $session_id]);
        
        // Actualizar estadísticas por hora
        updateHourlyActivity($restaurant_id);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error tracking page view: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Error general en trackPageView: " . $e->getMessage());
        return false;
    }
}

// Función para registrar una vista de producto
function trackProductView($restaurant_id, $product_id) {
    global $conn;
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $session_id = session_id() ?: 'session_' . uniqid();
        
        // Verificar si ya existe un registro para esta IP y producto
        $stmt = $conn->prepare("
            SELECT id, view_count FROM product_views 
            WHERE restaurant_id = ? AND product_id = ? AND ip_address = ?
        ");
        $stmt->execute([$restaurant_id, $product_id, $ip_address]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Actualizar contador de vistas
            $stmt = $conn->prepare("
                UPDATE product_views 
                SET view_count = view_count + 1, last_view_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$existing['id']]);
        } else {
            // Crear nuevo registro
            $stmt = $conn->prepare("
                INSERT INTO product_views (restaurant_id, product_id, ip_address, user_agent, session_id, view_count, first_view_at, last_view_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([$restaurant_id, $product_id, $ip_address, $user_agent, $session_id]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error tracking product view: " . $e->getMessage());
        return false;
    }
}

// Función para actualizar estadísticas por hora
function updateHourlyActivity($restaurant_id) {
    global $conn;
    
    try {
        $current_hour = (int)date('H');
        $current_date = date('Y-m-d');
        
        // Verificar si ya existe un registro para esta hora y fecha
        $stmt = $conn->prepare("
            SELECT id, page_views, unique_visitors FROM hourly_activity 
            WHERE restaurant_id = ? AND hour_of_day = ? AND activity_date = ?
        ");
        $stmt->execute([$restaurant_id, $current_hour, $current_date]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Actualizar contadores
            $stmt = $conn->prepare("
                UPDATE hourly_activity 
                SET page_views = page_views + 1, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$existing['id']]);
        } else {
            // Crear nuevo registro
            $stmt = $conn->prepare("
                INSERT INTO hourly_activity (restaurant_id, hour_of_day, activity_date, page_views, unique_visitors, created_at, updated_at)
                VALUES (?, ?, ?, 1, 1, NOW(), NOW())
            ");
            $stmt->execute([$restaurant_id, $current_hour, $current_date]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating hourly activity: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Error general en updateHourlyActivity: " . $e->getMessage());
        return false;
    }
}

// Función para obtener estadísticas generales
function getGeneralStats($restaurant_id, $period = '7d') {
    global $conn;
    
    try {
        // Calcular fecha de inicio según el período
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
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting general stats: " . $e->getMessage());
        return null;
    }
}

// Función para obtener visitas por día
function getDailyViews($restaurant_id, $days = 7) {
    global $conn;
    
    try {
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $stmt = $conn->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as views,
                COUNT(DISTINCT ip_address) as unique_visitors
            FROM page_views 
            WHERE restaurant_id = ? AND created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$restaurant_id, $start_date]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting daily views: " . $e->getMessage());
        return [];
    }
}

// Función para obtener productos más vistos
function getTopProducts($restaurant_id, $period = '7d', $limit = 10) {
    global $conn;
    
    try {
        // Calcular fecha de inicio según el período
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
            LIMIT ?
        ");
        $stmt->execute([$restaurant_id, $start_date, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting top products: " . $e->getMessage());
        return [];
    }
}

// Función para obtener actividad por hora
function getHourlyActivity($restaurant_id, $period = '7d') {
    global $conn;
    
    try {
        // Calcular fecha de inicio según el período
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
        
        $stmt = $conn->prepare("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as views,
                COUNT(DISTINCT ip_address) as unique_visitors
            FROM page_views 
            WHERE restaurant_id = ? AND created_at >= ?
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC
        ");
        $stmt->execute([$restaurant_id, $start_date]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting hourly activity: " . $e->getMessage());
        return [];
    }
}

// Función para obtener tipos de dispositivos
function getDeviceTypes($restaurant_id, $period = '7d') {
    global $conn;
    
    try {
        // Calcular fecha de inicio según el período
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
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting device types: " . $e->getMessage());
        return [];
    }
}

// Función para obtener tendencias de crecimiento
function getGrowthTrend($restaurant_id, $days = 30) {
    global $conn;
    
    try {
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $stmt = $conn->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as views
            FROM page_views 
            WHERE restaurant_id = ? AND created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$restaurant_id, $start_date]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting growth trend: " . $e->getMessage());
        return [];
    }
}

/**
 * Función para detectar tipo de dispositivo
 */
function getDeviceType() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/Mobile|Android|iPhone/', $user_agent)) {
        return 'Móvil';
    } elseif (preg_match('/Tablet|iPad/', $user_agent)) {
        return 'Tablet';
    } else {
        return 'Desktop';
    }
}

/**
 * Función para verificar si es un bot (para evitar tracking de bots)
 */
function isBot() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $bot_patterns = [
        'bot', 'crawler', 'spider', 'scraper', 'googlebot', 'bingbot', 
        'yandex', 'baiduspider', 'facebookexternalhit', 'twitterbot',
        'rogerbot', 'linkedinbot', 'embedly', 'quora link preview',
        'showyoubot', 'outbrain', 'pinterest', 'slackbot', 'vkShare',
        'W3C_Validator', 'validator', 'checker'
    ];
    
    foreach ($bot_patterns as $pattern) {
        if (stripos($user_agent, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Función principal para tracking automático
 */
function autoTrack($restaurant_id, $page_type, $page_id = null, $debug = false) {
    // Para pruebas, permitir deshabilitar la detección de bots
    $skip_bot_check = $debug || isset($_GET['skip_bot_check']);
    
    // No trackear bots (a menos que esté en modo debug)
    if (!$skip_bot_check && isBot()) {
        if ($debug) {
            error_log("DEBUG: Bot detectado, saltando tracking. User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'));
        }
        return false;
    }
    
    if ($debug) {
        error_log("DEBUG: Iniciando tracking para restaurante ID: $restaurant_id, tipo: $page_type, página ID: $page_id");
        error_log("DEBUG: IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A'));
        error_log("DEBUG: User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'));
    }
    
    // Registrar vista de página
    $page_result = trackPageView($restaurant_id, $page_type, $page_id);
    if ($debug) {
        error_log("DEBUG: Resultado trackPageView: " . ($page_result ? 'Éxito' : 'Error'));
    }
    
    // Registrar actividad por hora
    $hourly_result = updateHourlyActivity($restaurant_id);
    if ($debug) {
        error_log("DEBUG: Resultado updateHourlyActivity: " . ($hourly_result ? 'Éxito' : 'Error'));
    }
    
    // Si es una vista de producto, registrar también en product_views
    $product_result = true;
    if ($page_type === 'product' && $page_id) {
        $product_result = trackProductView($restaurant_id, $page_id);
        if ($debug) {
            error_log("DEBUG: Resultado trackProductView: " . ($product_result ? 'Éxito' : 'Error'));
        }
    }
    
    $overall_result = $page_result && $hourly_result && $product_result;
    
    if ($debug) {
        error_log("DEBUG: Resultado general del tracking: " . ($overall_result ? 'Éxito' : 'Error'));
    }
    
    return $overall_result;
}
?> 
