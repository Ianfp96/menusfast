// Definir BASE_URL desde una variable global de PHP
const BASE_URL = window.BASE_URL || '';

// Funciones para manejar el carrito con cookies
const CART_COOKIE_NAME = 'restaurant_cart';

// Función para obtener el carrito de las cookies
function getCartFromCookies() {
    const cartCookie = getCookie('cart');
    if (!cartCookie) return [];
    
    try {
        const cart = JSON.parse(cartCookie);
        console.log('Carrito recuperado de cookies:', cart);
        return cart;
    } catch (e) {
        console.error('Error al parsear el carrito:', e);
        return [];
    }
}

// Función para guardar el carrito en las cookies
function saveCartToCookies(cart) {
    try {
        setCookie('cart', JSON.stringify(cart), 7); // Guardar por 7 días
        console.log('Carrito guardado en cookies:', cart);
    } catch (e) {
        console.error('Error al guardar el carrito:', e);
    }
}

// Función para agregar un producto al carrito
function addToCart(productData) {
    // Debug: Mostrar los datos recibidos
    console.log('Datos recibidos en addToCart:', productData);

    const cart = getCartFromCookies();
    
    // Buscar si existe un producto idéntico (mismo ID y mismas opciones)
    const existingItemIndex = cart.findIndex(item => {
        if (item.id !== productData.id) return false;
        
        // Comparar las opciones
        if (!item.options && !productData.options) return true;
        if (!item.options || !productData.options) return false;
        
        // Convertir las opciones a strings para comparar
        const itemOptionsStr = JSON.stringify(item.options);
        const productOptionsStr = JSON.stringify(productData.options);
        
        return itemOptionsStr === productOptionsStr;
    });

    if (existingItemIndex > -1) {
        // Actualizar cantidad si el producto ya existe
        cart[existingItemIndex].quantity += productData.quantity;
        console.log('Producto actualizado en el carrito:', cart[existingItemIndex]);
    } else {
        // Agregar nuevo producto
        cart.push(productData);
        console.log('Nuevo producto agregado al carrito:', productData);
    }

    saveCartToCookies(cart);
    updateCartUI();
    return cart;
}

// Función para actualizar la cantidad de un producto
function updateCartQuantity(itemId, change) {
    const cart = getCartFromCookies();
    const itemIndex = cart.findIndex(item => item.cartItemId === itemId);

    if (itemIndex > -1) {
        const newQuantity = cart[itemIndex].quantity + change;
        
        // Solo permitir cantidades entre 1 y 99
        if (newQuantity >= 1 && newQuantity <= 99) {
            cart[itemIndex].quantity = newQuantity;
            saveCartToCookies(cart);
            updateCartUI();
        }
    }
}

// Función para eliminar un producto del carrito
function removeCartItem(itemId) {
    const cartItem = document.querySelector(`.floating-cart-item[data-item-id="${itemId}"]`);
    if (cartItem) {
        // Agregar clase para la animación
        cartItem.classList.add('removing');
        
        // Esperar a que termine la animación antes de eliminar
        setTimeout(() => {
            const cart = getCartFromCookies();
            const updatedCart = cart.filter(item => item.cartItemId !== itemId);
            saveCartToCookies(updatedCart);
            updateCartUI();
        }, 300); // Duración de la animación
    } else {
        // Si no se encuentra el elemento en el DOM, actualizar directamente
        const cart = getCartFromCookies();
        const updatedCart = cart.filter(item => item.cartItemId !== itemId);
        saveCartToCookies(updatedCart);
        updateCartUI();
    }
}

// Función para parsear precio desde texto según la moneda
function parsePriceFromText(priceText, currencyCode) {
    if (!priceText || priceText === '0') return 0;
    
    // Obtener configuración de decimales según la moneda
    const currencyDecimals = {
        'CLP': 0, 'COP': 0, 'ARS': 0, 'VES': 0, // Sin decimales
        'USD': 2, 'EUR': 2, 'GBP': 2, 'MXN': 2, 'PEN': 2, 'BRL': 2, 'UYU': 2 // Con decimales
    };
    
    const decimals = currencyDecimals[currencyCode] || 0;
    
    // Remover el código de moneda y cualquier texto adicional
    let cleanText = priceText.replace(new RegExp(currencyCode, 'gi'), '').trim();
    
    // Remover símbolos comunes de moneda
    cleanText = cleanText.replace(/[+$€£¥¢]/g, '').trim();
    
    // Manejar separadores de miles según la moneda
    if (decimals === 0) {
        // Para monedas sin decimales (CLP, COP, ARS, VES), remover puntos como separadores de miles
        cleanText = cleanText.replace(/\./g, '');
    } else {
        // Para monedas con decimales, manejar comas como separadores de miles
        cleanText = cleanText.replace(/,/g, '');
    }
    
    const price = parseFloat(cleanText);
    return isNaN(price) ? 0 : price;
}

