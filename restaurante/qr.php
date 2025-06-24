<?php
// Habilitar reporte de errores para diagnóstico
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar si las extensiones necesarias están cargadas
$missing_extensions = [];
if (!extension_loaded('pdo_mysql')) {
    $missing_extensions[] = 'pdo_mysql';
}
if (!extension_loaded('curl')) {
    $missing_extensions[] = 'curl';
}

if (!empty($missing_extensions)) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; margin: 20px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>Error de Configuración de PHP</h3>";
    echo "<p>Las siguientes extensiones de PHP no están cargadas:</p>";
    echo "<ul>";
    foreach ($missing_extensions as $ext) {
        echo "<li><strong>$ext</strong></li>";
    }
    echo "</ul>";
    echo "<p><strong>Solución:</strong> Habilitar estas extensiones en el archivo php.ini de XAMPP:</p>";
    echo "<ol>";
    echo "<li>Abrir XAMPP Control Panel</li>";
    echo "<li>Hacer clic en 'Config' → 'PHP (php.ini)'</li>";
    echo "<li>Buscar y descomentar las líneas: extension=pdo_mysql y extension=curl</li>";
    echo "<li>Reiniciar Apache</li>";
    echo "</ol>";
    echo "</div>";
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/functions.php';
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; margin: 20px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>Error al Cargar Archivos de Configuración</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    exit;
}

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    redirect(BASE_URL . '/restaurante/login.php');
}

// Verificar si el perfil está completo
try {
    $stmt = $conn->prepare("SELECT profile_completed, slug FROM restaurants WHERE id = ?");
    $stmt->execute([$_SESSION['restaurant_id']]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$restaurant['profile_completed']) {
        redirect(BASE_URL . '/restaurante/completar-perfil.php');
    }
    
    // Guardar el slug en la sesión si no existe
    if (!isset($_SESSION['restaurant_slug'])) {
        $_SESSION['restaurant_slug'] = $restaurant['slug'];
    }
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; margin: 20px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>Error de Base de Datos</h3>";
    echo "<p>Error al verificar el estado del perfil: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    exit;
}

$error = '';
$success = '';

