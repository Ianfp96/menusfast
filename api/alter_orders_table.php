<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Agregar todas las columnas necesarias si no existen
    $conn->exec("
        ALTER TABLE orders 
        ADD COLUMN IF NOT EXISTS order_type ENUM('delivery', 'pickup') NOT NULL DEFAULT 'pickup' AFTER customer_phone,
        ADD COLUMN IF NOT EXISTS delivery_address TEXT NULL AFTER order_type,
        ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER delivery_address,
        ADD COLUMN IF NOT EXISTS items JSON NULL AFTER notes,
        ADD COLUMN IF NOT EXISTS total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER items,
        ADD COLUMN IF NOT EXISTS status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending' AFTER total,
        ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status,
        ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at
    ");
    
    echo "Todas las columnas necesarias han sido agregadas exitosamente";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Las columnas ya existen";
    } else {
        echo "Error: " . $e->getMessage();
    }
} 
