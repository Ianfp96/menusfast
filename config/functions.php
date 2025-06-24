<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../classes/Database.php';

// Funciones auxiliares para el sistema

/**
 * Función para subir archivos
 */
function uploadFile($file, $directory = 'general', $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Verificar tipo de archivo
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return false;
    }
    
    // Verificar tamaño (máximo 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }
    
    // Crear directorio si no existe
    $upload_dir = UPLOAD_PATH . $directory;
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generar nombre único
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $filepath = $upload_dir . '/' . $filename;
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $directory . '/' . $filename;
    }
    
    return false;
}

/**
 * Función para eliminar archivos
 */
function deleteFile($filepath) {
    if ($filepath && file_exists(UPLOAD_PATH . $filepath)) {
        return unlink(UPLOAD_PATH . $filepath);
    }
    return false;
}

/**
 * Función para generar slug único
 */
function generateUniqueSlug($text, $table, $column = 'slug', $id = null) {
    global $conn;
    
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
    $original_slug = $slug;
    $counter = 1;
    
    do {
        $query = "SELECT id FROM $table WHERE $column = :slug";
        if ($id) {
            $query .= " AND id != :id";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':slug', $slug);
        if ($id) {
            $stmt->bindParam(':id', $id);
        }
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        } else {
            break;
        }
    } while (true);
    
    return $slug;
}

/**
 * Función para formatear precios
 */
