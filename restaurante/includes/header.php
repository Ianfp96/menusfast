<?php
if (!defined('BASE_URL')) {
    die('No se puede acceder directamente a este archivo');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>Panel de Restaurante</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/assets/css/admin.css" rel="stylesheet">
    
    <!-- Definir BASE_URL globalmente -->
    <script>
        window.BASE_URL = '<?php echo BASE_URL; ?>';
        
        // Si BASE_URL no está definido, detectarlo automáticamente
        if (!window.BASE_URL || window.BASE_URL === '') {
            const protocol = window.location.protocol;
            const host = window.location.host;
            const pathArray = window.location.pathname.split('/');
            const basePath = pathArray.slice(0, -1).join('/'); // Remover el archivo actual
            window.BASE_URL = protocol + '//' + host + basePath;
        }
        
        // Asegurar que BASE_URL use HTTPS si la página está en HTTPS
        if (window.location.protocol === 'https:' && window.BASE_URL && window.BASE_URL.startsWith('http:')) {
            window.BASE_URL = window.BASE_URL.replace('http:', 'https:');
        }
    </script>
    
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #ff8e8e;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #1a237e 0%, #0d47a1 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.4rem;
            color: #fff !important;
        }
        
        .navbar-brand i {
            color: #ffd700;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: #fff !important;
        }
        
        .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: #fff !important;
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="/restaurante/dashboard.php">
                <i class="fas fa-utensils me-2"></i>
                Panel de Restaurante
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/restaurante/dashboard.php">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/restaurante/menu.php">
                            <i class="fas fa-utensils me-1"></i> Menú
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/restaurante/sucursales.php">
                            <i class="fas fa-store me-1"></i> Sucursales
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/restaurante/ordenes.php">
                            <i class="fas fa-shopping-cart me-1"></i> Órdenes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/restaurante/planes.php">
                            <i class="fas fa-crown me-1"></i> Planes
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?= htmlspecialchars($_SESSION['restaurant_name'] ?? 'Restaurante') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="/restaurante/perfil.php">
                                    <i class="fas fa-user-cog me-2"></i> Perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/restaurante/qr.php">
                                    <i class="fas fa-qrcode me-2"></i> Código QR
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="/restaurante/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main> 
