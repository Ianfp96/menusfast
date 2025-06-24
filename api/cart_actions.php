<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cart_functions.php';

header('Content-Type: application/json');

// Verificar método de la petición
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener acción
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            // Validar datos requeridos
            if (!isset($_POST['restaurant_id'], $_POST['product_id'], $_POST['quantity'], $_POST['price'])) {
                throw new Exception('Faltan datos requeridos');
            }
            
            // Obtener o crear sesión del carrito
            $cart = getCartSession($_POST['restaurant_id']);
            
            // Agregar al carrito
            $selected_options = isset($_POST['options']) ? json_encode($_POST['options']) : null;
            $success = addToCart(
                $cart['id'],
                $_POST['product_id'],
                (int)$_POST['quantity'],
                (float)$_POST['price'],
                $selected_options
            );
            
            if ($success) {
                // Obtener items actualizados
                $items = getCartItems($cart['id']);
                $total = getCartTotal($cart['id']);
                
                echo json_encode([
                    'success' => true,
                    'cart_count' => count($items),
                    'cart_total' => $total,
                    'message' => 'Producto agregado al carrito'
                ]);
            } else {
                throw new Exception('Error al agregar al carrito');
            }
            break;
            
        case 'checkout':
            // Validar datos requeridos
            if (!isset($_POST['customer_name'], $_POST['customer_phone'], $_POST['delivery_method'])) {
                throw new Exception('Faltan datos requeridos');
            }
            
            // Validar dirección si es delivery
            if ($_POST['delivery_method'] === 'delivery' && empty($_POST['customer_address'])) {
                throw new Exception('La dirección es requerida para delivery');
            }
            
            // Obtener carrito actual
            $cart = getCartSession($_POST['restaurant_id']);
            
            // Actualizar información del cliente
            $success = updateCartCustomerInfo(
                $cart['id'],
                $_POST['customer_name'],
                $_POST['customer_phone'],
                $_POST['customer_address'] ?? null,
                $_POST['delivery_method'],
                $_POST['notes'] ?? null
            );
            
            if ($success) {
                // Generar URL de WhatsApp
                $whatsapp_url = generateWhatsAppMessage($cart['id']);
                
                echo json_encode([
                    'success' => true,
                    'whatsapp_url' => $whatsapp_url,
                    'message' => 'Pedido procesado correctamente'
                ]);
            } else {
                throw new Exception('Error al procesar el pedido');
            }
            break;
            
        case 'update_quantity':
            if (!isset($_POST['item_id'], $_POST['quantity'])) {
                throw new Exception('Faltan datos requeridos');
            }
            
            $success = updateCartItemQuantity($_POST['item_id'], (int)$_POST['quantity']);
            
            if ($success) {
                $cart = getCartSession($_POST['restaurant_id']);
                $total = getCartTotal($cart['id']);
                
                echo json_encode([
                    'success' => true,
                    'cart_total' => $total,
                    'message' => 'Cantidad actualizada'
                ]);
            } else {
                throw new Exception('Error al actualizar cantidad');
            }
            break;
            
        case 'remove':
            if (!isset($_POST['item_id'])) {
                throw new Exception('Falta ID del item');
            }
            
            $success = removeCartItem($_POST['item_id']);
            
            if ($success) {
                $cart = getCartSession($_POST['restaurant_id']);
                $total = getCartTotal($cart['id']);
                
                echo json_encode([
                    'success' => true,
                    'cart_total' => $total,
                    'message' => 'Producto eliminado del carrito'
                ]);
            } else {
                throw new Exception('Error al eliminar producto');
            }
            break;
            
        case 'update_customer_info':
            if (!isset($_POST['cart_id'], $_POST['customer_name'], $_POST['customer_phone'], $_POST['delivery_method'])) {
                throw new Exception('Faltan datos requeridos');
            }
            
            // Validar método de entrega
            if ($_POST['delivery_method'] === 'delivery' && empty($_POST['customer_address'])) {
                throw new Exception('La dirección es requerida para delivery');
            }
            
            $success = updateCartCustomerInfo(
                $_POST['cart_id'],
                $_POST['customer_name'],
                $_POST['customer_phone'],
                $_POST['customer_address'] ?? null,
                $_POST['delivery_method'],
                $_POST['notes'] ?? null
            );
            
            if ($success) {
                // Generar URL de WhatsApp
                $whatsapp_url = generateWhatsAppMessage($_POST['cart_id']);
                
                echo json_encode([
                    'success' => true,
                    'whatsapp_url' => $whatsapp_url,
                    'message' => 'Información actualizada'
                ]);
            } else {
                throw new Exception('Error al actualizar información');
            }
            break;
            
        case 'clear':
            if (!isset($_POST['cart_id'])) {
                throw new Exception('Falta ID del carrito');
            }
            
            $success = clearCart($_POST['cart_id']);
            
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Carrito vaciado'
                ]);
            } else {
                throw new Exception('Error al vaciar carrito');
            }
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?> 
