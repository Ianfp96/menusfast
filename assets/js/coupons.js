// Funciones para manejar cupones en el frontend
class CouponManager {
    constructor(restaurantId) {
        this.restaurantId = restaurantId;
        this.currentCoupon = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadAppliedCoupon();
    }

    setupEventListeners() {
        // Botón para aplicar cupón
        const applyButton = document.getElementById('apply-coupon-btn');
        if (applyButton) {
            applyButton.addEventListener('click', () => this.applyCoupon());
        }

        // Botón para remover cupón
        const removeButton = document.getElementById('remove-coupon-btn');
        if (removeButton) {
            removeButton.addEventListener('click', () => this.removeCoupon());
        }

        // Campo de código de cupón
        const couponInput = document.getElementById('coupon-code');
        if (couponInput) {
            couponInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.applyCoupon();
                }
            });
        }
    }

    async applyCoupon() {
        const couponCode = document.getElementById('coupon-code')?.value?.trim();
        const orderTotal = this.getOrderTotal();

        if (!couponCode) {
            this.showMessage('Por favor ingresa un código de cupón', 'error');
            return;
        }

        if (orderTotal <= 0) {
            this.showMessage('El carrito está vacío', 'error');
            return;
        }

        try {
            this.showLoading(true);

            const response = await fetch('/api/validate_coupon.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    code: couponCode,
                    restaurant_id: this.restaurantId,
                    order_total: orderTotal
                })
            });

            const data = await response.json();

            if (data.success) {
                this.currentCoupon = data.coupon;
                this.displayCouponInfo(data.coupon);
                this.updateOrderTotal(data.coupon.final_total);
                this.showMessage('¡Cupón aplicado exitosamente!', 'success');
                
                // Limpiar campo
                document.getElementById('coupon-code').value = '';
                
                // Guardar en localStorage
                this.saveCouponToStorage(data.coupon);
            } else {
                this.showMessage(data.message, 'error');
            }
        } catch (error) {
            console.error('Error al aplicar cupón:', error);
            this.showMessage('Error al aplicar el cupón', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    removeCoupon() {
        this.currentCoupon = null;
        this.hideCouponInfo();
        this.updateOrderTotal(this.getOrderTotal());
        this.removeCouponFromStorage();
        this.showMessage('Cupón removido', 'info');
    }

    displayCouponInfo(coupon) {
        const couponInfo = document.getElementById('coupon-info');
        if (!couponInfo) return;

        const discountText = coupon.discount_type === 'percentage' 
            ? `${coupon.discount_value}%` 
            : `$${coupon.discount_amount.toLocaleString()}`;

        couponInfo.innerHTML = `
            <div class="alert alert-success d-flex justify-content-between align-items-center">
                <div>
                    <strong>${coupon.name}</strong>
                    <br>
                    <small class="text-muted">Código: ${coupon.code}</small>
                    ${coupon.description ? `<br><small class="text-muted">${coupon.description}</small>` : ''}
                </div>
                <div class="text-end">
                    <div class="text-success fw-bold">-$${coupon.discount_amount.toLocaleString()}</div>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="remove-coupon-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;

        couponInfo.style.display = 'block';
        
        // Reconfigurar el botón de remover
        const removeButton = document.getElementById('remove-coupon-btn');
        if (removeButton) {
            removeButton.addEventListener('click', () => this.removeCoupon());
        }
    }

    hideCouponInfo() {
        const couponInfo = document.getElementById('coupon-info');
        if (couponInfo) {
            couponInfo.style.display = 'none';
            couponInfo.innerHTML = '';
        }
    }

    updateOrderTotal(newTotal) {
        const totalElement = document.getElementById('order-total');
        if (totalElement) {
            totalElement.textContent = `$${newTotal.toLocaleString()}`;
        }

        // Actualizar también el total en el formulario si existe
        const totalInput = document.getElementById('total');
        if (totalInput) {
            totalInput.value = newTotal;
        }
    }

    getOrderTotal() {
        const totalElement = document.getElementById('order-total');
        if (totalElement) {
            const totalText = totalElement.textContent.replace(/[^0-9]/g, '');
            return parseInt(totalText) || 0;
        }
        return 0;
    }

    showMessage(message, type = 'info') {
        // Crear o actualizar mensaje
        let messageElement = document.getElementById('coupon-message');
        if (!messageElement) {
            messageElement = document.createElement('div');
            messageElement.id = 'coupon-message';
            const couponSection = document.getElementById('coupon-section');
            if (couponSection) {
                couponSection.appendChild(messageElement);
            }
        }

        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';

        messageElement.className = `alert ${alertClass} alert-dismissible fade show`;
        messageElement.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Auto-ocultar después de 5 segundos
        setTimeout(() => {
            if (messageElement.parentNode) {
                messageElement.remove();
            }
        }, 5000);
    }

    showLoading(show) {
        const applyButton = document.getElementById('apply-coupon-btn');
        if (applyButton) {
            if (show) {
                applyButton.disabled = true;
                applyButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aplicando...';
            } else {
                applyButton.disabled = false;
                applyButton.innerHTML = 'Aplicar Cupón';
            }
        }
    }

    saveCouponToStorage(coupon) {
        try {
            localStorage.setItem(`coupon_${this.restaurantId}`, JSON.stringify(coupon));
        } catch (error) {
            console.error('Error al guardar cupón en localStorage:', error);
        }
    }

    removeCouponFromStorage() {
        try {
            localStorage.removeItem(`coupon_${this.restaurantId}`);
        } catch (error) {
            console.error('Error al remover cupón de localStorage:', error);
        }
    }

    loadAppliedCoupon() {
        try {
            const savedCoupon = localStorage.getItem(`coupon_${this.restaurantId}`);
            if (savedCoupon) {
                this.currentCoupon = JSON.parse(savedCoupon);
                this.displayCouponInfo(this.currentCoupon);
                this.updateOrderTotal(this.currentCoupon.final_total);
            }
        } catch (error) {
            console.error('Error al cargar cupón guardado:', error);
        }
    }

    // Método para obtener el cupón actual (útil para el checkout)
    getCurrentCoupon() {
        return this.currentCoupon;
    }

    // Método para limpiar cupón al completar orden
    clearCoupon() {
        this.currentCoupon = null;
        this.hideCouponInfo();
        this.removeCouponFromStorage();
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Obtener el restaurant_id del elemento data o de alguna variable global
    const restaurantId = document.querySelector('[data-restaurant-id]')?.dataset.restaurantId || 
                        window.RESTAURANT_ID;
    
    if (restaurantId) {
        window.couponManager = new CouponManager(restaurantId);
    }
});

// Función global para aplicar cupón desde otros scripts
window.applyCoupon = function() {
    if (window.couponManager) {
        window.couponManager.applyCoupon();
    }
};

// Función global para remover cupón desde otros scripts
window.removeCoupon = function() {
    if (window.couponManager) {
        window.couponManager.removeCoupon();
    }
}; 