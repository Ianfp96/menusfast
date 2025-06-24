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

// Obtener planes disponibles
try {
    $stmt = $conn->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price ASC");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error al cargar los planes: ' . $e->getMessage();
}

// Obtener estadísticas de restaurantes por plan
try {
    $stmt = $conn->query("
        SELECT 
            p.id as plan_id,
            p.name as plan_name,
            p.slug as plan_slug,
            COUNT(r.id) as restaurant_count,
            COUNT(CASE WHEN r.is_active = 1 THEN 1 END) as active_count,
            COUNT(CASE WHEN r.subscription_status = 'expired' THEN 1 END) as expired_count
        FROM plans p
        LEFT JOIN restaurants r ON p.id = r.current_plan_id
        WHERE p.is_active = 1
        GROUP BY p.id, p.name, p.slug
        ORDER BY p.base_price ASC
    ");
    $plan_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error al cargar estadísticas: ' . $e->getMessage();
}

// Función para obtener restaurantes expirados
function getExpiredRestaurants($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT r.id, r.name, r.email, r.slug, p.name as plan_name, r.subscription_ends_at
            FROM restaurants r
            JOIN plans p ON r.current_plan_id = p.id
            WHERE r.subscription_status = 'expired'
            AND r.is_active = 1
            AND r.email IS NOT NULL
            AND r.email != ''
            ORDER BY r.subscription_ends_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error obteniendo restaurantes expirados: " . $e->getMessage());
        return [];
    }
}

