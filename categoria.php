<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/tracking.php';

// Obtener el ID de la categoría de la URL
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id > 0) {
    // Obtener información del restaurante y la categoría
    $query = "SELECT c.*, r.*, r.is_active as restaurant_is_active, r.slug as restaurant_slug,
                     c.name as category_name,
                     c.image as category_image,
                     c.banner_categoria,
                     (SELECT COUNT(*) FROM products WHERE category_id = c.id AND is_active = 1) as product_count 
              FROM menu_categories c 
              JOIN restaurants r ON c.restaurant_id = r.id 
              WHERE c.id = :category_id AND c.is_active = 1 AND r.is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $restaurant = [
            'id' => $data['restaurant_id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'logo' => $data['logo'],
            'banner' => $data['banner'],
            'banner_color' => $data['banner_color'],
            'address' => $data['address'],
            'phone' => $data['phone'],
            'whatsapp_url' => $data['whatsapp_url'],
            'facebook_url' => $data['facebook_url'],
            'instagram_url' => $data['instagram_url'],
            'tiktok_url' => $data['tiktok_url'],
            'has_delivery' => $data['has_delivery'],
            'has_physical_store' => $data['has_physical_store'],
            'opening_hours' => $data['opening_hours'],
            'is_active' => $data['restaurant_is_active'],
            'slug' => $data['restaurant_slug']
        ];

        $selected_category = [
            'id' => $data['id'],
            'name' => $data['category_name'],
            'image' => $data['category_image'],
            'banner_categoria' => $data['banner_categoria'],
            'product_count' => $data['product_count']
        ];

        // Registrar visita a la categoría
        trackPageView($restaurant['id'], 'category', $category_id);

        // Obtener productos de la categoría
        $query = "SELECT p.*, 
                 (SELECT COUNT(*) FROM product_menu_options WHERE product_id = p.id) as options_count
                 FROM products p
                 WHERE p.category_id = :category_id 
                   AND p.restaurant_id = :restaurant_id 
                   AND p.is_active = 1 
                   AND p.is_available = 1
                 ORDER BY p.is_featured DESC, p.sort_order ASC, p.name ASC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':restaurant_id', $restaurant['id']);
        $stmt->execute();
        $category_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesar las opciones de cada producto
        foreach ($category_products as &$product) {
            // Obtener las opciones del producto
            $stmt = $conn->prepare("
                SELECT mo.*, 
                       (SELECT COUNT(*) FROM product_menu_option_values WHERE option_id = mo.id) as values_count
                FROM product_menu_options mo
                WHERE mo.product_id = ?
                ORDER BY mo.sort_order ASC
            ");
            $stmt->execute([$product['id']]);
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Para cada opción, obtener sus valores
            foreach ($options as &$option) {
                $stmt = $conn->prepare("
                    SELECT * FROM product_menu_option_values 
                    WHERE option_id = ?
                    ORDER BY sort_order ASC
                ");
                $stmt->execute([$option['id']]);
                $option['values'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($option);

            $product['has_options'] = !empty($options);
            $product['menu_options'] = ['options' => $options];
        }
        unset($product);
    }
}

// Si no se encontró la categoría o el restaurante, redirigir al menú principal
if (!$data) {
    header('Location: ' . BASE_URL);
    exit;
}

// Función para verificar si el restaurante está abierto
function isRestaurantOpen($restaurant) {
    // Si el restaurante está marcado como inactivo
    if (!isset($restaurant['is_active']) || !$restaurant['is_active']) {
        return false;
    }

    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $day = strtolower($now->format('l'));
    $current_time = $now->format('H:i');
    
    $opening_hours = json_decode($restaurant['opening_hours'], true);
    if (!$opening_hours || !isset($opening_hours[$day])) {
        return false;
    }
    
    $day_schedule = $opening_hours[$day];
    
    if (!$day_schedule['is_open']) {
        return false;
    }
    
    $open_time = $day_schedule['open_time'];
    $close_time = $day_schedule['close_time'];
    
    if (empty($open_time) || empty($close_time)) {
        return false;
    }
    
    if ($close_time < $open_time) {
        return $current_time >= $open_time || $current_time <= $close_time;
    }
    
    return $current_time >= $open_time && $current_time <= $close_time;
}

$is_open = isRestaurantOpen($restaurant);

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

// Incluir el header con la ruta correcta
require_once __DIR__ . '/includes/header.php';
?>
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
     

<style>
:root {
    --primary: <?php echo $restaurant['color_web'] ?? '#00b894'; ?>;
    --accent: <?php echo hex2rgba($restaurant['color_web'] ?? '#00b894', 0.8); ?>;
    --dark: #1a1a1a;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --border-radius: 12px;
    --transition: all 0.3s ease;
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background: var(--gray-100);
    color: var(--gray-900);
    line-height: 1.6;
    margin: 0;
    padding: 0;
}

/* Header mejorado */
.app-header {
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
    margin-right: auto;
}

.app-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-900);
    text-decoration: none;
    transition: var(--transition);
}

.app-logo i {
    color: var(--primary);
    font-size: 1.5rem;
}

.app-logo:hover {
    color: var(--primary);
    transform: translateY(-1px);
}

.status-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 500;
    transition: var(--transition);
}

