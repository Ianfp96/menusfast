<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Funciones de autenticación adicionales para el sistema
 * Nota: Las funciones básicas de autenticación (isLoggedIn, checkAuth, getCurrentRestaurantId, checkPlanLimits)
 * están definidas en config/functions.php
 */

// Verificar si el usuario tiene acceso a un recurso específico
function checkResourceAccess($restaurant_id) {
    if (!isLoggedIn() || $_SESSION['restaurant_id'] != $restaurant_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
}

// Verificar si el usuario es super admin
function isSuperAdmin() {
    return isset($_SESSION['super_admin_id']);
}

// Verificar acceso de super admin
function checkSuperAdminAuth() {
    if (!isSuperAdmin()) {
        redirect(BASE_URL . '/super_admin/login.php');
    }
}

// Verificar si la suscripción está activa
function checkSubscriptionStatus($restaurant) {
    if (!isSubscriptionActive($restaurant)) {
        redirect(BASE_URL . '/restaurante/planes.php');
    }
}

// Verificar si el perfil está completo
function checkProfileCompletion($restaurant) {
    if (!$restaurant['profile_completed']) {
        redirect(BASE_URL . '/restaurante/completar-perfil.php');
    }
} 
