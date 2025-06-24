<?php
require_once __DIR__ . '/database.php';

// Función para obtener o crear una sesión de carrito
function getCartSession($restaurant_id) {
    global $conn;
    
    try {
        // Intentar obtener el carrito de la sesión
        if (!isset($_SESSION['cart_id'])) {
            // Crear nueva sesión de carrito
            $cart_id = md5(uniqid() . time());
            $stmt = $conn->prepare("INSERT INTO cart_sessions (id, restaurant_id) VALUES (?, ?)");
            $stmt->execute([$cart_id, $restaurant_id]);
            $_SESSION['cart_id'] = $cart_id;
        }
        
        // Verificar si el carrito existe y pertenece al restaurante actual
        $stmt = $conn->prepare("SELECT * FROM cart_sessions WHERE id = ? AND restaurant_id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['cart_id'], $restaurant_id]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cart) {
            // Si no existe o pertenece a otro restaurante, crear uno nuevo
            $cart_id = md5(uniqid() . time());
            $stmt = $conn->prepare("INSERT INTO cart_sessions (id, restaurant_id) VALUES (?, ?)");
            $stmt->execute([$cart_id, $restaurant_id]);
            $_SESSION['cart_id'] = $cart_id;
            
            $stmt = $conn->prepare("SELECT * FROM cart_sessions WHERE id = ?");
            $stmt->execute([$cart_id]);
            $cart = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $cart;
    } catch (Exception $e) {
        error_log("Error en getCartSession: " . $e->getMessage());
        // Retornar un carrito por defecto en caso de error
        return [
            'id' => md5(uniqid() . time()),
            'restaurant_id' => $restaurant_id,
            'status' => 'active'
        ];
    }
}

// Función para agregar producto al carrito
function addToCart($cart_id, $product_id, $quantity, $unit_price, $selected_options = null) {
    global $conn;
    
    try {
        // Verificar si el producto ya está en el carrito
        $stmt = $conn->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cart_id, $product_id]);
        $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_item) {
            // Actualizar cantidad si ya existe
            $new_quantity = $existing_item['quantity'] + $quantity;
            $stmt = $conn->prepare("UPDATE cart_items SET quantity = ?, unit_price = ?, selected_options = ? WHERE id = ?");
            $stmt->execute([$new_quantity, $unit_price, $selected_options, $existing_item['id']]);
        } else {
            // Insertar nuevo item
            $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, unit_price, selected_options) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$cart_id, $product_id, $quantity, $unit_price, $selected_options]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error al agregar al carrito: " . $e->getMessage());
        return false;
    }
}

// Función para obtener items del carrito
function getCartItems($cart_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT ci.*, p.name, p.image 
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.cart_id = ?
            ORDER BY ci.created_at DESC
        ");
        $stmt->execute([$cart_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error en getCartItems: " . $e->getMessage());
        return [];
    }
}

// Función para actualizar cantidad de un item
function updateCartItemQuantity($item_id, $quantity) {
    global $conn;
    
    try {
        if ($quantity <= 0) {
            // Eliminar item si la cantidad es 0 o negativa
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ?");
            $stmt->execute([$item_id]);
        } else {
            // Actualizar cantidad
            $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $stmt->execute([$quantity, $item_id]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Error al actualizar cantidad: " . $e->getMessage());
        return false;
    }
}

// Función para eliminar item del carrito
function removeCartItem($item_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ?");
        $stmt->execute([$item_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al eliminar item del carrito: " . $e->getMessage());
        return false;
    }
}

// Función para actualizar información del cliente
function updateCartCustomerInfo($cart_id, $customer_name, $customer_phone, $customer_address, $delivery_method, $notes) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            UPDATE cart_sessions 
            SET customer_name = ?, 
                customer_phone = ?, 
                customer_address = ?, 
                delivery_method = ?, 
                notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$customer_name, $customer_phone, $customer_address, $delivery_method, $notes, $cart_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al actualizar información del cliente: " . $e->getMessage());
        return false;
    }
}

// Función para calcular el total del carrito
function getCartTotal($cart_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT SUM(quantity * unit_price) as total
            FROM cart_items
            WHERE cart_id = ?
        ");
        $stmt->execute([$cart_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error en getCartTotal: " . $e->getMessage());
        return 0;
    }
}

// Función para generar mensaje de WhatsApp
function generateWhatsAppMessage($cart_id) {
    global $conn;
    
    // Obtener información del carrito y restaurante
    $stmt = $conn->prepare("
        SELECT cs.*, r.name as restaurant_name, r.whatsapp_url
        FROM cart_sessions cs
        JOIN restaurants r ON cs.restaurant_id = r.id
        WHERE cs.id = ?
    ");
    $stmt->execute([$cart_id]);
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cart) return null;
    
    // Obtener items del carrito
    $items = getCartItems($cart_id);
    
    // Construir mensaje
    $message = "¡Hola! Me gustaría hacer un pedido:\n\n";
    $message .= "Restaurante: " . $cart['restaurant_name'] . "\n\n";
    
    foreach ($items as $item) {
        // Usar formatCurrency con el restaurant_id específico para evitar problemas de sesión
        $message .= "- " . $item['name'] . " x" . $item['quantity'] . " (" . formatCurrency($item['unit_price'], $cart['restaurant_id']) . " c/u)\n";
        if ($item['selected_options']) {
            $options = json_decode($item['selected_options'], true);
            if (is_array($options)) {
                foreach ($options as $option) {
                    $message .= "  * " . $option['name'] . "\n";
                }
            }
        }
    }
    
    $message .= "\nTotal: " . formatCurrency(getCartTotal($cart_id), $cart['restaurant_id']) . "\n\n";
    
    if ($cart['delivery_method'] === 'delivery') {
        $message .= "Método de entrega: Delivery\n";
        $message .= "Dirección: " . $cart['customer_address'] . "\n";
    } else {
        $message .= "Método de entrega: Retiro en local\n";
    }
    
    $message .= "Nombre: " . $cart['customer_name'] . "\n";
    $message .= "Teléfono: " . $cart['customer_phone'] . "\n";
    
    if ($cart['notes']) {
        $message .= "\nNotas adicionales:\n" . $cart['notes'];
    }
    
    // Generar URL de WhatsApp
    $whatsapp_number = preg_replace('/[^0-9]/', '', $cart['whatsapp_url']);
    $encoded_message = urlencode($message);
    return "https://wa.me/" . $whatsapp_number . "?text=" . $encoded_message;
}

// Función para limpiar el carrito
function clearCart($cart_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ?");
        $stmt->execute([$cart_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al limpiar el carrito: " . $e->getMessage());
        return false;
    }
}
?> 
