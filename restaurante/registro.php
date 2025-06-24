<?php
require_once '../config/database.php';
require_once '../config/functions.php'; // Asegurar que tenemos las funciones de seguridad
require_once '../config/email_config.php'; // Configuración de email
require_once '../classes/EmailService.php'; // Servicio de email

// Definir modo debug
define('DEBUG_MODE', false); // Cambiar a true para desarrollo

session_start();

// Configurar logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/registro_errors.log');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Deshabilitar en producción

// Crear directorio de logs si no existe
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// Configuración de seguridad
define('MAX_REGISTRATION_ATTEMPTS', 5); // Máximo intentos por IP
define('REGISTRATION_TIMEOUT', 3600); // 1 hora de bloqueo
define('MIN_PASSWORD_LENGTH', 8); // Contraseña mínima más segura
define('MAX_NAME_LENGTH', 100);
define('MAX_EMAIL_LENGTH', 254);
define('MAX_PHONE_LENGTH', 20);
define('MAX_ADDRESS_LENGTH', 255);
define('MAX_DESCRIPTION_LENGTH', 500);

$message = '';
$error = '';

// Verificar conexión a la base de datos
try {
    if (!$conn) {
        throw new Exception("No hay conexión a la base de datos");
    }
    $conn->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    error_log("Conexión a la base de datos establecida correctamente");
} catch (Exception $e) {
    error_log("Error de conexión a la base de datos: " . $e->getMessage());
    $error = 'Error de conexión. Por favor, intenta más tarde.';
}

// Si ya está logueado, redirigir al dashboard (a menos que se especifique forzar acceso)
if (isLoggedIn() && !isset($_GET['force'])) {
    redirect('/dashboard.php');
}