// Procesar generación de QR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Error de seguridad. Por favor, intenta nuevamente.';
    } else {
        try {
            switch ($_POST['action']) {
                case 'generate_qr':
                    // Verificar si ya existe un QR para este restaurante
                    $stmt = $conn->prepare("SELECT id FROM qr_codes WHERE restaurant_id = ?");
                    $stmt->execute([$_SESSION['restaurant_id']]);
                    $existing_qr = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_qr) {
                        $error = 'Ya existe un código QR para este restaurante. Puedes descargarlo o generar uno nuevo.';
                    } else {
                        // Verificar que el directorio de uploads existe y tiene permisos
                        if (!is_dir(UPLOAD_PATH)) {
                            if (!mkdir(UPLOAD_PATH, 0777, true)) {
                                throw new Exception("No se pudo crear el directorio de uploads");
                            }
                        }
                        
                        if (!is_writable(UPLOAD_PATH)) {
                            throw new Exception("El directorio de uploads no tiene permisos de escritura");
                        }
                        
                        // Crear directorio para códigos QR si no existe
                        $qr_dir = UPLOAD_PATH . 'qr_codes';
                        if (!is_dir($qr_dir)) {
                            if (!mkdir($qr_dir, 0777, true)) {
                                throw new Exception("No se pudo crear el directorio para códigos QR");
                            }
                        }
                        
                        // Generar URL única para el menú
                        $menu_url = BASE_URL . '/' . $_SESSION['restaurant_slug'];
                        
                        // Verificar si la URL ya existe
                        $stmt = $conn->prepare("SELECT id FROM qr_codes WHERE menu_url = ?");
                        $stmt->execute([$menu_url]);
                        if ($stmt->fetch()) {
                            throw new Exception("Ya existe un código QR con esta URL. Por favor, intenta regenerar el QR.");
                        }
                        
                        // Generar código QR usando QR Server API
                        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($menu_url) . '&format=png';
                        
                        // Guardar QR como imagen
                        $qr_filename = 'qr_' . $_SESSION['restaurant_id'] . '_' . time() . '.png';
                        $qr_path = 'qr_codes/' . $qr_filename;
                        
                        try {
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $qr_url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            $qr_image = curl_exec($ch);
                            
                            if (curl_errno($ch)) {
                                throw new Exception("Error al obtener el código QR: " . curl_error($ch));
                            }
                            
                            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            if ($http_code !== 200) {
                                throw new Exception("Error al obtener el código QR. Código HTTP: " . $http_code);
                            }
                            
                            curl_close($ch);
                            
                            if ($qr_image === false) {
                                throw new Exception("No se pudo obtener el código QR");
                            }
                            if (file_put_contents(UPLOAD_PATH . $qr_path, $qr_image) === false) {
                                throw new Exception("No se pudo guardar el código QR");
                            }
                        } catch (Exception $e) {
                            throw new Exception("Error al guardar el código QR: " . $e->getMessage());
                        }
                        
                        // Guardar en la base de datos
                        $stmt = $conn->prepare("
                            INSERT INTO qr_codes (restaurant_id, menu_url, qr_image, created_at)
                            VALUES (?, ?, ?, NOW())
                        ");
                        $stmt->execute([$_SESSION['restaurant_id'], $menu_url, $qr_path]);
                        
                        logActivity($_SESSION['restaurant_id'], 'qr_generate', "Código QR generado");
                        $success = 'Código QR generado exitosamente';
                    }
                    break;
                    
                case 'regenerate_qr':
                    // Obtener URL actual
                    $stmt = $conn->prepare("SELECT menu_url FROM qr_codes WHERE restaurant_id = ?");
                    $stmt->execute([$_SESSION['restaurant_id']]);
                    $current_qr = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$current_qr) {
                        $error = 'No se encontró un código QR existente';
                    } else {
                        // Actualizar la URL del menú
                        $menu_url = BASE_URL . '/' . $_SESSION['restaurant_slug'];
                        
                        // Generar nuevo código QR usando QR Server API
                        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($menu_url) . '&format=png';
                        
                        // Eliminar QR anterior
                        $stmt = $conn->prepare("SELECT qr_image FROM qr_codes WHERE restaurant_id = ?");
                        $stmt->execute([$_SESSION['restaurant_id']]);
                        $old_qr = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($old_qr && file_exists(UPLOAD_PATH . $old_qr['qr_image'])) {
                            unlink(UPLOAD_PATH . $old_qr['qr_image']);
                        }
                        
                        // Guardar nuevo QR
                        $qr_filename = 'qr_' . $_SESSION['restaurant_id'] . '_' . time() . '.png';
                        $qr_path = 'qr_codes/' . $qr_filename;
                        
                        try {
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $qr_url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            $qr_image = curl_exec($ch);
                            
                            if (curl_errno($ch)) {
                                throw new Exception("Error al obtener el código QR: " . curl_error($ch));
                            }
                            
                            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            if ($http_code !== 200) {
                                throw new Exception("Error al obtener el código QR. Código HTTP: " . $http_code);
                            }
                            
                            curl_close($ch);
                            
                            if ($qr_image === false) {
                                throw new Exception("No se pudo obtener el código QR");
                            }
                            if (file_put_contents(UPLOAD_PATH . $qr_path, $qr_image) === false) {
                                throw new Exception("No se pudo guardar el código QR");
                            }
                        } catch (Exception $e) {
                            throw new Exception("Error al guardar el código QR: " . $e->getMessage());
                        }
                        
                        // Actualizar en la base de datos
                        $stmt = $conn->prepare("
                            UPDATE qr_codes 
                            SET qr_image = ?, updated_at = NOW()
                            WHERE restaurant_id = ?
                        ");
                        $stmt->execute([$qr_path, $_SESSION['restaurant_id']]);
                        
                        logActivity($_SESSION['restaurant_id'], 'qr_regenerate', "Código QR regenerado");
                        $success = 'Código QR regenerado exitosamente';
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log("Error en operación de QR: " . $e->getMessage());
            $error = 'Error al procesar la operación: ' . $e->getMessage();
        }
    }
}

// Obtener código QR actual
try {
    // Verificar conexión a la base de datos
    if (!$conn) {
        throw new Exception("No hay conexión a la base de datos");
    }

    // Verificar sesión
    if (!isset($_SESSION['restaurant_id'])) {
        throw new Exception("No hay sesión de restaurante activa");
    }

    // Debug: Imprimir información de la sesión
    error_log("Intentando obtener QR para restaurante ID: " . $_SESSION['restaurant_id']);

    // Primero verificar si el restaurante existe y está activo
    $stmt = $conn->prepare("SELECT id, name, slug FROM restaurants WHERE id = ? AND is_active = 1");
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta del restaurante: " . $conn->errorInfo()[2]);
    }
    
    $stmt->execute([$_SESSION['restaurant_id']]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$restaurant) {
        error_log("Restaurante no encontrado o inactivo: " . $_SESSION['restaurant_id']);
        throw new PDOException("No se encontró el restaurante o no está activo");
    }

    error_log("Restaurante encontrado: " . $restaurant['name']);

    // Verificar que el directorio de uploads existe
    if (!defined('UPLOAD_PATH')) {
        throw new Exception("La constante UPLOAD_PATH no está definida");
    }

    if (!is_dir(UPLOAD_PATH)) {
        error_log("Directorio de uploads no existe: " . UPLOAD_PATH);
        throw new Exception("El directorio de uploads no existe: " . UPLOAD_PATH);
    }

    if (!is_writable(UPLOAD_PATH)) {
        error_log("Directorio de uploads no tiene permisos de escritura: " . UPLOAD_PATH);
        throw new Exception("El directorio de uploads no tiene permisos de escritura: " . UPLOAD_PATH);
    }

    // Luego obtener el código QR
    $stmt = $conn->prepare("
        SELECT q.*, r.name as restaurant_name, r.slug
        FROM qr_codes q
        JOIN restaurants r ON q.restaurant_id = r.id
        WHERE q.restaurant_id = ?
    ");
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta del QR: " . $conn->errorInfo()[2]);
    }

    $stmt->execute([$_SESSION['restaurant_id']]);
    $qr_code = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("Consulta QR ejecutada. Resultado: " . ($qr_code ? "QR encontrado" : "No hay QR"));

    // Actualizar la URL del menú en el registro del QR si es necesario
    if ($qr_code) {
        $new_menu_url = BASE_URL . '/' . $qr_code['slug'];
        if ($qr_code['menu_url'] !== $new_menu_url) {
            $update_stmt = $conn->prepare("UPDATE qr_codes SET menu_url = ? WHERE id = ?");
            $update_stmt->execute([$new_menu_url, $qr_code['id']]);
            $qr_code['menu_url'] = $new_menu_url;
        }
    }

    // Verificar si el archivo QR existe físicamente
    if ($qr_code) {
        $qr_file_path = UPLOAD_PATH . $qr_code['qr_image'];
        error_log("Verificando archivo QR en: " . $qr_file_path);
        
        if (!file_exists($qr_file_path)) {
            error_log("Archivo QR no encontrado: " . $qr_file_path);
            // Si el archivo no existe, eliminar el registro de la base de datos
            $stmt = $conn->prepare("DELETE FROM qr_codes WHERE restaurant_id = ?");
            $stmt->execute([$_SESSION['restaurant_id']]);
            $qr_code = null;
            $error = "El archivo del código QR no se encontró. Por favor, genera uno nuevo.";
        } else if (!is_readable($qr_file_path)) {
            error_log("Archivo QR no es legible: " . $qr_file_path);
            throw new Exception("El archivo del código QR existe pero no se puede leer");
        } else {
            error_log("Archivo QR encontrado y es legible");
        }
    }

} catch (PDOException $e) {
    error_log("Error PDO al obtener código QR: " . $e->getMessage());
    error_log("Código de error: " . $e->getCode());
    error_log("Trace: " . $e->getTraceAsString());
    $error = "Error al cargar el código QR. Detalles: " . $e->getMessage();
    $qr_code = null;
} catch (Exception $e) {
    error_log("Error general al verificar QR: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    $error = $e->getMessage();
    $qr_code = null;
}

// Debug: Imprimir información de error si existe
if ($error) {
    error_log("Error final en qr.php: " . $error);
}

// Generar token CSRF
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Código QR - Tumenufast</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #ffa500;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            padding: 20px;
        }
        
        .qr-container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 40px;
        }
        
        .qr-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .qr-header h1 {
            color: var(--dark-color);
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .qr-header p {
            color: #666;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .qr-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
        }
        
        .qr-image-container {
            background: var(--light-color);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .qr-image {
            max-width: 300px;
            height: auto;
            margin-bottom: 20px;
        }
        
        .qr-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn-qr {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 500;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-qr:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
            color: white;
        }
        
        .btn-qr.secondary {
            background: var(--light-color);
            color: var(--dark-color);
        }
        
        .btn-qr.secondary:hover {
            background: #e9ecef;
            color: var(--dark-color);
        }
        
        .qr-info {
            background: var(--light-color);
            padding: 20px;
            border-radius: 15px;
            width: 100%;
            max-width: 500px;
        }
        
        .qr-info h3 {
            color: var(--dark-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: #666;
        }
        
        .info-item i {
            color: var(--primary-color);
            width: 20px;
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .qr-steps {
            background: var(--light-color);
            padding: 30px;
            border-radius: 15px;
            margin-top: 40px;
        }
        
        .qr-steps h2 {
            color: var(--dark-color);
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .steps-list {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            gap: 20px;
            margin-top: 20px;
        }
        
        .step-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            flex: 1;
            min-width: 200px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .step-item:hover {
            transform: translateY(-5px);
        }
        
        .step-number {
            background: var(--primary-color);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 auto 15px;
        }
        
        .step-item h3 {
            color: var(--dark-color);
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        .step-item p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }

        @media (max-width: 768px) {
            .steps-list {
                flex-direction: column;
            }
            
            .step-item {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="qr-container">
        <div class="qr-header">
            <h1>Tu Código QR</h1>
            <p>Genera y gestiona el código QR que tus clientes escanearán para ver tu menú digital.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <?php if (strpos($error, "no se encontró") !== false): ?>
                    <div class="mt-3">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="generate_qr">
                            <button type="submit" class="btn-qr">
                                <i class="fas fa-qrcode"></i>
                                Generar Nuevo Código QR
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="qr-content">
            <?php if ($qr_code): ?>
                <div class="qr-image-container">
                    <img src="<?php echo BASE_URL . '/uploads/' . $qr_code['qr_image']; ?>" 
                         alt="Código QR del Menú" class="qr-image">
                    
                    <div class="qr-actions">
                        <a href="<?php echo BASE_URL . '/uploads/' . $qr_code['qr_image']; ?>" 
                           download="menu-qr-<?php echo $qr_code['restaurant_name']; ?>.png" 
                           class="btn-qr">
                            <i class="fas fa-download"></i>
                            Descargar QR
                        </a>
                        
                        <button type="button" class="btn-qr" onclick="shareQR()">
                            <i class="fas fa-share-alt"></i>
                            Compartir QR
                        </button>
                        
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="regenerate_qr">
                            <button type="submit" class="btn-qr secondary">
                                <i class="fas fa-sync-alt"></i>
                                Regenerar QR
                            </button>
                        </form>
                    </div>

                    <div class="share-buttons mt-3" style="display: none;" id="shareButtons">
                        <h6 class="mb-2">Compartir en:</h6>
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="https://wa.me/?text=<?php echo urlencode('¡Visita nuestro menú digital! ' . $qr_code['menu_url']); ?>" 
                               target="_blank" class="btn btn-success btn-sm">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($qr_code['menu_url']); ?>" 
                               target="_blank" class="btn btn-primary btn-sm">
                                <i class="fab fa-facebook"></i> Facebook
                            </a>
                            <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('¡Visita nuestro menú digital! ' . $qr_code['menu_url']); ?>" 
                               target="_blank" class="btn btn-info btn-sm">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="qr-info">
                    <h3>Información del Código QR</h3>
                    
                    <div class="info-item">
                        <i class="fas fa-link"></i>
                        <span>URL del Menú: <a href="<?php echo htmlspecialchars($qr_code['menu_url']); ?>" target="_blank">
                            <?php echo htmlspecialchars($qr_code['menu_url']); ?>
                        </a></span>
                    </div>
                    
                    <div class="info-item">
                        <i class="fas fa-calendar"></i>
                        <span>Generado: <?php echo formatDate($qr_code['created_at']); ?></span>
                    </div>
                    
                    <?php if ($qr_code['updated_at']): ?>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <span>Última actualización: <?php echo formatDate($qr_code['updated_at']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <p class="mb-4">Aún no has generado un código QR para tu menú.</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="generate_qr">
                        <button type="submit" class="btn-qr">
                            <i class="fas fa-qrcode"></i>
                            Generar Código QR
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="qr-steps">
            <h2>¿Cómo usar tu Código QR?</h2>
            
            <div class="steps-list">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <h3>Descarga el QR</h3>
                    <p>Descarga el código QR en formato PNG para imprimirlo o compartirlo digitalmente.</p>
                </div>
                
                <div class="step-item">
                    <div class="step-number">2</div>
                    <h3>Colócalo en tu Restaurante</h3>
                    <p>Imprime el código QR y colócalo en mesas, paredes o cualquier lugar visible para tus clientes.</p>
                </div>
                
                <div class="step-item">
                    <div class="step-number">3</div>
                    <h3>¡Listo!</h3>
                    <p>Tus clientes podrán escanear el código QR con su teléfono para ver tu menú digital.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareQR() {
            const shareButtons = document.getElementById('shareButtons');
            if (shareButtons.style.display === 'none') {
                shareButtons.style.display = 'block';
            } else {
                shareButtons.style.display = 'none';
            }
        }

        // Cerrar botones de compartir al hacer clic fuera
        document.addEventListener('click', function(event) {
            const shareButtons = document.getElementById('shareButtons');
            const shareButton = document.querySelector('.btn-qr[onclick="shareQR()"]');
            
            if (!shareButtons.contains(event.target) && !shareButton.contains(event.target)) {
                shareButtons.style.display = 'none';
            }
        });
    </script>
</body>
</html> 