.status-badge i {
    font-size: 0.75rem;
}

.status-open {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success);
}

.status-closed {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger);
}

/* Hero section mejorado */
.restaurant-hero {
    position: relative;
    height: 300px;
    margin-top: 72px;
    overflow: hidden;
    background: var(--gray-800);
}

.hero-background {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.7;
    transition: transform 0.3s ease;
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.7));
    display: flex;
    align-items: flex-end;
    padding: 2rem;
}

.restaurant-info {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    color: white;
}

.restaurant-header-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.restaurant-title-container {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.restaurant-logo {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: var(--shadow-lg);
}

.restaurant-details h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.restaurant-description {
    font-size: 1.1rem;
    margin: 0;
    opacity: 0.9;
    max-width: 600px;
}

/* Contenedor principal mejorado */
.main-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
}

/* Contenedor de productos con scroll horizontal */
.products-container {
    width: 100%;
    overflow-x: auto;
    padding: 1rem 0;
    /* Ocultar scrollbar pero mantener funcionalidad */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}

.products-container::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera */
}

.products-row {
    display: flex;
    gap: 1.5rem;
    padding: 0;
    min-width: min-content; /* Asegura que el contenedor no se encoja */
}

/* Productos */
.product-card {
    border: none;
    border-radius: var(--border-radius);
    overflow: hidden;
    transition: var(--transition);
    background: white;
    box-shadow: var(--shadow-sm);
    width: 280px; /* Ancho fijo para las tarjetas */
    flex: 0 0 auto; /* Evita que las tarjetas se estiren o encojan */
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.product-card.featured {
    border: 2px solid var(--primary);
}

.product-card .card-img-top {
    width: 100%;
    height: 200px;
    aspect-ratio: 1;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card:hover .card-img-top {
    transform: scale(1.05);
}

.card-body {
    padding: 1.5rem;
    padding-top: 0px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    height: 100%;
}

.card-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    margin-top: 0px;
    color: var(--gray-900);
    flex-grow: 1;
}

.product-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
    width: 100%;
    gap: 0.5rem;
}

.product-price {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--primary);
    margin: 0;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    flex-shrink: 0;
}

/* Aplicar color personalizado a todos los precios */
.product-price,
.option-price {
    color: var(--primary) !important;
}

.add-button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 50px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: var(--transition);
    cursor: pointer;
    white-space: nowrap;
    height: 38px;
    line-height: 1;
    flex-shrink: 0;
    min-width: fit-content;
}

.add-button:hover {
    background: var(--accent);
    transform: translateY(-2px);
}

.add-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Indicador de scroll */
.scroll-indicator {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.9));
    width: 50px;
    height: 100%;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.products-container:hover .scroll-indicator {
    opacity: 1;
}

.scroll-indicator i {
    color: var(--primary);
    font-size: 1.5rem;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateX(0);
    }
    40% {
        transform: translateX(-5px);
    }
    60% {
        transform: translateX(-3px);
    }
}