// Función para validar y sanitizar datos
function validateAndSanitizeData($data) {
    $errors = [];
    $sanitized = [];
    
    // Nombre del restaurante
    $name = trim($data['name'] ?? '');
    if (empty($name)) {
        $errors[] = 'El nombre del restaurante es obligatorio';
    } elseif (strlen($name) > MAX_NAME_LENGTH) {
        $errors[] = 'El nombre del restaurante es demasiado largo';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-\.]+$/', $name)) {
        $errors[] = 'El nombre del restaurante contiene caracteres no válidos';
    }
    $sanitized['name'] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    
    // Email
    $email = trim($data['email'] ?? '');
    if (empty($email)) {
        $errors[] = 'El email es obligatorio';
    } elseif (strlen($email) > MAX_EMAIL_LENGTH) {
        $errors[] = 'El email es demasiado largo';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El formato del email no es válido';
    }
    $sanitized['email'] = strtolower($email);
    
    // Contraseña
    $password = $data['password'] ?? '';
    $confirm_password = $data['confirm_password'] ?? '';
    
    if (empty($password)) {
        $errors[] = 'La contraseña es obligatoria';
    } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'La contraseña debe tener al menos ' . MIN_PASSWORD_LENGTH . ' caracteres';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s])/', $password)) {
        $errors[] = 'La contraseña debe contener al menos una minúscula, una mayúscula, un número y un carácter especial';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Las contraseñas no coinciden';
    }
    $sanitized['password'] = $password;
    
    // Teléfono (opcional)
    $phone = trim($data['phone'] ?? '');
    if (!empty($phone)) {
        if (strlen($phone) > MAX_PHONE_LENGTH) {
            $errors[] = 'El teléfono es demasiado largo';
        } elseif (!preg_match('/^[\+\d\s\-\(\)]+$/', $phone)) {
            $errors[] = 'El formato del teléfono no es válido';
        }
    }
    $sanitized['phone'] = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
    
    // Dirección (opcional)
    $address = trim($data['address'] ?? '');
    if (!empty($address)) {
        if (strlen($address) > MAX_ADDRESS_LENGTH) {
            $errors[] = 'La dirección es demasiado larga';
        }
    }
    $sanitized['address'] = htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
    
    // Descripción (opcional)
    $description = trim($data['description'] ?? '');
    if (!empty($description)) {
        if (strlen($description) > MAX_DESCRIPTION_LENGTH) {
            $errors[] = 'La descripción es demasiado larga';
        }
    }
    $sanitized['description'] = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    
    // Términos y condiciones
    if (!isset($data['terms']) || !$data['terms']) {
        $errors[] = 'Debes aceptar los términos y condiciones';
    }
    
    return ['errors' => $errors, 'data' => $sanitized];
}

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== Iniciando proceso de registro ===");
    
    // Verificar token CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Error de seguridad. Por favor, recarga la página e intenta nuevamente.';
        error_log("Error CSRF en registro");
        
        // Enviar respuesta JSON con error CSRF
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $error
        ]);
        exit;
    } else {
        try {
            // Validar y sanitizar datos
            $validation = validateAndSanitizeData($_POST);
            
            if (!empty($validation['errors'])) {
                $error = implode('. ', $validation['errors']);
                error_log("Errores de validación: " . $error);
                
                // Enviar respuesta JSON con errores de validación
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $error
                ]);
                exit;
            } else {
                $data = $validation['data'];
                
                // Verificar rate limiting SOLO después de validación exitosa
                $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                if (!checkRateLimit($client_ip, 'registration', 10, 1800)) { // 10 intentos en 30 minutos
                    $error = 'Demasiados intentos de registro. Por favor, espera 30 minutos antes de intentar nuevamente.';
                    error_log("Rate limit excedido para IP: " . $client_ip);
                    
                    // Enviar respuesta JSON con error de rate limiting
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => $error
                    ]);
                    exit;
                }
                
                // Verificar si el email ya existe
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM restaurants WHERE email = ?");
                $stmt->execute([$data['email']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    $error = 'Ya existe una cuenta con este email';
                    error_log("Email duplicado: " . $data['email']);
                    
                    // Enviar respuesta JSON con error de email duplicado
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => $error
                    ]);
                    exit;
                } else {
                    // Generar slug único
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));
                    $original_slug = $slug;
                    $counter = 1;
                    
                    do {
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM restaurants WHERE slug = ?");
                        $stmt->execute([$slug]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($result['count'] > 0) {
                            $slug = $original_slug . '-' . $counter;
                            $counter++;
                        } else {
                            break;
                        }
                    } while ($counter < 100); // Límite de seguridad
                    
                    // Encriptar contraseña con salt único
                    $hashed_password = password_hash($data['password'], PASSWORD_ARGON2ID, [
                        'memory_cost' => 65536,
                        'time_cost' => 4,
                        'threads' => 3
                    ]);
                    
                    // Iniciar transacción
                    $conn->beginTransaction();
                    error_log("Iniciando transacción para restaurante: " . $data['name']);
                    
                    try {
                        // Insertar restaurante con datos adicionales de seguridad
                        $stmt = $conn->prepare("
                            INSERT INTO restaurants (
                                name, slug, email, password, phone, address, description, 
                                current_plan_id, subscription_status, trial_ends_at,
                                is_active, created_at, updated_at, last_login_at,
                                registration_ip, failed_login_attempts
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, 
                                1, 'trial', DATE_ADD(NOW(), INTERVAL 7 DAY),
                                1, NOW(), NOW(), NULL,
                                ?, 0
                            )
                        ");
                        
                        $stmt->execute([
                            $data['name'], $slug, $data['email'], $hashed_password,
                            $data['phone'], $data['address'], $data['description'],
                            $client_ip
                        ]);
                        
                        $restaurant_id = $conn->lastInsertId();
                        error_log("Restaurante creado exitosamente con ID: " . $restaurant_id);
                        
                        // Intentar crear categorías de ejemplo (opcional)
                        try {
                            $example_categories = [
                                ['name' => 'Platos Principales', 'description' => 'Nuestros platos estrella', 'sort_order' => 1],
                                ['name' => 'Bebidas', 'description' => 'Refrescos, jugos y bebidas calientes', 'sort_order' => 2]
                            ];
                            
                            foreach ($example_categories as $category) {
                                $stmt = $conn->prepare("
                                    INSERT INTO menu_categories (
                                        restaurant_id, name, description, sort_order, 
                                        is_active, created_at, updated_at
                                    ) VALUES (?, ?, ?, ?, 1, NOW(), NOW())
                                ");
                                
                                $stmt->execute([
                                    $restaurant_id, $category['name'],
                                    $category['description'], $category['sort_order']
                                ]);
                            }
                            
                            error_log("Categorías creadas exitosamente");
                        } catch (Exception $e) {
                            error_log("No se pudieron crear las categorías (tabla menu_categories no existe): " . $e->getMessage());
                            // Continuar sin crear categorías
                        }
                        
                        // Confirmar transacción
                        $conn->commit();
                        error_log("Transacción completada exitosamente");
                        
                        // Iniciar sesión de forma segura
                        session_regenerate_id(true); // Prevenir session fixation
                        $_SESSION['restaurant_id'] = $restaurant_id;
                        $_SESSION['restaurant_name'] = $data['name'];
                        $_SESSION['restaurant_slug'] = $slug;
                        $_SESSION['plan_id'] = 1;
                        $_SESSION['is_trial'] = true;
                        $_SESSION['login_time'] = time();
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $_SESSION['ip_address'] = $client_ip;
                        
                        error_log("Sesión iniciada correctamente");
                        
                        // Registrar actividad de seguridad
                        logActivity($restaurant_id, 'registration', 'Registro exitoso desde IP: ' . $client_ip);
                        
                        // Enviar respuesta exitosa ANTES del correo
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'redirect' => 'dashboard.php',
                            'message' => 'Cuenta creada exitosamente',
                            'restaurant_id' => $restaurant_id
                        ]);
                        flush(); // Opcional, para enviar la respuesta inmediatamente

                        // Ahora intenta enviar el correo, pero cualquier error aquí no afecta al usuario
                        if (SEND_WELCOME_EMAIL) {
                            try {
                                $emailService = new EmailService($conn);
                                // Preparar datos para el email
                                $emailData = [
                                    'restaurant_id' => $restaurant_id,
                                    'name' => $data['name'],
                                    'email' => $data['email'],
                                    'phone' => $data['phone'],
                                    'address' => $data['address'],
                                    'description' => $data['description'],
                                    'slug' => $slug,
                                    'registration_date' => date('Y-m-d H:i:s'),
                                    'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
                                ];
                                $emailSent = $emailService->sendWelcomeEmail($emailData);
                                if ($emailSent) {
                                    error_log("Email de bienvenida enviado exitosamente a: " . $data['email']);
                                } else {
                                    error_log("Error al enviar email de bienvenida a: " . $data['email']);
                                }
                                $emailService->clearRecipients();
                            } catch (Exception $emailException) {
                                error_log("Error en el envío de email: " . $emailException->getMessage());
                            }
                        }
                        exit;
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        error_log("Error en la transacción: " . $e->getMessage());
                        throw $e;
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error en el proceso de registro: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor. Por favor, intenta más tarde.',
                'debug_info' => (defined('DEBUG_MODE') && DEBUG_MODE) ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ]);
            exit;
        }
    }
}

