<?php
require_once '../config/database.php';
require_once '../classes/Database.php';
requireLogin('super_admin');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';

// Procesar acciones
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $restaurant_id = $_POST['restaurant_id'] ?? 0;
    
    if ($action === 'toggle_status' && $restaurant_id) {
        $query = "UPDATE restaurants SET is_active = NOT is_active WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $restaurant_id);
        
        if ($stmt->execute()) {
            $message = 'Estado del restaurante actualizado correctamente';
        } else {
            $error = 'Error al actualizar el estado';
        }
    }
    
    if ($action === 'delete' && $restaurant_id) {
        // Verificar que no sea el último restaurante o tenga datos importantes
        $query = "DELETE FROM restaurants WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $restaurant_id);
        
        if ($stmt->execute()) {
            // Redirigir para evitar reenvío del formulario
            header('Location: ' . $_SERVER['PHP_SELF'] . '?message=' . urlencode('Restaurante eliminado correctamente'));
            exit;
        } else {
            $error = 'Error al eliminar el restaurante';
        }
    }
}

// Obtener mensaje de la URL si existe
$message = $_GET['message'] ?? $message;

// Obtener filtros
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$plan_filter = $_GET['plan'] ?? '';

// Construir query con filtros
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(r.name LIKE :search OR r.email LIKE :search OR r.phone LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($status_filter !== '') {
    $where_conditions[] = "r.is_active = :status";
    $params[':status'] = (int)$status_filter;
}

if ($plan_filter) {
    $where_conditions[] = "r.current_plan_id = :plan_id";
    $params[':plan_id'] = (int)$plan_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Obtener restaurantes con paginación
$page = max(1, $_GET['page'] ?? 1);
$per_page = 20; // Aumentar el número de elementos por página
$offset = ($page - 1) * $per_page;

// Preparar la consulta principal con información adicional
$query = "SELECT r.*, p.name as plan_name, p.base_price as plan_price, p.max_branches, p.max_products, p.max_categories,
          r.trial_ends_at, r.subscription_ends_at,
          (SELECT COUNT(*) FROM restaurants WHERE parent_restaurant_id = r.id) as branches_count,
          (SELECT COUNT(*) FROM products WHERE restaurant_id = r.id) as products_count,
          (SELECT COUNT(*) FROM menu_categories WHERE restaurant_id = r.id) as categories_count
          FROM restaurants r 
          LEFT JOIN plans p ON r.current_plan_id = p.id 
          $where_clause 
          ORDER BY r.created_at DESC 
          LIMIT :limit OFFSET :offset";

try {
    // Preparar y ejecutar la consulta principal
    $stmt = $conn->prepare($query);
    
    // Vincular los parámetros de filtro
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Vincular los parámetros de paginación
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar y ejecutar la consulta de conteo
    $count_query = "SELECT COUNT(*) as total FROM restaurants r $where_clause";
    $stmt = $conn->prepare($count_query);
    
    // Vincular solo los parámetros de filtro para la consulta de conteo
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $total_restaurants = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_restaurants / $per_page);
    
} catch (PDOException $e) {
    error_log("Error en restaurants.php: " . $e->getMessage());
    $error = "Error al cargar los restaurantes. Por favor, intente nuevamente.";
    $restaurants = [];
    $total_restaurants = 0;
    $total_pages = 0;
}

// Obtener planes para filtro
$query = "SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price";
$stmt = $conn->prepare($query);
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Restaurantes - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        
        .table-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }
        
        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.4em 0.8em;
            border-radius: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .restaurant-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .restaurant-email {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .restaurant-phone {
            font-size: 0.85rem;
            color: #495057;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.25rem;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 0.3rem;
            font-size: 0.75rem;
            color: #495057;
        }
        
        .stat-value {
            font-weight: 600;
            color: #667eea;
        }
        
        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 0.375rem;
            margin: 0.125rem;
        }
        
        .filters-section {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
        }
        
        .form-label {
            font-weight: 500;
            color: #34495e;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #dfe6e9;
            box-shadow: none;
            transition: border-color 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #2980b9;
            box-shadow: 0 0 0 0.1rem rgba(41,128,185,0.08);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(45deg, #5a6fd8, #6a4190);
            transform: translateY(-1px);
        }
        
        .pagination {
            margin-top: 1.5rem;
        }
        
        .pagination .page-link {
            border-radius: 0.4rem;
            color: #2980b9;
            margin: 0 0.125rem;
        }
        
        .pagination .page-item.active .page-link {
            background: #2980b9;
            border-color: #2980b9;
        }
        
        .alert {
            border-radius: 0.7rem;
            font-size: 1rem;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom: none;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            border-left: 4px solid #667eea;
        }
        
        .info-item-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .info-item-value {
            color: #6c757d;
        }
        
        /* Estilos para el modal mejorado */
        #restaurantInfoModal .modal-content {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        
        #restaurantInfoModal .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom: none;
            border-radius: 1rem 1rem 0 0;
        }
        
        #restaurantInfoModal .modal-title {
            font-weight: 600;
            font-size: 1.25rem;
        }
        
        #restaurantInfoModal .table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-color: #dee2e6;
            font-weight: 600;
            color: #495057;
            vertical-align: middle;
            padding: 1rem;
        }
        
        #restaurantInfoModal .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #dee2e6;
        }
        
        #restaurantInfoModal .table-striped > tbody > tr:nth-of-type(odd) > td {
            background-color: rgba(102, 126, 234, 0.02);
        }
        
        #restaurantInfoModal .badge {
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }
        
        #restaurantInfoModal .h5 {
            font-weight: 600;
        }
        
        #restaurantInfoModal .border.rounded {
            transition: all 0.3s ease;
        }
        
        #restaurantInfoModal .border.rounded:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-crown"></i> Super Admin
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="restaurants.php">
                                <i class="fas fa-store"></i> Restaurantes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="plans.php">
                                <i class="fas fa-credit-card"></i> Planes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Configuración
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Contenido principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-store"></i> Gestión de Restaurantes
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="create-restaurant.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nuevo Restaurante
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="filters-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">
                                <i class="fas fa-search"></i> Buscar
                            </label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Nombre, email o teléfono...">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">
                                <i class="fas fa-toggle-on"></i> Estado
                            </label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos los estados</option>
                                <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Activo</option>
                                <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="plan" class="form-label">
                                <i class="fas fa-credit-card"></i> Plan
                            </label>
                            <select class="form-select" id="plan" name="plan">
                                <option value="">Todos los planes</option>
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?= $plan['id'] ?>" <?= $plan_filter == $plan['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($plan['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filtrar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tabla de restaurantes -->
                <div class="table-container">
                    <div class="table-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Lista de Restaurantes 
                            <span class="badge bg-light text-dark ms-2"><?= $total_restaurants ?></span>
                        </h5>
                    </div>
                    
                    <?php if (empty($restaurants)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-store fa-3x text-muted mb-3"></i>
                            <h3 class="text-muted">No se encontraron restaurantes</h3>
                            <p class="text-muted">
                                <?= $search || $status_filter !== '' || $plan_filter ? 
                                    'Intenta ajustar los filtros de búsqueda' : 
                                    'Comienza creando tu primer restaurante' ?>
                            </p>
                            <a href="create-restaurant.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Crear Primer Restaurante
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Restaurante</th>
                                        <th>Contacto</th>
                                        <th>Plan</th>
                                        <th>Estado</th>
                                        <th>Estadísticas</th>
                                        <th>Fecha Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($restaurants as $restaurant): ?>
                                        <tr>
                                            <td>
                                                <div class="restaurant-name">
                                                    <?= htmlspecialchars($restaurant['name']) ?>
                                                </div>
                                                <div class="restaurant-email">
                                                    <i class="fas fa-link"></i> <?= htmlspecialchars($restaurant['slug']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="restaurant-email">
                                                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($restaurant['email']) ?>
                                                </div>
                                                <?php if ($restaurant['phone']): ?>
                                                    <div class="restaurant-phone">
                                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($restaurant['phone']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary status-badge">
                                                    <?= htmlspecialchars($restaurant['plan_name'] ?? 'Sin plan') ?>
                                                </span>
                                                <?php if ($restaurant['plan_price']): ?>
                                                    <div class="small text-muted mt-1">
                                                        $<?= number_format($restaurant['plan_price'], 0, ',', '.') ?>/mes
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $restaurant['is_active'] ? 'bg-success' : 'bg-danger' ?> status-badge">
                                                    <?= $restaurant['is_active'] ? 'Activo' : 'Inactivo' ?>
                                                </span>
                                                <div class="small text-muted mt-1">
                                                    <?php if ($restaurant['subscription_status'] === 'trial'): ?>
                                                        <i class="fas fa-clock"></i> Prueba
                                                    <?php elseif ($restaurant['subscription_status'] === 'active'): ?>
                                                        <i class="fas fa-check-circle"></i> Suscripción Activa
                                                    <?php elseif ($restaurant['subscription_status'] === 'expired'): ?>
                                                        <i class="fas fa-times-circle"></i> Expirada
                                                    <?php else: ?>
                                                        <i class="fas fa-ban"></i> Cancelada
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="stats-grid">
                                                    <div class="stat-item">
                                                        <div class="stat-value"><?= $restaurant['branches_count'] ?? 0 ?></div>
                                                        <div>Sucursales</div>
                                                    </div>
                                                    <div class="stat-item">
                                                        <div class="stat-value"><?= $restaurant['products_count'] ?? 0 ?></div>
                                                        <div>Productos</div>
                                                    </div>
                                                    <div class="stat-item">
                                                        <div class="stat-value"><?= $restaurant['categories_count'] ?? 0 ?></div>
                                                        <div>Categorías</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <i class="fas fa-calendar"></i> 
                                                    <?= date('d/m/Y', strtotime($restaurant['created_at'])) ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?= date('H:i', strtotime($restaurant['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-info btn-action" 
                                                            onclick="showRestaurantInfo(<?= htmlspecialchars(json_encode($restaurant)) ?>)">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                    <a href="../<?= $restaurant['slug'] ?>" target="_blank" 
                                                       class="btn btn-sm btn-outline-primary btn-action">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                    <a href="edit-restaurant.php?id=<?= $restaurant['id'] ?>" 
                                                       class="btn btn-sm btn-outline-warning btn-action">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-action dropdown-toggle" 
                                                            data-bs-toggle="dropdown">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="restaurant_id" value="<?= $restaurant['id'] ?>">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="fas fa-toggle-<?= $restaurant['is_active'] ? 'on' : 'off' ?>"></i>
                                                                    <?= $restaurant['is_active'] ? 'Desactivar' : 'Activar' ?>
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <button type="button" class="dropdown-item text-danger" 
                                                                    onclick="confirmDelete(<?= $restaurant['id'] ?>, '<?= htmlspecialchars($restaurant['name']) ?>')">
                                                                <i class="fas fa-trash"></i> Eliminar
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Navegación de páginas">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                <i class="fas fa-chevron-left"></i> Anterior
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                Siguiente <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div><br> <br> 

    <!-- Modal para información detallada -->
    <div class="modal fade" id="restaurantInfoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-store"></i> <span id="modalRestaurantName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalRestaurantContent">
                    <!-- Contenido dinámico -->
                </div>
                <div class="modal-footer">
                    <a href="#" id="modalViewPage" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> Ver Página
                    </a>
                    <a href="#" id="modalEditRestaurant" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div> 

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para mostrar información detallada del restaurante
        function showRestaurantInfo(restaurant) {
            const formatDate = (dateString) => {
                if (!dateString) return 'No disponible';
                return new Date(dateString).toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            };
            
            // Determinar el estado de la suscripción
            let subscriptionStatus = 'Sin suscripción';
            let subscriptionStatusClass = 'bg-secondary';
            
            if (restaurant.subscription_status === 'trial') {
                subscriptionStatus = 'Prueba';
                subscriptionStatusClass = 'bg-warning';
            } else if (restaurant.subscription_status === 'active') {
                subscriptionStatus = 'Activa';
                subscriptionStatusClass = 'bg-success';
            } else if (restaurant.subscription_status === 'expired') {
                subscriptionStatus = 'Expirada';
                subscriptionStatusClass = 'bg-danger';
            } else if (restaurant.subscription_status === 'cancelled') {
                subscriptionStatus = 'Cancelada';
                subscriptionStatusClass = 'bg-secondary';
            }
            
            // Actualizar el modal
            document.getElementById('modalRestaurantName').textContent = restaurant.name;
            document.getElementById('modalViewPage').href = '../' + restaurant.slug;
            document.getElementById('modalEditRestaurant').href = 'edit-restaurant.php?id=' + restaurant.id;
            
            // Generar contenido del modal
            const modalContent = `
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <tbody>
                            <tr>
                                <th class="table-primary" style="width: 30%;">
                                    <i class="fas fa-store"></i> Nombre del Restaurante
                                </th>
                                <td>${restaurant.name}</td>
                            </tr>
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-link"></i> URL del Sitio
                                </th>
                                <td>
                                    <a href="../${restaurant.slug}" target="_blank" class="text-decoration-none">
                                        ../${restaurant.slug} <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-envelope"></i> Email de Contacto
                                </th>
                                <td>${restaurant.email}</td>
                            </tr>
                            ${restaurant.phone ? `
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-phone"></i> Teléfono
                                </th>
                                <td>${restaurant.phone}</td>
                            </tr>
                            ` : ''}
                            ${restaurant.address ? `
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-map-marker-alt"></i> Dirección
                                </th>
                                <td>${restaurant.address}</td>
                            </tr>
                            ` : ''}
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-credit-card"></i> Plan Actual
                                </th>
                                <td>
                                    <span class="badge bg-primary fs-6">${restaurant.plan_name || 'Sin plan'}</span>
                                    ${restaurant.plan_price ? `
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-dollar-sign"></i> $${parseFloat(restaurant.plan_price).toLocaleString('es-ES')}/mes
                                            </small>
                                        </div>
                                    ` : ''}
                                </td>
                            </tr>
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-calendar-check"></i> Estado de Suscripción
                                </th>
                                <td>
                                    <span class="badge ${subscriptionStatusClass} fs-6">${subscriptionStatus}</span>
                                </td>
                            </tr>
                            ${restaurant.trial_ends_at ? `
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-clock"></i> Fecha de Término de Prueba
                                </th>
                                <td>
                                    <span class="text-${new Date(restaurant.trial_ends_at) > new Date() ? 'success' : 'danger'}">
                                        <i class="fas fa-calendar-alt"></i> ${formatDate(restaurant.trial_ends_at)}
                                    </span>
                                    ${new Date(restaurant.trial_ends_at) > new Date() ? 
                                        `<div class="mt-1"><small class="text-success"><i class="fas fa-check-circle"></i> Prueba activa</small></div>` : 
                                        `<div class="mt-1"><small class="text-danger"><i class="fas fa-times-circle"></i> Prueba expirada</small></div>`
                                    }
                                </td>
                            </tr>
                            ` : ''}
                            ${restaurant.subscription_ends_at ? `
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-calendar-times"></i> Fecha de Término de Suscripción
                                </th>
                                <td>
                                    <span class="text-${new Date(restaurant.subscription_ends_at) > new Date() ? 'success' : 'danger'}">
                                        <i class="fas fa-calendar-alt"></i> ${formatDate(restaurant.subscription_ends_at)}
                                    </span>
                                    ${new Date(restaurant.subscription_ends_at) > new Date() ? 
                                        `<div class="mt-1"><small class="text-success"><i class="fas fa-check-circle"></i> Suscripción activa</small></div>` : 
                                        `<div class="mt-1"><small class="text-danger"><i class="fas fa-times-circle"></i> Suscripción expirada</small></div>`
                                    }
                                </td>
                            </tr>
                            ` : ''}
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-toggle-on"></i> Estado del Restaurante
                                </th>
                                <td>
                                    <span class="badge ${restaurant.is_active ? 'bg-success' : 'bg-danger'} fs-6">
                                        ${restaurant.is_active ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-calendar-plus"></i> Fecha de Creación
                                </th>
                                <td>
                                    <i class="fas fa-calendar-alt"></i> ${formatDate(restaurant.created_at)}
                                </td>
                            </tr>
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-chart-bar"></i> Estadísticas
                                </th>
                                <td>
                                    <div class="row">
                                        <div class="col-md-4 text-center">
                                            <div class="border rounded p-2">
                                                <div class="h5 text-primary mb-0">${restaurant.branches_count || 0}</div>
                                                <small class="text-muted">Sucursales</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <div class="border rounded p-2">
                                                <div class="h5 text-success mb-0">${restaurant.products_count || 0}</div>
                                                <small class="text-muted">Productos</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <div class="border rounded p-2">
                                                <div class="h5 text-info mb-0">${restaurant.categories_count || 0}</div>
                                                <small class="text-muted">Categorías</small>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            ${restaurant.description ? `
                            <tr>
                                <th class="table-primary">
                                    <i class="fas fa-align-left"></i> Descripción
                                </th>
                                <td>${restaurant.description}</td>
                            </tr>
                            ` : ''}
                        </tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('modalRestaurantContent').innerHTML = modalContent;
            
            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('restaurantInfoModal'));
            modal.show();
        }

        // Función para confirmar eliminación
        function confirmDelete(restaurantId, restaurantName) {
            if (confirm(`¿Estás seguro de eliminar el restaurante "${restaurantName}"? Esta acción no se puede deshacer.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="restaurant_id" value="${restaurantId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Mostrar mensaje si existe
        <?php if (isset($_GET['message'])): ?>
            const message = '<?= addslashes($_GET['message']) ?>';
            if (message) {
                const alertHtml = `
                    
                `;
                const container = document.querySelector('.container-fluid');
                if (container) {
                    container.insertAdjacentHTML('afterbegin', alertHtml);
                }
            }
        <?php endif; ?>
    </script>
</body>
</html>