/* Media queries para el diseño responsivo */
@media (min-width: 1200px) {
    .row {
        --bs-gutter-x: 1.5rem;
    }
    .col-xl-3 {
        flex: 0 0 auto;
        width: 25%;
    }
}

@media (max-width: 1199px) {
    .col-lg-4 {
        flex: 0 0 auto;
        width: 33.333333%;
    }
}

@media (max-width: 991px) {
    .col-md-6 {
        flex: 0 0 auto;
        width: 50%;
    }
}

@media (max-width: 575px) {
    .col-12 {
        flex: 0 0 auto;
        width: 100%;
    }
    .product-card .card-img-top {
        aspect-ratio: 16/9;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .restaurant-hero {
        height: 250px;
        margin-top: 64px;
    }

    .restaurant-details h1 {
        font-size: 2rem;
    }

    .restaurant-logo {
        width: 60px;
        height: 60px;
    }

    .restaurant-description {
        font-size: 1rem;
    }

    .main-container {
        padding: 1.5rem 1rem;
    }

    .product-card {
        width: 240px; /* Tarjetas más pequeñas en móviles */
    }
}

@media (max-width: 480px) {
    .restaurant-hero {
        height: 180px;
        margin-top: 56px;
    }

    .hero-overlay {
        padding: 1rem;
    }

    .restaurant-title-container {
        flex-direction: row;
        align-items: center;
        gap: 0.75rem;
    }

    .restaurant-logo {
        width: 50px;
        height: 50px;
        border-width: 2px;
        flex-shrink: 0;
    }

    .restaurant-details {
        flex: 1;
    }

    .restaurant-details h1 {
        font-size: 1.35rem;
        line-height: 1.3;
        margin: 0;
    }

    .main-container {
        padding: 1rem 0.75rem;
    }

    .product-card {
        width: 220px;
    }

    .product-card .card-img-top {
        height: 160px;
    }

    .card-body {
        padding: 0.75rem;
        display: flex;
        flex-direction: column;
    }

    .card-title {
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 2.8em;
        line-height: 1.4;
    }

    .product-footer {
        padding-top: 0.75rem;
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        gap: 0.5rem;
        margin-top: auto;
    }

    .product-price {
        font-size: 1rem;
        margin: 0;
        display: inline-flex;
        align-items: center;
    }

    .add-button {
        padding: 0.35rem 0.75rem;
        font-size: 0.85rem;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: fit-content;
    }

    .add-button span {
        display: inline-block;
    }

    .add-button::after {
        content: none;
    }

    /* Ajustes para el header */
    .app-header {
        padding: 8px 12px;
    }

    .header-content {
        gap: 8px;
    }

    .back-button {
        padding: 6px 10px;
        font-size: 0.9rem;
    }

    .status-badge {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
    }

    /* Ajustes para el contenedor de productos */
    .products-container {
        margin: 0 -0.75rem;
        padding: 0.5rem 0;
    }

    .products-row {
        padding: 0.5rem 0.75rem;
        gap: 1rem;
    }

    /* Ajustes para el título de la sección */
    .d-flex.justify-content-between.align-items-center.mb-4 {
        padding: 0 0.75rem;
    }

    .d-flex.justify-content-between.align-items-center.mb-4 h2 {
        font-size: 1.25rem;
    }

    .d-flex.justify-content-between.align-items-center.mb-4 small {
        font-size: 0.875rem;
    }

    /* Ajustes para el scroll indicator */
    .scroll-indicator {
        width: 30px;
    }

    .scroll-indicator i {
        font-size: 1.2rem;
    }

    /* Contenedor de productos con grid para móviles */
    .products-container {
        margin: 0;
        padding: 0;
        overflow-x: hidden; /* Eliminamos el scroll horizontal */
    }

    .products-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr); /* Dos columnas de igual tamaño */
        gap: 0.75rem;
        padding: 0px;
        width: 100%;
    }

    .product-card {
        width: 100%; /* Ancho completo para la columna */
        margin: 0;
    }

    .product-card .card-img-top {
        height: 140px;
        aspect-ratio: 1;
    }

    .card-body {
        padding: 0.75rem;
    }

    .card-title {
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
        /* Limitar a 2 líneas con ellipsis */
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 2.8em;
        line-height: 1.4;
    }

    .product-footer {
        padding-top: 0.75rem;
        gap: 0.5rem;
    }

    .product-price {
        font-size: 1rem;
        width: 38%;
        margin-bottom: 0.25rem;
    }

    .add-button {
        width: 5%;
        justify-content: center;
        padding: 0.35rem 0.75rem;
        font-size: 10px;
        height: 30px;
        margin-left: 10px;
        gap: 0;
    }

    .add-button span {
        display: none; /* Ocultar el texto "Seleccionar" */
    }

    .add-button::after {
        content: "Agregar"; /* Agregar el texto "Agregar" */
        margin-left: 4px;
    }

    /* Eliminar el indicador de scroll ya que no es necesario */
    .scroll-indicator {
        display: none;
    }

    /* Ajustar el contenedor principal */
    .main-container {
        padding: 0.75rem;
    }

    /* Ajustar el título de la sección */
    .d-flex.justify-content-between.align-items-center.mb-4 {
        padding: 0 0.75rem;
        margin-bottom: 0.75rem !important;
    }
}

