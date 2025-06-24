console.log('categories.js cargado');

async function handleAddCategory(event) {
    console.log('handleAddCategory llamado');
    event.preventDefault();
    console.log('Evento prevenido');
    
    const form = event.target;
    const formData = new FormData(form);
    console.log('FormData creado:', Object.fromEntries(formData));

    try {
        console.log('Enviando petición a:', `${window.BASE_URL}/restaurante/ajax/add_category.php`);
        const response = await fetch(`${window.BASE_URL}/restaurante/ajax/add_category.php`, {
            method: 'POST',
            body: formData
        });
        console.log('Respuesta recibida:', response);

        const data = await response.json();
        console.log('Datos recibidos:', data);

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
            
            // Resetear el formulario
            form.reset();
            
            // Recargar la página después de un breve delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
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
            <i class="fas fa-exclamation-circle"></i> Error al procesar la solicitud
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.modal-body').insertBefore(alert, document.querySelector('.row'));
    }
    
    return false;
} 