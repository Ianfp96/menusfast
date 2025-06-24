<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/restaurante/login.php');
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];

// Obtener información del restaurante
try {
    $stmt = $conn->prepare("
        SELECT r.*, 
               COALESCE(p.name, 'Plan Básico') as plan_name,
               COALESCE(p.max_categories, 5) as max_categories,
               COALESCE(p.max_products, 20) as max_products,
               COALESCE((SELECT COUNT(*) FROM menu_categories WHERE restaurant_id = r.id), 0) as current_categories,
               COALESCE((SELECT COUNT(*) FROM products WHERE restaurant_id = r.id), 0) as current_products
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        WHERE r.id = ?
    ");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$restaurant) {
        redirect(BASE_URL . '/restaurante/logout.php');
    }
} catch (PDOException $e) {
    error_log("Error al obtener información del restaurante: " . $e->getMessage());
    $error = "Error al cargar la información del restaurante";
}

// Obtener el estado del filtro
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? '';

// Construir la consulta base
$query = "
    SELECT o.*, 
           COUNT(oi.id) as total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.restaurant_id = ?
";

$params = [$restaurant_id];

// Aplicar filtros
if ($status_filter !== 'all') {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $query .= " AND DATE(o.created_at) = ?";
    $params[] = $date_filter;
}

$query .= " GROUP BY o.id ORDER BY o.created_at DESC";

// Ejecutar la consulta
$stmt = $conn->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas (solo si hay filtro de fecha)
$stats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'confirmed_orders' => 0,
    'preparing_orders' => 0,
    'ready_orders' => 0,
    'delivered_orders' => 0,
    'cancelled_orders' => 0
];

