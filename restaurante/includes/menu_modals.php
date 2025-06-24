<!-- Modal para agregar categoría -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addCategoryForm">
                    <input type="hidden" name="csrf_token" id="categoryCsrfToken">
                    
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Nombre de la Categoría *</label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Descripción</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="categoryImage" class="form-label">Imagen de la Categoría</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="categoryImage" name="image" accept="image/jpeg,image/png,image/gif">
                                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('categoryImage').click()">
                                        <i class="fas fa-image"></i>
                                    </button>
                                </div>
                                <div class="form-text">Formatos permitidos: JPG, PNG, GIF. Máximo 5MB.</div>
                                <div id="imagePreview" class="mt-2 preview-container" style="display: none;">
                                    <div class="preview-header">
                                        <span>Vista previa de la imagen</span>
                                        <button type="button" class="btn-close btn-close-white" onclick="removeImage('categoryImage', 'imagePreview')"></button>
                                    </div>
                                    <img src="" alt="Vista previa" class="preview-image">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="categoryBanner" class="form-label">Banner de la Categoría</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="categoryBanner" name="banner_categoria" accept="image/jpeg,image/png,image/gif">
                                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('categoryBanner').click()">
                                        <i class="fas fa-image"></i>
                                    </button>
                                </div>
                                <div class="form-text">Formatos permitidos: JPG, PNG, GIF. Máximo 5MB.</div>
                                <div id="bannerPreview" class="mt-2 preview-container" style="display: none;">
                                    <div class="preview-header">
                                        <span>Vista previa del banner</span>
                                        <button type="button" class="btn-close btn-close-white" onclick="removeImage('categoryBanner', 'bannerPreview')"></button>
                                    </div>
                                    <img src="" alt="Vista previa del banner" class="preview-image">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveCategory()">Guardar Categoría</button>
            </div>
        </div>
    </div>
</div>

<style>
.preview-container {
    position: relative;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    overflow: hidden;
    background-color: #f8f9fa;
    margin-top: 1rem;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background-color: #6c757d;
    color: white;
    font-size: 0.875rem;
}

.preview-header .btn-close {
    padding: 0.5rem;
    margin: -0.5rem;
    opacity: 0.75;
}

.preview-header .btn-close:hover {
    opacity: 1;
}

.preview-image {
    width: 100%;
    height: 200px;
    object-fit: contain;
    padding: 0.5rem;
    background-color: white;
}

.preview-container:hover .preview-header {
    background-color: #5a6268;
}

.input-group .btn-outline-secondary {
    border-color: #ced4da;
}

.input-group .btn-outline-secondary:hover {
    background-color: #e9ecef;
    border-color: #ced4da;
    color: #495057;
}
</style>

<script>
// Función para inicializar el token CSRF cuando se abre el modal
document.getElementById('addCategoryModal').addEventListener('show.bs.modal', function () {
    if (window.CSRF_TOKEN) {
        document.getElementById('categoryCsrfToken').value = window.CSRF_TOKEN;
        console.log('Token CSRF establecido:', window.CSRF_TOKEN);
    } else {
        console.error('No se pudo establecer el token CSRF: no está disponible');
    }

    // Agregar event listeners para los inputs de archivo
    setupImagePreviews();
});

// Función para configurar los event listeners de las imágenes
function setupImagePreviews() {
    console.log('Configurando vista previa de imágenes...');
    
    // Configurar para la imagen principal
    const categoryImage = document.getElementById('categoryImage');
    if (categoryImage) {
        categoryImage.addEventListener('change', function(e) {
            console.log('Cambio detectado en imagen principal');
            handleImagePreview(this, 'imagePreview');
        });
    }

    // Configurar para el banner
    const categoryBanner = document.getElementById('categoryBanner');
    if (categoryBanner) {
        categoryBanner.addEventListener('change', function(e) {
            console.log('Cambio detectado en banner');
            handleImagePreview(this, 'bannerPreview');
        });
    }
}

