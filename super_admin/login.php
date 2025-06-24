<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

session_start();

// Configurar headers de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src \'self\' data: https:; connect-src \'self\';');

// Si ya está logueado como super admin, redirigir al dashboard
if (isset($_SESSION['super_admin_id'])) {
    redirect(BASE_URL . '/super_admin/dashboard.php');
}

$error = '';
$success = '';

// Configuración de seguridad
$max_attempts = 5;
$lockout_time = 900; // 15 minutos
$session_timeout = 3600; // 1 hora

// Función para verificar si la IP está bloqueada
function isIPBlocked($conn, $ip) {
    try {
        // Verificar si la tabla existe
        $stmt = $conn->prepare("SHOW TABLES LIKE 'login_attempts'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            return false; // Si la tabla no existe, no hay bloqueo
        }
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$ip, $GLOBALS['lockout_time']]);
        return $stmt->fetchColumn() >= $GLOBALS['max_attempts'];
    } catch (PDOException $e) {
        error_log("Error verificando bloqueo de IP: " . $e->getMessage());
        return false;
    }
}

// Función para registrar intento de login
function logLoginAttempt($conn, $email, $ip, $success, $reason = '') {
    try {
        // Verificar si la tabla existe
        $stmt = $conn->prepare("SHOW TABLES LIKE 'login_attempts'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // Crear tabla si no existe
            $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                success TINYINT(1) DEFAULT 0,
                reason VARCHAR(255),
                attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_time (ip_address, attempt_time),
                INDEX idx_email_time (email, attempt_time)
            )";
            $conn->exec($sql);
        }
        
        $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address, success, reason, attempt_time) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$email, $ip, $success ? 1 : 0, $reason]);
    } catch (PDOException $e) {
        error_log("Error registrando intento de login: " . $e->getMessage());
    }
}

// Función para limpiar intentos antiguos
function cleanOldAttempts($conn) {
    try {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error limpiando intentos antiguos: " . $e->getMessage());
    }
}

