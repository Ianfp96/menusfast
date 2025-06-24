<?php
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../config/functions.php';
requireLogin('super_admin');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';
$parent_restaurant = null;
$plans = [];

// Obtener ID del restaurante padre
$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;

if (!$parent_id) {
    redirect(BASE_URL . '/super_admin/restaurants.php');
}

// Obtener datos del restaurante padre
try {
    $query = "SELECT r.*, 
              p.name as plan_name, 
              p.base_price,
              p.max_branches,
              (SELECT COUNT(*) FROM restaurants WHERE parent_restaurant_id = r.id AND is_branch = 1) as current_branches
              FROM restaurants r 
              LEFT JOIN plans p ON r.current_plan_id = p.id 
              WHERE r.id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $parent_id, PDO::PARAM_INT);
    $stmt->execute();
    $parent_restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$parent_restaurant) {
        redirect(BASE_URL . '/super_admin/restaurants.php');
    }

    // Obtener planes disponibles
    $query = "SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Error al obtener datos del restaurante: ' . $e->getMessage();
}

// Procesar formulario
if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $plan_id = (int)($_POST['plan_id'] ?? $parent_restaurant['current_plan_id']);
    
    // Validaciones
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Nombre, email y contraseña son obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        try {
            // Verificar límite de sucursales
            if ($parent_restaurant['current_branches'] >= $parent_restaurant['max_branches']) {
                $error = "El restaurante ha alcanzado el límite de sucursales ({$parent_restaurant['max_branches']})";
            } else {
                // Verificar que el email no esté en uso
                $stmt = $conn->prepare("SELECT id FROM restaurants WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Este email ya está en uso';
                } else {
                    // Generar slug único
                    $base_slug = generateSlug($name);
                    $slug = $base_slug;
                    $counter = 1;
                    
                    while (true) {
                        $stmt = $conn->prepare("SELECT id FROM restaurants WHERE slug = ?");
                        $stmt->execute([$slug]);
                        if (!$stmt->fetch()) {
                            break;
                        }
                        $slug = $base_slug . '-' . $counter;
                        $counter++;
                    }
                    
                    // Obtener el siguiente número de sucursal
                    $stmt = $conn->prepare("
                        SELECT COALESCE(MAX(branch_number), 0) + 1 as next_branch_number 
                        FROM restaurants 
                        WHERE parent_restaurant_id = ? AND is_branch = 1
                    ");
                    $stmt->execute([$parent_id]);
                    $branch_number = $stmt->fetch(PDO::FETCH_ASSOC)['next_branch_number'];
                    
                    // Encriptar contraseña
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertar la nueva sucursal
                    $stmt = $conn->prepare("
                        INSERT INTO restaurants (
                            parent_restaurant_id, is_branch, branch_number, name, slug, email, password,
                            phone, address, description, current_plan_id, subscription_status, 
                            trial_ends_at, subscription_ends_at, is_active, created_at
                        ) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'trial', DATE_ADD(NOW(), INTERVAL 30 DAY), NULL, 1, NOW())
                    ");
                    
                    $stmt->execute([
                        $parent_id,
                        $branch_number,
                        $name,
                        $slug,
                        $email,
                        $hashed_password,
                        $phone,
                        $address,
                        $description,
                        $plan_id
                    ]);
                    
                    $message = 'Sucursal creada exitosamente. URL: ' . BASE_URL . '/' . $slug;
                    
                    // Redirigir después de 2 segundos
                    header("refresh:2;url=" . BASE_URL . "/super_admin/manage-branches.php?id=" . $parent_id);
                }
            }
        } catch (PDOException $e) {
            $error = 'Error al crear la sucursal: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Sucursal - Super Admin</title>
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
                            <h1>Crear Sucursal</h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-store"></i> Para: <?= htmlspecialchars($parent_restaurant['name']) ?>
                                <span class="badge bg-primary ms-2"><?= htmlspecialchars($parent_restaurant['plan_name']) ?></span>
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="/super_admin/manage-branches.php?id=<?= $parent_id ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Volver a Sucursales
                            </a>
                            <a href="/super_admin/restaurants.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> Ver Todos los Restaurantes
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
                    
                    <!-- Información del restaurante padre -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-info-circle"></i> Información del Restaurante Padre
                                    </h5>
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Plan</small>
                                            <p class="mb-1"><strong><?= htmlspecialchars($parent_restaurant['plan_name']) ?></strong></p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Límite de Sucursales</small>
                                            <p class="mb-1"><strong><?= $parent_restaurant['max_branches'] ?></strong></p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Sucursales Actuales</small>
                                            <p class="mb-1"><strong><?= $parent_restaurant['current_branches'] ?></strong></p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Disponibles</small>
                                            <p class="mb-1">
                                                <strong class="<?= ($parent_restaurant['max_branches'] - $parent_restaurant['current_branches']) <= 0 ? 'text-danger' : 'text-success' ?>">
                                                    <?= $parent_restaurant['max_branches'] - $parent_restaurant['current_branches'] ?>
                                                </strong>
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
                                        $usage_percentage = $parent_restaurant['max_branches'] > 0 ? ($parent_restaurant['current_branches'] / $parent_restaurant['max_branches']) * 100 : 0;
                                        $progress_class = $usage_percentage >= 90 ? 'bg-danger' : ($usage_percentage >= 70 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress-bar <?= $progress_class ?>" 
                                             role="progressbar" 
                                             style="width: <?= $usage_percentage ?>%"
                                             aria-valuenow="<?= $usage_percentage ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?= $parent_restaurant['current_branches'] ?>/<?= $parent_restaurant['max_branches'] ?>
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
                    
                    <?php if ($parent_restaurant['current_branches'] >= $parent_restaurant['max_branches']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Límite alcanzado:</strong> Este restaurante ha alcanzado el límite máximo de sucursales para su plan actual.
                        </div>
                    <?php else: ?>
                        <!-- Formulario de creación -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-plus"></i> Crear Nueva Sucursal
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Nombre de la Sucursal *</label>
                                            <input type="text" class="form-control" id="name" name="name" required 
                                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                                   placeholder="Ej: Sucursal Centro">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="email" name="email" required 
                                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                                   placeholder="sucursal@restaurante.com">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label">Contraseña *</label>
                                            <input type="password" class="form-control" id="password" name="password" required 
                                                   minlength="6" placeholder="Mínimo 6 caracteres">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Teléfono</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                                   placeholder="+56 9 1234 5678">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Dirección</label>
                                        <input type="text" class="form-control" id="address" name="address" 
                                               value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                                               placeholder="Dirección de la sucursal">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" 
                                                  placeholder="Descripción opcional de la sucursal"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="plan_id" class="form-label">Plan</label>
                                        <select class="form-control" id="plan_id" name="plan_id">
                                            <?php foreach ($plans as $plan): ?>
                                                <option value="<?= $plan['id'] ?>" 
                                                        <?= ($_POST['plan_id'] ?? $parent_restaurant['current_plan_id']) == $plan['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($plan['name']) ?> - $<?= number_format($plan['base_price'], 0, ',', '.') ?>/mes
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Por defecto se asigna el mismo plan que el restaurante padre</small>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Crear Sucursal
                                        </button>
                                        <a href="/super_admin/manage-branches.php?id=<?= $parent_id ?>" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Cancelar
                                        </a>
                                    </div>
                                </form>
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
</body>
</html> 