// Función para manejar la vista previa de imágenes
function handleImagePreview(input, previewId) {
    console.log('Manejando vista previa para:', previewId);
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    
    if (file) {
        console.log('Archivo seleccionado:', file.name, file.type, file.size);
        
        // Validar tipo de archivo
        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            console.error('Tipo de archivo no permitido:', file.type);
            showAlert('Tipo de archivo no permitido. Use JPG, PNG o GIF.', 'danger');
            input.value = '';
            return;
        }

        // Validar tamaño (5MB)
        if (file.size > 5 * 1024 * 1024) {
            console.error('Archivo demasiado grande:', file.size);
            showAlert('La imagen es demasiado grande. Máximo 5MB.', 'danger');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        
        reader.onload = function(e) {
            console.log('Imagen cargada correctamente');
            const img = preview.querySelector('img');
            img.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.onerror = function(e) {
            console.error('Error al leer la imagen:', e);
            showAlert('Error al leer la imagen', 'danger');
            input.value = '';
        };
        
        console.log('Iniciando lectura del archivo...');
        reader.readAsDataURL(file);
    } else {
        console.log('No se seleccionó ningún archivo');
        preview.style.display = 'none';
    }
}

// Función para eliminar imagen
function removeImage(inputId, previewId) {
    console.log('Eliminando imagen:', inputId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    input.value = '';
    preview.style.display = 'none';
    preview.querySelector('img').src = '';
}

// Función para mostrar alertas
function showAlert(message, type = 'success', container = '.modal-body') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Limpiar alertas anteriores
    const containerElement = document.querySelector(container);
    const existingAlerts = containerElement.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Insertar nueva alerta
    containerElement.insertBefore(alertDiv, containerElement.firstChild);
}

// Función para guardar la categoría
async function saveCategory() {
    console.log('Iniciando guardado de categoría');
    
    // Verificar que las variables globales estén disponibles
    if (!window.BASE_URL) {
        console.error('BASE_URL no está definido');
        showAlert('Error de configuración: BASE_URL no está definido', 'danger');
        return;
    }
    
    if (!window.CSRF_TOKEN) {
        console.error('CSRF_TOKEN no está definido');
        showAlert('Error de seguridad: Token CSRF no está disponible', 'danger');
        return;
    }

    const form = document.getElementById('addCategoryForm');
    const formData = new FormData(form);

    // Asegurarse de que el token CSRF esté en el FormData
    if (!formData.get('csrf_token')) {
        formData.set('csrf_token', window.CSRF_TOKEN);
    }

    // Log de los datos que se enviarán
    console.log('Datos del formulario:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }

    // Validar nombre
    const name = formData.get('name');
    if (!name || name.trim() === '') {
        showAlert('Por favor ingresa un nombre para la categoría', 'danger');
        return;
    }

    const url = `${window.BASE_URL}/restaurante/ajax/add_category.php`;
    console.log('URL de destino:', url);

    try {
        console.log('Enviando datos al servidor...');
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });

        console.log('Respuesta recibida:', response);
        console.log('Status:', response.status);
        console.log('Headers:', Object.fromEntries(response.headers.entries()));
        
        // Obtener el texto de la respuesta primero
        const responseText = await response.text();
        console.log('Respuesta texto:', responseText);

        // Intentar parsear como JSON
        let data;
        try {
            data = JSON.parse(responseText);
            console.log('Datos parseados:', data);
        } catch (e) {
            console.error('Error al parsear JSON:', e);
            throw new Error('La respuesta del servidor no es JSON válido: ' + responseText);
        }

        if (data.success) {
            // Mostrar mensaje de éxito en la página principal
            const mainAlert = document.createElement('div');
            mainAlert.className = 'alert alert-success alert-dismissible fade show';
            mainAlert.innerHTML = `
                <i class="fas fa-check-circle"></i> ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            const mainContainer = document.querySelector('.p-4');
            if (mainContainer) {
                mainContainer.insertBefore(mainAlert, mainContainer.firstChild);
            }
            
            // Cerrar el modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addCategoryModal'));
            if (modal) {
                modal.hide();
            }
            
            // Limpiar el formulario
            form.reset();
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('bannerPreview').style.display = 'none';
            
            // Recargar la página después de un breve delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message || 'Error al guardar la categoría', 'danger');
        }
    } catch (error) {
        console.error('Error completo:', error);
        showAlert('Error al guardar la categoría: ' + error.message, 'danger');
    }
}

// Asegurarse de que BASE_URL esté definido
if (typeof window.BASE_URL === 'undefined') {
    console.error('BASE_URL no está definido');
    showAlert('Error de configuración: BASE_URL no está definido', 'danger');
}
</script>

<!-- Modal para editar categoría -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Editar Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editCategoryForm">
                    <input type="hidden" name="csrf_token" id="editCategoryCsrfToken">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Nombre de la Categoría *</label>
                        <input type="text" class="form-control" id="edit_category_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_category_description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="edit_category_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_category_image" class="form-label">Imagen de la Categoría</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="edit_category_image" name="image" accept="image/jpeg,image/png,image/gif">
                                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('edit_category_image').click()">
                                        <i class="fas fa-image"></i>
                                    </button>
                                </div>
                                <div class="form-text">Formatos permitidos: JPG, PNG, GIF. Máximo 5MB.</div>
                                <div id="editImagePreview" class="mt-2 preview-container" style="display: none;">
                                    <div class="preview-header">
                                        <span>Imagen actual</span>
                                        <button type="button" class="btn-close btn-close-white" onclick="removeImage('edit_category_image', 'editImagePreview')"></button>
                                    </div>
                                    <img src="" alt="Vista previa" class="preview-image" id="current_category_image">
                                </div>
                                <input type="hidden" name="current_image" id="current_category_image_path">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_category_banner" class="form-label">Banner de la Categoría</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="edit_category_banner" name="banner_categoria" accept="image/jpeg,image/png,image/gif">
                                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('edit_category_banner').click()">
                                        <i class="fas fa-image"></i>
                                    </button>
                                </div>
                                <div class="form-text">Formatos permitidos: JPG, PNG, GIF. Máximo 5MB.</div>
                                <div id="editBannerPreview" class="mt-2 preview-container" style="display: none;">
                                    <div class="preview-header">
                                        <span>Banner actual</span>
                                        <button type="button" class="btn-close btn-close-white" onclick="removeImage('edit_category_banner', 'editBannerPreview')"></button>
                                    </div>
                                    <img src="" alt="Vista previa del banner" class="preview-image" id="current_category_banner">
                                </div>
                                <input type="hidden" name="current_banner" id="current_category_banner_path">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="updateCategory()">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<style>
.preview-container {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    background: #f8f9fa;
    margin-top: 10px;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    color: #fff;
}

.preview-image {
    max-width: 100%;
    max-height: 200px;
    display: block;
    margin: 0 auto;
}

.btn-close-white {
    background-color: rgba(255, 255, 255, 0.5);
    border-radius: 50%;
    padding: 0.25rem;
}

.btn-close-white:hover {
    background-color: rgba(255, 255, 255, 0.75);
}
</style>

<script>
// Función para remover una imagen
function removeImage(inputId, previewId) {
    console.log('Removiendo imagen:', inputId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const hiddenInput = document.getElementById(inputId.replace('_image', '_image_path'));
    
    if (input) input.value = '';
    if (hiddenInput) hiddenInput.value = '';
    if (preview) {
        preview.style.display = 'none';
        const img = preview.querySelector('img');
        if (img) img.src = '';
    }
}

// Función para manejar la vista previa de imágenes
function handleImagePreview(input, previewId) {
    console.log('Manejando vista previa para:', input.id);
    const preview = document.getElementById(previewId);
    const img = preview.querySelector('img');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validar tipo de archivo
        if (!file.type.match('image.*')) {
            showAlert('Por favor selecciona una imagen válida (JPG, PNG o GIF)', 'danger');
            input.value = '';
            return;
        }
        
        // Validar tamaño (5MB máximo)
        if (file.size > 5 * 1024 * 1024) {
            showAlert('La imagen no debe superar los 5MB', 'danger');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

// Función para mostrar alertas
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    const modalBody = document.querySelector('#editCategoryModal .modal-body');
    modalBody.insertBefore(alertDiv, modalBody.firstChild);
    
    // Auto cerrar después de 5 segundos
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, 5000);
}

// Función para guardar la categoría actualizada
async function updateCategory() {
    console.log('Iniciando actualización de categoría');
    
    // Verificar que las variables globales estén disponibles
    if (!window.BASE_URL) {
        console.error('BASE_URL no está definido');
        showAlert('Error de configuración: BASE_URL no está definido', 'danger');
        return;
    }
    
    if (!window.CSRF_TOKEN) {
        console.error('CSRF_TOKEN no está definido');
        showAlert('Error de seguridad: Token CSRF no está disponible', 'danger');
        return;
    }

    const form = document.getElementById('editCategoryForm');
    const formData = new FormData(form);

    // Asegurarse de que el token CSRF esté en el FormData
    if (!formData.get('csrf_token')) {
        formData.set('csrf_token', window.CSRF_TOKEN);
    }

    // Log de los datos que se enviarán
    console.log('Datos del formulario:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }

    // Validar nombre
    const name = formData.get('name');
    if (!name || name.trim() === '') {
        showAlert('Por favor ingresa un nombre para la categoría', 'danger');
        return;
    }

    const url = `${window.BASE_URL}/restaurante/ajax/update_category.php`;
    console.log('URL de destino:', url);

    try {
        console.log('Enviando datos al servidor...');
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });

        console.log('Respuesta recibida:', response);
        console.log('Status:', response.status);
        console.log('Headers:', Object.fromEntries(response.headers.entries()));
        
        // Obtener el texto de la respuesta primero
        const responseText = await response.text();
        console.log('Respuesta texto:', responseText);

        // Intentar parsear como JSON
        let data;
        try {
            data = JSON.parse(responseText);
            console.log('Datos parseados:', data);
        } catch (e) {
            console.error('Error al parsear JSON:', e);
            throw new Error('La respuesta del servidor no es JSON válido: ' + responseText);
        }

        if (data.success) {
            // Mostrar mensaje de éxito en la página principal
            const mainAlert = document.createElement('div');
            mainAlert.className = 'alert alert-success alert-dismissible fade show';
            mainAlert.innerHTML = `
                <i class="fas fa-check-circle"></i> ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            const mainContainer = document.querySelector('.p-4');
            if (mainContainer) {
                mainContainer.insertBefore(mainAlert, mainContainer.firstChild);
            }
            
            // Cerrar el modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editCategoryModal'));
            if (modal) {
                modal.hide();
            }
            
            // Limpiar el formulario
            form.reset();
            document.getElementById('editImagePreview').style.display = 'none';
            document.getElementById('editBannerPreview').style.display = 'none';
            
            // Recargar la página después de un breve delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message || 'Error al actualizar la categoría', 'danger');
        }
    } catch (error) {
        console.error('Error completo:', error);
        showAlert('Error al actualizar la categoría: ' + error.message, 'danger');
    }
}
</script>

