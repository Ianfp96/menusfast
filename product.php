<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/tracking.php';

// Establecer zona horaria de Chile
date_default_timezone_set('America/Santiago');

// Obtener el ID del producto de la URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    header("HTTP/1.0 404 Not Found");
    die("Error: No se proporcionó un ID de producto válido");
}

try {
    // Obtener información del producto
    $stmt = $conn->prepare("
        SELECT 
            p.id, p.name, p.description, p.price, p.image, p.is_featured, p.category_id,
            c.name as category_name, 
            r.id as restaurant_id, r.name as restaurant_name, r.slug as restaurant_slug,
            COALESCE(
                (
                    SELECT CONCAT(
                        '{\"options\":[',
                        GROUP_CONCAT(
                            CONCAT(
                                '{',
                                '\"id\":', mo.id, ',',
                                '\"name\":\"', REPLACE(mo.name, '\"', '\\\"'), '\",',
                                '\"type\":\"', mo.type, '\",',
                                '\"description\":', IF(mo.description IS NULL, 'null', CONCAT('\"', REPLACE(mo.description, '\"', '\\\"'), '\"')), ',',
                                '\"required\":', mo.is_required, ',',
                                '\"show_price\":', mo.show_price, ',',
                                '\"values\":',
                                (
                                    SELECT CONCAT(
                                        '[',
                                        GROUP_CONCAT(
                                            CONCAT(
                                                '{',
                                                '\"id\":', mov.id, ',',
                                                '\"name\":\"', REPLACE(mov.name, '\"', '\\\"'), '\",',
                                                '\"price\":', mov.price, ',',
                                                '\"sort_order\":', mov.sort_order,
                                                '}'
                                            )
                                            ORDER BY mov.sort_order ASC
                                        ),
                                        ']'
                                    )
                                    FROM product_menu_option_values mov
                                    WHERE mov.option_id = mo.id
                                ),
                                '}'
                            )
                        ),
                        ']}'
                    )
                    FROM product_menu_options mo
                    WHERE mo.product_id = p.id
                ),
                '{\"options\":[]}'
            ) as menu_options
        FROM products p
        JOIN menu_categories c ON p.category_id = c.id
        JOIN restaurants r ON p.restaurant_id = r.id
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->execute([$product_id]);
    $product_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product_data) {
        throw new PDOException("No se encontró el producto");
    }

    // Registrar visita al producto para estadísticas
    trackPageView($product_data['restaurant_id'], 'product', $product_id);
    trackProductView($product_data['restaurant_id'], $product_id);

    // Obtener información del restaurante
    $stmt = $conn->prepare("
        SELECT r.*, 
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.monday.open_time')), '%H:%i') as monday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.monday.close_time')), '%H:%i') as monday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.tuesday.open_time')), '%H:%i') as tuesday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.tuesday.close_time')), '%H:%i') as tuesday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.wednesday.open_time')), '%H:%i') as wednesday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.wednesday.close_time')), '%H:%i') as wednesday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.thursday.open_time')), '%H:%i') as thursday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.thursday.close_time')), '%H:%i') as thursday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.friday.open_time')), '%H:%i') as friday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.friday.close_time')), '%H:%i') as friday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.saturday.open_time')), '%H:%i') as saturday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.saturday.close_time')), '%H:%i') as saturday_close,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.sunday.open_time')), '%H:%i') as sunday_open,
               TIME_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(r.opening_hours, '$.sunday.close_time')), '%H:%i') as sunday_close
        FROM restaurants r
        JOIN products p ON p.restaurant_id = r.id
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    $restaurant_info = $stmt->fetch(PDO::FETCH_ASSOC);

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

    $is_restaurant_open = isRestaurantOpen($restaurant_info);

    // Obtener el código de moneda del restaurante
    $currency_code = getCurrencyCode($product_data['restaurant_id']);
    $currency_decimals = getCurrencyDecimals($currency_code);

    // Función para convertir hex a rgba
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

    // Procesar los datos del producto
    $product = [
        'id' => $product_data['id'],
        'name' => $product_data['name'],
        'description' => $product_data['description'],
        'price' => $product_data['price'],
        'image' => $product_data['image'],
        'category_id' => $product_data['category_id'],
        'category_name' => $product_data['category_name'],
        'restaurant_id' => $product_data['restaurant_id'],
        'restaurant_name' => $product_data['restaurant_name'],
        'restaurant_slug' => $product_data['restaurant_slug'],
        'is_featured' => $product_data['is_featured'],
        'menu_options' => json_decode($product_data['menu_options'] ?? '{"options":[]}', true)
    ];

    error_log("=== INICIO DE DEPURACIÓN DE PRODUCTO ===");
    error_log("ID del producto: " . $product_id);
    error_log("menu_options raw: " . print_r($product_data['menu_options'], true));
    error_log("menu_options decoded: " . print_r($product['menu_options'], true));

    // Procesar las opciones del producto
    $product_options = [];
    
    if (!empty($product['menu_options']['options'])) {
        foreach ($product['menu_options']['options'] as $index => $option) {
            error_log("Procesando opción " . ($index + 1) . ": " . print_r($option, true));
            $option_id = 'option_' . $index;
            
            // Verificar valores de la opción
            if (isset($option['values']) && is_array($option['values'])) {
                error_log("Valores encontrados para la opción " . ($index + 1) . ": " . count($option['values']));
                foreach ($option['values'] as $value_index => $value) {
                    error_log("Valor " . ($value_index + 1) . ": " . print_r($value, true));
                }
            } else {
                error_log("No se encontraron valores para la opción " . ($index + 1));
            }
            
            $product_options[] = [
                'id' => $option_id,
                'name' => $option['name'],
                'type' => $option['type'] ?? 'single',
                'required' => (bool)($option['required'] ?? false),
                'description' => $option['description'] ?? null,
                'min_selections' => $option['type'] === 'multiple' ? ($option['min_selections'] ?? 0) : 0,
                'max_selections' => $option['type'] === 'multiple' ? ($option['max_selections'] ?? 999) : 1,
                'sort_order' => $index,
                'values' => array_map(function($value, $value_index) use ($option_id) {
                    $processed_value = [
                        'id' => $option_id . '_value_' . $value_index,
                        'name' => $value['name'],
                        'price' => (float)($value['price'] ?? 0),
                        'description' => $value['description'] ?? null,
                        'sort_order' => $value_index
                    ];
                    error_log("Valor procesado: " . print_r($processed_value, true));
                    return $processed_value;
                }, $option['values'] ?? [], array_keys($option['values'] ?? []))
            ];
        }
    }

    error_log("Opciones procesadas: " . print_r($product_options, true));
    error_log("=== FIN DE DEPURACIÓN DE PRODUCTO ===");

    // Obtener productos similares (misma categoría)
    $stmt = $conn->prepare("
        SELECT id, name, description, price, image, is_featured
        FROM products 
        WHERE category_id = ? AND id != ? AND is_active = 1
        ORDER BY is_featured DESC, RAND()
        LIMIT 7
    ");
    $stmt->execute([$product['category_id'], $product_id]);
    $similar_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error en product.php: " . $e->getMessage());
    die("Error al cargar los datos del producto: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($product['name']); ?> - <?php echo htmlspecialchars($product['restaurant_name']); ?></title>
    
    <!-- Favicon dinámico -->
    <?php if ($restaurant_info['logo']): ?>
        <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($restaurant_info['logo']); ?>">
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($restaurant_info['logo']); ?>">
        <link rel="apple-touch-icon" href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($restaurant_info['logo']); ?>">
    <?php else: ?>
        <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/img/favicon.ico">
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/img/favicon.ico">
        <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/assets/img/favicon.ico">
    <?php endif; ?>
    
    <!-- Preconnect para mejorar performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- CSS optimizado -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    
    <style>
        :root {
            --primary: <?php echo $restaurant_info['color_web'] ?? '#00b894'; ?>;
            --primary-dark: <?php echo hex2rgba($restaurant_info['color_web'] ?? '#00b894', 0.8); ?>;
            --secondary: #FF6B6B;
            --accent: #4ECDC4;
            --dark: #2D3436;
            --gray-50: #F8F9FA;
            --gray-100: #F1F3F4;
            --gray-200: #E9ECEF;
            --gray-300: #DEE2E6;
            --gray-400: #CED4DA;
            --gray-500: #ADB5BD;
            --gray-600: #6C757D;
            --gray-700: #495057;
            --gray-800: #343A40;
            --gray-900: #212529;
            --white: #FFFFFF;
            --success: #00B894;
            --warning: #FDCB6E;
            --danger: #E17055;
            --info: #74B9FF;
            
            --border-radius: 16px;
            --border-radius-sm: 8px;
            --border-radius-lg: 24px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Header */
        .product-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--gray-200);
            padding: 12px 16px;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .back-button {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--gray-700);
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .back-button:hover {
            background: var(--gray-100);
            color: var(--dark);
        }

        .restaurant-name {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        /* Contenido principal */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 16px 24px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .product-image-container {
                aspect-ratio: 16/9;
            }
        }

        .product-image-container {
            position: relative;
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            aspect-ratio: 1;
            background: var(--gray-100);
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
            display: block;
        }

        .product-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .product-header-info h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 16px;
        }

        .product-description {
            color: var(--gray-700);
            font-size: 1rem;
            line-height: 1.6;
        }

        /* Opciones del producto */
        .product-options {
            margin-top: 2rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
        }

        .product-options .section-title {
            font-size: 1.25rem;
            color: var(--dark);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary);
        }

        .option-group {
            margin-bottom: 1.5rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            overflow: hidden;
            background: var(--white);
        }

        .option-group:last-child {
            margin-bottom: 0;
        }

        .option-group-header {
            background: var(--gray-50);
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .option-group-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .option-description {
            margin: 0.5rem 0 0;
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .option-required {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: #ff4d4d;
            color: white;
            border-radius: var(--border-radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
        }

        @media (max-width: 480px) {
            .option-required {
                font-size: 0.65rem;
                padding: 0.2rem 0.4rem;
            }
        }

        .option-list {
            padding: 1rem;
        }

        .option-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius-sm);
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--white);
            gap: 0.75rem;
        }

        .option-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid var(--gray-300);
            border-radius: 4px;
            position: relative;
            flex-shrink: 0;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Estilos específicos para radio buttons */
        .option-item input[type="radio"] + .option-checkbox {
            border-radius: 50%;
        }

        .option-item input[type="radio"]:checked + .option-checkbox {
            border-color: var(--primary);
            background: var(--primary);
        }

        .option-item input[type="radio"]:checked + .option-checkbox:after {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: white;
        }

        .option-info {
            flex: 1;
            min-width: 0;
        }

        .option-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }

        .option-name {
            font-weight: 500;
            color: var(--dark);
            flex: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .option-price {
            color: var(--primary);
            font-weight: 500;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .option-description {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }

        .option-empty {
            text-align: center;
            padding: 1rem;
            color: var(--gray-500);
            font-style: italic;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
        }

        @media (max-width: 480px) {
            .product-options {
                padding: 1rem;
            }

            .option-group-header {
                padding: 0.75rem;
            }

            .option-list {
                padding: 0.75rem;
            }

            .option-item {
                padding: 0.5rem;
            }
        }

        /* Botón de agregar al carrito */
        .add-to-cart-section {
            position: sticky;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 16px;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
            z-index: 100;
        }

        .add-to-cart-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 4px;
            background: var(--gray-100);
            padding: 8px;
            border-radius: var(--border-radius);
        }

        .quantity-button {
            width: 32px;
            height: 32px;
            border: none;
            background: white;
            border-radius: var(--border-radius-sm);
            color: var(--dark);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .quantity-button:hover {
            background: var(--primary);
            color: white;
        }

        .quantity-input {
            width: 40px;
            text-align: center;
            border: none;
            background: none;
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }

        .add-to-cart-button {
            flex: 1;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: var(--border-radius-lg);
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            box-shadow: 0 2px 8px rgba(0, 212, 170, 0.3);
        }

        .add-to-cart-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 212, 170, 0.4);
        }

        .add-to-cart-button:active {
            transform: translateY(0);
        }

        /* Productos similares */
        .similar-products-section {
            margin: 2rem 0;
            padding: 0 1rem;
        }

        .similar-product-card {
            display: block;
            text-decoration: none;
            color: inherit;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            height: 100%;
        }

        .similar-product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .similar-product-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
        }

        .similar-product-info {
            padding: 1rem;
            background: white;
        }

        .similar-product-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }

        .similar-product-price {
            color: var(--primary);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .similar-products-swiper {
            padding: 1rem 0;
            margin: 0 -1rem;
        }

        .similar-products-swiper.swiper-initialized {
            padding: 1rem 1rem;
        }

        .swiper-slide {
            height: auto;
            padding: 0.5rem;
            background: transparent;
        }

        /* Asegurar que el fondo sea blanco en el grid y carrusel */
        .similar-products-swiper:not(.swiper-initialized) .swiper-wrapper,
        .similar-products-swiper.swiper-initialized .swiper-wrapper {
            background: transparent;
        }

        .similar-products-swiper:not(.swiper-initialized) .swiper-slide,
        .similar-products-swiper.swiper-initialized .swiper-slide {
            background: transparent;
        }

        @media (min-width: 769px) {
            .similar-products-section {
                padding: 0;
            }

            .similar-products-swiper {
                margin: 0;
            }

            /* Grid layout para desktop cuando no es carrusel */
            .similar-products-swiper:not(.swiper-initialized) .swiper-wrapper {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
                transform: none !important;
                margin-bottom: 10px;
            }

            .similar-products-swiper:not(.swiper-initialized) .swiper-slide {
                width: 100% !important;
                padding: 0;
            }
        }

        @media (max-width: 768px) {
            .similar-products-section {
                margin: 1.5rem 0;
            }

            .similar-product-image {
                aspect-ratio: 16/9;
            }

            .similar-product-name {
                font-size: 0.95rem;
            }

            .similar-product-price {
                font-size: 1rem;
            }
        }

        /* Estilos para la información de entrega */
        .delivery-info {
            margin-top: 1.5rem;
            padding: 1.25rem;
            background: var(--gray-50);
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
        }

        .info-section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-700);
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .info-section-title i {
            color: var(--primary);
        }

        .delivery-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .delivery-info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem;
            background: white;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--gray-200);
        }

        .delivery-info-item i {
            font-size: 1.25rem;
            color: var(--primary);
            width: 24px;
            text-align: center;
            margin-top: 0.125rem;
        }

        .delivery-info-content {
            flex: 1;
        }

        .delivery-info-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }

        .delivery-info-text {
            color: var(--gray-600);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .delivery-info-grid {
                grid-template-columns: 1fr;
            }

            .delivery-info-item {
                padding: 0.6rem;
            }

            .delivery-info-item i {
                font-size: 1.1rem;
            }

            .delivery-info-label {
                font-size: 0.9rem;
            }

            .delivery-info-text {
                font-size: 0.85rem;
            }
        }

        /* Ocultar en móvil */
        @media (max-width: 768px) {
            .delivery-info-desktop {
                display: none !important;
            }
        }

        /* Mostrar solo en desktop */
        @media (min-width: 769px) {
            .delivery-info-desktop {
                display: block;
            }
        }

        /* Estilos para la notificación del carrito */
        .cart-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            border-radius: var(--border-radius);
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1100;
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(120%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            max-width: 320px;
        }

        .cart-notification.show {
            transform: translateX(0);
        }

        .cart-notification.success {
            border-left: 4px solid var(--success);
        }

        .cart-notification.error {
            border-left: 4px solid var(--danger);
        }

        .cart-notification i {
            font-size: 1.25rem;
        }

        .cart-notification.success i {
            color: var(--success);
        }

        .cart-notification.error i {
            color: var(--danger);
        }

        .cart-notification-content {
            flex: 1;
        }

        .cart-notification-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 2px;
            font-size: 0.95rem;
        }

        .cart-notification-message {
            font-size: 0.875rem;
            color: var(--gray-600);
            line-height: 1.4;
        }

        @media (max-width: 480px) {
            .cart-notification {
                top: 16px;
                right: 16px;
                left: 16px;
                max-width: none;
                padding: 12px 16px;
            }
        }

        .restaurant-closed-message {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--danger);
            font-weight: 500;
            padding: 8px 16px;
            background: rgba(225, 112, 85, 0.1);
            border-radius: var(--border-radius);
            margin-right: 16px;
        }

        .restaurant-closed-message i {
            font-size: 1.1rem;
        }

        @media (max-width: 480px) {
            .restaurant-closed-message {
                font-size: 0.9rem;
                padding: 6px 12px;
                margin-right: 12px;
            }
        }

        /* Aplicar color personalizado a todos los precios */
        .product-price,
        .similar-product-price,
        .option-price {
            color: var(--primary) !important;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="product-header">
        <div class="header-content">
            <a href="<?php echo BASE_URL . '/' . $product['restaurant_slug']; ?>" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Volver
            </a>
            
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="main-content">
        <div class="product-grid">
            <!-- Imagen del producto -->
            <div class="product-image-container">
                <?php if ($product['image']): ?>
                    <img src="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         class="product-image"
                         loading="lazy">
                <?php endif; ?>

            </div>

            

            <!-- Detalles del producto -->
            <div class="product-details">
                <div class="product-header-info">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    <div class="product-price"><?php echo formatCurrency($product['price'], $product['restaurant_id']); ?></div>
                    <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                </div>

                

                <?php if (!empty($product_options)): ?>
                <div class="product-options">
                    <h3 class="section-title">Opciones Disponibles</h3>
                    <?php foreach ($product_options as $option): ?>
                    <div class="option-group" data-option-group="<?php echo htmlspecialchars($option['id']); ?>">
                        <div class="option-group-header">
                            <h3 class="option-group-title">
                                <?php echo htmlspecialchars($option['name']); ?>
                                <?php if ($option['required']): ?>
                                    <span class="option-required">Obligatorio</span>
                                <?php endif; ?>
                            </h3>
                            <?php if (!empty($option['description'])): ?>
                                <p class="option-description"><?php echo htmlspecialchars($option['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="option-list">
                            <?php if (!empty($option['values'])): ?>
                                <?php foreach ($option['values'] as $value): ?>
                                <label class="option-item">
                                    <div class="option-checkbox"></div>
                                    <div class="option-info">
                                        <div class="option-header">
                                            <div class="option-name"><?php echo htmlspecialchars($value['name']); ?></div>
                                            <?php if (isset($value['price']) && $value['price'] > 0): ?>
                                                <div class="option-price">+<?php echo formatCurrency($value['price'], $product['restaurant_id']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($value['description'])): ?>
                                            <div class="option-description"><?php echo htmlspecialchars($value['description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="<?php echo $option['type'] === 'multiple' ? 'checkbox' : 'radio'; ?>" 
                                           name="option_<?php echo htmlspecialchars($option['id']); ?>" 
                                           value="<?php echo htmlspecialchars($value['id']); ?>"
                                           <?php echo $option['required'] ? 'required' : ''; ?>
                                           <?php if ($option['type'] === 'multiple'): ?>
                                               data-min="<?php echo $option['min_selections']; ?>"
                                               data-max="<?php echo $option['max_selections']; ?>"
                                           <?php endif; ?>
                                           style="display: none;">
                                </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="option-empty">No hay valores disponibles para esta opción</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <br>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Este producto no tiene opciones disponibles
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Productos similares -->
        <div class="similar-products-section">
            <div class="swiper similar-products-swiper">
                <h3 style=" font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem;">Productos similares</h3>
                <div class="swiper-wrapper">
                    <?php foreach ($similar_products as $similar): ?>
                    <div class="swiper-slide">
                        <a href="<?php echo BASE_URL . '/product.php?id=' . $similar['id']; ?>" class="similar-product-card">
                            <?php if ($similar['image']): ?>
                                <img src="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($similar['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($similar['name']); ?>" 
                                     class="similar-product-image"
                                     loading="lazy">
                            <?php endif; ?>
                            <div class="similar-product-info">
                                <h3 class="similar-product-name"><?php echo htmlspecialchars($similar['name']); ?></h3>
                                <div class="similar-product-price"><?php echo formatCurrency($similar['price'], $product['restaurant_id']); ?></div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Agregar navegación -->
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <!-- Agregar paginación -->
                <div class="swiper-pagination"></div>
            </div>
        </div>
    </main>

    <!-- Botón de agregar al carrito -->
    <section class="add-to-cart-section">
        <div class="add-to-cart-container">
            <?php if ($is_restaurant_open): ?>
                <div class="quantity-selector">
                    <button class="quantity-button" onclick="updateQuantity(-1)">-</button>
                    <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="99" readonly>
                    <button class="quantity-button" onclick="updateQuantity(1)">+</button>
                </div>
                <button class="add-to-cart-button" onclick="handleAddToCart()">
                    <i class="fas fa-shopping-cart"></i>
                    Agregar &nbsp; <span id="totalPrice"><?php 
                        echo $currency_code . ' ' . number_format($product['price'], $currency_decimals, '.', '');
                    ?></span>
                </button>
            <?php else: ?>
                <div class="restaurant-closed-message">
                    <i class="fas fa-clock"></i>
                    <span>El restaurante está cerrado en este momento</span>
                </div>
                <button class="add-to-cart-button" disabled style="opacity: 0.5; cursor: not-allowed;">
                    <i class="fas fa-shopping-cart"></i>
                    No disponible
                </button>
            <?php endif; ?>
        </div>
    </section>

    <!-- Notificación del carrito -->
    <div class="cart-notification" id="cartNotification">
        <i class="fas fa-check-circle"></i>
        <div class="cart-notification-content">
            <div class="cart-notification-title">Producto agregado</div>
            <div class="cart-notification-message">El producto se ha agregado al carrito correctamente</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- Definir BASE_URL como variable global -->
    <script>
        window.BASE_URL = '<?php echo BASE_URL; ?>';
        window.CURRENT_RESTAURANT_ID = <?php echo $product['restaurant_id']; ?>;
        window.CURRENT_RESTAURANT_CURRENCY = '<?php echo $currency_code; ?>';
    </script>
    <script src="<?php echo BASE_URL; ?>/assets/js/cart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables globales
        let basePrice = <?php echo $product['price']; ?>;
        let selectedOptions = new Map();
        let quantity = 1;
        let isRestaurantOpen = <?php echo $is_restaurant_open ? 'true' : 'false'; ?>;

        // Función para validar las selecciones de opciones
        function validateOptionSelections() {
            if (!isRestaurantOpen) {
                showCartNotification(false, 'El restaurante está cerrado en este momento');
                return false;
            }

            const optionGroups = document.querySelectorAll('.option-group');
            let isValid = true;
            let errorMessage = '';

            optionGroups.forEach(group => {
                const inputs = group.querySelectorAll('input[type="radio"], input[type="checkbox"]');
                if (inputs.length === 0) return;
                
                const isRequired = inputs[0].hasAttribute('required');
                const isMultiple = inputs[0].type === 'checkbox';
                const minSelections = isMultiple ? parseInt(inputs[0].dataset.min || 0) : 0;
                const maxSelections = isMultiple ? parseInt(inputs[0].dataset.max || 1) : 1;
                
                const selectedCount = Array.from(inputs).filter(input => input.checked).length;
                
                if (isRequired && selectedCount === 0) {
                    isValid = false;
                    errorMessage = 'Por favor, selecciona al menos una opción en cada grupo obligatorio';
                } else if (isMultiple && selectedCount < minSelections) {
                    isValid = false;
                    errorMessage = `Debes seleccionar al menos ${minSelections} opción(es)`;
                } else if (isMultiple && selectedCount > maxSelections) {
                    isValid = false;
                    errorMessage = `Puedes seleccionar máximo ${maxSelections} opción(es)`;
                }
            });

            if (!isValid) {
                showCartNotification(false, errorMessage);
            }

            return isValid;
        }

        // Función para actualizar la cantidad
        window.updateQuantity = function(change) {
            const newQuantity = quantity + change;
            if (newQuantity >= 1 && newQuantity <= 99) {
                quantity = newQuantity;
                document.getElementById('quantity').value = quantity;
                updateTotalPrice();
            }
        };

        // Función para parsear precio desde texto según la moneda
        function parsePriceFromText(priceText) {
            if (!priceText || priceText === '0') return 0;
            
            const currencyCode = '<?php echo $currency_code; ?>';
            const currencyDecimals = <?php echo $currency_decimals; ?>;
            
            console.log('=== INICIO PARSING PRECIO ===');
            console.log('Texto original:', JSON.stringify(priceText));
            console.log('Moneda:', currencyCode);
            console.log('Decimales:', currencyDecimals);
            
            // Remover el código de moneda y cualquier texto adicional
            let cleanText = priceText.replace(new RegExp(currencyCode, 'gi'), '').trim();
            console.log('Después de remover moneda:', JSON.stringify(cleanText));
            
            // Remover símbolos comunes de moneda y el signo +
            cleanText = cleanText.replace(/[+$€£¥¢]/g, '').trim();
            console.log('Después de remover símbolos:', JSON.stringify(cleanText));
            
            // Manejar separadores de miles según la moneda
            if (currencyDecimals === 0) {
                // Para monedas sin decimales (CLP, COP, ARS, VES), remover puntos como separadores de miles
                cleanText = cleanText.replace(/\./g, '');
                console.log('Después de remover puntos (separadores de miles):', JSON.stringify(cleanText));
            } else {
                // Para monedas con decimales, manejar comas como separadores de miles
                cleanText = cleanText.replace(/,/g, '');
                console.log('Después de remover comas (separadores de miles):', JSON.stringify(cleanText));
            }
            
            const price = parseFloat(cleanText);
            console.log('Precio final parseado:', price);
            console.log('=== FIN PARSING PRECIO ===');
            
            return isNaN(price) ? 0 : price;
        }

        // Función para formatear precio según la moneda
        function formatPrice(price) {
            const currencyCode = '<?php echo $currency_code; ?>';
            const currencyDecimals = <?php echo $currency_decimals; ?>;
            return currencyCode + ' ' + price.toFixed(currencyDecimals);
        }

        // Función para actualizar el precio total
        function updateTotalPrice() {
            let unitPrice = basePrice;
            
            // Limpiar opciones seleccionadas antes de recalcular
            selectedOptions.clear();
            
            // Recalcular opciones seleccionadas
            document.querySelectorAll('.option-item input:checked').forEach(input => {
                const groupId = input.name;
                const item = input.closest('.option-item');
                const optionName = item.querySelector('.option-name')?.textContent || '';
                const optionPriceText = item.querySelector('.option-price')?.textContent || '0';
                const optionPrice = parsePriceFromText(optionPriceText);
                
                console.log('Procesando opción:', {
                    groupId,
                    optionName,
                    optionPriceText,
                    optionPrice
                });
                
                if (!selectedOptions.has(groupId)) {
                    selectedOptions.set(groupId, new Set());
                }
                
                selectedOptions.get(groupId).add({
                    name: optionName,
                    price: optionPrice
                });
                
                unitPrice += optionPrice;
            });
            
            // Calcular el precio total multiplicando el precio unitario por la cantidad
            const totalPrice = unitPrice * quantity;
            
            console.log('Cálculo de precio total:', {
                basePrice,
                unitPrice,
                quantity,
                totalPrice
            });
            
            // Actualizar UI de manera segura con la abreviación de la moneda
            const totalPriceElement = document.getElementById('totalPrice');
            if (totalPriceElement) {
                totalPriceElement.textContent = formatPrice(totalPrice);
            } else {
                console.warn('Elemento totalPrice no encontrado en el DOM');
            }
            
            return totalPrice;
        }

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

        // Función para manejar el agregar al carrito
        window.handleAddToCart = function() {
            console.log('Iniciando handleAddToCart');
            
            if (!isRestaurantOpen) {
                showCartNotification(false, 'El restaurante está cerrado en este momento');
                return;
            }
            
            if (!validateOptionSelections()) {
                console.log('Validación de opciones falló');
                return;
            }

            const button = document.querySelector('.add-to-cart-button');
            if (!button) {
                console.error('Botón de agregar al carrito no encontrado');
                showCartNotification(false, 'Error: No se encontró el botón de agregar al carrito');
                return;
            }

            const originalText = button.innerHTML;
            
            // Animación de loading
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
            button.disabled = true;
            
            try {
                // Obtener el precio total actualizado que incluye las opciones
                const totalPrice = updateTotalPrice();
                console.log('Precio total calculado:', totalPrice);
                
                if (isNaN(totalPrice) || totalPrice <= 0) {
                    throw new Error('El precio total no es válido: ' + totalPrice);
                }
                
                // Calcular el precio unitario (sin dividir por cantidad, ya que updateTotalPrice ya lo calcula correctamente)
                const unitPrice = totalPrice / quantity;
                
                // Preparar datos del producto
                const productData = {
                    cartItemId: Date.now(),
                    id: <?php echo $product['id']; ?>,
                    name: '<?php echo addslashes($product['name']); ?>',
                    price: Number(unitPrice.toFixed(<?php echo $currency_decimals; ?>)), // Mantener los decimales según la moneda
                    quantity: quantity,
                    image: '<?php echo addslashes($product['image']); ?>',
                    options: []
                };

                // Procesar opciones seleccionadas
                document.querySelectorAll('.option-group').forEach(groupElement => {
                    const groupId = groupElement.dataset.optionGroup;
                    const groupTitle = groupElement.querySelector('.option-group-title')?.textContent?.trim() || 'Opciones';
                    const selectedInputs = groupElement.querySelectorAll('input:checked');
                    
                    if (selectedInputs.length > 0) {
                        const groupOptions = Array.from(selectedInputs).map(input => {
                            const optionItem = input.closest('.option-item');
                            if (!optionItem) return null;
                            
                            const optionName = optionItem.querySelector('.option-name')?.textContent?.trim() || '';
                            const optionPriceText = optionItem.querySelector('.option-price')?.textContent || '0';
                            const optionPrice = parsePriceFromText(optionPriceText);
                            
                            return {
                                name: optionName,
                                price: optionPrice
                            };
                        }).filter(option => option !== null);

                        if (groupOptions.length > 0) {
                            productData.options.push({
                                group_id: groupId,
                                name: groupTitle,
                                options: groupOptions
                            });
                        }
                    }
                });

                console.log('Datos finales del producto a agregar:', JSON.stringify(productData, null, 2));
                
                // Verificar que el precio sea válido antes de agregar al carrito
                if (typeof productData.price !== 'number' || isNaN(productData.price) || productData.price <= 0) {
                    throw new Error('El precio del producto no es válido: ' + productData.price);
                }
                
                // Verificar que addToCart existe
                if (typeof addToCart !== 'function') {
                    throw new Error('La función addToCart no está definida');
                }
                
                // Agregar al carrito
                addToCart(productData);
                
                // Mostrar mensaje de éxito
                button.innerHTML = '<i class="fas fa-check"></i> ¡Agregado!';
                showCartNotification(true, 'El producto se ha agregado al carrito correctamente');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 1500);
            } catch (error) {
                console.error('Error detallado al agregar al carrito:', error);
                button.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error';
                showCartNotification(false, 'Error al agregar el producto al carrito: ' + error.message);
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 1500);
            }
        };

        // Event listeners para opciones
        document.querySelectorAll('.option-item').forEach(item => {
            const input = item.querySelector('input[type="radio"], input[type="checkbox"]');
            const checkbox = item.querySelector('.option-checkbox');
            
            input.addEventListener('change', function() {
                const groupId = input.name;
                const isMultiple = input.type === 'checkbox';
                
                if (!isMultiple) {
                    // Para opciones de selección única, deseleccionar otras opciones del mismo grupo
                    document.querySelectorAll(`input[name="${groupId}"]`).forEach(otherInput => {
                        if (otherInput !== input) {
                            otherInput.checked = false;
                            otherInput.closest('.option-item').classList.remove('selected');
                            otherInput.closest('.option-item').querySelector('.option-checkbox').style.background = '';
                        }
                    });
                }
                
                // Actualizar UI
                if (input.checked) {
                    item.classList.add('selected');
                    checkbox.style.background = 'var(--primary)';
                    checkbox.style.borderColor = 'var(--primary)';
                } else {
                    item.classList.remove('selected');
                    checkbox.style.background = '';
                    checkbox.style.borderColor = 'var(--gray-300)';
                }
                
                // Actualizar precio total
                updateTotalPrice();
            });
        });

        // Inicializar Swiper solo si hay más de 4 productos en desktop
        let swiper = null;
        const similarProducts = document.querySelectorAll('.similar-product-card');
        
        function initSwiper() {
            const similarProducts = document.querySelectorAll('.similar-product-card');
            const swiperContainer = document.querySelector('.similar-products-swiper');
            
            if (window.innerWidth >= 769 && similarProducts.length > 4) {
                // Agregar clase para mostrar flechas
                swiperContainer.classList.add('has-many-products');
                
                if (!swiper) {
                    swiper = new Swiper('.similar-products-swiper', {
                        slidesPerView: 4,
                        spaceBetween: 24,
                        centeredSlides: false,
                        loop: false,
                        navigation: {
                            nextEl: '.swiper-button-next',
                            prevEl: '.swiper-button-prev',
                        },
                        pagination: {
                            el: '.swiper-pagination',
                            clickable: true,
                        },
                        breakpoints: {
                            769: {
                                slidesPerView: 4,
                                spaceBetween: 24
                            }
                        }
                    });
                }
            } else if (window.innerWidth < 769) {
                // Remover clase para flechas en móvil
                swiperContainer.classList.remove('has-many-products');
                
                if (!swiper) {
                    swiper = new Swiper('.similar-products-swiper', {
                        slidesPerView: 1.2,
                        spaceBetween: 16,
                        centeredSlides: false,
                        loop: false,
                        pagination: {
                            el: '.swiper-pagination',
                            clickable: true,
                        },
                        navigation: {
                            nextEl: '.swiper-button-next',
                            prevEl: '.swiper-button-prev',
                        },
                        breakpoints: {
                            480: {
                                slidesPerView: 1.5,
                            },
                            576: {
                                slidesPerView: 2.2,
                            }
                        }
                    });
                }
            } else {
                // Remover clase cuando no hay carrusel
                swiperContainer.classList.remove('has-many-products');
                
                if (swiper) {
                    swiper.destroy(true, true);
                    swiper = null;
                }
            }
        }

        // Inicializar al cargar
        initSwiper();

        // Reinicializar al cambiar el tamaño de la ventana
        window.addEventListener('resize', initSwiper);
        
        // Inicializar el precio al cargar la página
        updateTotalPrice();
    });
    </script>
</body>
</html> 
