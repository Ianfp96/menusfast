<?php
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/EmailService.php';
requireLogin('super_admin');

$db = new Database();
$conn = $db->getConnection();
$emailService = new EmailService($conn);

$message = '';
$error = '';
$email_stats = [];

// Función para obtener restaurantes inactivos separados por tipo
function getInactiveRestaurants($conn) {
    try {
        // Primero, verificar cuántos restaurantes hay en total
        $total_restaurants = $conn->query("SELECT COUNT(*) FROM restaurants")->fetchColumn();
        $active_restaurants = $conn->query("SELECT COUNT(*) FROM restaurants WHERE is_active = 1")->fetchColumn();
        $inactive_restaurants = $conn->query("SELECT COUNT(*) FROM restaurants WHERE is_active = 0")->fetchColumn();
        $restaurants_with_email = $conn->query("SELECT COUNT(*) FROM restaurants WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $inactive_with_email = $conn->query("SELECT COUNT(*) FROM restaurants WHERE is_active = 0 AND email IS NOT NULL AND email != ''")->fetchColumn();
        
        // Debug: Log estadísticas generales
        error_log("DEBUG - Estadísticas de restaurantes:");
        error_log("DEBUG - Total restaurantes: $total_restaurants");
        error_log("DEBUG - Restaurantes activos: $active_restaurants");
        error_log("DEBUG - Restaurantes inactivos: $inactive_restaurants");
        error_log("DEBUG - Restaurantes con email: $restaurants_with_email");
        error_log("DEBUG - Inactivos con email: $inactive_with_email");
        
        // Obtener restaurantes inactivos con información de plan
        $stmt = $conn->prepare("
            SELECT 
                r.id, 
                r.name, 
                r.email, 
                r.slug, 
                r.subscription_status,
                r.trial_ends_at,
                r.subscription_ends_at,
                r.created_at,
                r.is_active,
                p.name as plan_name,
                p.slug as plan_slug,
                p.is_free,
                p.base_price,
                CASE 
                    WHEN p.is_free = 1 THEN 'prueba_gratuita'
                    ELSE 'plan_pago'
                END as tipo_restaurante
            FROM restaurants r
            JOIN plans p ON r.current_plan_id = p.id
            WHERE r.is_active = 0
            AND r.email IS NOT NULL
            AND r.email != ''
            ORDER BY p.is_free DESC, r.created_at DESC
        ");
        $stmt->execute();
        $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log detalles de restaurantes encontrados
        error_log("DEBUG - Restaurantes inactivos encontrados: " . count($restaurants));
        foreach ($restaurants as $restaurant) {
            error_log("DEBUG - Restaurante: {$restaurant['name']} ({$restaurant['email']}) - Plan: {$restaurant['plan_name']} - Tipo: {$restaurant['tipo_restaurante']}");
        }
        
        // Separar por tipo
        $pruebas_gratuitas = [];
        $planes_pago = [];
        
        foreach ($restaurants as $restaurant) {
            if ($restaurant['tipo_restaurante'] === 'prueba_gratuita') {
                $pruebas_gratuitas[] = $restaurant;
            } else {
                $planes_pago[] = $restaurant;
            }
        }
        
        return [
            'pruebas_gratuitas' => $pruebas_gratuitas,
            'planes_pago' => $planes_pago,
            'total_pruebas_gratuitas' => count($pruebas_gratuitas),
            'total_planes_pago' => count($planes_pago),
            'total_general' => count($restaurants),
            'debug_stats' => [
                'total_restaurants' => $total_restaurants,
                'active_restaurants' => $active_restaurants,
                'inactive_restaurants' => $inactive_restaurants,
                'restaurants_with_email' => $restaurants_with_email,
                'inactive_with_email' => $inactive_with_email
            ]
        ];
    } catch (PDOException $e) {
        error_log("Error obteniendo restaurantes inactivos: " . $e->getMessage());
        return [
            'pruebas_gratuitas' => [],
            'planes_pago' => [],
            'total_pruebas_gratuitas' => 0,
            'total_planes_pago' => 0,
            'total_general' => 0,
            'debug_stats' => [
                'total_restaurants' => 0,
                'active_restaurants' => 0,
                'inactive_restaurants' => 0,
                'restaurants_with_email' => 0,
                'inactive_with_email' => 0
            ]
        ];
    }
}

// Obtener restaurantes inactivos
$inactive_data = getInactiveRestaurants($conn);

// Procesar envío de emails
if ($_POST && isset($_POST['send_emails'])) {
    $selected_types = $_POST['selected_types'] ?? [];
    $subject = trim($_POST['subject'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    $email_type = $_POST['email_type'] ?? 'custom';
    
    // Debug: Log los datos recibidos
    error_log("DEBUG - send-emails-inactive.php - Datos recibidos:");
    error_log("DEBUG - selected_types: " . print_r($selected_types, true));
    error_log("DEBUG - subject: " . $subject);
    error_log("DEBUG - message_content length: " . strlen($message_content));
    error_log("DEBUG - POST data: " . print_r($_POST, true));
    
    if (empty($selected_types)) {
        $_SESSION['email_send_error'] = 'Debes seleccionar al menos un tipo de restaurante';
        header('Location: ' . BASE_URL . '/super_admin/send-emails-inactive.php?error=1');
        exit;
    } elseif (empty($subject)) {
        $_SESSION['email_send_error'] = 'El asunto es obligatorio';
        header('Location: ' . BASE_URL . '/super_admin/send-emails-inactive.php?error=1');
        exit;
    } elseif (empty($message_content)) {
        $_SESSION['email_send_error'] = 'El mensaje es obligatorio';
        header('Location: ' . BASE_URL . '/super_admin/send-emails-inactive.php?error=1');
        exit;
    } else {
        try {
            error_log("DEBUG - Iniciando transacción...");
            $conn->beginTransaction();
            
            // Construir consulta según tipos seleccionados
            $where_conditions = [];
            $params = [];
            
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
                error_log("DEBUG - No se encontraron restaurantes inactivos");
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
                        $failed_count++;
                        $failed_emails[] = $restaurant['email'];
                        error_log("DEBUG - Error al enviar email a: " . $restaurant['email']);
                        
                        // Registrar error en logs usando la estructura correcta de la tabla
                        $stmt = $conn->prepare("
                            INSERT INTO email_logs (email_type, restaurant_id, recipient_email, sent_at, success, error_message)
                            VALUES (?, ?, ?, NOW(), 0, ?)
                        ");
                        $stmt->execute(['inactive_reactivation', $restaurant['id'], $restaurant['email'], 'Error al enviar email']);
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
            
            // Guardar resultados en sesión para mostrar después del redirect
            $_SESSION['email_send_results'] = [
                'total' => count($restaurants),
                'sent' => $sent_count,
                'failed' => $failed_count,
                'failed_emails' => $failed_emails,
                'subject' => $subject,
                'selected_types' => $selected_types
            ];
            
            error_log("DEBUG - Redirect a success...");
            // Redirect para evitar reenvío de formulario
            header('Location: ' . BASE_URL . '/super_admin/send-emails-inactive.php?success=1');
            exit;
            
        } catch (Exception $e) {
            error_log("ERROR - send-emails-inactive.php: " . $e->getMessage());
            error_log("ERROR - Stack trace: " . $e->getTraceAsString());
            
            if ($conn->inTransaction()) {
                $conn->rollback();
            }
            
            $_SESSION['email_send_error'] = 'Error al enviar emails: ' . $e->getMessage();
            header('Location: ' . BASE_URL . '/super_admin/send-emails-inactive.php?error=1');
            exit;
        }
    }
}

// Mostrar resultados del envío si existen en sesión
if (isset($_GET['success']) && isset($_SESSION['email_send_results'])) {
    $email_stats = $_SESSION['email_send_results'];
    
    $types_text = [];
    if (in_array('prueba_gratuita', $email_stats['selected_types'])) {
        $types_text[] = 'Pruebas Gratuitas';
    }
    if (in_array('plan_pago', $email_stats['selected_types'])) {
        $types_text[] = 'Planes de Pago';
    }
    
    $types_display = implode(', ', $types_text);
    $message = "Emails enviados exitosamente. Total: {$email_stats['total']}, Enviados: {$email_stats['sent']}, Fallidos: {$email_stats['failed']}. Tipos: {$types_display}";
    
    // Limpiar los resultados de la sesión
    unset($_SESSION['email_send_results']);
}

// Mostrar errores si existen en sesión
if (isset($_SESSION['email_send_error'])) {
    $error = $_SESSION['email_send_error'];
    unset($_SESSION['email_send_error']);
}

// Plantillas específicas para reactivación
$email_templates = [
    'reactivacion_prueba_gratuita' => [
        'subject' => '🔥 ¡Reactiva tu menú digital!',
        'message' => 'Hola {restaurant_name},

¡Te extrañamos! Notamos que tu menú digital gratuito ya no está activo.

¿Sabías que puedes reactivarlo?:

✅ Menú digital completo
✅ Panel de administración
✅ Código QR personalizado
✅ Gestionar tus ordenes
✅ Y mucho más.


Para reactivar tu cuenta, simplemente visita:
👉 {restaurant_slug}

 Solo te tomará unos minutos volver a tener tu menú digital funcionando.

Si necesitas ayuda, estamos aquí para ti:
• WhatsApp: +56 9 1234 5678
• Email: tumenufast@gmail.com

¡No pierdas la oportunidad de tener tu menú digital funcionando!

Saludos cordiales,
Equipo Tumenufast

---
Este email fue enviado a {restaurant_name} como parte de nuestra campaña de reactivación de cuentas gratuitas.
© 2025 Tumenufast. Todos los derechos reservados.'
    ],
    'reactivacion_plan_pago' => [
        'subject' => '🔥 ¡Tu menú digital te está esperando!',
        'message' => 'Hola {restaurant_name},

¡Te extrañamos! Notamos que tu menú digital con el plan {plan_name} ya no está activo.

Tu menú digital es una herramienta poderosa para:
✅ Atraer más clientes
✅ Facilitar pedidos
✅ Mejorar la experiencia del cliente
✅ Aumentar tus ventas

Para reactivar tu cuenta, visita:
👉 {restaurant_slug}

Opciones disponibles:
• Reactivar tu plan actual: {plan_name}
• Cambiar a un plan más económico


¿Necesitas ayuda para elegir la mejor opción? Contáctanos:
• WhatsApp: +56 9 1234 5678
• Email: tumenufast@gmail.com

¡No dejes que tus clientes se vayan a la competencia!

Saludos cordiales,
Equipo Tumenufast

---
Este email fue enviado a {restaurant_name} como parte de nuestra campaña de reactivación.
© 2025 Tumenufast. Todos los derechos reservados.'
    ],
    'oferta_especial' => [
        'subject' => '🎉 ¡Oferta especial para reactivar tu menú digital!',
        'message' => 'Hola {restaurant_name},

¡Tenemos una oferta especial para ti!

Como ex cliente de Tumenufast, queremos que vuelvas a disfrutar de los beneficios de tener un menú digital profesional.

OFERTA ESPECIAL:
• 15% de descuento en tu primer mes.

Para aprovechar esta oferta, visita:
👉 {restaurant_slug}

Esta oferta es válida solo por 7 días, ¡no la dejes pasar!

¿Tienes preguntas? Contáctanos:
• WhatsApp: +56 9 1234 5678
• Email: tumenufast@gmail.com

¡Esperamos verte de vuelta!

Saludos cordiales,
Equipo Tumenufast

---
Este email fue enviado a {restaurant_name} como parte de nuestra campaña de ofertas especiales.
© 2025 Tumenufast. Todos los derechos reservados.'
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emails a Restaurantes Inactivos - Super Admin</title>
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
        .type-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .type-card:hover {
            transform: translateY(-2px);
        }
        .type-card.selected {
            border: 2px solid #007bff;
            background-color: #f8f9fa;
        }
        .template-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .template-card:hover {
            background-color: #f8f9fa;
        }
        .template-card.selected {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
        .stats-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .restaurant-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .restaurant-item {
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        .restaurant-item:last-child {
            border-bottom: none;
        }
        .restaurant-email {
            color: #666;
            font-size: 0.8rem;
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
                        <a class="nav-link" href="/super_admin/create-restaurant.php">
                            <i class="fas fa-plus"></i> Crear Restaurante
                        </a>
                        <a class="nav-link" href="/super_admin/send-emails.php">
                            <i class="fas fa-envelope"></i> Enviar Emails
                        </a>
                        <a class="nav-link active" href="/super_admin/send-emails-inactive.php">
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
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-user-times"></i> Emails a Restaurantes Inactivos</h1>
                    <div>
                        
                        <a href="/super_admin/send-emails.php" class="btn btn-outline-primary">
                            <i class="fas fa-envelope"></i> Emails Generales
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
                
                <?php if (!empty($email_stats)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar"></i> Resultados del Envío
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-primary"><?= $email_stats['total'] ?></h3>
                                        <small class="text-muted">Total Destinatarios</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-success"><?= $email_stats['sent'] ?></h3>
                                        <small class="text-muted">Enviados Exitosamente</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-danger"><?= $email_stats['failed'] ?></h3>
                                        <small class="text-muted">Fallidos</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="text-info"><?= round(($email_stats['sent'] / $email_stats['total']) * 100, 1) ?>%</h3>
                                        <small class="text-muted">Tasa de Éxito</small>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($email_stats['failed_emails'])): ?>
                                <div class="mt-3">
                                    <h6>Emails que fallaron:</h6>
                                    <div class="alert alert-warning">
                                        <small><?= implode(', ', $email_stats['failed_emails']) ?></small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Estadísticas Generales -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?= $inactive_data['total_general'] ?></h3>
                                <p class="mb-0">Total Restaurantes Inactivos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3><?= $inactive_data['total_pruebas_gratuitas'] ?></h3>
                                <p class="mb-0">Pruebas Gratuitas Inactivas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h3><?= $inactive_data['total_planes_pago'] ?></h3>
                                <p class="mb-0">Planes de Pago Inactivos</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($inactive_data['total_general'] == 0): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> No hay restaurantes inactivos</h5>
                        <p>No se encontraron restaurantes inactivos en la base de datos. Esto puede deberse a:</p>
                        <ul>
                            <li>Todos los restaurantes están activos (is_active = 1)</li>
                            <li>Los restaurantes inactivos no tienen email válido</li>
                            <li>No hay restaurantes registrados en el sistema</li>
                        </ul>
                        
                        <div class="mt-3">
                            <h6>Estadísticas de la base de datos:</h6>
                            <div class="row">
                                <div class="col-md-2">
                                    <strong>Total:</strong> <?= $inactive_data['debug_stats']['total_restaurants'] ?>
                                </div>
                                <div class="col-md-2">
                                    <strong>Activos:</strong> <?= $inactive_data['debug_stats']['active_restaurants'] ?>
                                </div>
                                <div class="col-md-2">
                                    <strong>Inactivos:</strong> <?= $inactive_data['debug_stats']['inactive_restaurants'] ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Con email:</strong> <?= $inactive_data['debug_stats']['restaurants_with_email'] ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Inactivos con email:</strong> <?= $inactive_data['debug_stats']['inactive_with_email'] ?>
                                </div>
                            </div>
                        </div>
                        
                        <p class="mt-3"><strong>Para probar el sistema:</strong></p>
                        <ol>
                            <li>Ve a <a href="/super_admin/restaurants.php">Restaurantes</a></li>
                            <li>Desactiva algunos restaurantes cambiando su estado</li>
                            <li>Vuelve a esta página para enviar emails</li>
                        </ol>
                        
                        <div class="mt-3">
                            <a href="diagnostico-emails.php" class="btn btn-info">
                                <i class="fas fa-stethoscope"></i> Ver Diagnóstico Completo
                            </a>
                            <a href="../index.php" class="btn btn-success">
                                <i class="fas fa-list"></i> Ver Planes
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                  
                <?php endif; ?>
                
                <form method="POST" id="emailForm">
                    <div class="row">
                        <!-- Selección de Tipos -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-filter"></i> Seleccionar Tipos de Restaurantes
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="selectAllTypes()">
                                            <i class="fas fa-check-double"></i> Seleccionar Todos
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deselectAllTypes()">
                                            <i class="fas fa-times"></i> Deseleccionar Todos
                                        </button>
                                    </div>
                                    
                                    <!-- Pruebas Gratuitas -->
                                    <div class="type-card" data-type="prueba_gratuita">
                                        <div class="form-check">
                                            <input class="form-check-input type-checkbox" type="checkbox" 
                                                   name="selected_types[]" value="prueba_gratuita" 
                                                   id="type_prueba_gratuita">
                                            <label class="form-check-label" for="type_prueba_gratuita">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1 text-warning">
                                                            <i class="fas fa-gift"></i> Pruebas Gratuitas
                                                        </h6>
                                                        <small class="text-muted">Restaurantes con planes gratuitos inactivos</small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-warning stats-badge">
                                                            <?= $inactive_data['total_pruebas_gratuitas'] ?> restaurantes
                                                        </span>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                        
                                        <?php if (!empty($inactive_data['pruebas_gratuitas'])): ?>
                                            <div class="mt-3">
                                                <small class="text-muted">Ejemplos de restaurantes:</small>
                                                <div class="restaurant-list">
                                                    <?php foreach (array_slice($inactive_data['pruebas_gratuitas'], 0, 5) as $restaurant): ?>
                                                        <div class="restaurant-item">
                                                            <strong><?= htmlspecialchars($restaurant['name']) ?></strong>
                                                            <div class="restaurant-email"><?= htmlspecialchars($restaurant['email']) ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (count($inactive_data['pruebas_gratuitas']) > 5): ?>
                                                        <div class="restaurant-item text-muted">
                                                            <small>... y <?= count($inactive_data['pruebas_gratuitas']) - 5 ?> más</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Planes de Pago -->
                                    <div class="type-card" data-type="plan_pago">
                                        <div class="form-check">
                                            <input class="form-check-input type-checkbox" type="checkbox" 
                                                   name="selected_types[]" value="plan_pago" 
                                                   id="type_plan_pago">
                                            <label class="form-check-label" for="type_plan_pago">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1 text-danger">
                                                            <i class="fas fa-credit-card"></i> Planes de Pago
                                                        </h6>
                                                        <small class="text-muted">Restaurantes con planes pagos inactivos</small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-danger stats-badge">
                                                            <?= $inactive_data['total_planes_pago'] ?> restaurantes
                                                        </span>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                        
                                        <?php if (!empty($inactive_data['planes_pago'])): ?>
                                            <div class="mt-3">
                                                <small class="text-muted">Ejemplos de restaurantes:</small>
                                                <div class="restaurant-list">
                                                    <?php foreach (array_slice($inactive_data['planes_pago'], 0, 5) as $restaurant): ?>
                                                        <div class="restaurant-item">
                                                            <strong><?= htmlspecialchars($restaurant['name']) ?></strong>
                                                            <div class="restaurant-email"><?= htmlspecialchars($restaurant['email']) ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (count($inactive_data['planes_pago']) > 5): ?>
                                                        <div class="restaurant-item text-muted">
                                                            <small>... y <?= count($inactive_data['planes_pago']) - 5 ?> más</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Plantillas de Email -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-file-alt"></i> Plantillas de Reactivación
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div id="templateCards">
                                        <?php foreach ($email_templates as $key => $template): ?>
                                            <div class="card template-card mb-2" data-template="<?= $key ?>">
                                                <div class="card-body py-2">
                                                    <h6 class="card-title mb-1"><?= ucfirst(str_replace('_', ' ', $key)) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars(substr($template['subject'], 0, 50)) ?>...</small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contenido del Email -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-edit"></i> Contenido del Email
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Asunto *</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Mensaje *</label>
                                <textarea class="form-control" id="message" name="message" rows="10" required 
                                          placeholder="Escribe tu mensaje aquí...&#10;&#10;Variables disponibles:&#10;{restaurant_name} - Nombre del restaurante&#10;{plan_name} - Nombre del plan actual&#10;{restaurant_slug} - URL del restaurante"></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Variables disponibles:</strong><br>
                                <code>{restaurant_name}</code> - Nombre del restaurante<br>
                                <code>{plan_name}</code> - Nombre del plan actual<br>
                                <code>{restaurant_slug}</code> - URL del restaurante
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="previewEmail()">
                                    <i class="fas fa-eye"></i> Vista Previa
                                </button>
                                <button type="submit" name="send_emails" class="btn btn-primary" onclick="return confirmSend()">
                                    <i class="fas fa-paper-plane"></i> Enviar Emails
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de Vista Previa -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vista Previa del Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Asunto:</strong>
                        <div id="previewSubject" class="border p-2 bg-light"></div>
                    </div>
                    <div>
                        <strong>Mensaje:</strong>
                        <div id="previewMessage" class="border p-3 bg-light" style="white-space: pre-wrap;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Logout -->
    <div class="modal fade" id="logoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Cierre de Sesión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ¿Estás seguro de que quieres cerrar sesión?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="/super_admin/logout.php" class="btn btn-primary">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables para las plantillas
        const emailTemplates = <?= json_encode($email_templates) ?>;
        
        // Seleccionar/deseleccionar todos los tipos
        function selectAllTypes() {
            document.querySelectorAll('.type-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                checkbox.closest('.type-card').classList.add('selected');
            });
        }
        
        function deselectAllTypes() {
            document.querySelectorAll('.type-checkbox').forEach(checkbox => {
                checkbox.checked = false;
                checkbox.closest('.type-card').classList.remove('selected');
            });
        }
        
        // Manejar selección de tipos
        document.querySelectorAll('.type-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const card = this.closest('.type-card');
                if (this.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        });
        
        // Manejar selección de plantillas
        document.querySelectorAll('.template-card').forEach(card => {
            card.addEventListener('click', function() {
                const templateKey = this.dataset.template;
                const template = emailTemplates[templateKey];
                
                if (template) {
                    document.getElementById('subject').value = template.subject;
                    document.getElementById('message').value = template.message;
                }
                
                // Actualizar selección visual
                document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
        
        // Vista previa del email
        function previewEmail() {
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
            if (!subject || !message) {
                alert('Por favor completa el asunto y el mensaje antes de ver la vista previa.');
                return;
            }
            
            // Reemplazar variables con ejemplos
            const previewSubject = subject
                .replace('{restaurant_name}', 'Restaurante Ejemplo')
                .replace('{plan_name}', 'Plan Básico')
                .replace('{restaurant_slug}', 'restaurante-ejemplo');
            
            const previewMessage = message
                .replace('{restaurant_name}', 'Restaurante Ejemplo')
                .replace('{plan_name}', 'Plan Básico')
                .replace('{restaurant_slug}', 'restaurante-ejemplo');
            
            document.getElementById('previewSubject').textContent = previewSubject;
            document.getElementById('previewMessage').textContent = previewMessage;
            
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }
        
        // Confirmar envío
        function confirmSend() {
            const selectedTypes = document.querySelectorAll('.type-checkbox:checked');
            const subject = document.getElementById('subject').value.trim();
            const message = document.getElementById('message').value.trim();
            
            if (selectedTypes.length === 0) {
                alert('Debes seleccionar al menos un tipo de restaurante.');
                return false;
            }
            
            if (!subject) {
                alert('El asunto es obligatorio.');
                return false;
            }
            
            if (!message) {
                alert('El mensaje es obligatorio.');
                return false;
            }
            
            const count = selectedTypes.length;
            const typeNames = Array.from(selectedTypes).map(cb => {
                return cb.closest('.type-card').querySelector('h6').textContent.trim();
            }).join(', ');
            
            const confirmed = confirm(`¿Estás seguro de que quieres enviar este email a restaurantes inactivos?\n\nTipos seleccionados: ${typeNames}\n\nEsta acción no se puede deshacer.`);
            
            if (confirmed) {
                // Deshabilitar el botón para prevenir doble envío
                const submitBtn = document.querySelector('button[name="send_emails"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                
                // Mostrar mensaje de procesamiento
                const processingAlert = document.createElement('div');
                processingAlert.className = 'alert alert-info alert-dismissible fade show';
                processingAlert.innerHTML = `
                    <i class="fas fa-info-circle"></i> 
                    Procesando envío de emails a restaurantes inactivos. Por favor, espera...
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const form = document.getElementById('emailForm');
                form.insertBefore(processingAlert, form.firstChild);
                
                // Usar AJAX para enviar el formulario y mostrar resultados en tiempo real
                sendEmailsAjax();
                
                return false; // Prevenir envío normal del formulario
            }
            
            return false;
        }
        
        // Función AJAX para enviar emails
        function sendEmailsAjax() {
            const form = document.getElementById('emailForm');
            const formData = new FormData(form);
            
            fetch('send-emails-inactive-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Remover mensaje de procesamiento
                const processingAlert = document.querySelector('.alert-info');
                if (processingAlert) {
                    processingAlert.remove();
                }
                
                // Mostrar resultados
                if (data.success) {
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success alert-dismissible fade show';
                    successAlert.innerHTML = `
                        <i class="fas fa-check-circle"></i> 
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    form.insertBefore(successAlert, form.firstChild);
                } else {
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                    errorAlert.innerHTML = `
                        <i class="fas fa-exclamation-circle"></i> 
                        ${data.error}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    form.insertBefore(errorAlert, form.firstChild);
                }
                
                // Restaurar botón
                const submitBtn = document.querySelector('button[name="send_emails"]');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Emails';
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Remover mensaje de procesamiento
                const processingAlert = document.querySelector('.alert-info');
                if (processingAlert) {
                    processingAlert.remove();
                }
                
                // Mostrar error
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                errorAlert.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i> 
                    Error de conexión: ${error.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                form.insertBefore(errorAlert, form.firstChild);
                
                // Restaurar botón
                const submitBtn = document.querySelector('button[name="send_emails"]');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Emails';
            });
        }
        
        // Contador de caracteres para el mensaje
        document.getElementById('message').addEventListener('input', function() {
            const maxLength = 5000;
            const currentLength = this.value.length;
            const remaining = maxLength - currentLength;
            
            // Crear o actualizar contador
            let counter = document.getElementById('charCounter');
            if (!counter) {
                counter = document.createElement('small');
                counter.id = 'charCounter';
                counter.className = 'text-muted';
                this.parentNode.appendChild(counter);
            }
            
            counter.textContent = `${currentLength} / ${maxLength} caracteres`;
            
            if (remaining < 100) {
                counter.className = 'text-warning';
            } else {
                counter.className = 'text-muted';
            }
        });
    </script>
</body>
</html> 