/* Ajustes específicos para dispositivos muy pequeños (360px) */
@media (max-width: 360px) {
    .restaurant-hero {
        height: 160px;
    }

    .restaurant-logo {
        width: 50px;
        height: 50px;
    }

    .restaurant-details h1 {
        font-size: 1.35rem;
    }

    .product-card {
        width: 200px;
    }

    .product-card .card-img-top {
        height: 140px;
    }

    .back-button span {
        display: none;
    }

    .back-button i {
        margin: 0;
        font-size: 1.1rem;
    }

    .status-badge {
        padding: 0.35rem 0.7rem;
        font-size: 0.75rem;
    }

    .products-row {
        gap: 0.5rem;
        padding: 0.5rem;
    }

    .product-card .card-img-top {
        height: 120px;
    }

    .card-body {
        padding: 0.5rem;
    }

    .card-title {
        font-size: 0.9rem;
        height: 2.6em;
    }

    .product-price {
        font-size: 0.95rem;
    }

    .add-button {
        font-size: 0.8rem;
        height: 28px;
    }
}

/* Ajustes para el modal en móviles */
@media (max-width: 480px) {
    .modal-dialog {
        margin: 0.5rem;
    }

    .modal-content {
        border-radius: 12px;
    }

    .modal-header {
        padding: 1rem;
    }

    .modal-body {
        padding: 1rem;
    }

    .modal-footer {
        padding: 1rem;
    }

    .modal-title {
        font-size: 1.1rem;
    }

    #modalProductImage {
        max-height: 160px;
    }

    #modalProductName {
        font-size: 1.2rem;
    }

    .option-group {
        padding: 0.75rem;
    }

    .option-group-title {
        font-size: 1rem;
    }

    .option-item {
        padding: 0.6rem;
    }

    .option-name {
        font-size: 0.9rem;
    }

    .option-price {
        font-size: 0.9rem;
    }

    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
}

/* Botón volver en header */
.back-header-button {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--gray-900);
    background: transparent;
    border: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    text-decoration: none;
    cursor: pointer;
}

.back-header-button:hover {
    background: var(--gray-100);
    color: var(--primary);
    transform: translateX(-3px);
    text-decoration: none;
}

.back-header-button i {
    font-size: 1rem;
    transition: transform 0.3s ease;
}

.back-header-button:hover i {
    transform: translateX(-2px);
}

.back-header-button:hover i {
    transform: translateX(-2px);
}

/* Estilos para las opciones del producto */
.option-group {
    margin-bottom: 1.5rem;
    padding: 1rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    background: var(--gray-100);
}

.option-group-header {
    margin-bottom: 1rem;
}

.option-group-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.option-required {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    background: var(--primary);
    color: white;
    border-radius: 50px;
}

.option-description {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin-bottom: 0;
}

