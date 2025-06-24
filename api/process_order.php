<?php
// Configurar codificación UTF-8
header('Content-Type: application/json; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

try {
    // Validar datos requeridos
    $required_fields = ['customer_name', 'customer_phone', 'delivery_method', 'restaurant_id', 'cart_items'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    // Obtener y validar datos del formulario
    $restaurant_id = filter_var($_POST['restaurant_id'], FILTER_VALIDATE_INT);
    $customer_name = filter_var($_POST['customer_name'], FILTER_SANITIZE_STRING);
    $customer_phone = filter_var($_POST['customer_phone'], FILTER_SANITIZE_STRING);
    $delivery_method = filter_var($_POST['delivery_method'], FILTER_SANITIZE_STRING);
    $customer_address = isset($_POST['customer_address']) ? filter_var($_POST['customer_address'], FILTER_SANITIZE_STRING) : null;
    $notes = isset($_POST['notes']) ? filter_var($_POST['notes'], FILTER_SANITIZE_STRING) : null;
    $cart_items = json_decode($_POST['cart_items'], true);
    $whatsapp_number = filter_var($_POST['whatsapp_number'], FILTER_SANITIZE_STRING);
    $whatsapp_message = filter_var($_POST['whatsapp_message'], FILTER_SANITIZE_STRING);

    // Información del cupón (opcional)
    $coupon_id = isset($_POST['coupon_id']) ? filter_var($_POST['coupon_id'], FILTER_VALIDATE_INT) : null;
    $coupon_code = isset($_POST['coupon_code']) ? filter_var($_POST['coupon_code'], FILTER_SANITIZE_STRING) : null;
    $discount_amount = isset($_POST['discount_amount']) ? filter_var($_POST['discount_amount'], FILTER_VALIDATE_FLOAT) : 0;

    if (!$restaurant_id || !$customer_name || !$customer_phone || !$delivery_method || !$cart_items) {
        throw new Exception("Datos inválidos");
    }

    // Calcular total del pedido
    $total = 0;
    foreach ($cart_items as $item) {
        $item_total = $item['price'] * $item['quantity'];
        if (isset($item['selected_options'])) {
            $options = json_decode($item['selected_options'], true);
            foreach ($options as $option) {
                if (isset($option['price'])) {
                    $item_total += (float)$option['price'] * $item['quantity'];
                }
            }
        }
        $total += $item_total;
    }

    // Aplicar descuento del cupón si existe
    $final_total = $total;
    if ($coupon_id && $discount_amount > 0) {
        $final_total = max(0, $total - $discount_amount);
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Insertar pedido en la base de datos
    $stmt = $conn->prepare("
        INSERT INTO orders (
            restaurant_id,
            customer_name,
            customer_phone,
            order_type,
            delivery_address,
            notes,
            items,
            total,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    $stmt->execute([
        $restaurant_id,
        $customer_name,
        $customer_phone,
        $delivery_method,
        $customer_address,
        $notes,
        json_encode($cart_items),
        $final_total
    ]);

    $order_id = $conn->lastInsertId();

    // Registrar el uso del cupón si existe
    if ($coupon_id && $discount_amount > 0) {
        $stmt = $conn->prepare("
            INSERT INTO coupon_usage (
                coupon_id, order_id, customer_name, customer_phone, 
                discount_amount, order_total
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $coupon_id, $order_id, $customer_name, $customer_phone,
            $discount_amount, $total
        ]);

        // Actualizar el contador de usos del cupón
        $stmt = $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
        $stmt->execute([$coupon_id]);
    }

    // Obtener el mensaje personalizado de WhatsApp del restaurante
    $stmt = $conn->prepare("SELECT whatsapp_order_message FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Usar el mensaje personalizado o un mensaje por defecto
    $custom_message = $restaurant_data['whatsapp_order_message'] ?? '¡Hola! Me gustaría hacer el siguiente pedido:';

    // Generar mensaje para WhatsApp (versión sin emojis - principal)
    $message = $custom_message . "\n";
    $message .= "\n";
    $message .= "Pedido #" . $order_id . "\n\n";
    $message .= "Cliente: " . $customer_name . "\n";
    $message .= "Teléfono: " . $customer_phone . "\n";
    $message .= "Método de entrega: " . ($delivery_method === 'delivery' ? 'Delivery' : 'Retiro en local') . "\n";
    
    if ($delivery_method === 'delivery' && $customer_address) {
        $message .= "Dirección: " . $customer_address . "\n";
    }
    
    if ($notes) {
        $message .= "Notas: " . $notes . "\n";
    }
    
    $message .= "\n*PEDIDO:*\n";
    
    foreach ($cart_items as $item) {
        $message .= "• " . $item['name'] . " x" . $item['quantity'] . "\n";
        
        // Agregar enlace del producto si está disponible
        if (isset($item['id'])) {
            $product_url = BASE_URL . "/product.php?id=" . $item['id'];
            $message .= "  *Ver:* " . $product_url . "\n";
        }
        
        if (isset($item['selected_options'])) {
            $options = json_decode($item['selected_options'], true);
            foreach ($options as $option) {
                $message .= "  ◦ " . $option['name'] . "\n";
            }
        }
    }
    
    $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━\n";
    if ($coupon_code && $discount_amount > 0) {
        $message .= "*Subtotal:* $" . number_format($total, 0) . "\n";
        $message .= "*Descuento (" . $coupon_code . "):* -$" . number_format($discount_amount, 0) . "\n";
        $message .= "*TOTAL:* $" . number_format($final_total, 0) . "\n";
    } else {
        $message .= "*TOTAL:* $" . number_format($final_total, 0) . "\n";
    }
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━";

    // Verificar que el mensaje esté correctamente codificado
    if (!mb_check_encoding($message, 'UTF-8')) {
        $message = mb_convert_encoding($message, 'UTF-8', 'auto');
    }

    // Generar URL de WhatsApp (versión sin emojis)
    $whatsapp_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $whatsapp_number) . "?text=" . urlencode($message);

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'success' => true,
        'whatsapp_url' => $whatsapp_url,
        'order_id' => $order_id
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