<!-- Modal para eliminar categoría -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Eliminar Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/restaurante/ajax/delete_category.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <p>¿Estás seguro de que deseas eliminar la categoría "<span id="delete_category_name"></span>"?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar Categoría</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para agregar producto -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProductModalLabel">Nuevo Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addProductForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" id="addProductCsrfToken">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="product_name" class="form-label">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="product_name" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="product_description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="product_description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="product_category" class="form-label">Categoría *</label>
                                        <select class="form-select" id="product_category" name="category_id" required>
                                            <option value="">Seleccionar categoría</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="product_price" class="form-label">Precio *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="product_price" name="price" min="0" step="100" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="product_image" class="form-label">Imagen del Producto</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="product_image" name="image" accept="image/jpeg,image/png,image/gif">
                                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('product_image').click()">
                                        <i class="fas fa-image"></i>
                                    </button>
                                </div>
                                <div class="form-text">Formatos permitidos: JPG, PNG, GIF. Máximo 5MB.</div>
                                <div id="productImagePreview" class="mt-2 preview-container" style="display: none;">
                                    <div class="preview-header">
                                        <span>Vista previa</span>
                                        <button type="button" class="btn-close btn-close-white" onclick="removeImage('product_image', 'productImagePreview')"></button>
                                    </div>
                                    <img src="" alt="Vista previa" class="preview-image" id="product_preview_image">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="product_available" name="is_available" checked>
                                    <label class="form-check-label" for="product_available">Producto disponible</label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="product_featured" name="is_featured">
                                    <label class="form-check-label" for="product_featured">Destacar producto</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveProductBtn">Guardar Producto</button>
            </div>
        </div>
    </div>
