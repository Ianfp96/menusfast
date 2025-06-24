// Clase para manejar las opciones del menú
class MenuOptionsManager {
    constructor(baseUrl, csrfToken) {
        this.baseUrl = baseUrl;
        this.csrfToken = csrfToken;
        this.currentProductId = null;
        this.currentOptionId = null;
        this.options = [];
    }

    // Cargar opciones de un producto
    async loadOptions(productId) {
        try {
            console.log('=== Iniciando loadOptions ===');
            console.log('Product ID:', productId);
            if (!productId) {
                throw new Error('ID de producto no proporcionado');
            }

            const formData = new FormData();
            formData.append('action', 'get_options');
            formData.append('product_id', productId);
            formData.append('csrf_token', this.csrfToken);

            console.log('Enviando petición al servidor...');
            const response = await fetch(`${this.baseUrl}/restaurante/ajax/menu-options.php`, {
                method: 'POST',
                body: formData
            });

            console.log('Respuesta recibida del servidor');
            const data = await response.json();
            console.log('Datos recibidos:', data);

            if (!data.success) {
                throw new Error(data.error || 'Error al cargar las opciones');
            }

            if (!Array.isArray(data.options)) {
                console.error('Las opciones recibidas no son un array:', data.options);
                throw new Error('Formato de datos inválido');
            }

            this.options = data.options.map(option => {
                if (!option || !option.id) {
                    console.error('Opción inválida recibida:', option);
                    return null;
                }
                console.log('Procesando opción:', option);
                return {
                    ...option,
                    values: Array.isArray(option.values) ? option.values.filter(v => v && v.id) : []
                };
            }).filter(Boolean);

            console.log('Opciones procesadas:', this.options);
            return this.options;
        } catch (error) {
            console.error('Error al cargar opciones:', error);
            this.showError(error.message);
            throw error;
        }
    }

    // Mostrar un mensaje de éxito
    showSuccess(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show';
        alertDiv.innerHTML = `
            <i class="fas fa-check-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insertar el mensaje al inicio del modal
        const modalBody = document.querySelector('#productOptionsModal .modal-body');
        if (!modalBody) {
            console.error('No se encontró el modal-body en productOptionsModal');
            return;
        }
        modalBody.insertBefore(alertDiv, modalBody.firstChild);
        
        // Remover el mensaje después de 3 segundos
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }

    // Mostrar un mensaje de error
    showError(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = `
            <i class="fas fa-exclamation-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insertar el mensaje al inicio del modal
        const modalBody = document.querySelector('#productOptionsModal .modal-body');
        if (!modalBody) {
            console.error('No se encontró el modal-body en productOptionsModal');
            return;
        }
        modalBody.insertBefore(alertDiv, modalBody.firstChild);
    }