// Función para formatear precios según la moneda
function formatPrice(price, currencyCode) {
    // Obtener configuración de decimales según la moneda
    const currencyDecimals = {
        'CLP': 0, 'COP': 0, 'ARS': 0, 'VES': 0, // Sin decimales
        'USD': 2, 'EUR': 2, 'GBP': 2, 'MXN': 2, 'PEN': 2, 'BRL': 2, 'UYU': 2 // Con decimales
    };
    
    const decimals = currencyDecimals[currencyCode] || 0;
    return price.toFixed(decimals);
}

// Función para actualizar la UI del carrito
function updateCartUI() {
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
                                <div class="cart-item-price">${currencyCode} ${formatPrice(item.price, currencyCode)}</div>
                                <div class="cart-item-actions">
                                    <div class="quantity-selector">
                                        <button class="quantity-button" onclick="updateCartQuantity(${item.cartItemId}, -1)">-</button>
                                        <input type="number" class="quantity-input" value="${item.quantity}" readonly>
                                        <button class="quantity-button" onclick="updateCartQuantity(${item.cartItemId}, 1)">+</button>
                                    </div>
                                    <button class="remove-button" onclick="removeCartItem(${item.cartItemId})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
                
                <div class="cart-summary">
                    <div class="cart-total">
                        <span>Total:</span>
                        <span>${currencyCode} ${formatPrice(cartTotal, currencyCode)}</span>
                    </div>
                    <a href="${window.location.pathname.split('/cart')[0]}/checkout" class="checkout-button">
                        Proceder al pago
                    </a>
                </div>
            `;
        }
    }

    // Actualizar contenido del carrito flotante
    const cartItemsContainer = document.querySelector('.floating-cart-items');
    if (cartItemsContainer) {
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = `
                <div class="empty-cart-message">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Tu carrito está vacío</p>
                </div>
            `;
        } else {
            cartItemsContainer.innerHTML = cart.map(item => `
                <div class="floating-cart-item" data-item-id="${item.cartItemId}">
                    <div class="item-image">
                        <img src="${BASE_URL}/uploads/${item.image}" alt="${item.name}" loading="lazy">
                    </div>
                    <div class="item-info">
                        <h4>${item.name}</h4>
                        ${item.options ? `
                            <div class="item-options">
                                ${item.options.map(opt => 
                                    opt.options.map(option => 
                                        `<small>${option.name}${option.price > 0 ? ' (+' + currencyCode + ' ' + formatPrice(option.price, currencyCode) + ')' : ''}</small>`
                                    ).join('')
                                ).join('')}
                            </div>
                        ` : ''}
                        <div class="item-price">${currencyCode} ${formatPrice(item.price, currencyCode)}</div>
                    </div>
                    <div class="item-actions">
                        <div class="quantity-selector">
                            <button onclick="updateCartQuantity(${item.cartItemId}, -1)">-</button>
                            <span>${item.quantity}</span>
                            <button onclick="updateCartQuantity(${item.cartItemId}, 1)">+</button>
                        </div>
                        <button class="remove-item" onclick="removeCartItem(${item.cartItemId})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }
    }

    // Actualizar total y botón de checkout
    const cartFooter = document.querySelector('.floating-cart-footer');
    if (cartFooter) {
        if (cart.length > 0) {
            cartFooter.innerHTML = `
                <div class="cart-total">
                    <span>Total:</span>
                    <span>${currencyCode} ${formatPrice(cartTotal, currencyCode)}</span>
                </div>
                <button onclick="openCheckoutModal()" class="checkout-button">
                    Proceder al pago
                </button>
            `;
        } else {
            cartFooter.style.display = 'none';
        }
    }
}

// Funciones auxiliares para manejar cookies
function setCookie(name, value, days) {
    let expires = "";
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

function getCookie(name) {
    const nameEQ = name + "=";
    const ca = document.cookie.split(';');
    for(let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

// Inicializar el carrito al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    updateCartUI();
}); 