</div>

<script>
// Inicializar el modal de nuevo producto
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando modal de nuevo producto...');
    
    // Establecer el token CSRF cuando se abre el modal
    const addProductModal = document.getElementById('addProductModal');
    if (addProductModal) {
        addProductModal.addEventListener('show.bs.modal', function() {
            console.log('Modal de nuevo producto abierto');
            if (window.CSRF_TOKEN) {
                document.getElementById('addProductCsrfToken').value = window.CSRF_TOKEN;
            }
        });
    }

    // Configurar el botón de guardar
    const saveProductBtn = document.getElementById('saveProductBtn');
    if (saveProductBtn) {
        saveProductBtn.addEventListener('click', function() {
            console.log('Botón guardar producto clickeado');
            saveProduct();
        });
    }

    // Configurar la previsualización de imagen
    const productImage = document.getElementById('product_image');
    if (productImage) {
        productImage.addEventListener('change', function(e) {
            console.log('Cambio detectado en imagen de producto');
            handleImagePreview(this, 'productImagePreview');
        });
    }
});

// Función para guardar el producto
async function saveProduct() {
    console.log('Iniciando guardado de producto...');
    
    // Verificar que las variables globales estén disponibles
    if (!window.BASE_URL) {
        console.error('BASE_URL no está definido');
        showAlert('Error de configuración: BASE_URL no está definido', 'danger');
        return;
    }
    
    if (!window.CSRF_TOKEN) {
        console.error('CSRF_TOKEN no está definido');
        showAlert('Error de seguridad: Token CSRF no está disponible', 'danger');
        return;
    }

    const form = document.getElementById('addProductForm');
    if (!form) {
        console.error('Formulario no encontrado');
        showAlert('Error: No se encontró el formulario', 'danger');
        return;
    }

    // Validar campos requeridos
    const name = form.querySelector('#product_name').value.trim();
    const categoryId = form.querySelector('#product_category').value;
    const price = form.querySelector('#product_price').value;

    if (!name) {
        showAlert('Por favor ingresa un nombre para el producto', 'danger');
        return;
    }

    if (!categoryId) {
        showAlert('Por favor selecciona una categoría', 'danger');
        return;
    }

    if (!price || price <= 0) {
        showAlert('Por favor ingresa un precio válido', 'danger');
        return;
    }

    const formData = new FormData(form);
    
    // Asegurarse de que el token CSRF esté en el FormData
    if (!formData.get('csrf_token')) {
        formData.set('csrf_token', window.CSRF_TOKEN);
    }

    // Log de los datos que se enviarán
    console.log('Datos del formulario:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }

    const url = `${window.BASE_URL}/restaurante/ajax/add_product.php`;
    console.log('URL de destino:', url);

    try {
        // Deshabilitar el botón mientras se procesa
        const saveBtn = document.getElementById('saveProductBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';
        }

        console.log('Enviando datos al servidor...');
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });

        console.log('Respuesta recibida:', response);
        console.log('Status:', response.status);
        
        // Obtener el texto de la respuesta primero
        const responseText = await response.text();
        console.log('Respuesta texto:', responseText);

        // Intentar parsear como JSON
        let data;
        try {
            data = JSON.parse(responseText);
            console.log('Datos parseados:', data);
        } catch (e) {
            console.error('Error al parsear JSON:', e);
            throw new Error('La respuesta del servidor no es JSON válido: ' + responseText);
        }

        if (data.success) {
            // Mostrar mensaje de éxito
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-check-circle"></i> ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.p-4').insertBefore(alert, document.querySelector('.card'));

            // Cerrar el modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addProductModal'));
            if (modal) {
                modal.hide();
            }

            // Limpiar el formulario
            form.reset();
            document.getElementById('productImagePreview').style.display = 'none';
            document.getElementById('product_preview_image').src = '';

            // Recargar la página después de un breve delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message || 'Error al guardar el producto', 'danger');
        }
    } catch (error) {
        console.error('Error completo:', error);
        showAlert('Error al guardar el producto: ' + error.message, 'danger');
    } finally {
        // Restaurar el botón
        const saveBtn = document.getElementById('saveProductBtn');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = 'Guardar Producto';
        }
    }
}

