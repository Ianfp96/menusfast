<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();

/**
 * Función para enviar email de recuperación de contraseña
 */
function sendPasswordResetEmail($email, $name, $token) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        
        // Configuración de charset para caracteres especiales
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Configuración de seguridad
        if (defined('SMTP_SECURE') && SMTP_SECURE === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (defined('SMTP_SECURE') && SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        
        // Configuración SSL adicional si está definida
        if (defined('SMTP_VERIFY_PEER')) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => SMTP_VERIFY_PEER,
                    'verify_peer_name' => defined('SMTP_VERIFY_PEER_NAME') ? SMTP_VERIFY_PEER_NAME : false,
                    'allow_self_signed' => defined('SMTP_ALLOW_SELF_SIGNED') ? SMTP_ALLOW_SELF_SIGNED : true,
                ]
            ];
        }

        // Configuración del remitente
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $name);

        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = 'Recuperación de Contraseña - TuMenuFast';
        
        $reset_url = BASE_URL . '/restaurante/recuperar-password.php?token=' . $token;
        
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>TuMenuFast</h1>
                <p style='color: white; margin: 10px 0 0 0; font-size: 16px;'>Recuperación de Contraseña</p>
            </div>
            
            <div style='padding: 40px; background: #f8f9fa;'>
                <h2 style='color: #2c3e50; margin-bottom: 20px;'>Hola {$name},</h2>
                
                <p style='color: #555; line-height: 1.6; margin-bottom: 20px;'>
                    Has solicitado recuperar tu contraseña. Para continuar, haz clic en el botón de abajo:
                </p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$reset_url}' 
                       style='background: linear-gradient(135deg, #ff6b6b, #ffa500); 
                              color: white; 
                              padding: 15px 30px; 
                              text-decoration: none; 
                              border-radius: 10px; 
                              font-weight: bold; 
                              display: inline-block; 
                              margin: 10px;'>
                        Restablecer Contraseña
                    </a>
                </div>
                
                <p style='color: #666; font-size: 14px; margin-bottom: 20px;'>
                    Si el botón no funciona, copia y pega este enlace en tu navegador:
                </p>
                
                <p style='color: #667eea; font-size: 14px; word-break: break-all;'>
                    {$reset_url}
                </p>
                
                <div style='background: #e8f4fd; padding: 20px; border-radius: 10px; margin: 30px 0;'>
                    <h3 style='color: #2c3e50; margin-top: 0;'>⚠️ Importante:</h3>
                    <ul style='color: #555; margin: 0; padding-left: 20px;'>
                        <li>Este enlace expira en 1 hora</li>
                        <li>Si no solicitaste este cambio, puedes ignorar este email</li>
                        <li>Tu contraseña actual permanecerá sin cambios hasta que completes el proceso</li>
                    </ul>
                </div>
                
                <p style='color: #555; line-height: 1.6; margin-bottom: 20px;'>
                    Si tienes alguna pregunta, no dudes en contactarnos.
                </p>
                
                <p style='color: #555; line-height: 1.6; margin-bottom: 0;'>
                    Saludos,<br>
                    <strong>Equipo TuMenuFast</strong>
                </p>
            </div>
            
            <div style='background: #2c3e50; padding: 20px; text-align: center;'>
                <p style='color: #bdc3c7; margin: 0; font-size: 14px;'>
                    © " . date('Y') . " TuMenuFast. Todos los derechos reservados.
                </p>
            </div>
        </div>";

        $mail->AltBody = "
        Recuperación de Contraseña - TuMenuFast
        
        Hola {$name},
        
        Has solicitado recuperar tu contraseña. Para continuar, visita este enlace:
        {$reset_url}
        
        Este enlace expira en 1 hora.
        
        Si no solicitaste este cambio, puedes ignorar este email.
        
        Saludos,
        Equipo TuMenuFast";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error enviando email de recuperación: " . $e->getMessage());
        return false;
    }
}

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . '/restaurante/dashboard.php');
}

$error = '';
$success = '';

// Verificar configuración SMTP
if (!defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD') || empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
    $error = 'Configuración SMTP incompleta. Contacta al administrador.';
}

// Crear tabla de password reset si no existe
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        restaurant_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires_at TIMESTAMP NOT NULL,
        used BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    error_log("Error creando tabla password_reset_tokens: " . $e->getMessage());
}

