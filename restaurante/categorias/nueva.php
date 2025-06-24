<?php
require_once '../../config/database.php';
require_once '../validations.php';
requireLogin('restaurant');

$restaurant_id = $_SESSION['restaurant_id'];
$error = '';
$message = '';

// Validar límites del plan antes de mostrar el formulario
$validation = checkPlanLimits($restaurant_id, 'category');
if (!$validation['success']) {
    $_SESSION['error'] = $validation['message'];
    redirect('restaurante/categorias');
}

// Procesar formulario
if ($_POST) {
    // Validar nuevamente antes de procesar
    $validation = checkPlanLimits($restaurant_id, 'category');
    if (!$validation['success']) {
        $error = strip_tags($validation['message']);
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        if (empty($name)) {
            $error = 'El nombre de la categoría es obligatorio';
        } else {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Verificar que no exista una categoría con el mismo nombre
            $query = "SELECT id FROM menu_categories WHERE restaurant_id = :restaurant_id AND name = :name";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':restaurant_id', $restaurant_id);
            $stmt->bindParam(':name', $name);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error = 'Ya existe una categoría con este nombre';
            } else {
                $query = "INSERT INTO menu_categories (restaurant_id, name, description, sort_order) 
                          VALUES (:restaurant_id, :name, :description, :sort_order)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':restaurant_id', $restaurant_id);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':sort_order', $sort_order);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = 'Categoría creada exitosamente';
                    redirect('restaurante/categorias');
                } else {
                    $error = 'Error al crear la categoría';
                }
            }
        }
    }
}

// Obtener número de categorías actuales para sugerir orden
$db = new Database();
$conn = $db->getConnection();
$query = "SELECT COUNT(*) as count FROM menu_categories WHERE restaurant_id = :restaurant_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$category_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Categoría - <?= $_SESSION['restaurant_name'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Crear Nueva Categoría</h5>
                        <a href="/restaurante/categorias" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Mostrar alerta de límites si está cerca -->
                        <?php showLimitAlert($restaurant_id, 'categories'); ?>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre de la Categoría *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required
                                       placeholder="Ej: Bebidas, Platos Principales, Postres...">
                                <small class="text-muted">Este nombre aparecerá en tu menú público</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Descripción opcional de la categoría..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <small class="text-muted">Ayuda a tus clientes a entender qué tipo de productos encontrarán</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sort_order" class="form-label">Orden de Aparición</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                       value="<?= $_POST['sort_order'] ?? ($category_count + 1) ?>" min="0">
                                <small class="text-muted">Las categorías con número menor aparecen primero en el menú</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-lightbulb"></i>
                                <strong>Consejos:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Usa nombres claros y descriptivos</li>
                                    <li>Ordena las categorías por importancia o flujo de comida</li>
                                    <li>Puedes cambiar el orden más tarde arrastrando las categorías</li>
                                </ul>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="/restaurante/categorias" class="btn btn-secondary">
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Crear Categoría
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
