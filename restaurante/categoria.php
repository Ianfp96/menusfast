<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

session_start();

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    redirect(BASE_URL . '/restaurante/login.php');
}

$restaurant_id = $_SESSION['restaurant_id'];
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($category_id)) {
    redirect(BASE_URL . '/restaurante/menu.php');
}

try {
    // Obtener información de la categoría
    $stmt = $conn->prepare("
        SELECT c.*, r.name as restaurant_name 
        FROM menu_categories c
        JOIN restaurants r ON c.restaurant_id = r.id
        WHERE c.id = ? AND c.restaurant_id = ? AND c.is_active = 1
    ");
    $stmt->execute([$category_id, $restaurant_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        redirect(BASE_URL . '/restaurante/menu.php');
    }

    // Obtener productos de la categoría
    $stmt = $conn->prepare("
        SELECT p.*, 
               COALESCE(p.menu_options, '{\"options\":[]}') as menu_options,
               (SELECT COUNT(*) FROM menu_options WHERE product_id = p.id) as options_count
        FROM products p
        WHERE p.category_id = ? 
          AND p.restaurant_id = ? 
          AND p.is_active = 1 
          AND p.is_available = 1
        ORDER BY p.is_featured DESC, p.sort_order ASC, p.name ASC
    ");
    $stmt->execute([$category_id, $restaurant_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decodificar menu_options para cada producto
    foreach ($products as &$product) {
        $product['menu_options'] = json_decode($product['menu_options'], true);
        $product['has_options'] = !empty($product['menu_options']['options']);
    }
    unset($product);

} catch (PDOException $e) {
    error_log("Error en categoria.php: " . $e->getMessage());
    $error = "Error al cargar los datos de la categoría";
}

// Generar token CSRF
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($category['name']) ?> - <?= htmlspecialchars($category['restaurant_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #00d4aa;
            --dark: #1a1a1a;
            --gray-50: #f8f9fa;
            --gray-100: #f1f3f5;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }

        body {
            background: var(--gray-50);
            color: var(--gray-900);
        }

        .category-header {
            background: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .category-image {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
        }

        .category-info {
            padding: 1rem;
        }

        .category-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .category-description {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 1.5rem;
        }

        .product-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-content {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .product-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .product-description {
            color: var(--gray-600);
            margin-bottom: 1rem;
            flex-grow: 1;
        }

        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }

        .product-price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
        }

        .add-button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
        }

        .add-button:hover {
            background: #00b894;
            transform: translateY(-2px);
        }

        .back-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--primary);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: var(--shadow);
            transition: var(--transition);
            z-index: 1000;
        }

        .back-button:hover {
            background: #00b894;
            transform: translateY(-2px);
            color: white;
        }

        @media (max-width: 768px) {
            .category-header {
                padding: 1rem 0;
            }

            .category-image {
                width: 150px;
                height: 150px;
            }

            .category-title {
                font-size: 2rem;
            }

            .category-description {
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .category-image {
                width: 120px;
                height: 120px;
            }

            .category-title {
                font-size: 1.75rem;
            }

            .product-image {
                height: 180px;
            }
        }
    </style>
</head>
<body>
    <!-- Header de la categoría -->
    <header class="category-header">
        <div class="container">
            <div class="row align-items-center">
                <?php if ($category['image']): ?>
                    <div class="col-md-3 text-center">
                        <img src="/uploads/<?= htmlspecialchars($category['image']) ?>" 
                             alt="<?= htmlspecialchars($category['name']) ?>" 
                             class="category-image">
                    </div>
                <?php endif; ?>
                <div class="col-md-<?= $category['image'] ? '9' : '12' ?>">
                    <div class="category-info">
                        <h1 class="category-title"><?= htmlspecialchars($category['name']) ?></h1>
                        <?php if ($category['description']): ?>
                            <p class="category-description"><?= htmlspecialchars($category['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="container mb-5">
        <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                <h3 class="text-muted">No hay productos en esta categoría</h3>
                <p class="text-muted">Los productos aparecerán aquí cuando los agregues</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-6 col-lg-4">
                        <article class="product-card">
                            <?php if ($product['image']): ?>
                                <img src="/uploads/<?= htmlspecialchars($product['image']) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>" 
                                     class="product-image"
                                     loading="lazy"
                                     onerror="this.onerror=null; this.src='/assets/img/no-image.png';">
                            <?php else: ?>
                                <div class="product-image d-flex align-items-center justify-content-center bg-light">
                                    <i class="fas fa-utensils fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-content">
                                <h2 class="product-name"><?= htmlspecialchars($product['name']) ?></h2>
                                <?php if ($product['description']): ?>
                                    <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                                <?php endif; ?>
                                
                                <?php if ($product['has_options']): ?>
                                    <div class="product-options mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-cog"></i> 
                                            <?= count($product['menu_options']['options']) ?> opciones disponibles
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="product-footer">
                                    <div class="product-price">$<?= number_format($product['price'], 0) ?></div>
                                    <button class="add-button" onclick="addToCart(<?= $product['id'] ?>)">
                                        <i class="fas fa-plus"></i> Agregar
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Botón de volver -->
    <a href="/restaurante/menu.php" class="back-button" title="Volver al menú">
        <i class="fas fa-arrow-left"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para agregar al carrito
        function addToCart(productId) {
            const button = event.target.closest('.add-button');
            const originalText = button.innerHTML;
            
            // Animación de loading
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
            button.disabled = true;
            
            // Simular agregado al carrito
            setTimeout(() => {
                button.innerHTML = '<i class="fas fa-check"></i> Agregado';
                button.style.background = '#28a745';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                    button.style.background = '';
                }, 1500);
            }, 800);
            
            // Aquí puedes agregar la lógica real para agregar al carrito
            console.log('Producto agregado:', productId);
        }
    </script>
</body>
</html> 
