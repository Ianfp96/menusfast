<?php
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../config/functions.php';
requireLogin('super_admin');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';
$restaurant = null;
$branches = [];

// Obtener ID del restaurante
$restaurant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$restaurant_id) {
    redirect(BASE_URL . '/super_admin/restaurants.php');
}

// Obtener datos del restaurante
try {
    $query = "SELECT r.*, 
              p.name as plan_name, 
              p.base_price,
              p.max_branches,
              s.id as subscription_id, 
              s.duration_months, 
              s.price as subscription_price,
              s.start_date, 
              s.end_date, 
              s.status as subscription_status
              FROM restaurants r 
              LEFT JOIN plans p ON r.current_plan_id = p.id 
              LEFT JOIN subscriptions s ON r.id = s.restaurant_id AND s.status = 'active'
              WHERE r.id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $restaurant_id, PDO::PARAM_INT);
    $stmt->execute();
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$restaurant) {
        redirect(BASE_URL . '/super_admin/restaurants.php');
    }

    // Obtener las sucursales del restaurante
    $query = "SELECT r.*, 
              p.name as plan_name,
              s.duration_months,
              s.price as subscription_price,
              s.start_date as subscription_start_date,
              s.end_date as subscription_end_date,
              s.status as subscription_status
              FROM restaurants r 
              LEFT JOIN plans p ON r.current_plan_id = p.id 
              LEFT JOIN subscriptions s ON r.id = s.restaurant_id AND s.status = 'active'
              WHERE r.parent_restaurant_id = :parent_id AND r.is_branch = 1 
              ORDER BY r.branch_number ASC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':parent_id', $restaurant_id, PDO::PARAM_INT);
    $stmt->execute();
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Error al obtener datos del restaurante: ' . $e->getMessage();
}

// Procesar acciones
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $branch_id = $_POST['branch_id'] ?? 0;
    
    if ($action === 'toggle_status' && $branch_id) {
        $query = "UPDATE restaurants SET is_active = NOT is_active WHERE id = :id AND parent_restaurant_id = :parent_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $branch_id);
        $stmt->bindParam(':parent_id', $restaurant_id);
        
        if ($stmt->execute()) {
            $message = 'Estado de la sucursal actualizado correctamente';
        } else {
            $error = 'Error al actualizar el estado';
        }
    }
    
    if ($action === 'delete' && $branch_id) {
        try {
            $conn->beginTransaction();
            
            // Eliminar productos de la sucursal
            $stmt = $conn->prepare("DELETE FROM products WHERE restaurant_id = ?");
            $stmt->execute([$branch_id]);
            
            // Eliminar categorías de la sucursal
            $stmt = $conn->prepare("DELETE FROM menu_categories WHERE restaurant_id = ?");
            $stmt->execute([$branch_id]);
            
            // Eliminar la sucursal
            $stmt = $conn->prepare("DELETE FROM restaurants WHERE id = ? AND parent_restaurant_id = ?");
            $stmt->execute([$branch_id, $restaurant_id]);
            
            $conn->commit();
            $message = 'Sucursal eliminada correctamente';
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error al eliminar la sucursal: ' . $e->getMessage();
        }
    }
}

