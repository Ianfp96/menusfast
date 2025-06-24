<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

session_start();

// Debug temporal
error_log('Iniciando proceso de login');
error_log('Estado de la sesión antes del login: ' . print_r($_SESSION, true));

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    error_log('Usuario ya está logueado, redirigiendo a dashboard');
    redirect(BASE_URL . '/restaurante/dashboard.php');
}

$error = '';

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('Procesando formulario POST de login');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $remember = isset($_POST['remember']);

    // Detectar si es una petición AJAX
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Validar token CSRF
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        try {
            // Buscar el restaurante por email
            $stmt = $conn->prepare("SELECT * FROM restaurants WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($restaurant && password_verify($password, $restaurant['password'])) {
                error_log('Credenciales válidas para el restaurante: ' . $restaurant['id']);
                
                // Verificar estado de la suscripción
                if (!isSubscriptionActive($restaurant)) {
                    $error = 'Tu suscripción ha expirado. Por favor, contacta con soporte.';
                } else {
                    // Login exitoso - Configurar sesión
                    $_SESSION['restaurant_id'] = $restaurant['id'];
                    $_SESSION['restaurant_name'] = $restaurant['name'];
                    $_SESSION['restaurant_slug'] = $restaurant['slug'];
                    $_SESSION['plan_id'] = $restaurant['current_plan_id'];
                    $_SESSION['is_trial'] = $restaurant['subscription_status'] === 'trial';
                    
                    // Información adicional para sucursales
                    if (isset($restaurant['is_branch']) && $restaurant['is_branch'] == 1) {
                        $_SESSION['is_branch'] = true;
                        $_SESSION['parent_restaurant_id'] = $restaurant['parent_restaurant_id'];
                        $_SESSION['branch_number'] = $restaurant['branch_number'];
                        error_log('Login de sucursal - ID: ' . $restaurant['id'] . ', Padre: ' . $restaurant['parent_restaurant_id']);
                    } else {
                        $_SESSION['is_branch'] = false;
                    }
                    
                    error_log('Sesión configurada: ' . print_r($_SESSION, true));
                    
                    // Si marcó "Recordarme", establecer cookie
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expires = time() + (30 * 24 * 60 * 60); // 30 días
                        
                        // Guardar token en la base de datos
                        $stmt = $conn->prepare("INSERT INTO remember_tokens (restaurant_id, token, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))");
                        $stmt->execute([$restaurant['id'], $token, $expires]);
                        
                        // Establecer cookie
                        setcookie('remember_token', $token, $expires, '/', '', true, true);
                    }
                    
                    // Registrar actividad
                    logActivity($restaurant['id'], 'login', 'Inicio de sesión exitoso');
                    
                    error_log('Redirigiendo a dashboard después de login exitoso');
                    
                    // Si es AJAX, enviar respuesta JSON
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'redirect' => BASE_URL . '/restaurante/dashboard.php',
                            'message' => 'Login exitoso'
                        ]);
                        exit();
                    } else {
                        // Redirigir al dashboard para peticiones normales
                        redirect(BASE_URL . '/restaurante/dashboard.php');
                    }
                }
            } else {
                error_log('Credenciales inválidas para el email: ' . $email);
                $error = 'Email o contraseña incorrectos';
            }
        } catch (PDOException $e) {
            error_log("Error en login: " . $e->getMessage());
            $error = 'Error al intentar iniciar sesión. Por favor, intenta más tarde.';
        }
    }
    
    // Si es AJAX y hay error, enviar respuesta JSON
    if ($isAjax && $error) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $error
        ]);
        exit();
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
    <title>Iniciar Sesión - Tumenufast</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../uploads/img-web/img-tumenufast.png">
    <link rel="shortcut icon" type="image/png" href="../uploads/img-web/img-tumenufast.png">
    <link rel="apple-touch-icon" href="../uploads/img-web/img-tumenufast.png">
    
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #ffa500;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: var(--dark-color);
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
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 107, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 500;
            width: 100%;
            color: white;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
            color: white;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 10px;
            font-size: 0.9rem;
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
        
        /* Estilos para el modal de éxito */
        .welcome-icon {
            animation: float 2s ease-in-out infinite;
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }
        
        .welcome-icon i {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 4rem;
            opacity: 0;
            animation: iconChange 6s infinite;
        }
        
        .welcome-icon i:nth-child(1) { animation-delay: 0s; }
        .welcome-icon i:nth-child(2) { animation-delay: 2s; }
        .welcome-icon i:nth-child(3) { animation-delay: 4s; }
        
        @keyframes iconChange {
            0%, 30% { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
            5%, 25% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            100% { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .loading-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
        }
        
        .loading-dots .dot {
            width: 8px;
            height: 8px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: dotPulse 1.5s infinite;
        }
        
        .loading-dots .dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .loading-dots .dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes dotPulse {
            0%, 100% { transform: scale(0.8); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 1; }
        }
        
        #successModal .modal-content {
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <!-- Modal de Éxito -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="modal-body text-center p-5 text-white">
                    <div class="welcome-icon mb-4">
                        <i class="fas fa-utensils"></i>
                        <i class="fas fa-hamburger"></i>
                        <i class="fas fa-pizza-slice"></i>
                    </div>
                    <h3 class="mb-3 fw-bold">¡Bienvenido de nuevo!</h3>
                    <p class="mb-0" style="font-size: 1.1rem; opacity: 0.9;">
                        Preparando tu panel de control...
                    </p>
                    <div class="loading-dots mt-4">
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <div class="brand-logo">
                <i class="fas fa-utensils"></i>
            </div>
            <h1>Bienvenido de nuevo</h1>
            <p>Ingresa tus credenciales para acceder a tu panel</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           required autofocus>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Recordarme</label>
            </div>
            
            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
            </button>
        </form>
        
        <div class="login-footer">
            <p>
                ¿No tienes una cuenta? 
                <a href="<?php echo BASE_URL; ?>/restaurante/registro.php">Regístrate gratis</a>
            </p>
            <p>
                <a href="<?php echo BASE_URL; ?>/restaurante/recuperar-password.php">
                    ¿Olvidaste tu contraseña?
                </a>
            </p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        // Función para la animación de celebración
        function celebrateAndRedirect() {
            // Mostrar modal de éxito
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();

            // Configurar confetti
            const duration = 2 * 1000;
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
            }, 2500);
        }

        // Modificar el manejo del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validar el formulario
            if (!this.checkValidity()) {
                this.reportValidity();
                return;
            }

            // Mostrar indicador de carga
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Iniciando sesión...';
            submitBtn.disabled = true;

            // Enviar formulario con fetch
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(this),
                redirect: 'follow' // Seguir redirecciones automáticamente
            })
            .then(response => {
                // Verificar si la respuesta es una redirección
                if (response.redirected) {
                    // Si hay redirección, el login fue exitoso
                    celebrateAndRedirect();
                    return null;
                }
                
                // Intentar parsear como JSON primero
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                }
                
                // Si no es JSON, procesar como HTML
                return response.text();
            })
            .then(data => {
                if (typeof data === 'object' && data !== null) {
                    // Es una respuesta JSON
                    if (data.success) {
                        // Login exitoso
                        celebrateAndRedirect();
                    } else {
                        // Error en el login
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                        errorDiv.innerHTML = `
                            <i class="fas fa-exclamation-circle"></i> ${data.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        
                        // Remover error existente si lo hay
                        const existingError = document.querySelector('.alert-danger');
                        if (existingError) {
                            existingError.remove();
                        }
                        
                        this.insertBefore(errorDiv, this.firstChild);
                    }
                } else if (typeof data === 'string') {
                    // Es una respuesta HTML
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const errorDiv = doc.querySelector('.alert-danger');
                    
                    if (errorDiv) {
                        // Remover error existente si lo hay
                        const existingError = document.querySelector('.alert-danger');
                        if (existingError) {
                            existingError.remove();
                        }
                        this.insertBefore(errorDiv, this.firstChild);
                    } else {
                        // Si no hay error visible, verificar si la página contiene el dashboard
                        if (data.includes('dashboard') || data.includes('Dashboard')) {
                            // Login exitoso, redirigir
                            celebrateAndRedirect();
                            return;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Crear mensaje de error más específico
                let errorMessage = 'Error al intentar iniciar sesión. ';
                if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                    errorMessage += 'Verifica tu conexión a internet e intenta nuevamente.';
                } else {
                    errorMessage += 'Por favor, intenta más tarde.';
                }
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                errorDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i> ${errorMessage}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                // Remover error existente si lo hay
                const existingError = document.querySelector('.alert-danger');
                if (existingError) {
                    existingError.remove();
                }
                
                this.insertBefore(errorDiv, this.firstChild);
            })
            .finally(() => {
                // Restaurar el botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    </script>
</body>
</html>