.option-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.option-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius);
    background: white;
    cursor: pointer;
    transition: var(--transition);
}

.option-item:hover {
    border-color: var(--primary);
    background: var(--gray-50);
}

.option-item.selected {
    border-color: var(--primary);
    background: rgba(0, 160, 130, 0.05);
}

.option-checkbox {
    width: 20px;
    height: 20px;
    border: 2px solid var(--gray-400);
    border-radius: 4px;
    position: relative;
    transition: var(--transition);
}

.option-item.selected .option-checkbox {
    background: var(--primary);
    border-color: var(--primary);
}

.option-item.selected .option-checkbox::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.option-info {
    flex-grow: 1;
}

.option-name {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.option-price {
    font-weight: 600;
    color: var(--primary);
}

/* Estilos para radio buttons personalizados */
.option-item input[type="radio"]:checked + .option-checkbox {
    background: var(--primary);
    border-color: var(--primary);
}

.option-item input[type="radio"]:checked + .option-checkbox::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 6px;
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
}

/* Eliminar los estilos de grid que puedan interferir */
.row {
    display: block;
    margin: 0;
    padding: 0;
}

.col-12 {
    width: 100%;
    padding: 0;
    margin: 0;
}

/* Agregar nuevo estilo para el margen responsivo */
.products-section {
    margin-left: 40px;
    margin-right: 40px;
}

@media (max-width: 768px) {
    .products-section {
        margin-left: 0;
        margin-right: 0;
    }
}

/* Aplicar color personalizado al footer */
.footer-section h3 {
    color: var(--primary) !important;
}

.footer-section h3::after {
    background: var(--primary) !important;
}

.footer-info i {
    color: var(--primary) !important;
}

.social-icon {
    background: var(--primary) !important;
}

.social-link:hover .social-icon {
    background: var(--accent) !important;
}

.location-button.primary {
    background: var(--primary) !important;
    color: white !important;
}

.location-button.primary:hover {
    background: var(--accent) !important;
}

.service-item i {
    color: var(--primary) !important;
}

/* Estilos adicionales para el footer */
.footer-section h4 {
    color: var(--primary) !important;
}

.footer-section h4::after {
    background: var(--primary) !important;
}

.schedule-item.today .badge {
    background: var(--primary) !important;
}

.location-button.secondary {
    border-color: var(--primary) !important;
    color: var(--primary) !important;
}

.location-button.secondary:hover {
    background: var(--primary) !important;
    color: white !important;
}

.add-button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 50px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: var(--transition);
    cursor: pointer;
    white-space: nowrap;
    height: 38px;
    line-height: 1;
    flex-shrink: 0;
    min-width: fit-content;
}

.add-button:hover {
    background: var(--accent);
    transform: translateY(-2px);
}

.add-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}
</style>

<!-- Header fijo -->
<header class="app-header" id="appHeader">
    <div class="header-content">
        <?php if (isset($restaurant['slug'])): ?>
            <a href="/menu.php?slug=<?= htmlspecialchars($restaurant['slug']) ?>" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Volver
            </a>
        <?php else: ?>
            <a href="" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Volver
            </a>
        <?php endif; ?>
        <span class="restaurant-name"></span>
        <div class="status-badge <?= $is_open ? 'status-open' : 'status-closed' ?>">
            <i class="fas fa-circle"></i>
            <?= $is_open ? 'Abierto' : 'Cerrado' ?>
        </div>
    </div>
</header>

<!-- Hero section -->
<section class="restaurant-hero">
    <?php if ($selected_category['banner_categoria']): ?>
        <img src="/uploads/<?= htmlspecialchars($selected_category['banner_categoria']) ?>" 
             alt="<?= htmlspecialchars($selected_category['name']) ?>" 
             class="hero-background">
    <?php elseif ($selected_category['image']): ?>
        <img src="/uploads/<?= htmlspecialchars($selected_category['image']) ?>" 
             alt="<?= htmlspecialchars($selected_category['name']) ?>" 
             class="hero-background">
    <?php endif; ?>
    
    <div class="hero-overlay">
        <div class="restaurant-info">
            <div class="restaurant-header-content">
                <div class="restaurant-title-container">
                    <?php if ($selected_category['image']): ?>
                        <img src="/uploads/<?= htmlspecialchars($selected_category['image']) ?>" 
                             alt="<?= htmlspecialchars($selected_category['name']) ?>" 
                             class="restaurant-logo">
                    <?php endif; ?>
                    <div class="restaurant-details">
                        <h1><?= htmlspecialchars($selected_category['name']) ?></h1>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contenedor principal -->
