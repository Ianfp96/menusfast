// Definir BASE_URL globalmente
const BASE_URL = window.BASE_URL || '';

// Verificar que BASE_URL esté definido
if (!BASE_URL) {
    console.error('BASE_URL no está definido en products.js');
}

// Función para mostrar la vista previa de una imagen
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Función para cargar los datos del producto en el modal de edición
function loadProductData(productId, productImage) {
    // Primero establecer los datos básicos del producto desde los atributos del botón
    const button = document.querySelector(`.edit-product[data-product-id="${productId}"]`);
    if (button) {
        document.getElementById('edit_product_id').value = button.dataset.productId;
        document.getElementById('edit_product_name').value = button.dataset.productName;
        document.getElementById('edit_product_description').value = button.dataset.productDescription;
        document.getElementById('edit_product_category').value = button.dataset.productCategory;
        document.getElementById('edit_product_price').value = button.dataset.productPrice;
        document.getElementById('edit_product_available').checked = button.dataset.productAvailable === '1';
        document.getElementById('edit_product_featured').checked = button.dataset.productFeatured === '1';
        
        // Manejar la imagen actual
        const currentImage = document.getElementById('edit_product_current_image');
        const currentImagePath = document.getElementById('edit_product_current_image_path');
        if (button.dataset.productImage) {
            currentImage.src = `${BASE_URL}/uploads/${button.dataset.productImage}`;
            currentImage.style.display = 'block';
            currentImagePath.value = button.dataset.productImage;
        } else {
            currentImage.style.display = 'none';
            currentImagePath.value = '';
        }
    }

    // Mostrar el modal
    const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
    modal.show();
}

// Función para manejar el envío del formulario de agregar producto
async function handleAddProduct(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    try {
        const response = await fetch(`${BASE_URL}/restaurante/ajax/add_product.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
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
            modal.hide();
            
            // Limpiar el formulario
            form.reset();
            
            // Recargar la página después de un breve retraso
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Mostrar mensaje de error
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-exclamation-circle"></i> ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.modal-body').insertBefore(alert, document.querySelector('.row'));
        }
    } catch (error) {
        console.error('Error:', error);
        // Mostrar mensaje de error genérico
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show';
        alert.innerHTML = `
            <i class="fas fa-exclamation-circle"></i> Error al agregar el producto
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.modal-body').insertBefore(alert, document.querySelector('.row'));
    }
    
    return false;
}

// Inicializar cuando el documento esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Agregar event listeners para los botones de edición
    document.querySelectorAll('.edit-product').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            loadProductData(productId);
        });
    });

    // Manejar el envío del formulario de edición
    const editProductForm = document.getElementById('editProductModal').querySelector('form');
    if (editProductForm) {
        editProductForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editProductModal'));
                    modal.hide();
                    
                    // Recargar la página después de un breve retraso
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Mostrar mensaje de error
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-danger alert-dismissible fade show';
                    alert.innerHTML = `
                        <i class="fas fa-exclamation-circle"></i> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.modal-body').insertBefore(alert, document.querySelector('.row'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Mostrar mensaje de error genérico
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger alert-dismissible fade show';
                alert.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i> Error al actualizar el producto
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.modal-body').insertBefore(alert, document.querySelector('.row'));
            });
        });
    }
});

// Función para validar imágenes
function validateImage(input, type) {
    const file = input.files[0];
    const errorElement = document.getElementById(`${type}Error`);
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    
    // Limpiar mensaje de error anterior
    errorElement.textContent = '';
    input.classList.remove('is-invalid');
    
    if (file) {
        // Validar tipo de archivo
        if (!allowedTypes.includes(file.type)) {
            errorElement.textContent = 'Solo se permiten archivos JPG, PNG y GIF';
            input.classList.add('is-invalid');
            input.value = '';
            return false;
        }
        
        // Validar tamaño
        if (file.size > maxSize) {
            errorElement.textContent = 'El archivo no debe superar los 5MB';
            input.classList.add('is-invalid');
            input.value = '';
            return false;
        }
        
        // Mostrar vista previa si es una imagen de edición
        if (type.startsWith('edit_')) {
            const previewId = type === 'edit_image' ? 'edit_category_current_image' : 'edit_category_current_banner';
            const preview = document.getElementById(previewId);
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    }
    
    return true;
}

// Función para manejar el envío del formulario de agregar categoría
async function handleAddCategory(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validar imágenes antes de enviar
    const imageInput = form.querySelector('input[name="image"]');
    const bannerInput = form.querySelector('input[name="banner_categoria"]');
    
    if (imageInput.files[0] && !validateImage(imageInput, 'image')) return false;
    if (bannerInput.files[0] && !validateImage(bannerInput, 'banner')) return false;
    
    try {
        const response = await fetch(`${BASE_URL}/restaurante/ajax/add_category.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
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
            const modal = bootstrap.Modal.getInstance(document.getElementById('addCategoryModal'));
            modal.hide();
            
            // Limpiar el formulario
            form.reset();
            
            // Recargar la página después de un breve retraso
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Mostrar mensaje de error
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-exclamation-circle"></i> ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.modal-body').insertBefore(alert, document.querySelector('.row'));
        }
    } catch (error) {
        console.error('Error:', error);
        // Mostrar mensaje de error genérico
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show';
        alert.innerHTML = `
            <i class="fas fa-exclamation-circle"></i> Error al agregar la categoría
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.modal-body').insertBefore(alert, document.querySelector('.row'));
    }
    
    return false;
}

// Función para manejar el envío del formulario de editar categoría
async function handleEditCategory(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validar imágenes antes de enviar
    const imageInput = form.querySelector('input[name="image"]');
    const bannerInput = form.querySelector('input[name="banner_categoria"]');
    
    if (imageInput.files[0] && !validateImage(imageInput, 'edit_image')) return false;
    if (bannerInput.files[0] && !validateImage(bannerInput, 'edit_banner')) return false;
    
    try {
        const response = await fetch(`${BASE_URL}/restaurante/ajax/update_category.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
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
            const modal = bootstrap.Modal.getInstance(document.getElementById('editCategoryModal'));
            modal.hide();
            
            // Recargar la página después de un breve retraso
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Mostrar mensaje de error
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-exclamation-circle"></i> ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.modal-body').insertBefore(alert, document.querySelector('.row'));
        }
    } catch (error) {
        console.error('Error:', error);
        // Mostrar mensaje de error genérico
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show';
        alert.innerHTML = `
            <i class="fas fa-exclamation-circle"></i> Error al actualizar la categoría
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.modal-body').insertBefore(alert, document.querySelector('.row'));
    }
    
    return false;
} 