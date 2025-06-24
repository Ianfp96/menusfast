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
$message = '';
$error = '';

// Obtener mensajes flash de la sesión
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

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

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $_SESSION['flash_error'] = 'Error de seguridad. Por favor, recarga la página e intenta nuevamente.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_credentials':
                $email = trim($_POST['email'] ?? '');
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                try {
                    // Validar email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('El correo electrónico no es válido');
                    }
                    
                    // Verificar si el email ya está en uso por otro restaurante
                    $stmt = $conn->prepare("
                        SELECT id FROM restaurants 
                        WHERE email = ? AND id != ?
                    ");
                    $stmt->execute([$email, $restaurant_id]);
                    if ($stmt->fetch()) {
                        throw new Exception('Este correo electrónico ya está en uso');
                    }
                    
                    // Si se está cambiando la contraseña
                    if (!empty($new_password)) {
                        // Verificar contraseña actual
                        $stmt = $conn->prepare("SELECT password FROM restaurants WHERE id = ?");
                        $stmt->execute([$restaurant_id]);
                        $current_hash = $stmt->fetchColumn();
                        
                        if (!password_verify($current_password, $current_hash)) {
                            throw new Exception('La contraseña actual es incorrecta');
                        }
                        
                        // Validar nueva contraseña
                        if (strlen($new_password) < 8) {
                            throw new Exception('La nueva contraseña debe tener al menos 8 caracteres');
                        }
                        
                        if ($new_password !== $confirm_password) {
                            throw new Exception('Las contraseñas no coinciden');
                        }
                        
                        // Actualizar email y contraseña
                        $stmt = $conn->prepare("
                            UPDATE restaurants 
                            SET email = ?, password = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $email,
                            password_hash($new_password, PASSWORD_DEFAULT),
                            $restaurant_id
                        ]);
                    } else {
                        // Solo actualizar email
                        $stmt = $conn->prepare("
                            UPDATE restaurants 
                            SET email = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$email, $restaurant_id]);
                    }
                    
                    $_SESSION['flash_message'] = 'Credenciales actualizadas correctamente';
                    
                    // Redirigir para evitar reenvío del formulario
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = $e->getMessage();
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
                break;
        }
    }
}

// Generar token CSRF
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil Vendedor - <?= htmlspecialchars($restaurant['name']) ?></title>
    
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
    <style>
        :root {
            --primary-color: #00D4AA;
            --primary-dark: #00b8d4;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --bg-light: #f8fafc;
            --bg-hover: #f1f5f9;
            --border-color: #eef2f7;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            --hover-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-primary);
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            box-shadow: var(--hover-shadow);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        .card-header h5 {
            margin: 0;
            color: var(--text-primary);
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 212, 170, 0.1);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
        }

        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .password-field {
            position: relative;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .p-4 {
                padding: 0 !important;
                margin-top: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
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

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user-shield me-2"></i>
                                Credenciales de Acceso
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="credentialsForm">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="update_credentials">
                                
                                <div class="mb-4">
                                    <label for="email" class="form-label">Correo Electrónico</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($restaurant['email']) ?>" required>
                                    <small class="text-muted">Este correo se usa para iniciar sesión en el sistema</small>
                                </div>

                                <hr class="my-4">

                                <h6 class="mb-3">Cambiar Contraseña</h6>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    Deja estos campos en blanco si no deseas cambiar la contraseña
                                </div>

                                <div class="mb-3 password-field">
                                    <label for="current_password" class="form-label">Contraseña Actual</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('current_password')"></i>
                                </div>

                                <div class="mb-3 password-field">
                                    <label for="new_password" class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="8">
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password')"></i>
                                    <small class="text-muted">Mínimo 8 caracteres</small>
                                </div>

                                <div class="mb-4 password-field">
                                    <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           minlength="8">
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Validación del formulario
        document.getElementById('credentialsForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword || confirmPassword) {
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden');
                }
                
                if (newPassword.length < 8) {
                    e.preventDefault();
                    alert('La nueva contraseña debe tener al menos 8 caracteres');
                }
            }
        });
    </script>
</body>
</html> 
