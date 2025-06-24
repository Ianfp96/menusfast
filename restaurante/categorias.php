<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];

// Obtener categorías del restaurante
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id > 0) {
    // Obtener información de la categoría seleccionada
    $query = "SELECT c.*, 
                     (SELECT COUNT(*) FROM products WHERE category_id = c.id AND is_active = 1) as product_count 
              FROM menu_categories c 
              WHERE c.id = :category_id AND c.restaurant_id = :restaurant_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':category_id', $category_id);
    $stmt->bindParam(':restaurant_id', $restaurant_id);
    $stmt->execute();
    $selected_category = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selected_category) {
        // Obtener productos de la categoría seleccionada
        $query = "SELECT p.*, 
                        COALESCE(p.menu_options, '{\"options\":[]}') as menu_options
                 FROM products p
                 WHERE p.category_id = :category_id 
                   AND p.restaurant_id = :restaurant_id 
                   AND p.is_active = 1 
                   AND p.is_available = 1
                 ORDER BY p.is_featured DESC, p.sort_order ASC, p.name ASC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':restaurant_id', $restaurant_id);
        $stmt->execute();
        $category_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decodificar menu_options para cada producto
        foreach ($category_products as &$product) {
            $product['menu_options'] = json_decode($product['menu_options'], true);
            $product['has_options'] = !empty($product['menu_options']['options']);
        }
        unset($product);
    }
}

// Obtener todas las categorías para la navegación
$query = "SELECT c.*, 
                 (SELECT COUNT(*) FROM products WHERE category_id = c.id AND is_active = 1) as product_count 
          FROM menu_categories c 
          WHERE c.restaurant_id = :restaurant_id 
          ORDER BY c.sort_order ASC, c.name ASC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':restaurant_id', $restaurant_id);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Incluir el header
include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <?php if (empty($categories)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-list fa-3x text-muted mb-3"></i>
                    <h3 class="text-muted">No tienes categorías creadas</h3>
                    <p class="text-muted">Las categorías te ayudan a organizar tu menú de manera clara y atractiva</p>
                    <?php if ($planManager->canAddCategory($restaurant_id)): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                            <i class="fas fa-plus"></i> Crear Primera Categoría
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <?php if ($category_id > 0 && $selected_category): ?>
                            <!-- Mostrar productos de la categoría seleccionada -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h2>
                                        <?= htmlspecialchars($selected_category['name']) ?>
                                        <small class="text-muted">(<?= count($category_products) ?> productos)</small>
                                    </h2>
                                    <a href="/restaurante/categorias.php" class="btn btn-outline-primary">
                                        <i class="fas fa-arrow-left"></i> Volver a todas las categorías
                                    </a>
                                </div>
                                
                                <?php if (empty($category_products)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> No hay productos en esta categoría
                                    </div>
                                <?php else: ?>
                                    <div class="row g-4">
                                        <?php foreach ($category_products as $product): ?>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="card h-100">
                                                    <?php if ($product['image']): ?>
                                                        <img src="/uploads/<?= htmlspecialchars($product['image']) ?>" 
                                                             class="card-img-top" 
                                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                                             style="height: 200px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                                             style="height: 200px;">
                                                            <i class="fas fa-utensils fa-3x text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                                        <?php if ($product['description']): ?>
                                                            <p class="card-text text-muted"><?= htmlspecialchars($product['description']) ?></p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($product['has_options']): ?>
                                                            <div class="mb-2">
                                                                <small class="text-muted">
                                                                    <i class="fas fa-cog"></i> 
                                                                    <?= count($product['menu_options']['options']) ?> opciones disponibles
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="h5 mb-0 text-primary"><?= formatCurrency($product['price']) ?></span>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                        onclick="editProduct(<?= $product['id'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                        onclick="deleteProduct(<?= $product['id'] ?>)">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Mostrar lista de todas las categorías -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Mis Categorías (<?= count($categories) ?>)</h5>
                                <small class="text-muted">
                                    <i class="fas fa-arrows-alt"></i> Arrastra para reordenar
                                </small>
                            </div>
                            
                            <div id="categories-container">
                                <?php foreach ($categories as $category): ?>
                                    <div class="category-card p-3 mb-3 <?= !$category['is_active'] ? 'inactive' : '' ?>" 
                                         data-category-id="<?= $category['id'] ?>">
                                        <div class="d-flex">
                                            <div class="col-auto">
                                                <i class="fas fa-grip-vertical drag-handle"></i>
                                            </div>
                                            <div class="col">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($category['image']): ?>
                                                            <img src="/uploads/<?= htmlspecialchars($category['image']) ?>" 
                                                                 alt="<?= htmlspecialchars($category['name']) ?>" 
                                                                 class="img-thumbnail me-3" 
                                                                 style="width: 60px; height: 60px; object-fit: cover;">
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <a href="/restaurante/categorias.php?id=<?= $category['id'] ?>" 
                                                                   class="text-decoration-none">
                                                                    <?= htmlspecialchars($category['name']) ?>
                                                                </a>
                                                                <?php if (!$category['is_active']): ?>
                                                                    <span class="badge bg-secondary ms-2">Inactiva</span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <?php if ($category['description']): ?>
                                                                <p class="text-muted mb-1 small">
                                                                    <?= htmlspecialchars($category['description']) ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            <small class="text-muted">
                                                                <?= $category['product_count'] ?> producto<?= $category['product_count'] != 1 ? 's' : '' ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                                type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item" href="#" 
                                                                   onclick="editCategory(<?= htmlspecialchars(json_encode($category)) ?>)">
                                                                    <i class="fas fa-edit"></i> Editar
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="action" value="toggle_status">
                                                                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                                                    <button type="submit" class="dropdown-item">
                                                                        <?php if ($category['is_active']): ?>
                                                                            <i class="fas fa-eye-slash text-warning"></i> Desactivar
                                                                        <?php else: ?>
                                                                            <i class="fas fa-eye text-success"></i> Activar
                                                                        <?php endif; ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="d-inline" 
                                                                      onsubmit="return confirm('¿Estás seguro de eliminar esta categoría?')">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                                                    <button type="submit" class="dropdown-item text-danger"
                                                                            <?= $category['product_count'] > 0 ? 'disabled title="No se puede eliminar porque tiene productos"' : '' ?>>
                                                                        <i class="fas fa-trash"></i> Eliminar
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 
