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

try {
    // Obtener información del restaurante
    $stmt = $conn->prepare("
        SELECT r.*, p.name as plan_name
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        WHERE r.id = ?
    ");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$restaurant) {
        redirect(BASE_URL . '/restaurante/logout.php');
    }

    // Obtener cupones del restaurante
    $stmt = $conn->prepare("
        SELECT c.*, 
               COUNT(cu.id) as total_uses,
               COALESCE(SUM(cu.discount_amount), 0) as total_discount_given
        FROM coupons c
        LEFT JOIN coupon_usage cu ON c.id = cu.coupon_id
        WHERE c.restaurant_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$restaurant_id]);
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error en cupones: " . $e->getMessage());
    $error = "Error al cargar los cupones: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupones - <?= htmlspecialchars($restaurant['name']) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/restaurante/css/estilo_webmenu.css" rel="stylesheet">
    
    <style>
        .coupon-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .coupon-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .coupon-status {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .discount-badge {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
        }
        .usage-progress {
            height: 8px;
            border-radius: 4px;
        }
        
        /* Estilos mejorados para el modal de estadísticas */
        .stats-modal .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .stats-modal .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
            padding: 1.5rem;
        }
        
        .stats-modal .modal-title {
            font-weight: 600;
            font-size: 1.25rem;
        }
        
        .stats-modal .modal-body {
            padding: 2rem;
            background: #f8f9fa;
        }
        
        .stats-modal .modal-footer {
            border-top: none;
            padding: 1rem 2rem 2rem;
            background: #f8f9fa;
        }
        
        /* Estilos para las tarjetas de estadísticas */
        .stats-card {
            background: white;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stats-card .card-body {
            padding: 1.5rem;
            text-align: center;
        }
        
        .stats-card h4 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stats-card small {
            font-size: 0.875rem;
            opacity: 0.8;
            font-weight: 500;
        }
        
        /* Gradientes para las tarjetas */
        .stats-card.bg-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .stats-card.bg-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%) !important;
        }
        
        .stats-card.bg-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important;
        }
        
        .stats-card.bg-warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%) !important;
        }
        
        /* Estilos para las tablas */
        .stats-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stats-table .table {
            margin-bottom: 0;
        }
        
        .stats-table .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }
        
        .stats-table .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }
        
        .stats-table .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Estilos para la información del cupón */
        .coupon-info-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .coupon-info-card h6 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .coupon-info-card .table {
            margin-bottom: 0;
        }
        
        .coupon-info-card .table td {
            border: none;
            padding: 0.75rem 0;
            vertical-align: middle;
        }
        
        .coupon-info-card .table td:first-child {
            font-weight: 600;
            color: #495057;
            width: 40%;
        }
        
        .coupon-info-card .table td:last-child {
            color: #6c757d;
        }
        
        /* Estilos para el progreso */
        .progress-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .progress-container .progress {
            height: 12px;
            border-radius: 6px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        
        .progress-container .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 6px;
        }
        
        /* Estilos para los badges */
        .stats-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        /* Estilos para las secciones */
        .stats-section {
            margin-bottom: 2rem;
        }
        
        .stats-section h6 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }
        
        .stats-section h6::before {
            content: '';
            width: 4px;
            height: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin-right: 0.75rem;
            border-radius: 2px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-modal .modal-body {
                padding: 1rem;
            }
            
            .stats-card .card-body {
                padding: 1rem;
            }
            
            .stats-card h4 {
                font-size: 1.5rem;
            }
        }
        
        /* Estilos para el modal de edición */
        .edit-modal .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .edit-modal .modal-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
            padding: 1.5rem;
        }
        
        .edit-modal .modal-title {
            font-weight: 600;
            font-size: 1.25rem;
        }
        
        .edit-modal .modal-body {
            padding: 2rem;
        }
        
        .edit-modal .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .edit-modal .form-control,
        .edit-modal .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .edit-modal .form-control:focus,
        .edit-modal .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .edit-modal .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .edit-modal .btn-primary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .edit-modal .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .edit-modal .btn-secondary {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-ticket-alt text-primary"></i> 
                        Cupones de Descuento
                    </h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearCupon">
                        <i class="fas fa-plus"></i> Crear Cupón
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Estadísticas de cupones -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Cupones</h6>
                                        <h3><?= count($coupons) ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-ticket-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Cupones Activos</h6>
                                        <h3><?= count(array_filter($coupons, function($c) { return $c['is_active'] && strtotime($c['valid_until']) > time(); })) ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Usos</h6>
                                        <h3><?= array_sum(array_column($coupons, 'total_uses')) ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Descuento Total</h6>
                                        <h3>$<?= number_format(array_sum(array_column($coupons, 'total_discount_given')), 0, ',', '.') ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-dollar-sign fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de cupones -->
                <div class="row">
                    <?php if (empty($coupons)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No tienes cupones creados</h4>
                                <p class="text-muted">Crea tu primer cupón de descuento para empezar a promocionar tu restaurante</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearCupon">
                                    <i class="fas fa-plus"></i> Crear Primer Cupón
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($coupons as $coupon): ?>
                            <?php 
                            $is_expired = strtotime($coupon['valid_until']) < time();
                            $is_active = $coupon['is_active'] && !$is_expired;
                            $usage_percentage = $coupon['usage_limit'] ? ($coupon['used_count'] / $coupon['usage_limit']) * 100 : 0;
                            ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card coupon-card h-100 position-relative">
                                    <div class="card-body">
                                        <!-- Estado del cupón -->
                                        <div class="coupon-status">
                                            <?php if ($is_active): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php elseif ($is_expired): ?>
                                                <span class="badge bg-danger">Expirado</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Código del cupón -->
                                        <div class="text-center mb-3">
                                            <h5 class="card-title text-primary"><?= htmlspecialchars($coupon['code']) ?></h5>
                                            <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($coupon['name']) ?></h6>
                                        </div>

                                        <!-- Descuento -->
                                        <div class="text-center mb-3">
                                            <span class="discount-badge">
                                                <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                                    <?= $coupon['discount_value'] ?>% OFF
                                                <?php else: ?>
                                                    $<?= number_format($coupon['discount_value'], 0, ',', '.') ?> OFF
                                                <?php endif; ?>
                                            </span>
                                        </div>

                                        <!-- Detalles -->
                                        <div class="mb-3">
                                            <?php if ($coupon['description']): ?>
                                                <p class="card-text small text-muted"><?= htmlspecialchars($coupon['description']) ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <small class="text-muted">Mínimo</small><br>
                                                    <strong>$<?= number_format($coupon['minimum_order_amount'], 0, ',', '.') ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Usos</small><br>
                                                    <strong><?= $coupon['used_count'] ?>/<?= $coupon['usage_limit'] ?: '∞' ?></strong>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Barra de progreso de uso -->
                                        <?php if ($coupon['usage_limit']): ?>
                                            <div class="mb-3">
                                                <div class="progress usage-progress">
                                                    <div class="progress-bar bg-info" style="width: <?= min($usage_percentage, 100) ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?= round($usage_percentage, 1) ?>% usado</small>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Fechas -->
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> 
                                                Válido hasta: <?= date('d/m/Y', strtotime($coupon['valid_until'])) ?>
                                            </small>
                                        </div>

                                        <!-- Acciones -->
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary flex-fill" 
                                                    onclick="editarCupon(<?= $coupon['id'] ?>)">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                            <button class="btn btn-sm btn-outline-info flex-fill" 
                                                    onclick="verEstadisticas(<?= $coupon['id'] ?>)">
                                                <i class="fas fa-chart-bar"></i> Stats
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="eliminarCupon(<?= $coupon['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Crear Cupón -->
    <div class="modal fade" id="modalCrearCupon" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Cupón</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formCrearCupon">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code" class="form-label">Código del Cupón *</label>
                                    <input type="text" class="form-control" id="code" name="code" required 
                                           placeholder="Ej: DESCUENTO20" maxlength="50">
                                    <div class="form-text">Código único que usarán los clientes</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nombre del Cupón *</label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           placeholder="Ej: Descuento 20%" maxlength="255">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Descripción opcional del cupón"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="discount_type" class="form-label">Tipo de Descuento *</label>
                                    <select class="form-select" id="discount_type" name="discount_type" required>
                                        <option value="percentage">Porcentaje (%)</option>
                                        <option value="fixed">Monto Fijo ($)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="discount_value" class="form-label">Valor del Descuento *</label>
                                    <input type="number" class="form-control" id="discount_value" name="discount_value" 
                                           required min="0" step="0.01" placeholder="0">
                                    <div class="form-text" id="discount_help">
                                        Para porcentaje: máximo 100%. Para monto fijo: sin límite.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="minimum_order_amount" class="form-label">Monto Mínimo de Orden</label>
                                    <input type="number" class="form-control" id="minimum_order_amount" 
                                           name="minimum_order_amount" min="0" step="0.01" value="0" placeholder="0">
                                    <div class="form-text">Monto mínimo que debe tener la orden para aplicar el cupón</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="maximum_discount" class="form-label">Descuento Máximo</label>
                                    <input type="number" class="form-control" id="maximum_discount" 
                                           name="maximum_discount" min="0" step="0.01" placeholder="Sin límite">
                                    <div class="form-text">Límite máximo del descuento (solo para porcentajes)</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="usage_limit" class="form-label">Límite de Usos</label>
                                    <input type="number" class="form-control" id="usage_limit" name="usage_limit" 
                                           min="1" placeholder="Sin límite">
                                    <div class="form-text">Número máximo de veces que se puede usar el cupón</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="valid_until" class="form-label">Válido Hasta *</label>
                                    <input type="datetime-local" class="form-control" id="valid_until" 
                                           name="valid_until" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Crear Cupón
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Estadísticas -->
    <div class="modal fade stats-modal" id="modalEstadisticas" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-bar me-2"></i>
                        Estadísticas del Cupón
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="estadisticasContent">
                    <!-- Contenido cargado dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Cupón -->
    <div class="modal fade edit-modal" id="modalEditarCupon" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Editar Cupón
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEditarCupon">
                    <div class="modal-body">
                        <input type="hidden" id="edit_coupon_id" name="coupon_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_code" class="form-label">Código del Cupón *</label>
                                    <input type="text" class="form-control" id="edit_code" name="code" required 
                                           placeholder="Ej: DESCUENTO20" maxlength="50">
                                    <div class="form-text">Código único que usarán los clientes</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_name" class="form-label">Nombre del Cupón *</label>
                                    <input type="text" class="form-control" id="edit_name" name="name" required 
                                           placeholder="Ej: Descuento 20%" maxlength="255">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3" 
                                      placeholder="Descripción opcional del cupón"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_discount_type" class="form-label">Tipo de Descuento *</label>
                                    <select class="form-select" id="edit_discount_type" name="discount_type" required>
                                        <option value="percentage">Porcentaje (%)</option>
                                        <option value="fixed">Monto Fijo ($)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_discount_value" class="form-label">Valor del Descuento *</label>
                                    <input type="number" class="form-control" id="edit_discount_value" name="discount_value" 
                                           required min="0" step="0.01" placeholder="0">
                                    <div class="form-text" id="edit_discount_help">
                                        Para porcentaje: máximo 100%. Para monto fijo: sin límite.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_minimum_order_amount" class="form-label">Monto Mínimo de Orden</label>
                                    <input type="number" class="form-control" id="edit_minimum_order_amount" 
                                           name="minimum_order_amount" min="0" step="0.01" value="0" placeholder="0">
                                    <div class="form-text">Monto mínimo que debe tener la orden para aplicar el cupón</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_maximum_discount" class="form-label">Descuento Máximo</label>
                                    <input type="number" class="form-control" id="edit_maximum_discount" 
                                           name="maximum_discount" min="0" step="0.01" placeholder="Sin límite">
                                    <div class="form-text">Límite máximo del descuento (solo para porcentajes)</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_usage_limit" class="form-label">Límite de Usos</label>
                                    <input type="number" class="form-control" id="edit_usage_limit" name="usage_limit" 
                                           min="1" placeholder="Sin límite">
                                    <div class="form-text">Número máximo de veces que se puede usar el cupón</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_valid_until" class="form-label">Válido Hasta *</label>
                                    <input type="datetime-local" class="form-control" id="edit_valid_until" 
                                           name="valid_until" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                                <label class="form-check-label" for="edit_is_active">
                                    Cupón activo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Generar código automático
        document.getElementById('code').addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });

        // Cambiar ayuda según tipo de descuento
        document.getElementById('discount_type').addEventListener('change', function() {
            const help = document.getElementById('discount_help');
            const valueInput = document.getElementById('discount_value');
            
            if (this.value === 'percentage') {
                help.textContent = 'Para porcentaje: máximo 100%. Para monto fijo: sin límite.';
                valueInput.max = 100;
            } else {
                help.textContent = 'Monto fijo en pesos.';
                valueInput.removeAttribute('max');
            }
        });

        // Formulario crear cupón
        document.getElementById('formCrearCupon').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/create_coupon.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al crear el cupón');
            });
        });

        function editarCupon(id) {
            // Cargar datos del cupón
            fetch('ajax/get_coupon.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'coupon_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const coupon = data.coupon;
                    
                    // Llenar el formulario con los datos del cupón
                    document.getElementById('edit_coupon_id').value = coupon.id;
                    document.getElementById('edit_code').value = coupon.code;
                    document.getElementById('edit_name').value = coupon.name;
                    document.getElementById('edit_description').value = coupon.description || '';
                    document.getElementById('edit_discount_type').value = coupon.discount_type;
                    document.getElementById('edit_discount_value').value = coupon.discount_value;
                    document.getElementById('edit_minimum_order_amount').value = coupon.minimum_order_amount;
                    document.getElementById('edit_maximum_discount').value = coupon.maximum_discount || '';
                    document.getElementById('edit_usage_limit').value = coupon.usage_limit || '';
                    document.getElementById('edit_valid_until').value = coupon.valid_until.replace(' ', 'T');
                    document.getElementById('edit_is_active').checked = coupon.is_active == 1;
                    
                    // Actualizar ayuda del descuento
                    updateEditDiscountHelp();
                    
                    // Abrir el modal
                    new bootstrap.Modal(document.getElementById('modalEditarCupon')).show();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar los datos del cupón');
            });
        }

        // Función para actualizar ayuda del descuento en edición
        function updateEditDiscountHelp() {
            const help = document.getElementById('edit_discount_help');
            const valueInput = document.getElementById('edit_discount_value');
            const typeSelect = document.getElementById('edit_discount_type');
            
            if (typeSelect.value === 'percentage') {
                help.textContent = 'Para porcentaje: máximo 100%. Para monto fijo: sin límite.';
                valueInput.max = 100;
            } else {
                help.textContent = 'Monto fijo en pesos.';
                valueInput.removeAttribute('max');
            }
        }

        // Event listener para cambio de tipo de descuento en edición
        document.getElementById('edit_discount_type').addEventListener('change', updateEditDiscountHelp);

        // Generar código automático en edición
        document.getElementById('edit_code').addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });

        // Formulario editar cupón
        document.getElementById('formEditarCupon').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Cambiar texto del botón mientras procesa
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
            submitBtn.disabled = true;
            
            fetch('ajax/update_coupon.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar mensaje de éxito
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
                    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                    alertDiv.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(alertDiv);
                    
                    // Cerrar el modal
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarCupon')).hide();
                    
                    // Remover el mensaje después de 3 segundos
                    setTimeout(() => {
                        if (alertDiv.parentNode) {
                            alertDiv.remove();
                        }
                    }, 3000);
                    
                    // Recargar la página para mostrar los cambios
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al actualizar el cupón');
            })
            .finally(() => {
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        function verEstadisticas(id) {
            fetch('ajax/get_coupon_stats.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'coupon_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('estadisticasContent').innerHTML = data.html;
                    new bootstrap.Modal(document.getElementById('modalEstadisticas')).show();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar estadísticas');
            });
        }

        function eliminarCupon(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este cupón?')) {
                fetch('ajax/delete_coupon.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'coupon_id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el cupón');
                });
            }
        }
    </script>
</body>
</html> 
