// Ejemplo de integración de cupones en el carrito
// Este archivo muestra cómo agregar la funcionalidad de cupones al carrito existente

// Extender la función updateCartUI para incluir cupones
function updateCartUIWithCoupons() {
    const cart = getCartFromCookies();
    const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
    const cartTotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);

    // Obtener la abreviación de moneda del restaurante actual
    const currencyCode = window.CURRENT_RESTAURANT_CURRENCY || 'CLP';

    // Actualizar contador del carrito
    const cartCountElement = document.querySelector('.cart-count');
    if (cartCountElement) {
        cartCountElement.textContent = cartCount;
        cartCountElement.style.display = cartCount > 0 ? 'flex' : 'none';
    }

    // Actualizar contenido de la página del carrito si estamos en ella
    const cartContent = document.getElementById('cartContent');
    if (cartContent) {
        if (cart.length === 0) {
            cartContent.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Tu carrito está vacío</h2>
                    <p>Parece que aún no has agregado productos a tu carrito.</p>
                    <a href="${window.location.pathname.split('/cart')[0]}" class="continue-shopping">
                        Comenzar a comprar
                    </a>
                </div>
            `;
        } else {
            // Obtener el cupón aplicado si existe
            const appliedCoupon = window.couponManager?.getCurrentCoupon();
            const finalTotal = appliedCoupon ? appliedCoupon.final_total : cartTotal;

            cartContent.innerHTML = `
                <div class="cart-items">
                    ${cart.map(item => `
                        <div class="cart-item" data-item-id="${item.cartItemId}">
                            <div class="cart-item-content">
                                <h3 class="cart-item-name">${item.name}</h3>
                                ${item.options && item.options.length > 0 ? `
                                    <div class="cart-item-options">
                                        ${item.options.map(opt => `
                                            ${opt.options.length > 0 ? `
                                                <div class="option-group">
                                                    <div class="option-group-name">${opt.name}:</div>
                                                    <div class="option-values">
                                                        ${opt.options.map(option => `
                                                            <div class="option-value">
                                                                <span class="option-value-name">${option.name}</span>
                                                                ${option.price > 0 ? `<span class="option-value-price">+${currencyCode} ${formatPrice(option.price, currencyCode)}</span>` : ''}
                                                            </div>
                                                        `).join('')}
                                                    </div>
                                                </div>
                                            ` : ''}
                                        `).join('')}
                                    </div>
                                ` : ''}
                                <div class="cart-item-price">
                                    ${currencyCode} ${formatPrice(item.price, currencyCode)} x ${item.quantity}
                                </div>
                            </div>
                            <div class="cart-item-actions">
                                <button onclick="updateCartQuantity('${item.cartItemId}', -1)" class="quantity-btn">-</button>
                                <span class="quantity">${item.quantity}</span>
                                <button onclick="updateCartQuantity('${item.cartItemId}', 1)" class="quantity-btn">+</button>
                                <button onclick="removeCartItem('${item.cartItemId}')" class="remove-btn">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>

                <!-- Sección de Cupones -->
                <div class="coupon-section" id="coupon-section">
                    <h3><i class="fas fa-ticket-alt"></i> Cupón de Descuento</h3>
                    
                    <!-- Campo para ingresar código de cupón -->
                    <div class="coupon-input-group">
                        <input type="text" id="coupon-code" placeholder="Ingresa tu código de cupón" class="form-control">
                        <button type="button" id="apply-coupon-btn" class="btn btn-primary">
                            Aplicar Cupón
                        </button>
                    </div>

                    <!-- Área para mostrar información del cupón aplicado -->
                    <div id="coupon-info" style="display: none;"></div>

                    <!-- Área para mensajes -->
                    <div id="coupon-message"></div>
                </div>

                <!-- Resumen del pedido -->
                <div class="order-summary">
                    <h3>Resumen del Pedido</h3>
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>${currencyCode} ${formatPrice(cartTotal, currencyCode)}</span>
                    </div>
                    
                    ${appliedCoupon ? `
                        <div class="summary-row discount-row">
                            <span>Descuento (${appliedCoupon.code}):</span>
                            <span class="text-success">-${currencyCode} ${formatPrice(appliedCoupon.discount_amount, currencyCode)}</span>
                        </div>
                    ` : ''}
                    
                    <div class="summary-row total-row">
                        <span><strong>Total:</strong></span>
                        <span><strong>${currencyCode} ${formatPrice(finalTotal, currencyCode)}</strong></span>
                    </div>
                </div>

                <!-- Botón de checkout -->
                <div class="checkout-section">
                    <button type="button" onclick="proceedToCheckout()" class="btn btn-success btn-lg w-100">
                        Proceder al Pago
                    </button>
                </div>
            `;

            // Inicializar el gestor de cupones si no existe
            if (!window.couponManager) {
                const restaurantId = document.querySelector('[data-restaurant-id]')?.dataset.restaurantId;
                if (restaurantId) {
                    // Cargar el script de cupones si no está cargado
                    if (typeof CouponManager === 'undefined') {
                        const script = document.createElement('script');
                        script.src = '/assets/js/coupons.js';
                        script.onload = () => {
                            window.couponManager = new CouponManager(restaurantId);
                        };
                        document.head.appendChild(script);
                    } else {
                        window.couponManager = new CouponManager(restaurantId);
                    }
                }
            }
        }
    }
}

// Función para proceder al checkout con cupón
function proceedToCheckout() {
    const appliedCoupon = window.couponManager?.getCurrentCoupon();
    
    // Agregar información del cupón al formulario de checkout
    if (appliedCoupon) {
        // Crear campos ocultos para el cupón
        const couponFields = `
            <input type="hidden" name="coupon_id" value="${appliedCoupon.id}">
            <input type="hidden" name="coupon_code" value="${appliedCoupon.code}">
            <input type="hidden" name="discount_amount" value="${appliedCoupon.discount_amount}">
            <input type="hidden" name="final_total" value="${appliedCoupon.final_total}">
        `;
        
        // Agregar los campos al formulario
        const checkoutForm = document.getElementById('checkout-form');
        if (checkoutForm) {
            checkoutForm.insertAdjacentHTML('beforeend', couponFields);
        }
    }
    
    // Continuar con el proceso de checkout normal
    // ... código existente del checkout
}

// Función para limpiar cupón al completar orden
function clearCouponAfterOrder() {
    if (window.couponManager) {
        window.couponManager.clearCoupon();
    }
}

// CSS adicional para la sección de cupones
const couponStyles = `
<style>
.coupon-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.coupon-section h3 {
    margin-bottom: 15px;
    color: #333;
}

.coupon-input-group {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.coupon-input-group input {
    flex: 1;
    text-transform: uppercase;
}

.coupon-input-group button {
    white-space: nowrap;
}

#coupon-info {
    margin-top: 15px;
}

#coupon-info .alert {
    margin-bottom: 0;
}

.order-summary {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.discount-row {
    color: #28a745;
}

.total-row {
    border-top: 1px solid #dee2e6;
    padding-top: 10px;
    margin-top: 10px;
    font-size: 1.1em;
}

.checkout-section {
    margin-top: 20px;
}

@media (max-width: 768px) {
    .coupon-input-group {
        flex-direction: column;
    }
    
    .coupon-input-group button {
        width: 100%;
    }
}
</style>
`;

// Agregar estilos al documento
document.head.insertAdjacentHTML('beforeend', couponStyles);

// Reemplazar la función updateCartUI original
if (typeof updateCartUI === 'function') {
    // Guardar la función original
    const originalUpdateCartUI = updateCartUI;
    
    // Reemplazar con la nueva función que incluye cupones
    window.updateCartUI = updateCartUIWithCoupons;
} 