function formatPrice($price, $currency = null, $restaurant_id = null) {
    global $conn;
    
    // Si no se especifica moneda, intentar obtenerla del restaurante
    if ($currency === null) {
        if ($restaurant_id === null) {
            $restaurant_id = getCurrentRestaurantId();
        }
        
        if ($restaurant_id) {
            try {
                $stmt = $conn->prepare("SELECT currency FROM restaurants WHERE id = ?");
                $stmt->execute([$restaurant_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $currency = $result['currency'] ?? 'CLP';
            } catch (Exception $e) {
                $currency = 'CLP'; // Fallback a peso chileno
            }
        } else {
            $currency = 'CLP'; // Fallback a peso chileno
        }
    }
    
    // Obtener configuración de la moneda
    try {
        $stmt = $conn->prepare("SELECT * FROM currencies WHERE code = ? AND is_active = 1");
        $stmt->execute([$currency]);
        $currencyConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currencyConfig) {
            // Si no se encuentra la moneda, usar configuración por defecto
            $currencyConfig = [
                'symbol' => '$',
                'position' => 'before',
                'decimals' => 0,
                'thousands_separator' => '.',
                'decimal_separator' => ','
            ];
        }
    } catch (Exception $e) {
        // Fallback a configuración por defecto
        $currencyConfig = [
            'symbol' => '$',
            'position' => 'before',
            'decimals' => 0,
            'thousands_separator' => '.',
            'decimal_separator' => ','
        ];
    }
    
    // Formatear el número
    $formattedNumber = number_format(
        $price, 
        $currencyConfig['decimals'], 
        $currencyConfig['decimal_separator'], 
        $currencyConfig['thousands_separator']
    );
    
    // Aplicar símbolo según la posición
    if ($currencyConfig['position'] === 'before') {
        return $currencyConfig['symbol'] . $formattedNumber;
    } else {
        return $formattedNumber . $currencyConfig['symbol'];
    }
}

/**
 * Función para obtener las monedas disponibles
 */
function getAvailableCurrencies() {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT * FROM currencies WHERE is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Retornar monedas por defecto si hay error
        return [
            ['code' => 'CLP', 'name' => 'Peso Chileno', 'symbol' => '$'],
            ['code' => 'USD', 'name' => 'Dólar Estadounidense', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€']
        ];
    }
}

/**
 * Función para obtener la abreviación de la moneda de un restaurante
 */
function getCurrencyCode($restaurant_id = null) {
    global $conn;
    
    if ($restaurant_id === null) {
        $restaurant_id = getCurrentRestaurantId();
    }
    
    if (!$restaurant_id) {
        return 'CLP'; // Moneda por defecto
    }
    
    try {
        $stmt = $conn->prepare("SELECT currency FROM restaurants WHERE id = ?");
        $stmt->execute([$restaurant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['currency'] ?? 'CLP';
    } catch (Exception $e) {
        return 'CLP'; // Fallback
    }
}

/**
 * Función para obtener la configuración de moneda de un restaurante
 */
function getRestaurantCurrency($restaurant_id = null) {
    global $conn;
    
    if ($restaurant_id === null) {
        $restaurant_id = getCurrentRestaurantId();
    }
    
    if (!$restaurant_id) {
        return 'CLP'; // Moneda por defecto
    }
    
    try {
        $stmt = $conn->prepare("SELECT currency FROM restaurants WHERE id = ?");
        $stmt->execute([$restaurant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['currency'] ?? 'CLP';
    } catch (Exception $e) {
        return 'CLP'; // Fallback
    }
}

/**
 * Función para validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Función para generar código QR
 */
function generateQRCode($text, $size = 200) {
    // Usar servicio externo para generar QR
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($text);
}

/**
 * Función para limpiar texto
 */
function cleanText($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

/**
 * Función para truncar texto
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Función para verificar si es móvil
 */
function isMobile() {
    try {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Patrones para detectar dispositivos móviles
        $mobile_patterns = [
            '/Mobile|Android|iPhone|iPad|iPod/',
            '/BlackBerry|BB10/',
            '/Windows Phone/',
            '/Opera Mini/',
            '/IEMobile/',
            '/webOS/',
            '/Kindle|Silk/',
            '/PlayBook/',
            '/Nokia/',
            '/SonyEricsson/',
            '/LG/',
            '/HTC/',
            '/Samsung/',
            '/Motorola/',
            '/Xiaomi/',
            '/Huawei/',
            '/OnePlus/',
            '/Googlebot-Mobile/',
            '/Bingbot-Mobile/'
        ];
        
        foreach ($mobile_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return true;
            }
        }
        
        // Verificar también por el tamaño de pantalla si está disponible
        if (isset($_SERVER['HTTP_X_WAP_PROFILE']) || 
            isset($_SERVER['HTTP_PROFILE']) ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/vnd.wap.wml') !== false)) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        // Si hay algún error, asumir que no es móvil
        error_log("Error en isMobile(): " . $e->getMessage());
        return false;
    }
}

/**
 * Función para enviar email (placeholder)
 */
function sendEmail($to, $subject, $message, $from = null) {
    // Implementar con PHPMailer o servicio de email
    // Por ahora solo log
    error_log("Email to: $to, Subject: $subject");
    return true;
}

/**
 * Función para log de actividades
 */
function logActivity($restaurant_id, $action, $details = '') {
    global $conn;
    
    try {
        $query = "INSERT INTO activity_logs (restaurant_id, action, details, ip_address, user_agent) 
                  VALUES (:restaurant_id, :action, :details, :ip, :user_agent)";
        $stmt = $conn->prepare($query);
        
        // Usar bindValue para valores literales
        $stmt->bindValue(':restaurant_id', $restaurant_id);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':details', $details);
        $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
        $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

// Función para redireccionar
function redirect($url, $statusCode = 303) {
    error_log('Intentando redireccionar a: ' . $url);
    if (!headers_sent()) {
        header('Location: ' . $url, true, $statusCode);
        error_log('Headers enviados correctamente');
    } else {
        error_log('Headers ya fueron enviados, usando JavaScript para redirección');
        echo '<script>window.location.href = "' . $url . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $url . '"></noscript>';
    }
    exit();
}

// Función para mostrar mensajes de error
function showError($message) {
    return '<div class="alert alert-danger" role="alert">' . htmlspecialchars($message) . '</div>';
}

// Función para mostrar mensajes de éxito
function showSuccess($message) {
    return '<div class="alert alert-success" role="alert">' . htmlspecialchars($message) . '</div>';
}

/**
 * Función para verificar si el usuario está logueado
 */
function isLoggedIn() {
    return isset($_SESSION['restaurant_id']);
}

// Función para obtener el ID del restaurante actual
function getCurrentRestaurantId() {
    return $_SESSION['restaurant_id'] ?? null;
}

// Función para verificar si el usuario tiene acceso a una ruta
function checkAuth() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/restaurante/login.php');
    }
}

// Función para limpiar datos de entrada
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Función para generar un token CSRF
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para verificar token CSRF
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Función para formatear fecha
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

// Función para formatear moneda
function formatCurrency($amount, $restaurant_id = null) {
    global $conn;
    
    // Si no se especifica restaurante, usar el actual
    if ($restaurant_id === null) {
        $restaurant_id = getCurrentRestaurantId();
    }
    
    // Obtener la moneda del restaurante
    $currency = 'CLP'; // Fallback
    if ($restaurant_id) {
        try {
            $stmt = $conn->prepare("SELECT currency FROM restaurants WHERE id = ?");
            $stmt->execute([$restaurant_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $currency = $result['currency'] ?? 'CLP';
        } catch (Exception $e) {
            $currency = 'CLP'; // Fallback
        }
    }
    
    // Obtener configuración de la moneda
    try {
        $stmt = $conn->prepare("SELECT * FROM currencies WHERE code = ? AND is_active = 1");
        $stmt->execute([$currency]);
        $currencyConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currencyConfig) {
            // Si no se encuentra la moneda, usar configuración por defecto
            $currencyConfig = [
                'decimals' => 0,
                'thousands_separator' => '.',
                'decimal_separator' => ','
            ];
        }
    } catch (Exception $e) {
        // Fallback a configuración por defecto
        $currencyConfig = [
            'decimals' => 0,
            'thousands_separator' => '.',
            'decimal_separator' => ','
        ];
    }
    
    // Formatear el número
    $formattedNumber = number_format(
        $amount, 
        $currencyConfig['decimals'], 
        $currencyConfig['decimal_separator'], 
        $currencyConfig['thousands_separator']
    );
    
    // Verificar si es dispositivo móvil de manera segura
    $is_mobile = false;
    try {
        $is_mobile = isMobile();
    } catch (Exception $e) {
        // Si hay error al detectar móvil, asumir que no es móvil
        $is_mobile = false;
    }
    
    if ($is_mobile) {
        // En móviles, solo mostrar el número formateado sin el código de moneda
        return $formattedNumber;
    }
    
    // En desktop, mostrar con el código de moneda
    return $currency . ' ' . $formattedNumber;
}

/**
 * Función específica para formatear moneda en móviles (sin código de moneda)
 */
function formatCurrencyMobile($amount, $restaurant_id = null) {
    global $conn;
    
    // Si no se especifica restaurante, usar el actual
    if ($restaurant_id === null) {
        $restaurant_id = getCurrentRestaurantId();
    }
    
    // Obtener la moneda del restaurante
    $currency = 'CLP'; // Fallback
    if ($restaurant_id) {
        try {
            $stmt = $conn->prepare("SELECT currency FROM restaurants WHERE id = ?");
            $stmt->execute([$restaurant_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $currency = $result['currency'] ?? 'CLP';
        } catch (Exception $e) {
            $currency = 'CLP'; // Fallback
        }
    }
    
    // Obtener configuración de la moneda
    try {
        $stmt = $conn->prepare("SELECT * FROM currencies WHERE code = ? AND is_active = 1");
        $stmt->execute([$currency]);
        $currencyConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currencyConfig) {
            // Si no se encuentra la moneda, usar configuración por defecto
            $currencyConfig = [
                'decimals' => 0,
                'thousands_separator' => '.',
                'decimal_separator' => ','
            ];
        }
    } catch (Exception $e) {
        // Fallback a configuración por defecto
        $currencyConfig = [
            'decimals' => 0,
            'thousands_separator' => '.',
            'decimal_separator' => ','
        ];
    }
    
    // Formatear el número
    $formattedNumber = number_format(
        $amount, 
        $currencyConfig['decimals'], 
        $currencyConfig['decimal_separator'], 
        $currencyConfig['thousands_separator']
    );
    
    // En móviles, solo mostrar el número formateado sin el código de moneda
    return $formattedNumber;
}

// Función para verificar si una imagen es válida
function isValidImage($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!isset($file['error']) || is_array($file['error'])) {
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }

    if ($file['size'] > $maxSize) {
        return false;
    }

    return true;
}

// Función para generar una URL amigable
function generateSlug($text) {
    // Reemplazar caracteres especiales
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', ' ', $text);
    $text = preg_replace('/\s/', '-', $text);
    return $text;
}

/**
 * Función para verificar si una suscripción está activa
 */
function isSubscriptionActive($restaurant) {
    // Si es una sucursal, verificar la suscripción del restaurante padre
    if (isset($restaurant['is_branch']) && $restaurant['is_branch'] == 1 && isset($restaurant['parent_restaurant_id'])) {
        global $conn;
        try {
            $stmt = $conn->prepare("
                SELECT subscription_status, trial_ends_at, subscription_ends_at 
                FROM restaurants 
                WHERE id = ?
            ");
            $stmt->execute([$restaurant['parent_restaurant_id']]);
            $parent_restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($parent_restaurant) {
                if ($parent_restaurant['subscription_status'] === 'trial') {
                    return strtotime($parent_restaurant['trial_ends_at']) > time();
                }
                return $parent_restaurant['subscription_status'] === 'active';
            }
        } catch (PDOException $e) {
            error_log("Error verificando suscripción del restaurante padre: " . $e->getMessage());
        }
    }
    
    // Para restaurantes normales, verificar su propia suscripción
    if ($restaurant['subscription_status'] === 'trial') {
        return strtotime($restaurant['trial_ends_at']) > time();
    }
    return $restaurant['subscription_status'] === 'active';
}

/**
 * Función para obtener días restantes de prueba
 */
function getRemainingTrialDays($trialEndsAt) {
    $remaining = ceil((strtotime($trialEndsAt) - time()) / (60 * 60 * 24));
    return max(0, $remaining);
}

/**
 * Función para obtener el nombre del plan
 */
function getPlanName($plan_id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT name FROM plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['name'] : 'Plan Desconocido';
    } catch (PDOException $e) {
        error_log("Error al obtener nombre del plan: " . $e->getMessage());
        return 'Plan Desconocido';
    }
}

/**
 * Función para verificar límites del plan
 */
function checkPlanLimits($restaurant_id, $type) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT p.max_branches, p.max_products, p.max_categories,
                   COUNT(DISTINCT b.id) as branch_count,
                   COUNT(DISTINCT pr.id) as product_count,
                   COUNT(DISTINCT mc.id) as category_count
            FROM restaurants r
            JOIN plans p ON r.current_plan_id = p.id
            LEFT JOIN branches b ON r.id = b.restaurant_id
            LEFT JOIN products pr ON r.id = pr.restaurant_id
            LEFT JOIN menu_categories mc ON r.id = mc.restaurant_id
            WHERE r.id = ?
            GROUP BY r.id
        ");
        $stmt->execute([$restaurant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) return false;

        switch ($type) {
            case 'branches':
                return $result['branch_count'] < $result['max_branches'];
            case 'products':
                return $result['product_count'] < $result['max_products'];
            case 'categories':
                return $result['category_count'] < $result['max_categories'];
            default:
                return false;
        }
    } catch (PDOException $e) {
        error_log("Error al verificar límites del plan: " . $e->getMessage());
        return false;
    }
}

/**
 * Maneja la subida de imágenes para productos
 * @param array $file Array $_FILES del archivo a subir
 * @param string $subdirectory Subdirectorio dentro de uploads/ donde guardar la imagen
 * @return array Array con el resultado de la operación
 */
function handleImageUpload($file, $subdirectory = '') {
    try {
        // Validar que el archivo existe y no hay errores
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo: " . ($file['error'] ?? 'Archivo no especificado'));
        }

        // Validar tipo de archivo
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("Tipo de archivo no permitido. Solo se permiten JPG, PNG y GIF");
        }

        // Validar tamaño (5MB máximo)
        $max_size = 5 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            throw new Exception("La imagen no debe superar los 5MB");
        }

        // Crear directorio si no existe
        $upload_dir = __DIR__ . '/../uploads/' . $subdirectory . '/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                throw new Exception("Error al crear el directorio de subida");
            }
        }

        // Generar nombre único para el archivo
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_name = uniqid('product_') . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;

        // Mover el archivo
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception("Error al mover el archivo subido");
        }

        // Verificar si GD está disponible
        if (extension_loaded('gd')) {
            try {
                // Intentar comprimir la imagen si GD está disponible
                $image_info = getimagesize($file_path);
                if ($image_info) {
                    $quality = 80; // Calidad de compresión
                    
                    switch ($image_info[2]) {
                        case IMAGETYPE_JPEG:
                            $image = imagecreatefromjpeg($file_path);
                            if ($image) {
                                imagejpeg($image, $file_path, $quality);
                                imagedestroy($image);
                            }
                            break;
                        case IMAGETYPE_PNG:
                            $image = imagecreatefrompng($file_path);
                            if ($image) {
                                // Preservar transparencia
                                imagealphablending($image, false);
                                imagesavealpha($image, true);
                                // Comprimir PNG (0-9, donde 9 es máxima compresión)
                                imagepng($image, $file_path, 6);
                                imagedestroy($image);
                            }
                            break;
                    }
                }
            } catch (Exception $e) {
                error_log("Error al comprimir imagen: " . $e->getMessage());
                // Continuar con la imagen original si falla la compresión
            }
        }

        // Retornar éxito
        return [
            'success' => true,
            'filename' => $subdirectory . '/' . $file_name,
            'message' => 'Imagen subida exitosamente'
        ];

    } catch (Exception $e) {
        error_log("Error en handleImageUpload: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Obtiene el color del badge según el estado de la orden
 * @param string $status Estado de la orden
 * @return string Clase de color para el badge
 */
function getOrderStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'preparing' => 'primary',
        'ready' => 'success',
        'delivered' => 'secondary',
        'cancelled' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

/**
 * Requiere que el usuario esté logueado como el tipo especificado
 * @param string $type Tipo de usuario ('restaurant' o 'super_admin')
 * @return void
 */
function requireLogin($type = 'restaurant') {
    session_start();
    
    if ($type === 'super_admin') {
        if (!isset($_SESSION['super_admin_id'])) {
            redirect(BASE_URL . '/super_admin/login.php');
        }
    } else {
        if (!isset($_SESSION['restaurant_id'])) {
            redirect(BASE_URL . '/restaurante/login.php');
        }
    }
}

// ========== FUNCIONES DE SEGURIDAD ADICIONALES ==========

/**
 * Función para verificar rate limiting
 */
function checkRateLimit($ip, $action = 'general', $max_attempts = 5, $time_window = 3600) {
    global $conn;
    
    try {
        // Limpiar registros antiguos
        $stmt = $conn->prepare("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$time_window]);
        
        // Verificar intentos recientes
        $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM rate_limits WHERE ip_address = ? AND action = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$ip, $action, $time_window]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['attempts'] >= $max_attempts) {
            return false; // Demasiados intentos
        }
        
        // Registrar intento
        $stmt = $conn->prepare("INSERT INTO rate_limits (ip_address, action, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$ip, $action]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error en rate limiting: " . $e->getMessage());
        return true; // En caso de error, permitir
    }
}

/**
 * Función para verificar si una cuenta está bloqueada
 */
function isAccountLocked($restaurant_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT account_locked_until FROM restaurants WHERE id = ?");
        $stmt->execute([$restaurant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['account_locked_until']) {
            return strtotime($result['account_locked_until']) > time();
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error verificando bloqueo de cuenta: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para registrar intento fallido de login
 */
function recordFailedLogin($email) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            UPDATE restaurants 
            SET failed_login_attempts = failed_login_attempts + 1,
                last_failed_login = NOW(),
                account_locked_until = CASE 
                    WHEN failed_login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 1 HOUR)
                    ELSE account_locked_until
                END
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        
        // Registrar en logs de seguridad
        logSecurityEvent(null, 'failed_login', "Intento fallido de login para email: $email");
        
    } catch (Exception $e) {
        error_log("Error registrando intento fallido: " . $e->getMessage());
    }
}

/**
 * Función para resetear intentos fallidos de login
 */
function resetFailedLoginAttempts($restaurant_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            UPDATE restaurants 
            SET failed_login_attempts = 0,
                last_failed_login = NULL,
                account_locked_until = NULL
            WHERE id = ?
        ");
        $stmt->execute([$restaurant_id]);
        
    } catch (Exception $e) {
        error_log("Error reseteando intentos fallidos: " . $e->getMessage());
    }
}

