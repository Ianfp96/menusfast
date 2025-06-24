<?php
require_once '../config/database.php';
require_once '../classes/Database.php';
requireLogin('super_admin');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';
$restaurant = null;

// Obtener ID del restaurante
$restaurant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$restaurant_id) {
    redirect(BASE_URL . '/super_admin/restaurants.php');
}

// Obtener datos del restaurante con información completa de suscripción
try {
    $query = "SELECT r.*, 
              p.name as plan_name, 
              p.base_price,
              s.id as subscription_id, 
              s.duration_months, 
              s.price as subscription_price,
              s.start_date, 
              s.end_date, 
              s.status as subscription_status,
              COALESCE(s.status, r.subscription_status) as final_subscription_status
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

    // Asegurarnos de que subscription_id esté definido
    if (!isset($restaurant['subscription_id'])) {
        $restaurant['subscription_id'] = null;
    }
    
} catch (PDOException $e) {
    $error = 'Error al obtener datos del restaurante: ' . $e->getMessage();
}

// Definir las opciones de duración con sus descuentos
$duration_options = [
    1 => ['label' => '1 mes', 'discount' => 0],
    3 => ['label' => '3 meses', 'discount' => 10],
    6 => ['label' => '6 meses', 'discount' => 15],
    12 => ['label' => '12 meses', 'discount' => 20]
];

// Opciones especiales para el Plan Inicial (Gratuito)
$free_plan_duration_options = [
    7 => ['label' => '7 días', 'discount' => 0],
    30 => ['label' => '30 días', 'discount' => 0]
];

// Calcular días restantes de suscripción
$days_remaining = 0;
if ($restaurant && isset($restaurant['final_subscription_status']) && $restaurant['final_subscription_status'] === 'active' && isset($restaurant['end_date']) && $restaurant['end_date']) {
    $end_date = new DateTime($restaurant['end_date']);
    $now = new DateTime();
    $days_remaining = $now->diff($end_date)->days;
}