if ($date_filter) {
    $stats_query = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
            SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing_orders,
            SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_orders,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
        FROM orders 
        WHERE restaurant_id = ? AND DATE(created_at) = ?
    ";
    $stmt = $conn->prepare($stats_query);
    $stmt->execute([$restaurant_id, $date_filter]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Si no hay filtro de fecha, obtener estadísticas de todos los pedidos
    $stats_query = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
            SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing_orders,
            SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_orders,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
        FROM orders 
        WHERE restaurant_id = ?
    ";
    $stmt = $conn->prepare($stats_query);
    $stmt->execute([$restaurant_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Generar token CSRF
$csrf_token = generateCSRFToken();

$page_title = "Gestión de Pedidos";

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - <?= htmlspecialchars($restaurant['name']) ?></title>
    
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
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="p-4">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Gestión de Pedidos</h5>
                        <div class="d-flex gap-2">
                            <input type="date" class="form-control" id="dateFilter" value="<?= htmlspecialchars($date_filter) ?>" placeholder="Filtrar por fecha">
                            <select class="form-select" id="statusFilter">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Todos los estados</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pendientes</option>
                                <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmados</option>
                                <option value="preparing" <?= $status_filter === 'preparing' ? 'selected' : '' ?>>En preparación</option>
                                <option value="ready" <?= $status_filter === 'ready' ? 'selected' : '' ?>>Listos</option>
                                <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Entregados</option>
                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelados</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Estadísticas -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">Total Pedidos</h6>
                                        <h2 class="mb-0"><?= $stats['total_orders'] ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">Pendientes</h6>
                                        <h2 class="mb-0"><?= $stats['pending_orders'] ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">En Preparación</h6>
                                        <h2 class="mb-0"><?= $stats['preparing_orders'] ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">Entregados</h6>
                                        <h2 class="mb-0"><?= $stats['delivered_orders'] ?></h2>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de Pedidos -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Tipo</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?= $order['id'] ?></td>
                                            <td>
                                                <?= htmlspecialchars($order['customer_name']) ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($order['customer_phone']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($order['order_type'] === 'delivery'): ?>
                                                    <span class="badge bg-info">Delivery</span>
                                                    <?php if ($order['delivery_address']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($order['delivery_address']) ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Retiro en local</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>$<?= number_format($order['total'], 0, ',', '.') ?></td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'pending' => 'warning',
                                                    'confirmed' => 'info',
                                                    'preparing' => 'primary',
                                                    'ready' => 'success',
                                                    'delivered' => 'success',
                                                    'cancelled' => 'danger'
                                                ][$order['status']];
                                                $status_text = [
                                                    'pending' => 'Pendiente',
                                                    'confirmed' => 'Confirmado',
                                                    'preparing' => 'En preparación',
                                                    'ready' => 'Listo',
                                                    'delivered' => 'Entregado',
                                                    'cancelled' => 'Cancelado'
                                                ][$order['status']];
                                                ?>
                                                <span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                            </td>
                                            <td>
                                                <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="viewOrder(<?= $order['id'] ?>)" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#orderModal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            onclick="showQuickStatusModal(<?= $order['id'] ?>, '<?= $order['status'] ?>')"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#quickStatusModal">
                                                        <i class="fas fa-pencil-alt"></i>
                                                    </button>
                                                    <?php if ($order['status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-sm btn-success" 
                                                                onclick="updateOrderStatus(<?= $order['id'] ?>, 'confirmed', 'confirm')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="updateOrderStatus(<?= $order['id'] ?>, 'cancelled', 'cancel')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php elseif ($order['status'] === 'confirmed'): ?>
                                                        <button type="button" class="btn btn-sm btn-primary" 
                                                                onclick="updateOrderStatus(<?= $order['id'] ?>, 'preparing', 'start_preparing')">
                                                            <i class="fas fa-utensils"></i>
                                                        </button>
                                                    <?php elseif ($order['status'] === 'preparing'): ?>
                                                        <button type="button" class="btn btn-sm btn-success" 
                                                                onclick="updateOrderStatus(<?= $order['id'] ?>, 'ready', 'ready')">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    <?php elseif ($order['status'] === 'ready' && $order['order_type'] === 'delivery'): ?>
                                                        <button type="button" class="btn btn-sm btn-success" 
                                                                onclick="updateOrderStatus(<?= $order['id'] ?>, 'delivered', 'deliver')">
                                                            <i class="fas fa-truck"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <p class="text-muted mb-0">No hay pedidos para mostrar</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver detalles del pedido -->
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Pedido #<span id="orderModalId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="orderLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando detalles del pedido...</p>
                </div>
                <div id="orderError" class="alert alert-danger d-none">
                    <i class="fas fa-exclamation-circle"></i> <span id="orderErrorMessage"></span>
                </div>
                <div id="orderDetails" class="d-none">
                    <!-- El contenido se cargará dinámicamente -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para cambio rápido de estado -->
<div class="modal fade" id="quickStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar Estado del Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="quickStatusOrderId">
                <div class="list-group">
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" 
                            onclick="quickUpdateStatus('pending')">
                        <span>
                            <i class="fas fa-clock text-warning me-2"></i>
                            Pendiente
                        </span>
                        <span class="badge bg-warning rounded-pill">1</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" 
                            onclick="quickUpdateStatus('confirmed')">
                        <span>
                            <i class="fas fa-check-circle text-info me-2"></i>
                            Confirmado
                        </span>
                        <span class="badge bg-info rounded-pill">2</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" 
                            onclick="quickUpdateStatus('preparing')">
                        <span>
                            <i class="fas fa-utensils text-primary me-2"></i>
                            En preparación
                        </span>
                        <span class="badge bg-primary rounded-pill">3</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" 
                            onclick="quickUpdateStatus('ready')">
                        <span>
                            <i class="fas fa-check-double text-success me-2"></i>
                            Listo
                        </span>
                        <span class="badge bg-success rounded-pill">4</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" 
                            onclick="quickUpdateStatus('delivered')">
                        <span>
                            <i class="fas fa-truck text-secondary me-2"></i>
                            Entregado
                        </span>
                        <span class="badge bg-secondary rounded-pill">5</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-danger" 
                            onclick="quickUpdateStatus('cancelled')">
                        <span>
                            <i class="fas fa-times-circle me-2"></i>
                            Cancelado
                        </span>
                        <span class="badge bg-danger rounded-pill">6</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Función para actualizar el estado del pedido
function updateOrderStatus(orderId, status, action) {
    if (!confirm('¿Estás seguro de que deseas realizar esta acción?')) {
        return;
    }

    fetch('/restaurante/ajax/update-order-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_id: orderId,
            status: status,
            action: action,
            csrf_token: '<?= $csrf_token ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al actualizar el estado del pedido');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al actualizar el estado del pedido');
    });
}

// Función para ver detalles del pedido
function viewOrder(orderId) {
    console.log('Viewing order:', orderId); // Debug log

    // Actualizar el ID en el título
    document.getElementById('orderModalId').textContent = orderId;

    // Mostrar loading y ocultar otros elementos
    document.getElementById('orderLoading').classList.remove('d-none');
    document.getElementById('orderError').classList.add('d-none');
    document.getElementById('orderDetails').classList.add('d-none');

    // Realizar la petición
    fetch(`/restaurante/ajax/get-order-details.php?order_id=${orderId}`)
        .then(response => {
            console.log('Response status:', response.status); // Debug log
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data); // Debug log
            // Ocultar loading
            document.getElementById('orderLoading').classList.add('d-none');

            if (data.success) {
                const order = data.order;
                let html = `
                    <div class="order-details">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0">Información del Cliente</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Nombre:</strong> ${order.customer_name}</p>
                                        <p><strong>Teléfono:</strong> ${order.customer_phone}</p>
                                        ${order.customer_email ? `<p><strong>Email:</strong> ${order.customer_email}</p>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0">Detalles del Pedido</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Fecha:</strong> ${new Date(order.created_at).toLocaleString()}</p>
                                        <p><strong>Tipo:</strong> ${order.order_type === 'delivery' ? 'Delivery' : 'Retiro en local'}</p>
                                        ${order.delivery_address ? `<p><strong>Dirección:</strong> ${order.delivery_address}</p>` : ''}
                                        <p><strong>Estado actual:</strong> <span class="badge bg-${getStatusBadgeClass(order.status)}">${getStatusText(order.status)}</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Productos</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Producto</th>
                                                <th class="text-center">Cantidad</th>
                                                <th class="text-end">Precio</th>
                                                <th class="text-end">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;

                if (Array.isArray(order.items)) {
                    order.items.forEach(item => {
                        let optionsHtml = '';
                        if (item.options && item.options.length > 0) {
                            optionsHtml = '<br><small class="text-muted">';
                            item.options.forEach(option => {
                                optionsHtml += `${option.name}: ${option.options.map(opt => opt.name).join(', ')}<br>`;
                            });
                            optionsHtml += '</small>';
                        }

                        html += `
                            <tr>
                                <td>
                                    ${item.name}
                                    ${optionsHtml}
                                </td>
                                <td class="text-center">${item.quantity}</td>
                                <td class="text-end">$${Math.round(item.price).toLocaleString()}</td>
                                <td class="text-end">$${Math.round(item.price * item.quantity).toLocaleString()}</td>
                            </tr>`;
                    });
                }

                html += `
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="3" class="text-end">Total:</th>
                                                <th class="text-end">$${Math.round(order.total).toLocaleString()}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>`;

                if (order.notes) {
                    html += `
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Notas del Pedido</h6>
                            </div>
                            <div class="card-body">
                                ${order.notes}
                            </div>
                        </div>`;
                }

                if (order.history && order.history.length > 0) {
                    html += `
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Historial de Estados</h6>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    ${order.history.map(h => `
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-${getStatusBadgeClass(h.status)}"></div>
                                            <div class="timeline-content">
                                                <h6 class="mb-0">${getStatusText(h.status)}</h6>
                                                <small class="text-muted">${new Date(h.created_at).toLocaleString()}</small>
                                                ${h.notes ? `<p class="mb-0 mt-1">${h.notes}</p>` : ''}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>`;
                }

                html += '</div>';
                document.getElementById('orderDetails').innerHTML = html;
                document.getElementById('orderDetails').classList.remove('d-none');
            } else {
                // Mostrar error
                document.getElementById('orderErrorMessage').textContent = data.message || 'Error al cargar los detalles del pedido';
                document.getElementById('orderError').classList.remove('d-none');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('orderLoading').classList.add('d-none');
            document.getElementById('orderErrorMessage').textContent = 'Error al cargar los detalles del pedido: ' + error.message;
            document.getElementById('orderError').classList.remove('d-none');
        });
}

// Función auxiliar para obtener la clase del badge según el estado
function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'warning',
        'confirmed': 'info',
        'preparing': 'primary',
        'ready': 'success',
        'delivered': 'secondary',
        'cancelled': 'danger'
    };
    return classes[status] || 'secondary';
}

// Función auxiliar para obtener el texto del estado
function getStatusText(status) {
    const statusTexts = {
        'pending': 'Pendiente',
        'confirmed': 'Confirmado',
        'preparing': 'En preparación',
        'ready': 'Listo',
        'delivered': 'Entregado',
        'cancelled': 'Cancelado'
    };
    return statusTexts[status] || status;
}

// Manejar filtros
document.getElementById('dateFilter').addEventListener('change', function() {
    updateFilters();
});

document.getElementById('statusFilter').addEventListener('change', function() {
    updateFilters();
});

function updateFilters() {
    const date = document.getElementById('dateFilter').value;
    const status = document.getElementById('statusFilter').value;
    const url = new URL(window.location.href);
    
    if (date) {
        url.searchParams.set('date', date);
    } else {
        url.searchParams.delete('date');
    }
    
    if (status !== 'all') {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    
    window.location.href = url.toString();
}

// Asegurarse de que el modal se inicialice correctamente
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar todos los modales de Bootstrap
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        new bootstrap.Modal(modal);
    });

    // Agregar event listener para el modal
    var orderModal = document.getElementById('orderModal');
    orderModal.addEventListener('show.bs.modal', function(event) {
        // Limpiar el contenido anterior
        document.getElementById('orderLoading').classList.remove('d-none');
        document.getElementById('orderError').classList.add('d-none');
        document.getElementById('orderDetails').classList.add('d-none');
    });
});

// Función para mostrar el modal de cambio rápido de estado
function showQuickStatusModal(orderId, currentStatus) {
    document.getElementById('quickStatusOrderId').value = orderId;
    
    // Resaltar el estado actual
    const buttons = document.querySelectorAll('#quickStatusModal .list-group-item');
    buttons.forEach(button => {
        button.classList.remove('active');
        const status = button.getAttribute('onclick').match(/'([^']+)'/)[1];
        if (status === currentStatus) {
            button.classList.add('active');
        }
    });
}

// Función para actualizar el estado rápidamente
function quickUpdateStatus(newStatus) {
    const orderId = document.getElementById('quickStatusOrderId').value;
    const statusMap = {
        'pending': { status: 'pending', action: 'pending' },
        'confirmed': { status: 'confirmed', action: 'confirm' },
        'preparing': { status: 'preparing', action: 'start_preparing' },
        'ready': { status: 'ready', action: 'ready' },
        'delivered': { status: 'delivered', action: 'deliver' },
        'cancelled': { status: 'cancelled', action: 'cancel' }
    };

    const { status, action } = statusMap[newStatus];
    
    // Cerrar el modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('quickStatusModal'));
    modal.hide();

    // Actualizar el estado
    updateOrderStatus(orderId, status, action);
}
</script>

<style>
.timeline {
    position: relative;
    padding: 1rem 0;
}

.timeline-item {
    position: relative;
    padding-left: 2rem;
    margin-bottom: 1rem;
}

.timeline-marker {
    position: absolute;
    left: 0;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #2c3e50;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 5px;
    top: 12px;
    height: calc(100% + 1rem);
    width: 2px;
    background: #e9ecef;
}

.timeline-content {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,.125);
}

.table > :not(caption) > * > * {
    padding: 1rem;
}

.table tbody tr:last-child td {
    border-bottom: none;
}

/* Estilos para el modal de cambio rápido de estado */
.list-group-item {
    cursor: pointer;
    transition: all 0.2s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.list-group-item.active {
    background-color: #e9ecef;
    border-color: #dee2e6;
    color: #212529;
}

.list-group-item i {
    width: 20px;
    text-align: center;
}

.list-group-item .badge {
    font-size: 0.8rem;
}

/* Responsive improvements for orders page */
@media (max-width: 768px) {
    .card-body {
        margin-top: 13px;
    }
    
    /* Improve table scrolling */
    .table-responsive {
        border: none;
    }
    
    /* Better spacing for mobile */
    .p-4 {
        padding: 0.5rem !important;
    }
    
    /* Improve filter layout */
    .card-header .d-flex {
        align-items: stretch;
    }
    
    .card-header .d-flex .form-control,
    .card-header .d-flex .form-select {
        flex: 1;
    }
    
    /* Better statistics layout - Two columns */
    .row .col-md-3 {
        flex: 0 0 50% !important;
        max-width: 50% !important;
        margin-bottom: 1rem;
    }
    
    .row .col-md-3 .card {
        height: 100%;
        margin-bottom: 0;
    }
    
    .row .col-md-3 .card-body {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        min-height: 80px;
        padding: 0.75rem;
        text-align: center;
    }
    
    .row .col-md-3 .card-title {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
        line-height: 1.2;
    }
    
    .row .col-md-3 h2 {
        font-size: 1.4rem;
        margin-bottom: 0;
        font-weight: bold;
    }
    
    /* Improve button accessibility */
    .btn-group .btn {
        touch-action: manipulation;
    }
    
    /* Better modal handling */
    .modal-dialog {
        margin: 0.5rem;
    }
    
    /* Improve order details layout */
    .order-details .row {
        margin-left: -0.25rem;
        margin-right: -0.25rem;
    }
    
    .order-details .col-md-6 {
        padding-left: 0.25rem;
        padding-right: 0.25rem;
    }
}

@media (max-width: 576px) {
    .p-4 {
        padding: 0.25rem !important;
    }
    
    /* Keep two columns but more compact */
    .row .col-md-3 {
        flex: 0 0 50% !important;
        max-width: 50% !important;
        margin-bottom: 0.75rem;
    }
    
    .row .col-md-3 .card-body {
        min-height: 70px;
        padding: 0.5rem;
    }
    
    .row .col-md-3 .card-title {
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
    }
    
    .row .col-md-3 h2 {
        font-size: 1.2rem;
    }
    
    /* Hide less important table columns */
    .table thead th:nth-child(3),
    .table tbody td:nth-child(3) {
        display: none;
    }
    
    /* Stack action buttons vertically */
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn {
        width: 100%;
        margin-bottom: 0.25rem;
    }
    
    /* Better modal sizing */
    .modal-dialog.modal-lg {
        max-width: 95%;
        margin: 0.25rem;
    }
    
    /* Improve timeline on small screens */
    .timeline-item {
        padding-left: 1rem;
    }
    
    .timeline-marker {
        left: -2px;
    }
    
    /* Better list group items */
    .list-group-item {
        padding: 0.75rem 0.5rem;
    }
    
    .list-group-item .d-flex {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .list-group-item .badge {
        align-self: flex-end;
    }
}

/* Touch-friendly improvements */
@media (hover: none) and (pointer: coarse) {
    .btn-group .btn {
        min-height: 44px;
        min-width: 44px;
    }
    
    .list-group-item {
        min-height: 44px;
    }
    
    .form-control,
    .form-select {
        min-height: 44px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