// Recargar datos después de acciones
if ($message || $error) {
    $query = "SELECT r.*, 
              p.name as plan_name,
              s.duration_months,
              s.price as subscription_price,
              s.start_date as subscription_start_date,
              s.end_date as subscription_end_date,
              s.status as subscription_status
              FROM restaurants r 
              LEFT JOIN plans p ON r.current_plan_id = p.id 
              LEFT JOIN subscriptions s ON r.id = s.restaurant_id AND s.status = 'active'
              WHERE r.parent_restaurant_id = :parent_id AND r.is_branch = 1 
              ORDER BY r.branch_number ASC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':parent_id', $restaurant_id, PDO::PARAM_INT);
    $stmt->execute();
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Sucursales - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #232526 0%, #414345 100%);
            min-height: 100vh;
            color: #fff;
            box-shadow: 2px 0 10px rgba(44,62,80,0.08);
        }
        .sidebar .nav-link {
            color: #bdc3c7;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 0.3rem;
            transition: background 0.2s, color 0.2s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #2980b9;
            color: #fff;
        }
        .sidebar h4 {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        /* Estilos modernos para el contenido principal */
        .col-md-9, .col-lg-10 {
            background: linear-gradient(135deg, #ffffff 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem !important;
        }
        
        .col-md-9 .p-4, .col-lg-10 .p-4 {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            padding: 2.5rem !important;
        }
        
        h1 {
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .card {
            border-radius: 1.5rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 1.5rem;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .branch-card {
            transition: all 0.3s ease;
            border-radius: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            overflow: hidden;
            position: relative;
        }
        
        .branch-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(41,128,185,0.15);
        }
        
        .branch-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #17a2b8, #138496);
        }
        
        .branch-number {
            background: linear-gradient(45deg, #17a2b8, #138496);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .branch-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
            padding: 0.5rem;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .branch-info-item:hover {
            background: rgba(23, 162, 184, 0.1);
            transform: translateX(3px);
        }
        
        .branch-info-item i {
            width: 20px;
            margin-right: 0.8rem;
            color: #17a2b8;
            font-size: 0.9rem;
        }
        
        .branch-info-item small {
            color: #495057;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .btn {
            border-radius: 0.8rem;
            font-weight: 600;
            padding: 0.8rem 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(45deg, #5a6fd8, #6a4190);
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
        }
        
        .btn-outline-primary {
            color: #667eea;
            border: 2px solid #667eea;
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-color: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-outline-secondary {
            color: #6c757d;
            border: 2px solid #6c757d;
            background: transparent;
        }
        
        .btn-outline-secondary:hover {
            background: linear-gradient(45deg, #6c757d, #495057);
            border-color: #6c757d;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .alert {
            border-radius: 1rem;
            font-size: 1rem;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.4em 0.8em;
            border-radius: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .progress {
            border-radius: 1rem;
            background: rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .progress-bar {
            border-radius: 1rem;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .dropdown-menu {
            border-radius: 0.8rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: none;
            padding: 0.5rem;
        }
        
        .dropdown-item {
            border-radius: 0.5rem;
            padding: 0.6rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(3px);
        }
        
        .text-muted {
            color: #6c757d !important;
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                min-height: auto;
            }
            .col-md-9, .col-lg-10 {
                padding: 1rem !important;
            }
            .col-md-9 .p-4, .col-lg-10 .p-4 {
                padding: 1.5rem !important;
            }
            h1 {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 767px) {
            .sidebar {
                padding: 0.5rem 0.2rem;
            }
            .col-md-9, .col-lg-10 {
                padding: 0.5rem !important;
            }
            .col-md-9 .p-4, .col-lg-10 .p-4 {
                padding: 1rem !important;
            }
            h1 {
                font-size: 1.8rem;
            }
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <div class="p-3">
                        <h4><i class="fas fa-crown"></i> Super Admin</h4>
                        <small class="text-muted">Bienvenido, <?= $_SESSION['super_admin_username'] ?></small>
                    </div>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="/super_admin/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="/super_admin/restaurants.php">
                            <i class="fas fa-store"></i> Restaurantes
                        </a>
                        <a class="nav-link" href="/super_admin/create-restaurant.php">
                            <i class="fas fa-plus"></i> Crear Restaurante
                        </a>
                        <a class="nav-link" href="/super_admin/send-emails.php">
                            <i class="fas fa-envelope"></i> Enviar Emails
                        </a>
                        <a class="nav-link" href="/super_admin/send-emails-inactive.php">
                            <i class="fas fa-user-times"></i> Emails Inactivos
                        </a>
                        <a class="nav-link" href="/super_admin/change-password.php">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </a>
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1>Gestionar Sucursales</h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-store"></i> <?= htmlspecialchars($restaurant['name']) ?>
                                <span class="badge bg-primary ms-2"><?= htmlspecialchars($restaurant['plan_name']) ?></span>
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="/super_admin/edit-restaurant.php?id=<?= $restaurant_id ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Editar Restaurante
                            </a>
                            <a href="/super_admin/restaurants.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Volver a Lista
                            </a>
                        </div>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Información del restaurante -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-info-circle"></i> Información del Restaurante
                                    </h5>
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Plan</small>
                                            <p class="mb-1"><strong><?= htmlspecialchars($restaurant['plan_name']) ?></strong></p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Límite de Sucursales</small>
                                            <p class="mb-1"><strong><?= $restaurant['max_branches'] ?></strong></p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Sucursales Actuales</small>
                                            <p class="mb-1"><strong><?= count($branches) ?></strong></p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Estado</small>
                                            <p class="mb-1">
                                                <span class="badge <?= $restaurant['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= $restaurant['is_active'] ? 'Activo' : 'Inactivo' ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-chart-bar"></i> Uso de Sucursales
                                    </h5>
                                    <div class="progress mb-3" style="height: 25px;">
                                        <?php 
                                        $usage_percentage = $restaurant['max_branches'] > 0 ? (count($branches) / $restaurant['max_branches']) * 100 : 0;
                                        $progress_class = $usage_percentage >= 90 ? 'bg-danger' : ($usage_percentage >= 70 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress-bar <?= $progress_class ?>" 
                                             role="progressbar" 
                                             style="width: <?= $usage_percentage ?>%"
                                             aria-valuenow="<?= $usage_percentage ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?= count($branches) ?>/<?= $restaurant['max_branches'] ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?php if ($usage_percentage >= 90): ?>
                                            <i class="fas fa-exclamation-triangle text-danger"></i> Límite casi alcanzado
                                        <?php elseif ($usage_percentage >= 70): ?>
                                            <i class="fas fa-info-circle text-warning"></i> Uso moderado
                                        <?php else: ?>
                                            <i class="fas fa-check-circle text-success"></i> Uso normal
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista de Sucursales -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-store"></i> Sucursales (<?= count($branches) ?>)
                            </h5>
                            <?php if (count($branches) < $restaurant['max_branches']): ?>
                                <a href="/super_admin/create-branch.php?parent_id=<?= $restaurant_id ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Crear Sucursal
                                </a>
                            <?php else: ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Límite alcanzado
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($branches)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-store fa-3x text-muted mb-3"></i>
                                    <h4 class="text-muted">No hay sucursales</h4>
                                    <p class="text-muted">Este restaurante aún no tiene sucursales creadas.</p>
                                    <?php if (count($branches) < $restaurant['max_branches']): ?>
                                        <a href="/super_admin/create-branch.php?parent_id=<?= $restaurant_id ?>" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Crear Primera Sucursal
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($branches as $branch): ?>
                                        <div class="col-lg-6 col-xl-4 mb-4">
                                            <div class="card branch-card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <div class="d-flex align-items-center">
                                                            <span class="branch-number me-2">
                                                                #<?= $branch['branch_number'] ?>
                                                            </span>
                                                            <span class="badge <?= $branch['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                                                                <?= $branch['is_active'] ? 'Activa' : 'Inactiva' ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <h5 class="card-title mb-3"><?= htmlspecialchars($branch['name']) ?></h5>
                                                    
                                                    <div class="branch-info-item">
                                                        <i class="fas fa-link"></i>
                                                        <small>/<?= $branch['slug'] ?></small>
                                                    </div>
                                                    
                                                    <div class="branch-info-item">
                                                        <i class="fas fa-envelope"></i>
                                                        <small><?= htmlspecialchars($branch['email']) ?></small>
                                                    </div>
                                                    
                                                    <?php if ($branch['phone']): ?>
                                                        <div class="branch-info-item">
                                                            <i class="fas fa-phone"></i>
                                                            <small><?= htmlspecialchars($branch['phone']) ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($branch['address']): ?>
                                                        <div class="branch-info-item">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                            <small><?= htmlspecialchars($branch['address']) ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="branch-info-item">
                                                        <i class="fas fa-calendar"></i>
                                                        <small>Creada: <?= date('d/m/Y', strtotime($branch['created_at'])) ?></small>
                                                    </div>

                                                    <div class="card-footer bg-transparent border-top-0 pt-3">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <a href="/<?= $branch['slug'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i> Ver
                                                            </a>
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                                        type="button" data-bs-toggle="dropdown"
                                                                        aria-expanded="false">
                                                                    <i class="fas fa-cog"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a class="dropdown-item" href="/super_admin/edit-restaurant.php?id=<?= $branch['id'] ?>">
                                                                            <i class="fas fa-edit"></i> Editar
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de cambiar el estado de esta sucursal?')">
                                                                            <input type="hidden" name="action" value="toggle_status">
                                                                            <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>">
                                                                            <button type="submit" class="dropdown-item">
                                                                                <?= $branch['is_active'] ? 
                                                                                    '<i class="fas fa-ban text-warning"></i> Desactivar' : 
                                                                                    '<i class="fas fa-check text-success"></i> Activar' ?>
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li>
                                                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar esta sucursal? Esta acción no se puede deshacer.')">
                                                                            <input type="hidden" name="action" value="delete">
                                                                            <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>">
                                                                            <button type="submit" class="dropdown-item text-danger">
                                                                                <i class="fas fa-trash"></i> Eliminar
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Modal de Logout -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="logoutModalLabel">
                        <i class="fas fa-sign-out-alt"></i> Confirmar Cierre de Sesión
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-question-circle text-warning" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p class="mb-0">¿Estás seguro de que deseas cerrar sesión?</p>
                        <small class="text-muted">Serás redirigido a la página de inicio de sesión.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <a href="/super_admin/logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Sí, Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 
