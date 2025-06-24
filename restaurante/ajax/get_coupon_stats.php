<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    if (empty($_POST['coupon_id'])) {
        throw new Exception('ID del cupón requerido');
    }

    $coupon_id = intval($_POST['coupon_id']);

    // Obtener información del cupón
    $stmt = $conn->prepare("
        SELECT c.*, 
               COUNT(cu.id) as total_uses,
               COALESCE(SUM(cu.discount_amount), 0) as total_discount_given,
               COALESCE(AVG(cu.discount_amount), 0) as avg_discount_per_use,
               COALESCE(SUM(cu.order_total), 0) as total_orders_value
        FROM coupons c
        LEFT JOIN coupon_usage cu ON c.id = cu.coupon_id
        WHERE c.id = ? AND c.restaurant_id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$coupon_id, $restaurant_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        throw new Exception('Cupón no encontrado');
    }

    // Obtener historial de usos recientes
    $stmt = $conn->prepare("
        SELECT cu.*, o.status as order_status
        FROM coupon_usage cu
        LEFT JOIN orders o ON cu.order_id = o.id
        WHERE cu.coupon_id = ?
        ORDER BY cu.used_at DESC
        LIMIT 10
    ");
    $stmt->execute([$coupon_id]);
    $recent_uses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener estadísticas por día (últimos 30 días)
    $stmt = $conn->prepare("
        SELECT DATE(cu.used_at) as date,
               COUNT(*) as uses,
               SUM(cu.discount_amount) as total_discount
        FROM coupon_usage cu
        WHERE cu.coupon_id = ? 
        AND cu.used_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(cu.used_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$coupon_id]);
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estadísticas adicionales
    $is_expired = strtotime($coupon['valid_until']) < time();
    $is_active = $coupon['is_active'] && !$is_expired;
    $usage_percentage = $coupon['usage_limit'] ? ($coupon['used_count'] / $coupon['usage_limit']) * 100 : 0;
    $days_remaining = $is_expired ? 0 : ceil((strtotime($coupon['valid_until']) - time()) / (24 * 60 * 60));

    // Generar HTML para las estadísticas
    ob_start();
    ?>
    <div class="stats-section">
        <div class="row">
            <div class="col-md-6">
                <div class="coupon-info-card">
                    <h6><i class="fas fa-info-circle me-2"></i>Información del Cupón</h6>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Código:</strong></td>
                            <td><span class="badge bg-primary stats-badge"><?= htmlspecialchars($coupon['code']) ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Nombre:</strong></td>
                            <td><?= htmlspecialchars($coupon['name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Descuento:</strong></td>
                            <td>
                                <span class="badge bg-success stats-badge">
                                    <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                        <?= $coupon['discount_value'] ?>%
                                    <?php else: ?>
                                        $<?= number_format($coupon['discount_value'], 0, ',', '.') ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Estado:</strong></td>
                            <td>
                                <?php if ($is_active): ?>
                                    <span class="badge bg-success stats-badge">Activo</span>
                                <?php elseif ($is_expired): ?>
                                    <span class="badge bg-danger stats-badge">Expirado</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary stats-badge">Inactivo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Válido hasta:</strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($coupon['valid_until'])) ?></td>
                        </tr>
                        <?php if (!$is_expired): ?>
                        <tr>
                            <td><strong>Días restantes:</strong></td>
                            <td><span class="badge bg-info stats-badge"><?= $days_remaining ?> días</span></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="coupon-info-card">
                    <h6><i class="fas fa-chart-pie me-2"></i>Estadísticas de Uso</h6>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="card stats-card bg-primary text-white">
                                <div class="card-body">
                                    <h4><?= $coupon['total_uses'] ?></h4>
                                    <small>Total Usos</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="card stats-card bg-success text-white">
                                <div class="card-body">
                                    <h4>$<?= number_format($coupon['total_discount_given'], 0, ',', '.') ?></h4>
                                    <small>Descuento Total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="card stats-card bg-info text-white">
                                <div class="card-body">
                                    <h4>$<?= number_format($coupon['avg_discount_per_use'], 0, ',', '.') ?></h4>
                                    <small>Promedio por Uso</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="card stats-card bg-warning text-dark">
                                <div class="card-body">
                                    <h4>$<?= number_format($coupon['total_orders_value'], 0, ',', '.') ?></h4>
                                    <small>Valor Total Órdenes</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($coupon['usage_limit']): ?>
                    <div class="progress-container">
                        <label class="form-label fw-bold">Progreso de Uso</label>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?= min($usage_percentage, 100) ?>%"></div>
                        </div>
                        <small class="text-muted mt-2 d-block"><?= $coupon['used_count'] ?> de <?= $coupon['usage_limit'] ?> usos (<?= round($usage_percentage, 1) ?>%)</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($recent_uses)): ?>
    <div class="stats-section">
        <h6><i class="fas fa-history me-2"></i>Usos Recientes</h6>
        <div class="stats-table">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar me-1"></i>Fecha</th>
                            <th><i class="fas fa-user me-1"></i>Cliente</th>
                            <th><i class="fas fa-tag me-1"></i>Descuento</th>
                            <th><i class="fas fa-shopping-cart me-1"></i>Total Orden</th>
                            <th><i class="fas fa-info-circle me-1"></i>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_uses as $use): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($use['used_at'])) ?></td>
                            <td>
                                <?php if ($use['customer_name']): ?>
                                    <strong><?= htmlspecialchars($use['customer_name']) ?></strong>
                                    <?php if ($use['customer_phone']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($use['customer_phone']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Sin nombre</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-success">$<?= number_format($use['discount_amount'], 0, ',', '.') ?></span></td>
                            <td><strong>$<?= number_format($use['order_total'], 0, ',', '.') ?></strong></td>
                            <td>
                                <?php
                                $status_badges = [
                                    'pending' => 'bg-warning',
                                    'confirmed' => 'bg-info',
                                    'preparing' => 'bg-primary',
                                    'ready' => 'bg-success',
                                    'delivered' => 'bg-success',
                                    'cancelled' => 'bg-danger'
                                ];
                                $status_class = $status_badges[$use['order_status']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?= $status_class ?>"><?= ucfirst($use['order_status'] ?? 'N/A') ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($daily_stats)): ?>
    <div class="stats-section">
        <h6><i class="fas fa-calendar-alt me-2"></i>Actividad Últimos 30 Días</h6>
        <div class="stats-table">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar me-1"></i>Fecha</th>
                            <th><i class="fas fa-users me-1"></i>Usos</th>
                            <th><i class="fas fa-dollar-sign me-1"></i>Descuento Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_stats as $stat): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($stat['date'])) ?></strong></td>
                            <td><span class="badge bg-primary"><?= $stat['uses'] ?></span></td>
                            <td><strong>$<?= number_format($stat['total_discount'], 0, ',', '.') ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    error_log("Error al obtener estadísticas del cupón: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
