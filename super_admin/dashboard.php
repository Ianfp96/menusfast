<?php
require_once '../config/database.php';
require_once '../classes/Database.php';
requireLogin('super_admin');

$db = new Database();
$conn = $db->getConnection();

$error = '';
$stats = [];

try {
    // Obtener estadísticas generales
    $stats['total_restaurants'] = $conn->query("SELECT COUNT(*) FROM restaurants")->fetchColumn();
    $stats['active_restaurants'] = $conn->query("SELECT COUNT(*) FROM restaurants WHERE is_active = 1")->fetchColumn();
    $stats['total_orders'] = $conn->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stats['today_orders'] = $conn->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['total_revenue'] = $conn->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
    $stats['today_revenue'] = $conn->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'")->fetchColumn();
    
    // Obtener restaurantes recientes
    $stmt = $conn->query("SELECT r.*, p.name as plan_name 
                         FROM restaurants r 
                         LEFT JOIN plans p ON r.current_plan_id = p.id 
                         ORDER BY r.created_at DESC LIMIT 5");
    $recent_restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener órdenes recientes
    $stmt = $conn->query("SELECT o.*, r.name as restaurant_name 
                         FROM orders o 
                         JOIN restaurants r ON o.restaurant_id = r.id 
                         ORDER BY o.created_at DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error en dashboard de super admin: " . $e->getMessage());
    $error = "Error al cargar las estadísticas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .stat-label {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
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
                        <a class="nav-link active" href="/super_admin/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link" href="/super_admin/restaurants.php">
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
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#welcomeModal">
                            <i class="fas fa-hand-wave"></i> Bienvenida
                        </a>
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Dashboard</h1>
                    <div>
                        <a href="/super_admin/create-restaurant.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Crear Restaurante
                        </a>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary text-white">
                            <div class="stat-icon">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['total_restaurants']) ?></div>
                            <div class="stat-label">Total Restaurantes</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['active_restaurants']) ?></div>
                            <div class="stat-label">Restaurantes Activos</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info text-white">
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-value"><?= number_format($stats['today_orders']) ?></div>
                            <div class="stat-label">Órdenes Hoy</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-white">
                            <div class="stat-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-value">$<?= number_format($stats['today_revenue'], 2) ?></div>
                            <div class="stat-label">Ingresos Hoy</div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Restaurantes Recientes -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-store"></i> Restaurantes Recientes
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Plan</th>
                                                <th>Estado</th>
                                                <th>Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_restaurants as $restaurant): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($restaurant['name']) ?></td>
                                                    <td><?= htmlspecialchars($restaurant['plan_name']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $restaurant['is_active'] ? 'success' : 'danger' ?>">
                                                            <?= $restaurant['is_active'] ? 'Activo' : 'Inactivo' ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('d/m/Y', strtotime($restaurant['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Órdenes Recientes -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-shopping-cart"></i> Órdenes Recientes
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Restaurante</th>
                                                <th>Total</th>
                                                <th>Estado</th>
                                                <th>Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($order['restaurant_name']) ?></td>
                                                    <td>$<?= number_format($order['total'], 2) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= getOrderStatusColor($order['status']) ?>">
                                                            <?= ucfirst($order['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Modal de Bienvenida -->
    <div class="modal fade" id="welcomeModal" tabindex="-1" aria-labelledby="welcomeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="welcomeModalLabel">
                        <i class="fas fa-hand-wave"></i> ¡Bienvenido al Panel de Super Administrador!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 text-center mb-4">
                            <i class="fas fa-crown text-warning" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                            <h4 class="text-primary">Hola, <?= $_SESSION['super_admin_username'] ?>!</h4>
                            <p class="lead">Bienvenido al panel de administración de WebMenu</p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card border-primary">
                                <div class="card-body text-center">
                                    <i class="fas fa-store text-primary" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                    <h6>Gestión de Restaurantes</h6>
                                    <p class="small text-muted">Administra todos los restaurantes registrados en la plataforma</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line text-success" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                    <h6>Estadísticas</h6>
                                    <p class="small text-muted">Visualiza el rendimiento y métricas de la plataforma</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card border-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-envelope text-info" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                    <h6>Comunicación</h6>
                                    <p class="small text-muted">Envía emails masivos a restaurantes y usuarios</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-cog text-warning" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                    <h6>Configuración</h6>
                                    <p class="small text-muted">Gestiona configuraciones del sistema</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Consejo:</strong> Utiliza el menú lateral para navegar entre las diferentes secciones del panel de administración.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                    <a href="/super_admin/restaurants.php" class="btn btn-primary">
                        <i class="fas fa-store"></i> Ir a Restaurantes
                    </a>
                </div>
            </div>
        </div>
    </div>
    
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
