<!-- Modal Opciones de Producto -->
<div class="modal fade" id="productOptionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Opciones de Personalización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Lista de Opciones Existentes -->
                <div id="optionsList" class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Opciones Actuales</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.menuOptions.showAddOptionForm()">
                            <i class="fas fa-plus"></i> Nueva Opción
                        </button>
                    </div>
                    <div id="optionsContainer" class="list-group">
                        <!-- Las opciones se cargarán dinámicamente aquí -->
                    </div>
                </div>

                <!-- Formulario para Nueva Opción -->
                <form id="addOptionForm" class="border-top pt-3" style="display: none;">
                    <h6 id="optionFormTitle" class="mb-3">Nueva Opción</h6>
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="option_id" id="optionId">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="optionName" class="form-label">Nombre de la Opción</label>
                                <input type="text" class="form-control" id="optionName" name="name" required
                                       placeholder="Ej: Tamaño, Extras, Preparación...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="optionType" class="form-label">Tipo de Selección</label>
                                <select class="form-select" id="optionType" name="type" required>
                                    <option value="single">Selección Única</option>
                                    <option value="multiple">Selección Múltiple</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="optionDescription" class="form-label">Descripción</label>
                        <textarea class="form-control" id="optionDescription" name="description" rows="2"
                                  placeholder="Descripción opcional de la opción..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="optionRequired" 
                                       name="is_required">
                                <label class="form-check-label" for="optionRequired">
                                    Opción Requerida
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="optionShowPrice" 
                                       name="show_price" checked>
                                <label class="form-check-label" for="optionShowPrice">
                                    Mostrar Precios
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="selectionsRange" style="display: none;">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="minSelections" class="form-label">Selecciones Mínimas</label>
                                <input type="number" class="form-control" id="minSelections" name="min_selections" 
                                       min="0" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="maxSelections" class="form-label">Selecciones Máximas</label>
                                <input type="number" class="form-control" id="maxSelections" name="max_selections" 
                                       min="1" value="1">
                            </div>
                        </div>
                    </div>

                

                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-secondary" onclick="window.menuOptions.cancelOptionForm()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Opción</button>
                    </div>
                </form>

                <!-- Formulario para Agregar/Editar Valor -->
                <form id="addValueForm" class="border-top pt-3" style="display: none;">
                    <h6 id="valueFormTitle" class="mb-3">Nuevo Valor</h6>
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="option_id" id="valueOptionId">
                    <input type="hidden" name="value_id" id="valueId">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="valueName" class="form-label">Nombre del Valor</label>
                                <input type="text" class="form-control" id="valueName" name="name" required
                                       placeholder="Ej: Grande, Extra Queso, Bien Cocido...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="valuePrice" class="form-label">Precio Adicional</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="valuePrice" name="price" 
                                           min="0" step="0.01" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="valueDescription" class="form-label">Descripción</label>
                        <textarea class="form-control" id="valueDescription" name="description" rows="2"
                                  placeholder="Descripción opcional del valor..."></textarea>
                    </div>

                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-secondary" onclick="window.menuOptions.cancelValueForm()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Valor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Template para Valor de Opción -->
<template id="optionValueTemplate">
    <div class="option-value-item mb-2">
        <div class="row g-2">
            <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" name="values[][name]" 
                       placeholder="Nombre del valor" required>
            </div>
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" name="values[][price]" 
                           placeholder="0.00" min="0" step="0.01" value="0">
                </div>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger btn-sm w-100" 
                        onclick="removeOptionValue(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
// Manejar cambios en el tipo de opción
document.getElementById('optionType')?.addEventListener('change', function() {
    const isMultiple = this.value === 'multiple';
    const selectionsRange = document.getElementById('selectionsRange');
    if (selectionsRange) {
        selectionsRange.style.display = isMultiple ? 'flex' : 'none';
    }
});

// Manejar el envío del formulario de opciones
document.getElementById('addOptionForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    try {
        const formData = new FormData(this);
        const optionId = formData.get('option_id');
        if (optionId) {
            await window.menuOptions.updateOption(formData);
        } else {
            await window.menuOptions.addOption(formData);
        }
        window.menuOptions.cancelOptionForm();
    } catch (error) {
        console.error('Error al guardar la opción:', error);
        window.menuOptions.showError(error.message);
    }
});

// Manejar el envío del formulario de valores
document.getElementById('addValueForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    try {
        const formData = new FormData(this);
        const valueId = formData.get('value_id');
        if (valueId) {
            await window.menuOptions.updateValue(formData);
        } else {
            await window.menuOptions.addValue(formData);
        }
        window.menuOptions.cancelValueForm();
    } catch (error) {
        console.error('Error al guardar el valor:', error);
        window.menuOptions.showError(error.message);
    }
});

// Función para agregar un nuevo valor de opción
function addOptionValue() {
    const template = document.getElementById('optionValueTemplate');
    const container = document.getElementById('optionValuesList');
    if (!template || !container) {
        console.error('No se encontró el template o el contenedor de valores');
        return;
    }
    const clone = template.content.cloneNode(true);
    container.appendChild(clone);
}

// Función para eliminar un valor de opción
function removeOptionValue(button) {
    const item = button.closest('.option-value-item');
    if (item) {
        item.remove();
    }
}
</script>

<style>
.option-value-item {
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background-color: #f8f9fa;
}

.option-value-item:hover {
    background-color: #e9ecef;
}

#optionsList .list-group-item {
    transition: all 0.2s ease;
}

#optionsList .list-group-item:hover {
    background-color: #f8f9fa;
}
</style> 
