<?php
// Incluir PHPMailer manualmente (sin Composer)
require_once dirname(__DIR__) . '/PHPMailer/PHPMailer.php';
require_once dirname(__DIR__) . '/PHPMailer/SMTP.php';
require_once dirname(__DIR__) . '/PHPMailer/Exception.php';

// Incluir configuración de email
require_once dirname(__DIR__) . '/config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $conn;
    private $logEnabled;
    
    public function __construct($conn = null) {
        $this->conn = $conn;
        $this->logEnabled = EMAIL_LOG_ENABLED;
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Configuración del servidor
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = SMTP_SECURE;
            $this->mailer->Port = SMTP_PORT;
            $this->mailer->CharSet = 'UTF-8';
            
            // Configuración para solucionar problemas de SSL (solo para desarrollo)
            $this->mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Configuración del remitente
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            // Configuración de debug (solo en desarrollo)
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            } else {
                $this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
            }
            
        } catch (Exception $e) {
            $this->logError("Error al inicializar PHPMailer: " . $e->getMessage());
            throw new Exception("Error de configuración de email");
        }
    }
    
    /**
     * Envía email de bienvenida al restaurante
     */
    public function sendWelcomeEmail($restaurantData) {
        try {
            $subject = "¡Bienvenido a Tumenufast! Tu cuenta ha sido creada exitosamente";
            
            $htmlContent = $this->getWelcomeEmailTemplate($restaurantData);
            $textContent = $this->getWelcomeEmailTextTemplate($restaurantData);
            
            $this->mailer->addAddress($restaurantData['email'], $restaurantData['name']);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $htmlContent;
            $this->mailer->AltBody = $textContent;
            
            $result = $this->mailer->send();
            
            if ($result) {
                $this->logEmail('welcome', $restaurantData['email'], $restaurantData['restaurant_id'], true);
                return true;
            }
            
        } catch (Exception $e) {
            $this->logError("Error enviando email de bienvenida: " . $e->getMessage());
            $this->logEmail('welcome', $restaurantData['email'], $restaurantData['restaurant_id'], false, $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Envía email de confirmación de registro
     */
    public function sendRegistrationConfirmation($restaurantData) {
        try {
            $subject = "Confirmación de registro - Tumenufast";
            
            $htmlContent = $this->getRegistrationConfirmationTemplate($restaurantData);
            $textContent = $this->getRegistrationConfirmationTextTemplate($restaurantData);
            
            $this->mailer->addAddress($restaurantData['email'], $restaurantData['name']);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $htmlContent;
            $this->mailer->AltBody = $textContent;
            
            $result = $this->mailer->send();
            
            if ($result) {
                $this->logEmail('registration_confirmation', $restaurantData['email'], $restaurantData['restaurant_id'], true);
                return true;
            }
            
        } catch (Exception $e) {
            $this->logError("Error enviando confirmación de registro: " . $e->getMessage());
            $this->logEmail('registration_confirmation', $restaurantData['email'], $restaurantData['restaurant_id'], false, $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Template HTML para email de bienvenida
     */
    private function getWelcomeEmailTemplate($data) {
        $dashboardUrl = EMAIL_BASE_URL . '/restaurante/login.php';
        $loginUrl = EMAIL_BASE_URL . '/restaurante/login.php';
        $supportEmail = APP_EMAIL_SUPPORT;
        
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Bienvenido a Tumenufast</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .btn { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .highlight { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
                .feature-list { margin: 20px 0; }
                .feature-item { margin: 10px 0; padding-left: 20px; }
                .feature-item:before { content: '✓'; color: #28a745; font-weight: bold; margin-right: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>¡Bienvenido a Tumenufast!</h1>
                    <p>Nos complace darte la bienvenida a <strong>Tumenufast</strong>. Tu cuenta ha sido creada exitosamente y ya puedes comenzar a digitalizar tu restaurante.</p>
                </div>
                
                <div class='content'>
                    <h2>¡Hola {$data['name']}!</h2>
                    
                    <p>Nos complace darte la bienvenida a <strong>Tumenufast</strong>. Tu cuenta ha sido creada exitosamente y ya puedes comenzar a digitalizar tu restaurante.</p>
                    
                    <div class='highlight'>
                        <strong>Información de tu cuenta:</strong><br>
                        • <strong>Restaurante:</strong> {$data['name']}<br>
                        • <strong>Email:</strong> {$data['email']}<br>
                        • <strong>Plan actual:</strong> Prueba gratuita (7 días)<br>
                        • <strong>Estado:</strong> Activo
                    </div>
                    
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$dashboardUrl}' class='btn'>Acceder al Dashboard</a>
                    </div>
                    
                    <h3>Próximos pasos recomendados:</h3>
                    <ol>
                        <li><strong>Completa tu perfil:</strong> Añade información adicional sobre tu restaurante</li>
                        <li><strong>Crea categorías:</strong> Organiza tu menú en secciones</li>
                        <li><strong>Añade productos:</strong> Sube fotos y descripciones de tus platos</li>
                        <li><strong>Personaliza tu menu:</strong>logo a tu marca, banners, redes sociales, etc.</li>
                        <li><strong>Genera códigos QR:</strong> Para que tus clientes puedan ver el menú y más.</li>
                    </ol>
                    
                    <div class='highlight'>
                        <strong>💡 Consejo:</strong> Durante tu período de prueba gratuita, tendrás acceso completo a todas las funciones premium. ¡Aprovecha estos 7 días para explorar todas las posibilidades!
                    </div>
                    
                    <p>Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos:</p>
                    <ul>
                        <li><strong>Email de soporte:</strong> <a href='mailto:{$supportEmail}'>{$supportEmail}</a></li>
                        <li><strong>Horario de atención:</strong> Lunes a Viernes, 9:00 AM - 6:00 PM</li>
                    </ul>
                    
                    <p>¡Gracias por elegir Tumenufast para digitalizar tu restaurante!</p>
                    
                    <p>Saludos cordiales,<br>
                    <strong>El equipo de Tumenufast</strong></p>
                </div>
                
                <div class='footer'>
                    <p>Este email fue enviado a {$data['email']} como confirmación de tu registro en Tumenufast.</p>
                    <p>Si no solicitaste esta cuenta, puedes ignorar este mensaje.</p>
                    <p>&copy; " . date('Y') . " Tumenufast. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template de texto plano para email de bienvenida
     */
    private function getWelcomeEmailTextTemplate($data) {
        $dashboardUrl = EMAIL_BASE_URL . '/restaurante/dashboard.php';
        $supportEmail = APP_EMAIL_SUPPORT;
        
        return "
¡Bienvenido a Tumenufast!

¡Hola {$data['name']}!

Nos complace darte la bienvenida a Tumenufast. Tu cuenta ha sido creada exitosamente y ya puedes comenzar a digitalizar tu restaurante.

INFORMACIÓN DE TU CUENTA:
- Restaurante: {$data['name']}
- Email: {$data['email']}
- Plan actual: Prueba gratuita (7 días)
- Estado: Activo


PRÓXIMOS PASOS RECOMENDADOS:
1. Completa tu perfil: Añade información adicional sobre tu restaurante
2. Crea categorías: Organiza tu menú en secciones
3. Añade productos: Sube fotos y descripciones de tus platos
4. Personaliza tu menu: agrega logo a tu marca y banners.
5. Genera códigos QR: Para que tus clientes puedan ver el menú.

CONSEJO: Aprovecha tu período de prueba gratuita por 7 días para explorar todas las posibilidades!

Si tienes alguna pregunta o necesitas ayuda:
- Email de soporte: {$supportEmail}
- Horario de atención: Lunes a Viernes, 9:00 AM - 6:00 PM

¡Gracias por elegir Tumenufast para digitalizar tu restaurante!

Saludos cordiales,
El equipo de Tumenufast

---
Este email fue enviado a {$data['email']} como confirmación de tu registro en Tumenufast.
Si no solicitaste esta cuenta, puedes ignorar este mensaje.
© " . date('Y') . " Tumenufast. Todos los derechos reservados.";
    }
    
    /**
     * Template HTML para confirmación de registro
     */
    private function getRegistrationConfirmationTemplate($data) {
        $dashboardUrl = EMAIL_BASE_URL . '/restaurante/dashboard.php';
        
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Confirmación de Registro</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .btn { display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✓ Registro Confirmado</h1>
                    <p>Tu cuenta ha sido creada exitosamente</p>
                </div>
                
                <div class='content'>
                    <h2>¡Hola {$data['name']}!</h2>
                    
                    <p>Tu registro en <strong>Tumenufast</strong> ha sido confirmado exitosamente.</p>
                    
                    <p><strong>Detalles de tu cuenta:</strong></p>
                    <ul>
                        <li><strong>Restaurante:</strong> {$data['name']}</li>
                        <li><strong>Email:</strong> {$data['email']}</li>
                        <li><strong>Fecha de registro:</strong> " . date('d/m/Y H:i') . "</li>
                    </ul>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$dashboardUrl}' class='btn'>Acceder a mi cuenta</a>
                    </div>
                    
                    <p>Ya puedes comenzar a usar todas las funciones de Tumenufast para digitalizar tu restaurante.</p>
                    
                    <p>Saludos cordiales,<br>
                    <strong>El equipo de Tumenufast</strong></p>
                </div>
                
                <div class='footer'>
                    <p>Este email confirma tu registro en Tumenufast.</p>
                    <p>&copy; " . date('Y') . " Tumenufast. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template de texto plano para confirmación de registro
     */
    private function getRegistrationConfirmationTextTemplate($data) {
        $dashboardUrl = EMAIL_BASE_URL . '/restaurante/dashboard.php';
        
        return "
Confirmación de Registro - Tumenufast

¡Hola {$data['name']}!

Tu registro en Tumenufast ha sido confirmado exitosamente.

DETALLES DE TU CUENTA:
- Restaurante: {$data['name']}
- Email: {$data['email']}
- Fecha de registro: " . date('d/m/Y H:i') . "

Accede a tu cuenta: {$dashboardUrl}

Ya puedes comenzar a usar todas las funciones de Tumenufast para digitalizar tu restaurante.

Saludos cordiales,
El equipo de Tumenufast

---
Este email confirma tu registro en Tumenufast.
© " . date('Y') . " Tumenufast. Todos los derechos reservados.";
    }
    
    /**
     * Registra el envío de emails en la base de datos
     */
    private function logEmail($type, $email, $restaurantId, $success, $error = null) {
        if (!$this->logEnabled || !$this->conn) {
            return;
        }
        
        try {
            // Verificar si el restaurant_id existe antes de insertar
            if ($restaurantId > 0) {
                $checkStmt = $this->conn->prepare("SELECT id FROM restaurants WHERE id = ?");
                $checkStmt->execute([$restaurantId]);
                if (!$checkStmt->fetch()) {
                    // Si el restaurant_id no existe, usar NULL
                    $restaurantId = null;
                }
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO email_logs (
                    restaurant_id, email_type, recipient_email, 
                    sent_at, success, error_message, created_at
                ) VALUES (?, ?, ?, NOW(), ?, ?, NOW())
            ");
            
            $stmt->execute([
                $restaurantId,
                $type,
                $email,
                $success ? 1 : 0,
                $error
            ]);
            
        } catch (Exception $e) {
            error_log("Error al registrar email en la base de datos: " . $e->getMessage());
            // No fallar el envío de email por problemas de logging
        }
    }
    
    /**
     * Registra errores en el log
     */
    private function logError($message) {
        if ($this->logEnabled) {
            $logMessage = date('Y-m-d H:i:s') . " - " . $message . PHP_EOL;
            file_put_contents(EMAIL_LOG_PATH, $logMessage, FILE_APPEND | LOCK_EX);
        }
        error_log($message);
    }
    
    /**
     * Verifica si se puede enviar email (rate limiting)
     */
    public function canSendEmail($email) {
        if (!$this->conn) {
            return true;
        }
        
        try {
            // Verificar límite por hora
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM email_logs 
                WHERE recipient_email = ? 
                AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND success = 1
            ");
            $stmt->execute([$email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] < MAX_EMAILS_PER_HOUR;
            
        } catch (Exception $e) {
            $this->logError("Error verificando límite de emails: " . $e->getMessage());
            return true; // En caso de error, permitir el envío
        }
    }
    
    /**
     * Envía email de advertencia de expiración de suscripción
     */
    public function sendExpirationWarningEmail($data, $daysRemaining) {
        try {
            $subject = $daysRemaining == 1 
                ? "⚠️ Tu suscripción expira mañana - Tumenufast"
                : "⚠️ Tu suscripción expira en {$daysRemaining} días - Tumenufast";
            
            $htmlContent = $this->getExpirationWarningTemplate($data, $daysRemaining);
            $textContent = $this->getExpirationWarningTextTemplate($data, $daysRemaining);
            
            $this->mailer->addAddress($data['email'], $data['name']);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $htmlContent;
            $this->mailer->AltBody = $textContent;
            
            $result = $this->mailer->send();
            
            if ($result) {
                $emailType = $daysRemaining == 1 ? 'expiration_1_day' : 'expiration_7_days';
                $this->logEmail($emailType, $data['email'], $data['restaurant_id'], true);
                return true;
            }
            
        } catch (Exception $e) {
            $this->logError("Error enviando email de expiración: " . $e->getMessage());
            $emailType = $daysRemaining == 1 ? 'expiration_1_day' : 'expiration_7_days';
            $this->logEmail($emailType, $data['email'], $data['restaurant_id'], false, $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Envía email post-expiración (días 1, 2 y 3 después de expirar)
     */
    public function sendPostExpirationEmail($data, $daysExpired) {
        try {
            $subject = $this->getPostExpirationSubject($daysExpired);
            
            $htmlContent = $this->getPostExpirationTemplate($data, $daysExpired);
            $textContent = $this->getPostExpirationTextTemplate($data, $daysExpired);
            
            $this->mailer->addAddress($data['email'], $data['name']);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $htmlContent;
            $this->mailer->AltBody = $textContent;
            
            $result = $this->mailer->send();
            
            if ($result) {
                $emailType = 'post_expiration_' . $daysExpired . '_days';
                $this->logEmail($emailType, $data['email'], $data['restaurant_id'], true);
                return true;
            }
            
        } catch (Exception $e) {
            $this->logError("Error enviando email post-expiración: " . $e->getMessage());
            $emailType = 'post_expiration_' . $daysExpired . '_days';
            $this->logEmail($emailType, $data['email'], $data['restaurant_id'], false, $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Obtiene el asunto del email según los días transcurridos
     */
    private function getPostExpirationSubject($daysExpired) {
        switch ($daysExpired) {
            case 1:
                return "🔴 Tu suscripción ha expirado - Tumenufast";
            case 2:
                return "⚠️ Tu menú digital está inactivo - Tumenufast";
            case 3:
                return "🚨 Última oportunidad: Reactiva tu cuenta - Tumenufast";
            default:
                return "Tu suscripción ha expirado - Tumenufast";
        }
    }
    
    /**
     * Template HTML para email post-expiración
     */
    private function getPostExpirationTemplate($data, $daysExpired) {
        $dashboardUrl = EMAIL_BASE_URL . '/restaurante/dashboard.php';
        $plansUrl = EMAIL_BASE_URL . '/restaurante/planes.php';
        $supportEmail = APP_EMAIL_SUPPORT;
        $expirationDate = date('d/m/Y', strtotime($data['expiration_date']));
        
        // Mensajes según los días transcurridos
        $messages = $this->getPostExpirationMessages($daysExpired);
        
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Tu suscripción ha expirado - Tumenufast</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .btn { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                .btn-danger { background: #dc3545; }
                .btn-success { background: #28a745; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .highlight { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
                .plan-info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .urgent { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$messages['title']}</h1>
                    <p>{$messages['subtitle']}</p>
                </div>
                
                <div class='content'>
                    <h2>¡Hola {$data['name']}!</h2>
                    
                    <div class='highlight'>
                        <h3>{$messages['main_message']}</h3>
                        <p><strong>Fecha de expiración:</strong> {$expirationDate}</p>
                        <p><strong>Plan anterior:</strong> {$data['plan_name']}</p>
                        <p><strong>Días transcurridos:</strong> {$daysExpired} día(s)</p>
                    </div>
                    
                    <h3>¿Qué está pasando con tu cuenta?</h3>
                    <ul>
                        <li>Tu menú digital ya no está disponible para tus clientes</li>
                        <li>Los códigos QR han dejado de funcionar</li>
                        <li>No puedes agregar nuevos productos o categorías</li>
                        <li>Has perdido acceso a las estadísticas y reportes</li>
                    </ul>
                    
                    <div class='plan-info'>
                        <h4>💡 {$messages['recommendation_title']}</h4>
                        <p>{$messages['recommendation_text']}</p>
                    </div>
                    
                    <!-- Sección de WhatsApp -->
                    <div style='background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 10px; margin: 25px 0; border: 2px solid #25D366;'>
                        <div style='text-align: center; margin-bottom: 15px;'>
                            <div style='display: inline-block; background: #25D366; color: white; border-radius: 50%; width: 50px; height: 50px; line-height: 50px; font-size: 24px; margin-bottom: 10px;'>
                                💬
                            </div>
                            <h3 style='color: #25D366; margin: 0; font-size: 18px;'>¿Necesitas ayuda para reactivar?</h3>
                            <p style='color: #666; margin: 10px 0; font-size: 14px;'>Nuestro equipo está aquí para ayudarte a elegir el plan perfecto</p>
                        </div>
                        
                        <div style='text-align: center;'>
                            <a href='https://wa.me/56912345678?text=Hola,%20necesito%20ayuda%20para%20reactivar%20mi%20cuenta%20de%20Tumenufast' 
                               style='display: inline-block; padding: 15px 35px; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; text-decoration: none; border-radius: 25px; margin: 10px 5px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3); transition: all 0.3s ease;'>
                                📱 Chatear por WhatsApp
                            </a>
                        </div>
                        
                        <div style='text-align: center; margin-top: 15px;'>
                            <p style='color: #666; font-size: 12px; margin: 0;'>
                                <strong>Respuesta en menos de 5 minutos</strong> • Horario: Lunes a Domingo, 9:00 AM - 8:00 PM
                            </p>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$plansUrl}' class='btn btn-success'>Ver Planes y Reactivar</a>
                        <a href='{$dashboardUrl}' class='btn'>Ir al Dashboard</a>
                    </div>
                    
                    <div class='urgent'>
                        <h4>🚨 {$messages['urgency_title']}</h4>
                        <p>{$messages['urgency_text']}</p>
                    </div>
                    
                    <h3>¿Necesitas ayuda?</h3>
                    <p>Nuestro equipo de soporte está aquí para ayudarte:</p>
                    <ul>
                        <li><strong>Email:</strong> {$supportEmail}</li>
                        <li><strong>Horario:</strong> Lunes a Viernes, 9:00 AM - 6:00 PM</li>
                    </ul>
                    
                    <p>Saludos cordiales,<br>
                    <strong>El equipo de Tumenufast</strong></p>
                </div>
                
                <div class='footer'>
                    <p>Este email fue enviado a {$data['email']} como notificación post-expiración.</p>
                    <p>&copy; " . date('Y') . " Tumenufast. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Obtiene los mensajes según los días transcurridos
     */
    private function getPostExpirationMessages($daysExpired) {
        switch ($daysExpired) {
            case 1:
                return [
                    'title' => '🔴 Tu suscripción ha expirado',
                    'subtitle' => 'Tu período de prueba gratuita ha terminado',
                    'main_message' => 'Tu suscripción expiró ayer',
                    'recommendation_title' => 'Recomendación',
                    'recommendation_text' => 'Reactiva tu cuenta ahora para continuar disfrutando de todas las funcionalidades de Tumenufast.',
                    'urgency_title' => 'Importante',
                    'urgency_text' => 'Reactivar ahora te permitirá mantener todos tus datos y configuraciones intactos.'
                ];
            case 2:
                return [
                    'title' => '⚠️ Tu menú digital está inactivo',
                    'subtitle' => 'Han pasado 2 días desde que expiró tu suscripción',
                    'main_message' => 'Tu cuenta lleva 2 días inactiva',
                    'recommendation_title' => 'No pierdas más tiempo',
                    'recommendation_text' => 'Cada día que pasa, más clientes no pueden acceder a tu menú. Reactiva ahora para recuperar tu presencia digital.',
                    'urgency_title' => 'Oportunidad limitada',
                    'urgency_text' => 'Reactivar en los próximos días te dará acceso a ofertas especiales de reactivación.'
                ];
            case 3:
                return [
                    'title' => '🚨 Última oportunidad',
                    'subtitle' => 'Han pasado 3 días desde que expiró tu suscripción',
                    'main_message' => 'Esta es tu última oportunidad',
                    'recommendation_title' => 'Oferta especial de reactivación',
                    'recommendation_text' => 'Por ser un cliente que regresa, tenemos una oferta especial para ti. Reactiva ahora y obtén un descuento exclusivo.',
                    'urgency_title' => 'Oferta por tiempo limitado',
                    'urgency_text' => 'Esta oferta especial solo está disponible por 24 horas más. No dejes pasar esta oportunidad.'
                ];
            default:
                return [
                    'title' => 'Tu suscripción ha expirado',
                    'subtitle' => 'Han pasado varios días desde que expiró tu suscripción',
                    'main_message' => 'Tu cuenta está inactiva',
                    'recommendation_title' => 'Recomendación',
                    'recommendation_text' => 'Reactiva tu cuenta para continuar usando Tumenufast.',
                    'urgency_title' => 'Importante',
                    'urgency_text' => 'Reactivar ahora te permitirá mantener todos tus datos.'
                ];
        }
    }
    
    /**
     * Template de texto plano para email post-expiración
     */
    private function getPostExpirationTextTemplate($data, $daysExpired) {
        $dashboardUrl = EMAIL_BASE_URL . '/restaurante/dashboard.php';
        $plansUrl = EMAIL_BASE_URL . '/restaurante/planes.php';
        $supportEmail = APP_EMAIL_SUPPORT;
        $expirationDate = date('d/m/Y', strtotime($data['expiration_date']));
        
        $messages = $this->getPostExpirationMessages($daysExpired);
        
        return "
{$messages['title']} - Tumenufast

¡Hola {$data['name']}!

{$messages['main_message']}

DETALLES DE TU CUENTA:
- Fecha de expiración: {$expirationDate}
- Plan anterior: {$data['plan_name']}
- Días transcurridos: {$daysExpired} día(s)

¿QUÉ ESTÁ PASANDO CON TU CUENTA?
- Tu menú digital ya no está disponible para tus clientes
- Los códigos QR han dejado de funcionar
- No puedes agregar nuevos productos o categorías
- Has perdido acceso a las estadísticas y reportes

{$messages['recommendation_title']}:
{$messages['recommendation_text']}

💬 ¿Necesitas ayuda para reactivar? Escríbenos por WhatsApp:
https://wa.me/56912345678?text=Hola,%20necesito%20ayuda%20para%20reactivar%20mi%20cuenta%20de%20Tumenufast

Enlaces importantes:
- Ver planes y reactivar: {$plansUrl}
- Ir al dashboard: {$dashboardUrl}

{$messages['urgency_title']}:
{$messages['urgency_text']}

¿Necesitas ayuda?
- Email de soporte: {$supportEmail}
- Horario: Lunes a Viernes, 9:00 AM - 6:00 PM

Saludos cordiales,
El equipo de Tumenufast";
    }
    
    /**
     * Template HTML para email de advertencia de expiración
     */
    private function getExpirationWarningTemplate($data, $daysRemaining) {
        $dashboardUrl = EMAIL_BASE_URL . '/restaurante/dashboard.php';
        $plansUrl = EMAIL_BASE_URL . '/restaurante/planes.php';
        $supportEmail = APP_EMAIL_SUPPORT;
        $expirationDate = date('d/m/Y', strtotime($data['expiration_date']));
        
        $urgencyMessage = $daysRemaining == 1 
            ? "Tu suscripción expira <strong>mañana</strong>"
            : "Tu suscripción expira en <strong>{$daysRemaining} días</strong>";
        
        $urgencyColor = $daysRemaining == 1 ? '#dc3545' : '#ffc107';
        
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Advertencia de Expiración - Tumenufast</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: {$urgencyColor}; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .btn { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                .btn-warning { background: #ffc107; color: #212529; }
                .btn-danger { background: #dc3545; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .highlight { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
                .urgent { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
                .plan-info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ Advertencia de Expiración</h1>
                    <p>{$urgencyMessage}</p>
                </div>
                
                <div class='content'>
                    <h2>¡Hola {$data['name']}!</h2>
                    
                    <div class='urgent'>
                        <h3>⚠️ {$urgencyMessage}</h3>
                        <p><strong>Fecha de expiración:</strong> {$expirationDate}</p>
                        <p><strong>Plan actual:</strong> {$data['plan_name']}</p>
                    </div>
                    
                    <h3>¿Qué pasa cuando expire tu suscripción?</h3>
                    <ul>
                        <li>Tu menú digital dejará de estar disponible para tus clientes</li>
                        <li>Perderás acceso a las estadísticas y reportes</li>
                        <li>No podrás agregar nuevos productos o categorías</li>
                        <li>Los códigos QR dejarán de funcionar</li>
                    </ul>
                    
                    <div class='plan-info'>
                        <h4>💡 Recomendación</h4>
                        <p>Renueva tu suscripción ahora para mantener tu restaurante digital funcionando sin interrupciones.</p>
                    </div>
                    
                    <!-- Sección de WhatsApp con mejor diseño -->
                    <div style='background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 10px; margin: 25px 0; border: 2px solid #25D366;'>
                        <div style='text-align: center; margin-bottom: 15px;'>
                            <div style='display: inline-block; background: #25D366; color: white; border-radius: 50%; width: 50px; height: 50px; line-height: 50px; font-size: 24px; margin-bottom: 10px;'>
                                💬
                            </div>
                            <h3 style='color: #25D366; margin: 0; font-size: 18px;'>¿Necesitas ayuda inmediata?</h3>
                            <p style='color: #666; margin: 10px 0; font-size: 14px;'>Nuestro equipo está disponible para ayudarte con cualquier duda sobre tu suscripción</p>
                        </div>
                        
                        <div style='text-align: center;'>
                            <a href='https://wa.me/56912345678?text=Hola,%20necesito%20ayuda%20con%20mi%20suscripción%20de%20Tumenufast' 
                               style='display: inline-block; padding: 15px 35px; background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; text-decoration: none; border-radius: 25px; margin: 10px 5px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3); transition: all 0.3s ease;'>
                                📱 Chatear por WhatsApp
                            </a>
                        </div>
                        
                        <div style='text-align: center; margin-top: 15px;'>
                            <p style='color: #666; font-size: 12px; margin: 0;'>
                                <strong>Respuesta en menos de 5 minutos</strong> • Horario: Lunes a Domingo, 9:00 AM - 8:00 PM
                            </p>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$plansUrl}' class='btn btn-warning'>Ver Planes y Renovar</a>
                        <a href='{$dashboardUrl}' class='btn'>Ir al Dashboard</a>
                    </div>
                    
                    <h3>¿Necesitas ayuda?</h3>
                    <p>Nuestro equipo de soporte está aquí para ayudarte:</p>
                    <ul>
                        <li><strong>Email:</strong> {$supportEmail}</li>
                        <li><strong>Horario:</strong> Lunes a Viernes, 9:00 AM - 6:00 PM</li>
                    </ul>
                    
                    <div class='highlight'>
                        <p><strong>¿Tienes preguntas sobre tu plan?</strong><br>
                        No dudes en contactarnos. Estamos aquí para ayudarte a elegir el plan perfecto para tu restaurante.</p>
                    </div>
                    
                    <p>Saludos cordiales,<br>
                    <strong>El equipo de Tumenufast</strong></p>
                </div>
                
                <div class='footer'>
                    <p>Este email fue enviado a {$data['email']} como notificación de expiración de suscripción.</p>
                    <p>&copy; " . date('Y') . " Tumenufast. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template de texto plano para email de advertencia de expiración
     */
    private function getExpirationWarningTextTemplate($data, $daysRemaining) {
        $dashboardUrl = EMAIL_BASE_URL . '/restaurante/dashboard.php';
        $plansUrl = EMAIL_BASE_URL . '/restaurante/planes.php';
        $supportEmail = APP_EMAIL_SUPPORT;
        $expirationDate = date('d/m/Y', strtotime($data['expiration_date']));
        
        $urgencyMessage = $daysRemaining == 1 
            ? "Tu suscripción expira MAÑANA"
            : "Tu suscripción expira en {$daysRemaining} días";
        
        return "
Advertencia de Expiración - Tumenufast

¡Hola {$data['name']}!

⚠️ {$urgencyMessage}

DETALLES DE TU SUSCRIPCIÓN:
- Fecha de expiración: {$expirationDate}
- Plan actual: {$data['plan_name']}

¿QUÉ PASA CUANDO EXPIRE TU SUSCRIPCIÓN?
- Tu menú digital dejará de estar disponible para tus clientes
- Dpeendiendo de tu plan puede perder acceso a otras caracteristicas

RECOMENDACIÓN:
Renueva tu suscripción ahora para mantener tu restaurante digital funcionando sin interrupciones.

💬 ¿Necesitas ayuda inmediata? Escríbenos por WhatsApp:
https://wa.me/56912345678?text=Hola,%20necesito%20ayuda%20con%20mi%20suscripción%20de%20Tumenufast

Enlaces importantes:
- Ver planes y renovar: {$plansUrl}
- Ir al dashboard: {$dashboardUrl}

¿Necesitas ayuda?
- Email de soporte: {$supportEmail}
- Horario: Lunes a Viernes, 9:00 AM - 6:00 PM

Saludos cordiales,
El equipo de Tumenufast

---
Este email fue enviado a {$data['email']} como notificación de expiración de suscripción.
© " . date('Y') . " Tumenufast. Todos los derechos reservados.";
    }
    
    /**
     * Limpia la configuración del mailer
     */
    public function clearRecipients() {
        $this->mailer->clearAddresses();
        $this->mailer->clearAttachments();
    }
    
    /**
     * Envía un email personalizado
     */
    public function sendCustomEmail($emailData) {
        try {
            $this->clearRecipients();
            
            $subject = $emailData['subject'];
            $message = $emailData['message'];
            $recipientEmail = $emailData['email'];
            $recipientName = $emailData['name'] ?? '';
            
            // Crear contenido HTML
            $htmlContent = $this->getCustomEmailTemplate($emailData);
            
            // Crear contenido de texto plano
            $textContent = $this->getCustomEmailTextTemplate($emailData);
            
            $this->mailer->addAddress($recipientEmail, $recipientName);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $htmlContent;
            $this->mailer->AltBody = $textContent;
            
            $result = $this->mailer->send();
            
            if ($result) {
                $this->logEmail('custom', $recipientEmail, $emailData['restaurant_id'] ?? null, true);
                return true;
            }
            
        } catch (Exception $e) {
            $this->logError("Error enviando email personalizado: " . $e->getMessage());
            $this->logEmail('custom', $recipientEmail ?? '', $emailData['restaurant_id'] ?? null, false, $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Template HTML para email personalizado
     */
    private function getCustomEmailTemplate($data) {
        $dashboardUrl = EMAIL_BASE_URL . '/restaurante/dashboard.php';
        $supportEmail = APP_EMAIL_SUPPORT;
        
        // Convertir saltos de línea a HTML
        $messageHtml = nl2br(htmlspecialchars($data['message']));
        
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Tumenufast - {$data['subject']}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .message { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #007bff; }
                .btn { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Tumenufast</h1>
                    <p>Comunicación oficial</p>
                </div>
                
                <div class='content'>
                    <h2>¡Hola {$data['name']}!</h2>
                    
                    <div class='message'>
                        {$messageHtml}
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$dashboardUrl}' class='btn'>Ir al Dashboard</a>
                    </div>
                    
                    <h3>¿Necesitas ayuda?</h3>
                    <p>Nuestro equipo de soporte está aquí para ayudarte:</p>
                    <ul>
                        <li><strong>Email:</strong> <a href='mailto:{$supportEmail}'>{$supportEmail}</a></li>
                        <li><strong>Horario:</strong> Lunes a Viernes, 9:00 AM - 6:00 PM</li>
                    </ul>
                    
                    <p>Saludos cordiales,<br>
                    <strong>El equipo de Tumenufast</strong></p>
                </div>
                
                <div class='footer'>
                    <p>Este email fue enviado a {$data['email']} desde Tumenufast.</p>
                    <p>&copy; " . date('Y') . " Tumenufast. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Template de texto plano para email personalizado
     */
    private function getCustomEmailTextTemplate($data) {
        $dashboardUrl = EMAIL_BASE_URL . '/restaurante/dashboard.php';
        $supportEmail = APP_EMAIL_SUPPORT;
        
        return "
Tumenufast - {$data['subject']}

¡Hola {$data['name']}!

{$data['message']}

Ir al Dashboard: {$dashboardUrl}

¿Necesitas ayuda?
- Email de soporte: {$supportEmail}
- Horario: Lunes a Viernes, 9:00 AM - 6:00 PM

Saludos cordiales,
El equipo de Tumenufast

---
Este email fue enviado a {$data['email']} desde Tumenufast.
© " . date('Y') . " Tumenufast. Todos los derechos reservados.";
    }
} 
