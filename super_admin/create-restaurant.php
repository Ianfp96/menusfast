<?php
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../config/functions.php';
requireLogin('super_admin');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';

if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $plan_id = $_POST['plan_id'] ?? 1;
    $duration_months = (int)($_POST['duration_months'] ?? 1);
    
    // Validaciones
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Nombre, email y contraseña son obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        // Verificar si el email ya existe
        $query = "SELECT id FROM restaurants WHERE email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = 'Ya existe un restaurante con este email';
        } else {
            // Generar slug único
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            $original_slug = $slug;
            $counter = 1;
            
            // Verificar que el slug sea único
            do {
                $query = "SELECT id FROM restaurants WHERE slug = :slug";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':slug', $slug);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $slug = $original_slug . '-' . $counter;
                    $counter++;
                } else {
                    break;
                }
            } while (true);
            
            // Encriptar contraseña
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Calcular precio con descuento
            $query = "SELECT base_price FROM plans WHERE id = :plan_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':plan_id', $plan_id);
            $stmt->execute();
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $base_price = $plan['base_price'];
            $discount = 0;
            if ($duration_months === 3) $discount = 10;
            elseif ($duration_months === 6) $discount = 15;
            elseif ($duration_months === 12) $discount = 20;
            
            $final_price = $base_price * (1 - $discount/100);
            $total_price = $final_price * $duration_months;
            
            // Insertar restaurante
            try {
                $conn->beginTransaction();
                
                // Insertar restaurante
                $query = "INSERT INTO restaurants (name, slug, email, password, phone, address, description, 
                          current_plan_id, subscription_status, trial_ends_at) 
                          VALUES (:name, :slug, :email, :password, :phone, :address, :description, 
                          :plan_id, 'trial', DATE_ADD(NOW(), INTERVAL 7 DAY))";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':slug', $slug);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':plan_id', $plan_id);
                
                if ($stmt->execute()) {
                    $restaurant_id = $conn->lastInsertId();
                    
                    // Insertar suscripción usando el ID del restaurante
                    $query = "INSERT INTO subscriptions (restaurant_id, plan_id, duration_months, price, start_date, end_date, status) 
                              VALUES (:restaurant_id, :plan_id, :duration_months, :price, NOW(), DATE_ADD(NOW(), INTERVAL :duration_months MONTH), 'active')";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':restaurant_id', $restaurant_id);
                    $stmt->bindParam(':plan_id', $plan_id);
                    $stmt->bindParam(':duration_months', $duration_months);
                    $stmt->bindParam(':price', $total_price);
                    $stmt->execute();
                    
                    $conn->commit();
                    $message = 'Restaurante creado exitosamente. URL: ' . BASE_URL . '/' . $slug;
                    
                    // Redirigir a la lista de restaurantes con mensaje de éxito
                    header('Location: ' . BASE_URL . '/super_admin/restaurants.php?edited=true&message=' . urlencode($message));
                    exit;
                } else {
                    $conn->rollback();
                    $error = 'Error al crear el restaurante';
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Obtener planes disponibles
$query = "SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price";
$stmt = $conn->prepare($query);
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Restaurante - Super Admin</title>
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
        
        /* Estilos profesionales para el contenido principal */
        .col-md-9, .col-lg-10 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 0.8rem;
            border: 2px solid #e8f4fd;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            padding: 0.8rem 1rem;
            font-size: 1rem;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
            background: #ffffff;
            transform: translateY(-2px);
        }
        
        .btn {
            border-radius: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 0.8rem 1.5rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 0.9rem;
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
        
        .btn-secondary {
            background: linear-gradient(45deg, #6c757d, #495057);
            border: none;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(45deg, #5a6268, #343a40);
            transform: translateY(-2px);
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
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .card {
            border-radius: 1.5rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
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
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1.5rem;
        }
        
        .text-muted {
            color: #6c757d !important;
            font-size: 0.9rem;
            font-style: italic;
        }
        
        /* Estilos para la información de precios */
        #price-info {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border: 2px solid #2196f3;
            border-radius: 1rem;
            padding: 1.5rem;
        }
        
        #price-info strong {
            color: #1976d2;
            font-weight: 600;
        }
        
        /* Animaciones y efectos */
        .form-control:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
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
                        <a class="nav-link" href="/super_admin/restaurants.php">
                            <i class="fas fa-store"></i> Restaurantes
                        </a>
                        <a class="nav-link active" href="/super_admin/create-restaurant.php">
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
                        <h1>Crear Nuevo Restaurante</h1>
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
                    
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Información del Restaurante</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="name" class="form-label">Nombre del Restaurante *</label>
                                                    <input type="text" class="form-control" id="name" name="name" 
                                                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                                    <small class="text-muted">Se generará automáticamente la URL</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email *</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                                    <small class="text-muted">Email para acceder al panel</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="password" class="form-label">Contraseña *</label>
                                                    <input type="password" class="form-control" id="password" name="password" required>
                                                    <small class="text-muted">Mínimo 6 caracteres</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="phone" class="form-label">Teléfono</label>
                                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Dirección</label>
                                            <input type="text" class="form-control" id="address" name="address" 
                                                   value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Descripción</label>
                                            <textarea class="form-control" id="description" name="description" rows="3" 
                                                      placeholder="Describe el tipo de comida, especialidades, etc."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="plan_id" class="form-label">Plan</label>
                                                <select class="form-select" id="plan_id" name="plan_id" required onchange="updateDurationOptions()">
                                                    <option value="">Seleccione un plan</option>
                                                    <?php foreach ($plans as $plan): ?>
                                                        <option value="<?php echo $plan['id']; ?>" data-price="<?php echo $plan['base_price']; ?>">
                                                            <?php echo htmlspecialchars($plan['name']); ?> - <?php echo formatCurrency($plan['base_price'], null); ?>/mes
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="duration_months" class="form-label">Duración de la Suscripción</label>
                                                <select class="form-select" id="duration_months" name="duration_months" required onchange="updatePrice()">
                                                    <!-- Las opciones se cargarán dinámicamente con JavaScript -->
                                                </select>
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
                                        
                                        <div class="d-flex justify-content-between">
                                            <a href="/super_admin/restaurants.php" class="btn btn-secondary">
                                                Cancelar
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Crear Restaurante
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
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

        // Inicializar las opciones de duración al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            updateDurationOptions();
        });
    </script>
</body>
</html>
