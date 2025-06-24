<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Iniciar transacción
    $conn->beginTransaction();
    echo "Iniciando transacción...\n";

    // 1. Crear una tabla temporal con la estructura correcta
    echo "Creando tabla temporal...\n";
    $conn->exec("
        CREATE TABLE orders_new (
            id int(11) NOT NULL AUTO_INCREMENT,
            restaurant_id int(11) NOT NULL,
            customer_name varchar(100) DEFAULT NULL,
            customer_phone varchar(20) DEFAULT NULL,
            order_type enum('delivery','pickup') NOT NULL DEFAULT 'pickup',
            delivery_address text DEFAULT NULL,
            notes text DEFAULT NULL,
            items longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`items`)),
            total decimal(10,2) NOT NULL DEFAULT 0.00,
            customer_email varchar(100) DEFAULT NULL,
            total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            status enum('pending','confirmed','preparing','ready','delivered','cancelled') DEFAULT 'pending',
            order_data longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`order_data`)),
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (id),
            KEY restaurant_id (restaurant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "Tabla temporal creada exitosamente.\n";

    // 2. Copiar los datos de la tabla antigua a la nueva
    echo "Copiando datos...\n";
    $conn->exec("
        INSERT INTO orders_new (
            restaurant_id, customer_name, customer_phone, order_type, 
            delivery_address, notes, items, total, customer_email, 
            total_amount, status, order_data, created_at, updated_at
        )
        SELECT 
            restaurant_id, customer_name, customer_phone, order_type, 
            delivery_address, notes, items, total, customer_email, 
            COALESCE(total_amount, total), status, order_data, created_at, updated_at
        FROM orders
    ");
    echo "Datos copiados exitosamente.\n";

    // 3. Eliminar la tabla antigua
    echo "Eliminando tabla antigua...\n";
    $conn->exec("DROP TABLE orders");
    echo "Tabla antigua eliminada.\n";

    // 4. Renombrar la nueva tabla
    echo "Renombrando tabla...\n";
    $conn->exec("RENAME TABLE orders_new TO orders");
    echo "Tabla renombrada exitosamente.\n";

    // Confirmar transacción
    $conn->commit();
    echo "Transacción completada exitosamente.\n";
    echo "Tabla orders modificada exitosamente. La columna id ahora es AUTO_INCREMENT.\n";
} catch (PDOException $e) {
    // Revertir cambios en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
        echo "Transacción revertida debido a un error.\n";
    }
    echo "Error al modificar la tabla: " . $e->getMessage() . "\n";
    echo "Código de error: " . $e->getCode() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 
