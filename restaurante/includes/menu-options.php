<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

class MenuOptions {
    private $conn;
    private $restaurant_id;

    public function __construct($conn, $restaurant_id) {
        $this->conn = $conn;
        $this->restaurant_id = $restaurant_id;
    }

    // Obtener todas las opciones de un producto
    public function getProductOptions($product_id) {
        try {
            // Verificar que el producto pertenece al restaurante
            $stmt = $this->conn->prepare("
                SELECT p.id 
                FROM products p 
                WHERE p.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$product_id, $this->restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Producto no encontrado o no pertenece al restaurante");
            }

            // Obtener las opciones
            $stmt = $this->conn->prepare("
                SELECT mo.*, 
                       (SELECT COUNT(*) FROM product_menu_option_values WHERE option_id = mo.id) as values_count
                FROM product_menu_options mo
                WHERE mo.product_id = ?
                ORDER BY mo.sort_order ASC
            ");
            $stmt->execute([$product_id]);
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener los valores para cada opción
            foreach ($options as &$option) {
                $stmt = $this->conn->prepare("
                    SELECT * FROM product_menu_option_values 
                    WHERE option_id = ?
                    ORDER BY sort_order ASC
                ");
                $stmt->execute([$option['id']]);
                $option['values'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $options;
        } catch (Exception $e) {
            error_log("Error al obtener opciones del producto: " . $e->getMessage());
            throw $e;
        }
    }

    // Agregar una nueva opción
    public function addOption($product_id, $data) {
        try {
            $this->conn->beginTransaction();

            // Verificar que el producto pertenece al restaurante
            $stmt = $this->conn->prepare("
                SELECT id FROM products 
                WHERE id = ? AND restaurant_id = ?
            ");
            $stmt->execute([$product_id, $this->restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Producto no encontrado o no pertenece al restaurante");
            }

            // Obtener el siguiente sort_order
            $stmt = $this->conn->prepare("
                SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order 
                FROM product_menu_options 
                WHERE product_id = ?
            ");
            $stmt->execute([$product_id]);
            $next_order = $stmt->fetchColumn();

            // Insertar la opción
            $stmt = $this->conn->prepare("
                INSERT INTO product_menu_options (
                    product_id, name, description, type, 
                    is_required, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $product_id,
                $data['name'],
                $data['description'] ?? null,
                $data['type'] ?? 'single',
                $data['is_required'] ?? 0,
                $next_order
            ]);

            $option_id = $this->conn->lastInsertId();

            // Si hay valores, insertarlos
            if (!empty($data['values'])) {
                $stmt = $this->conn->prepare("
                    INSERT INTO product_menu_option_values (
                        option_id, name, price, sort_order
                    ) VALUES (?, ?, ?, ?)
                ");

                $sort_order = 0;
                foreach ($data['values'] as $value) {
                    $sort_order++;
                    $stmt->execute([
                        $option_id,
                        $value['name'],
                        $value['price'] ?? 0,
                        $sort_order
                    ]);
                }
            }

            $this->conn->commit();
            return $option_id;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error al agregar opción: " . $e->getMessage());
            throw $e;
        }
    }

    // Actualizar una opción existente
    public function updateOption($option_id, $data) {
        try {
            $this->conn->beginTransaction();

            // Verificar que la opción pertenece a un producto del restaurante
            $stmt = $this->conn->prepare("
                SELECT mo.id 
                FROM product_menu_options mo
                JOIN products p ON mo.product_id = p.id
                WHERE mo.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$option_id, $this->restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Opción no encontrada o no pertenece al restaurante");
            }

            // Actualizar la opción
            $stmt = $this->conn->prepare("
                UPDATE product_menu_options 
                SET name = ?, 
                    description = ?, 
                    type = ?, 
                    is_required = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['type'] ?? 'single',
                $data['is_required'] ?? 0,
                $option_id
            ]);

            // Si hay valores, actualizarlos
            if (isset($data['values'])) {
                // Primero eliminar todos los valores existentes
                $stmt = $this->conn->prepare("
                    DELETE FROM product_menu_option_values 
                    WHERE option_id = ?
                ");
                $stmt->execute([$option_id]);

                // Luego insertar los nuevos valores
                $stmt = $this->conn->prepare("
                    INSERT INTO product_menu_option_values (
                        option_id, name, price, sort_order
                    ) VALUES (?, ?, ?, ?)
                ");

                $sort_order = 0;
                foreach ($data['values'] as $value) {
                    $sort_order++;
                    $stmt->execute([
                        $option_id,
                        $value['name'],
                        $value['price'] ?? 0,
                        $sort_order
                    ]);
                }
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error al actualizar opción: " . $e->getMessage());
            throw $e;
        }
    }

    // Eliminar una opción
    public function deleteOption($option_id) {
        try {
            // Verificar que la opción pertenece a un producto del restaurante
            $stmt = $this->conn->prepare("
                SELECT mo.id 
                FROM product_menu_options mo
                JOIN products p ON mo.product_id = p.id
                WHERE mo.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$option_id, $this->restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Opción no encontrada o no pertenece al restaurante");
            }

            // Eliminar la opción (esto eliminará en cascada los valores)
            $stmt = $this->conn->prepare("DELETE FROM product_menu_options WHERE id = ?");
            return $stmt->execute([$option_id]);
        } catch (Exception $e) {
            error_log("Error al eliminar opción: " . $e->getMessage());
            throw $e;
        }
    }

    // Agregar un valor a una opción
    public function addOptionValue($option_id, $data) {
        try {
            // Verificar que la opción pertenece a un producto del restaurante
            $stmt = $this->conn->prepare("
                SELECT mo.id 
                FROM product_menu_options mo
                JOIN products p ON mo.product_id = p.id
                WHERE mo.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$option_id, $this->restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Opción no encontrada o no pertenece al restaurante");
            }

            // Obtener el siguiente sort_order
            $stmt = $this->conn->prepare("
                SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order 
                FROM product_menu_option_values 
                WHERE option_id = ?
            ");
            $stmt->execute([$option_id]);
            $next_order = $stmt->fetchColumn();

            // Insertar el valor
            $stmt = $this->conn->prepare("
                INSERT INTO product_menu_option_values (
                    option_id, name, price, sort_order
                ) VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $option_id,
                $data['name'],
                $data['price'] ?? 0,
                $next_order
            ]);

            return $this->conn->lastInsertId();
        } catch (Exception $e) {
            error_log("Error al agregar valor de opción: " . $e->getMessage());
            throw $e;
        }
    }

    // Eliminar un valor de una opción
    public function deleteOptionValue($value_id) {
        try {
            // Verificar que el valor pertenece a una opción de un producto del restaurante
            $stmt = $this->conn->prepare("
                SELECT mov.id 
                FROM product_menu_option_values mov
                JOIN product_menu_options mo ON mov.option_id = mo.id
                JOIN products p ON mo.product_id = p.id
                WHERE mov.id = ? AND p.restaurant_id = ?
            ");
            $stmt->execute([$value_id, $this->restaurant_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Valor no encontrado o no pertenece al restaurante");
            }

            // Eliminar el valor
            $stmt = $this->conn->prepare("DELETE FROM product_menu_option_values WHERE id = ?");
            return $stmt->execute([$value_id]);
        } catch (Exception $e) {
            error_log("Error al eliminar valor de opción: " . $e->getMessage());
            throw $e;
        }
    }
}
?> 