// Función para mostrar alertas
function showAlert(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.querySelector('.p-4').insertBefore(alert, document.querySelector('.card'));
}

// Función para manejar la previsualización de imágenes
function handleImagePreview(input, previewContainerId) {
    const previewContainer = document.getElementById(previewContainerId);
    const previewImage = previewContainer.querySelector('img');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validar tipo de archivo
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            showAlert('Tipo de archivo no permitido. Solo se permiten imágenes JPG, PNG y GIF.', 'danger');
            input.value = '';
            return;
        }
        
        // Validar tamaño (5MB)
        if (file.size > 5 * 1024 * 1024) {
            showAlert('La imagen es demasiado grande. El tamaño máximo permitido es 5MB.', 'danger');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

// Función para eliminar la imagen seleccionada
function removeImage(inputId, previewContainerId) {
    const input = document.getElementById(inputId);
    const previewContainer = document.getElementById(previewContainerId);
    const previewImage = previewContainer.querySelector('img');
    
    input.value = '';
    previewImage.src = '';
    previewContainer.style.display = 'none';
}
</script>

<!-- Modal para editar producto -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/restaurante/ajax/update_product.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Nombre del producto</label>
                                <input type="text" class="form-control" name="name" id="edit_product_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="description" id="edit_product_description" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Categoría</label>
                                        <select class="form-select" name="category_id" id="edit_product_category" required>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Precio</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" name="price" id="edit_product_price" min="0" step="0.01" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Imagen del producto</label>
                                <div class="mb-2">
                                    <img id="edit_product_current_image" src="" alt="Imagen actual del producto" class="img-thumbnail" style="max-height: 200px; display: none;">
                                </div>
                                <input type="hidden" name="current_image" id="edit_product_current_image_path">
                                <input type="file" class="form-control" name="image" accept="image/*" onchange="previewImage(this, 'edit_product_current_image')">
                                <small class="text-muted">Formatos permitidos: JPG, PNG, GIF. Máximo 5MB.</small>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_available" id="edit_product_available">
                                    <label class="form-check-label" for="edit_product_available">Disponible</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_featured" id="edit_product_featured">
                                    <label class="form-check-label" for="edit_product_featured">Destacado</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección de Opciones de Personalización -->
                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Opciones de Personalización</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="showAddOptionModal(document.getElementById('edit_product_id').value)">
                                <i class="fas fa-cog"></i> Gestionar Opciones
                            </button>
                        </div>
                        <div id="productOptionsContainer" class="mb-3">
                            <!-- Las opciones se cargarán dinámicamente aquí -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para eliminar producto -->
<div class="modal fade" id="deleteProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Eliminar Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="delete_product_id">
                <p>¿Estás seguro de que deseas eliminar el producto "<span id="delete_product_name"></span>"?</p>
                <p class="text-danger">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteProduct">
                    <i class="fas fa-trash"></i> Eliminar Producto
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para importar productos del restaurante principal -->
<div class="modal fade modal-import-custom" id="importProductsModal" tabindex="-1" aria-labelledby="importProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importProductsModalLabel">
                    <i class="fas fa-cloud-download-alt"></i> Importar Productos del Restaurante Principal
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="importLoading" class="import-loading" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p>Cargando productos del restaurante principal...</p>
                </div>
                
                <div id="importContent" style="display: none;">
                    <div class="alert alert-import-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Información:</strong> Selecciona los productos que deseas importar del restaurante principal. 
                        Las categorías se crearán automáticamente si no existen.
                    </div>
                    
                    <div id="parentCategoriesContainer">
                        <!-- Las categorías y productos se cargarán aquí dinámicamente -->
                    </div>
                </div>
                
                <div id="importError" class="alert alert-danger" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="importErrorMessage"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="importSelectedProducts" style="display: none;">
                    <i class="fas fa-cloud-download-alt"></i> Importar Productos Seleccionados
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Funcionalidad para el modal de importación
document.addEventListener('DOMContentLoaded', function() {
    const importModal = document.getElementById('importProductsModal');
    const importLoading = document.getElementById('importLoading');
    const importContent = document.getElementById('importContent');
    const importError = document.getElementById('importError');
    const importErrorMessage = document.getElementById('importErrorMessage');
    const importButton = document.getElementById('importSelectedProducts');
    const parentCategoriesContainer = document.getElementById('parentCategoriesContainer');
    
    let selectedProducts = [];
    
    // Cargar productos cuando se abre el modal
    importModal.addEventListener('show.bs.modal', function() {
        loadParentProducts();
    });
    
    // Función para cargar productos del restaurante padre
    async function loadParentProducts() {
        importLoading.style.display = 'block';
        importContent.style.display = 'none';
        importError.style.display = 'none';
        importButton.style.display = 'none';
        
        try {
            const response = await fetch(`${window.BASE_URL}/restaurante/ajax/get_parent_categories.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: window.CSRF_TOKEN
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                displayParentProducts(data.categories, data.parent_restaurant);
                importContent.style.display = 'block';
                importButton.style.display = 'block';
            } else {
                throw new Error(data.message || 'Error al cargar los productos');
            }
        } catch (error) {
            console.error('Error al cargar productos:', error);
            importErrorMessage.textContent = error.message || 'Error al cargar los productos del restaurante principal';
            importError.style.display = 'block';
        } finally {
            importLoading.style.display = 'none';
        }
    }
    
    // Función para mostrar los productos del restaurante padre
    function displayParentProducts(categories, parentRestaurant) {
        if (categories.length === 0) {
            parentCategoriesContainer.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    No hay productos disponibles en el restaurante principal.
                </div>
            `;
            return;
        }
        
        let html = `
            <div class="alert alert-import-success mb-3">
                <i class="fas fa-building"></i>
                <strong>Restaurante Principal:</strong> ${parentRestaurant.name}
            </div>
        `;
        
        categories.forEach(category => {
            if (category.products.length === 0) return;
            
            html += `
                <div class="card category-import-card">
                    <div class="card-header">
                        <div class="form-check">
                            <input class="form-check-input category-checkbox" type="checkbox" 
                                   id="category_${category.id}" data-category-id="${category.id}">
                            <label class="form-check-label" for="category_${category.id}">
                                <i class="fas fa-folder"></i> ${category.name}
                                <span class="badge ms-2">${category.products.length} productos</span>
                            </label>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
            `;
            
            category.products.forEach(product => {
                html += `
                    <div class="col-md-6 col-lg-4 mb-2">
                        <div class="card product-import-card h-100">
                            ${product.image ? `
                                <img src="${window.BASE_URL}/uploads/${product.image}" 
                                     class="card-img-top" alt="${product.name}">
                            ` : `
                                <div class="card-img-top placeholder">
                                    <i class="fas fa-image"></i>
                                </div>
                            `}
                            <div class="card-body">
                                <div class="form-check mb-2">
                                    <input class="form-check-input product-checkbox" type="checkbox" 
                                           id="product_${product.id}" 
                                           data-product-id="${product.id}"
                                           data-category-id="${category.id}">
                                    <label class="form-check-label fw-bold small" for="product_${product.id}">
                                        ${product.name}
                                    </label>
                                </div>
                                <p class="card-text small text-muted mb-2" style="font-size: 0.8rem; line-height: 1.3;">${product.description || 'Sin descripción'}</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h6 mb-0 text-primary fw-bold">$${parseFloat(product.price).toLocaleString()}</span>
                                    ${product.is_featured ? '<span class="badge bg-warning small"><i class="fas fa-star"></i></span>' : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `
                        </div>
                    </div>
                </div>
            `;
        });
        
        parentCategoriesContainer.innerHTML = html;
        
        // Configurar event listeners para checkboxes
        setupCheckboxListeners();
    }
    
    // Configurar listeners para checkboxes
    function setupCheckboxListeners() {
        // Checkbox de categoría
        document.querySelectorAll('.category-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const categoryId = this.dataset.categoryId;
                const productCheckboxes = document.querySelectorAll(`.product-checkbox[data-category-id="${categoryId}"]`);
                
                productCheckboxes.forEach(productCheckbox => {
                    productCheckbox.checked = this.checked;
                    updateSelectedProducts(productCheckbox);
                });
            });
        });
        
        // Checkbox de productos
        document.querySelectorAll('.product-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectedProducts(this);
                updateCategoryCheckbox(this.dataset.categoryId);
            });
        });
    }
    
    // Actualizar productos seleccionados
    function updateSelectedProducts(checkbox) {
        const productId = parseInt(checkbox.dataset.productId);
        
        if (checkbox.checked) {
            if (!selectedProducts.includes(productId)) {
                selectedProducts.push(productId);
            }
        } else {
            const index = selectedProducts.indexOf(productId);
            if (index > -1) {
                selectedProducts.splice(index, 1);
            }
        }
        
        // Actualizar texto del botón
        if (selectedProducts.length > 0) {
            importButton.textContent = `Importar ${selectedProducts.length} Producto${selectedProducts.length > 1 ? 's' : ''} Seleccionado${selectedProducts.length > 1 ? 's' : ''}`;
        } else {
            importButton.textContent = 'Importar Productos Seleccionados';
        }
    }
    
    // Actualizar checkbox de categoría
    function updateCategoryCheckbox(categoryId) {
        const categoryCheckbox = document.querySelector(`.category-checkbox[data-category-id="${categoryId}"]`);
        const productCheckboxes = document.querySelectorAll(`.product-checkbox[data-category-id="${categoryId}"]`);
        const checkedProducts = document.querySelectorAll(`.product-checkbox[data-category-id="${categoryId}"]:checked`);
        
        if (checkedProducts.length === 0) {
            categoryCheckbox.checked = false;
            categoryCheckbox.indeterminate = false;
        } else if (checkedProducts.length === productCheckboxes.length) {
            categoryCheckbox.checked = true;
            categoryCheckbox.indeterminate = false;
        } else {
            categoryCheckbox.checked = false;
            categoryCheckbox.indeterminate = true;
        }
    }
    
    // Importar productos seleccionados
    importButton.addEventListener('click', async function() {
        if (selectedProducts.length === 0) {
            showAlert('Por favor selecciona al menos un producto para importar.', 'warning');
            return;
        }
        
        if (!confirm(`¿Estás seguro de que deseas importar ${selectedProducts.length} producto${selectedProducts.length > 1 ? 's' : ''}?`)) {
            return;
        }
        
        importButton.disabled = true;
        importButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando productos...';
        importButton.style.opacity = '0.8';
        
        try {
            const response = await fetch(`${window.BASE_URL}/restaurante/ajax/import_products.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: window.CSRF_TOKEN,
                    product_ids: selectedProducts
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showAlert(data.message, 'success');
                // Cerrar modal y recargar página
                const modal = bootstrap.Modal.getInstance(importModal);
                modal.hide();
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                throw new Error(data.message || 'Error al importar productos');
            }
        } catch (error) {
            console.error('Error al importar productos:', error);
            showAlert('Error al importar productos: ' + error.message, 'danger');
        } finally {
            importButton.disabled = false;
            importButton.innerHTML = '<i class="fas fa-cloud-download-alt"></i> Importar Productos Seleccionados';
            importButton.style.opacity = '1';
        }
    });
});
</script>
</div> 