// Obtener IP real del usuario
function getRealIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Verificar si la cuenta está activa
function isAccountActive($conn, $admin_id) {
    try {
        // Verificar si la columna is_active existe
        $stmt = $conn->prepare("SHOW COLUMNS FROM super_admins LIKE 'is_active'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            return true; // Si no existe la columna, considerar activa
        }
        
        $stmt = $conn->prepare("SELECT is_active FROM super_admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['is_active'] == 1;
    } catch (PDOException $e) {
        error_log("Error verificando estado de cuenta: " . $e->getMessage());
        return true; // En caso de error, permitir acceso
    }
}

try {
    $client_ip = getRealIP();
    
    // Limpiar intentos antiguos
    cleanOldAttempts($conn);
    
    // Verificar si la IP está bloqueada
    if (isIPBlocked($conn, $client_ip)) {
        $error = 'Demasiados intentos fallidos. Tu IP ha sido bloqueada temporalmente. Intenta nuevamente en 15 minutos.';
    } else {
        // Procesar el formulario de login
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $csrf_token = $_POST['csrf_token'] ?? '';
            $remember = isset($_POST['remember']);
            
            // Validaciones de seguridad
            if (empty($email) || empty($password)) {
                $error = 'Email y contraseña son requeridos';
                logLoginAttempt($conn, $email, $client_ip, false, 'Campos vacíos');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Formato de email inválido';
                logLoginAttempt($conn, $email, $client_ip, false, 'Email inválido');
            } elseif (strlen($password) < 6) {
                $error = 'Contraseña demasiado corta';
                logLoginAttempt($conn, $email, $client_ip, false, 'Contraseña corta');
            } elseif (!verifyCSRFToken($csrf_token)) {
                $error = 'Error de seguridad. Por favor, recarga la página e intenta nuevamente.';
                logLoginAttempt($conn, $email, $client_ip, false, 'Token CSRF inválido');
            } else {
                try {
                    // Buscar el super admin por email
                    $stmt = $conn->prepare("SELECT * FROM super_admins WHERE email = ?");
                    $stmt->execute([$email]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($admin && password_verify($password, $admin['password'])) {
                        // Verificar si la cuenta está activa
                        if (isAccountActive($conn, $admin['id'])) {
                            // Login exitoso - Configurar sesión segura
                            session_regenerate_id(true); // Prevenir session fixation
                            
                            $_SESSION['super_admin_id'] = $admin['id'];
                            $_SESSION['super_admin_username'] = $admin['username'];
                            $_SESSION['super_admin_email'] = $admin['email'];
                            $_SESSION['login_time'] = time();
                            $_SESSION['ip_address'] = $client_ip;
                            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                            $_SESSION['last_activity'] = time();
                            
                            // Configurar cookie de sesión segura si se solicita
                            if ($remember) {
                                try {
                                    // Verificar si la tabla remember_tokens existe
                                    $stmt = $conn->prepare("SHOW TABLES LIKE 'remember_tokens'");
                                    $stmt->execute();
                                    if ($stmt->rowCount() == 0) {
                                        // Crear tabla si no existe
                                        $sql = "CREATE TABLE IF NOT EXISTS remember_tokens (
                                            id INT AUTO_INCREMENT PRIMARY KEY,
                                            user_id INT NOT NULL,
                                            token VARCHAR(64) NOT NULL UNIQUE,
                                            expires_at TIMESTAMP NOT NULL,
                                            user_type ENUM('super_admin', 'restaurant_admin') NOT NULL,
                                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                            INDEX idx_token (token),
                                            INDEX idx_user_type (user_type),
                                            INDEX idx_expires (expires_at)
                                        )";
                                        $conn->exec($sql);
                                    }
                                    
                                    $token = bin2hex(random_bytes(32));
                                    $expires = time() + (30 * 24 * 60 * 60); // 30 días
                                    
                                    $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at, user_type) VALUES (?, ?, FROM_UNIXTIME(?), 'super_admin')");
                                    $stmt->execute([$admin['id'], $token, $expires]);
                                    
                                    setcookie('remember_token', $token, $expires, '/', '', true, true);
                                } catch (PDOException $e) {
                                    error_log("Error configurando token de recordarme: " . $e->getMessage());
                                    // Continuar sin el token de recordarme
                                }
                            }
                            
                            // Registrar login exitoso
                            logLoginAttempt($conn, $email, $client_ip, true, 'Login exitoso');
                            
                            // Registrar actividad si la función existe
                            if (function_exists('logActivity')) {
                                logActivity($admin['id'], 'super_admin_login', 'Inicio de sesión de super administrador exitoso desde IP: ' . $client_ip);
                            }
                            
                            // Limpiar intentos fallidos para esta IP
                            try {
                                $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND success = 0");
                                $stmt->execute([$client_ip]);
                            } catch (PDOException $e) {
                                // Ignorar errores de limpieza
                            }
                            
                            // Redirigir al dashboard de administración
                            redirect(BASE_URL . '/super_admin/dashboard.php');
                        } else {
                            $error = 'Cuenta deshabilitada. Contacta al administrador.';
                            logLoginAttempt($conn, $email, $client_ip, false, 'Cuenta deshabilitada');
                        }
                    } else {
                        $error = 'Email o contraseña incorrectos';
                        logLoginAttempt($conn, $email, $client_ip, false, 'Credenciales incorrectas');
                        
                        // Verificar si se debe bloquear la IP
                        if (isIPBlocked($conn, $client_ip)) {
                            $error = 'Demasiados intentos fallidos. Tu IP ha sido bloqueada temporalmente. Intenta nuevamente en 15 minutos.';
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Error en login de super admin: " . $e->getMessage());
                    $error = 'Error al intentar iniciar sesión. Por favor, intenta más tarde.';
                    logLoginAttempt($conn, $email, $client_ip, false, 'Error de base de datos');
                }
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error crítico en login: " . $e->getMessage());
    $error = 'Error del sistema. Por favor, contacta al administrador.';
}

// Generar token CSRF
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrador - Tumenufast</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="icon" type="image/png" href="/uploads/img-web/img-tumenufast.png">
    <link rel="shortcut icon" type="image/png" href="/uploads/img-web/img-tumenufast.png">
    <link rel="apple-touch-icon" href="/uploads/img-web/img-tumenufast.png">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --light-color: #f8f9fa;
            --danger-color: #e74c3c;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            backdrop-filter: blur(10px);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 1px solid #ddd;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 500;
            width: 100%;
            color: white;
            margin-top: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
            color: white;
        }
        
        .btn-login:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
        }
        
        .alert {
            border-radius: 10px;
            font-size: 0.9rem;
            border: none;
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-left: 4px solid #28a745;
        }
        
        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border-left: 4px solid #ffc107;
        }
        
        .brand-logo {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .input-group-text {
            background: transparent;
            border: 1px solid #ddd;
            border-right: none;
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .input-group .form-control:focus {
            border-left: none;
        }
        
        .security-info {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.8rem;
            color: #666;
        }
        
        .security-info i {
            color: var(--accent-color);
            margin-right: 5px;
        }
        
        .password-toggle {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--accent-color);
        }
        
        .loading {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="brand-logo">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1>Panel de Administración</h1>
            <p>Ingresa tus credenciales de administrador</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['logout']) && $_GET['logout'] == '1'): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i>
                Sesión cerrada exitosamente. ¡Hasta pronto!
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['expired']) && $_GET['expired'] == '1'): ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-clock"></i>
                Tu sesión ha expirado por inactividad. Por favor, inicia sesión nuevamente.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           required autofocus autocomplete="email">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" class="form-control" id="password" name="password" 
                           required autocomplete="current-password">
                    <span class="input-group-text password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="passwordIcon"></i>
                    </span>
                </div>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Recordarme (30 días)</label>
            </div>
            
            <button type="submit" class="btn btn-login" id="loginBtn">
                <span class="loading">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Verificando...
                </span>
                <span class="btn-text">
                    <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                </span>
            </button>
        </form>
        
        <div class="security-info">
            <div><i class="fas fa-shield-alt"></i> Conexión segura con SSL</div>
            <div><i class="fas fa-clock"></i> Sesión automática expira en 1 hora</div>
            <div><i class="fas fa-ban"></i> Bloqueo automático tras 5 intentos fallidos</div>
            <div><i class="fas fa-lock"></i> Protección CSRF activa</div>
            <div><i class="fas fa-eye-slash"></i> Prevención de session fixation</div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Prevenir múltiples envíos del formulario
        let formSubmitted = false;
        
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Por favor, completa todos los campos');
                return false;
            }
            
            // Mostrar loading
            const btn = document.getElementById('loginBtn');
            const loading = btn.querySelector('.loading');
            const btnText = btn.querySelector('.btn-text');
            
            loading.style.display = 'inline-block';
            btnText.style.display = 'none';
            btn.disabled = true;
            
            formSubmitted = true;
        });
        
        // Función para mostrar/ocultar contraseña
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }
        
        // Prevenir copiar/pegar en campos sensibles
        document.getElementById('password').addEventListener('copy', function(e) {
            e.preventDefault();
        });
        
        document.getElementById('password').addEventListener('paste', function(e) {
            e.preventDefault();
        });
        
        // Limpiar formulario al cargar la página
        window.addEventListener('load', function() {
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
        
        // Detectar intentos de automatización
        if (window.navigator.webdriver) {
            console.warn('Detectado modo de automatización');
        }
        
        // Prevenir acceso desde iframes
        if (window.self !== window.top) {
            window.top.location.href = window.self.location.href;
        }
    </script>
</body>
</html> 
