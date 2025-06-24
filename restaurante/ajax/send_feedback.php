<?php
// Configurar cabeceras para UTF-8
header('Content-Type: application/json; charset=UTF-8');
header('Content-Encoding: UTF-8');

// Configurar PHP para UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/email_config.php';

// Incluir PHPMailer
require_once __DIR__ . '/../../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/SMTP.php';
require_once __DIR__ . '/../../PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();

// Log de inicio
error_log("=== INICIO DE PETICIÓN DE FEEDBACK ===");
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("Datos POST: " . print_r($_POST, true));
error_log("Session restaurant_id: " . ($_SESSION['restaurant_id'] ?? 'NO DEFINIDO'));

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    error_log("ERROR: Usuario no autenticado");
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Método no permitido - " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Obtener datos del formulario
    $restaurant_id = $_SESSION['restaurant_id'] ?? null;
    $restaurant_name = $_POST['restaurant_name'] ?? '';
    $user_email = $_POST['email'] ?? '';
    $type = $_POST['type'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    error_log("Datos procesados:");
    error_log("- restaurant_id: $restaurant_id");
    error_log("- restaurant_name: $restaurant_name");
    error_log("- user_email: $user_email");
    error_log("- type: $type");
    error_log("- subject: $subject");
    error_log("- message: " . substr($message, 0, 100) . "...");

    // Validar datos requeridos
    if (!$restaurant_id) {
        throw new Exception('ID de restaurante no válido');
    }

    if (empty($type) || !in_array($type, ['suggestion', 'bug', 'feature', 'compliment', 'other'])) {
        throw new Exception('Tipo de feedback no válido: ' . $type);
    }

    if (empty($subject) || strlen($subject) > 200) {
        throw new Exception('El asunto es requerido y no puede exceder 200 caracteres');
    }

    if (empty($message) || strlen($message) > 5000) {
        throw new Exception('El mensaje es requerido y no puede exceder 5000 caracteres');
    }

    if (!empty($user_email) && !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email de contacto no válido');
    }

    // Obtener información del restaurante si no se proporcionó
    if (empty($restaurant_name)) {
        $stmt = $conn->prepare("SELECT name FROM restaurants WHERE id = ?");
        $stmt->execute([$restaurant_id]);
        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
        $restaurant_name = $restaurant['name'] ?? 'Restaurante';
        error_log("Nombre de restaurante obtenido de BD: $restaurant_name");
    }

    // Verificar si la tabla feedback existe
    $stmt = $conn->prepare("SHOW TABLES LIKE 'feedback'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        throw new Exception('La tabla feedback no existe en la base de datos');
    }
    error_log("Tabla feedback existe - OK");

    // Insertar feedback en la base de datos
    $stmt = $conn->prepare("
        INSERT INTO feedback (
            restaurant_id, 
            restaurant_name, 
            user_email, 
            type, 
            subject, 
            message, 
            status, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    error_log("Ejecutando inserción en BD...");
    $result = $stmt->execute([
        $restaurant_id,
        $restaurant_name,
        $user_email,
        $type,
        $subject,
        $message
    ]);

    if (!$result) {
        $error_info = $stmt->errorInfo();
        throw new Exception('Error al insertar en BD: ' . $error_info[2]);
    }

    $feedback_id = $conn->lastInsertId();
    error_log("Feedback insertado exitosamente - ID: $feedback_id");

    // Enviar email de notificación
    error_log("Intentando enviar email...");
    $email_sent = sendFeedbackEmail($feedback_id, $restaurant_name, $user_email, $type, $subject, $message);
    error_log("Resultado del envío de email: " . ($email_sent ? 'EXITOSO' : 'FALLIDO'));

    // Log de actividad
    if (function_exists('logActivity')) {
        logActivity($restaurant_id, 'feedback_submitted', "Feedback enviado: $subject (ID: $feedback_id)");
        error_log("Actividad registrada");
    } else {
        error_log("Función logActivity no disponible");
    }

    $response = [
        'success' => true, 
        'message' => '¡Gracias por tu feedback! Hemos recibido tu mensaje y nos pondremos en contacto contigo pronto.',
        'feedback_id' => $feedback_id,
        'email_sent' => $email_sent
    ];

    error_log("Respuesta exitosa: " . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    error_log("ERROR en send_feedback.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response = [
        'success' => false, 
        'message' => 'Error al enviar el feedback: ' . $e->getMessage()
    ];
    
    error_log("Respuesta de error: " . json_encode($response));
    echo json_encode($response);
}

error_log("=== FIN DE PETICIÓN DE FEEDBACK ===");

/**
 * Envía email de notificación del feedback
 */
function sendFeedbackEmail($feedback_id, $restaurant_name, $user_email, $type, $subject, $message) {
    try {
        error_log("Iniciando envío de email de feedback...");
        
        // Configurar PHPMailer
        $mail = new PHPMailer(true);
        
        // Habilitar debug para desarrollo
        $mail->SMTPDebug = EMAIL_DEBUG_MODE ? EMAIL_DEBUG_LEVEL : 0;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };
        
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = EMAIL_CHARSET;
        $mail->Encoding = EMAIL_ENCODING;
        
        // Configuraciones adicionales para mejorar la compatibilidad
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => SMTP_VERIFY_PEER_SSL,
                'verify_peer_name' => SMTP_VERIFY_PEER_NAME_SSL,
                'allow_self_signed' => SMTP_ALLOW_SELF_SIGNED_SSL
            )
        );
        
        // Configuraciones adicionales
        $mail->Timeout = SMTP_TIMEOUT;
        $mail->SMTPKeepAlive = SMTP_KEEPALIVE;
        $mail->SMTPAutoTLS = SMTP_AUTO_TLS;
        
        // Configurar autenticación
        if (defined('SMTP_AUTH_TYPE') && SMTP_AUTH_TYPE !== 'LOGIN') {
            $mail->AuthType = SMTP_AUTH_TYPE;
        }

        error_log("Configuración SMTP completada");

        // Configurar remitente y destinatario
        $mail->setFrom(SMTP_USERNAME, 'Tumenufast - Feedback Clientes');
        $mail->addAddress('tumenufast@gmail.com', 'Soporte WebMenu');
        
        // Si el usuario proporcionó email, agregarlo como CC
        if (!empty($user_email)) {
            $mail->addCC($user_email, 'Usuario');
            error_log("Email de usuario agregado como CC: $user_email");
        }

        // Configurar asunto y contenido
        $mail->isHTML(true);
        $mail->Subject = "Nuevo Feedback - $subject (ID: $feedback_id)";
        
        // Configurar encabezados adicionales
        if (EMAIL_ADD_HEADERS) {
            $mail->addCustomHeader('X-Mailer', EMAIL_X_MAILER);
            $mail->addCustomHeader('X-Priority', EMAIL_PRIORITY);
            $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
            $mail->addCustomHeader('Importance', 'Normal');
        }

        // Crear contenido del email
        $type_labels = [
            'suggestion' => 'Sugerencia',
            'bug' => 'Reporte de Error',
            'feature' => 'Solicitud de Función',
            'compliment' => 'Elogio',
            'other' => 'Otro'
        ];

        $email_content = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Nuevo Feedback - Tumenufast</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
                .info-item { background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea; }
                .info-label { font-weight: bold; color: #667eea; font-size: 0.9em; text-transform: uppercase; }
                .info-value { margin-top: 5px; }
                .priority-badge { display: inline-block; padding: 5px 10px; border-radius: 15px; color: white; font-size: 0.8em; font-weight: bold; }
                .message-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #dee2e6; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-size: 1.5em;'>📝 Nuevo Feedback Recibido</h1>
                    <p style='margin: 5px 0 0 0; opacity: 0.9;'>WebMenu - Sistema de Feedback</p>
                </div>
                
                <div class='content'>
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>Restaurante</div>
                            <div class='info-value'>$restaurant_name</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>ID de Feedback</div>
                            <div class='info-value'>#$feedback_id</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Tipo</div>
                            <div class='info-value'>" . $type_labels[$type] . "</div>
                        </div>
                    </div>

                    <div class='info-item'>
                        <div class='info-label'>Asunto</div>
                        <div class='info-value'><strong>$subject</strong></div>
                    </div>

                    <div class='message-box'>
                        <div class='info-label'>Mensaje</div>
                        <div class='info-value' style='margin-top: 10px; white-space: pre-wrap;'>" . htmlspecialchars($message) . "</div>
                    </div>

                    " . (!empty($user_email) ? "
                    <div class='info-item'>
                        <div class='info-label'>Email de Contacto</div>
                        <div class='info-value'>$user_email</div>
                    </div>
                    " : "") . "

                    <div class='footer'>
                        <p>Este feedback ha sido registrado en el sistema de WebMenu.</p>
                        <p>Fecha y hora: " . date('d/m/Y H:i:s') . "</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $email_content;
        $mail->AltBody = "
        Nuevo Feedback - WebMenu
        
        Restaurante: $restaurant_name
        ID de Feedback: #$feedback_id
        Tipo: " . $type_labels[$type] . "
        Asunto: $subject
        
        Mensaje:
        $message
        
        " . (!empty($user_email) ? "Email de contacto: $user_email" : "") . "
        
        Fecha: " . date('d/m/Y H:i:s');

        error_log("Contenido del email preparado, intentando enviar...");
        
        // Intentar envío con reintentos
        $attempts = 0;
        $max_attempts = EMAIL_MAX_RETRIES;
        $success = false;
        
        while ($attempts < $max_attempts && !$success) {
            $attempts++;
            error_log("Intento de envío #$attempts de $max_attempts");
            
            try {
                $mail->send();
                $success = true;
                error_log("Email enviado exitosamente en el intento #$attempts");
            } catch (Exception $e) {
                error_log("Error en intento #$attempts: " . $e->getMessage());
                
                if ($attempts < $max_attempts) {
                    error_log("Esperando " . EMAIL_RETRY_DELAY_SECONDS . " segundos antes del siguiente intento...");
                    sleep(EMAIL_RETRY_DELAY_SECONDS);
                }
            }
        }
        
        if ($success) {
            return true;
        } else {
            error_log("Falló el envío después de $max_attempts intentos");
            return false;
        }

    } catch (Exception $e) {
        error_log("Error enviando email de feedback: " . $e->getMessage());
        error_log("Detalles del error: " . $e->getTraceAsString());
        return false;
    }
}
?> 