<main class="main-container">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-12">
                <!-- Mostrar productos de la categoría seleccionada -->
                <div class="mb-4 products-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">
    
                            <small class="text-muted">Artículos (<?= count($category_products) ?> )</small>
                        </h2>
                    </div>
                    
                    <?php if (empty($category_products)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No hay productos disponibles en esta categoría
                        </div>
                    <?php else: ?>
                        <div class="products-container">
                            <div class="products-row">
                            <?php foreach ($category_products as $product): ?>
                                    <div class="product-card <?= $product['is_featured'] ? 'featured' : '' ?>" data-has-options="<?= $product['has_options'] ? 'true' : 'false' ?>">
                                        <?php if ($product['image']): ?>
                                            <img src="/uploads/<?= htmlspecialchars($product['image']) ?>" 
                                                 class="card-img-top" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-utensils fa-2x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                            
                                            <div class="product-footer">
                                                <div class="product-price"><?= formatCurrency($product['price'], $restaurant['id']) ?></div>
                                                <button class="add-button" onclick="window.location.href='<?php echo BASE_URL; ?>/product.php?id=<?php echo $product['id']; ?>'">
                                                    <i class="fas fa-plus"></i>
                                                    <span>Agregar</span>
                                                </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Definir variables globales -->
<script>
    window.BASE_URL = '<?php echo BASE_URL; ?>';
    window.CURRENT_RESTAURANT_ID = <?php echo $restaurant['id']; ?>;
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/cart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Header scroll effect
    const appHeader = document.getElementById('appHeader');
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

    const productOptionsModal = new bootstrap.Modal(document.getElementById('productOptionsModal'));
    let currentProduct = null;
    let basePrice = 0;

    // Función para formatear precio
    function formatPrice(price) {
        return '$' + price.toLocaleString('es-CL');
    }

    // Función para actualizar el precio total
    function updateTotalPrice() {
        let total = basePrice;
        const selectedOptions = document.querySelectorAll('.option-item.selected');
        
        selectedOptions.forEach(option => {
            const priceElement = option.querySelector('.option-price');
            if (priceElement) {
                const price = parseFloat(priceElement.dataset.price) || 0;
                total += price;
            }
        });

        document.getElementById('modalTotalPrice').textContent = formatPrice(total);
    }

    // Función para cargar las opciones del producto
    async function loadProductOptions(productId) {
        try {
            const response = await fetch(`${BASE_URL}/api/get_product_options.php?product_id=${productId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Error al cargar las opciones');
            }

            const optionsContainer = document.getElementById('productOptionsContainer');
            optionsContainer.innerHTML = '';

            if (!data.options || data.options.length === 0) {
                optionsContainer.innerHTML = '<p class="text-muted">Este producto no tiene opciones disponibles.</p>';
                return;
            }

            data.options.forEach(option => {
                const optionGroup = document.createElement('div');
                optionGroup.className = 'option-group';
                optionGroup.innerHTML = `
                    <div class="option-group-header">
                        <h3 class="option-group-title">
                            ${option.name}
                            ${option.required ? '<span class="option-required">Obligatorio</span>' : ''}
                        </h3>
                        ${option.description ? `<p class="option-description">${option.description}</p>` : ''}
                    </div>
                    <div class="option-list">
                        ${option.values.map(value => `
                            <label class="option-item" data-price="${value.price || 0}">
                                <input type="${option.type === 'multiple' ? 'checkbox' : 'radio'}" 
                                       name="option_${option.id}" 
                                       value="${value.id}"
                                       ${option.required ? 'required' : ''}
                                       style="display: none;">
                                <div class="option-checkbox"></div>
                                <div class="option-info">
                                    <div class="option-name">${value.name}</div>
                                    ${value.price > 0 ? `<div class="option-price" data-price="${value.price}">+${formatPrice(value.price)}</div>` : ''}
                                </div>
                            </label>
                        `).join('')}
                    </div>
                `;
                optionsContainer.appendChild(optionGroup);
            });

            // Agregar event listeners para las opciones
            document.querySelectorAll('.option-item').forEach(item => {
                item.addEventListener('click', function() {
                    const input = this.querySelector('input');
                    const isCheckbox = input.type === 'checkbox';
                    
                    if (isCheckbox) {
                        this.classList.toggle('selected');
                        input.checked = !input.checked;
                    } else {
                        // Para radio buttons, deseleccionar otros en el mismo grupo
                        const group = document.querySelectorAll(`input[name="${input.name}"]`);
                        group.forEach(radio => {
                            radio.closest('.option-item').classList.remove('selected');
                        });
                        this.classList.add('selected');
                        input.checked = true;
                    }
                    
                    updateTotalPrice();
                });
            });

        } catch (error) {
            console.error('Error:', error);
            alert('Error al cargar las opciones del producto');
        }
    }

    // Función para abrir el modal de opciones
    window.openProductOptions = async function(productId, productName, productDescription, productImage, price) {
        currentProduct = { id: productId, name: productName, price: price };
        basePrice = price;

        // Actualizar contenido del modal
        document.getElementById('modalProductId').value = productId;
        document.getElementById('modalProductName').textContent = productName;
        document.getElementById('modalProductDescription').textContent = productDescription || '';
        document.getElementById('modalProductImage').src = productImage || `${BASE_URL}/assets/img/no-image.png`;
        document.getElementById('modalTotalPrice').textContent = formatPrice(price);

        // Cargar opciones
        await loadProductOptions(productId);

        // Mostrar modal
        productOptionsModal.show();
    };

    // Función para agregar al carrito con opciones
    document.getElementById('addToCartWithOptions').addEventListener('click', function() {
        const form = document.getElementById('productOptionsForm');
        const selectedOptions = [];
        
        // Recolectar opciones seleccionadas
        document.querySelectorAll('.option-item.selected').forEach(item => {
            const input = item.querySelector('input');
            const optionName = item.closest('.option-group').querySelector('.option-group-title').textContent.trim();
            const valueName = item.querySelector('.option-name').textContent;
            const price = parseFloat(item.dataset.price) || 0;

            selectedOptions.push({
                optionId: input.name.replace('option_', ''),
                optionName: optionName,
                valueId: input.value,
                valueName: valueName,
                price: price
            });
        });

        // Verificar opciones requeridas
        const requiredInputs = form.querySelectorAll('input[required]');
        let isValid = true;
        requiredInputs.forEach(input => {
            if (!input.checked) {
                isValid = false;
                const optionGroup = input.closest('.option-group');
                optionGroup.style.borderColor = 'var(--danger)';
                setTimeout(() => {
                    optionGroup.style.borderColor = 'var(--gray-200)';
                }, 2000);
            }
        });

        if (!isValid) {
            alert('Por favor, selecciona todas las opciones requeridas');
            return;
        }

        // Aquí puedes implementar la lógica para agregar al carrito
        console.log('Producto:', currentProduct);
        console.log('Opciones seleccionadas:', selectedOptions);

        // Cerrar modal
        productOptionsModal.hide();

        // Mostrar mensaje de éxito
        const button = document.querySelector(`[onclick="openProductOptions(${currentProduct.id})"]`);
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
        button.disabled = true;
        
        setTimeout(() => {
            button.innerHTML = '<i class="fas fa-check"></i> Agregado';
            button.style.background = 'var(--success)';
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
                button.style.background = '';
            }, 1500);
        }, 800);
    });
});
        
// Función para agregar al carrito
window.addToCart = function(productId) {
    window.location.href = `${BASE_URL}/product.php?id=${productId}`;
    };
</script> 

