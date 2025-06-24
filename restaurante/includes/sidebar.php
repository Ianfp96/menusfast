<?php
// Obtener la página actual para marcar el enlace activo
$current_page = basename($_SERVER['PHP_SELF']);
$current_tab = $_GET['tab'] ?? '';

// Determinar qué enlace debe estar activo
$is_dashboard = $current_page === 'dashboard.php';
$is_profile = $current_page === 'perfil.php' && ($current_tab === 'profile' || empty($current_tab));
$is_vendor = $current_page === 'perfil.php' && $current_tab === 'vendor';
$is_menu = $current_page === 'menu.php' || ($current_page === 'perfil.php' && $current_tab === 'menu');
$is_orders = $current_page === 'ordenes.php';
$is_cupones = $current_page === 'cupones.php';
$is_estadisticas = $current_page === 'estadisticas.php';
$is_sucursales = $current_page === 'sucursales.php';

// Verificar si el usuario tiene un plan que permite acceso a estadísticas (Gratuito, Premium u Oro)
$can_access_estadisticas = isset($restaurant['current_plan_id']) && in_array($restaurant['current_plan_id'], [1, 3, 4]);

// Verificar si el usuario tiene el plan Premium o Premium Pro para mostrar sucursales
// Y que NO sea una sucursal (solo restaurantes principales pueden gestionar sucursales)
$can_access_sucursales = isset($restaurant['current_plan_id']) && 
                         ($restaurant['current_plan_id'] == 3 || $restaurant['current_plan_id'] == 4) &&
                         (!isset($restaurant['is_branch']) || $restaurant['is_branch'] != 1);
?>

<!-- Botón de Toggle para Móviles -->
<button class="btn btn-primary d-md-none sidebar-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-expanded="false" aria-controls="sidebarCollapse">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div class="col-md-3 col-lg-2 px-0">
    <div class="sidebar collapse d-md-block" id="sidebarCollapse">
        <div class="p-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-utensils"></i> <?= htmlspecialchars($restaurant['name']) ?></h5>
            <button type="button" class="btn-close btn-close-white d-md-none" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-label="Cerrar"></button>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link <?= $is_dashboard ? 'active' : '' ?>" href="/restaurante/dashboard.php">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </span>
                <span></span>
            </a>
            <a class="nav-link <?= $is_profile ? 'active' : '' ?>" href="/restaurante/perfil.php?tab=profile">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-store"></i> Mi Restaurante
                </span>
                <span></span>
            </a>
            
            <a class="nav-link <?= $is_menu ? 'active' : '' ?>" href="/restaurante/menu.php">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-list"></i> Menú
                </span>
                <span></span>
            </a>
            <a class="nav-link <?= $is_orders ? 'active' : '' ?>" href="/restaurante/ordenes.php">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-shopping-cart"></i> Ordenes
                </span>
                <span></span>
            </a>
            <a class="nav-link <?= $is_cupones ? 'active' : '' ?>" href="/restaurante/cupones.php">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-ticket-alt"></i> Cupones
                </span>
                <span></span>
            </a>
            
            <?php if ($can_access_estadisticas): ?>
            <?php if ($restaurant['current_plan_id'] == 1 || $restaurant['current_plan_id'] == 2): ?>
            <span class="nav-link disabled" style="cursor: not-allowed; opacity: 0.7; display: flex; align-items: center; justify-content: space-between; border-radius: 8px; margin: 0 0.5rem;">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-chart-line"></i> Estadísticas
                </span>
                <span class="badge bg-warning text-dark" style="margin-left: 10px; flex-shrink: 0;">Premium</span>
            </span>
            <?php else: ?>
            <a class="nav-link <?= $is_estadisticas ? 'active' : '' ?>" href="/restaurante/estadisticas.php">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-chart-line"></i> Estadísticas
                </span>
                <span></span>
            </a>
            <?php endif; ?>
            <?php endif; ?>

            
            <?php if ($can_access_sucursales): ?>
            <hr>
            <a class="nav-link <?= $is_sucursales ? 'active' : '' ?>" href="/restaurante/sucursales.php">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-store"></i> Sucursales
                </span>
                <span></span>
            </a>
           
            <?php endif; ?>
            
            <hr>
            <a class="nav-link <?= $is_vendor ? 'active' : '' ?>" href="/restaurante/perfil-vendedor.php">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-user-tie"></i> Perfil Vendedor
                </span>
                <span></span>
            </a>
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#modalCerrarSesion">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </span>
                <span></span>
            </a>
        </nav>
    </div>
</div>

<!-- Modal de Confirmación de Cierre de Sesión -->
<div class="modal fade" id="modalCerrarSesion" tabindex="-1" aria-labelledby="modalCerrarSesionLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCerrarSesionLabel">Confirmar Cierre de Sesión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                ¿Estás seguro que deseas cerrar sesión?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="/restaurante/logout.php" class="btn btn-danger">Cerrar Sesión</a>
            </div>
        </div>
    </div>
</div>

<style>
.sidebar-toggle {
    position: static;
    z-index: 1030;
    padding: 0.5rem;
    background: #2c3e50;
    border: none;
    border-radius: 4px;
    margin: 0;
}

.sidebar-toggle:hover {
    background: #34495e;
}

.sidebar {
    background: #2c3e50;
    min-height: 100vh;
    color: white;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1020;
}

@media (max-width: 767px) {
    .sidebar {
        margin-top: 34px;
        padding-top: 14px;
    }
    
    .sidebar .btn-close {
        padding: 0.5rem;
        margin: -0.5rem -0.5rem -0.5rem auto;
        background-color: transparent;
        opacity: 0.8;
    }
    
    .sidebar .btn-close:hover {
        opacity: 1;
    }
}

@media (min-width: 768px) {
    .sidebar {
        position: relative;
        width: auto;
        margin-top: 0;
    }
    
    .sidebar-toggle {
        display: none;
    }
}

.sidebar .nav-link {
    color: #bdc3c7;
    padding: 0.5rem 0.5rem;
    transition: all 0.3s ease;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 48px;
}
.sidebar .nav-link:hover,
.sidebar .nav-link.active {
    background: #34495e;
    color: white;
    transform: translateX(5px);
}
.sidebar .nav-link i {
    width: 20px;
    text-align: center;
    margin-right: 10px;
    flex-shrink: 0;
}
.sidebar .nav-link.disabled {
    background: transparent !important;
    transform: none !important;
}
.sidebar .nav-link.disabled:hover {
    background: transparent !important;
    transform: none !important;
}
</style> 
