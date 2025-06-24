<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/cart_functions.php';
require_once __DIR__ . '/config/tracking.php';

// Establecer zona horaria de Chile
date_default_timezone_set('America/Santiago');

// Obtener el slug del restaurante de la URL
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = parse_url(BASE_URL, PHP_URL_PATH);
// Asegurar que $base_path no sea null
$base_path = $base_path ?: '';
$path = trim(str_replace($base_path, '', $request_uri), '/');

// Lógica de redirección mejorada
if (basename($_SERVER['SCRIPT_NAME']) === 'menu.php' && isset($_GET['slug'])) {
    $slug = $_GET['slug'];
    if ($path !== $slug) {
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . BASE_URL . "/" . $slug);
        exit;
    }
}

if (empty($path)) {
    header("HTTP/1.0 404 Not Found");
    die("Error: No se proporcionó un identificador de restaurante");
}

$slug = $path;

// Obtener información del restaurante
try {
    $stmt = $conn->prepare("
        SELECT r.*, 
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.monday.open')), '%H:%i') as monday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.monday.close')), '%H:%i') as monday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.tuesday.open')), '%H:%i') as tuesday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.tuesday.close')), '%H:%i') as tuesday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.wednesday.open')), '%H:%i') as wednesday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.wednesday.close')), '%H:%i') as wednesday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.thursday.open')), '%H:%i') as thursday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.thursday.close')), '%H:%i') as thursday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.friday.open')), '%H:%i') as friday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.friday.close')), '%H:%i') as friday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.saturday.open')), '%H:%i') as saturday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.saturday.close')), '%H:%i') as saturday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.sunday.open')), '%H:%i') as sunday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.sunday.close')), '%H:%i') as sunday_close
        FROM restaurants r 
        WHERE r.slug = ? AND r.is_active = 1
    ");

    $stmt->execute([$slug]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$restaurant) {
        // En lugar de lanzar una excepción, mostrar una página de error amigable
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Restaurante no disponible - Menú Digital</title>
            <link rel="stylesheet" href="restaurante/css/estilo_webmenu.css">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
            <style>
                .error-container {
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    padding: 20px;
                    background: var(--gray-50);
                }
                .error-content {
                    max-width: 500px;
                    padding: 40px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                .error-icon {
                    font-size: 64px;
                    color: var(--gray-400);
                    margin-bottom: 20px;
                }
                .error-title {
                    font-size: 24px;
                    font-weight: 600;
                    color: var(--gray-800);
                    margin-bottom: 12px;
                }
                .error-message {
                    color: var(--gray-600);
                    margin-bottom: 24px;
                    line-height: 1.5;
                }
                .back-button {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 24px;
                    background: var(--primary);
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 500;
                    transition: background-color 0.2s;
                }
                .back-button:hover {
                    background: var(--primary-dark);
                }
                .error-footer {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 20px;
                }
                .signature {
                    margin-top: 10px;
                    color: var(--gray-500);
                    font-size: 14px;
                    line-height: 1.4;
                }
                .signature-link {
                    color: var(--primary);
                    text-decoration: none;
                    font-weight: 500;
                    transition: color 0.2s;
                }
                .signature-link:hover {
                    color: var(--primary-dark);
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-content">
                    <i class="fas fa-store-slash error-icon"></i>
                    <h1 class="error-title">Restaurante no disponible</h1>
                    <p class="error-message">
                        Lo sentimos, este restaurante no está disponible en este momento. 
                        Esto puede deberse a que el restaurante está temporalmente inactivo.
                    </p>
                    <div class="error-footer">
                        
                        <p class="signature">
                            Atentamente,<br>
                            <a href="index.php" class="signature-link">Tumenufast</a>
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Registrar visita al menú
    trackPageView($restaurant['id'], 'menu');
    
    // Obtener categorías y productos
    error_log("Intentando obtener categorías para restaurante ID: " . $restaurant['id']);
    $stmt = $conn->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM products WHERE category_id = c.id AND is_active = 1) as product_count 
        FROM menu_categories c
        WHERE c.restaurant_id = ?
        ORDER BY c.sort_order ASC, c.name ASC
    ");
    
    if (!$stmt) {
        throw new PDOException("Error al preparar la consulta de categorías: " . print_r($conn->errorInfo(), true));
    }
    
    $stmt->execute([$restaurant['id']]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Categorías obtenidas: " . count($categories) . " - Datos: " . print_r($categories, true));
    
    $items_by_category = [];
    $has_products = false;
    
    foreach ($categories as $category) {
        $stmt = $conn->prepare("
            SELECT id, name, description, price, image, is_active, sort_order, is_featured 
            FROM products 
            WHERE restaurant_id = ? AND category_id = ? AND is_active = 1
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt->execute([$restaurant['id'], $category['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items_by_category[$category['id']] = $items;
        
        if (!empty($items)) {
            $has_products = true;
    }
}

} catch (PDOException $e) {
    error_log("Error en menu.php: " . $e->getMessage());
    die("Error al cargar los datos del restaurante: " . $e->getMessage());
}

// Función para verificar si el restaurante está abierto
function isRestaurantOpen($restaurant) {
    // Si el restaurante está marcado como cerrado globalmente
    if (!$restaurant['is_open']) {
        return false;
    }

    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $day = strtolower($now->format('l'));
    $current_time = $now->format('H:i');
    
    // Extraer los horarios del JSON directamente
    $opening_hours = json_decode($restaurant['opening_hours'], true);
    if (!$opening_hours || !isset($opening_hours[$day])) {
        return false;
    }
    
    $day_schedule = $opening_hours[$day];
    
    // Si el día está marcado como cerrado
    if (!$day_schedule['is_open']) {
        return false;
    }
    
    $open_time = $day_schedule['open_time'];
    $close_time = $day_schedule['close_time'];
    
    // Si no hay horarios definidos para este día
    if (empty($open_time) || empty($close_time)) {
        return false;
    }
    
    // Manejar casos especiales (ej: horario que cruza la medianoche)
    if ($close_time < $open_time) {
        // Si el horario cruza la medianoche, el restaurante está abierto si:
        // - La hora actual es mayor o igual a la hora de apertura, O
        // - La hora actual es menor o igual a la hora de cierre
        return $current_time >= $open_time || $current_time <= $close_time;
    }
    
    // Horario normal (dentro del mismo día)
    return $current_time >= $open_time && $current_time <= $close_time;
}

$is_open = isRestaurantOpen($restaurant);

function hex2rgba($hex, $alpha = 1) {
    $hex = str_replace('#', '', $hex);
    
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    
    return "rgba($r, $g, $b, $alpha)";
}

// Obtener o crear sesión del carrito
$cart = getCartSession($restaurant['id']);
$cart_items = getCartItems($cart['id']);
$cart_total = getCartTotal($cart['id']);
$cart_count = count($cart_items);

// Obtener productos destacados
try {
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN menu_categories c ON p.category_id = c.id
        WHERE p.restaurant_id = ? AND p.is_featured = 1 AND p.is_active = 1
        ORDER BY p.sort_order ASC, p.name ASC
    ");
    $stmt->execute([$restaurant['id']]);
    $featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener productos destacados: " . $e->getMessage());
    $featured_products = [];
}

// Verificar si se deben mostrar los productos destacados
$show_featured_section = !empty($featured_products) && ($restaurant['show_featured_products'] ?? 1);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($restaurant['name']); ?> - Menú Digital</title>
    
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
    
    <!-- Preconnect para mejorar performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- CSS optimizado -->
    <link rel="stylesheet" href="restaurante/css/estilo_webmenu.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
   
    <style>
        :root {
            --banner-gradient-start: <?php echo hex2rgba($restaurant['banner_color'] ?? '#8e8d91', 0.8); ?>;
            --banner-gradient-middle: <?php echo hex2rgba($restaurant['banner_color'] ?? '#8e8d91', 0.6); ?>;
            --web-color: <?php echo $restaurant['color_web'] ?? '#00b894'; ?>;
            --web-color-dark: <?php echo hex2rgba($restaurant['color_web'] ?? '#00b894', 0.8); ?>;
        }

        /* Aplicar color personalizado al logo */
        .app-logo {
            color: var(--web-color) !important;
        }

        .app-logo i {
            color: var(--web-color) !important;
        }

        .app-logo:hover {
            color: var(--web-color-dark) !important;
        }

        /* Aplicar color personalizado a los botones de agregar */
        .add-button {
            background: var(--web-color) !important;
            color: white !important;
        }

        .add-button:hover {
            background: var(--web-color-dark) !important;
            transform: translateY(-2px);
        }

        /* Aplicar color personalizado al botón de checkout */
        .checkout-button {
            background: var(--web-color) !important;
            color: white !important;
        }

        .checkout-button:hover {
            background: var(--web-color-dark) !important;
        }

        /* Aplicar color personalizado a los precios */
        .product-price {
            color: var(--web-color) !important;
        }

        .similar-product-price {
            color: var(--web-color) !important;
        }

        .cart-total span:last-child {
            color: var(--web-color) !important;
        }

        .checkout-total span:last-child {
            color: var(--web-color) !important;
        }

        /* Aplicar color personalizado al footer */
        .restaurant-footer .footer-section h3 {
            color: var(--web-color) !important;
        }

        .restaurant-footer .footer-section h3::after {
            background: var(--web-color) !important;
        }

        .restaurant-footer .footer-info i {
            color: var(--web-color) !important;
        }

        .restaurant-footer .social-icon {
            background: var(--web-color) !important;
        }

        .restaurant-footer .social-link:hover .social-icon {
            background: var(--web-color-dark) !important;
        }

        .restaurant-footer .location-button.primary {
            background: var(--web-color) !important;
            color: white !important;
        }

        .restaurant-footer .location-button.primary:hover {
            background: var(--web-color-dark) !important;
        }

        .restaurant-footer .service-item i {
            color: var(--web-color) !important;
        }

        /* Estilos adicionales para el footer */
        .restaurant-footer .footer-section h4 {
            color: var(--web-color) !important;
        }

        .restaurant-footer .footer-section h4::after {
            background: var(--web-color) !important;
        }

        .restaurant-footer .schedule-item.today .badge {
            background: var(--web-color) !important;
        }

        .restaurant-footer .location-button.secondary {
            border-color: var(--web-color) !important;
            color: var(--web-color) !important;
        }

        .restaurant-footer .location-button.secondary:hover {
            background: var(--web-color) !important;
            color: white !important;
        }

        .swiper-pagination {
            position: relative; 
            text-align: center;
            transition: .3s opacity;
            transform: translate3d(0, 0, 0);
            z-index: 10;
            margin-bottom: 0px;
        }

        .swiper{
                margin-left: auto;
                margin-right: auto;
                margin-bottom: 0px;
                position: relative;
                overflow: hidden;
                list-style: none;
                padding: 0;
                z-index: 1;
                display: block;
            }

        /* Estilos para el campo de cupón */
        .coupon-section {
            padding: 15px;
            border-top: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .coupon-input-container {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .coupon-input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: border-color 0.2s;
        }

        .coupon-input:focus {
            outline: none;
            border-color: var(--web-color);
            box-shadow: 0 0 0 2px rgba(0, 184, 148, 0.1);
        }

        .apply-coupon-btn {
            padding: 10px 12px;
            background: var(--web-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
        }

        .apply-coupon-btn:hover {
            background: var(--web-color-dark);
        }

        .apply-coupon-btn:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
        }

        .coupon-message {
            font-size: 12px;
           
            
        }

        .coupon-message.success {
            color: #10b981;
        }

        .coupon-message.error {
            color: #ef4444;
        }

        .coupon-message.info {
            color: #3b82f6;
        }

        .applied-coupon {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .coupon-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .coupon-info i {
            color: #10b981;
            font-size: 16px;
        }

        .coupon-discount {
            color: #10b981;
            font-weight: 600;
            font-size: 14px;
        }

        .remove-coupon-btn {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: color 0.2s;
        }

        .remove-coupon-btn:hover {
            color: #ef4444;
        }

        /* Estilos para el descuento en el total */
        .cart-total-discount {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-200);
            font-size: 14px;
        }

        .cart-total-discount .discount-label {
            color: var(--gray-600);
        }

        .cart-total-discount .discount-amount {
            color: #10b981;
            font-weight: 600;
        }

        .cart-total-final {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            font-weight: 600;
            font-size: 16px;
            border-top: 2px solid var(--gray-200);
        }
    </style>

</head>


<body>
    <!-- Header fijo mejorado -->
    <header class="app-header" id="appHeader">
        <div class="header-content">
            <a href="<?php echo BASE_URL; ?>" class="app-logo">
                <i class="fas fa-utensils"></i>
                <?php echo htmlspecialchars($restaurant['name']); ?>
            </a>
            <div class="header-actions">
                <div class="status-badge <?php echo $is_open ? 'status-open' : 'status-closed'; ?>">
                    <i class="fas fa-circle"></i>
                    <?php echo $is_open ? 'Abierto' : 'Cerrado'; ?>
                </div>
                <a href="<?php echo BASE_URL . '/' . $restaurant['slug'] . '/cart'; ?>" class="cart-button" id="cartButton">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count" style="display: <?php echo $cart_count > 0 ? 'flex' : 'none'; ?>">
                        <?php echo $cart_count; ?>
                    </span>
                </a>
            </div>
        </div>
    </header>

    <!-- Hero section del restaurante -->
    <section class="restaurant-hero">
        <?php if ($restaurant['banner']): ?>
            <img src="<?php echo BASE_URL . '/uploads/' . $restaurant['banner']; ?>" 
                 alt="<?php echo htmlspecialchars($restaurant['name']); ?>" 
                 class="hero-background">
        <?php endif; ?>
        
        <div class="hero-overlay">
            <div class="restaurant-info">
                <div class="restaurant-header-content">
                    <div class="restaurant-title-container">
                        <?php if ($restaurant['logo']): ?>
                            <img src="<?php echo BASE_URL . '/uploads/' . $restaurant['logo']; ?>" 
                                 alt="<?php echo htmlspecialchars($restaurant['name']); ?>" 
                                 class="restaurant-logo">
                        <?php endif; ?>
                        <div class="restaurant-details">
                            <h1><?php echo htmlspecialchars($restaurant['name']); ?></h1>
                        </div>
                    </div>
                    <p class="restaurant-description"><?php echo htmlspecialchars($restaurant['description']); ?></p>
                    <?php
                    // Obtener el día actual en zona horaria de Chile
                    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
                    $current_day = strtolower($now->format('l'));
                    $open_time = $restaurant["{$current_day}_open"];
                    $close_time = $restaurant["{$current_day}_close"];
                    if ($open_time && $close_time):
                    ?>
                    <div class="today-schedule">
                        <i class="fas fa-clock"></i>
                        <span>Horario de hoy: <?php echo htmlspecialchars($open_time); ?> - <?php echo htmlspecialchars($close_time); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="restaurant-meta">
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <?php
                            $opening_hours = json_decode($restaurant['opening_hours'], true);
                            $current_day = strtolower(date('l'));
                            $day_schedule = $opening_hours[$current_day] ?? null;
                            
                            if ($day_schedule && $day_schedule['is_open'] && !empty($day_schedule['open_time']) && !empty($day_schedule['close_time'])) {
                                echo '<span class="' . ($is_open ? 'text-success' : 'text-danger') . '">';
                                echo $is_open ? 'Abierto ahora' : 'Cerrado';
                                echo '</span>';
                            } else {
                                echo '<span class="text-danger">Cerrado hoy</span>';
                            }
                            ?>
                        </div>
                        <?php if ($restaurant['whatsapp_url']): ?>
                        <div class="meta-item whatsapp-meta desktop-only">
                            <i class="fab fa-whatsapp"></i>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $restaurant['whatsapp_url']); ?>" 
                               target="_blank" 
                               style="color: white; text-decoration: none;">
                                <?php echo preg_replace('/[^0-9]/', '', $restaurant['whatsapp_url']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if ($restaurant['has_delivery']): ?>
                        <div class="meta-item">
                            <i class="fas fa-motorcycle"></i>
                            <span>Delivery disponible</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($restaurant['has_physical_store']): ?>
                        <div class="meta-item">
                            <i class="fas fa-store"></i>
                            <span>Tienda física</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Carrito flotante -->
    <div class="floating-cart" id="floatingCart">
        <div class="floating-cart-header">
            <h3>Tu pedido</h3>
            <button class="close-cart" onclick="toggleCart()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="floating-cart-items">
            <?php if (empty($cart_items)): ?>
                <div class="empty-cart-message">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Tu carrito está vacío</p>
                </div>
            <?php else: ?>
                <?php foreach ($cart_items as $item): ?>
                    <div class="floating-cart-item" data-item-id="<?php echo $item['id']; ?>">
                        <?php if ($item['image']): ?>
                            <img src="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($item['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                 class="item-image"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="item-image" style="background: var(--gray-100); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="color: var(--gray-400); font-size: 1.5rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="item-info">
                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                            <?php if ($item['selected_options']): ?>
                                <?php 
                                $options = json_decode($item['selected_options'], true);
                                if (!empty($options)):
                                ?>
                                    <div class="item-options">
                                        <?php foreach ($options as $option): ?>
                                            <small>* <?php echo htmlspecialchars($option['name']); ?></small>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="item-price"><?php echo formatCurrency($item['unit_price'], $restaurant['id']); ?></div>
                        </div>
                        <div class="item-actions">
                            <div class="quantity-selector">
                                <button onclick="updateCartQuantity(<?php echo $item['id']; ?>, -1)">-</button>
                                <span><?php echo $item['quantity']; ?></span>
                                <button onclick="updateCartQuantity(<?php echo $item['id']; ?>, 1)">+</button>
                            </div>
                            <button class="remove-item" onclick="removeCartItem(<?php echo $item['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
         
        <!-- Campo de cupón -->
        <div class="coupon-section">
            <div class="coupon-input-container">
                <input type="text" 
                       id="couponCode" 
                       class="coupon-input" 
                       placeholder="¿Tienes un cupón?"
                       maxlength="20">
                <button type="button" 
                        id="applyCouponBtn" 
                        class="apply-coupon-btn">
                    <i class="fas fa-tag"></i>
                </button>
            </div>
            <div id="couponMessage" class="coupon-message"></div>
            <div id="appliedCoupon" class="applied-coupon" style="display: none;">
                <div class="coupon-info">
                    <i class="fas fa-check-circle"></i>
                    <span id="couponName"></span>
                    <span id="couponDiscount" class="coupon-discount"></span>
                </div>
                <button type="button" id="removeCouponBtn" class="remove-coupon-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
       
       
            <div class="floating-cart-footer">
                <div class="cart-total">
                    <span>Total:</span>
                    <span>$<?php echo number_format($cart_total, 0); ?></span>
                </div>
                <button onclick="openCheckoutModal()" class="checkout-button">
                    Proceder al pago
                </button>
            </div>
        
    </div>

    <!-- Modal de Checkout -->
    <div class="checkout-modal" id="checkoutModal">
        <div class="checkout-modal-content">
            <div class="checkout-modal-header">
                <h3>Finalizar pedido</h3>
                <button class="close-modal" onclick="closeCheckoutModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="checkout-modal-body">
                <?php if ($restaurant['enable_whatsapp_order']): ?>
                <form id="checkoutForm" onsubmit="handleCheckout(event)">
                    <div class="form-group">
                        <label for="customer_name">Nombre</label>
                        <input type="text" id="customer_name" name="customer_name" required>
                    </div>
                    <div class="form-group">
                        <label for="customer_phone">Teléfono</label>
                        <input type="tel" id="customer_phone" name="customer_phone" placeholder="Ej: +56912345678" maxlength="13" required>
                    </div>
                    <div class="form-group">
                        <label>Método de entrega</label>
                        <div class="delivery-options">
                            <label class="delivery-option">
                                <input type="radio" name="delivery_method" value="pickup" checked>
                                <span>Retiro en local</span>
                            </label>
                            <?php if ($restaurant['has_delivery']): ?>
                            <label class="delivery-option">
                                <input type="radio" name="delivery_method" value="delivery">
                                <span>Delivery</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group delivery-address" style="display: none;">
                        <label for="customer_address">Dirección de entrega</label>
                        <textarea id="customer_address" name="customer_address"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notas adicionales</label>
                        <textarea id="notes" name="notes" placeholder="Ej: Sin cebolla, extra picante, etc."></textarea>
                    </div>
                    
                    
                    
                    <div class="checkout-summary">
                        <h4>Resumen del pedido</h4>
                        <div id="checkoutItems"></div>
                        <div class="checkout-total">
                            <span>Total:</span>
                            <span>$<?php echo number_format($cart_total, 0); ?></span>
                        </div>
                    </div>
                    <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['id']; ?>">
                    <input type="hidden" name="whatsapp_number" value="<?php echo htmlspecialchars($restaurant['whatsapp_order_number']); ?>">
                    <input type="hidden" name="whatsapp_message" value="<?php echo htmlspecialchars($restaurant['whatsapp_order_message']); ?>">
                    <button type="submit" class="submit-order-button">
                        <i class="fab fa-whatsapp"></i> Enviar pedido por WhatsApp
                    </button>
                    <div style="text-align: center; margin-top: 10px;">
                        <small>Se abrirá WhatsApp con tu pedido listo para enviar</small>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-info-circle text-info" style="font-size: 4rem;"></i>
                    <h3 class="mt-4">Pedidos por WhatsApp Desactivados</h3>
                    <p class="text-muted">En este momento, los pedidos por WhatsApp no están disponibles.</p>
                    <p class="text-muted">Por favor, contacta al restaurante directamente para realizar tu pedido.</p>
                    <?php if ($restaurant['whatsapp_url']): ?>
                    <div class="mt-4">
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $restaurant['whatsapp_url']); ?>" 
                           target="_blank" 
                           class="btn btn-success btn-lg">
                            <i class="fab fa-whatsapp"></i> Contactar por WhatsApp
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Overlay para el carrito -->
    <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
    
    <!-- Contenedor principal -->
    <main class="main-container">
        <?php if ($show_featured_section): ?>
            <section class="featured-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-star"></i>
                        Productos Destacados
                    </h2>
                    <p class="section-description">Nuestras mejores opciones seleccionadas especialmente para ti</p>
                </div>
                
                <?php if (count($featured_products) > 2): ?>
                    <!-- Slider para móviles y escritorio -->
                    <div class="featured-slider-container">
                        <div class="swiper featured-swiper">
                            <div class="swiper-wrapper">
                                <?php foreach ($featured_products as $item): ?>
                                    <div class="swiper-slide">
                                        <article class="product-card featured" 
                                                 data-product-id="<?= htmlspecialchars($item['id']) ?>"
                                                 onclick="window.location.href='<?php echo BASE_URL; ?>/product.php?id=<?php echo $item['id']; ?>'">
                                            <?php if ($item['image']): ?>
                                                <img src="<?= BASE_URL . '/uploads/' . htmlspecialchars($item['image']); ?>" 
                                                     alt="<?= htmlspecialchars($item['name']); ?>" 
                                                     class="product-image"
                                                     loading="lazy">
                                            <?php endif; ?>
                                            
                                            <div class="product-content">
                                                <div class="featured-badge">
                                                    <i class="fas fa-star"></i>
                                                    Destacado
                                                </div>
                                                <h3 class="product-name"><?= htmlspecialchars($item['name']) ?></h3>
                                                <?php if ($item['description']): ?>
                                                   
                                                <?php endif; ?>
                                                <div class="product-footer">
                                                    <div class="product-price"><?php echo formatCurrency($item['price'], $restaurant['id']); ?></div>
                                                    <button class="add-button" onclick="event.stopPropagation(); window.location.href='<?php echo BASE_URL; ?>/product.php?id=<?php echo $item['id']; ?>'">
                                                        <i class="fas fa-plus"></i>
                                                        Agregar
                                                    </button>
                                                </div>
                                            </div>
                                        </article>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Navegación -->
                            <div class="swiper-button-prev featured-nav-button"></div>
                            <div class="swiper-button-next featured-nav-button"></div>
                            <!-- Paginación -->
                            <div class="swiper-pagination featured-pagination"></div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Grid normal para 2 o menos productos -->
                    <div class="products-grid featured-grid">
                        <?php foreach ($featured_products as $item): ?>
                            <article class="product-card featured" 
                                     data-product-id="<?= htmlspecialchars($item['id']) ?>"
                                     onclick="window.location.href='<?php echo BASE_URL; ?>/product.php?id=<?php echo $item['id']; ?>'">
                                <?php if ($item['image']): ?>
                                    <img src="<?= BASE_URL . '/uploads/' . htmlspecialchars($item['image']); ?>" 
                                         alt="<?= htmlspecialchars($item['name']); ?>" 
                                         class="product-image"
                                         loading="lazy">
                                <?php endif; ?>
                                
                                <div class="product-content">
                                    <div class="featured-badge">
                                        <i class="fas fa-star"></i>
                                        Destacado
                                    </div>
                                    <h3 class="product-name"><?= htmlspecialchars($item['name']) ?></h3>
                                    <?php if ($item['description']): ?>
                                        <p class="product-description"><?= htmlspecialchars($item['description']) ?></p>
                                    <?php endif; ?>
                                    <div class="product-footer">
                                        <div class="product-price"><?php echo formatCurrency($item['price'], $restaurant['id']); ?></div>
                                        <button class="add-button" onclick="event.stopPropagation(); window.location.href='<?php echo BASE_URL; ?>/product.php?id=<?php echo $item['id']; ?>'">
                                            <i class="fas fa-plus"></i>
                                            Agregar
                                        </button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!$has_products): ?>
            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <h3>Menú en preparación</h3>
                <p>El restaurante está actualizando su menú. Vuelve pronto para ver las deliciosas opciones disponibles.</p>
            </div>
        <?php else: ?>

            


            <!-- Barra de búsqueda -->
            <section class="search-section">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           class="search-input" 
                           id="productSearch" 
                           placeholder="Buscar platillos, bebidas..." 
                           autocomplete="off">
                    <button class="search-clear" id="searchClear" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="searchResults" class="search-results"></div>
            </section>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('productSearch');
                const searchClear = document.getElementById('searchClear');
                const searchResults = document.getElementById('searchResults');
                const categorySections = document.querySelectorAll('.category-section');

                function performSearch(query) {
                    query = query.toLowerCase().trim();
                    
                    if (query === '') {
                        clearSearch();
                        return;
                    }
                    
                    searchClear.style.display = 'block';
                    let hasResults = false;
                    let resultsHTML = '<div class="products-grid">';
                    let resultsCount = 0;

                    // Buscar en productos destacados y regulares
                    const allProducts = [
                        ...document.querySelectorAll('.featured-swiper .product-card'),
                        ...document.querySelectorAll('.products-grid .product-card')
                    ];

                    allProducts.forEach(card => {
                        const productName = card.querySelector('.product-name').textContent.toLowerCase();
                        const productDesc = card.querySelector('.product-description')?.textContent.toLowerCase() || '';
                        const categoryName = card.closest('.category-section')?.querySelector('.category-title')?.textContent.toLowerCase() || '';
                        
                        if (productName.includes(query) || productDesc.includes(query) || categoryName.includes(query)) {
                            hasResults = true;
                            resultsCount++;
                            const clonedCard = card.cloneNode(true);
                            
                            // Resaltar el texto que coincide con la búsqueda
                            const nameElement = clonedCard.querySelector('.product-name');
                            const nameText = nameElement.textContent;
                            const highlightedName = nameText.replace(new RegExp(`(${query})`, 'gi'), '<mark>$1</mark>');
                            nameElement.innerHTML = highlightedName;
                            
                            resultsHTML += clonedCard.outerHTML;
                        }
                    });

                    resultsHTML += '</div>';

                    if (hasResults) {
                        searchResults.innerHTML = `
                            <div class="search-header">
                                <p class="search-count">Se encontraron ${resultsCount} resultados</p>
                            </div>
                            ${resultsHTML}
                        `;
                        categorySections.forEach(section => section.style.display = 'none');
                        
                        // Re-bind event listeners para los cards clonados
                        bindProductEvents();
                    } else {
                        searchResults.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <h3>Sin resultados</h3>
                                <p>No encontramos productos que coincidan con "${query}". Intenta con otros términos.</p>
                            </div>
                        `;
                        categorySections.forEach(section => section.style.display = 'none');
                    }
                }

                function clearSearch() {
                    searchResults.innerHTML = '';
                    searchClear.style.display = 'none';
                    categorySections.forEach(section => section.style.display = 'block');
                }

                function bindProductEvents() {
                    document.querySelectorAll('.add-button').forEach(button => {
                        button.addEventListener('click', function(e) {
                            e.stopPropagation();
                            const productId = this.closest('.product-card').dataset.productId;
                            window.location.href = `${window.BASE_URL}/product.php?id=${productId}`;
                        });
                    });
                }

                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const query = this.value;
                    
                    if (query.length > 0) {
                        searchResults.innerHTML = `
                            <div class="search-loading">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Buscando...</p>
                            </div>
                        `;
                    }
                    
                    searchTimeout = setTimeout(() => {
                        performSearch(query);
                    }, 150);
                });

                searchClear.addEventListener('click', function() {
                    searchInput.value = '';
                    clearSearch();
                    searchInput.focus();
                });

                // Inicializar eventos de productos
                bindProductEvents();
            });
            </script>

            <!-- Navegación de categorías -->
            <nav class="category-nav">
                <div class="category-container">
                    <div class="swiper category-swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($categories as $category): ?>
                                <?php if ($category['is_active']): ?>
                                    <div class="swiper-slide">
                                        <a href="/categoria.php?id=<?= $category['id'] ?>" 
                                           class="category-link"
                                           title="Ver todos los productos de <?= htmlspecialchars($category['name']) ?>">
                                            <?php if ($category['image']): ?>
                                                <img src="/uploads/<?= htmlspecialchars($category['image']) ?>" 
                                                     alt="<?= htmlspecialchars($category['name']) ?>"
                                                     class="category-icon"
                                                     loading="lazy"
                                                     onerror="this.onerror=null; this.src='/assets/img/no-image.png';">
                                            <?php else: ?>
                                                <i class="fas fa-tag"></i>
                                            <?php endif; ?>
                                            <span class="category-name"><?= htmlspecialchars($category['name']) ?></span>
                                            <?php if ($category['product_count'] > 0): ?>
                                                <span class="product-count">(<?= $category['product_count'] ?>)</span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <!-- Botones de navegación -->
                        <div class="swiper-button-prev category-nav-button"></div>
                        <div class="swiper-button-next category-nav-button"></div>
                    </div>
                </div>
            </nav>

            <!-- Contenido principal -->
            <section class="content-section">

                <!-- Resultados de búsqueda -->
            <div id="searchResults"></div>

                <!-- Categorías y productos -->
            <?php foreach ($categories as $category): ?>
                <?php if (!empty($items_by_category[$category['id']])): ?>
                <section id="category-<?php echo $category['id']; ?>" class="category-section">
                    <div class="category-header">
                        <div class="category-info">
                            <h2 class="category-title"><?php echo htmlspecialchars($category['name']); ?></h2>
                            <?php if ($category['description']): ?>
                                <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div> <br>
                    
                    <div class="products-grid">
                        <?php foreach ($items_by_category[$category['id']] as $item): ?>
                            <?php
                            // Verificar si el producto tiene opciones
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM product_menu_options WHERE product_id = ?");
                            $stmt->execute([$item['id']]);
                            $has_options = $stmt->fetchColumn() > 0;
                            ?>
                            <article class="product-card <?= $item['is_featured'] ? 'featured' : '' ?>" 
                                     data-product-id="<?= htmlspecialchars($item['id']) ?>"
                                     data-has-options="<?= $has_options ? 'true' : 'false' ?>"
                                     onclick="window.location.href='<?php echo BASE_URL; ?>/product.php?id=<?php echo $item['id']; ?>'">
                                <?php if ($item['image']): ?>
                                    <img src="<?= BASE_URL . '/uploads/' . htmlspecialchars($item['image']); ?>" 
                                         alt="<?= htmlspecialchars($item['name']); ?>" 
                                         class="product-image"
                                         loading="lazy">
                                <?php endif; ?>
                                
                                <div class="product-content">
                                    <h3 class="product-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <?php if ($item['description']): ?>
                                      
                                    <?php endif; ?>
                                    <?php if ($has_options): ?>
                                        <div class="product-options mb-2">
                                            <small class="text-muted" style="font-size: 9px;">
                                                <i class="fas fa-cog"></i> 
                                                Opciones disponibles
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    <div class="product-footer">
                                        <div class="product-price"><?php echo formatCurrency($item['price'], $restaurant['id']); ?></div>
                                        <button class="add-button" onclick="event.stopPropagation(); window.location.href='<?php echo BASE_URL; ?>/product.php?id=<?php echo $item['id']; ?>'">
                                            <i class="fas fa-plus"></i>
                                            Agregar
                                        </button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            <?php endforeach; ?>
            </section>
            <?php endif; ?>
    </main>
    
    <!-- Footer mejorado -->
    <footer class="restaurant-footer">
        <div class="footer-content">
            <div class="footer-grid">
                <!-- Información de contacto -->
                    <div class="footer-section">
                    <h3>Contacto</h3>
                        
                        <?php if ($restaurant['phone']): ?>
                        <div class="footer-info">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($restaurant['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($restaurant['address']): ?>
                        <div class="footer-info">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($restaurant['address']); ?></span>
                        </div>
                        <?php endif; ?>
                    
                    
                    <?php if ($restaurant['whatsapp_url']): ?>
                    <div class="footer-info">
                        <i class="fab fa-whatsapp"></i>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $restaurant['whatsapp_url']); ?>" 
                           target="_blank" 
                           style="color: var(--gray-300); text-decoration: none;">
                            <?php echo preg_replace('/[^0-9]/', '', $restaurant['whatsapp_url']); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($restaurant['has_delivery'] || $restaurant['has_physical_store']): ?>
                    <h4>Servicios Disponibles</h4>
                    <div class="services-grid">
                        <?php if ($restaurant['has_delivery']): ?>
                        <div class="service-item">
                            <i class="fas fa-motorcycle"></i>
                            <div class="service-info">
                                <h5>Delivery</h5>
                                <p>Servicio a domicilio disponible</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($restaurant['has_physical_store']): ?>
                        <div class="service-item">
                            <i class="fas fa-store"></i>
                            <div class="service-info">
                                <h5>Tienda Física</h5>
                                <p>Visítanos en nuestro local</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($restaurant['facebook_url'] || $restaurant['instagram_url'] || $restaurant['tiktok_url']): ?>
                    <h4>Síguenos</h4>
                    <div class="social-links">
                        <?php if ($restaurant['facebook_url']): ?>
                        <a href="<?php echo htmlspecialchars($restaurant['facebook_url']); ?>" 
                           target="_blank" 
                           class="social-link">
                            <div class="social-icon">
                                <i class="fab fa-facebook-f"></i>
                            </div>
                            <span class="social-name">Facebook</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($restaurant['instagram_url']): ?>
                        <a href="<?php echo htmlspecialchars($restaurant['instagram_url']); ?>" 
                           target="_blank" 
                           class="social-link">
                            <div class="social-icon">
                                <i class="fab fa-instagram"></i>
                            </div>
                            <span class="social-name">Instagram</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($restaurant['tiktok_url']): ?>
                        <a href="<?php echo htmlspecialchars($restaurant['tiktok_url']); ?>" 
                           target="_blank" 
                           class="social-link">
                            <div class="social-icon">
                                <i class="fab fa-tiktok"></i>
                            </div>
                            <span class="social-name">TikTok</span>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Horarios -->
                    <div class="footer-section">
                    <h3>Horarios de Atención</h3>
                        
                        <?php
                        $days = [
                            'monday' => 'Lunes',
                            'tuesday' => 'Martes',
                            'wednesday' => 'Miércoles',
                            'thursday' => 'Jueves',
                            'friday' => 'Viernes',
                            'saturday' => 'Sábado',
                            'sunday' => 'Domingo'
                        ];
                        
                        $opening_hours = json_decode($restaurant['opening_hours'], true);
                        $now = new DateTime('now', new DateTimeZone('America/Santiago'));
                        $current_day = strtolower($now->format('l'));
                        
                        foreach ($days as $day_key => $day_name):
                            $day_schedule = $opening_hours[$day_key] ?? null;
                            $is_today = $day_key === $current_day;
                            $is_open = $day_schedule && $day_schedule['is_open'] && 
                                      !empty($day_schedule['open_time']) && 
                                      !empty($day_schedule['close_time']);
                        ?>
                            <div class="schedule-item <?= $is_today ? 'today' : '' ?>">
                                <span class="schedule-day">
                                    <?= $day_name ?>
                                    <?php if ($is_today): ?>
                                        <span class="badge bg-primary">Hoy</span>
                                    <?php endif; ?>
                                </span>
                                <span class="schedule-time">
                                    <?php if ($is_open): ?>
                                        <?= htmlspecialchars($day_schedule['open_time']) ?> - 
                                        <?= htmlspecialchars($day_schedule['close_time']) ?>
                                    <?php else: ?>
                                        <span class="text-danger">Cerrado</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                </div>
                
                <!-- Ubicación -->
                <?php if ($restaurant['address']): ?>
                    <div class="footer-section">
                    <h3>Ubicación</h3>
                    <p style="color: var(--gray-300); margin-bottom: 16px;">
                        Encuéntranos fácilmente y disfruta de nuestros platillos.
                    </p>
                    
                    <div class="map-container">
                        <div id="footer-map"></div>
                    </div>
                    
                    <div class="location-info">
                        <div class="location-actions">
                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($restaurant['address']); ?>" 
                               target="_blank" 
                               class="location-button primary">
                                <i class="fas fa-directions"></i>
                                Cómo llegar
                            </a>
                            <a href="https://www.google.com/maps?q=<?php echo urlencode($restaurant['address']); ?>" 
                               target="_blank" 
                               class="location-button secondary">
                                <i class="fas fa-external-link-alt"></i>
                                Ver en Maps
                            </a>
                </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <!-- Notificación del carrito -->
    <div class="cart-notification" id="cartNotification">
        <i class="fas fa-check-circle"></i>
        <div class="cart-notification-content">
            <div class="cart-notification-title">Producto agregado</div>
            <div class="cart-notification-message">El producto se ha agregado al carrito correctamente</div>
        </div>
    </div>

    <!-- Scripts optimizados -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- Definir variables globales -->
    <script>
        window.BASE_URL = '<?php echo BASE_URL; ?>';
        window.CURRENT_RESTAURANT_ID = <?php echo $restaurant['id']; ?>;
    </script>
    <script src="<?php echo BASE_URL; ?>/assets/js/cart.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elementos del DOM
        const appHeader = document.getElementById('appHeader');
        const searchInput = document.getElementById('productSearch');
        const searchClear = document.getElementById('searchClear');
        const searchResults = document.getElementById('searchResults');
        const categorySections = document.querySelectorAll('.category-section');
        const productCards = document.querySelectorAll('.product-card');
        
        // Inicialización del carrusel de categorías
        let categorySwiper = null;
        
        function initCategorySwiper() {
            const swiperContainer = document.querySelector('.category-swiper');
            if (!swiperContainer) return;
            
            // Destruir instancia existente si hay una
            if (categorySwiper) {
                try {
                    categorySwiper.destroy(true, true);
                } catch (error) {
                    console.warn('Error al destruir Swiper:', error);
                }
            }
            
            try {
                categorySwiper = new Swiper('.category-swiper', {
                    slidesPerView: 'auto',
                    spaceBetween: 8,
                    freeMode: {
                        enabled: true,
                        momentum: true,
                        momentumRatio: 0.5,
                        momentumVelocityRatio: 0.5,
                        sticky: true
                    },
                    grabCursor: true,
                    watchSlidesProgress: true,
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                        disabledClass: 'swiper-button-disabled',
                        lockClass: 'swiper-button-lock',
                        hiddenClass: 'swiper-button-hidden'
                    },
                    touchRatio: 1,
                    touchAngle: 45,
                    resistance: true,
                    resistanceRatio: 0.5,
                    preventInteractionOnTransition: true,
                    threshold: 5,
                    allowTouchMove: true,
                    touchStartPreventDefault: false,
                    touchMoveStopPropagation: true,
                    breakpoints: {
                        0: {
                            slidesPerView: 'auto',
                            spaceBetween: 8,
                            freeMode: {
                                enabled: true,
                                momentum: true,
                                sticky: true
                            }
                        },
                        768: {
                            slidesPerView: 'auto',
                            spaceBetween: 12,
                            freeMode: {
                                enabled: false
                            }
                        }
                    },
                    on: {
                        init: function() {
                            if (this && typeof this.update === 'function') {
                                this.update();
                                setTimeout(() => {
                                    updateNavigationVisibility(this);
                                    if (this.navigation && typeof this.navigation.update === 'function') {
                                        this.navigation.update();
                                    }
                                }, 100);
                            }
                        },
                        resize: function() {
                            if (this && typeof this.update === 'function') {
                                this.update();
                                setTimeout(() => {
                                    updateNavigationVisibility(this);
                                    if (this.navigation && typeof this.navigation.update === 'function') {
                                        this.navigation.update();
                                    }
                                }, 100);
                            }
                        },
                        slideChange: function() {
                            updateNavigationVisibility(this);
                        },
                        touchStart: function() {
                            if (this.navigation && typeof this.navigation.update === 'function') {
                                this.navigation.update();
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error al inicializar Swiper:', error);
            }
        }

        // Inicializar Swiper
        initCategorySwiper();

        // Función para actualizar la visibilidad de la navegación
        function updateNavigationVisibility(swiper) {
            if (!swiper || !swiper.containerEl || !swiper.wrapperEl) return;
            
            try {
                const isMobile = window.innerWidth < 768;
                const hasMultipleSlides = swiper.slides && swiper.slides.length > 1;
                const isOverflowing = swiper.wrapperEl.scrollWidth > swiper.containerEl.clientWidth;
                
                const shouldShowNav = isMobile ? hasMultipleSlides : isOverflowing;
                
                if (!swiper.navigation || !swiper.navigation.nextEl || !swiper.navigation.nextEl.parentElement) return;
                
                const navButtons = swiper.navigation.nextEl.parentElement.querySelectorAll('.category-nav-button');
                navButtons.forEach(button => {
                    if (shouldShowNav) {
                        button.style.display = 'flex';
                        button.style.opacity = '1';
                        button.style.pointerEvents = 'auto';
                    } else {
                        button.style.display = 'none';
                        button.style.opacity = '0';
                        button.style.pointerEvents = 'none';
                    }
                });
                
                if (shouldShowNav && swiper.navigation && typeof swiper.navigation.update === 'function') {
                    swiper.navigation.update();
                }
            } catch (error) {
                console.warn('Error al actualizar la visibilidad de navegación:', error);
            }
        }

        // Actualizar navegación al cambiar el tamaño de la ventana
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (categorySwiper) {
                    categorySwiper.update();
                    updateNavigationVisibility(categorySwiper);
                }
            }, 250);
        });

        // Asegurar que los botones de navegación sean clickeables
        document.querySelectorAll('.category-nav-button').forEach(button => {
            button.addEventListener('touchstart', function(e) {
                e.stopPropagation();
            }, { passive: true });
            
            button.addEventListener('click', function(e) {
                e.stopPropagation();
            }, { passive: true });
        });

        // Header scroll effect
        let lastScrollY = window.scrollY;
        
        window.addEventListener('scroll', () => {
            const currentScrollY = window.scrollY;
            
            if (currentScrollY > 100) {
                appHeader.classList.add('scrolled');
            } else {
                appHeader.classList.remove('scrolled');
            }
            
            lastScrollY = currentScrollY;
        });
        
        // Búsqueda mejorada
        let searchTimeout;
        
        function performSearch(query) {
            query = query.toLowerCase().trim();
            
            if (query === '') {
                clearSearch();
                return;
            }
            
            searchClear.style.display = 'block';

            let hasResults = false;
            let resultsHTML = '<div class="products-grid">';

            productCards.forEach(card => {
                const productName = card.querySelector('.product-name').textContent.toLowerCase();
                const productDesc = card.querySelector('.product-description').textContent.toLowerCase();
                
                if (productName.includes(query) || productDesc.includes(query)) {
                    hasResults = true;
                    const clonedCard = card.cloneNode(true);
                    resultsHTML += clonedCard.outerHTML;
                }
            });

            resultsHTML += '</div>';

            if (hasResults) {
                searchResults.innerHTML = resultsHTML;
                categorySections.forEach(section => section.style.display = 'none');
                
                // Re-bind event listeners for cloned cards
                bindProductEvents();
            } else {
                searchResults.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>Sin resultados</h3>
                        <p>No encontramos productos que coincidan con "${query}". Intenta con otros términos.</p>
                    </div>
                `;
                categorySections.forEach(section => section.style.display = 'none');
            }
        }

        function clearSearch() {
            searchResults.innerHTML = '';
            searchClear.style.display = 'none';
            categorySections.forEach(section => section.style.display = 'block');
            updateActiveCategory();
        }
        
        // Event listeners para búsqueda
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(this.value);
            }, 300);
        });

        searchClear.addEventListener('click', function() {
            searchInput.value = '';
            clearSearch();
            searchInput.focus();
        });
        
        // Actualizar categoría activa en scroll
        function updateActiveCategory() {
            const scrollPosition = window.scrollY + 200;
            
            categorySections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionBottom = sectionTop + section.offsetHeight;
                
                if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                    const sectionId = section.getAttribute('id');
                    const correspondingLink = document.querySelector(`a[href="#${sectionId}"]`);
                    if (correspondingLink) {
                        correspondingLink.classList.add('active');
                    }
                }
            });
        }
        
        window.addEventListener('scroll', updateActiveCategory);
        updateActiveCategory();
        
        // Función para agregar al carrito
        window.addToCart = function(productId) {
            window.location.href = `${BASE_URL}/product.php?id=${productId}`;
        };
        
        // Bind events para productos
        function bindProductEvents() {
            document.querySelectorAll('.add-button').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const productId = this.closest('.product-card').dataset.productId;
                    addToCart(productId);
                });
            });
        }
        
        bindProductEvents();
        
        // Inicializar mapa si existe dirección
        <?php if (!empty($restaurant['address'])): ?>
        try {
            const footerMap = L.map('footer-map', {
                zoomControl: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false,
                dragging: false,
                tap: false
            }).setView([0, 0], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(footerMap);
            
            // Usar nuestro endpoint PHP para geocodificar
            fetch(`${BASE_URL}/api/geocode.php?address=<?php echo urlencode($restaurant['address']); ?>`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success && data.lat && data.lon) {
                        footerMap.setView([data.lat, data.lon], 16);
                        
                        const customIcon = L.divIcon({
                            html: '<i class="fas fa-map-marker-alt" style="color: var(--primary); font-size: 24px;"></i>',
                            iconSize: [24, 24],
                            className: 'custom-marker'
                        });
                        
                        L.marker([data.lat, data.lon], { icon: customIcon })
                            .addTo(footerMap)
                            .bindPopup(`
                                <div style="text-align: center; padding: 8px;">
                                    <strong><?php echo htmlspecialchars($restaurant['name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($restaurant['address']); ?></small>
                                </div>
                            `);
                    } else {
                        throw new Error('No se pudo obtener la ubicación');
                    }
                })
                .catch(error => {
                    console.warn('Error al cargar el mapa:', error);
                    // Mostrar un mensaje amigable en el contenedor del mapa
                    document.getElementById('footer-map').innerHTML = `
                        <div style="text-align: center; padding: 20px; color: var(--gray-600);">
                            <i class="fas fa-map-marked-alt" style="font-size: 24px; margin-bottom: 10px;"></i>
                            <p>No se pudo cargar el mapa en este momento.</p>
                            <small>Puedes ver la ubicación en Google Maps usando los botones de abajo.</small>
                        </div>
                    `;
                });
        } catch (error) {
            console.warn('Error al inicializar el mapa:', error);
        }
        <?php endif; ?>
        
        // Lazy loading para imágenes
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src || img.src;
                        img.classList.remove('skeleton');
                        observer.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[loading="lazy"]').forEach(img => {
                img.classList.add('skeleton');
                imageObserver.observe(img);
            });
        }
        
        // Performance: Debounce scroll events
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Aplicar debounce a eventos de scroll
        window.addEventListener('scroll', debounce(updateActiveCategory, 100));

        // Inicialización del slider de productos destacados
        const featuredProducts = <?php echo count($featured_products); ?>;
        
        if (featuredProducts > 2) {
            const featuredSwiper = new Swiper('.featured-swiper', {
                slidesPerView: 2,
                spaceBetween: 10,
                centeredSlides: false,
                loop: true,
                grabCursor: true,
                navigation: {
                    nextEl: '.featured-nav-button.swiper-button-next',
                    prevEl: '.featured-nav-button.swiper-button-prev',
                },
                pagination: {
                    el: '.featured-pagination',
                    clickable: true,
                },
                breakpoints: {
                    320: {
                        slidesPerView: 2,
                        spaceBetween: 10,
                    },
                    480: {
                        slidesPerView: 2,
                        spaceBetween: 15,
                    },
                    768: {
                        slidesPerView: 3,
                        spaceBetween: 20,
                    },
                    1024: {
                        slidesPerView: 4,
                        spaceBetween: 20,
                    }
                },
                on: {
                    init: function() {
                        updateFeaturedNavigation(this);
                    },
                    resize: function() {
                        updateFeaturedNavigation(this);
                    }
                }
            });

            function updateFeaturedNavigation(swiper) {
                const shouldShowNav = featuredProducts > 2;
                const isDesktop = window.innerWidth >= 1024;
                const shouldShowNavDesktop = featuredProducts > 4;
                
                const navButtons = document.querySelectorAll('.featured-nav-button');
                navButtons.forEach(button => {
                    if ((isDesktop && shouldShowNavDesktop) || (!isDesktop && shouldShowNav)) {
                        button.style.display = 'flex';
                    } else {
                        button.style.display = 'none';
                    }
                });
            }

            // Actualizar navegación al cambiar el tamaño de la ventana
            window.addEventListener('resize', debounce(() => {
                if (featuredSwiper) {
                    featuredSwiper.update();
                    updateFeaturedNavigation(featuredSwiper);
                }
            }, 250));
        }
    });
    </script>

    <script>
    // Función para alternar el carrito
    function toggleCart() {
        const cart = document.getElementById('floatingCart');
        const overlay = document.getElementById('cartOverlay');
        cart.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    // Event listener para el botón del carrito
    document.getElementById('cartButton').addEventListener('click', function(e) {
        e.preventDefault();
        toggleCart();
    });
    </script>

    <script>
    // Función para mostrar notificación
    function showCartNotification(success, message) {
        const notification = document.getElementById('cartNotification');
        notification.className = 'cart-notification ' + (success ? 'success' : 'error');
        notification.querySelector('i').className = success ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
        notification.querySelector('.cart-notification-title').textContent = success ? 'Producto agregado' : 'Error';
        notification.querySelector('.cart-notification-message').textContent = message;
        
        notification.classList.add('show');
        
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    }

    // Actualizar la función updateCartUI en cart.js
    const originalUpdateCartUI = window.updateCartUI;
    window.updateCartUI = function() {
        originalUpdateCartUI();
        
        // Actualizar el contador del carrito en el header
        const cartCount = document.querySelector('.cart-count');
        const cart = getCartFromCookies();
        const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
        
        if (cartCount) {
            if (totalItems > 0) {
                cartCount.textContent = totalItems;
                cartCount.style.display = 'flex';
            } else {
                cartCount.style.display = 'none';
            }
        }
    };
    </script>

    <script>
    function openCheckoutModal() {
        document.getElementById('checkoutModal').classList.add('active');
        updateCheckoutItems();
    }

    function closeCheckoutModal() {
        document.getElementById('checkoutModal').classList.remove('active');
    }

    function updateCheckoutItems() {
        const cartItems = getCartFromCookies();
        const checkoutItems = document.getElementById('checkoutItems');
        let total = 0;
        
        checkoutItems.innerHTML = cartItems.map(item => {
            // Calcular el precio total del item incluyendo opciones
            let itemTotal = item.price * item.quantity;
            if (item.selected_options) {
                const options = JSON.parse(item.selected_options);
                options.forEach(opt => {
                    if (opt.price) {
                        itemTotal += (parseFloat(opt.price) * item.quantity);
                    }
                });
            }
            total += itemTotal;

            return `
                <div class="checkout-item">
                    <div class="checkout-item-info">
                        <span class="checkout-item-name">${item.name} x${item.quantity}</span>
                        <span class="checkout-item-price">$${itemTotal.toLocaleString()}</span>
                    </div>
                    ${item.selected_options ? `
                        <div class="checkout-item-options">
                            ${JSON.parse(item.selected_options).map(opt => `<small>* ${opt.name}</small>`).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');

        // Obtener información del cupón aplicado
        const currentCoupon = window.couponManager ? window.couponManager.getCurrentCoupon() : null;
        const finalTotal = currentCoupon ? currentCoupon.final_total : total;

        // Actualizar el total en el checkout
        const totalElement = document.querySelector('.checkout-total span:last-child');
        if (totalElement) {
            totalElement.textContent = `$${finalTotal.toLocaleString()}`;
        }

        // Mostrar descuento si hay cupón aplicado
        const checkoutSummary = document.querySelector('.checkout-summary');
        if (checkoutSummary && currentCoupon) {
            // Buscar si ya existe una línea de descuento
            let discountLine = checkoutSummary.querySelector('.checkout-discount');
            if (!discountLine) {
                // Crear línea de descuento
                discountLine = document.createElement('div');
                discountLine.className = 'checkout-discount';
                discountLine.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--gray-200); font-size: 14px;';
                
                // Insertar antes del total
                const totalContainer = checkoutSummary.querySelector('.checkout-total');
                if (totalContainer) {
                    totalContainer.parentNode.insertBefore(discountLine, totalContainer);
                }
            }
            
            discountLine.innerHTML = `
                <span style="color: var(--gray-600);">Descuento (${currentCoupon.code}):</span>
                <span style="color: #10b981; font-weight: 600;">-$${currentCoupon.discount_amount.toLocaleString()}</span>
            `;
        } else if (checkoutSummary) {
            // Remover línea de descuento si no hay cupón
            const discountLine = checkoutSummary.querySelector('.checkout-discount');
            if (discountLine) {
                discountLine.remove();
            }
        }
    }

    // Manejar cambio en método de entrega
    document.querySelectorAll('input[name="delivery_method"]').forEach(input => {
        input.addEventListener('change', function() {
            const addressField = document.querySelector('.delivery-address');
            addressField.style.display = this.value === 'delivery' ? 'block' : 'none';
            if (this.value === 'delivery') {
                document.getElementById('customer_address').required = true;
            } else {
                document.getElementById('customer_address').required = false;
            }
        });
    });

    async function handleCheckout(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        // Agregar los items del carrito al formData
        const cartItems = getCartFromCookies();
        formData.append('cart_items', JSON.stringify(cartItems));
        
        // Agregar información del cupón si está aplicado
        if (window.couponManager && window.couponManager.getCurrentCoupon()) {
            const currentCoupon = window.couponManager.getCurrentCoupon();
            formData.append('coupon_id', currentCoupon.id);
            formData.append('coupon_code', currentCoupon.code);
            formData.append('discount_amount', currentCoupon.discount_amount);
        }
        
        try {
            // Mostrar mensaje de "Procesando pedido..."
            const submitButton = form.querySelector('.submit-order-button');
            const originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando pedido...';
            submitButton.disabled = true;
            
            const response = await fetch('<?php echo BASE_URL; ?>/api/process_order.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Abrir WhatsApp con el mensaje generado
                window.open(data.whatsapp_url, '_blank');
                
                // Mostrar mensaje de éxito en el modal
                const modalBody = document.querySelector('.checkout-modal-body');
                modalBody.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h3 class="mt-4">¡Pedido Enviado!</h3>
                        <p class="text-muted">Tu pedido ha sido enviado correctamente por WhatsApp.</p>
                        <p class="text-muted">Número de pedido: #${data.order_id}</p>
                        <div class="mt-4">
                            <p class="text-muted mb-3">Serás redirigido en unos segundos...</p>
                            
                        </div>
                    </div>
                `;
                
                // Iniciar temporizador para recargar la página
                setTimeout(() => {
                    finalizarPedido();
                }, 5000); // 5 segundos
                
            } else {
                // Restaurar el botón original
                submitButton.innerHTML = originalButtonText;
                submitButton.disabled = false;
                
                showCartNotification(false, data.message || 'Error al procesar el pedido');
            }
        } catch (error) {
            console.error('Error:', error);
            
            // Restaurar el botón original
            const submitButton = form.querySelector('.submit-order-button');
            submitButton.innerHTML = originalButtonText;
            submitButton.disabled = false;
            
            showCartNotification(false, 'Error al procesar el pedido');
        }
    }

    // Función para finalizar el pedido y limpiar todo
    function finalizarPedido() {
        // Limpiar el carrito en la base de datos
        fetch('<?php echo BASE_URL; ?>/api/cart_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear&cart_id=<?php echo $cart["id"]; ?>'
        }).finally(() => {
            // Limpiar las cookies del carrito
            document.cookie = 'cart=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            
            // Limpiar el cupón aplicado
            if (window.couponManager) {
                window.couponManager.clearCoupon();
            }
            
            // Cerrar el modal
            closeCheckoutModal();
            
            // Cerrar el carrito flotante si está abierto
            const floatingCart = document.getElementById('floatingCart');
            const cartOverlay = document.getElementById('cartOverlay');
            if (floatingCart.classList.contains('active')) {
                floatingCart.classList.remove('active');
                cartOverlay.classList.remove('active');
            }
            
            // Mostrar mensaje de éxito
            showCartNotification(true, '¡Pedido enviado correctamente!');
            
            // Recargar la página después de un breve delay
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        });
    }
    </script>

    <script>
    // Gestor de cupones
    class CouponManager {
        constructor(restaurantId) {
            this.restaurantId = restaurantId;
            this.currentCoupon = null;
            this.setupEventListeners();
            this.loadAppliedCoupon();
        }

        setupEventListeners() {
            // Carrito flotante
            const applyBtn = document.getElementById('applyCouponBtn');
            const couponInput = document.getElementById('couponCode');
            const removeBtn = document.getElementById('removeCouponBtn');

            if (applyBtn) {
                applyBtn.addEventListener('click', () => this.applyCoupon('floating'));
            }

            if (couponInput) {
                couponInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.applyCoupon('floating');
                    }
                });
            }

            if (removeBtn) {
                removeBtn.addEventListener('click', () => this.removeCoupon('floating'));
            }

            // Modal de checkout
            const checkoutApplyBtn = document.getElementById('checkout_apply_coupon_btn');
            const checkoutCouponInput = document.getElementById('checkout_coupon_code');
            const checkoutRemoveBtn = document.getElementById('checkout_remove_coupon_btn');

            if (checkoutApplyBtn) {
                checkoutApplyBtn.addEventListener('click', () => this.applyCoupon('checkout'));
            }

            if (checkoutCouponInput) {
                checkoutCouponInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.applyCoupon('checkout');
                    }
                });
            }

            if (checkoutRemoveBtn) {
                checkoutRemoveBtn.addEventListener('click', () => this.removeCoupon('checkout'));
            }
        }

        async applyCoupon(context = 'floating') {
            const inputId = context === 'checkout' ? 'checkout_coupon_code' : 'couponCode';
            const messageId = context === 'checkout' ? 'checkout_coupon_message' : 'couponMessage';
            const applyBtnId = context === 'checkout' ? 'checkout_apply_coupon_btn' : 'applyCouponBtn';
            
            const couponCode = document.getElementById(inputId)?.value?.trim();
            const orderTotal = this.getOrderTotal();

            if (!couponCode) {
                this.showMessage('Por favor ingresa un código de cupón', 'error', messageId);
                return;
            }

            if (orderTotal <= 0) {
                this.showMessage('El carrito está vacío', 'error', messageId);
                return;
            }

            try {
                this.showLoading(true, applyBtnId);

                const response = await fetch(`${window.BASE_URL}/api/validate_coupon.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        code: couponCode,
                        restaurant_id: this.restaurantId,
                        order_total: orderTotal
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.currentCoupon = data.coupon;
                    this.displayCouponInfo(data.coupon, context);
                    this.updateOrderTotal(data.coupon.final_total);
                    this.showMessage('¡Cupón aplicado exitosamente!', 'success', messageId);
                    
                    // Limpiar campo
                    document.getElementById(inputId).value = '';
                    
                    // Guardar en localStorage
                    this.saveCouponToStorage(data.coupon);
                } else {
                    this.showMessage(data.message, 'error', messageId);
                }
            } catch (error) {
                console.error('Error al aplicar cupón:', error);
                this.showMessage('Error al aplicar el cupón', 'error', messageId);
            } finally {
                this.showLoading(false, applyBtnId);
            }
        }

        removeCoupon(context = 'floating') {
            this.currentCoupon = null;
            this.hideCouponInfo(context);
            this.updateOrderTotal(this.getOrderTotal());
            this.removeCouponFromStorage();
            this.showMessage('Cupón removido', 'info', context === 'checkout' ? 'checkout_coupon_message' : 'couponMessage');
        }

        displayCouponInfo(coupon, context = 'floating') {
            const couponInfoId = context === 'checkout' ? 'checkout_applied_coupon' : 'appliedCoupon';
            const couponNameId = context === 'checkout' ? 'checkout_coupon_name' : 'couponName';
            const couponDiscountId = context === 'checkout' ? 'checkout_coupon_discount' : 'couponDiscount';
            
            const couponInfo = document.getElementById(couponInfoId);
            const couponName = document.getElementById(couponNameId);
            const couponDiscount = document.getElementById(couponDiscountId);

            if (couponInfo && couponName && couponDiscount) {
                couponName.textContent = coupon.name;
                couponDiscount.textContent = `-$${coupon.discount_amount.toLocaleString()}`;
                couponInfo.style.display = 'flex';
            }
        }

        hideCouponInfo(context = 'floating') {
            const couponInfoId = context === 'checkout' ? 'checkout_applied_coupon' : 'appliedCoupon';
            const couponInfo = document.getElementById(couponInfoId);
            if (couponInfo) {
                couponInfo.style.display = 'none';
            }
        }

        showMessage(message, type, messageId) {
            const messageElement = document.getElementById(messageId);
            if (messageElement) {
                messageElement.textContent = message;
                messageElement.className = `coupon-message ${type}`;
                
                // Limpiar mensaje después de 5 segundos
                setTimeout(() => {
                    messageElement.textContent = '';
                    messageElement.className = 'coupon-message';
                }, 5000);
            }
        }

        showLoading(loading, buttonId) {
            const button = document.getElementById(buttonId);
            if (button) {
                if (loading) {
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                } else {
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-tag"></i>';
                }
            }
        }

        getOrderTotal() {
            const cart = getCartFromCookies();
            return cart.reduce((total, item) => {
                let itemTotal = item.price * item.quantity;
                if (item.selected_options) {
                    const options = JSON.parse(item.selected_options);
                    options.forEach(opt => {
                        if (opt.price) {
                            itemTotal += (parseFloat(opt.price) * item.quantity);
                        }
                    });
                }
                return total + itemTotal;
            }, 0);
        }

        updateOrderTotal(finalTotal) {
            // Actualizar total en el carrito flotante
            const cartTotal = document.querySelector('.floating-cart-footer .cart-total span:last-child');
            if (cartTotal) {
                cartTotal.textContent = `$${finalTotal.toLocaleString()}`;
            }

            // Actualizar total en el checkout
            const checkoutTotal = document.querySelector('.checkout-total span:last-child');
            if (checkoutTotal) {
                checkoutTotal.textContent = `$${finalTotal.toLocaleString()}`;
            }

            // Actualizar resumen del checkout
            this.updateCheckoutSummary();
        }

        updateCheckoutSummary() {
            const cartItems = getCartFromCookies();
            const checkoutItems = document.getElementById('checkoutItems');
            let subtotal = 0;
            
            checkoutItems.innerHTML = cartItems.map(item => {
                let itemTotal = item.price * item.quantity;
                if (item.selected_options) {
                    const options = JSON.parse(item.selected_options);
                    options.forEach(opt => {
                        if (opt.price) {
                            itemTotal += (parseFloat(opt.price) * item.quantity);
                        }
                    });
                }
                subtotal += itemTotal;

                return `
                    <div class="checkout-item">
                        <div class="checkout-item-info">
                            <span class="checkout-item-name">${item.name} x${item.quantity}</span>
                            <span class="checkout-item-price">$${itemTotal.toLocaleString()}</span>
                        </div>
                        ${item.selected_options ? `
                            <div class="checkout-item-options">
                                ${JSON.parse(item.selected_options).map(opt => `<small>* ${opt.name}</small>`).join('')}
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');

            // Actualizar total con descuento
            const finalTotal = this.currentCoupon ? this.currentCoupon.final_total : subtotal;
            const totalElement = document.querySelector('.checkout-total span:last-child');
            if (totalElement) {
                totalElement.textContent = `$${finalTotal.toLocaleString()}`;
            }
        }

        saveCouponToStorage(coupon) {
            try {
                localStorage.setItem(`coupon_${this.restaurantId}`, JSON.stringify(coupon));
            } catch (error) {
                console.error('Error al guardar cupón:', error);
            }
        }

        removeCouponFromStorage() {
            try {
                localStorage.removeItem(`coupon_${this.restaurantId}`);
            } catch (error) {
                console.error('Error al remover cupón:', error);
            }
        }

        loadAppliedCoupon() {
            try {
                const savedCoupon = localStorage.getItem(`coupon_${this.restaurantId}`);
                if (savedCoupon) {
                    this.currentCoupon = JSON.parse(savedCoupon);
                    this.displayCouponInfo(this.currentCoupon, 'floating');
                    this.displayCouponInfo(this.currentCoupon, 'checkout');
                    this.updateOrderTotal(this.currentCoupon.final_total);
                }
            } catch (error) {
                console.error('Error al cargar cupón guardado:', error);
            }
        }

        getCurrentCoupon() {
            return this.currentCoupon;
        }

        clearCoupon() {
            this.currentCoupon = null;
            this.hideCouponInfo('floating');
            this.hideCouponInfo('checkout');
            this.removeCouponFromStorage();
        }
    }

    // Inicializar el gestor de cupones cuando se carga la página
    document.addEventListener('DOMContentLoaded', function() {
        if (window.CURRENT_RESTAURANT_ID) {
            window.couponManager = new CouponManager(window.CURRENT_RESTAURANT_ID);
        }
    });

    // Función global para aplicar cupón
    window.applyCoupon = function(context = 'floating') {
        if (window.couponManager) {
            window.couponManager.applyCoupon(context);
        }
    };

    // Función global para remover cupón
    window.removeCoupon = function(context = 'floating') {
        if (window.couponManager) {
            window.couponManager.removeCoupon(context);
        }
    };
    </script>
</body>
</html> 
