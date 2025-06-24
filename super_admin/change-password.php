<?php
require_once '../config/database.php';
require_once '../classes/Database.php';
requireLogin('super_admin');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';

// Procesar el formulario de cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validaciones
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Todos los campos son obligatorios';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Las contraseñas nuevas no coinciden';
    } elseif (strlen($new_password) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres';
    } else {
        try {
            // Obtener la información actual del super admin
            $stmt = $conn->prepare("SELECT * FROM super_admins WHERE id = ?");
            $stmt->execute([$_SESSION['super_admin_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin) {
                $error = 'Error: No se encontró la información del administrador';
            } elseif (!password_verify($current_password, $admin['password'])) {
                $error = 'La contraseña actual es incorrecta';
            } else {
                // Verificar que la nueva contraseña sea diferente a la actual
                if (password_verify($new_password, $admin['password'])) {
                    $error = 'La nueva contraseña debe ser diferente a la actual';
                } else {
                    // Actualizar la contraseña
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE super_admins SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['super_admin_id']]);
                    
                    $message = 'Contraseña actualizada exitosamente';
                    
                    // Limpiar los campos del formulario
                    $_POST = [];
                }
            }
        } catch (PDOException $e) {
            error_log("Error al cambiar contraseña de super admin: " . $e->getMessage());
            $error = 'Error al actualizar la contraseña. Por favor, intenta más tarde.';
        }
    }
}

// Obtener información del super admin para mostrar
try {
    $stmt = $conn->prepare("SELECT username, email, created_at FROM super_admins WHERE id = ?");
    $stmt->execute([$_SESSION['super_admin_id']]);
    $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener información del super admin: " . $e->getMessage());
    $admin_info = null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Super Admin</title>
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
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .requirement-met {
            color: #28a745;
        }
        .requirement-not-met {
            color: #dc3545;
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
                        <a class="nav-link" href="/super_admin/send-emails-inactive.php">
                            <i class="fas fa-user-times"></i> Emails Inactivos
                        </a>
                        <a class="nav-link active" href="/super_admin/change-password.php">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </a>
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1><i class="fas fa-key"></i> Cambiar Contraseña</h1>
                        <a href="/super_admin/dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver al Dashboard
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
                    
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <!-- Información del Administrador -->
                            <?php if ($admin_info): ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-user-shield"></i> Información del Administrador</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Usuario:</strong> <?= htmlspecialchars($admin_info['username']) ?></p>
                                                <p><strong>Email:</strong> <?= htmlspecialchars($admin_info['email']) ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Miembro desde:</strong> <?= date('d/m/Y', strtotime($admin_info['created_at'])) ?></p>
                                                <p><strong>Última sesión:</strong> <?= date('d/m/Y H:i') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Formulario de Cambio de Contraseña -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-lock"></i> Cambiar Contraseña</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="passwordForm">
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Contraseña Actual *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                                <input type="password" class="form-control" id="current_password" name="current_password" 
                                                       value="<?= htmlspecialchars($_POST['current_password'] ?? '') ?>" required>
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">Nueva Contraseña *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-key"></i>
                                                </span>
                                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                                       value="<?= htmlspecialchars($_POST['new_password'] ?? '') ?>" required 
                                                       onkeyup="checkPasswordStrength()">
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div class="password-strength" id="passwordStrength"></div>
                                            <div class="password-requirements mt-2">
                                                <div id="lengthCheck" class="requirement-not-met">
                                                    <i class="fas fa-circle"></i> Mínimo 6 caracteres
                                                </div>
                                                <div id="uppercaseCheck" class="requirement-not-met">
                                                    <i class="fas fa-circle"></i> Al menos una mayúscula
                                                </div>
                                                <div id="lowercaseCheck" class="requirement-not-met">
                                                    <i class="fas fa-circle"></i> Al menos una minúscula
                                                </div>
                                                <div id="numberCheck" class="requirement-not-met">
                                                    <i class="fas fa-circle"></i> Al menos un número
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-check"></i>
                                                </span>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                       value="<?= htmlspecialchars($_POST['confirm_password'] ?? '') ?>" required 
                                                       onkeyup="checkPasswordMatch()">
                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div id="passwordMatch" class="mt-2"></div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <a href="/super_admin/dashboard.php" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Cancelar
                                            </a>
                                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                                <i class="fas fa-save"></i> Cambiar Contraseña
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Consejos de Seguridad -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Consejos de Seguridad</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <li>Usa una contraseña única que no hayas usado en otros servicios</li>
                                        <li>Combina letras mayúsculas, minúsculas, números y símbolos</li>
                                        <li>Evita información personal como fechas de nacimiento o nombres</li>
                                        <li>Considera usar un gestor de contraseñas</li>
                                        <li>Cambia tu contraseña regularmente</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Modal de Logout -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="logoutModalLabel">
                        <i class="fas fa-sign-out-alt"></i> Confirmar Cierre de Sesión
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-question-circle text-warning" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p class="mb-0">¿Estás seguro de que deseas cerrar sesión?</p>
                        <small class="text-muted">Serás redirigido a la página de inicio de sesión.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <a href="/super_admin/logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Sí, Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');
            
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
        
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthBar = document.getElementById('passwordStrength');
            const submitBtn = document.getElementById('submitBtn');
            
            // Verificar requisitos
            const hasLength = password.length >= 6;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            // Actualizar indicadores visuales
            document.getElementById('lengthCheck').className = hasLength ? 'requirement-met' : 'requirement-not-met';
            document.getElementById('uppercaseCheck').className = hasUppercase ? 'requirement-met' : 'requirement-not-met';
            document.getElementById('lowercaseCheck').className = hasLowercase ? 'requirement-met' : 'requirement-not-met';
            document.getElementById('numberCheck').className = hasNumber ? 'requirement-met' : 'requirement-not-met';
            
            // Calcular fortaleza
            let strength = 0;
            if (hasLength) strength++;
            if (hasUppercase) strength++;
            if (hasLowercase) strength++;
            if (hasNumber) strength++;
            
            // Actualizar barra de fortaleza
            strengthBar.className = 'password-strength';
            if (strength <= 1) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 3) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
            
            // Verificar si todas las condiciones se cumplen
            const allRequirementsMet = hasLength && hasUppercase && hasLowercase && hasNumber;
            submitBtn.disabled = !allRequirementsMet;
        }
        
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirmPassword === '') {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (newPassword === confirmPassword) {
                matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Las contraseñas coinciden</span>';
                submitBtn.disabled = false;
            } else {
                matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Las contraseñas no coinciden</span>';
                submitBtn.disabled = true;
            }
        }
        
        // Validar formulario antes de enviar
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;
            
            if (!currentPassword) {
                e.preventDefault();
                alert('Por favor, ingresa tu contraseña actual');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas nuevas no coinciden');
                return;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('La nueva contraseña debe tener al menos 6 caracteres');
                return;
            }
        });
    </script>
</body>
</html> 
