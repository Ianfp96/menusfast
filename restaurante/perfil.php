<?php
// Prevenir errores de headers already sent
ob_start();

// Configuración de sesión ANTES de iniciar la sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Iniciar sesión al principio para evitar errores de headers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    redirect(BASE_URL . '/restaurante/login.php');
}

$restaurant_id = $_SESSION['restaurant_id'];
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';

// Limpiar mensajes de sesión después de usarlos
unset($_SESSION['message'], $_SESSION['error']);

// Obtener datos actuales del restaurante
try {
    // Obtener información del restaurante
    $stmt = $conn->prepare("
        SELECT r.*, 
               COALESCE(p.name, 'Plan Básico') as plan_name, 
               COALESCE(p.max_categories, 5) as max_categories,
               COALESCE(p.max_products, 20) as max_products,
               COALESCE((SELECT COUNT(*) FROM menu_categories WHERE restaurant_id = r.id), 0) as current_categories,
               COALESCE((SELECT COUNT(*) FROM products WHERE restaurant_id = r.id), 0) as current_products
        FROM restaurants r
        LEFT JOIN plans p ON r.current_plan_id = p.id
        WHERE r.id = ?
    ");
    $stmt->execute([$_SESSION['restaurant_id']]);
    $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$restaurant) {
        redirect(BASE_URL . '/restaurante/logout.php');
    }

    // Asegurar que todas las claves necesarias existan con valores por defecto
    $restaurant = array_merge([
        'plan_name' => 'Plan Básico',
        'max_categories' => 5,
        'max_products' => 20,
        'current_categories' => 0,
        'current_products' => 0,
        'current_plan_id' => null
    ], $restaurant);

    // Obtener categorías y productos
    $stmt = $conn->prepare("
        SELECT mc.*, COUNT(p.id) as product_count 
        FROM menu_categories mc
        LEFT JOIN products p ON mc.id = p.category_id
        WHERE mc.restaurant_id = ?
        GROUP BY mc.id
        ORDER BY mc.sort_order ASC
    ");
    $stmt->execute([$restaurant_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener productos por categoría
    $products_by_category = [];
    foreach ($categories as $category) {
        $stmt = $conn->prepare("
            SELECT * FROM products 
            WHERE restaurant_id = ? AND category_id = ?
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt->execute([$restaurant_id, $category['id']]);
        $products_by_category[$category['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener monedas disponibles
    $stmt = $conn->prepare("SELECT * FROM currencies WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al obtener datos del restaurante: " . $e->getMessage());
    die("Error al cargar los datos del restaurante");
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        error_log("Error CSRF - Token recibido: " . $csrf_token);
        error_log("Token esperado: " . $_SESSION['csrf_token'] ?? 'no definido');
        $_SESSION['error'] = 'Error de seguridad. Por favor, recarga la página e intenta nuevamente.';
        redirect(BASE_URL . '/restaurante/perfil.php?tab=' . ($_POST['action'] ?? 'profile'));
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_profile':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $facebook_url = trim($_POST['facebook_url'] ?? '');
                $instagram_url = trim($_POST['instagram_url'] ?? '');
                $tiktok_url = trim($_POST['tiktok_url'] ?? '');
                $whatsapp_url = trim($_POST['whatsapp_url'] ?? '');
                // Guardar exactamente lo que el usuario ingresa, sin agregar prefijos automáticamente
                $whatsapp_store = trim($_POST['whatsapp_store'] ?? '');
                $banner_color = trim($_POST['banner_color'] ?? '#8e8d91');
                $color_web = trim($_POST['color_web'] ?? '#00b894');
                $is_open = isset($_POST['is_open']) ? 1 : 0;
                $has_delivery = isset($_POST['has_delivery']) ? 1 : 0;
                $has_physical_store = isset($_POST['has_physical_store']) ? 1 : 0;
                $show_featured_products = isset($_POST['show_featured_products']) ? 1 : 0;
                
                // Validaciones
                if (empty($name)) {
                    $_SESSION['error'] = 'El nombre del restaurante es obligatorio';
                } else {
                    try {
                        // Manejar upload de logo
                        $logo_path = $restaurant['logo'];
                        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                            $logo_path = uploadFile($_FILES['logo'], 'restaurants/logos');
                            if (!$logo_path) {
                                $error = 'Error al subir el logo';
                            }
                        }
                        
                        // Manejar upload de banner
                        $banner_path = $restaurant['banner'];
                        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
                            $banner_path = uploadFile($_FILES['banner'], 'restaurants/banners');
                            if (!$banner_path) {
                                $error = 'Error al subir el banner';
                            }
                        }
                        
                        if (!$error) {
                            $stmt = $conn->prepare("
                                UPDATE restaurants SET 
                                name = ?, 
                                description = ?, 
                                phone = ?, 
                                address = ?, 
                                logo = ?, 
                                banner = ?, 
                                is_open = ?,
                                has_delivery = ?,
                                has_physical_store = ?,
                                facebook_url = ?,
                                instagram_url = ?,
                                tiktok_url = ?,
                                whatsapp_url = ?,
                                whatsapp_store = ?,
                                banner_color = ?,
                                color_web = ?,
                                show_featured_products = ?,
                                updated_at = NOW()
                                WHERE id = ?
                            ");
                            
                            if ($stmt->execute([
                                $name,
                                $description,
                                $phone,
                                $address,
                                $logo_path,
                                $banner_path,
                                $is_open,
                                $has_delivery,
                                $has_physical_store,
                                $facebook_url,
                                $instagram_url,
                                $tiktok_url,
                                $whatsapp_url,
                                $whatsapp_store,
                                $banner_color,
                                $color_web,
                                $show_featured_products,
                                $restaurant_id
                            ])) {
                                // Marcar el perfil como completado después de una actualización exitosa
                                $stmt = $conn->prepare("UPDATE restaurants SET profile_completed = 1 WHERE id = ?");
                                $stmt->execute([$restaurant_id]);
                                
                                $_SESSION['message'] = 'Perfil actualizado correctamente';
                                $_SESSION['restaurant_name'] = $name;
                                
                                // Recargar datos del restaurante
                                $stmt = $conn->prepare("SELECT * FROM restaurants WHERE id = ?");
                                $stmt->execute([$restaurant_id]);
                                $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
                            } else {
                                $_SESSION['error'] = 'Error al actualizar el perfil';
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error al actualizar perfil: " . $e->getMessage());
                        $_SESSION['error'] = 'Error al actualizar el perfil. Por favor, intenta más tarde.';
                    }
                }
                redirect(BASE_URL . '/restaurante/perfil.php?tab=profile');
                break;

            case 'update_hours':
                // Procesar horarios
                $opening_hours = [];
                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                $day_names = [
                    'monday' => 'Lunes',
                    'tuesday' => 'Martes', 
                    'wednesday' => 'Miércoles',
                    'thursday' => 'Jueves',
                    'friday' => 'Viernes',
                    'saturday' => 'Sábado',
                    'sunday' => 'Domingo'
                ];
                
                $error = null;
                
                foreach ($days as $day) {
                    $is_open_day = isset($_POST[$day . '_open']);
                    $open_time = trim($_POST[$day . '_open_time'] ?? '');
                    $close_time = trim($_POST[$day . '_close_time'] ?? '');
                    
                    // Validar horarios
                    if ($is_open_day) {
                        if (empty($open_time) || empty($close_time)) {
                            $error = "Por favor, ingresa los horarios de apertura y cierre para " . $day_names[$day];
                            break;
                        }
                        
                        // Convertir a objetos DateTime para validación
                        $open_dt = DateTime::createFromFormat('H:i', $open_time);
                        $close_dt = DateTime::createFromFormat('H:i', $close_time);
                        
                        if (!$open_dt || !$close_dt) {
                            $error = "El formato de hora para " . $day_names[$day] . " no es válido";
                            break;
                        }
                        
                        // Validar que la hora de cierre sea posterior a la de apertura
                        // (excepto para horarios que cruzan la medianoche)
                        if ($close_time < $open_time && $close_time !== '00:00') {
                            $error = "La hora de cierre debe ser posterior a la hora de apertura para " . $day_names[$day];
                            break;
                        }
                    }
                    
                    $opening_hours[$day] = [
                        'name' => $day_names[$day],
                        'is_open' => $is_open_day,
                        'open_time' => $is_open_day ? $open_time : '',
                        'close_time' => $is_open_day ? $close_time : ''
                    ];
                }
                
                if (!$error) {
                    $opening_hours_json = json_encode($opening_hours);
                    
                    try {
                        $stmt = $conn->prepare("UPDATE restaurants SET opening_hours = ?, updated_at = NOW() WHERE id = ?");
                        
                        if ($stmt->execute([$opening_hours_json, $restaurant_id])) {
                            // Marcar el perfil como completado después de una actualización exitosa
                            $stmt = $conn->prepare("UPDATE restaurants SET profile_completed = 1 WHERE id = ?");
                            $stmt->execute([$restaurant_id]);
                            
                            $_SESSION['message'] = 'Horarios actualizados correctamente';
                            
                            // Recargar datos del restaurante
                            $stmt = $conn->prepare("SELECT * FROM restaurants WHERE id = ?");
                            $stmt->execute([$restaurant_id]);
                            $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
                        } else {
                            $_SESSION['error'] = 'Error al actualizar los horarios';
                        }
                    } catch (Exception $e) {
                        error_log("Error al actualizar horarios: " . $e->getMessage());
                        $_SESSION['error'] = 'Error al actualizar los horarios. Por favor, intenta más tarde.';
                    }
                } else {
                    $_SESSION['error'] = $error;
                }
                
                redirect(BASE_URL . '/restaurante/perfil.php?tab=hours');
                break;

            case 'add_category':
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                
                if (empty($name)) {
                    $error = 'El nombre de la categoría es requerido';
                } else {
                    try {
                        // Verificar el plan y límites
                        $stmt = $conn->prepare("
                            SELECT p.max_categories, 
                                   (SELECT COUNT(*) FROM menu_categories WHERE restaurant_id = ?) as current_categories
                            FROM restaurants r
                            JOIN plans p ON r.current_plan_id = p.id
                            WHERE r.id = ?
                        ");
                        $stmt->execute([$restaurant_id, $restaurant_id]);
                        $limits = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$limits) {
                            throw new Exception("No se pudo verificar los límites del plan");
                        }
                        
                        if ($limits['current_categories'] >= $limits['max_categories']) {
                            $error = "Has alcanzado el límite de categorías permitidas en tu plan actual";
                        } else {
                            // Obtener el siguiente sort_order
                            $stmt = $conn->prepare("
                                SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order 
                                FROM menu_categories 
                                WHERE restaurant_id = ?
                            ");
                            $stmt->execute([$restaurant_id]);
                            $next_order = $stmt->fetchColumn();
                            
                            // Insertar la categoría
                            $stmt = $conn->prepare("
                                INSERT INTO menu_categories 
                                (restaurant_id, name, description, sort_order, is_active) 
                                VALUES (?, ?, ?, ?, TRUE)
                            ");
                            
                            if ($stmt->execute([$restaurant_id, $name, $description, $next_order])) {
                                $message = 'Categoría agregada exitosamente';
                                logActivity($restaurant_id, 'category_add', "Categoría agregada: $name");
                                // Recargar la página para mostrar la nueva categoría
                                redirect(BASE_URL . '/restaurante/perfil.php?tab=menu');
                            } else {
                                throw new Exception("Error al insertar la categoría");
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error al agregar categoría: " . $e->getMessage());
                        $error = 'Error al agregar la categoría: ' . $e->getMessage();
                    }
                }
                break;

            case 'edit_category':
                $category_id = (int)($_POST['category_id'] ?? 0);
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                
                if (empty($name)) {
                    $error = 'El nombre de la categoría es requerido';
                } else {
                    try {
                        $stmt = $conn->prepare("
                            UPDATE menu_categories 
                            SET name = ?, description = ?
                            WHERE id = ? AND restaurant_id = ?
                        ");
                        
                        if ($stmt->execute([$name, $description, $category_id, $restaurant_id])) {
                            $message = 'Categoría actualizada exitosamente';
                            logActivity($restaurant_id, 'category_edit', "Categoría actualizada: $name");
                        }
                    } catch (PDOException $e) {
                        error_log("Error al actualizar categoría: " . $e->getMessage());
                        $error = 'Error al actualizar la categoría';
                    }
                }
                break;

            case 'delete_category':
                $category_id = (int)($_POST['category_id'] ?? 0);
                
                try {
                    // Verificar si hay productos en la categoría
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                    $stmt->execute([$category_id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'No se puede eliminar la categoría porque contiene productos';
                    } else {
                        $stmt = $conn->prepare("DELETE FROM menu_categories WHERE id = ? AND restaurant_id = ?");
                        if ($stmt->execute([$category_id, $restaurant_id])) {
                            $message = 'Categoría eliminada exitosamente';
                            logActivity($restaurant_id, 'category_delete', "Categoría eliminada ID: $category_id");
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Error al eliminar categoría: " . $e->getMessage());
                    $error = 'Error al eliminar la categoría';
                }
                break;

            case 'add_product':
                $category_id = (int)($_POST['category_id'] ?? 0);
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $price = (float)($_POST['price'] ?? 0);
                $is_available = isset($_POST['is_available']) ? 1 : 0;
                
                if (empty($name)) {
                    $error = 'El nombre del producto es requerido';
                } elseif ($price <= 0) {
                    $error = 'El precio debe ser mayor a 0';
                } elseif ($restaurant['current_products'] >= $restaurant['max_products']) {
                    $error = "Has alcanzado el límite de productos permitidos en tu plan actual ({$restaurant['plan_name']})";
                } else {
                    try {
                        // Procesar imagen si se subió una
                        $image_path = null;
                        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                            if (isValidImage($_FILES['image'])) {
                                $image_path = uploadFile($_FILES['image'], 'products');
                                if (!$image_path) {
                                    $error = 'Error al subir la imagen';
                                }
                            } else {
                                $error = 'El archivo de imagen no es válido';
                            }
                        }
                        
                        if (empty($error)) {
                            $stmt = $conn->prepare("
                                INSERT INTO products (restaurant_id, category_id, name, description, price, image, is_available, is_featured)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            if ($stmt->execute([
                                $restaurant_id, $category_id, $name, $description,
                                $price, $image_path, $is_available, isset($_POST['is_featured']) ? 1 : 0
                            ])) {
                                $message = 'Producto agregado exitosamente';
                                logActivity($restaurant_id, 'product_add', "Producto agregado: $name");
                                redirect(BASE_URL . '/restaurante/perfil.php?tab=menu');
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Error al agregar producto: " . $e->getMessage());
                        $error = 'Error al agregar el producto';
                    }
                }
                break;

            case 'edit_product':
                $product_id = (int)($_POST['product_id'] ?? 0);
                $category_id = (int)($_POST['category_id'] ?? 0);
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $price = (float)($_POST['price'] ?? 0);
                $is_available = isset($_POST['is_available']) ? 1 : 0;
                
                if (empty($name)) {
                    $error = 'El nombre del producto es requerido';
                } elseif ($price <= 0) {
                    $error = 'El precio debe ser mayor a 0';
                } else {
                    try {
                        // Obtener imagen actual
                        $stmt = $conn->prepare("SELECT image FROM products WHERE id = ? AND restaurant_id = ?");
                        $stmt->execute([$product_id, $restaurant_id]);
                        $current_image = $stmt->fetchColumn();
                        
                        // Procesar nueva imagen si se subió una
                        $image_path = $current_image;
                        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                            if (isValidImage($_FILES['image'])) {
                                $image_path = uploadFile($_FILES['image'], 'products');
                                if (!$image_path) {
                                    $error = 'Error al subir la imagen';
                                }
                            } else {
                                $error = 'El archivo de imagen no es válido';
                            }
                        }
                        
                        if (empty($error)) {
                            $stmt = $conn->prepare("
                                UPDATE products 
                                SET category_id = ?, name = ?, description = ?, price = ?, 
                                    image = ?, is_available = ?, is_featured = ?
                                WHERE id = ? AND restaurant_id = ?
                            ");
                            
                            if ($stmt->execute([
                                $category_id, $name, $description, $price,
                                $image_path, $is_available, isset($_POST['is_featured']) ? 1 : 0,
                                $product_id, $restaurant_id
                            ])) {
                                $message = 'Producto actualizado exitosamente';
                                logActivity($restaurant_id, 'product_edit', "Producto actualizado: $name");
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Error al actualizar producto: " . $e->getMessage());
                        $error = 'Error al actualizar el producto';
                    }
                }
                break;

            case 'delete_product':
                $product_id = (int)($_POST['product_id'] ?? 0);
                
                try {
                    // Obtener imagen actual para eliminarla
                    $stmt = $conn->prepare("SELECT image FROM products WHERE id = ? AND restaurant_id = ?");
                    $stmt->execute([$product_id, $restaurant_id]);
                    $image_path = $stmt->fetchColumn();
                    
                    $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND restaurant_id = ?");
                    if ($stmt->execute([$product_id, $restaurant_id])) {
                        // Eliminar imagen si existe
                        if ($image_path && file_exists(UPLOAD_PATH . $image_path)) {
                            unlink(UPLOAD_PATH . $image_path);
                        }
                        $message = 'Producto eliminado exitosamente';
                        logActivity($restaurant_id, 'product_delete', "Producto eliminado ID: $product_id");
                    }
                } catch (PDOException $e) {
                    error_log("Error al eliminar producto: " . $e->getMessage());
                    $error = 'Error al eliminar el producto';
                }
                break;

            case 'update_whatsapp_order':
                try {
                    // Log para depuración
                    error_log("POST data: " . print_r($_POST, true));
                    
                    // Validar y sanitizar los datos
                    $enable_whatsapp_order = isset($_POST['enable_whatsapp_order']) ? 1 : 0;
                    $whatsapp_order_number = trim($_POST['whatsapp_order_number'] ?? '');
                    $whatsapp_order_message = trim($_POST['whatsapp_order_message'] ?? '');
                    $include_product_details = isset($_POST['include_product_details']) ? 1 : 0;
                    $include_customer_info = isset($_POST['include_customer_info']) ? 1 : 0;

                    // Log de valores procesados
                    error_log("Valores procesados:");
                    error_log("enable_whatsapp_order: " . $enable_whatsapp_order);
                    error_log("whatsapp_order_number (original): " . $whatsapp_order_number);
                    error_log("include_product_details: " . $include_product_details);
                    error_log("include_customer_info: " . $include_customer_info);

                    // Solo validar el número de WhatsApp si está habilitado
                    if ($enable_whatsapp_order) {
                        // Limpiar el número de cualquier carácter no numérico
                        $whatsapp_order_number = preg_replace('/[^0-9]/', '', $whatsapp_order_number);
                        error_log("whatsapp_order_number (limpio): " . $whatsapp_order_number);
                        
                        if (empty($whatsapp_order_number) || strlen($whatsapp_order_number) < 10 || strlen($whatsapp_order_number) > 15) {
                            error_log("Error de validación - Longitud del número: " . strlen($whatsapp_order_number));
                            throw new Exception('El número de WhatsApp debe tener entre 10 y 15 dígitos (incluyendo prefijo del país)');
                        }
                        
                        // Preparar el número de WhatsApp con el formato correcto
                        $whatsapp_order_number = 'https://wa.me/' . $whatsapp_order_number;
                    } else {
                        // Si está desactivado, limpiar el número
                        $whatsapp_order_number = '';
                    }

                    // Actualizar la base de datos
                    $stmt = $conn->prepare("
                        UPDATE restaurants SET 
                            enable_whatsapp_order = ?,
                            whatsapp_order_number = ?,
                            whatsapp_order_message = ?,
                            include_product_details = ?,
                            include_customer_info = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");

                    if ($stmt->execute([
                        $enable_whatsapp_order,
                        $whatsapp_order_number,
                        $whatsapp_order_message,
                        $include_product_details,
                        $include_customer_info,
                        $restaurant_id
                    ])) {
                        $_SESSION['message'] = 'Configuración de pedidos por WhatsApp actualizada correctamente';
                        
                        // Recargar los datos del restaurante
                        $stmt = $conn->prepare("SELECT * FROM restaurants WHERE id = ?");
                        $stmt->execute([$restaurant_id]);
                        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        throw new PDOException("Error al ejecutar la actualización");
                    }
                } catch (Exception $e) {
                    error_log("Error al actualizar configuración de pedidos por WhatsApp: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al actualizar la configuración: ' . $e->getMessage();
                }
                redirect(BASE_URL . '/restaurante/perfil.php?tab=whatsapp_order');
                break;

            case 'update_currency':
                $currency = trim($_POST['currency'] ?? 'CLP');
                
                // Validar que la moneda existe
                $valid_currencies = array_column($currencies, 'code');
                if (!in_array($currency, $valid_currencies)) {
                    $_SESSION['error'] = 'Moneda no válida';
                    redirect(BASE_URL . '/restaurante/perfil.php?tab=currency');
                }
                
                try {
                    $stmt = $conn->prepare("UPDATE restaurants SET currency = ? WHERE id = ?");
                    $stmt->execute([$currency, $restaurant_id]);
                    
                    $_SESSION['message'] = 'Moneda actualizada correctamente';
                    redirect(BASE_URL . '/restaurante/perfil.php?tab=currency');
                } catch (PDOException $e) {
                    error_log("Error al actualizar moneda: " . $e->getMessage());
                    $_SESSION['error'] = 'Error al actualizar la moneda';
                    redirect(BASE_URL . '/restaurante/perfil.php?tab=currency');
                }
                break;
        }
    }
}

// Decodificar horarios
$opening_hours = json_decode($restaurant['opening_hours'] ?? '[]', true) ?? [];

// Definir días de la semana
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
$day_names = [
    'monday' => 'Lunes',
    'tuesday' => 'Martes', 
    'wednesday' => 'Miércoles',
    'thursday' => 'Jueves',
    'friday' => 'Viernes',
    'saturday' => 'Sábado',
    'sunday' => 'Domingo'
];

// Generar token CSRF
$csrf_token = generateCSRFToken();

// Determinar la pestaña activa
$active_tab = $_GET['tab'] ?? 'profile';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?= htmlspecialchars($restaurant['name']) ?></title>
    
    <!-- Favicon dinámico -->
    <?php if (!empty($restaurant['logo'])): ?>
        <link rel="icon" type="image/x-icon" href="/uploads/restaurants/logos/<?= htmlspecialchars($restaurant['logo']) ?>">
        <link rel="shortcut icon" type="image/x-icon" href="/uploads/restaurants/logos/<?= htmlspecialchars($restaurant['logo']) ?>">
        <link rel="apple-touch-icon" href="/uploads/restaurants/logos/<?= htmlspecialchars($restaurant['logo']) ?>">
    <?php else: ?>
        <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
        <link rel="shortcut icon" type="image/x-icon" href="/assets/img/favicon.ico">
        <link rel="apple-touch-icon" href="/assets/img/favicon.ico">
    <?php endif; ?>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00D4AA;
            --primary-dark: #00b8d4;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --bg-light: #f8fafc;
            --bg-hover: #f1f5f9;
            --border-color: #eef2f7;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            --hover-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        }

        body {
            background-color: #f8fafc;
            color: var(--text-primary);
        }

        .sidebar {
            background: linear-gradient(180deg, #1e293b, #0f172a);
            min-height: 100vh;
            color: white;
            box-shadow: 4px 0 12px rgba(0, 0, 0, 0.1);
        }

        .sidebar .nav-link {
            color: #94a3b8;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin: 0;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 0.75rem;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            box-shadow: var(--hover-shadow);
        }

        .card-header {
            background: linear-gradient(45deg, var(--bg-light), #ffffff);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem;
            border-radius: 16px 16px 0 0 !important;
        }

        .card-header h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .card-header h5 i {
            color: var(--primary-color);
            margin-right: 0.75rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background: white;
            margin: auto;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 212, 170, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
            border: none;
            box-shadow: 0 4px 12px rgba(0, 212, 170, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 212, 170, 0.3);
        }

        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        .nav-tabs {
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 2rem;
            gap: 0.5rem;
        }

        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            border-radius: 8px 8px 0 0;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            background: var(--bg-light);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            font-weight: 600;
            background: transparent;
        }

        .nav-tabs .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
        }

        .plan-info {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
        }

        .plan-info h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .plan-info .progress {
            height: 0.75rem;
            border-radius: 1rem;
            background: var(--bg-light);
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .plan-info .progress-bar {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
            border-radius: 1rem;
        }

        .hours-row {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 0.75rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
        }

        .hours-row:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transform: translateY(-1px);
        }

        .product-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .product-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .product-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .product-image:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
        }

        .category-header {
            background: white;
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.25rem;
            cursor: move;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
        }

        .category-header:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .sortable-ghost {
            opacity: 0.6;
            background: var(--bg-light);
            border: 2px dashed var(--primary-color);
        }

        .sortable-drag {
            background: white;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            border-radius: 12px;
        }

        .status-toggle {
            transform: scale(1.3);
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: linear-gradient(45deg, var(--bg-light), #ffffff);
            border-bottom: 1px solid var(--border-color);
            border-radius: 16px 16px 0 0;
            padding: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.25rem;
            border-radius: 0 0 16px 16px;
        }

        .input-group-text {
            background: var(--bg-light);
            border: 1px solid #e2e8f0;
            color: var(--text-secondary);
        }

        .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 212, 170, 0.1);
        }

        .form-check-input {
            border: 2px solid #e2e8f0;
        }

        .form-check-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 212, 170, 0.1);
        }

        .form-check-label {
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-text {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .badge {
            padding: 0.5em 0.75em;
            border-radius: 6px;
            font-weight: 500;
        }

        .badge-success {
            background: #ecfdf5;
            color: #065f46;
        }

        .badge-danger {
            background: #fef2f2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fffbeb;
            color: #92400e;
        }

        .badge-info {
            background: #eff6ff;
            color: #1e40af;
        }

        .config-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        .config-section h6 {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .config-section .form-check {
            padding: 1.25rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .config-section .form-check:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .config-section .form-check:last-child {
            margin-bottom: 0;
        }
        .config-section .form-check-input {
            margin: 0;
            width: 3em;
            height: 1.5em;
            flex-shrink: 0;
        }
        .config-section .form-check-label {
            margin: 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .config-section .form-check-label strong {
            display: block;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }
        .config-section .form-check-label small {
            color: #64748b;
            font-size: 0.875rem;
        }

        .p-4 {
            padding: 1.5rem !important;
        }

        /* Quitar padding en dispositivos móviles */
        @media (max-width: 767.98px) {
            .p-4 {
                padding: 0 !important;
            }
        }

        /* Estilos personalizados para las pestañas */
        .custom-tabs-container {
            background: white;
            border-radius: 16px;
            padding: 0.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
        }

        .custom-tabs {
            border: none;
            gap: 0.5rem;
            padding: 0;
            margin: 0;
        }

        .custom-tabs .nav-item {
            flex: 1;
            min-width: 0;
        }

        .custom-tabs .nav-link {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin: 0;
            background: transparent;
            color: #64748b;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 60px;
        }

        .custom-tabs .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            opacity: 0;
            transition: all 0.3s ease;
            border-radius: 12px;
        }

        .custom-tabs .nav-link:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 212, 170, 0.15);
        }

        .custom-tabs .nav-link:hover::before {
            opacity: 1;
        }

        .custom-tabs .nav-link.active {
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            box-shadow: 0 8px 25px rgba(0, 212, 170, 0.3);
            transform: translateY(-2px);
        }

        .custom-tabs .nav-link.active::before {
            opacity: 0;
        }

        .custom-tabs .nav-link.active::after {
            display: none;
        }

        .custom-tabs .tab-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .custom-tabs .tab-content i {
            font-size: 1.25rem;
            transition: all 0.3s ease;
        }

        .custom-tabs .nav-link:hover .tab-content i {
            transform: scale(1.1);
        }

        .custom-tabs .nav-link.active .tab-content i {
            transform: scale(1.1);
        }

        .custom-tabs .tab-content span {
            font-weight: 600;
            text-align: center;
            line-height: 1.2;
        }

        /* Responsive para pestañas */
        @media (max-width: 768px) {
            .custom-tabs-container {
                padding: 0.25rem;
            }
            
            .custom-tabs .nav-link {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
                min-height: 50px;
            }
            
            .custom-tabs .tab-content {
                gap: 0.25rem;
            }
            
            .custom-tabs .tab-content i {
                font-size: 1rem;
            }
            
            .custom-tabs .tab-content span {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .custom-tabs .nav-link {
                padding: 0.5rem 0.25rem;
                font-size: 0.75rem;
            }
            
            .custom-tabs .tab-content span {
                font-size: 0.7rem;
            }
        }

    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>


                    <!-- Tabs -->
                    <div class="custom-tabs-container mb-4">
                        <ul class="nav nav-tabs custom-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?= $active_tab === 'profile' ? 'active' : '' ?>" 
                                   href="/restaurante/perfil.php?tab=profile"
                                   role="tab">
                                    <div class="tab-content">
                                        <i class="fas fa-store"></i>
                                        <span>Información del Restaurante</span>
                                    </div>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?= $active_tab === 'hours' ? 'active' : '' ?>" 
                                   href="/restaurante/perfil.php?tab=hours"
                                   role="tab">
                                    <div class="tab-content">
                                        <i class="fas fa-clock"></i>
                                        <span>Horarios</span>
                                    </div>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?= $active_tab === 'currency' ? 'active' : '' ?>" 
                                   href="/restaurante/perfil.php?tab=currency"
                                   role="tab">
                                    <div class="tab-content">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span>Configuración de Moneda</span>
                                    </div>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?= $active_tab === 'whatsapp_order' ? 'active' : '' ?>" 
                                   href="/restaurante/perfil.php?tab=whatsapp_order"
                                   role="tab">
                                    <div class="tab-content">
                                        <i class="fab fa-whatsapp"></i>
                                        <span>WhatsApp Pedido</span>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <?php if ($active_tab === 'profile'): ?>
                        <!-- Profile Form -->
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <!-- Información Básica -->
                                <div class="col-lg-8">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="mb-0">Información Básica</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="name" class="form-label">Nombre del Restaurante *</label>
                                                        <input type="text" class="form-control" id="name" name="name" 
                                                               value="<?= htmlspecialchars($restaurant['name']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="phone" class="form-label">Teléfono</label>
                                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                                               value="<?= htmlspecialchars($restaurant['phone']) ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="address" class="form-label">Dirección</label>
                                                <input type="text" class="form-control" id="address" name="address" 
                                                       value="<?= htmlspecialchars($restaurant['address']) ?>">
                                            </div>

                                            <!-- Redes Sociales -->
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h5 class="mb-0">Redes Sociales</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="facebook_url" class="form-label">
                                                                    <i class="fab fa-facebook text-primary"></i> Facebook
                                                                </label>
                                                                <input type="url" class="form-control" id="facebook_url" name="facebook_url" 
                                                                       value="<?= htmlspecialchars($restaurant['facebook_url'] ?? '') ?>"
                                                                       placeholder="https://facebook.com/tu-pagina">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="instagram_url" class="form-label">
                                                                    <i class="fab fa-instagram text-danger"></i> Instagram
                                                                </label>
                                                                <input type="url" class="form-control" id="instagram_url" name="instagram_url" 
                                                                       value="<?= htmlspecialchars($restaurant['instagram_url'] ?? '') ?>"
                                                                       placeholder="https://instagram.com/tu-cuenta">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="tiktok_url" class="form-label">
                                                                    <i class="fab fa-tiktok"></i> TikTok
                                                                </label>
                                                                <input type="url" class="form-control" id="tiktok_url" name="tiktok_url" 
                                                                       value="<?= htmlspecialchars($restaurant['tiktok_url'] ?? '') ?>"
                                                                       placeholder="https://tiktok.com/@tu-cuenta">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="whatsapp_url" class="form-label">
                                                                    <i class="fab fa-whatsapp text-success"></i> WhatsApp contacto
                                                                </label>
                                                                    <input type="tel" 
                                                                           class="form-control" 
                                                                           id="whatsapp_url" 
                                                                           name="whatsapp_url" 
                                                                           value="<?= preg_replace('/[^0-9]/', '', $restaurant['whatsapp_url'] ?? '') ?>"
                                                                           placeholder="Ej: 912345678 o +56912345678"
                                                                           maxlength="13"
                                                                           oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                                                <small class="form-text text-muted">Ingresa el número de WhatsApp con o sin prefijo del país (opcional)</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="description" class="form-label">Descripción</label>
                                                <textarea class="form-control" id="description" name="description" rows="3" 
                                                          placeholder="Describe tu restaurante, especialidades, etc."><?= htmlspecialchars($restaurant['description']) ?></textarea>
                                            </div>
                                            
                                            <!-- Estado del Restaurante -->
                                            <div class="config-section">
                                                <h6><i class="fas fa-store me-2"></i>Estado del Restaurante</h6>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input status-toggle" type="checkbox" 
                                                           id="is_open" name="is_open" <?= $restaurant['is_open'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="is_open">
                                                        <strong>Restaurante Abierto</strong>
                                                        <small class="text-muted d-block">Los clientes podrán ver tu menú cuando esté activado</small>
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Servicios -->
                                            <div class="config-section">
                                                <h6><i class="fas fa-concierge-bell me-2"></i>Servicios</h6>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input status-toggle" type="checkbox" 
                                                           id="has_delivery" name="has_delivery" 
                                                           <?= ($restaurant['has_delivery'] ?? 0) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="has_delivery">
                                                        <strong>Delivery Disponible</strong>
                                                        <small class="text-muted d-block">Indica si ofreces servicio de delivery</small>
                                                    </label>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input status-toggle" type="checkbox" 
                                                           id="has_physical_store" name="has_physical_store" 
                                                           <?= ($restaurant['has_physical_store'] ?? 0) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="has_physical_store">
                                                        <strong>Tienda Física</strong>
                                                        <small class="text-muted d-block">Indica si tienes local físico</small>
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Configuración de Visualización -->
                                            <div class="config-section">
                                                <h6><i class="fas fa-eye me-2"></i>Configuración de Visualización</h6>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input status-toggle" type="checkbox" 
                                                           id="show_featured_products" name="show_featured_products" 
                                                           <?= ($restaurant['show_featured_products'] ?? 1) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="show_featured_products">
                                                        <strong>Mostrar Productos Destacados</strong>
                                                        <small class="text-muted d-block">Activa o desactiva la sección de productos destacados en el menú</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    
                                </div>
                                
                                <!-- Imágenes -->
                                <div class="col-lg-4">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="mb-0">Logo del Restaurante</h5>
                                        </div>
                                        <div class="card-body text-center">
                                            <?php if (!empty($restaurant['logo']) && file_exists(UPLOAD_PATH . $restaurant['logo'])): ?>
                                                <img src="/uploads/<?= htmlspecialchars($restaurant['logo']) ?>" 
                                                     alt="Logo actual" class="image-preview mb-3" style="max-width: 200px; max-height: 150px; object-fit: contain;">
                                                <div class="mb-2">
                                                    <small class="text-muted">Imagen actual</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="image-preview mb-3 d-flex align-items-center justify-content-center bg-light" 
                                                     style="width: 200px; height: 150px; margin: 0 auto;">
                                                    <i class="fas fa-image fa-2x text-muted"></i>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-muted">No hay logo seleccionado</small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label for="logo" class="form-label">Cambiar Logo</label>
                                                <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                                <small class="text-muted d-block mt-1">JPG, PNG, GIF. Máximo 2MB</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="mb-0">Banner del Restaurante</h5>
                                        </div>
                                        <div class="card-body text-center">
                                            <?php if (!empty($restaurant['banner']) && file_exists(UPLOAD_PATH . $restaurant['banner'])): ?>
                                                <img src="/uploads/<?= htmlspecialchars($restaurant['banner']) ?>" 
                                                     alt="Banner actual" class="image-preview mb-3" style="max-width: 100%; max-height: 150px; object-fit: cover;">
                                                <div class="mb-2">
                                                    <small class="text-muted">Imagen actual</small>
                                                </div>
                                            <?php else: ?>
                                                <div class="image-preview mb-3 d-flex align-items-center justify-content-center bg-light" 
                                                     style="width: 100%; height: 150px;">
                                                    <i class="fas fa-image fa-2x text-muted"></i>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-muted">No hay banner seleccionado</small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label for="banner" class="form-label">Cambiar Banner</label>
                                                <input type="file" class="form-control" id="banner" name="banner" accept="image/*">
                                                <small class="text-muted d-block mt-1">JPG, PNG, GIF. Máximo 2MB</small>
                                            </div>

                                            <div class="mb-3">
                                                <label for="banner_color" class="form-label">
                                                    <i class="fas fa-palette"></i> Color del Banner
                                                    <?php if ($restaurant['current_plan_id'] == 1 || $restaurant['current_plan_id'] == 2 || $restaurant['current_plan_id'] === null): ?>
                                                        <span class="badge bg-warning text-dark ms-2">Premium</span>
                                                    <?php endif; ?>
                                                </label>
                                                <input type="color" class="form-control form-control-color" id="banner_color" name="banner_color" 
                                                       value="<?= htmlspecialchars($restaurant['banner_color'] ?? '#8e8d91') ?>"
                                                       title="Elige el color del banner"
                                                       <?php if ($restaurant['current_plan_id'] == 1 || $restaurant['current_plan_id'] == 2 || $restaurant['current_plan_id'] === null): ?>disabled<?php endif; ?>>
                                                <small class="text-muted d-block mt-1">
                                                    Este color se superpone al banner
                                                    
                                                </small>
                                            </div>

                                            <div class="mb-3">
                                                <label for="color_web" class="form-label">
                                                    <i class="fas fa-paint-brush"></i> Color de la Web
                                                    <?php if ($restaurant['current_plan_id'] == 1 || $restaurant['current_plan_id'] == 2 || $restaurant['current_plan_id'] === null): ?>
                                                        <span class="badge bg-warning text-dark ms-2">Premium</span>
                                                    <?php endif; ?>
                                                </label>
                                                <input type="color" class="form-control form-control-color" id="color_web" name="color_web" 
                                                       value="<?= htmlspecialchars($restaurant['color_web'] ?? '#00b894') ?>"
                                                       title="Elige el color principal de tu web"
                                                       <?php if ($restaurant['current_plan_id'] == 1 || $restaurant['current_plan_id'] == 2 || $restaurant['current_plan_id'] === null): ?>disabled<?php endif; ?>>
                                                <small class="text-muted d-block mt-1">
                                                    Este color se aplicará al logo, botones y elementos principales de tu menú web
                                                    <?php if ($restaurant['current_plan_id'] == 1 || $restaurant['current_plan_id'] == 2 || $restaurant['current_plan_id'] === null): ?>
                                                        <br><strong class="text-warning">Función disponible solo en planes Premium</strong>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-header bg-gradient">
                                            <h5 class="mb-0">
                                                <i class="fas fa-user-circle me-2"></i>
                                                Información de la Cuenta
                                            </h5>
                                        </div>
                                        <div class="card-body account-info">
                                            <div class="account-info-item">
                                                <div class="info-icon">
                                                    <i class="fas fa-envelope"></i>
                                                </div>
                                                <div class="info-content">
                                                    <label>Correo Electrónico</label>
                                                    <p><?= htmlspecialchars($restaurant['email']) ?></p>
                                                </div>
                                            </div>
                                            
                                            <div class="account-info-item">
                                                <div class="info-icon">
                                                    <i class="fas fa-store"></i>
                                                </div>
                                                <div class="info-content">
                                                    <label>URL de tu Tienda</label>
                                                    <div class="url-box">
                                                        <a href="/<?= $restaurant['slug'] ?>" target="_blank" class="store-url">
                                                            /<?= $restaurant['slug'] ?>
                                                            <i class="fas fa-external-link-alt ms-2"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-primary copy-url" 
                                                                data-url="/<?= $restaurant['slug'] ?>"
                                                                title="Copiar URL">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="account-info-item">
                                                <div class="info-icon">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </div>
                                                <div class="info-content">
                                                    <label>Fecha de Registro</label>
                                                    <p><?= date('d/m/Y', strtotime($restaurant['created_at'])) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <style>
                                        .account-info {
                                            padding: 1.5rem;
                                        }
                                        .account-info-item {
                                            display: flex;
                                            align-items: flex-start;
                                            padding: 0;
                                            border-radius: 12px;
                                            background: #f8fafc;
                                            margin-bottom: 1rem;
                                            transition: all 0.3s ease;
                                        }
                                        .account-info-item:last-child {
                                            margin-bottom: 0;
                                        }
                                        .account-info-item:hover {
                                            background: #f1f5f9;
                                            transform: translateX(5px);
                                        }
                                        .info-icon {
                                            width: 40px;
                                            height: 40px;
                                            background: linear-gradient(45deg, #00D4AA, #00b8d4);
                                            border-radius: 10px;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            margin-right: 1rem;
                                            flex-shrink: 0;
                                        }
                                        .info-icon i {
                                            color: white;
                                            font-size: 1.2rem;
                                        }
                                        .info-content {
                                            flex-grow: 1;
                                        }
                                        .info-content label {
                                            display: block;
                                            font-size: 0.875rem;
                                            color: #64748b;
                                            margin-bottom: 0.25rem;
                                            font-weight: 500;
                                        }
                                        .info-content p {
                                            margin: 0;
                                            color: #1e293b;
                                            font-weight: 500;
                                        }
                                        .url-box {
                                            display: flex;
                                            font-size: 10px;
                                            align-items: center;
                                            gap: 0.5rem;
                                            background: white;
                                            padding: 0.5rem ;
                                            border-radius: 8px;
                                            border: 1px solid #e2e8f0;
                                        }
                                        .store-url {
                                            color: #00D4AA;
                                            text-decoration: none;
                                            font-weight: 500;
                                            flex-grow: 1;
                                            display: flex;
                                            align-items: center;
                                            transition: all 0.3s ease;
                                        }
                                        .store-url:hover {
                                            color: #00b8d4;
                                        }
                                        .store-url i {
                                            font-size: 0.875rem;
                                            opacity: 0.7;
                                        }
                                        .copy-url {
                                            padding: 0.25rem 0.5rem;
                                            font-size: 0.875rem;
                                            border-radius: 6px;
                                            border-color: #00D4AA;
                                            color: #00D4AA;
                                            transition: all 0.3s ease;
                                        }
                                        .copy-url:hover {
                                            background: #00D4AA;
                                            color: white;
                                        }
                                        .card-header.bg-gradient {
                                            background: linear-gradient(45deg, #f8fafc, #ffffff);
                                            border-bottom: 1px solid #eef2f7;
                                        }
                                        .card-header h5 {
                                            color: #1e293b;
                                            font-weight: 600;
                                        }
                                        .card-header h5 i {
                                            color: #00D4AA;
                                        }
                                    </style>

                                    <script>
                                        document.addEventListener('DOMContentLoaded', function() {
                                            // Función para copiar URL
                                            document.querySelectorAll('.copy-url').forEach(button => {
                                                button.addEventListener('click', function(e) {
                                                    e.preventDefault(); // Prevenir cualquier comportamiento por defecto
                                                    e.stopPropagation(); // Detener la propagación del evento
                                                    
                                                    const url = this.dataset.url;
                                                    navigator.clipboard.writeText(url).then(() => {
                                                        // Cambiar temporalmente el ícono y texto
                                                        const icon = this.querySelector('i');
                                                        icon.classList.remove('fa-copy');
                                                        icon.classList.add('fa-check');
                                                        this.classList.add('btn-success');
                                                        this.classList.remove('btn-outline-primary');
                                                        
                                                        // Mostrar mensaje de confirmación
                                                        const originalTitle = this.getAttribute('title');
                                                        this.setAttribute('title', 'URL copiada');
                                                        
                                                        // Restaurar después de 2 segundos
                                                        setTimeout(() => {
                                                            icon.classList.remove('fa-check');
                                                            icon.classList.add('fa-copy');
                                                            this.classList.remove('btn-success');
                                                            this.classList.add('btn-outline-primary');
                                                            this.setAttribute('title', originalTitle);
                                                        }, 2000);
                                                    }).catch(err => {
                                                        console.error('Error al copiar URL:', err);
                                                        alert('Error al copiar la URL');
                                                    });
                                                });
                                            });
                                        });
                                    </script>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    <?php elseif ($active_tab === 'hours'): ?>
                        <!-- Horarios de Atención Form -->
                        <div class="row">
                            <!-- Formulario de Configuración -->
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Configuración de Horarios de Atención</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?tab=hours">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="action" value="update_hours">
                                            
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i>
                                                Marca los días en que el restaurante está abierto y establece los horarios de atención.
                                                Puedes dejar días sin marcar si el restaurante está cerrado ese día.
                                            </div>

                                            <?php 
                                            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                            $day_names = [
                                                'monday' => 'Lunes',
                                                'tuesday' => 'Martes', 
                                                'wednesday' => 'Miércoles',
                                                'thursday' => 'Jueves',
                                                'friday' => 'Viernes',
                                                'saturday' => 'Sábado',
                                                'sunday' => 'Domingo'
                                            ];
                                            $opening_hours = json_decode($restaurant['opening_hours'] ?? '[]', true) ?? [];
                                            ?>

                                            <?php foreach ($days as $day): 
                                                $day_data = $opening_hours[$day] ?? [
                                                    'is_open' => false,
                                                    'open_time' => '09:00',
                                                    'close_time' => '22:00'
                                                ];
                                            ?>
                                                <div class="hours-row mb-3">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input day-toggle" type="checkbox" 
                                                                       id="<?= $day ?>_open" name="<?= $day ?>_open" 
                                                                       <?= $day_data['is_open'] ? 'checked' : '' ?>
                                                                       data-day="<?= $day ?>"
                                                                       onchange="toggleDayHours('<?= $day ?>'); updateHoursSummary();">
                                                                <label class="form-check-label" for="<?= $day ?>_open">
                                                                    <strong><?= $day_names[$day] ?></strong>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="input-group">
                                                                <span class="input-group-text"><i class="fas fa-door-open"></i></span>
                                                                <input type="time" class="form-control time-input" 
                                                                       id="<?= $day ?>_open_time" name="<?= $day ?>_open_time" 
                                                                       value="<?= $day_data['open_time'] ?>"
                                                                       <?= !$day_data['is_open'] ? 'disabled' : '' ?>
                                                                       data-day="<?= $day ?>"
                                                                       onchange="validateHours('<?= $day ?>');">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-1 text-center">
                                                            <i class="fas fa-arrow-right"></i>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="input-group">
                                                                <span class="input-group-text"><i class="fas fa-door-closed"></i></span>
                                                                <input type="time" class="form-control time-input" 
                                                                       id="<?= $day ?>_close_time" name="<?= $day ?>_close_time" 
                                                                       value="<?= $day_data['close_time'] ?>"
                                                                       <?= !$day_data['is_open'] ? 'disabled' : '' ?>
                                                                       data-day="<?= $day ?>"
                                                                       onchange="validateHours('<?= $day ?>');">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>

                                            <div class="text-center">
                                                <button type="submit" class="btn btn-primary btn-lg">
                                                    <i class="fas fa-save"></i> Guardar Horarios
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Resumen Visual -->
                            <div class="col-lg-4 mb-4">
                                <div class="card">
                                    <div class="card-header bg-gradient">
                                        <h5 class="mb-0">
                                            <i class="fas fa-calendar-alt me-2"></i>
                                            Resumen de Horarios
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $open_days = 0;
                                        $closed_days = 0;
                                        $opening_hours = json_decode($restaurant['opening_hours'] ?? '[]', true) ?? [];
                                        
                                        foreach ($days as $day) {
                                            if (($opening_hours[$day]['is_open'] ?? false)) {
                                                $open_days++;
                                            } else {
                                                $closed_days++;
                                            }
                                        }
                                        ?>
                                        
                                        
                                        
                                        <div class="weekly-overview mt-3">
                                            <h6 class="mb-3">Vista Semanal</h6>
                                            <?php foreach ($days as $day): 
                                                $day_data = $opening_hours[$day] ?? ['is_open' => false];
                                                $is_open = $day_data['is_open'] ?? false;
                                            ?>
                                                <div class="day-status <?= $is_open ? 'open' : 'closed' ?>">
                                                    <div class="day-name"><?= $day_names[$day] ?></div>
                                                    <div class="day-indicator">
                                                        <?php if ($is_open): ?>
                                                            <i class="fas fa-check-circle text-success"></i>
                                                            <span class="hours-text">
                                                                <?= $day_data['open_time'] ?? '--:--' ?> - <?= $day_data['close_time'] ?? '--:--' ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <i class="fas fa-times-circle text-danger"></i>
                                                            <span class="hours-text">Cerrado</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <style>
                        .hours-summary-stats {
                            display: flex;
                            gap: 1rem;
                            margin-bottom: 1rem;
                        }
                        
                        .stat-item {
                            flex: 1;
                            display: flex;
                            align-items: center;
                            padding: 1rem;
                            border-radius: 12px;
                            background: #f8fafc;
                            transition: all 0.3s ease;
                        }
                        
                        .stat-item.open {
                            background: linear-gradient(45deg, #dcfce7, #f0fdf4);
                            border: 1px solid #bbf7d0;
                        }
                        
                        .stat-item.closed {
                            background: linear-gradient(45deg, #fef2f2, #fefefe);
                            border: 1px solid #fecaca;
                        }
                        
                        .stat-icon {
                            width: 40px;
                            height: 40px;
                            border-radius: 10px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin-right: 0.75rem;
                        }
                        
                        .stat-item.open .stat-icon {
                            background: #22c55e;
                            color: white;
                        }
                        
                        .stat-item.closed .stat-icon {
                            background: #ef4444;
                            color: white;
                        }
                        
                        .stat-number {
                            font-size: 1.5rem;
                            font-weight: 700;
                            line-height: 1;
                        }
                        
                        .stat-label {
                            font-size: 0.875rem;
                            color: #64748b;
                            margin-top: 0.25rem;
                        }
                        
                        .weekly-overview {
                            border-top: 1px solid #e2e8f0;
                            padding-top: 1rem;
                        }
                        
                        .day-status {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            padding: 0.75rem;
                            margin-bottom: 0.5rem;
                            border-radius: 8px;
                            transition: all 0.3s ease;
                        }
                        
                        .day-status.open {
                            background: #f0fdf4;
                            border: 1px solid #bbf7d0;
                        }
                        
                        .day-status.closed {
                            background: #fef2f2;
                            border: 1px solid #fecaca;
                        }
                        
                        .day-name {
                            font-weight: 600;
                            color: #1e293b;
                        }
                        
                        .day-indicator {
                            display: flex;
                            align-items: center;
                            gap: 0.5rem;
                        }
                        
                        .hours-text {
                            font-size: 0.875rem;
                            color: #64748b;
                        }
                        
                        .card-header.bg-gradient {
                            background: linear-gradient(45deg, #f8fafc, #ffffff);
                            border-bottom: 1px solid #eef2f7;
                        }
                        </style>

                        <script>
                        // Funciones para manejar los horarios
                        function toggleDayHours(day) {
                            const checkbox = document.getElementById(day + '_open');
                            if (!checkbox) {
                                console.warn(`Checkbox no encontrado para el día: ${day}`);
                                return;
                            }
                            
                            const isOpen = checkbox.checked;
                            const openTimeInput = document.getElementById(day + '_open_time');
                            const closeTimeInput = document.getElementById(day + '_close_time');
                            
                            if (openTimeInput) openTimeInput.disabled = !isOpen;
                            if (closeTimeInput) closeTimeInput.disabled = !isOpen;
                            
                            if (!isOpen) {
                                if (openTimeInput) openTimeInput.value = '';
                                if (closeTimeInput) closeTimeInput.value = '';
                            } else {
                                // Establecer valores por defecto si están vacíos
                                if (openTimeInput && !openTimeInput.value) openTimeInput.value = '09:00';
                                if (closeTimeInput && !closeTimeInput.value) closeTimeInput.value = '22:00';
                            }
                        }

                        function validateHours(day) {
                            const openTime = document.getElementById(day + '_open_time');
                            const closeTime = document.getElementById(day + '_close_time');
                            
                            if (!openTime || !closeTime) {
                                console.warn(`Elementos de tiempo no encontrados para el día: ${day}`);
                                return false;
                            }
                            
                            const openTimeValue = openTime.value;
                            const closeTimeValue = closeTime.value;
                            const dayName = document.querySelector(`label[for="${day}_open"] strong`);
                            const dayNameText = dayName ? dayName.textContent : day;
                            
                            if (!openTimeValue || !closeTimeValue) {
                                alert(`Por favor, ingresa los horarios de apertura y cierre para ${dayNameText}`);
                                return false;
                            }
                            
                            // Validar que la hora de cierre sea posterior a la de apertura
                            // (excepto para horarios que cruzan la medianoche)
                            if (closeTimeValue < openTimeValue && closeTimeValue !== '00:00') {
                                alert(`La hora de cierre debe ser posterior a la hora de apertura para ${dayNameText}`);
                                return false;
                            }
                            
                            return true;
                        }

                        function updateHoursSummary() {
                            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                            let openDays = 0;
                            
                            days.forEach(day => {
                                const checkbox = document.getElementById(day + '_open');
                                if (checkbox && checkbox.checked) {
                                    openDays++;
                                }
                            });
                            
                            const summary = document.getElementById('hoursSummary');
                            if (summary) {
                                summary.textContent = openDays + ' días abierto';
                            }
                        }

                        // Inicializar los estados de los campos de hora al cargar la página
                        document.addEventListener('DOMContentLoaded', function() {
                            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                            days.forEach(day => {
                                toggleDayHours(day);
                            });
                            updateHoursSummary();
                        });
                        </script>
                    <?php elseif ($active_tab === 'currency'): ?>
                        <!-- Configuración de Moneda Form -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Configuración de Moneda</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?tab=currency">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="update_currency">
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        Selecciona la moneda en la que se mostrarán los precios de tus productos en el menú digital.
                                    </div>

                                    <div class="mb-4">
                                        <label for="currency" class="form-label">
                                            <i class="fas fa-money-bill-wave"></i> Moneda Principal
                                        </label>
                                        <select class="form-select" id="currency" name="currency" required>
                                            <?php foreach ($currencies as $currency): ?>
                                                <option value="<?= htmlspecialchars($currency['code']) ?>" 
                                                        <?= $restaurant['currency'] === $currency['code'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($currency['name']) ?> (<?= htmlspecialchars($currency['symbol']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">
                                            Esta moneda se utilizará para mostrar todos los precios en tu menú digital.
                                        </small>
                                    </div>
                                    <div class="text-center">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save"></i> Guardar Configuración de Moneda
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php elseif ($active_tab === 'whatsapp_order'): ?>
                        <!-- WhatsApp Pedido Form -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Configuración de Pedidos por WhatsApp</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?tab=whatsapp_order" id="whatsappOrderForm">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="update_whatsapp_order">
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        Configura cómo se enviarán los pedidos a través de WhatsApp. Los clientes podrán enviar sus pedidos directamente a tu WhatsApp.
                                    </div>

                                    <div class="mb-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input status-toggle" type="checkbox" 
                                                   id="enable_whatsapp_order" name="enable_whatsapp_order" 
                                                   value="1"
                                                   <?= ($restaurant['enable_whatsapp_order'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="enable_whatsapp_order">
                                                <strong>Activar Pedidos por WhatsApp</strong>
                                                <small class="text-muted d-block">Permite a los clientes enviar pedidos directamente a tu WhatsApp</small>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="whatsapp_order_number" class="form-label">
                                            <i class="fab fa-whatsapp text-success"></i> Número de WhatsApp para Pedidos
                                        </label>
                                        <input type="tel" 
                                               class="form-control" 
                                               id="whatsapp_order_number" 
                                               name="whatsapp_order_number" 
                                               value="<?= preg_replace('/[^0-9]/', '', str_replace('https://wa.me/', '', $restaurant['whatsapp_order_number'] ?? '')) ?>"
                                               placeholder="569XXXXXXXX"
                                               pattern="[0-9]{10,15}"
                                               maxlength="15"
                                               oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                               <?= !($restaurant['enable_whatsapp_order'] ?? 0) ? 'disabled' : '' ?>>
                                        <small class="form-text text-muted">Ingresa el número completo con prefijo del país (ej: 56912345678)</small>
                                    </div>

                                    <div class="mb-4">
                                        <label for="whatsapp_order_message" class="form-label">
                                            <i class="fas fa-comment"></i> Mensaje Personalizado
                                        </label>
                                        <textarea class="form-control" 
                                                  id="whatsapp_order_message" 
                                                  name="whatsapp_order_message" 
                                                  rows="3" 
                                                  placeholder="¡Hola! Me gustaría hacer el siguiente pedido:"
                                                  <?= !($restaurant['enable_whatsapp_order'] ?? 0) ? 'disabled' : '' ?>><?= htmlspecialchars($restaurant['whatsapp_order_message'] ?? '¡Hola! Me gustaría hacer el siguiente pedido:') ?></textarea>
                                        <small class="form-text text-muted">Este mensaje se incluirá al inicio de cada pedido</small>
                                    </div>

                                    

                                    <div class="text-center">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save"></i> Guardar Configuración
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const enableWhatsAppOrder = document.getElementById('enable_whatsapp_order');
                            const form = document.getElementById('whatsappOrderForm');
                            const inputs = form.querySelectorAll('input:not([type="checkbox"]):not([type="hidden"]), textarea, select');
                            const checkboxes = form.querySelectorAll('input[type="checkbox"]:not(#enable_whatsapp_order)');

                            function toggleInputs() {
                                const isEnabled = enableWhatsAppOrder.checked;
                                inputs.forEach(input => {
                                    input.disabled = !isEnabled;
                                    if (!isEnabled) {
                                        if (input.type === 'tel') {
                                            input.value = '';
                                        }
                                    }
                                });
                                checkboxes.forEach(checkbox => {
                                    checkbox.disabled = !isEnabled;
                                    if (!isEnabled) {
                                        checkbox.checked = false;
                                    }
                                });
                            }

                            enableWhatsAppOrder.addEventListener('change', function() {
                                // Asegurarse de que el formulario siempre envíe el token CSRF
                                const csrfInput = form.querySelector('input[name="csrf_token"]');
                                if (!csrfInput) {
                                    const csrfToken = document.createElement('input');
                                    csrfToken.type = 'hidden';
                                    csrfToken.name = 'csrf_token';
                                    csrfToken.value = '<?= htmlspecialchars($csrf_token) ?>';
                                    form.appendChild(csrfToken);
                                }
                                toggleInputs();
                            });

                            toggleInputs(); // Estado inicial

                            form.addEventListener('submit', function(e) {
                                // Asegurarse de que el token CSRF esté presente
                                if (!form.querySelector('input[name="csrf_token"]')) {
                                    const csrfToken = document.createElement('input');
                                    csrfToken.type = 'hidden';
                                    csrfToken.name = 'csrf_token';
                                    csrfToken.value = '<?= htmlspecialchars($csrf_token) ?>';
                                    form.appendChild(csrfToken);
                                }

                                if (enableWhatsAppOrder.checked) {
                                    const whatsappNumber = document.getElementById('whatsapp_order_number').value;
                                    if (whatsappNumber.length < 10 || whatsappNumber.length > 15) {
                                        e.preventDefault();
                                        alert('El número de WhatsApp debe tener entre 10 y 15 dígitos (incluyendo prefijo del país)');
                                        document.getElementById('whatsapp_order_number').focus();
                                        return;
                                    }
                                }
                            });
                        });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="add_category">
                        
                        <div class="mb-3">
                            <label for="category_name" class="form-label">Nombre de la Categoría</label>
                            <input type="text" class="form-control" id="category_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="category_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar Categoría</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="edit_category">
                        <input type="hidden" name="category_id" id="edit_category_id">
                        
                        <div class="mb-3">
                            <label for="edit_category_name" class="form-label">Nombre de la Categoría</label>
                            <input type="text" class="form-control" id="edit_category_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_category_description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_category_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar la categoría "<span id="delete_category_name"></span>"?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" id="delete_category_id">
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Eliminar Categoría</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="add_product">
                        
                        <div class="mb-3">
                            <label for="product_category" class="form-label">Categoría</label>
                            <select class="form-control" id="product_category" name="category_id" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="product_name" class="form-label">Nombre del Producto</label>
                            <input type="text" class="form-control" id="product_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="product_description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="product_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="product_price" class="form-label">Precio</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="product_price" name="price" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="product_image" class="form-label">Imagen</label>
                            <input type="file" class="form-control" id="product_image" name="image" accept="image/*">
                            <small class="text-muted">JPG, PNG, GIF. Máximo 2MB</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="product_available" 
                                       name="is_available" checked>
                                <label class="form-check-label" for="product_available">Producto Disponible</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="product_featured" 
                                       name="is_featured">
                                <label class="form-check-label" for="product_featured">
                                    <i class="fas fa-star text-warning"></i> Producto Destacado
                                    <small class="text-muted d-block">Los productos destacados aparecerán primero en el menú</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar Producto</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="edit_product">
                        <input type="hidden" name="product_id" id="edit_product_id">
                        
                        <div class="mb-3">
                            <label for="edit_product_category" class="form-label">Categoría</label>
                            <select class="form-control" id="edit_product_category" name="category_id" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_product_name" class="form-label">Nombre del Producto</label>
                            <input type="text" class="form-control" id="edit_product_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_product_description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="edit_product_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_product_price" class="form-label">Precio</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="edit_product_price" name="price" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_product_image" class="form-label">Imagen</label>
                            <input type="file" class="form-control" id="edit_product_image" name="image" accept="image/*">
                            <small class="text-muted">Deja vacío para mantener la imagen actual</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_product_available" 
                                       name="is_available">
                                <label class="form-check-label" for="edit_product_available">Producto Disponible</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_product_featured" 
                                       name="is_featured">
                                <label class="form-check-label" for="edit_product_featured">
                                    <i class="fas fa-star text-warning"></i> Producto Destacado
                                    <small class="text-muted d-block">Los productos destacados aparecerán primero en el menú</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button> 
                        </div>
                    </form> 
                </div>
            </div>
        </div>
    </div> <br><br><br><br>

    <!-- Delete Product Modal -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar el producto "<span id="delete_product_name"></span>"?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="product_id" id="delete_product_id">
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Eliminar Producto</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <!-- Definir BASE_URL antes de cargar products.js -->
    <script>
        window.BASE_URL = '<?php echo BASE_URL; ?>';
        
        // Si BASE_URL no está definido, detectarlo automáticamente
        if (!window.BASE_URL || window.BASE_URL === '') {
            const protocol = window.location.protocol;
            const host = window.location.host;
            const pathArray = window.location.pathname.split('/');
            const basePath = pathArray.slice(0, -2).join('/'); // Remover 'restaurante' y el archivo actual
            window.BASE_URL = protocol + '//' + host + basePath;
        }
        
        // Asegurar que BASE_URL use HTTPS si la página está en HTTPS
        if (window.location.protocol === 'https:' && window.BASE_URL && window.BASE_URL.startsWith('http:')) {
            window.BASE_URL = window.BASE_URL.replace('http:', 'https:');
        }
        
        console.log('BASE_URL configurado:', window.BASE_URL);
    </script>
    
    <script src="/restaurante/js/products.js"></script>
</body>
</html>
<?php
// Cerrar el buffer de salida
ob_end_flush();
?>
