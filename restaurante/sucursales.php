<?php
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../classes/Restaurant.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    redirect(BASE_URL . '/restaurante/login.php');
}

$restaurant_id = $_SESSION['restaurant_id'];
$restaurant = new Restaurant($conn);
$restaurant_data = $restaurant->getRestaurant($restaurant_id);
$restaurant = $restaurant_data;

// Verificar que NO sea una sucursal (solo restaurantes principales pueden crear sucursales)
if (isset($restaurant_data['is_branch']) && $restaurant_data['is_branch'] == 1) {
    $_SESSION['error'] = 'Las sucursales no pueden crear otras sucursales. Solo el restaurante principal puede gestionar sucursales.';
    redirect(BASE_URL . '/restaurante/dashboard.php');
}

// Verificar si el restaurante tiene el plan Premium o Premium Pro
if ($restaurant_data['current_plan_id'] != 3 && $restaurant_data['current_plan_id'] != 4) {
    $_SESSION['error'] = 'Esta funcionalidad solo está disponible para los planes Premium y Premium Pro.';
    redirect(BASE_URL . '/restaurante/planes.php');
}

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);

try {
    // Verificar que restaurant_id esté definido
    if (!isset($restaurant_id) || empty($restaurant_id)) {
        throw new Exception("ID de restaurante no válido");
    }

    // Obtener las sucursales del restaurante con consulta más simple
    $stmt = $conn->prepare("
        SELECT r.*, p.name as plan_name, p.max_branches
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        WHERE r.id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta principal");
    }
    
    $stmt->execute([$restaurant_id]);
    $restaurant_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$restaurant_info) {
        throw new Exception("Restaurante no encontrado");
    }

    // Obtener el conteo de sucursales en una consulta separada
    $stmt = $conn->prepare("
        SELECT COUNT(*) as current_branches
        FROM restaurants 
        WHERE parent_restaurant_id = ? AND is_branch = 1
    ");
    $stmt->execute([$restaurant_id]);
    $branch_count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $restaurant_info['current_branches'] = $branch_count['current_branches'] ?? 0;

    // Obtener las sucursales existentes
    $stmt = $conn->prepare("
        SELECT * FROM restaurants 
        WHERE parent_restaurant_id = ? AND is_branch = 1 
        ORDER BY branch_number ASC
    ");
    $stmt->execute([$restaurant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $can_create_branch = $restaurant_info['current_branches'] < $restaurant_info['max_branches'];

} catch (PDOException $e) {
    error_log("Error en sucursales.php: " . $e->getMessage());
    $error = "Error al cargar la información de sucursales.";
    $restaurant_info = null;
    $branches = [];
    $can_create_branch = false;
} catch (Exception $e) {
    error_log("Error en sucursales.php: " . $e->getMessage());
    $error = $e->getMessage();
    $restaurant_info = null;
    $branches = [];
    $can_create_branch = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Sucursales - <?= htmlspecialchars($restaurant_data['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- <link href="../assets/css/restaurant-dashboard.css" rel="stylesheet"> -->
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background: linear-gradient(135deg,rgba(138, 106, 211, 0.58) 0%,rgb(248, 248, 248) 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            margin: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0.5rem 0 0 0;
            position: relative;
            z-index: 1;
        }

        .content-wrapper {
            padding: 2rem;
        }

        .plan-info-section {
            margin-bottom: 2rem;
        }

        .plan-info-card,
        .how-it-works-card {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .plan-info-card::before,
        .how-it-works-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .plan-info-card:hover,
        .how-it-works-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .plan-info-card::before {
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
        }

        .how-it-works-card::before {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .plan-info-header,
        .how-it-works-header {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
        }

        .plan-icon,
        .how-it-works-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
            color: white;
        }

        .plan-icon {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .how-it-works-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .plan-info-content,
        .how-it-works-content {
            flex: 1;
        }

        .plan-title,
        .how-it-works-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }

        .plan-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 1rem;
        }

        .plan-details {
            display: flex;
            flex-direction: column;
            gap: 0rem;
        }

        .plan-stat {
            font-size: 1.1rem;
            color: #64748b;
        }

        .how-it-works-text {
            font-size: 1.1rem;
            color: #64748b;
            line-height: 1.6;
            margin: 0;
        }

        .stats-grid {
            display: none;
        }

        .stat-card {
            display: none;
        }

        .stat-icon {
            display: none;
        }

        .stat-title {
            display: none;
        }

        .stat-value {
            display: none;
        }

        .progress-container {
            margin: 1rem 0;
        }

        .progress {
            height: 8px;
            border-radius: 10px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        .create-branch-btn {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .create-branch-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .create-branch-btn:hover::before {
            left: 100%;
        }

        .create-branch-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .branches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .branch-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .branch-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .branch-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .branch-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .branch-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }

        .status-inactive {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
        }

        .branch-number {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .branch-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0.5rem 0;
        }

        .branch-info {
            padding: 1.5rem;
        }

        .branch-detail {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
            color: #64748b;
        }

        .branch-detail i {
            width: 20px;
            margin-right: 0.8rem;
            color: var(--primary-color);
        }

        .branch-actions {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .btn-modern {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-secondary-modern {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-danger-modern {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 2px dashed var(--border-color);
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h4 {
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #94a3b8;
            margin-bottom: 2rem;
        }

        .alert-modern {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success-modern {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .alert-danger-modern {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                border-radius: 16px;
            }

            .page-header {
                padding: 1.5rem;
                border-radius: 16px 16px 0 0;
            }

            .page-title {
                font-size: 2rem;
            }

            .content-wrapper {
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .branches-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .branch-actions {
                flex-direction: column;
            }

            .btn-modern {
                justify-content: center;
            }
        }

        .location-button.secondary:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Animaciones para modales de éxito y error */
        .success-animation, .error-animation {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .success-circle, .error-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            position: relative;
            animation: pulse 2s infinite;
        }

        .success-circle {
            background: linear-gradient(135deg, #28a745, #20c997);
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.4);
        }

        .error-circle {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
            box-shadow: 0 0 20px rgba(220, 53, 69, 0.4);
        }

        .success-circle::before, .error-circle::before {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            border-radius: 50%;
            border: 2px solid transparent;
            animation: ripple 1.5s infinite;
        }

        .success-circle::before {
            border-color: rgba(40, 167, 69, 0.3);
        }

        .error-circle::before {
            border-color: rgba(220, 53, 69, 0.3);
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        @keyframes ripple {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            100% {
                transform: scale(1.3);
                opacity: 0;
            }
        }

        /* Animación de entrada para el modal */
        .modal.fade .modal-dialog {
            transform: scale(0.8);
            transition: transform 0.3s ease-out;
        }

        .modal.show .modal-dialog {
            transform: scale(1);
        }

        /* Efecto de confeti para éxito */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #f39c12;
            animation: confetti-fall 3s linear infinite;
            z-index: 9999;
        }

        @keyframes confetti-fall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* Animación de shake para error */
        .shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Efecto de brillo para botones de éxito */
        .btn-success {
            position: relative;
            overflow: hidden;
        }

        .btn-success::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn-success:hover::before {
            left: 100%;
        }

        /* Estilos para el modal de información del plan */
        #planInfoModal .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: var(--shadow-lg);
        }

        #planInfoModal .modal-header {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border-radius: 16px 16px 0 0;
            border-bottom: none;
        }

        #planInfoModal .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
        }

        #planInfoModal .btn-close {
            filter: brightness(0) invert(1);
        }

        .how-it-works-details {
            padding: 1rem 0;
        }

        .works-overview {
            margin-bottom: 2rem;
        }

        .works-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 12px;
            border: 1px solid #f59e0b;
        }

        .works-icon-large {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .works-title-large {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .works-description {
            font-size: 1.1rem;
            color: #64748b;
            margin: 0;
        }

        .works-explanation {
            margin-bottom: 2rem;
        }

        .explanation-title,
        .steps-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .plan-specific-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .plan-type {
            font-size: 1.3rem;
            font-weight: 700;
            color: #f59e0b;
            margin-bottom: 1rem;
        }

        .plan-description-detailed {
            font-size: 1.1rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .features-explained {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .feature-explained {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .feature-explained-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            flex-shrink: 0;
        }

        .feature-explained-content h6 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .feature-explained-content p {
            font-size: 0.95rem;
            color: #64748b;
            line-height: 1.5;
            margin: 0;
        }

        .works-steps {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #0ea5e9;
        }

        .steps-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .step-content h6 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .step-content p {
            font-size: 0.95rem;
            color: #64748b;
            line-height: 1.5;
            margin: 0;
        }

        .btn-info-details {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.4s ease;
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
            margin-top: 1.5rem;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-info-details::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }

        .btn-info-details:hover::before {
            left: 100%;
        }

        .btn-info-details:hover {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
            color: white;
        }

        .btn-info-details:active {
            transform: translateY(-1px) scale(0.98);
        }

        .btn-info-details i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="main-container">
                    <!-- Header de la página -->
                    <div class="page-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="page-title">
                                    <i class="fas fa-store"></i> Gestionar Sucursales
                                </h1>
                                <p class="page-subtitle">Expande tu negocio con sucursales adicionales</p>
                            </div>
                            <div>
                                <?php if ($can_create_branch): ?>
                                <button type="button" class="btn create-branch-btn" data-bs-toggle="modal" data-bs-target="#createBranchModal">
                                    <i class="fas fa-plus"></i> Crear Nueva Sucursal
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="content-wrapper">
                        <?php if ($message): ?>
                            <div class="alert alert-success-modern alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger-modern alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Información del plan -->
                        <div class="plan-info-section">
                            <div class="plan-info-card">
                                <div class="plan-info-header">
                                    <div class="plan-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="plan-info-content">
                                        <h5 class="plan-title">Información del Plan</h5>
                                        <?php if ($restaurant_info): ?>
                                        <div class="plan-name"><?= htmlspecialchars($restaurant_info['plan_name'] ?? 'No disponible') ?></div>
                                        <div class="plan-details">
                                            <span class="plan-stat">
                                                <strong>Sucursales:</strong> <?= $restaurant_info['current_branches'] ?? 0 ?> de <?= $restaurant_info['max_branches'] ?? 0 ?> disponibles
                                            </span>
                                            <div class="progress-container">
                                                <div class="progress">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?= ($restaurant_info['max_branches'] > 0) ? (($restaurant_info['current_branches'] ?? 0) / $restaurant_info['max_branches']) * 100 : 0 ?>%">
                                                    </div>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= ($restaurant_info['max_branches'] ?? 0) - ($restaurant_info['current_branches'] ?? 0) ?> sucursales disponibles
                                            </small>
                                            <button type="button" class="btn btn-info-details" data-bs-toggle="modal" data-bs-target="#planInfoModal">
                                                <i class="fas fa-lightbulb"></i> ¿Cómo funciona?
                                            </button>
                                        </div>
                                        <?php else: ?>
                                        <p class="text-danger">
                                            <i class="fas fa-exclamation-triangle"></i> Error al cargar la información del plan
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            
                        </div>

                        <!-- Lista de sucursales -->
                        <?php if (empty($branches)): ?>
                            <div class="empty-state">
                                <i class="fas fa-store"></i>
                                <h4>No tienes sucursales creadas</h4>
                                <p>Crea tu primera sucursal para expandir tu negocio</p>
                                <?php if ($can_create_branch): ?>
                                    <button type="button" class="btn create-branch-btn" data-bs-toggle="modal" data-bs-target="#createBranchModal">
                                        <i class="fas fa-plus"></i> Crear Primera Sucursal
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="branches-grid">
                                <?php foreach ($branches as $branch): ?>
                                    <div class="branch-card">
                                        <div class="branch-header">
                                            <span class="branch-status <?= $branch['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $branch['is_active'] ? 'Activa' : 'Inactiva' ?>
                                            </span>
                                            
                                            <span class="branch-number">
                                                Sucursal <?= $branch['branch_number'] ?>
                                            </span>
                                            <h5 class="branch-name"><?= htmlspecialchars($branch['name']) ?></h5>
                                        </div>
                                        
                                        <div class="branch-info">
                                            <div class="branch-detail">
                                                <i class="fas fa-envelope"></i>
                                                <?= htmlspecialchars($branch['email']) ?>
                                            </div>
                                            <?php if ($branch['phone']): ?>
                                            <div class="branch-detail">
                                                <i class="fas fa-phone"></i>
                                                <?= htmlspecialchars($branch['phone']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($branch['address']): ?>
                                            <div class="branch-detail">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($branch['address']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="branch-actions">
                                            <a href="/<?= $branch['slug'] ?>" target="_blank" class="btn-modern btn-primary-modern">
                                                <i class="fas fa-external-link-alt"></i> Ver Menú
                                            </a>
                                            <button type="button" class="btn-modern btn-secondary-modern" onclick="editBranch(<?= $branch['id'] ?>)">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                            <button type="button" class="btn-modern btn-danger-modern" onclick="deleteBranch(<?= $branch['id'] ?>)">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para crear sucursal -->
    <div class="modal fade" id="createBranchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus"></i> Crear Nueva Sucursal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createBranchForm" action="ajax/create_branch.php" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="branch_name" class="form-label">Nombre de la Sucursal *</label>
                                    <input type="text" class="form-control" id="branch_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="branch_email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="branch_email" name="email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="branch_password" class="form-label">Contraseña *</label>
                                    <input type="password" class="form-control" id="branch_password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="branch_phone" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="branch_phone" name="phone">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="branch_address" class="form-label">Dirección</label>
                            <textarea class="form-control" id="branch_address" name="address" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="branch_description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="branch_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Crear Sucursal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar sucursal -->
    <div class="modal fade" id="editBranchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Editar Sucursal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editBranchForm" action="ajax/edit_branch.php" method="POST">
                    <input type="hidden" id="edit_branch_id" name="branch_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_branch_name" class="form-label">Nombre de la Sucursal *</label>
                                    <input type="text" class="form-control" id="edit_branch_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_branch_email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="edit_branch_email" name="email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_branch_password" class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="edit_branch_password" name="password" placeholder="Dejar vacío para mantener la actual">
                                    <small class="form-text text-muted">Mínimo 6 caracteres</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_branch_phone" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="edit_branch_phone" name="phone">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_branch_address" class="form-label">Dirección</label>
                            <textarea class="form-control" id="edit_branch_address" name="address" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_branch_description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_branch_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para confirmar eliminación de sucursal -->
    <div class="modal fade" id="deleteBranchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-trash-alt text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="text-center mb-3">¿Estás seguro de que quieres eliminar esta sucursal?</h6>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Advertencia:</strong> Esta acción no se puede deshacer. Se eliminarán todos los datos asociados a la sucursal, incluyendo:
                        <ul class="mb-0 mt-2">
                            <li>Menú y productos de la sucursal</li>
                            <li>Pedidos y estadísticas</li>
                            <li>Configuraciones personalizadas</li>
                        </ul>
                    </div>
                    <div class="text-center">
                        <p class="mb-0"><strong>Sucursal a eliminar:</strong></p>
                        <p class="text-muted" id="deleteBranchName"></p>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="confirmDeleteCheckbox">
                        <label class="form-check-label" for="confirmDeleteCheckbox">
                            <strong>Entiendo que esta acción es irreversible</strong> y que todos los datos de la sucursal serán eliminados permanentemente.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                        <i class="fas fa-trash"></i> Sí, Eliminar Sucursal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de éxito con animaciones -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body text-center p-5">
                    <div class="success-animation mb-4">
                        <div class="success-circle">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                    <h4 class="text-success mb-3" id="successTitle">¡Operación Exitosa!</h4>
                    <p class="text-muted mb-4" id="successMessage">La operación se ha completado correctamente.</p>
                    <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
                        <i class="fas fa-thumbs-up"></i> ¡Perfecto!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de error con animaciones -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body text-center p-5">
                    <div class="error-animation mb-4">
                        <div class="error-circle">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <h4 class="text-danger mb-3">¡Error!</h4>
                    <p class="text-muted mb-4" id="errorMessage">Ha ocurrido un error durante la operación.</p>
                    <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Entendido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de información detallada del plan -->
    <div class="modal fade" id="planInfoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-lightbulb"></i> ¿Cómo funciona?
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="how-it-works-details">
                        <div class="works-overview">
                            <div class="works-header">
                                <div class="works-icon-large">
                                    <i class="fas fa-lightbulb"></i>
                                </div>
                                <div class="works-info">
                                    <h4 class="works-title-large">Sistema de Sucursales</h4>
                                    <p class="works-description">
                                        Entiende cómo funciona el sistema de sucursales y cómo puedes aprovechar al máximo tu plan.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="works-explanation">
                            <h5 class="explanation-title">
                                <i class="fas fa-info-circle"></i> Explicación Detallada
                            </h5>
                            <div class="explanation-content">
                                <?php if ($restaurant_info['current_plan_id'] == 4): ?>
                                <div class="plan-specific-info">
                                    <h6 class="plan-type">Plan Premium Pro</h6>
                                    <p class="plan-description-detailed">
                                        Con el plan Premium Pro puedes crear hasta <strong>3 sucursales adicionales</strong>. 
                                        Cada sucursal tendrá su propio panel de administración y URL única.
                                    </p>
                                    
                                    <div class="features-explained">
                                        <div class="feature-explained">
                                            <div class="feature-explained-icon">
                                                <i class="fas fa-store"></i>
                                            </div>
                                            <div class="feature-explained-content">
                                                <h6>Panel Independiente</h6>
                                                <p>Cada sucursal tiene su propio panel de administración donde puedes gestionar menús, productos, pedidos y configuraciones específicas de esa ubicación.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="feature-explained">
                                            <div class="feature-explained-icon">
                                                <i class="fas fa-link"></i>
                                            </div>
                                            <div class="feature-explained-content">
                                                <h6>URL Única</h6>
                                                <p>Cada sucursal tendrá su propia URL personalizada, permitiendo a los clientes acceder directamente al menú de esa ubicación específica.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="feature-explained">
                                            <div class="feature-explained-icon">
                                                <i class="fas fa-chart-bar"></i>
                                            </div>
                                            <div class="feature-explained-content">
                                                <h6>Estadísticas Individuales</h6>
                                                <p>Obtén reportes y estadísticas detalladas para cada sucursal por separado, permitiendo un análisis granular del rendimiento.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="feature-explained">
                                            <div class="feature-explained-icon">
                                                <i class="fas fa-cogs"></i>
                                            </div>
                                            <div class="feature-explained-content">
                                                <h6>Gestión Centralizada</h6>
                                                <p>Desde este panel principal puedes gestionar todas las sucursales, crear nuevas, editar configuraciones y monitorear el estado general.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="plan-specific-info">
                                    <h6 class="plan-type">Plan Premium</h6>
                                    <p class="plan-description-detailed">
                                        Con el plan Premium puedes crear hasta <strong>1 sucursal adicional</strong>. 
                                        Cada sucursal tendrá su propio panel de administración y URL única.
                                    </p>
                                    
                                    <div class="features-explained">
                                        <div class="feature-explained">
                                            <div class="feature-explained-icon">
                                                <i class="fas fa-store"></i>
                                            </div>
                                            <div class="feature-explained-content">
                                                <h6>Panel Independiente</h6>
                                                <p>Tu sucursal adicional tendrá su propio panel de administración donde puedes gestionar menús, productos, pedidos y configuraciones específicas.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="feature-explained">
                                            <div class="feature-explained-icon">
                                                <i class="fas fa-link"></i>
                                            </div>
                                            <div class="feature-explained-content">
                                                <h6>URL Única</h6>
                                                <p>Tu sucursal tendrá su propia URL personalizada, permitiendo a los clientes acceder directamente al menú de esa ubicación específica.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="feature-explained">
                                            <div class="feature-explained-icon">
                                                <i class="fas fa-chart-bar"></i>
                                            </div>
                                            <div class="feature-explained-content">
                                                <h6>Estadísticas Individuales</h6>
                                                <p>Obtén reportes y estadísticas detalladas para tu sucursal, permitiendo un análisis específico del rendimiento.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="feature-explained">
                                            <div class="feature-explained-icon">
                                                <i class="fas fa-cogs"></i>
                                            </div>
                                            <div class="feature-explained-content">
                                                <h6>Gestión Centralizada</h6>
                                                <p>Desde este panel principal puedes gestionar tu sucursal, editar configuraciones y monitorear el estado general.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="works-steps">
                            <h5 class="steps-title">
                                <i class="fas fa-list-ol"></i> Proceso de Creación
                            </h5>
                            <div class="steps-list">
                                <div class="step-item">
                                    <div class="step-number">1</div>
                                    <div class="step-content">
                                        <h6>Crear Sucursal</h6>
                                        <p>Haz clic en "Crear Nueva Sucursal" y completa la información básica: nombre, email, contraseña y datos de contacto.</p>
                                    </div>
                                </div>
                                
                                <div class="step-item">
                                    <div class="step-number">2</div>
                                    <div class="step-content">
                                        <h6>Configurar Menú</h6>
                                        <p>Accede al panel de la sucursal y configura el menú, productos, categorías y precios específicos para esa ubicación.</p>
                                    </div>
                                </div>
                                
                                <div class="step-item">
                                    <div class="step-number">3</div>
                                    <div class="step-content">
                                        <h6>Personalizar</h6>
                                        <p>Personaliza los colores, logo y configuraciones visuales para que la sucursal tenga su propia identidad.</p>
                                    </div>
                                </div>
                                
                                <div class="step-item">
                                    <div class="step-number">4</div>
                                    <div class="step-content">
                                        <h6>Compartir URL</h6>
                                        <p>Comparte la URL única de la sucursal con tus clientes para que puedan hacer pedidos directamente.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Datos de las sucursales para usar en JavaScript
        const branchesData = <?= json_encode($branches) ?>;

        function editBranch(branchId) {
            // Buscar los datos de la sucursal
            const branch = branchesData.find(b => b.id == branchId);
            if (!branch) {
                alert('Sucursal no encontrada');
                return;
            }

            // Llenar el formulario con los datos actuales
            document.getElementById('edit_branch_id').value = branch.id;
            document.getElementById('edit_branch_name').value = branch.name;
            document.getElementById('edit_branch_email').value = branch.email;
            document.getElementById('edit_branch_phone').value = branch.phone || '';
            document.getElementById('edit_branch_address').value = branch.address || '';
            document.getElementById('edit_branch_description').value = branch.description || '';
            document.getElementById('edit_branch_password').value = '';

            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('editBranchModal'));
            modal.show();
        }

        function deleteBranch(branchId) {
            // Buscar los datos de la sucursal
            const branch = branchesData.find(b => b.id == branchId);
            if (!branch) {
                alert('Sucursal no encontrada');
                return;
            }

            // Mostrar el nombre de la sucursal en el modal
            document.getElementById('deleteBranchName').textContent = branch.name;
            
            // Resetear el checkbox y botón
            document.getElementById('confirmDeleteBtn').disabled = true;
            document.getElementById('confirmDeleteCheckbox').checked = false;
            
            // Configurar el evento del botón de confirmación
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.onclick = function() {
                performDelete(branchId, branch.name);
            };
            
            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('deleteBranchModal'));
            modal.show();
        }

        // Función para manejar el checkbox de confirmación
        document.getElementById('confirmDeleteCheckbox').addEventListener('change', function() {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.disabled = !this.checked;
        });

        // Función para realizar la eliminación
        function performDelete(branchId, branchName) {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const originalText = confirmBtn.innerHTML;
            
            // Deshabilitar botón y mostrar loading
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';
            
            // Crear FormData para enviar los datos
            const formData = new FormData();
            formData.append('branch_id', branchId);
            
            fetch('ajax/delete_branch.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        // Cerrar el modal de eliminación
                        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteBranchModal'));
                        modal.hide();
                        
                        // Mostrar modal de éxito con animaciones
                        showSuccessModal('¡Sucursal Eliminada!', data.message);
                        
                        // Crear efecto de confeti
                        createConfetti();
                        
                        // Recargar página después de un delay
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        showErrorModal('Error al eliminar', data.message);
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', text);
                    showErrorModal('Error', 'Respuesta del servidor no válida');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Error', 'Error al eliminar la sucursal: ' + error.message);
            })
            .finally(() => {
                // Restaurar botón
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            });
        }

        // Función para mostrar modal de éxito
        function showSuccessModal(title, message) {
            document.getElementById('successTitle').textContent = title;
            document.getElementById('successMessage').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('successModal'));
            modal.show();
        }

        // Función para mostrar modal de error
        function showErrorModal(title, message) {
            document.getElementById('errorMessage').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('errorModal'));
            modal.show();
            
            // Agregar efecto shake al modal
            const modalElement = document.getElementById('errorModal');
            modalElement.classList.add('shake');
            setTimeout(() => {
                modalElement.classList.remove('shake');
            }, 500);
        }

        // Función para crear efecto de confeti
        function createConfetti() {
            const colors = ['#f39c12', '#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#1abc9c'];
            
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.animationDelay = Math.random() * 2 + 's';
                    confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
                    
                    document.body.appendChild(confetti);
                    
                    // Remover confeti después de la animación
                    setTimeout(() => {
                        if (confetti.parentNode) {
                            confetti.parentNode.removeChild(confetti);
                        }
                    }, 5000);
                }, i * 50);
            }
        }

        // Manejar envío del formulario de crear sucursal
        document.getElementById('createBranchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Deshabilitar botón y mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text(); // Primero obtener como texto
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        // Cerrar modal de creación
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createBranchModal'));
                        modal.hide();
                        
                        // Mostrar modal de éxito con animaciones
                        showSuccessModal('¡Sucursal Creada!', data.message);
                        
                        // Crear efecto de confeti
                        createConfetti();
                        
                        // Recargar página después de un delay
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        showErrorModal('Error al crear', data.message || 'Error desconocido');
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', text);
                    showErrorModal('Error', 'Respuesta del servidor no válida');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Error', 'Error al crear la sucursal: ' + error.message);
            })
            .finally(() => {
                // Restaurar botón
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Manejar envío del formulario de editar sucursal
        document.getElementById('editBranchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Deshabilitar botón y mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        // Cerrar modal de edición
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editBranchModal'));
                        modal.hide();
                        
                        // Mostrar modal de éxito con animaciones
                        showSuccessModal('¡Sucursal Actualizada!', data.message);
                        
                        // Crear efecto de confeti
                        createConfetti();
                        
                        // Recargar página después de un delay
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        showErrorModal('Error al actualizar', data.message || 'Error desconocido');
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', text);
                    showErrorModal('Error', 'Respuesta del servidor no válida');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Error', 'Error al actualizar la sucursal: ' + error.message);
            })
            .finally(() => {
                // Restaurar botón
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    </script>
</body>
</html> 
