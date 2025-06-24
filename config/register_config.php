<?php
// Configuración para el registro de restaurantes
class RegisterConfig {
    // Configuración de validación
    const MIN_PASSWORD_LENGTH = 8;
    const MAX_NAME_LENGTH = 100;
    const MAX_EMAIL_LENGTH = 100;
    const MAX_PHONE_LENGTH = 20;
    const MAX_ADDRESS_LENGTH = 255;
    
    // Mensajes de error
    const ERROR_MESSAGES = [
        'required' => 'El campo %s es obligatorio',
        'email' => 'El email no es válido',
        'password_length' => 'La contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres',
        'password_match' => 'Las contraseñas no coinciden',
        'name_length' => 'El nombre no puede tener más de ' . self::MAX_NAME_LENGTH . ' caracteres',
        'email_length' => 'El email no puede tener más de ' . self::MAX_EMAIL_LENGTH . ' caracteres',
        'phone_length' => 'El teléfono no puede tener más de ' . self::MAX_PHONE_LENGTH . ' caracteres',
        'address_length' => 'La dirección no puede tener más de ' . self::MAX_ADDRESS_LENGTH . ' caracteres',
        'email_exists' => 'Este email ya está registrado',
        'slug_exists' => 'Este nombre ya está en uso, por favor elige otro',
        'invalid_chars' => 'El nombre solo puede contener letras, números, espacios y guiones',
        'plan_not_found' => 'El plan seleccionado no existe',
        'upload_error' => 'Error al subir la imagen',
        'invalid_image' => 'El archivo debe ser una imagen válida (JPG, PNG, GIF)',
        'image_size' => 'La imagen no puede ser mayor a 2MB'
    ];

    // Configuración de archivos
    const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    const MAX_IMAGE_SIZE = 2 * 1024 * 1024; // 2MB
    const UPLOAD_DIR = __DIR__ . '/../uploads/';
    const LOGO_DIR = 'logos/';
    const BANNER_DIR = 'banners/';

    // Configuración de planes
    const DEFAULT_PLAN = 'free'; // Plan gratuito por defecto
    const TRIAL_DAYS = 7; // Días de prueba gratis
    const TRIAL_FEATURES = [
        'access_all_features' => true,
        'no_credit_card' => true,
        'easy_setup' => true
    ];

    // Validar datos del registro
    public static function validateRegistration($data) {
        $errors = [];
        
        // Validar campos requeridos
        $required_fields = ['name', 'email', 'password', 'confirm_password', 'phone', 'address'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = sprintf(self::ERROR_MESSAGES['required'], $field);
            }
        }

        // Validar email
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = self::ERROR_MESSAGES['email'];
            }
            if (strlen($data['email']) > self::MAX_EMAIL_LENGTH) {
                $errors['email'] = self::ERROR_MESSAGES['email_length'];
            }
        }

        // Validar contraseña
        if (!empty($data['password'])) {
            if (strlen($data['password']) < self::MIN_PASSWORD_LENGTH) {
                $errors['password'] = self::ERROR_MESSAGES['password_length'];
            }
            if ($data['password'] !== $data['confirm_password']) {
                $errors['confirm_password'] = self::ERROR_MESSAGES['password_match'];
            }
        }

        // Validar nombre
        if (!empty($data['name'])) {
            if (strlen($data['name']) > self::MAX_NAME_LENGTH) {
                $errors['name'] = self::ERROR_MESSAGES['name_length'];
            }
            if (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $data['name'])) {
                $errors['name'] = self::ERROR_MESSAGES['invalid_chars'];
            }
        }

        // Validar teléfono
        if (!empty($data['phone']) && strlen($data['phone']) > self::MAX_PHONE_LENGTH) {
            $errors['phone'] = self::ERROR_MESSAGES['phone_length'];
        }

        // Validar dirección
        if (!empty($data['address']) && strlen($data['address']) > self::MAX_ADDRESS_LENGTH) {
            $errors['address'] = self::ERROR_MESSAGES['address_length'];
        }

        return $errors;
    }

    // Generar slug único para el restaurante
    public static function generateSlug($name) {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9\s\-]/', '', $name)));
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return $slug;
    }

    // Validar y procesar imagen
    public static function processImage($file, $type = 'logo') {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception(self::ERROR_MESSAGES['upload_error']);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception(self::ERROR_MESSAGES['upload_error']);
        }

        if (!in_array($file['type'], self::ALLOWED_IMAGE_TYPES)) {
            throw new Exception(self::ERROR_MESSAGES['invalid_image']);
        }

        if ($file['size'] > self::MAX_IMAGE_SIZE) {
            throw new Exception(self::ERROR_MESSAGES['image_size']);
        }

        $upload_dir = self::UPLOAD_DIR . ($type === 'logo' ? self::LOGO_DIR : self::BANNER_DIR);
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = uniqid() . '_' . basename($file['name']);
        $filepath = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception(self::ERROR_MESSAGES['upload_error']);
        }

        return ($type === 'logo' ? self::LOGO_DIR : self::BANNER_DIR) . $filename;
    }

    // Verificar si el email ya existe
    public static function emailExists($email, $db) {
        $stmt = $db->prepare("SELECT id FROM restaurants WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->rowCount() > 0;
    }

    // Verificar si el slug ya existe
    public static function slugExists($slug, $db) {
        $stmt = $db->prepare("SELECT id FROM restaurants WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->rowCount() > 0;
    }

    // Obtener el ID del plan por defecto
    public static function getDefaultPlanId($db) {
        try {
            // Primero intentamos obtener el plan gratuito
            $stmt = $db->prepare("SELECT id FROM plans WHERE slug = ? AND is_active = 1 AND is_free = 1");
            $stmt->execute([self::DEFAULT_PLAN]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                // Si no existe el plan gratuito, lo creamos
                $stmt = $db->prepare("INSERT INTO plans (name, slug, price, max_branches, max_products, max_categories, features, is_active, is_free) 
                    VALUES ('Gratuito', 'free', 0.00, 1, 5, 1, ?, 1, 1)");
                $features = json_encode([
                    'Menú digital básico',
                    'Panel de administración simple',
                    'Soporte por email',
                    'Prueba gratis de ' . self::TRIAL_DAYS . ' días'
                ]);
                $stmt->execute([$features]);
                return $db->lastInsertId();
            }
            
            return $result['id'];
        } catch (PDOException $e) {
            error_log("Error al obtener/crear plan gratuito: " . $e->getMessage());
            return null;
        }
    }

    // Verificar si el restaurante está en período de prueba
    public static function isInTrialPeriod($created_at) {
        $trial_end = strtotime($created_at . ' + ' . self::TRIAL_DAYS . ' days');
        return time() <= $trial_end;
    }

    // Obtener días restantes de prueba
    public static function getRemainingTrialDays($created_at) {
        $trial_end = strtotime($created_at . ' + ' . self::TRIAL_DAYS . ' days');
        $remaining = ceil(($trial_end - time()) / (60 * 60 * 24));
        return max(0, $remaining);
    }
}
?> 