    // Mostrar un mensaje informativo
    showInfo(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-info alert-dismissible fade show';
        alertDiv.innerHTML = `
            <i class="fas fa-info-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insertar el mensaje al inicio del modal
        const modalBody = document.querySelector('#productOptionsModal .modal-body');
        if (!modalBody) {
            console.error('No se encontró el modal-body en productOptionsModal');
            return;
        }
        modalBody.insertBefore(alertDiv, modalBody.firstChild);
        
        // Remover el mensaje después de 3 segundos
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }

    // Agregar una nueva opción
    async addOption(formData) {
        try {
            console.log('Iniciando addOption con formData:', Object.fromEntries(formData));
            formData.append('action', 'add_option');
            formData.append('csrf_token', this.csrfToken);
            formData.append('product_id', this.currentProductId);

            const response = await fetch(`${this.baseUrl}/restaurante/ajax/menu-options.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            console.log('Respuesta del servidor:', data);

            if (!data.success) {
                throw new Error(data.error || 'Error al agregar la opción');
            }

            // Recargar las opciones después de agregar una nueva
            await this.loadOptions(this.currentProductId);
            console.log('Opciones cargadas:', this.options);
            this.renderOptions();
            
            // Mostrar mensaje de éxito
            this.showSuccess('Opción agregada correctamente');
            
            // Usar el ID de la opción que devuelve el servidor
            if (data.option_id) {
                console.log('Mostrando formulario para agregar valores con option_id:', data.option_id);
                this.showAddValueForm(data.option_id);
            } else {
                console.warn('No se recibió option_id en la respuesta del servidor');
            }
            
            return data;
        } catch (error) {
            console.error('Error al agregar opción:', error);
            this.showError(error.message);
            throw error;
        }
    }

    // Agregar un nuevo valor
    async addValue(formData) {
        try {
            console.log('Iniciando addValue con formData:', Object.fromEntries(formData));
            formData.append('action', 'add_value');
            formData.append('csrf_token', this.csrfToken);
            formData.append('product_id', this.currentProductId);

            // Mostrar mensaje de carga
            this.showInfo('Guardando valor...');

            const response = await fetch(`${this.baseUrl}/restaurante/ajax/menu-options.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            console.log('Respuesta del servidor al agregar valor:', data);

            if (!data.success) {
                throw new Error(data.error || 'Error al agregar el valor');
            }

            // Recargar las opciones después de agregar un nuevo valor
            await this.loadOptions(this.currentProductId);
            this.renderOptions();
            
            // Mostrar mensaje de éxito
            this.showSuccess('¡Valor guardado exitosamente!');
            
            // Mantener el formulario abierto para agregar más valores
            const optionId = formData.get('option_id');
            console.log('Manteniendo formulario abierto con optionId:', optionId);
            if (optionId) {
                this.showAddValueForm(optionId);
            } else {
                console.warn('No se encontró option_id en el formData');
            }
            
            return data;
        } catch (error) {
            console.error('Error al agregar valor:', error);
            this.showError(`Error al guardar el valor: ${error.message}`);
            throw error;
        }
    }

    // Actualizar una opción
    async updateOption(formData) {
        try {
            formData.append('action', 'update_option');
            formData.append('csrf_token', this.csrfToken);
            formData.append('product_id', this.currentProductId);

            const response = await fetch(`${this.baseUrl}/restaurante/ajax/menu-options.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Error al actualizar la opción');
            }

            // Recargar las opciones después de actualizar
            await this.loadOptions(this.currentProductId);
            this.renderOptions();
            
            // Mostrar mensaje de éxito
            this.showSuccess('Opción actualizada correctamente');
            
            return data;
        } catch (error) {
            console.error('Error al actualizar opción:', error);
            this.showError(error.message);
            throw error;
        }
    }

    // Actualizar un valor
    async updateValue(formData) {
        try {
            formData.append('action', 'update_value');
            formData.append('csrf_token', this.csrfToken);

            const response = await fetch(`${this.baseUrl}/restaurante/ajax/menu-options.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Error al actualizar el valor');
            }

            // Recargar las opciones después de actualizar
            await this.loadOptions(this.currentProductId);
            this.renderOptions();
            
            // Mostrar mensaje de éxito
            this.showSuccess('Valor actualizado correctamente');
            
            return data;
        } catch (error) {
            console.error('Error al actualizar valor:', error);
            this.showError(error.message);
            throw error;
        }
    }

    // Eliminar una opción
    async deleteOption(optionId) {
        if (!confirm('¿Estás seguro de que deseas eliminar esta opción? Esta acción eliminará también todos sus valores y no se puede deshacer.')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete_option');
            formData.append('option_id', optionId);
            formData.append('csrf_token', this.csrfToken);
            formData.append('product_id', this.currentProductId);

            const response = await fetch(`${this.baseUrl}/restaurante/ajax/menu-options.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Error al eliminar la opción');
            }

            // Recargar las opciones después de eliminar
            await this.loadOptions(this.currentProductId);
            this.renderOptions();
            
            // Mostrar mensaje de éxito
            this.showSuccess('Opción eliminada correctamente');
            
            return data;
        } catch (error) {
            console.error('Error al eliminar opción:', error);
            this.showError(error.message);
            throw error;
        }
    }

    // Eliminar un valor
    async deleteValue(valueId) {
        if (!confirm('¿Estás seguro de que deseas eliminar este valor? Esta acción no se puede deshacer.')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete_value');
            formData.append('value_id', valueId);
            formData.append('csrf_token', this.csrfToken);
            formData.append('product_id', this.currentProductId);

            const response = await fetch(`${this.baseUrl}/restaurante/ajax/menu-options.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Error al eliminar el valor');
            }

            // Recargar las opciones después de eliminar
            await this.loadOptions(this.currentProductId);
            this.renderOptions();
            
            // Mostrar mensaje de éxito
            this.showSuccess('Valor eliminado correctamente');
            
            return data;
        } catch (error) {
            console.error('Error al eliminar valor:', error);
            this.showError(error.message);
            throw error;
        }
    }

    // Actualizar el orden de las opciones y valores
    async updateOrder(orders) {
        try {
            const formData = new FormData();
            formData.append('action', 'update_order');
            formData.append('orders', JSON.stringify(orders));
            formData.append('csrf_token', this.csrfToken);

            const response = await fetch(`${this.baseUrl}/restaurante/ajax/menu-options.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Error al actualizar el orden');
            }

            return data;
        } catch (error) {
            console.error('Error al actualizar orden:', error);
            throw error;
        }
    }

    // Renderizar todas las opciones
    renderOptions() {
        const container = document.getElementById('optionsContainer');
        if (!container) {
            console.error('No se encontró el contenedor de opciones');
            return;
        }

        console.log('Renderizando opciones:', this.options);

        // Limpiar el contenedor
        container.innerHTML = '';

        if (!Array.isArray(this.options) || this.options.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No hay opciones definidas para este producto.
                    <br>
                    <small>Agrega opciones como "Tamaño", "Ingredientes", "Preparación", etc.</small>
                </div>
            `;
            return;
        }

        // Crear un contenedor para las opciones
        const optionsWrapper = document.createElement('div');
        optionsWrapper.className = 'options-wrapper';

        // Ordenar opciones por sort_order
        this.options.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));

        this.options.forEach(option => {
            if (!option || !option.id) {
                console.error('Opción inválida encontrada:', option);
                return;
            }
            console.log('Renderizando opción:', option);
            
            const card = document.createElement('div');
            card.className = 'card mb-3 option-card';
            card.dataset.optionId = option.id;
            
            const valuesList = this.createValuesList(option.values || []);
            const isRequired = option.is_required ? '<span class="badge bg-warning ms-2">Requerido</span>' : '';
            const showPrice = option.show_price ? '<span class="badge bg-info ms-2">Muestra Precio</span>' : '';
            const typeBadge = option.type === 'single' ? 
                '<span class="badge bg-primary">Selección Única</span>' : 
                '<span class="badge bg-success">Selección Múltiple</span>';
            
            card.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="card-title mb-1">
                                ${option.name || 'Sin nombre'}
                                ${typeBadge}
                                ${isRequired}
                                ${showPrice}
                            </h5>
                            ${option.description ? `<p class="card-text text-muted small mb-2">${option.description}</p>` : ''}
                        </div>
                        <div class="action-buttons">
                            <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="window.menuOptions.editOption(${option.id})" title="Editar opción">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.menuOptions.deleteOption(${option.id})" title="Eliminar opción">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    <div class="option-values" data-option-id="${option.id}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">
                                <i class="fas fa-list-ul me-2"></i>Valores
                                <small class="text-muted">(${(option.values || []).length})</small>
                            </h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.menuOptions.showAddValueForm(${option.id})">
                                <i class="fas fa-plus"></i> Agregar Valor
                            </button>
                        </div>
                        ${valuesList}
                    </div>
                </div>
            `;
            
            optionsWrapper.appendChild(card);
        });

        container.appendChild(optionsWrapper);

        // Inicializar Sortable para las opciones
        new Sortable(optionsWrapper, {
            animation: 150,
            handle: '.card-body',
            onEnd: async (evt) => {
                const orders = Array.from(evt.to.children).map((el, index) => ({
                    id: parseInt(el.dataset.optionId),
                    order: index + 1
                }));
                
                try {
                    await this.updateOrder({ options: orders });
                    this.showSuccess('Orden actualizado correctamente');
                } catch (error) {
                    console.error('Error al actualizar orden:', error);
                    this.showError('Error al actualizar el orden');
                }
            }
        });
    }

    // Crear el HTML para la lista de valores
    createValuesList(values) {
        console.log('Creando lista de valores:', values);
        
        if (!Array.isArray(values)) {
            console.error('Los valores no son un array:', values);
            return '<div class="alert alert-warning mb-0">Error al cargar los valores</div>';
        }

        if (values.length === 0) {
            return `
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> No hay valores definidos
                    <br>
                    <small>Haz clic en "Agregar Valor" para crear uno nuevo</small>
                </div>
            `;
        }

        // Ordenar valores por sort_order
        values.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));

        const valuesList = document.createElement('div');
        valuesList.className = 'list-group list-group-flush';

        values.forEach(value => {
            if (!value || !value.id) {
                console.error('Valor inválido encontrado:', value);
                return;
            }

            const item = document.createElement('div');
            item.className = 'list-group-item d-flex justify-content-between align-items-center';
            item.dataset.valueId = value.id;

            const priceDisplay = value.price ? 
                `<span class="badge bg-success ms-2">$${parseFloat(value.price).toFixed(2)}</span>` : '';

            item.innerHTML = `
                <div>
                    <span class="me-2">${value.name || 'Sin nombre'}</span>
                    ${priceDisplay}
                </div>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" onclick="window.menuOptions.editValue(${value.id})" title="Editar valor">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="window.menuOptions.deleteValue(${value.id})" title="Eliminar valor">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;

            valuesList.appendChild(item);
        });

        return valuesList.outerHTML;
    }

    // Cancelar el formulario de opción
    cancelOptionForm() {
        const form = document.getElementById('addOptionForm');
        form.reset();
        form.style.display = 'none';
        document.getElementById('optionFormTitle').textContent = 'Nueva Opción';
        form.querySelector('input[name="option_id"]')?.remove();
    }

    // Cancelar el formulario de valor
    cancelValueForm() {
        const form = document.getElementById('addValueForm');
        form.reset();
        form.style.display = 'none';
        document.getElementById('valueFormTitle').textContent = 'Nuevo Valor';
        form.querySelector('input[name="value_id"]')?.remove();
    }

    // Mostrar el formulario para agregar una opción
    showAddOptionForm() {
        const form = document.getElementById('addOptionForm');
        form.reset();
        form.style.display = 'block';
        document.getElementById('addValueForm').style.display = 'none';
        document.getElementById('optionFormTitle').textContent = 'Nueva Opción';
        form.querySelector('input[name="option_id"]')?.remove();
        
        // Enfocar el primer campo
        form.querySelector('input[name="name"]').focus();
    }

    // Mostrar el formulario para editar una opción
    editOption(optionId) {
        if (!optionId) {
            console.error('ID de opción no proporcionado');
            return;
        }

        const option = this.options.find(opt => opt && opt.id === optionId);
        if (!option) {
            console.error('Opción no encontrada:', optionId);
            this.showError('No se encontró la opción a editar');
            return;
        }

        const form = document.getElementById('addOptionForm');
        if (!form) {
            console.error('No se encontró el formulario de opciones');
            return;
        }

        form.reset();
        form.style.display = 'block';
        document.getElementById('addValueForm').style.display = 'none';
        document.getElementById('optionFormTitle').textContent = 'Editar Opción';

        // Agregar campo oculto para el ID de la opción
        let optionIdInput = form.querySelector('input[name="option_id"]');
        if (!optionIdInput) {
            optionIdInput = document.createElement('input');
            optionIdInput.type = 'hidden';
            optionIdInput.name = 'option_id';
            form.appendChild(optionIdInput);
        }
        optionIdInput.value = optionId;

        // Llenar los campos con los datos de la opción
        const nameInput = form.querySelector('input[name="name"]');
        const descriptionInput = form.querySelector('textarea[name="description"]');
        const typeInput = form.querySelector('select[name="type"]');
        const requiredInput = form.querySelector('input[name="is_required"]');
        const showPriceInput = form.querySelector('input[name="show_price"]');
        const minSelectionsInput = form.querySelector('input[name="min_selections"]');
        const maxSelectionsInput = form.querySelector('input[name="max_selections"]');

        if (nameInput) nameInput.value = option.name || '';
        if (descriptionInput) descriptionInput.value = option.description || '';
        if (typeInput) typeInput.value = option.type || 'single';
        if (requiredInput) requiredInput.checked = !!option.is_required;
        if (showPriceInput) showPriceInput.checked = !!option.show_price;
        if (minSelectionsInput) minSelectionsInput.value = option.min_selections || 0;
        if (maxSelectionsInput) maxSelectionsInput.value = option.max_selections || 1;

        // Enfocar el primer campo
        if (nameInput) nameInput.focus();
    }

    // Mostrar el formulario para agregar un valor
    showAddValueForm(optionId) {
        console.log('showAddValueForm llamado con optionId:', optionId);
        const form = document.getElementById('addValueForm');
        if (!form) {
            console.error('No se encontró el formulario addValueForm');
            return;
        }
        
        // Asegurarnos de que el option_id se establezca correctamente
        let optionIdInput = form.querySelector('input[name="option_id"]');
        if (!optionIdInput) {
            optionIdInput = document.createElement('input');
            optionIdInput.type = 'hidden';
            optionIdInput.name = 'option_id';
            form.appendChild(optionIdInput);
        }
        optionIdInput.value = optionId;
        
        form.reset();
        form.style.display = 'block';
        document.getElementById('addOptionForm').style.display = 'none';
        document.getElementById('valueFormTitle').textContent = 'Nuevo Valor';
        form.querySelector('input[name="value_id"]')?.remove();
        
        // Enfocar el primer campo
        const nameInput = form.querySelector('input[name="name"]');
        if (nameInput) {
            nameInput.focus();
        } else {
            console.error('No se encontró el campo name en el formulario');
        }
    }

    // Mostrar el formulario para editar un valor
    editValue(valueId) {
        if (!valueId) {
            console.error('ID de valor no proporcionado');
            return;
        }

        const option = this.options.find(opt => opt && opt.values && opt.values.some(val => val && val.id === valueId));
        if (!option) {
            console.error('No se encontró la opción que contiene el valor:', valueId);
            this.showError('No se encontró el valor a editar');
            return;
        }

        const value = option.values.find(val => val && val.id === valueId);
        if (!value) {
            console.error('Valor no encontrado:', valueId);
            this.showError('No se encontró el valor a editar');
            return;
        }

        const form = document.getElementById('addValueForm');
        if (!form) {
            console.error('No se encontró el formulario de valores');
            return;
        }

        form.reset();
        form.style.display = 'block';
        document.getElementById('addOptionForm').style.display = 'none';
        document.getElementById('valueFormTitle').textContent = 'Editar Valor';

        // Agregar campo oculto para el ID del valor
        let valueIdInput = form.querySelector('input[name="value_id"]');
        if (!valueIdInput) {
            valueIdInput = document.createElement('input');
            valueIdInput.type = 'hidden';
            valueIdInput.name = 'value_id';
            form.appendChild(valueIdInput);
        }
        valueIdInput.value = valueId;

        // Agregar campo oculto para el ID de la opción
        let optionIdInput = form.querySelector('input[name="option_id"]');
        if (!optionIdInput) {
            optionIdInput = document.createElement('input');
            optionIdInput.type = 'hidden';
            optionIdInput.name = 'option_id';
            form.appendChild(optionIdInput);
        }
        optionIdInput.value = option.id;

        // Llenar los campos con los datos del valor
        const nameInput = form.querySelector('input[name="name"]');
        const priceInput = form.querySelector('input[name="price"]');
        const descriptionInput = form.querySelector('textarea[name="description"]');

        if (nameInput) nameInput.value = value.name || '';
        if (priceInput) priceInput.value = value.price || '0';
        if (descriptionInput) descriptionInput.value = value.description || '';

        // Enfocar el primer campo
        if (nameInput) nameInput.focus();
    }

    // Mostrar el modal de opciones
    async showOptionsModal(productId) {
        console.log('=== Iniciando showOptionsModal ===');
        console.log('Product ID:', productId);
        this.currentProductId = productId;
        
        // Limpiar el contenedor de opciones
        const container = document.getElementById('optionsContainer');
        if (container) {
            console.log('Contenedor de opciones encontrado');
            container.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
        } else {
            console.error('No se encontró el contenedor de opciones (optionsContainer)');
        }

        try {
            // Mostrar el modal
            const modalElement = document.getElementById('productOptionsModal');
            if (!modalElement) {
                console.error('No se encontró el modal productOptionsModal');
                return;
            }
            console.log('Modal encontrado, mostrando...');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();

            // Cargar las opciones
            console.log('Iniciando carga de opciones...');
            const options = await this.loadOptions(productId);
            console.log('Opciones cargadas del servidor:', options);

            if (!options || options.length === 0) {
                console.log('No se encontraron opciones para este producto');
                if (container) {
                    container.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No hay opciones definidas para este producto.
                            <br>
                            <small>Agrega opciones como "Tamaño", "Ingredientes", "Preparación", etc.</small>
                        </div>
                    `;
                }
            } else {
                console.log('Renderizando opciones encontradas...');
                this.renderOptions();
            }

            // Ocultar formularios
            const addOptionForm = document.getElementById('addOptionForm');
            const addValueForm = document.getElementById('addValueForm');
            if (addOptionForm) {
                console.log('Ocultando formulario de opciones');
                addOptionForm.style.display = 'none';
            }
            if (addValueForm) {
                console.log('Ocultando formulario de valores');
                addValueForm.style.display = 'none';
            }

            // Agregar evento para limpiar al cerrar
            modalElement.addEventListener('hidden.bs.modal', () => {
                console.log('Modal cerrado, limpiando formularios...');
                this.cancelOptionForm();
                this.cancelValueForm();
                // Limpiar el contenedor de opciones
                if (container) {
                    container.innerHTML = '';
                }
            }, { once: true });
        } catch (error) {
            console.error('Error al mostrar modal de opciones:', error);
            this.showError(error.message);
            if (container) {
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error al cargar las opciones: ${error.message}
                    </div>
                `;
            }
        }
    }
}

// Inicializar el manejador de opciones cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        console.error('No se encontró el token CSRF');
        return;
    }

    // Hacer que menuOptions esté disponible globalmente
    window.menuOptions = new MenuOptionsManager(BASE_URL, csrfToken);
    console.log('MenuOptionsManager inicializado como menuOptions:', window.menuOptions);

    // Manejar el envío del formulario de opciones
    document.getElementById('addOptionForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        try {
            const formData = new FormData(this);
            // Determinar si es una edición o una nueva opción
            const optionId = formData.get('option_id');
            if (optionId) {
                // Es una edición
                await window.menuOptions.updateOption(formData);
            } else {
                // Es una nueva opción
                await window.menuOptions.addOption(formData);
            }
            window.menuOptions.cancelOptionForm();
        } catch (error) {
            window.menuOptions.showError(error.message);
        }
    });

    // Manejar el envío del formulario de valores
    document.getElementById('addValueForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        try {
            console.log('Formulario de valores enviado');
            const formData = new FormData(this);
            
            // Verificar que tenemos el option_id
            const optionId = formData.get('option_id');
            if (!optionId) {
                throw new Error('No se encontró el ID de la opción');
            }
            
            // Determinar si es una edición o un nuevo valor
            const valueId = formData.get('value_id');
            console.log('Enviando formulario con option_id:', optionId, 'value_id:', valueId);
            
            if (valueId) {
                // Es una edición
                await window.menuOptions.updateValue(formData);
            } else {
                // Es un nuevo valor
                await window.menuOptions.addValue(formData);
            }
            
            window.menuOptions.cancelValueForm();
        } catch (error) {
            console.error('Error al enviar formulario de valores:', error);
            window.menuOptions.showError(error.message);
        }
    });

    // Manejar cambios en el tipo de opción
    document.getElementById('optionType')?.addEventListener('change', function() {
        const isMultiple = this.value === 'multiple';
        const minSelections = document.getElementById('minSelections');
        const maxSelections = document.getElementById('maxSelections');
        
        if (minSelections && maxSelections) {
            minSelections.disabled = !isMultiple;
            maxSelections.disabled = !isMultiple;
            if (!isMultiple) {
                minSelections.value = '0';
                maxSelections.value = '1';
            }
        }
    });
}); 