// Procesar formulario
if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $plan_id = $_POST['plan_id'] ?? ($restaurant ? $restaurant['current_plan_id'] : null);
    $duration_months = (int)($_POST['duration_months'] ?? 1);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_open = isset($_POST['is_open']) ? 1 : 0;
    $subscription_status = $_POST['subscription_status'] ?? ($restaurant ? $restaurant['final_subscription_status'] : 'trial');
    
    // Validaciones
    if (empty($name) || empty($email)) {
        $error = 'Nombre y email son obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido';
    } else {
        // Verificar si el email ya existe (excluyendo el restaurante actual)
        $query = "SELECT id FROM restaurants WHERE email = :email AND id != :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':id', $restaurant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = 'Ya existe un restaurante con este email';
        } else {
            // Validación adicional: si el estado es "cancelled", la tienda debe estar inactiva
            if ($subscription_status === 'cancelled') {
                $is_active = 0;
            }
            
            // Generar nuevo slug si el nombre cambió
            $slug = $restaurant['slug'];
            if ($name !== $restaurant['name']) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                $original_slug = $slug;
                $counter = 1;
                
                // Verificar que el slug sea único
                do {
                    $query = "SELECT id FROM restaurants WHERE slug = :slug AND id != :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':slug', $slug);
                    $stmt->bindParam(':id', $restaurant_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $slug = $original_slug . '-' . $counter;
                        $counter++;
                    } else {
                        break;
                    }
                } while (true);
            }
            
            // Manejar cambios en el estado de suscripción
            $subscription_changed = false;
            $old_subscription_status = $restaurant['final_subscription_status'];
            
            // Si el estado de suscripción cambió, actualizar la tabla subscriptions
            if ($subscription_status !== $old_subscription_status) {
                $subscription_changed = true;
                
                // Si el estado cambia a "cancelled", automáticamente desactivar la tienda
                if ($subscription_status === 'cancelled') {
                    $is_active = 0;
                }
                
                if (!empty($restaurant['subscription_id'])) {
                    // Actualizar suscripción existente
                    $query = "UPDATE subscriptions SET status = :status, updated_at = NOW() WHERE id = :subscription_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':status', $subscription_status);
                    $stmt->bindParam(':subscription_id', $restaurant['subscription_id']);
                    $stmt->execute();
                } else if ($subscription_status === 'active') {
                    // Crear nueva suscripción activa si no existe
                    $query = "INSERT INTO subscriptions 
                            (restaurant_id, plan_id, duration_months, price, start_date, end_date, status) 
                            VALUES 
                            (:restaurant_id, :plan_id, :duration_months, :price, NOW(), DATE_ADD(NOW(), INTERVAL :duration_months_interval MONTH), 'active')";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
                    $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
                    $stmt->bindParam(':duration_months', $duration_months, PDO::PARAM_INT);
                    $stmt->bindParam(':duration_months_interval', $duration_months, PDO::PARAM_INT);
                    $price_zero = 0; // Variable para el precio 0
                    $stmt->bindParam(':price', $price_zero, PDO::PARAM_STR); // Precio 0 para suscripciones manuales
                    $stmt->execute();
                }
            }
            
            // Calcular precio con descuento si el plan o la duración cambió
            $needs_subscription_update = false;
            if ($plan_id != $restaurant['current_plan_id'] || $duration_months != ($restaurant['duration_months'] ?? 1)) {
                $needs_subscription_update = true;
                
                try {
                    // Obtener el precio base del plan
                    $query = "SELECT base_price FROM plans WHERE id = :plan_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$plan) {
                        throw new Exception("Plan no encontrado");
                    }
                    
                    $base_price = $plan['base_price'];
                    $discount = 0;
                    if ($duration_months === 3) $discount = 10;
                    elseif ($duration_months === 6) $discount = 15;
                    elseif ($duration_months === 12) $discount = 20;
                    
                    $final_price = $base_price * (1 - $discount/100);
                    $total_price = $final_price * $duration_months;
                    
                    // Actualizar o crear suscripción
                    if (!empty($restaurant['subscription_id'])) {
                        // Actualizar suscripción existente
                        $query = "UPDATE subscriptions SET 
                                    plan_id = :plan_id,
                                    duration_months = :duration_months,
                                    price = :price,
                                    end_date = DATE_ADD(NOW(), INTERVAL :duration_months_interval MONTH),
                                    updated_at = NOW()
                                  WHERE id = :subscription_id";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
                        $stmt->bindParam(':duration_months', $duration_months, PDO::PARAM_INT);
                        $stmt->bindParam(':duration_months_interval', $duration_months, PDO::PARAM_INT);
                        $stmt->bindParam(':price', $total_price, PDO::PARAM_STR);
                        $stmt->bindParam(':subscription_id', $restaurant['subscription_id'], PDO::PARAM_INT);
                        
                        if (!$stmt->execute()) {
                            throw new PDOException("Error al actualizar la suscripción: " . implode(" ", $stmt->errorInfo()));
                        }
                    } else {
                        // Crear nueva suscripción
                        $query = "INSERT INTO subscriptions 
                                (restaurant_id, plan_id, duration_months, price, start_date, end_date, status) 
                                VALUES 
                                (:restaurant_id, :plan_id, :duration_months, :price, NOW(), DATE_ADD(NOW(), INTERVAL :duration_months_interval MONTH), 'active')";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
                        $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
                        $stmt->bindParam(':duration_months', $duration_months, PDO::PARAM_INT);
                        $stmt->bindParam(':duration_months_interval', $duration_months, PDO::PARAM_INT);
                        $stmt->bindParam(':price', $total_price, PDO::PARAM_STR);
                        
                        if (!$stmt->execute()) {
                            throw new PDOException("Error al crear la suscripción: " . implode(" ", $stmt->errorInfo()));
                        }
                    }
                } catch (Exception $e) {
                    throw new Exception("Error en la actualización de la suscripción: " . $e->getMessage());
                }
            }
            
            // Actualizar restaurante
            try {
                $conn->beginTransaction();
                
                $query = "UPDATE restaurants SET 
                            name = :name,
                            slug = :slug,
                            email = :email,
                            phone = :phone,
                            address = :address,
                            description = :description,
                            current_plan_id = :plan_id,
                            is_active = :is_active,
                            is_open = :is_open,
                            subscription_status = :subscription_status,
                            updated_at = NOW()
                          WHERE id = :id";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
                $stmt->bindParam(':address', $address, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                $stmt->bindParam(':is_open', $is_open, PDO::PARAM_INT);
                $stmt->bindParam(':subscription_status', $subscription_status, PDO::PARAM_STR);
                $stmt->bindParam(':id', $restaurant_id, PDO::PARAM_INT);
                
                if (!$stmt->execute()) {
                    throw new PDOException("Error al actualizar el restaurante: " . implode(" ", $stmt->errorInfo()));
                }
                
                $conn->commit();
                $message = 'Restaurante actualizado exitosamente';
                
                // Actualizar datos en sesión si es necesario
                if (isset($_SESSION['restaurant_id']) && $restaurant_id == $_SESSION['restaurant_id']) {
                    $_SESSION['restaurant_name'] = $name;
                    $_SESSION['restaurant_slug'] = $slug;
                }
                
                // Redirigir a la lista de restaurantes con mensaje de éxito
                header('Location: ' . BASE_URL . '/super_admin/restaurants.php?edited=true&message=' . urlencode($message));
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                throw new Exception("Error en la actualización del restaurante: " . $e->getMessage());
            }
        }
    }
}