// Verificar si es un enlace de recuperación
$reset_token = $_GET['token'] ?? '';
$show_reset_form = false;
$token_valid = false; // Nueva variable para rastrear si el token es válido

if (!empty($reset_token)) {
    try {
        $stmt = $conn->prepare("SELECT prt.*, r.id as restaurant_id, r.name 
                              FROM password_reset_tokens prt 
                              JOIN restaurants r ON prt.restaurant_id = r.id 
                              WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()");
        $stmt->execute([$reset_token]);
        $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset_data) {
            $show_reset_form = true;
            $token_valid = true; // Marcar token como válido
        } else {
            // Verificar si el token existe pero está usado o expirado para dar mejor información
            $stmt = $conn->prepare("SELECT prt.used, prt.expires_at 
                                  FROM password_reset_tokens prt 
                                  WHERE prt.token = ?");
            $stmt->execute([$reset_token]);
            $token_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($token_info) {
                if ($token_info['used']) {
                    $error = 'Este enlace de recuperación ya ha sido utilizado. Por favor, solicita uno nuevo.';
                } elseif (strtotime($token_info['expires_at']) <= time()) {
                    $error = 'Este enlace de recuperación ha expirado. Por favor, solicita uno nuevo.';
                } else {
                    $error = 'El enlace de recuperación es inválido.';
                }
            } else {
                $error = 'El enlace de recuperación es inválido o ha expirado.';
            }
        }
    } catch (PDOException $e) {
        error_log("Error verificando token: " . $e->getMessage());
        $error = 'Error al verificar el enlace de recuperación.';
    }
}

// Procesar formulario de recuperación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Validar token CSRF
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        if ($action === 'request_reset') {
            // Solicitar recuperación de contraseña
            $email = sanitize($_POST['email'] ?? '');
            
            if (!isValidEmail($email)) {
                $error = 'Por favor, ingresa un email válido.';
            } elseif (!defined('SMTP_USERNAME') || empty(SMTP_USERNAME)) {
                $error = 'Configuración SMTP incompleta. Contacta al administrador.';
            } else {
                try {
                    // Verificar si el email existe
                    $stmt = $conn->prepare("SELECT id, name, email FROM restaurants WHERE email = ? AND is_active = 1");
                    $stmt->execute([$email]);
                    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($restaurant) {
                        // Generar token único
                        $token = bin2hex(random_bytes(32));
                        $expires_at = date('Y-m-d H:i:s', time() + (60 * 60)); // 1 hora

                        // Eliminar tokens anteriores del mismo restaurante
                        $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE restaurant_id = ?");
                        $stmt->execute([$restaurant['id']]);

                        // Guardar nuevo token
                        $stmt = $conn->prepare("INSERT INTO password_reset_tokens (restaurant_id, token, expires_at) VALUES (?, ?, ?)");
                        $stmt->execute([$restaurant['id'], $token, $expires_at]);

                        // Enviar email
                        if (sendPasswordResetEmail($restaurant['email'], $restaurant['name'], $token)) {
                            $success = 'Se ha enviado un enlace de recuperación a tu email. Por favor, revisa tu bandeja de entrada.';
                        } else {
                            $error = 'Error al enviar el email. Por favor, intenta más tarde.';
                        }
                    } else {
                        // Por seguridad, no revelar si el email existe o no
                        $success = 'Si el email existe en nuestro sistema, recibirás un enlace de recuperación.';
                    }
                } catch (PDOException $e) {
                    error_log("Error en recuperación de contraseña: " . $e->getMessage());
                    $error = 'Error al procesar la solicitud. Por favor, intenta más tarde.';
                }
            }
        } elseif ($action === 'reset_password') {
            // Cambiar contraseña
            $token = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Verificar que el token sea válido antes de procesar
            if (!$token_valid) {
                $error = 'El enlace de recuperación es inválido o ha expirado. Por favor, solicita uno nuevo.';
            } elseif (empty($token) || empty($password) || empty($confirm_password)) {
                $error = 'Todos los campos son requeridos.';
            } elseif ($password !== $confirm_password) {
                $error = 'Las contraseñas no coinciden.';
            } elseif (strlen($password) < 8) {
                $error = 'La contraseña debe tener al menos 8 caracteres.';
            } else {
                try {
                    // Verificar token válido nuevamente para seguridad adicional
                    $stmt = $conn->prepare("SELECT prt.*, r.id as restaurant_id, r.name 
                                          FROM password_reset_tokens prt 
                                          JOIN restaurants r ON prt.restaurant_id = r.id 
                                          WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()");
                    $stmt->execute([$token]);
                    $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($reset_data) {
                        // Iniciar transacción para asegurar consistencia
                        $conn->beginTransaction();
                        
                        try {
                            // Actualizar contraseña
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("UPDATE restaurants SET password = ? WHERE id = ?");
                            $stmt->execute([$hashed_password, $reset_data['restaurant_id']]);

                            // Marcar token como usado
                            $stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
                            $stmt->execute([$reset_data['id']]);

                            // Confirmar transacción
                            $conn->commit();

                            // Registrar actividad
                            logActivity($reset_data['restaurant_id'], 'password_reset', 'Contraseña restablecida exitosamente');

                            $success = 'Tu contraseña ha sido actualizada exitosamente. Ahora puedes iniciar sesión con tu nueva contraseña.';
                        } catch (Exception $e) {
                            // Revertir transacción en caso de error
                            $conn->rollBack();
                            throw $e;
                        }
                    } else {
                        $error = 'El enlace de recuperación es inválido o ha expirado. Por favor, solicita uno nuevo.';
                    }
                } catch (PDOException $e) {
                    error_log("Error al cambiar contraseña: " . $e->getMessage());
                    $error = 'Error al actualizar la contraseña. Por favor, intenta más tarde.';
                }
            }
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
    <title><?php echo $show_reset_form ? 'Cambiar Contraseña' : 'Recuperar Contraseña'; ?> - TuMenuFast</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="icon" type="image/png" href="/webmenu/uploads/img-web/img-tumenufast.png">
    <link rel="shortcut icon" type="image/png" href="/webmenu/uploads/img-web/img-tumenufast.png">
    <link rel="apple-touch-icon" href="/webmenu/uploads/img-web/img-tumenufast.png">
    
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
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: var(--dark-color);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .header p {
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
        
        .btn-primary {
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
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
            color: white;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .password-strength {
            margin-top: 10px;
            font-size: 0.8rem;
        }
        
        .strength-bar {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-key"></i>
                <?php echo $show_reset_form ? 'Cambiar Contraseña' : 'Recuperar Contraseña'; ?>
            </h1>
            <p>
                <?php if ($show_reset_form): ?>
                    Ingresa tu nueva contraseña
                <?php else: ?>
                    Ingresa tu email para recibir un enlace de recuperación
                <?php endif; ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($show_reset_form): ?>
            <!-- Formulario para cambiar contraseña -->
            <form method="POST" id="resetForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($reset_token); ?>">
                
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Nueva Contraseña
                    </label>
                    <input type="password" class="form-control" id="password" name="password" 
                           required minlength="8" placeholder="Mínimo 8 caracteres">
                    <div class="password-strength">
                        <div id="strengthText">Fortaleza de la contraseña</div>
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock"></i> Confirmar Contraseña
                    </label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           required minlength="8" placeholder="Confirma tu nueva contraseña">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Cambiar Contraseña
                </button>
            </form>
        <?php else: ?>
            <!-- Formulario para solicitar recuperación -->
            <form method="POST">
                <input type="hidden" name="action" value="request_reset">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> Email
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           required placeholder="Ingresa tu email registrado">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Enviar Enlace de Recuperación
                </button>
            </form>
        <?php endif; ?>

        <div class="footer">
            <a href="<?php echo BASE_URL; ?>/restaurante/login.php">
                <i class="fas fa-arrow-left"></i> Volver al Login
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validación de fortaleza de contraseña
        document.getElementById('password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthText = document.getElementById('strengthText');
            const strengthBar = document.getElementById('strengthBar');
            
            let strength = 0;
            let text = '';
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch (strength) {
                case 0:
                case 1:
                    text = 'Muy débil';
                    strengthBar.className = 'strength-bar strength-weak';
                    break;
                case 2:
                case 3:
                    text = 'Débil';
                    strengthBar.className = 'strength-bar strength-weak';
                    break;
                case 4:
                    text = 'Media';
                    strengthBar.className = 'strength-bar strength-medium';
                    break;
                case 5:
                    text = 'Fuerte';
                    strengthBar.className = 'strength-bar strength-strong';
                    break;
            }
            
            strengthText.textContent = text;
        });
        
        // Validación de confirmación de contraseña
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return false;
            }
        });
    </script>
</body>
</html>