// Generar token CSRF
$csrf_token = generateCSRFToken();

// Obtener planes para mostrar información
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
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:;">
    <title>Registro - Tumenufast</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link rel="icon" type="image/png" href="../uploads/img-web/img-tumenufast.png">
    <link rel="shortcut icon" type="image/png" href="../uploads/img-web/img-tumenufast.png">
    <link rel="apple-touch-icon" href="../uploads/img-web/img-tumenufast.png">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .register-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .feature-list {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .feature-item i {
            color: #28a745;
            margin-right: 0.5rem;
        }
        .plan-info {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        .celebration-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 215, 0, 0.1);
            z-index: 9999;
            pointer-events: none;
            animation: fadeOut 2s ease-in-out forwards;
            animation-delay: 3s;
        }
        .password-strength {
            margin-top: 0.5rem;
        }
        .strength-bar {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
        
        /* Estilos para el campo de contraseña con ojo */
        .password-field {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
            padding: 5px;
        }
        .password-toggle:hover {
            color: #495057;
        }
        .password-field .form-control {
            padding-right: 40px;
        }
        
        /* Personalización del ancho del contenedor principal */
        @media (min-width: 992px) {
            .col-lg-10 {
                flex: 0 0 auto;
                width: 93.33333333%;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-8">
                    <div class="register-card">
                        <div class="register-header">
                            <h2><i class="fas fa-utensils"></i> Tumenufast</h2>
                            <p class="mb-0">Digitaliza tu restaurante en minutos</p>
                        </div>
                        
                        <div class="register-body">
                            <?php if (isLoggedIn()): ?>
                                <div class="alert alert-info alert-dismissible fade show">
                                    <i class="fas fa-info-circle"></i> 
                                    Ya tienes una sesión activa como <strong><?= htmlspecialchars($_SESSION['restaurant_name'] ?? 'Usuario') ?></strong>.
                                    <div class="mt-2">
                                        <a href="dashboard.php" class="btn btn-primary btn-sm">Ir al Dashboard</a>
                                        <a href="logout.php" class="btn btn-outline-secondary btn-sm">Cerrar Sesión</a>
                                        <a href="registro.php?force=1" class="btn btn-outline-info btn-sm">Registrar Nueva Cuenta</a>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <!-- Formulario de Registro -->
                                <div class="col-lg-7">
                                    <h4 class="mb-4">Crear Cuenta Gratuita</h4>
                                    
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
                                    
                                    <form method="POST" id="registrationForm" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="name" class="form-label">Nombre del Restaurante *</label>
                                                    <input type="text" class="form-control" id="name" name="name" 
                                                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required
                                                           placeholder="Mi Restaurante" maxlength="<?= MAX_NAME_LENGTH ?>"
                                                           pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-\.]+">
                                                    <div class="invalid-feedback">
                                                        Por favor ingresa un nombre válido para el restaurante.
                                                    </div>
                                                    <!-- URL Preview -->
                                                    <div class="mt-2" id="urlPreview" style="display: none;">
                                                        <small class="text-muted">
                                                            <i class="fas fa-link"></i> Tu menú estará disponible en:
                                                            <br>
                                                            <code class="text-primary bg-light px-2 py-1 rounded" id="previewUrl" style="font-size: 0.9em; border: 1px solid #e9ecef; width: 93%; display: inline-block;">
                                                                /
                                                            </code>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email *</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required
                                                           placeholder="tu@email.com" maxlength="<?= MAX_EMAIL_LENGTH ?>">
                                                    <div class="invalid-feedback">
                                                        Por favor ingresa un email válido.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="password" class="form-label">Contraseña *</label>
                                                    <div class="password-field">
                                                        <input type="password" class="form-control" id="password" name="password" required
                                                               placeholder="Mínimo <?= MIN_PASSWORD_LENGTH ?> caracteres" minlength="<?= MIN_PASSWORD_LENGTH ?>">
                                                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                    <div class="password-strength">
                                                        <div class="strength-bar" id="strengthBar"></div>
                                                        <small id="strengthText" class="text-muted"></small>
                                                    </div>
                                                    <div class="invalid-feedback">
                                                        La contraseña debe tener al menos <?= MIN_PASSWORD_LENGTH ?> caracteres con mayúsculas, minúsculas, números y símbolos.
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                                                    <div class="password-field">
                                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                                               placeholder="Repite tu contraseña">
                                                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                    <div class="invalid-feedback">
                                                        Las contraseñas no coinciden.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Teléfono</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                                   placeholder="+1 234 567 8900" maxlength="<?= MAX_PHONE_LENGTH ?>"
                                                   pattern="[\+\d\s\-\(\)]+">
                                            <div class="invalid-feedback">
                                                Por favor ingresa un teléfono válido.
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Dirección</label>
                                            <input type="text" class="form-control" id="address" name="address" 
                                                   value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                                                   placeholder="Calle Principal 123, Ciudad" maxlength="<?= MAX_ADDRESS_LENGTH ?>">
                                            <div class="invalid-feedback">
                                                La dirección es demasiado larga.
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Descripción del Restaurante</label>
                                            <textarea class="form-control" id="description" name="description" rows="3" 
                                                      placeholder="Describe tu restaurante, tipo de comida, especialidades..." 
                                                      maxlength="<?= MAX_DESCRIPTION_LENGTH ?>"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                            <div class="invalid-feedback">
                                                La descripción es demasiado larga.
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                                <label class="form-check-label" for="terms">
                                                    Acepto los <a href="/webmenu/terminos-y-condiciones.php" target="_blank" rel="noopener noreferrer">Términos y Condiciones</a> y la 
                                                    <a href="/webmenu/politica-de-privacidad.php" target="_blank" rel="noopener noreferrer">Política de Privacidad</a>
                                                </label>
                                                <div class="invalid-feedback">
                                                    Debes aceptar los términos y condiciones.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-register w-100 mb-3" id="submitBtn">
                                            <i class="fas fa-rocket"></i> Crear Cuenta Gratuita
                                        </button>
                                        
                                        <div class="text-center">
                                            <p class="mb-0">¿Ya tienes cuenta? 
                                                <a href="/restaurante/login.php" class="text-decoration-none">
                                                    Iniciar Sesión
                                                </a>
                                            </p>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Información y Beneficios -->
                                <div class="col-lg-5">
                                    <div class="plan-info">
                                        <h5><i class="fas fa-gift"></i> ¡7 Días Gratis!</h5>
                                        <p class="mb-0">Prueba todas las funciones sin compromiso</p>
                                    </div>
                                    
                                    <div class="feature-list">
                                        <h6 class="mb-3">Lo que obtienes:</h6>
                                        
                                        <div class="feature-item">
                                            <i class="fas fa-check"></i>
                                            <span>Menú digital con código QR</span>
                                        </div>
                                        
                                        <div class="feature-item">
                                            <i class="fas fa-check"></i>
                                            <span>Panel de administración completo</span>
                                        </div>
                                        
                                        <div class="feature-item">
                                            <i class="fas fa-check"></i>
                                            <span>Personalización de marca</span>
                                        </div>
                                        
                                        <div class="feature-item">
                                            <i class="fas fa-check"></i>
                                            <span>Gestión de productos y categorías</span>
                                        </div>
                                        
                                        <div class="feature-item">
                                            <i class="fas fa-check"></i>
                                            <span>Múltiples sucursales</span>
                                        </div>
                                        
                                        <div class="feature-item">
                                            <i class="fas fa-check"></i>
                                            <span>Soporte técnico incluido</span>
                                        </div>
                                        
                                        <div class="feature-item">
                                            <i class="fas fa-check"></i>
                                            <span>Sin tarjeta de crédito requerida</span>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center">
                                        <small class="text-muted">
                                            <i class="fas fa-shield-alt text-success"></i>
                                            Tus datos están seguros y protegidos
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Éxito -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">¡Registro Exitoso!</h4>
                    <p class="mb-0">Tu cuenta ha sido creada correctamente. Serás redirigido en unos segundos...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuración de seguridad
        const MIN_PASSWORD_LENGTH = <?= MIN_PASSWORD_LENGTH ?>;
        const MAX_NAME_LENGTH = <?= MAX_NAME_LENGTH ?>;
        const MAX_EMAIL_LENGTH = <?= MAX_EMAIL_LENGTH ?>;
        const MAX_PHONE_LENGTH = <?= MAX_PHONE_LENGTH ?>;
        const MAX_ADDRESS_LENGTH = <?= MAX_ADDRESS_LENGTH ?>;
        const MAX_DESCRIPTION_LENGTH = <?= MAX_DESCRIPTION_LENGTH ?>;

        // Función para mostrar/ocultar contraseña
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.parentElement.querySelector('.password-toggle');
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
                button.title = 'Ocultar contraseña';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
                button.title = 'Mostrar contraseña';
            }
        }

        // Función para validar contraseña
        function validatePassword(password) {
            const minLength = password.length >= MIN_PASSWORD_LENGTH;
            const hasLower = /[a-z]/.test(password);
            const hasUpper = /[A-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[@$!%*?&]/.test(password);
            
            const strength = [minLength, hasLower, hasUpper, hasNumber, hasSpecial].filter(Boolean).length;
            
            return {
                isValid: strength >= 4,
                strength: strength,
                feedback: {
                    minLength,
                    hasLower,
                    hasUpper,
                    hasNumber,
                    hasSpecial
                }
            };
        }

        // Función para actualizar indicador de fortaleza de contraseña
        function updatePasswordStrength(password) {
            const validation = validatePassword(password);
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strengthClass = 'strength-weak';
            let strengthLabel = 'Muy débil';
            
            if (validation.strength >= 4) {
                strengthClass = 'strength-strong';
                strengthLabel = 'Fuerte';
            } else if (validation.strength >= 2) {
                strengthClass = 'strength-medium';
                strengthLabel = 'Media';
            }
            
            strengthBar.className = `strength-bar ${strengthClass}`;
            strengthText.textContent = `Fortaleza: ${strengthLabel}`;
            
            return validation.isValid;
        }

        // Validación en tiempo real de contraseñas
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const isValid = updatePasswordStrength(password);
            
            if (password.length > 0) {
                this.classList.toggle('is-valid', isValid);
                this.classList.toggle('is-invalid', !isValid);
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                const isValid = password === confirmPassword;
                this.classList.toggle('is-valid', isValid);
                this.classList.toggle('is-invalid', !isValid);
                this.setCustomValidity(isValid ? '' : 'Las contraseñas no coinciden');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
                this.setCustomValidity('');
            }
        });

        // Validación de longitud en tiempo real
        document.getElementById('name').addEventListener('input', function() {
            const isValid = this.value.length <= MAX_NAME_LENGTH;
            this.classList.toggle('is-invalid', this.value.length > 0 && !isValid);
            
            // Actualizar preview de URL
            updateUrlPreview(this.value);
        });

        // Función para actualizar el preview de la URL
        function updateUrlPreview(restaurantName) {
            const urlPreview = document.getElementById('urlPreview');
            const previewUrl = document.getElementById('previewUrl');
            
            if (restaurantName.trim()) {
                // Crear slug similar al que se genera en el servidor
                let slug = restaurantName.toLowerCase()
                    .trim()
                    .replace(/[áéíóúÁÉÍÓÚ]/g, function(match) {
                        const accents = {'á':'a','é':'e','í':'i','ó':'o','ú':'u','Á':'a','É':'e','Í':'i','Ó':'o','Ú':'u'};
                        return accents[match];
                    })
                    .replace(/[^a-z0-9\s-]/g, '') // Remover caracteres especiales
                    .replace(/\s+/g, '-') // Reemplazar espacios con guiones
                    .replace(/-+/g, '-') // Remover guiones múltiples
                    .replace(/^-+|-+$/g, ''); // Remover guiones al inicio y final
                
                // Si el slug está vacío después de la limpieza, usar un valor por defecto
                if (!slug) {
                    slug = 'mi-restaurante';
                }
                
                const baseUrl = window.location.origin;
                const fullUrl = `${baseUrl}/webmenu/${slug}`;
                
                previewUrl.textContent = fullUrl;
                urlPreview.style.display = 'block';
            } else {
                urlPreview.style.display = 'none';
            }
        }

        document.getElementById('email').addEventListener('input', function() {
            const isValid = this.value.length <= MAX_EMAIL_LENGTH;
            this.classList.toggle('is-invalid', this.value.length > 0 && !isValid);
        });

        document.getElementById('phone').addEventListener('input', function() {
            const isValid = this.value.length <= MAX_PHONE_LENGTH;
            this.classList.toggle('is-invalid', this.value.length > 0 && !isValid);
        });

        document.getElementById('address').addEventListener('input', function() {
            const isValid = this.value.length <= MAX_ADDRESS_LENGTH;
            this.classList.toggle('is-invalid', this.value.length > 0 && !isValid);
        });

        document.getElementById('description').addEventListener('input', function() {
            const isValid = this.value.length <= MAX_DESCRIPTION_LENGTH;
            this.classList.toggle('is-invalid', this.value.length > 0 && !isValid);
        });
        
        // Función para la animación de celebración
        function celebrateAndRedirect() {
            // Mostrar modal de éxito
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();

            // Crear overlay de celebración
            const overlay = document.createElement('div');
            overlay.className = 'celebration-overlay';
            document.body.appendChild(overlay);

            // Configurar confetti
            const duration = 3 * 1000;
            const animationEnd = Date.now() + duration;
            const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 10000 };

            function randomInRange(min, max) {
                return Math.random() * (max - min) + min;
            }

            const interval = setInterval(function() {
                const timeLeft = animationEnd - Date.now();

                if (timeLeft <= 0) {
                    return clearInterval(interval);
                }

                const particleCount = 50 * (timeLeft / duration);
                
                // Disparar confetti dorado
                confetti({
                    ...defaults,
                    particleCount,
                    origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 },
                    colors: ['#FFD700', '#FFA500', '#FFC107']
                });
                confetti({
                    ...defaults,
                    particleCount,
                    origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 },
                    colors: ['#FFD700', '#FFA500', '#FFC107']
                });
            }, 250);

            // Redirigir después de la animación
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 3500);
        }

        // Modificar el manejo del formulario con validaciones mejoradas
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Limpiar mensajes de error anteriores
            const previousErrors = this.querySelectorAll('.alert-danger');
            previousErrors.forEach(error => error.remove());
            
            // Validar el formulario
            if (!this.checkValidity()) {
                this.reportValidity();
                return;
            }

            // Validación adicional de contraseña
            const password = document.getElementById('password').value;
            const passwordValidation = validatePassword(password);
            if (!passwordValidation.isValid) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                errorDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i> La contraseña no cumple con los requisitos de seguridad.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                this.insertBefore(errorDiv, this.firstChild);
                return;
            }

            // Mostrar indicador de carga
            const submitButton = document.getElementById('submitBtn');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando cuenta...';

            // Enviar formulario con fetch
            fetch(window.location.href, {
                method: 'POST',
                body: new FormData(this),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Respuesta del servidor:', data);
                
                if (data.success) {
                    celebrateAndRedirect();
                } else {
                    // Mostrar error si existe
                    if (data.error) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                        errorDiv.innerHTML = `
                            <i class="fas fa-exclamation-circle"></i> ${data.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        this.insertBefore(errorDiv, this.firstChild);
                        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            })
            .catch(error => {
                console.error('Error completo:', error);
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                errorDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i> Error al procesar la solicitud. Por favor, intenta más tarde.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                this.insertBefore(errorDiv, this.firstChild);
            })
            .finally(() => {
                // Restaurar el botón
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });

        // Prevenir múltiples envíos
        let isSubmitting = false;
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
        });

        // Inicializar preview de URL si ya hay un valor
        document.addEventListener('DOMContentLoaded', function() {
            const nameField = document.getElementById('name');
            if (nameField.value) {
                updateUrlPreview(nameField.value);
            }
        });
    </script>
</body>
</html>