// Procesar envío de emails
if ($_POST && isset($_POST['send_emails'])) {
    $selected_plans = $_POST['selected_plans'] ?? [];
    $subject = trim($_POST['subject'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    $email_type = $_POST['email_type'] ?? 'custom';
    
    if (empty($selected_plans)) {
        $_SESSION['email_send_error'] = 'Debes seleccionar al menos un plan';
        header('Location: ' . BASE_URL . '/super_admin/send-emails.php?error=1');
        exit;
    } elseif (empty($subject)) {
        $_SESSION['email_send_error'] = 'El asunto es obligatorio';
        header('Location: ' . BASE_URL . '/super_admin/send-emails.php?error=1');
        exit;
    } elseif (empty($message_content)) {
        $_SESSION['email_send_error'] = 'El mensaje es obligatorio';
        header('Location: ' . BASE_URL . '/super_admin/send-emails.php?error=1');
        exit;
    } else {
        try {
            $conn->beginTransaction();
            
            // Obtener restaurantes de los planes seleccionados
            $placeholders = str_repeat('?,', count($selected_plans) - 1) . '?';
            $stmt = $conn->prepare("
                SELECT r.id, r.name, r.email, r.slug, p.name as plan_name
                FROM restaurants r
                JOIN plans p ON r.current_plan_id = p.id
                WHERE r.current_plan_id IN ($placeholders)
                AND r.is_active = 1
                AND r.email IS NOT NULL
                AND r.email != ''
            ");
            $stmt->execute($selected_plans);
            $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($restaurants)) {
                throw new Exception('No se encontraron restaurantes activos con email válido para los planes seleccionados');
            }
            
            $sent_count = 0;
            $failed_count = 0;
            $failed_emails = [];
            
            foreach ($restaurants as $restaurant) {
                try {
                    // Personalizar el mensaje para cada restaurante
                    $personalized_message = str_replace(
                        ['{restaurant_name}', '{plan_name}', '{restaurant_slug}'],
                        [$restaurant['name'], $restaurant['plan_name'], $restaurant['slug']],
                        $message_content
                    );
                    
                    // Enviar email
                    $emailData = [
                        'restaurant_id' => $restaurant['id'],
                        'email' => $restaurant['email'],
                        'name' => $restaurant['name'],
                        'subject' => $subject,
                        'message' => $personalized_message
                    ];
                    
                    if ($emailService->sendCustomEmail($emailData)) {
                        $sent_count++;
                        
                        // Registrar en logs
                        $stmt = $conn->prepare("
                            INSERT INTO email_logs (email_type, restaurant_id, email, subject, sent_at, success)
                            VALUES (?, ?, ?, ?, NOW(), 1)
                        ");
                        $stmt->execute(['custom', $restaurant['id'], $restaurant['email'], $subject]);
                    } else {
                        $failed_count++;
                        $failed_emails[] = $restaurant['email'];
                        
                        // Registrar error en logs
                        $stmt = $conn->prepare("
                            INSERT INTO email_logs (email_type, restaurant_id, email, subject, sent_at, success, error_message)
                            VALUES (?, ?, ?, ?, NOW(), 0, ?)
                        ");
                        $stmt->execute(['custom', $restaurant['id'], $restaurant['email'], $subject, 'Error al enviar email']);
                    }
                    
                } catch (Exception $e) {
                    $failed_count++;
                    $failed_emails[] = $restaurant['email'];
                    error_log("Error enviando email a {$restaurant['email']}: " . $e->getMessage());
                }
            }
            
            $conn->commit();
            
            // Guardar resultados en sesión para mostrar después del redirect
            $_SESSION['email_send_results'] = [
                'total' => count($restaurants),
                'sent' => $sent_count,
                'failed' => $failed_count,
                'failed_emails' => $failed_emails,
                'subject' => $subject,
                'selected_plans' => $selected_plans
            ];
            
            // Redirect para evitar reenvío de formulario
            header('Location: ' . BASE_URL . '/super_admin/send-emails.php?success=1');
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['email_send_error'] = 'Error al enviar emails: ' . $e->getMessage();
            header('Location: ' . BASE_URL . '/super_admin/send-emails.php?error=1');
            exit;
        }
    }
}

// Mostrar resultados del envío si existen en sesión
if (isset($_GET['success']) && isset($_SESSION['email_send_results'])) {
    $email_stats = $_SESSION['email_send_results'];
    
    // Obtener nombres de los planes seleccionados
    $plan_names = [];
    if (!empty($email_stats['selected_plans'])) {
        $placeholders = str_repeat('?,', count($email_stats['selected_plans']) - 1) . '?';
        $stmt = $conn->prepare("SELECT name FROM plans WHERE id IN ($placeholders)");
        $stmt->execute($email_stats['selected_plans']);
        $plan_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    $plans_text = implode(', ', $plan_names);
    $message = "Emails enviados exitosamente. Total: {$email_stats['total']}, Enviados: {$email_stats['sent']}, Fallidos: {$email_stats['failed']}. Planes: {$plans_text}";
    
    // Limpiar los resultados de la sesión
    unset($_SESSION['email_send_results']);
}

// Mostrar errores si existen en sesión
if (isset($_SESSION['email_send_error'])) {
    $error = $_SESSION['email_send_error'];
    unset($_SESSION['email_send_error']);
}

// Obtener plantillas predefinidas
$email_templates = [
    'welcome' => [
        'subject' => '¡Bienvenido a Tumenufast!',
        'message' => 'Hola {restaurant_name},

¡Bienvenido a Tumenufast! Tu cuenta ha sido creada exitosamente.

Tu plan actual es: {plan_name}
Tu URL personalizada: {restaurant_slug}

Gracias por confiar en nosotros.

Saludos,
Equipo Tumenufast'
    ],
    'plan_upgrade' => [
        'subject' => 'Actualiza tu plan y obtén más beneficios',
        'message' => 'Hola {restaurant_name},

¿Sabías que puedes obtener más funcionalidades con nuestros planes superiores?

Tu plan actual: {plan_name}

Contáctanos para conocer las opciones disponibles.

Saludos,
Equipo Tumenufast'
    ],
    'maintenance' => [
        'subject' => 'Mantenimiento programado',
        'message' => 'Hola {restaurant_name},

Te informamos que realizaremos mantenimiento programado en nuestro sistema.

Fecha: [FECHA]
Hora: [HORA]
Duración estimada: [DURACIÓN]

Disculpa las molestias.

Saludos,
Equipo Tumenufast'
    ],
    'remarketing_expired' => [
        'subject' => '🔥 Tu plan ha vencido y tu menú digital ya no está disponible',
        'message' => 'Hola {restaurant_name},

Hola, {nombre},

Queremos informarte que tu plan {plan_name} ha vencido y, por lo tanto, tu menú digital ya no está visible para tus clientes.

Esto significa que:

El acceso a tu menú mediante el código QR ha sido desactivado.

Ya no podrás hacer actualizaciones ni recibir estadísticas.

Si deseas seguir utilizando tu menú digital, puedes reactivarlo fácilmente desde este enlace:
👉 {restaurant_slug}

Si necesitas ayuda para renovarlo o tienes dudas, estamos disponibles para ayudarte.

Contacto directo:
• WhatsApp: +56 9 1234 5678
• Email: soporte@tumenufast.com
• Horario: Lunes a Domingo, 9:00 AM - 8:00 PM

Saludos,
El equipo de Tumenufast

Saludos cordiales,
Equipo Tumenufast

P.D.: Si tienes alguna pregunta, responde a este email o llámanos directamente. Estamos aquí para ayudarte a reactivar tu cuenta exitosamente.

---
Este email fue enviado a {restaurant_name} como parte de nuestra campaña de reactivación.
© 2025 Tumenufast. Todos los derechos reservados.'
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Emails - Super Admin</title>
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
        .plan-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .plan-card:hover {
            transform: translateY(-2px);
        }
        .plan-card.selected {
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
                        <a class="nav-link active" href="/super_admin/send-emails.php">
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
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-envelope"></i> Enviar Emails Masivos</h1>
                    <a href="/super_admin/send-emails-inactive.php" class="btn btn-outline-warning">
                        <i class="fas fa-user-times"></i> Emails a Inactivos
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
                
                <form method="POST" id="emailForm">
                    <div class="row">
                        <!-- Selección de Planes -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-layer-group"></i> Seleccionar Planes
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="selectAllPlans()">
                                            <i class="fas fa-check-double"></i> Seleccionar Todos
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deselectAllPlans()">
                                            <i class="fas fa-times"></i> Deseleccionar Todos
                                        </button>
                                    </div>
                                    
                                    <?php foreach ($plan_stats as $plan): ?>
                                        <div class="plan-card" data-plan-id="<?= $plan['plan_id'] ?>">
                                            <div class="form-check">
                                                <input class="form-check-input plan-checkbox" type="checkbox" 
                                                       name="selected_plans[]" value="<?= $plan['plan_id'] ?>" 
                                                       id="plan_<?= $plan['plan_id'] ?>">
                                                <label class="form-check-label" for="plan_<?= $plan['plan_id'] ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-1"><?= htmlspecialchars($plan['plan_name']) ?></h6>
                                                            <small class="text-muted"><?= htmlspecialchars($plan['plan_slug']) ?></small>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="badge bg-primary stats-badge">
                                                                <?= $plan['active_count'] ?> activos
                                                            </span>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?= $plan['restaurant_count'] ?> total
                                                            </small>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Plantillas de Email -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-file-alt"></i> Plantillas de Email
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
        
        // Seleccionar/deseleccionar todos los planes
        function selectAllPlans() {
            document.querySelectorAll('.plan-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                checkbox.closest('.plan-card').classList.add('selected');
            });
        }
        
        function deselectAllPlans() {
            document.querySelectorAll('.plan-checkbox').forEach(checkbox => {
                checkbox.checked = false;
                checkbox.closest('.plan-card').classList.remove('selected');
            });
        }
        
        // Manejar selección de planes
        document.querySelectorAll('.plan-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const card = this.closest('.plan-card');
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
                    document.getElementById('emailType').value = templateKey;
                }
                
                // Actualizar selección visual
                document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
        
        // Cambio de tipo de email
        document.getElementById('emailType').addEventListener('change', function() {
            const templateKey = this.value;
            const template = emailTemplates[templateKey];
            
            if (template && templateKey !== 'custom') {
                document.getElementById('subject').value = template.subject;
                document.getElementById('message').value = template.message;
                
                // Actualizar selección visual
                document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
                const selectedCard = document.querySelector(`[data-template="${templateKey}"]`);
                if (selectedCard) {
                    selectedCard.classList.add('selected');
                }
            }
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
            const selectedPlans = document.querySelectorAll('.plan-checkbox:checked');
            const subject = document.getElementById('subject').value.trim();
            const message = document.getElementById('message').value.trim();
            
            if (selectedPlans.length === 0) {
                alert('Debes seleccionar al menos un plan.');
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
            
            const count = selectedPlans.length;
            const planNames = Array.from(selectedPlans).map(cb => {
                return cb.closest('.plan-card').querySelector('h6').textContent;
            }).join(', ');
            
            const confirmed = confirm(`¿Estás seguro de que quieres enviar este email a ${count} plan(es)?\n\nPlanes seleccionados: ${planNames}\n\nEsta acción no se puede deshacer.`);
            
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
                    Procesando envío de emails. Por favor, espera...
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const form = document.getElementById('emailForm');
                form.insertBefore(processingAlert, form.firstChild);
                
                return true;
            }
            
            return false;
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
        
        // Función para seleccionar campaña de reactivación
        function selectExpiredCampaign() {
            // Seleccionar la plantilla de reactivación
            const template = emailTemplates['remarketing_expired'];
            if (template) {
                document.getElementById('subject').value = template.subject;
                document.getElementById('message').value = template.message;
                document.getElementById('emailType').value = 'remarketing_expired';
                
                // Actualizar selección visual
                document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
                const selectedCard = document.querySelector('[data-template="remarketing_expired"]');
                if (selectedCard) {
                    selectedCard.classList.add('selected');
                }
                
                // Seleccionar solo planes que tienen restaurantes expirados
                document.querySelectorAll('.plan-checkbox').forEach(checkbox => {
                    const card = checkbox.closest('.plan-card');
                    const expiredBadge = card.querySelector('.badge.bg-danger');
                    if (expiredBadge && parseInt(expiredBadge.textContent) > 0) {
                        checkbox.checked = true;
                        card.classList.add('selected');
                    } else {
                        checkbox.checked = false;
                        card.classList.remove('selected');
                    }
                });
                
                // Mostrar mensaje de confirmación
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle"></i> 
                    Campaña de reactivación configurada. Se han seleccionado automáticamente los planes con restaurantes expirados.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const form = document.getElementById('emailForm');
                form.insertBefore(alertDiv, form.firstChild);
                
                // Auto-remover el mensaje después de 5 segundos
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
        }
        
        // Función para mostrar lista de restaurantes expirados
        function showExpiredList() {
            // Crear modal con la lista
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'expiredListModal';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-list"></i> Restaurantes con Planes Expirados
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                Estos restaurantes son candidatos ideales para la campaña de reactivación.
                            </div>
                            <div id="expiredRestaurantsList">
                                <div class="text-center">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="button" class="btn btn-warning" onclick="selectExpiredCampaign(); bootstrap.Modal.getInstance(document.getElementById('expiredListModal')).hide();">
                                <i class="fas fa-rocket"></i> Usar Campaña de Reactivación
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Mostrar el modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Cargar datos de restaurantes expirados (simulado)
            setTimeout(() => {
                const listContainer = document.getElementById('expiredRestaurantsList');
                listContainer.innerHTML = `
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Restaurante</th>
                                    <th>Plan</th>
                                    <th>Email</th>
                                    <th>Expiró</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Restaurante Ejemplo 1</td>
                                    <td><span class="badge bg-secondary">Básico</span></td>
                                    <td>ejemplo1@email.com</td>
                                    <td>Hace 5 días</td>
                                </tr>
                                <tr>
                                    <td>Restaurante Ejemplo 2</td>
                                    <td><span class="badge bg-info">Premium</span></td>
                                    <td>ejemplo2@email.com</td>
                                    <td>Hace 12 días</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Nota:</strong> Esta es una vista de ejemplo. Los datos reales se cargarían desde la base de datos.
                    </div>
                `;
            }, 1000);
            
            // Limpiar modal cuando se cierre
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        }
    </script>
</body>
</html> 