/**
 * Función para registrar evento de seguridad
 */
function logSecurityEvent($restaurant_id, $action, $details = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO security_logs (restaurant_id, ip_address, user_agent, action, details) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $restaurant_id,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $action,
            $details
        ]);
    } catch (Exception $e) {
        error_log("Error registrando evento de seguridad: " . $e->getMessage());
    }
}

/**
 * Función para registrar sesión activa
 */
function registerActiveSession($restaurant_id, $session_id) {
    global $conn;
    
    try {
        // Eliminar sesión anterior si existe
        $stmt = $conn->prepare("DELETE FROM active_sessions WHERE session_id = ?");
        $stmt->execute([$session_id]);
        
        // Registrar nueva sesión
        $stmt = $conn->prepare("
            INSERT INTO active_sessions (restaurant_id, session_id, ip_address, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $restaurant_id,
            $session_id,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
    } catch (Exception $e) {
        error_log("Error registrando sesión activa: " . $e->getMessage());
    }
}

/**
 * Función para verificar sesión válida
 */
function validateSession($session_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT * FROM active_sessions 
            WHERE session_id = ? 
            AND last_activity > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$session_id]);
        
        if ($stmt->rowCount() > 0) {
            // Actualizar última actividad
            $stmt = $conn->prepare("UPDATE active_sessions SET last_activity = NOW() WHERE session_id = ?");
            $stmt->execute([$session_id]);
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error validando sesión: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para cerrar sesión de forma segura
 */
function secureLogout($session_id = null) {
    global $conn;
    
    if (!$session_id) {
        $session_id = session_id();
    }
    
    try {
        // Eliminar de sesiones activas
        $stmt = $conn->prepare("DELETE FROM active_sessions WHERE session_id = ?");
        $stmt->execute([$session_id]);
        
        // Destruir sesión
        session_destroy();
        
        // Eliminar cookies de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
    } catch (Exception $e) {
        error_log("Error en logout seguro: " . $e->getMessage());
    }
}

/**
 * Función para validar contraseña fuerte
 */
function validateStrongPassword($password) {
    $min_length = 8;
    $has_lower = preg_match('/[a-z]/', $password);
    $has_upper = preg_match('/[A-Z]/', $password);
    $has_number = preg_match('/\d/', $password);
    $has_special = preg_match('/[@$!%*?&]/', $password);
    
    return strlen($password) >= $min_length && $has_lower && $has_upper && $has_number && $has_special;
}

/**
 * Función para obtener IP real del cliente
 */
function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Función para sanitizar datos de entrada
 */
function sanitizeInput($data, $type = 'string') {
    switch ($type) {
        case 'email':
            return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var(trim($data), FILTER_SANITIZE_URL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'string':
        default:
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Función para validar token de recordarme
 */
function validateRememberToken($token) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT rt.*, r.id as restaurant_id, r.name, r.slug 
            FROM remember_tokens rt
            JOIN restaurants r ON rt.restaurant_id = r.id
            WHERE rt.token = ? AND rt.expires_at > NOW() AND r.is_active = 1
        ");
        $stmt->execute([$token]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error validando token de recordarme: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para generar token de recordarme
 */
function generateRememberToken($restaurant_id) {
    global $conn;
    
    try {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 días
        
        $stmt = $conn->prepare("
            INSERT INTO remember_tokens (restaurant_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$restaurant_id, $token, $expires]);
        
        return $token;
    } catch (Exception $e) {
        error_log("Error generando token de recordarme: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para limpiar datos antiguos de seguridad
 */
function cleanupSecurityData() {
    global $conn;
    
    try {
        // Limpiar rate limits antiguos
        $stmt = $conn->prepare("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        
        // Limpiar sesiones inactivas
        $stmt = $conn->prepare("DELETE FROM active_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        
        // Limpiar tokens expirados
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        
        // Limpiar logs antiguos
        $stmt = $conn->prepare("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        
        // Desbloquear cuentas
        $stmt = $conn->prepare("
            UPDATE restaurants 
            SET account_locked_until = NULL 
            WHERE account_locked_until IS NOT NULL 
            AND account_locked_until < NOW()
        ");
        $stmt->execute();
        
        return true;
    } catch (Exception $e) {
        error_log("Error limpiando datos de seguridad: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para obtener los decimales de una moneda específica
 */
function getCurrencyDecimals($currency_code) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT decimals FROM currencies WHERE code = ?");
        $stmt->execute([$currency_code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['decimals'] ?? 0;
    } catch (Exception $e) {
        return 0; // Fallback
    }
}
?>
