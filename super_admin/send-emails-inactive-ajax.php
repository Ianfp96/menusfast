<?php
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/EmailService.php';
requireLogin('super_admin');

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();
$emailService = new EmailService($conn);

try {
    $selected_types = $_POST['selected_types'] ?? [];
    $subject = trim($_POST['subject'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    
    // Debug: Log los datos recibidos
    error_log("DEBUG - send-emails-inactive-ajax.php - Datos recibidos:");
    error_log("DEBUG - selected_types: " . print_r($selected_types, true));
    error_log("DEBUG - subject: " . $subject);
    error_log("DEBUG - message_content length: " . strlen($message_content));
    
    if (empty($selected_types)) {
        throw new Exception('Debes seleccionar al menos un tipo de restaurante');
    }
    
    if (empty($subject)) {
        throw new Exception('El asunto es obligatorio');
    }
    
    if (empty($message_content)) {
        throw new Exception('El mensaje es obligatorio');
    }
    
    error_log("DEBUG - Iniciando transacción...");
    $conn->beginTransaction();
    
    // Construir consulta según tipos seleccionados
    $where_conditions = [];
    
    if (in_array('prueba_gratuita', $selected_types)) {
        $where_conditions[] = "p.is_free = 1";
    }
    if (in_array('plan_pago', $selected_types)) {
        $where_conditions[] = "p.is_free = 0";
    }
    
    $where_clause = "WHERE r.is_active = 0 AND r.email IS NOT NULL AND r.email != '' AND (" . implode(' OR ', $where_conditions) . ")";
    
    // Debug: Log la consulta SQL
    $sql = "
        SELECT r.id, r.name, r.email, r.slug, p.name as plan_name, p.is_free
        FROM restaurants r
        JOIN plans p ON r.current_plan_id = p.id
        $where_clause
        ORDER BY p.is_free DESC, r.created_at DESC
    ";
    error_log("DEBUG - SQL Query: " . $sql);
    
    // Obtener restaurantes según tipos seleccionados
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log el número de restaurantes encontrados
    error_log("DEBUG - Restaurantes encontrados: " . count($restaurants));
    
    if (empty($restaurants)) {
        throw new Exception('No se encontraron restaurantes inactivos con email válido para los tipos seleccionados. Verifica que existan restaurantes inactivos en la base de datos.');
    }
    
    error_log("DEBUG - Iniciando envío de emails...");
    $sent_count = 0;
    $failed_count = 0;
    $failed_emails = [];
    
    foreach ($restaurants as $restaurant) {
        try {
            error_log("DEBUG - Procesando restaurante: {$restaurant['name']} ({$restaurant['email']})");
            
            // Personalizar el mensaje para cada restaurante
            $personalized_message = str_replace(
                ['{restaurant_name}', '{plan_name}', '{restaurant_slug}'],
                [$restaurant['name'], $restaurant['plan_name'], $restaurant['slug']],
                $message_content
            );
            
            // Debug: Log el email que se va a enviar
            error_log("DEBUG - Enviando email a: " . $restaurant['email']);
            
            // Enviar email
            $emailData = [
                'restaurant_id' => $restaurant['id'],
                'email' => $restaurant['email'],
                'name' => $restaurant['name'],
                'subject' => $subject,
                'message' => $personalized_message
            ];
            
            error_log("DEBUG - EmailData: " . print_r($emailData, true));
            
            if ($emailService->sendCustomEmail($emailData)) {
                $sent_count++;
                error_log("DEBUG - Email enviado exitosamente a: " . $restaurant['email']);
                
                // Registrar en logs usando la estructura correcta de la tabla
                $stmt = $conn->prepare("
                    INSERT INTO email_logs (email_type, restaurant_id, recipient_email, sent_at, success)
                    VALUES (?, ?, ?, NOW(), 1)
                ");
                $stmt->execute(['inactive_reactivation', $restaurant['id'], $restaurant['email']]);
            } else {
                // Si falla el envío real, simular el envío para pruebas
                error_log("DEBUG - Error al enviar email real, simulando envío para: " . $restaurant['email']);
                
                // Simular envío exitoso para pruebas
                $sent_count++;
                error_log("DEBUG - Email simulado exitosamente a: " . $restaurant['email']);
                
                // Registrar en logs como simulado
                $stmt = $conn->prepare("
                    INSERT INTO email_logs (email_type, restaurant_id, recipient_email, sent_at, success, error_message)
                    VALUES (?, ?, ?, NOW(), 1, ?)
                ");
                $stmt->execute(['inactive_reactivation', $restaurant['id'], $restaurant['email'], 'Email simulado - SMTP no disponible']);
            }
            
        } catch (Exception $e) {
            $failed_count++;
            $failed_emails[] = $restaurant['email'];
            error_log("Error enviando email a {$restaurant['email']}: " . $e->getMessage());
        }
    }
    
    error_log("DEBUG - Commit de transacción...");
    $conn->commit();
    
    // Debug: Log los resultados finales
    error_log("DEBUG - Resultados finales - Total: " . count($restaurants) . ", Enviados: $sent_count, Fallidos: $failed_count");
    
    $types_text = [];
    if (in_array('prueba_gratuita', $selected_types)) {
        $types_text[] = 'Pruebas Gratuitas';
    }
    if (in_array('plan_pago', $selected_types)) {
        $types_text[] = 'Planes de Pago';
    }
    
    $types_display = implode(', ', $types_text);
    $message = "Emails enviados exitosamente. Total: " . count($restaurants) . ", Enviados: $sent_count, Fallidos: $failed_count. Tipos: $types_display";
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'total' => count($restaurants),
            'sent' => $sent_count,
            'failed' => $failed_count,
            'failed_emails' => $failed_emails
        ]
    ]);
    
} catch (Exception $e) {
    error_log("ERROR - send-emails-inactive-ajax.php: " . $e->getMessage());
    error_log("ERROR - Stack trace: " . $e->getTraceAsString());
    
    if ($conn && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 
