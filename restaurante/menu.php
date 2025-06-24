<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

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

// Obtener datos del restaurante y su plan
try {
    $stmt = $conn->prepare("
        SELECT r.*, 
               COALESCE(p.name, 'Plan Básico') as plan_name, 
               COALESCE(p.max_categories, 5) as max_categories,
               COALESCE(p.max_products, 20) as max_products,
               COALESCE((SELECT COUNT(*) FROM menu_categories WHERE restaurant_id = r.id), 0) as current_categories,
               COALESCE((SELECT COUNT(*) FROM products WHERE restaurant_id = r.id), 0) as current_products,
               COALESCE(s.status, r.subscription_status) as final_subscription_status
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        LEFT JOIN subscriptions s ON r.id = s.restaurant_id AND s.status = 'active'
        WHERE r.id = ?
    ");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$restaurant) {
        redirect(BASE_URL . '/restaurante/logout.php');
    }

    // Verificar estado de suscripción y tienda para determinar restricciones
    $subscription_status = $restaurant['final_subscription_status'] ?? 'trial';
    $is_restaurant_active = $restaurant['is_active'] ?? 0;
    
    // Determinar si se pueden agregar productos/categorías
    $can_add_content = true;
    $restriction_reason = '';
    
    if ($subscription_status === 'cancelled') {
        $can_add_content = false;
        $restriction_reason = 'Suscripción cancelada';
    } elseif ($subscription_status === 'expired') {
        $can_add_content = false;
        $restriction_reason = 'Suscripción expirada';
    } elseif (!$is_restaurant_active) {
        $can_add_content = false;
        $restriction_reason = 'Tienda inactiva';
    }

    // Obtener categorías y productos
    $stmt = $conn->prepare("
        SELECT mc.*, COUNT(p.id) as product_count 
        FROM menu_categories mc
        LEFT JOIN products p ON mc.id = p.category_id
        WHERE mc.restaurant_id = ?
        GROUP BY mc.id
        ORDER BY mc.sort_order ASC
    ");
    $stmt->execute([$restaurant_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener productos por categoría
    $products_by_category = [];
    foreach ($categories as $category) {
        $stmt = $conn->prepare("
            SELECT * FROM products 
            WHERE restaurant_id = ? AND category_id = ?
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt->execute([$restaurant_id, $category['id']]);
        $products_by_category[$category['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Error al obtener datos del menú: " . $e->getMessage());
    $error = "Error al cargar los datos del menú";
}

// Generar token CSRF
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Menú - <?= htmlspecialchars($restaurant['name']) ?></title>
    
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
</head>

<style>
    .product-card .btn-group .btn {
        padding: 2px 0px;
        font-size: 9px;
    }

    .modal-header {
        background-color: #000000;
        border-bottom: 1px solid #333333;
        padding: 1rem 1.25rem;
        color: #ffffff;
    }

    .modal-header .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    .modal-header .modal-title {
        color: #ffffff;
    }
    
    /* Estilos para el contenedor de botones de acción */
    .action-buttons-container {
        padding: 0rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        border: 1px solid #dee2e6;
    }

    .action-buttons-container h6 {
        font-weight: 600;
        color: #6c757d;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .action-buttons-container h6 i {
        margin-right: 0.5rem;
        color: #495057;
    }

    .action-buttons-container .row {
        margin: 0;
    }

    .action-buttons-container .col-12,
    .action-buttons-container .col-sm-6,
    .action-buttons-container .col-md-4 {
        padding: 0rem;
    }

    /* Estilos modernos para el botón de importar */
    .btn-import-custom {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 12px;
        padding: 12px 16px;
        color: white;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.25);
        position: relative;
        overflow: hidden;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-import-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
        color: white;
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    }

    .btn-import-custom:active {
        transform: translateY(0);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.25);
    }

    .btn-import-content {
        display: flex;
        align-items: center;
        gap: 6px;
        justify-content: center;
        width: 100%;
    }

    .btn-import-custom i {
        font-size: 16px;
        transition: transform 0.3s ease;
    }

    .btn-import-custom:hover i {
        transform: scale(1.1);
    }

    /* Estilos para el botón de Nueva Categoría */
    .btn-category-custom {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
        border-radius: 12px;
        padding: 12px 16px;
        color: white;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.25);
        position: relative;
        overflow: hidden;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-category-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(40, 167, 69, 0.35);
        color: white;
        background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
    }

    .btn-category-custom:active {
        transform: translateY(0);
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.25);
    }

    .btn-category-custom i {
        font-size: 16px;
        transition: transform 0.3s ease;
    }

    .btn-category-custom:hover i {
        transform: scale(1.1);
    }

    /* Estilos para el botón de Nuevo Producto */
    .btn-product-custom {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border: none;
        border-radius: 12px;
        padding: 12px 16px;
        color: white;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.25);
        position: relative;
        overflow: hidden;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-product-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 123, 255, 0.35);
        color: white;
        background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    }

    .btn-product-custom:active {
        transform: translateY(0);
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.25);
    }

    .btn-product-custom i {
        font-size: 16px;
        transition: transform 0.3s ease;
    }

    .btn-product-custom:hover i {
        transform: scale(1.1);
    }

    /* Estilos para el texto de los botones */
    .btn-text {
        font-weight: 600;
        white-space: nowrap;
    }

    /* Estilos mejorados para el modal de importación */
    .modal-import-custom .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        max-height: 85vh;
    }

    .modal-import-custom .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-bottom: none;
        padding: 1.25rem 1.5rem;
        position: relative;
    }

    .modal-import-custom .modal-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        opacity: 0.2;
    }

    .modal-import-custom .modal-title {
        color: white;
        font-weight: 700;
        font-size: 1.1rem;
        position: relative;
        z-index: 1;
    }

    .modal-import-custom .modal-body {
        padding: 1.5rem;
        background: #f8f9fa;
        max-height: 60vh;
        overflow-y: auto;
    }

    .modal-import-custom .modal-footer {
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        padding: 1.25rem 1.5rem;
    }

    .modal-import-custom .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
        opacity: 0.8;
        transition: opacity 0.3s ease;
    }

    .modal-import-custom .btn-close:hover {
        opacity: 1;
    }

    /* Estilos para las tarjetas de productos en el modal */
    .product-import-card {
        border: none;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: all 0.3s ease;
        overflow: hidden;
        height: 100%;
    }

    .product-import-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    }

    .product-import-card .card-img-top {
        height: 120px;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .product-import-card:hover .card-img-top {
        transform: scale(1.05);
    }

    .product-import-card .card-body {
        padding: 1rem;
    }

    .product-import-card .form-check-input {
        width: 16px;
        height: 16px;
        margin-top: 0.125rem;
        border: 2px solid #dee2e6;
        border-radius: 3px;
        transition: all 0.2s ease;
    }

    .product-import-card .form-check-input:checked {
        background-color: #667eea;
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .product-import-card .form-check-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    /* Estilo para placeholder de imagen */
    .product-import-card .card-img-top.placeholder {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .product-import-card .card-img-top.placeholder::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="%23dee2e6"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
        opacity: 0.3;
    }

    .product-import-card .card-img-top.placeholder i {
        position: relative;
        z-index: 1;
        font-size: 2.5rem;
        color: #adb5bd;
    }

    /* Estilos para las categorías */
    .category-import-card {
        border: none;
        border-radius: 8px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        margin-bottom: 1rem;
        overflow: hidden;
    }

    .category-import-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #dee2e6;
        padding: 0.75rem 1rem;
    }

    .category-import-card .card-header .form-check-label {
        font-size: 1rem;
        font-weight: 600;
        color: #495057;
    }

    .category-import-card .badge {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        border-radius: 15px;
        padding: 0.4rem 0.6rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Estilos para el loading */
    .import-loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    .import-loading .spinner-border {
        width: 2.5rem;
        height: 2.5rem;
        color: #667eea;
    }

    .import-loading p {
        margin-top: 0.75rem;
        color: #6c757d;
        font-weight: 500;
        font-size: 0.9rem;
    }

    /* Estilos para los botones del modal */
    .modal-import-custom .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 6px;
        padding: 0.6rem 1.2rem;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        box-shadow: 0 3px 10px rgba(102, 126, 234, 0.25);
        min-width: 120px;
    }

    .modal-import-custom .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.35);
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    }

    .modal-import-custom .btn-secondary {
        background: #6c757d;
        border: none;
        border-radius: 6px;
        padding: 0.6rem 1.2rem;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        min-width: 100px;
    }

    .modal-import-custom .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-1px);
    }

    /* Estilos para las alertas */
    .alert-import-info {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        border: none;
        border-radius: 8px;
        border-left: 4px solid #2196f3;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }

    .alert-import-success {
        background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
        border: none;
        border-radius: 8px;
        border-left: 4px solid #4caf50;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }

    /* Animaciones */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .product-import-card {
        animation: fadeInUp 0.5s ease forwards;
    }

    .product-import-card:nth-child(1) { animation-delay: 0.05s; }
    .product-import-card:nth-child(2) { animation-delay: 0.1s; }
    .product-import-card:nth-child(3) { animation-delay: 0.15s; }
    .product-import-card:nth-child(4) { animation-delay: 0.2s; }
    .product-import-card:nth-child(5) { animation-delay: 0.25s; }
    .product-import-card:nth-child(6) { animation-delay: 0.3s; }

    /* Scrollbar personalizado para el modal */
    .modal-import-custom .modal-body::-webkit-scrollbar {
        width: 6px;
    }

    .modal-import-custom .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .modal-import-custom .modal-body::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    .modal-import-custom .modal-body::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    @media (max-width: 767px) {
        .card-body {
            margin-top: 13px;
        }
        
        .action-buttons-container {
            padding: 0px;
        }
        
        
        .action-buttons-container .col-12,
        .action-buttons-container .col-sm-6,
        .action-buttons-container .col-md-4 {
            padding: 0.25rem;
        }
        
        .btn-import-custom,
        .btn-category-custom,
        .btn-product-custom {
            padding: 10px 12px;
            font-size: 12px;
            height: 44px;
        }
        
        .btn-import-content {
            gap: 4px;
        }
        
        .btn-import-custom i,
        .btn-category-custom i,
        .btn-product-custom i {
            font-size: 14px;
        }
        
        .btn-text {
            font-size: 12px;
        }
        
        .modal-import-custom .modal-body {
            padding: 1rem;
            max-height: 70vh;
        }
        
        .modal-import-custom .modal-footer {
            padding: 1rem;
        }
        
        .modal-import-custom .modal-header {
            padding: 1rem;
        }
        
        .modal-import-custom .modal-title {
            font-size: 1rem;
        }
        
        .product-import-card .card-img-top {
            height: 100px;
        }
        
        .product-import-card .card-body {
            padding: 0.75rem;
        }
        
        .category-import-card .card-header {
            padding: 0.5rem 0.75rem;
        }
        
        .category-import-card .card-header .form-check-label {
            font-size: 0.9rem;
        }
        
        .alert-import-info,
        .alert-import-success {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
        }
        
        .modal-import-custom .btn-primary,
        .modal-import-custom .btn-secondary {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            min-width: 100px;
        }
    }

    @media (max-width: 576px) {
        .action-buttons-container {
            padding: 0.5rem;
        }
        
        .action-buttons-container h6 {
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .action-buttons-container .col-12,
        .action-buttons-container .col-sm-6,
        .action-buttons-container .col-md-4 {
            padding: 0.25rem;
        }
        
        .btn-import-custom,
        .btn-category-custom,
        .btn-product-custom {
            padding: 8px 10px;
            font-size: 11px;
            height: 40px;
        }
        
        .btn-import-custom i,
        .btn-category-custom i,
        .btn-product-custom i {
            font-size: 12px;
        }
        
        .btn-text {
            font-size: 11px;
        }
        
        .modal-import-custom .modal-dialog {
            margin: 0.5rem;
        }
        
        .product-import-card .card-img-top {
            height: 80px;
        }
        
        .product-import-card .card-body {
            padding: 0.5rem;
        }
        
        .product-import-card .form-check-input {
            width: 14px;
            height: 14px;
        }
        
        .category-import-card .card-header .form-check-label {
            font-size: 0.85rem;
        }
        
        .category-import-card .badge {
            padding: 0.3rem 0.5rem;
            font-size: 0.7rem;
        }
    }

    /* Estilos para pantallas grandes - botones en línea horizontal */
    @media (min-width: 768px) {
        .action-buttons-container {
            padding: 0.5rem;
        }
        
        .action-buttons-container h6 {
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        
        .action-buttons-container .col-md-4 {
            padding: 0.45rem;
        }
        
        .btn-import-custom,
        .btn-category-custom,
        .btn-product-custom {
            height: 52px;
            font-size: 14px;
        }
        
        .btn-import-custom i,
        .btn-category-custom i,
        .btn-product-custom i {
            font-size: 18px;
        }
        
        .btn-text {
            font-size: 11px;
        }
    }

    /* Estilos para botones deshabilitados */
    .btn-category-custom:disabled,
    .btn-product-custom:disabled,
    .btn-import-custom:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
        background: #6c757d !important;
        color: #ffffff !important;
    }

    .btn-category-custom:disabled:hover,
    .btn-product-custom:disabled:hover,
    .btn-import-custom:disabled:hover {
        transform: none !important;
        box-shadow: none !important;
        background: #6c757d !important;
        color: #ffffff !important;
    }

    .btn-category-custom:disabled i,
    .btn-product-custom:disabled i,
    .btn-import-custom:disabled i {
        transform: none !important;
    }
</style>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">
                            <i class="fas fa-utensils"></i> Gestión de Menú
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <a href="/restaurante/dashboard.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                                </a>
                            </div>
                        </div>
                    </div>

                    <?php if (!$can_add_content): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <div>
                                    <strong>Funcionalidad Limitada:</strong> No puedes agregar productos o categorías porque tu <?= $restriction_reason ?>.
                                    <br>
                                    <small class="text-muted">
                                        Contacta al administrador para reactivar tu cuenta o renovar tu suscripción.
                                    </small>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-warning alert-dismissible fade show">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <div>
                                    <?php if (strpos($error, 'Has alcanzado el límite') !== false): ?>
                                        <h5 class="alert-heading mb-2">¡Límite de categorías alcanzado!</h5>
                                        <p class="mb-2"><?= strip_tags($error, '<a>') ?></p>
                                        <hr>
                                        <div class="d-flex align-items-center mt-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <small class="text-muted">¿Necesitas más categorías? Actualiza tu plan para desbloquear más funcionalidades.</small>
                                        </div>
                                    <?php else: ?>
                                        <?= htmlspecialchars($error) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Plan Info -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-0">Plan Actual: <?= htmlspecialchars($restaurant['plan_name']) ?></h5>
                                    <div class="mt-3">
                                        <p class="mb-1">Categorías: <?= $restaurant['current_categories'] ?> / <?= $restaurant['max_categories'] ?></p>
                                        <div class="progress mb-3">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= ($restaurant['current_categories'] / $restaurant['max_categories']) * 100 ?>%">
                                            </div>
                                        </div>
                                        <p class="mb-1">Productos: <?= $restaurant['current_products'] ?> / <?= $restaurant['max_products'] ?></p>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= ($restaurant['current_products'] / $restaurant['max_products']) * 100 ?>%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="action-buttons-container">
                                        <h6 class="text-center mb-3 text-muted">
                                            <i class="fas fa-tools"></i> Acciones Rápidas
                                        </h6>
                                        <div class="row g-3">
                                            <div class="col-12 col-sm-6 col-md-4">
                                                <button type="button" class="btn btn-category-custom w-100" 
                                                        data-bs-toggle="modal" data-bs-target="#addCategoryModal"
                                                        <?= !$can_add_content ? 'disabled' : '' ?>>
                                                    <i class="fas fa-folder-plus"></i>
                                                    <span class="btn-text">Nueva Categoría</span>
                                                </button>
                                            </div>
                                            <div class="col-12 col-sm-6 col-md-4">
                                                <button type="button" class="btn btn-product-custom w-100" 
                                                        data-bs-toggle="modal" data-bs-target="#addProductModal"
                                                        <?= !$can_add_content ? 'disabled' : '' ?>>
                                                    <i class="fas fa-plus-circle"></i>
                                                    <span class="btn-text">Nuevo Producto</span>
                                                </button>
                                            </div>
                                            <?php if (isset($_SESSION['is_branch']) && $_SESSION['is_branch']): ?>
                                            <div class="col-12 col-sm-12 col-md-4">
                                                <button type="button" class="btn btn-import-custom w-100" 
                                                        data-bs-toggle="modal" data-bs-target="#importProductsModal"
                                                        <?= !$can_add_content ? 'disabled' : '' ?>>
                                                    <div class="btn-import-content">
                                                        <i class="fas fa-cloud-download-alt"></i>
                                                        <span class="btn-text">Importar Productos</span>
                                                    </div>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Buscador -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" id="searchProducts" class="form-control" placeholder="Buscar productos...">
                            </div>
                        </div>
                    </div>

                    <!-- Categories and Products -->
                    <div id="categories-container">
                        <?php foreach ($categories as $category): ?>
                            <div class="card mb-4" data-category-id="<?= $category['id'] ?>">
                                <div class="card-header category-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="fas fa-folder"></i>
                                            <?= htmlspecialchars($category['name']) ?>
                                            <small class="text-muted">(<?= $category['product_count'] ?> productos)</small>
                                        </h5>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-primary edit-category"
                                                    data-category-id="<?= $category['id'] ?>"
                                                    data-category-name="<?= htmlspecialchars($category['name']) ?>"
                                                    data-category-description="<?= htmlspecialchars($category['description']) ?>"
                                                    data-category-image="<?= htmlspecialchars($category['image'] ?? '') ?>"
                                                    data-category-banner="<?= htmlspecialchars($category['Banner_categoria'] ?? '') ?>"
                                                    <?= !$can_add_content ? 'disabled' : '' ?>>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-category"
                                                    data-category-id="<?= $category['id'] ?>"
                                                    data-category-name="<?= htmlspecialchars($category['name']) ?>"
                                                    data-category-product-count="<?= $category['product_count'] ?>"
                                                    <?= !$can_add_content ? 'disabled' : '' ?>>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($category['description'])): ?>
                                        <p class="text-muted mb-3"><?= htmlspecialchars($category['description']) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (empty($products_by_category[$category['id']])): ?>
                                        <p class="text-muted text-center my-3">No hay productos en esta categoría</p>
                                    <?php else: ?>
                                        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3">
                                            <?php foreach ($products_by_category[$category['id']] as $product): ?>
                                                <div class="col">
                                                    <div class="card product-card h-100">
                                                        <?php if ($product['image']): ?>
                                                            <img src="/uploads/<?= $product['image'] ?>" 
                                                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                                                 class="card-img-top product-image">
                                                        <?php endif; ?>
                                                        <div class="card-body">
                                                            <h6 class="card-title">
                                                                <?= htmlspecialchars($product['name']) ?>
                                                                <?php if ($product['is_featured']): ?>
                                                                    <i class="fas fa-star text-warning"></i>
                                                                <?php endif; ?>
                                                            </h6>
                                                        
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="h5 mb-0"><?= formatCurrency($product['price']) ?></span>
                                                                <div class="form-check form-switch">
                                                                    
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer bg-transparent border-top-0">
                                                            <div class="btn-group w-100">
                                                                <button type="button" class="btn btn-outline-primary edit-product"
                                                                        data-product-id="<?= $product['id'] ?>"
                                                                        data-product-category="<?= $product['category_id'] ?>"
                                                                        data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                                                        data-product-description="<?= htmlspecialchars($product['description']) ?>"
                                                                        data-product-price="<?= $product['price'] ?>"
                                                                        data-product-available="<?= $product['is_available'] ?>"
                                                                        data-product-featured="<?= $product['is_featured'] ?>"
                                                                        data-product-image="<?= htmlspecialchars($product['image'] ?? '') ?>"
                                                                        <?= !$can_add_content ? 'disabled' : '' ?>>
                                                                    <i class="fas fa-edit"></i> Editar
                                                                </button>
                                                                <button type="button" class="btn btn-outline-info manage-options"
                                                                        data-product-id="<?= $product['id'] ?>"
                                                                        data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                                                        onclick="showAddOptionModal(<?= $product['id'] ?>)"
                                                                        <?= !$can_add_content ? 'disabled' : '' ?>>
                                                                    <i class="fas fa-cog"></i> Opciones
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger delete-product"
                                                                        data-product-id="<?= $product['id'] ?>"
                                                                        data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                                                        <?= !$can_add_content ? 'disabled' : '' ?>>
                                                                    <i class="fas fa-trash"></i> Eliminar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Modals -->
    <?php include 'includes/menu_modals.php'; ?>
    <?php include 'includes/product-options-modal.php'; ?>

    <!-- Modal de Confirmación para Eliminar Categoría -->
    <div class="modal fade" id="deleteCategoryConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar la categoría "<span id="deleteCategoryName"></span>"?</p>
                    <div id="deleteCategoryWarning" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>¡Atención!</strong> Esta categoría contiene <span id="deleteCategoryProductCount"></span> producto(s) que también serán eliminados.
                    </div>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteCategory">
                        <i class="fas fa-trash"></i> Eliminar Categoría
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <!-- Definir variables globales primero -->
    <script>
        // Definir BASE_URL y CSRF token como variables globales
        window.BASE_URL = '<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>';
        window.CSRF_TOKEN = '<?= $csrf_token ?>';
        
        // Forzar HTTPS si la página actual está en HTTPS para evitar Mixed Content
        if (window.location.protocol === 'https:' && window.BASE_URL && window.BASE_URL.startsWith('http:')) {
            window.BASE_URL = window.BASE_URL.replace('http:', 'https:');
            console.log('BASE_URL actualizado a HTTPS:', window.BASE_URL);
        }
        
        // Si BASE_URL no está definido, intentar detectarlo automáticamente
        if (!window.BASE_URL || window.BASE_URL === '') {
            const protocol = window.location.protocol;
            const host = window.location.host;
            const pathname = window.location.pathname;
            
            // Detectar el directorio base
            let basePath = '';
            if (pathname.includes('/restaurante/')) {
                basePath = pathname.substring(0, pathname.indexOf('/restaurante/'));
            } else if (pathname.includes('/super_admin/')) {
                basePath = pathname.substring(0, pathname.indexOf('/super_admin/'));
            } else {
                basePath = pathname.substring(0, pathname.lastIndexOf('/'));
            }
            
            window.BASE_URL = protocol + '//' + host + basePath;
            console.log('BASE_URL detectado automáticamente:', window.BASE_URL);
        }
        
        // Verificar que las variables estén definidas
        if (!window.BASE_URL) {
            console.error('BASE_URL no está definido correctamente');
        }
        if (!window.CSRF_TOKEN) {
            console.error('CSRF_TOKEN no está definido correctamente');
        }
        
        // Función para mostrar alertas
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i> 
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insertar al inicio del contenido principal
            const mainContent = document.querySelector('.p-4');
            if (mainContent) {
                mainContent.insertBefore(alertDiv, mainContent.firstChild);
                
                // Auto-remover después de 5 segundos
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
        }
    </script>
    
    <!-- Cargar scripts después de definir las variables globales -->
    <script src="/restaurante/assets/js/menu-options.js"></script>
    <script src="/restaurante/js/products.js"></script>
    <script src="/restaurante/js/categories.js"></script>
    <script>
        // Verificar que las variables globales estén disponibles
        if (!window.BASE_URL || !window.CSRF_TOKEN) {
            console.error('Variables globales no disponibles:', {
                BASE_URL: window.BASE_URL,
                CSRF_TOKEN: window.CSRF_TOKEN
            });
        } else {
            console.log('Variables globales disponibles:', {
                BASE_URL: window.BASE_URL,
                CSRF_TOKEN: window.CSRF_TOKEN ? 'Definido' : 'No definido'
            });
        }

        // Inicializar el gestor de opciones del menú
        let menuOptionsInitialized = false;
        
        function initializeMenuOptions() {
            if (menuOptionsInitialized) {
                console.log('MenuOptionsManager ya está inicializado');
                return;
            }

            console.log('Iniciando inicialización de MenuOptionsManager...');
            try {
                if (!window.BASE_URL) {
                    throw new Error('BASE_URL no está definido');
                }
                if (!window.CSRF_TOKEN) {
                    throw new Error('Token CSRF no está disponible');
                }

                window.menuOptions = new MenuOptionsManager(window.BASE_URL, window.CSRF_TOKEN);
                menuOptionsInitialized = true;
                console.log('MenuOptionsManager inicializado exitosamente:', window.menuOptions);
            } catch (error) {
                console.error('Error al inicializar MenuOptionsManager:', error);
                alert('Error al inicializar el sistema de opciones: ' + error.message);
            }
        }

        // Intentar inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM cargado, intentando inicializar MenuOptionsManager...');
            initializeMenuOptions();
        });

        // También intentar inicializar después de un breve retraso por si acaso
        setTimeout(function() {
            if (!menuOptionsInitialized) {
                console.log('Intentando inicializar MenuOptionsManager después del timeout...');
                initializeMenuOptions();
            }
        }, 1000);
        
        // Función para mostrar el modal de opciones
        function showAddOptionModal(productId) {
            console.log('=== Iniciando showAddOptionModal ===');
            console.log('Product ID recibido:', productId);
            
            // Intentar inicializar si aún no está inicializado
            if (!menuOptionsInitialized) {
                console.log('MenuOptionsManager no inicializado, intentando inicializar...');
                initializeMenuOptions();
            }
            
            // Verificar que el gestor está inicializado
            if (!window.menuOptions) {
                console.error('MenuOptionsManager no está inicializado después de intentar inicializarlo');
                alert('Error: El sistema de opciones no está inicializado correctamente. Por favor, recarga la página.');
                return;
            }
            console.log('MenuOptionsManager está inicializado:', window.menuOptions);
            
            // Asegurarnos de que el modal existe
            const modalElement = document.getElementById('productOptionsModal');
            if (!modalElement) {
                console.error('No se encontró el elemento del modal productOptionsModal');
                return;
            }
            console.log('Modal encontrado:', modalElement);
            
            // Establecer el ID del producto actual
            window.menuOptions.currentProductId = productId;
            console.log('ID del producto establecido:', productId);
            
            // Mostrar el modal usando el método de la clase
            console.log('Intentando mostrar el modal usando MenuOptionsManager...');
            window.menuOptions.showOptionsModal(productId);
        }

        // Inicializar Sortable para las categorías
        document.addEventListener('DOMContentLoaded', function() {
            const categoriesContainer = document.getElementById('categories-container');
            if (categoriesContainer) {
                new Sortable(categoriesContainer, {
                    animation: 150,
                    handle: '.category-header',
                    onEnd: function(evt) {
                        const orders = Array.from(evt.to.children).map((el, index) => ({
                            id: el.dataset.categoryId,
                            order: index + 1
                        }));
                        
                        // Enviar nuevo orden al servidor
                        fetch(`${window.BASE_URL}/restaurante/ajax/update_category_order.php`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                csrf_token: '<?= $csrf_token ?>',
                                orders: orders
                            })
                        }).then(response => response.json())
                          .then(data => {
                              if (data.success) {
                                  // Mostrar mensaje de éxito
                                  const alert = document.createElement('div');
                                  alert.className = 'alert alert-success alert-dismissible fade show';
                                  alert.innerHTML = `
                                      <i class="fas fa-check-circle"></i> Orden actualizado correctamente
                                      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                  `;
                                  document.querySelector('.p-4').insertBefore(alert, document.querySelector('.card'));
                              }
                          });
                    }
                });
            }
        });

        // Función para actualizar el estado del producto
        function updateProductStatus(productId, isAvailable) {
            fetch(`${window.BASE_URL}/restaurante/ajax/update_product_status.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: '<?= $csrf_token ?>',
                    product_id: productId,
                    is_available: isAvailable
                })
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      // Mostrar mensaje de éxito
                      const alert = document.createElement('div');
                      alert.className = 'alert alert-success alert-dismissible fade show';
                      alert.innerHTML = `
                          <i class="fas fa-check-circle"></i> Estado del producto actualizado
                          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      `;
                      document.querySelector('.p-4').insertBefore(alert, document.querySelector('.card'));
                  }
              });
        }

        // Manejar edición de categoría
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-category').forEach(button => {
                button.addEventListener('click', function() {
                    console.log('Botón de edición clickeado');
                    const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
                    
                    // Obtener los datos del botón
                    const categoryId = this.dataset.categoryId;
                    const categoryName = this.dataset.categoryName;
                    const categoryDescription = this.dataset.categoryDescription;
                    const categoryImage = this.dataset.categoryImage;
                    const categoryBanner = this.dataset.categoryBanner;
                    
                    console.log('Datos de la categoría:', {
                        id: categoryId,
                        name: categoryName,
                        description: categoryDescription,
                        image: categoryImage,
                        banner: categoryBanner
                    });

                    // Establecer valores en el formulario
                    document.getElementById('edit_category_id').value = categoryId;
                    document.getElementById('edit_category_name').value = categoryName;
                    document.getElementById('edit_category_description').value = categoryDescription;
                    
                    // Establecer token CSRF
                    if (window.CSRF_TOKEN) {
                        document.getElementById('editCategoryCsrfToken').value = window.CSRF_TOKEN;
                    }

                    // Mostrar imágenes actuales si existen
                    const currentImage = document.getElementById('current_category_image');
                    const currentBanner = document.getElementById('current_category_banner');
                    const editImagePreview = document.getElementById('editImagePreview');
                    const editBannerPreview = document.getElementById('editBannerPreview');

                    if (categoryImage) {
                        currentImage.src = `${window.BASE_URL}/uploads/${categoryImage}`;
                        document.getElementById('current_category_image_path').value = categoryImage;
                        editImagePreview.style.display = 'block';
                    } else {
                        currentImage.src = '';
                        document.getElementById('current_category_image_path').value = '';
                        editImagePreview.style.display = 'none';
                    }

                    if (categoryBanner) {
                        currentBanner.src = `${window.BASE_URL}/uploads/${categoryBanner}`;
                        document.getElementById('current_category_banner_path').value = categoryBanner;
                        editBannerPreview.style.display = 'block';
                    } else {
                        currentBanner.src = '';
                        document.getElementById('current_category_banner_path').value = '';
                        editBannerPreview.style.display = 'none';
                    }

                    // Mostrar el modal
                    modal.show();
                });
            });
        });
        
        // Manejar eliminación de categoría
        document.addEventListener('DOMContentLoaded', function() {
            let categoryToDelete = null;
            const deleteCategoryModal = new bootstrap.Modal(document.getElementById('deleteCategoryConfirmModal'));
            
            document.querySelectorAll('.delete-category').forEach(button => {
                button.addEventListener('click', function() {
                    const categoryId = this.dataset.categoryId;
                    const categoryName = this.dataset.categoryName;
                    const productCount = parseInt(this.dataset.categoryProductCount) || 0;
                    
                    // Guardar la referencia al botón que inició la eliminación
                    categoryToDelete = this;
                    
                    // Actualizar el modal con la información de la categoría
                    document.getElementById('deleteCategoryName').textContent = categoryName;
                    
                    // Mostrar advertencia si hay productos
                    const warningDiv = document.getElementById('deleteCategoryWarning');
                    const productCountSpan = document.getElementById('deleteCategoryProductCount');
                    
                    if (productCount > 0) {
                        productCountSpan.textContent = productCount;
                        warningDiv.style.display = 'block';
                    } else {
                        warningDiv.style.display = 'none';
                    }
                    
                    // Mostrar el modal
                    deleteCategoryModal.show();
                });
            });
            
            // Manejar la confirmación de eliminación
            document.getElementById('confirmDeleteCategory').addEventListener('click', function() {
                if (!categoryToDelete) return;
                
                const categoryId = categoryToDelete.dataset.categoryId;
                const categoryName = categoryToDelete.dataset.categoryName;
                
                const formData = new FormData();
                formData.append('csrf_token', '<?= $csrf_token ?>');
                formData.append('category_id', categoryId);
                
                console.log('Enviando solicitud para eliminar categoría:', {
                    categoryId,
                    categoryName,
                    csrfToken: '<?= $csrf_token ?>'
                });
                
                fetch(`${window.BASE_URL}/restaurante/ajax/delete_category.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Respuesta recibida:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Datos recibidos:', data);
                    
                    // Cerrar el modal
                    deleteCategoryModal.hide();
                    
                    if (data.success) {
                        // Eliminar la categoría del DOM
                        const categoryElement = categoryToDelete.closest('.card[data-category-id]');
                        if (categoryElement) {
                            categoryElement.remove();
                            console.log('Categoría eliminada del DOM');
                        } else {
                            console.error('No se encontró el elemento de la categoría en el DOM');
                        }
                        
                        // Mostrar mensaje de éxito
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success alert-dismissible fade show';
                        alert.innerHTML = `
                            <i class="fas fa-check-circle"></i> ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.querySelector('.p-4').insertBefore(alert, document.querySelector('.card'));
                    } else {
                        // Mostrar mensaje de error
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-danger alert-dismissible fade show';
                        alert.innerHTML = `
                            <i class="fas fa-exclamation-circle"></i> ${data.message || 'Error al eliminar la categoría'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.querySelector('.p-4').insertBefore(alert, document.querySelector('.card'));
                    }
                    
                    // Limpiar la referencia
                    categoryToDelete = null;
                })
                .catch(error => {
                    console.error('Error en la solicitud:', error);
                    // Cerrar el modal
                    deleteCategoryModal.hide();
                    
                    // Mostrar mensaje de error genérico
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-danger alert-dismissible fade show';
                    alert.innerHTML = `
                        <i class="fas fa-exclamation-circle"></i> Error al eliminar la categoría: ${error.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.p-4').insertBefore(alert, document.querySelector('.card'));
                    
                    // Limpiar la referencia
                    categoryToDelete = null;
                });
            });
        });
        
        // Manejar eliminación de producto
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.delete-product').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
                    document.getElementById('delete_product_id').value = this.dataset.productId;
                    document.getElementById('delete_product_name').textContent = this.dataset.productName;
                    modal.show();
                });
            });
        });

        // Función de búsqueda en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchProducts');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase().trim();
                    const productCards = document.querySelectorAll('.product-card');
                    const categories = document.querySelectorAll('.card[data-category-id]');
                    
                    let hasVisibleProducts = false;
                    
                    // Ocultar/mostrar categorías basado en si tienen productos visibles
                    categories.forEach(category => {
                        const categoryProducts = category.querySelectorAll('.product-card');
                        let categoryHasVisibleProducts = false;
                        
                        categoryProducts.forEach(product => {
                            const productName = product.querySelector('.card-title').textContent.toLowerCase();
                            const productDescription = product.querySelector('.text-muted')?.textContent.toLowerCase() || '';
                            
                            if (productName.includes(searchTerm) || productDescription.includes(searchTerm)) {
                                product.style.display = '';
                                categoryHasVisibleProducts = true;
                                hasVisibleProducts = true;
                            } else {
                                product.style.display = 'none';
                            }
                        });
                        
                        // Mostrar/ocultar la categoría completa
                        category.style.display = categoryHasVisibleProducts ? '' : 'none';
                    });
                    
                    // Mostrar mensaje si no hay resultados
                    const noResultsMessage = document.getElementById('noSearchResults');
                    if (!hasVisibleProducts && searchTerm !== '') {
                        if (!noResultsMessage) {
                            const message = document.createElement('div');
                            message.id = 'noSearchResults';
                            message.className = 'alert alert-info text-center my-4';
                            message.innerHTML = '<i class="fas fa-info-circle"></i> No se encontraron productos que coincidan con la búsqueda';
                            document.getElementById('categories-container').insertBefore(message, document.getElementById('categories-container').firstChild);
                        }
                    } else if (noResultsMessage) {
                        noResultsMessage.remove();
                    }
                });
            }
        });
    </script>
</body>
</html> 