// Obtener planes disponibles
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
    <title>Editar Restaurante - Super Admin</title>
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
        
        .subscription-status-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .status-badge {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
        }
        
        .status-trial { background-color: #fff3cd; color: #856404; }
        .status-active { background-color: #d1edff; color: #0c5460; }
        .status-expired { background-color: #f8d7da; color: #721c24; }
        .status-cancelled { background-color: #e2e3e5; color: #383d41; }
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
                        <h1>Editar Restaurante</h1>
                        <a href="/super_admin/restaurants.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Lista
                        </a>
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
                    
                    <?php if ($restaurant): ?>
                        <div class="row justify-content-center">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Información del Restaurante</h5>
                                    </div>
                                    <div class="card-body">
                                        <!-- Información actual de suscripción -->
                                        <div class="subscription-status-info">
                                            <h6><i class="fas fa-info-circle"></i> Estado Actual de Suscripción</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <strong>Estado:</strong> 
                                                    <span class="status-badge status-<?= $restaurant['final_subscription_status'] ?>">
                                                        <?php
                                                        switch($restaurant['final_subscription_status']) {
                                                            case 'trial': echo 'Prueba'; break;
                                                            case 'active': echo 'Activa'; break;
                                                            case 'expired': echo 'Expirada'; break;
                                                            case 'cancelled': echo 'Cancelada'; break;
                                                            default: echo 'Desconocido';
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Plan:</strong> <?= htmlspecialchars($restaurant['plan_name'] ?? 'Sin plan') ?>
                                                </div>
                                            </div>
                                            <?php if ($restaurant['end_date']): ?>
                                                <div class="row mt-2">
                                                    <div class="col-md-6">
                                                        <strong>Fecha de Fin:</strong> <?= date('d/m/Y H:i', strtotime($restaurant['end_date'])) ?>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Días Restantes:</strong> 
                                                        <span class="<?= $days_remaining > 7 ? 'text-success' : ($days_remaining > 0 ? 'text-warning' : 'text-danger') ?>">
                                                            <?= $days_remaining > 0 ? $days_remaining : 'Expirada' ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <form method="POST">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="name" class="form-label">Nombre del Restaurante *</label>
                                                        <input type="text" class="form-control" id="name" name="name" 
                                                               value="<?= htmlspecialchars($restaurant['name']) ?>" required>
                                                        <small class="text-muted">URL actual: /<?= $restaurant['slug'] ?></small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="email" class="form-label">Email *</label>
                                                        <input type="email" class="form-control" id="email" name="email" 
                                                               value="<?= htmlspecialchars($restaurant['email']) ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="phone" class="form-label">Teléfono</label>
                                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                                               value="<?= htmlspecialchars($restaurant['phone']) ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="plan_id" class="form-label">Plan</label>
                                                        <select class="form-select" id="plan_id" name="plan_id" required onchange="updateDurationOptions()">
                                                            <?php foreach ($plans as $plan): ?>
                                                                <option value="<?php echo $plan['id']; ?>" 
                                                                        data-price="<?php echo $plan['base_price']; ?>"
                                                                        <?php echo ($restaurant['current_plan_id'] == $plan['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($plan['name']); ?> - <?php echo formatCurrency($plan['base_price']); ?>/mes
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="duration_months" class="form-label">Duración de la Suscripción</label>
                                                    <select class="form-select" id="duration_months" name="duration_months" required onchange="updatePrice()">
                                                        <!-- Las opciones se cargarán dinámicamente con JavaScript -->
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="subscription_status" class="form-label">Estado de Suscripción</label>
                                                        <select class="form-select" id="subscription_status" name="subscription_status" onchange="handleSubscriptionStatusChange()">
                                                            <option value="trial" <?= $restaurant['final_subscription_status'] === 'trial' ? 'selected' : '' ?>>Prueba</option>
                                                            <option value="active" <?= $restaurant['final_subscription_status'] === 'active' ? 'selected' : '' ?>>Activa</option>
                                                            <option value="expired" <?= $restaurant['final_subscription_status'] === 'expired' ? 'selected' : '' ?>>Expirada</option>
                                                            <option value="cancelled" <?= $restaurant['final_subscription_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                                                        </select>
                                                        <small class="text-muted">
                                                            <i class="fas fa-info-circle"></i> 
                                                            Cambiar el estado aquí actualizará tanto la tabla restaurants como subscriptions
                                                        </small>
                                                        <div id="cancellation-warning" class="alert alert-warning mt-2" style="display: none;">
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                            <strong>Advertencia:</strong> Al cambiar el estado a "Cancelada", la tienda se desactivará automáticamente.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="alert alert-info" id="price-info" style="display: none;">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <strong>Precio Base:</strong> $<span id="base-price">0.00</span>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <strong>Descuento:</strong> <span id="discount">0</span>%
                                                        </div>
                                                        <div class="col-md-4">
                                                            <strong>Precio Final:</strong> $<span id="final-price">0.00</span>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2">
                                                        <div class="col-12">
                                                            <strong>Total a Pagar:</strong> $<span id="total-price">0.00</span>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2">
                                                        <div class="col-12">
                                                            <strong>Fecha de Inicio:</strong> <span id="start-date"></span>
                                                        </div>
                                                        <div class="col-12">
                                                            <strong>Fecha de Fin:</strong> <span id="end-date"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="address" class="form-label">Dirección</label>
                                                <input type="text" class="form-control" id="address" name="address" 
                                                       value="<?= htmlspecialchars($restaurant['address']) ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="description" class="form-label">Descripción</label>
                                                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($restaurant['description']) ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                                                   <?= $restaurant['is_active'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="is_active">Activo</label>
                                                        </div>
                                                        <small class="text-muted">Permite el acceso al panel</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="is_open" name="is_open" 
                                                                   <?= $restaurant['is_open'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="is_open">Abierto</label>
                                                        </div>
                                                        <small class="text-muted">Muestra el restaurante como abierto</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between">
                                                <a href="/super_admin/restaurants.php" class="btn btn-secondary">
                                                    Cancelar
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Guardar Cambios
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
    
    <script>
        const durationOptions = <?php echo json_encode($duration_options); ?>;
        const freePlanDurationOptions = <?php echo json_encode($free_plan_duration_options); ?>;

        function updateDurationOptions() {
            const planSelect = document.getElementById('plan_id');
            const durationSelect = document.getElementById('duration_months');
            const selectedOption = planSelect.options[planSelect.selectedIndex];
            const planName = selectedOption.text;
            
            // Limpiar opciones actuales
            durationSelect.innerHTML = '';
            
            // Determinar qué opciones mostrar
            let options;
            if (planName.includes('Inicial') || planName.includes('Gratuito')) {
                options = freePlanDurationOptions;
            } else {
                options = durationOptions;
            }
            
            // Agregar las nuevas opciones
            for (const [value, data] of Object.entries(options)) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = data.label;
                // Seleccionar la duración actual si existe
                if (value == <?php echo $restaurant['duration_months'] ?? 1; ?>) {
                    option.selected = true;
                }
                durationSelect.appendChild(option);
            }
            
            // Actualizar el precio
            updatePrice();
        }

        function updatePrice() {
            const planSelect = document.getElementById('plan_id');
            const durationSelect = document.getElementById('duration_months');
            const selectedPlan = planSelect.options[planSelect.selectedIndex];
            const selectedDuration = durationSelect.value;
            const planName = selectedPlan.text;
            
            if (!selectedPlan.value || !selectedDuration) {
                document.getElementById('price-info').style.display = 'none';
                return;
            }
            
            const basePrice = parseFloat(selectedPlan.dataset.price);
            let discount = 0;
            let durationInMonths = parseFloat(selectedDuration);
            
            // Determinar el descuento basado en el plan y la duración
            if (planName.includes('Inicial') || planName.includes('Gratuito')) {
                // Para el plan gratuito, no hay descuento
                discount = 0;
                // Convertir días a meses para el cálculo
                durationInMonths = parseFloat(selectedDuration) / 30;
            } else {
                // Para otros planes, usar los descuentos normales
                const durationData = durationOptions[selectedDuration];
                discount = durationData ? durationData.discount : 0;
            }
            
            const finalPrice = basePrice * (1 - discount/100);
            const totalPrice = finalPrice * durationInMonths;
            
            // Actualizar la información en la interfaz
            document.getElementById('base-price').textContent = basePrice.toFixed(2);
            document.getElementById('discount').textContent = discount;
            document.getElementById('final-price').textContent = finalPrice.toFixed(2);
            document.getElementById('total-price').textContent = totalPrice.toFixed(2);
            
            // Calcular y mostrar las fechas
            const startDate = new Date();
            const endDate = new Date(startDate);
            if (planName.includes('Inicial') || planName.includes('Gratuito')) {
                endDate.setDate(startDate.getDate() + parseInt(selectedDuration));
            } else {
                endDate.setMonth(startDate.getMonth() + parseInt(selectedDuration));
            }
            
            document.getElementById('start-date').textContent = startDate.toLocaleDateString();
            document.getElementById('end-date').textContent = endDate.toLocaleDateString();
            
            document.getElementById('price-info').style.display = 'block';
        }

        function handleSubscriptionStatusChange() {
            const subscriptionStatusSelect = document.getElementById('subscription_status');
            const selectedStatus = subscriptionStatusSelect.value;
            const cancellationWarning = document.getElementById('cancellation-warning');
            const isActiveCheckbox = document.getElementById('is_active');
            
            if (selectedStatus === 'cancelled') {
                cancellationWarning.style.display = 'block';
                // Deshabilitar y desmarcar el checkbox de "Activo"
                isActiveCheckbox.checked = false;
                isActiveCheckbox.disabled = true;
            } else {
                cancellationWarning.style.display = 'none';
                // Habilitar el checkbox de "Activo"
                isActiveCheckbox.disabled = false;
            }
        }

        // Inicializar las opciones de duración al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            updateDurationOptions();
            handleSubscriptionStatusChange(); // Verificar estado inicial
        });
    </script>
</body>
</html> 
