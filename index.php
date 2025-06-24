<?php 
// Incluir funciones auxiliares - COMENTADO TEMPORALMENTE PARA EVITAR ERRORES
// require_once 'config/database.php';
// require_once __DIR__ . '/config/functions.php';

// Iniciar sesión para verificar autenticación
session_start();

// Verificar si el usuario está logueado
$isLoggedIn = isset($_SESSION['restaurant_id']);



// Definir BASE_URL temporalmente
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/webmenu');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title>Tumenufast - Digitaliza tu Restaurante | Menú Digital QR | SaaS Gastronómico</title>
    <meta name="description" content="Digitaliza tu restaurante con Tumenufast. Crea menús digitales con QR, gestiona múltiples sucursales, personaliza tu marca y aumenta ventas. Plataforma SaaS líder para restaurantes. Prueba gratis 7 días.">
    <meta name="keywords" content="menú digital, restaurante, QR code, digitalización, SaaS, gastronomía, pedidos online, gestión restaurante, menú QR, restaurante digital">
    <meta name="author" content="Tumenufast">
    <meta name="robots" content="index, follow">
    <meta name="language" content="Spanish">
    <meta name="revisit-after" content="7 days">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://tumenufast.com/">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://tumenufast.com/">
    <meta property="og:title" content="Tumenufast - Digitaliza tu Restaurante | Menú Digital QR">
    <meta property="og:description" content="Digitaliza tu restaurante con Tumenufast. Crea menús digitales con QR, gestiona múltiples sucursales y aumenta ventas. Prueba gratis 7 días.">
    <meta property="og:image" content="https://tumenufast.com/assets/images/og-image.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="Tumenufast">
    <meta property="og:locale" content="es_ES">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://tumenufast.com/">
    <meta property="twitter:title" content="Tumenufast - Digitaliza tu Restaurante | Menú Digital QR">
    <meta property="twitter:description" content="Digitaliza tu restaurante con Tumenufast. Crea menús digitales con QR, gestiona múltiples sucursales y aumenta ventas. Prueba gratis 7 días.">
    <meta property="twitter:image" content="https://tumenufast.com/assets/images/twitter-image.jpg">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/webmenu/uploads/img-web/img-tumenufast.png">
   <link rel="shortcut icon" type="image/png" href="/webmenu/uploads/img-web/img-tumenufast.png">
   <link rel="apple-touch-icon" href="/webmenu/uploads/img-web/img-tumenufast.png">
    
    <!-- Preconnect to external domains -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "Tumenufast",
        "description": "Plataforma SaaS para digitalizar restaurantes con menús digitales QR, gestión de múltiples sucursales y herramientas de análisis.",
        "url": "https://tumenufast.com",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "offers": {
            "@type": "Offer",
            "price": "7.99",
            "priceCurrency": "CLP",
            "priceSpecification": {
                "@type": "UnitPriceSpecification",
                "price": "7.99",
                "priceCurrency": "CLP",
                "unitText": "MONTH"
            }
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "ratingCount": "1250"
        },
        "provider": {
            "@type": "Organization",
            "name": "Tumenufast",
            "url": "https://tumenufast.com",
            "contactPoint": {
                "@type": "ContactPoint",
                "contactType": "customer service",
                "email": "tumenufast@gmail.com"
            }
        }
    }
    </script>
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Tumenufast",
        "url": "https://tumenufast.com",
        "logo": "https://tumenufast.com/assets/images/logo.png",
        "description": "Plataforma líder para digitalizar restaurantes con menús digitales QR y herramientas de gestión.",
        "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "customer service",
            "email": "tumenufast@gmail.com"
        },
        "sameAs": [
            "https://facebook.com/tumenufast",
            "https://twitter.com/tumenufast",
            "https://instagram.com/tumenufast",
            "https://linkedin.com/company/tumenufast"
        ]
    }
    </script>
    
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #ffa500;
            --accent-color: #4ecdc4;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
            --success-color: #28a745;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            overflow-x: hidden; /* Prevenir scroll horizontal */
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            padding-top: 76px; /* Añadido para compensar el header fijo */
        }
        
        /* Header */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-link {
            font-weight: 500;
            color: var(--dark-color) !important;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover {
            color: var(--primary-color) !important;
        }
        
        /* Estilos para el botón del navbar en móviles */
        .navbar-toggler {
            border: none;
            padding: 0.25rem 0.5rem;
            transition: all 0.3s ease;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
            outline: none;
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='%232c3e50' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        .navbar-toggler:hover .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='%23ff6b6b' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.1)" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            animation: fadeInUp 1s ease;
        }
        
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease 0.2s both;
        }
        
        .btn-hero {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            animation: fadeInUp 1s ease 0.4s both, pulseGlow 2s ease-in-out infinite;
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-hero:hover::before {
            left: 100%;
        }
        
        .btn-hero:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 40px rgba(255, 107, 107, 0.4), 0 0 30px rgba(255, 107, 107, 0.6);
            color: white;
            animation: pulseGlow 1s ease-in-out infinite;
        }
        
        /* Animación de pulso y brillo */
        @keyframes pulseGlow {
            0% {
                box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3), 0 0 0 0 rgba(255, 107, 107, 0.7);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3), 0 0 0 10px rgba(255, 107, 107, 0);
                transform: scale(1.02);
            }
            100% {
                box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3), 0 0 0 0 rgba(255, 107, 107, 0);
                transform: scale(1);
            }
        }
        
        /* Animación de rebote para el ícono */
        .btn-hero i {
            animation: bounce 2s infinite;
            display: inline-block;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-5px);
            }
            60% {
                transform: translateY(-3px);
            }
        }
        
        /* Efecto de partículas flotantes alrededor del botón */
        .btn-hero-container {
            position: relative;
            display: inline-block;
        }
        
        .btn-hero-container::before,
        .btn-hero-container::after {
            content: '';
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 50%;
            animation: float 3s ease-in-out infinite;
        }
        
        .btn-hero-container::before {
            top: -10px;
            left: 20%;
            animation-delay: 0s;
        }
        
        .btn-hero-container::after {
            top: -15px;
            right: 20%;
            animation-delay: 1.5s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) scale(1);
                opacity: 0.7;
            }
            50% {
                transform: translateY(-20px) scale(1.2);
                opacity: 1;
            }
        }
        
        .hero-image {
            animation: fadeInRight 1s ease 0.6s both;
        }
        
        /* Features Section */
        .features {
            padding: 100px 0;
            background: var(--light-color);
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            border: none;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }
        
        .feature-card h4 {
            color: var(--dark-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        /* Pricing Section */
        .pricing {
            padding: 100px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .pricing-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            border: 2px solid transparent;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .pricing-card.featured {
            border-color: var(--primary-color);
            transform: scale(1.05);
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        }
        
        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }
        
        .pricing-card.featured:hover {
            transform: scale(1.05) translateY(-10px);
        }
        
        .pricing-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 8px 25px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 10;
        }
        
        .pricing-header {
            margin-bottom: 2rem;
        }
        
        .pricing-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .pricing-description {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }
        
        .pricing-price {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 1rem 0;
            line-height: 1;
        }
        
        .pricing-price small {
            font-size: 1rem;
            font-weight: 400;
            color: #6c757d;
        }
        
        .pricing-features {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
            flex-grow: 1;
        }
        
        .pricing-features li {
            padding: 0.8rem 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .pricing-features li:last-child {
            border-bottom: none;
        }
        
        .pricing-features li i {
            color: var(--success-color);
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }
        
        .pricing-features li span {
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .pricing-cta {
            margin-top: 2rem;
        }
        
        .btn-pricing {
            width: 100%;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .btn-pricing.primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-pricing.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
            color: white;
        }
        
        .btn-pricing.outline {
            background: transparent;
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-pricing.outline:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Mobile Carousel Styles */
        .pricing-carousel {
            display: none;
        }
        
        .pricing-carousel .carousel-item {
            padding: 0 10px;
        }
        
        .pricing-carousel .pricing-card {
            margin: 0 auto;
            max-width: 350px;
        }
        
        .carousel-control-prev,
        .carousel-control-next {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.8;
        }
        
        .carousel-control-prev {
            left: -25px;
        }
        
        .carousel-control-next {
            right: -25px;
        }
        
        .carousel-control-prev:hover,
        .carousel-control-next:hover {
            opacity: 1;
            background: var(--primary-color);
        }
        
        /* Ocultar controles del carrusel en escritorio */
        @media (min-width: 769px) {
            .carousel-control-prev,
            .carousel-control-next {
                display: none !important;
            }
        }
        
        .carousel-indicators {
            bottom: -50px;
        }
        
        .carousel-indicators button {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #dee2e6;
            border: none;
            margin: 0 5px;
        }
        
        .carousel-indicators button.active {
            background-color: var(--primary-color);
        }
        
        /* Pricing Info Styles */
        .pricing-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid #dee2e6;
            margin-top: 3rem;
        }
        
        .pricing-info h5 {
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .pricing-info .d-flex {
            font-size: 0.95rem;
            color: var(--dark-color);
        }
        
        .pricing-info i {
            font-size: 1.1rem;
        }
        
        /* Pricing Toggle Styles */
        .pricing-toggle-container {
            text-align: center;
            margin-bottom: 3rem;
            background: white;
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .pricing-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-check-input {
            width: 3.5rem;
            height: 2rem;
            background-color: #e9ecef;
            border: 2px solid #dee2e6;
            border-radius: 1rem;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            font-weight: 600;
            color: var(--dark-color);
            cursor: pointer;
            font-size: 1.1rem;
        }
        
        .annual-savings-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        .pricing-price.annual {
            color: #28a745;
        }
        
        .pricing-price.annual small {
            color: #28a745;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .pricing-desktop {
                display: none;
            }
            
            .pricing-carousel {
                display: block;
            }
            
            .pricing-card.featured {
                transform: none;
            }
            
            .pricing-card.featured:hover {
                transform: translateY(-10px);
            }
            
            .pricing {
                padding: 60px 0 100px;
            }
        }
        
        /* Testimonials */
        .testimonials {
            padding: 100px 0;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .testimonial-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .testimonial-card h5 {
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            font-weight: 600;
        }
        
        .testimonial-card p {
            color: #f8f9fa;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .testimonial-card small {
            color: #e9ecef;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .testimonial-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        
        /* CTA Section */
        .cta {
            padding: 100px 0;
            background: var(--dark-color);
            color: white;
            text-align: center;
        }
        
        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .cta p {
            color: #f8f9fa;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .urgency-message p {
            color: #fff3cd;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            font-weight: 600;
        }
        
        /* Footer */
        .footer {
            background: #1a1a1a;
            color: white;
            padding: 50px 0 20px;
        }
        
        .footer h5 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .footer a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer a:hover {
            color: var(--primary-color);
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Scroll animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Mejoras de contraste para secciones con fondos de colores */
        .hero p {
            color: #ffffff;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }
        
        .hero .social-proof p {
            color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
            font-weight: 500;
        }
        
        .hero small {
            color: #f8f9fa !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        /* Mejoras para botones en secciones oscuras */
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.8);
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .btn-outline-light:hover {
            background-color: #ffffff;
            color: var(--dark-color);
            border-color: #ffffff;
        }
        
        /* WhatsApp support button styling */
        .whatsapp-support .btn-success {
            background: linear-gradient(135deg, #25d366, #128c7e);
            border: none;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
            transition: all 0.3s ease;
        }
        
        .whatsapp-support .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
        }
        
        /* How it Works Section */
        .how-it-works {
            padding: 100px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .steps-list {
            margin-top: 2rem;
        }
        
        .step-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .step-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }
        
        .step-content h5 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .step-content p {
            color: #6c757d;
            margin-bottom: 0;
        }
        
        /* Phone Mockup */
        .mockup-container {
            position: relative;
            display: inline-block;
            max-width: 100%;
        }
        
        .mobile-menu-preview {
            max-width: 300px;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        
        .mobile-menu-preview:hover {
            transform: translateY(-10px);
        }
        
        /* Nueva clase específica para la imagen del hero */
        .hero-mockup-image {
            max-width: 550px;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        
        .hero-mockup-image:hover {
            transform: translateY(-10px);
        }
        
        @media (max-width: 768px) {
            .mobile-menu-preview {
                max-width: 250px;
            }
            
            .hero-mockup-image {
                max-width: 300px;
            }
        }
        
        .phone-mockup {
            width: 280px;
            height: 500px;
            background: #2c3e50;
            border-radius: 30px;
            padding: 8px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin: 0 auto;
        }
        
        .phone-screen {
            width: 100%;
            height: 100%;
            background: white;
            border-radius: 22px;
            overflow: hidden;
            position: relative;
        }
        
        .menu-preview {
            padding: 1rem;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .menu-header {
            text-align: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 1rem;
        }
        
        .menu-header i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .menu-header h4 {
            margin: 0;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .menu-item-preview {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .item-info h6 {
            margin: 0;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .item-info p {
            margin: 0;
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .add-btn {
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            font-weight: 700;
            cursor: pointer;
        }
        
        .cart-preview {
            margin-top: auto;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
        }
        
        .cart-items {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .total {
            color: var(--primary-color);
        }
        
        .whatsapp-btn {
            width: 100%;
            background: #25d366;
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        /* Dashboard Section */
        .dashboard-section {
            padding: 100px 0;
            background: white;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .feature-item {
            text-align: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .feature-item i {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .feature-item h5 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .feature-item p {
            color: #6c757d;
            margin: 0;
            font-size: 0.9rem;
        }
        
        /* Dashboard Mockup */
        .dashboard-mockup {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
            margin: 0 auto;
        }
        
        .dashboard-header {
            background: var(--dark-color);
            padding: 1rem;
        }
        
        .dashboard-nav {
            display: flex;
            gap: 1rem;
        }
        
        .nav-item {
            color: #ccc;
            padding: 0rem 0rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .nav-item.active {
            background: var(--primary-color);
            color: white;
        }
        
        .dashboard-content {
            padding: 1.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card h3 {
            color: var(--primary-color);
            margin: 0;
            font-weight: 700;
        }
        
        .stat-card p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .recent-orders h5 {
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .status {
            background: #28a745;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
        }
        
        /* Store Preview Section */
        .store-preview {
            padding: 100px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .store-preview .hero-mockup-image {
            max-width: 600px;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        
        .store-preview .hero-mockup-image:hover {
            transform: translateY(-10px);
        }
        
        @media (max-width: 768px) {
            .store-preview .hero-mockup-image {
                max-width: 400px;
            }
        }
        
        .benefits-list {
            margin-top: 2rem;
        }
        
        .benefit-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .benefit-item i {
            font-size: 1.5rem;
            margin-right: 1rem;
            margin-top: 0.2rem;
        }
        
        .benefit-item h5 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .benefit-item p {
            color: #6c757d;
            margin: 0;
        }
        
        /* Desktop Mockup */
        .store-mockup-container {
            position: relative;
            display: inline-block;
        }
        
        .desktop-mockup {
            width: 100%;
            max-width: 500px;
            background: #2c3e50;
            border-radius: 15px;
            padding: 8px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin: 0 auto;
        }
        
        .desktop-screen {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            min-height: 400px;
        }
        
        .store-preview-content {
            padding: 1.5rem;
        }
        
        .store-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .store-logo {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .store-info h3 {
            margin: 0;
            color: var(--dark-color);
            font-weight: 700;
        }
        
        .store-info p {
            margin: 0;
            color: #6c757d;
        }
        
        .categories-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .category {
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .category.active {
            background: var(--primary-color);
            color: white;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .product-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }
        
        .product-info h5 {
            margin: 0 0 0.5rem 0;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .product-info p {
            margin: 0 0 1rem 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .price {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .add-to-cart {
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            font-weight: 700;
            cursor: pointer;
        }
        
        .cart-summary {
            background: var(--dark-color);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-total {
            font-weight: 700;
        }
        
        .order-btn {
            background: #25d366;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        /* Video Section */
        .video-section {
            padding: 100px 0;
            background: white;
        }
        
        .video-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .video-wrapper {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        
        .video-placeholder {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 3rem;
            text-align: center;
            color: white;
        }
        
        .video-thumbnail {
            position: relative;
            margin-bottom: 2rem;
        }
        
        .play-button {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .play-button:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }
        
        .play-button i {
            font-size: 2rem;
            margin-left: 5px;
        }
        
        .video-info h4 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .video-info p {
            opacity: 0.8;
            margin: 0;
        }
        
        .video-description {
            text-align: left;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .video-description ul {
            list-style: none;
            padding: 0;
        }
        
        .video-description li {
            padding: 0.3rem 0;
        }
        
        /* FAQ Section */
        .faq-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .faq-item {
            background: white;
            border-radius: 15px;
            margin-bottom: 1rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-item:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .faq-question {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .faq-question:hover {
            background: #f8f9fa;
        }
        
        .faq-question h5 {
            margin: 0;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .faq-question i {
            color: var(--primary-color);
            transition: transform 0.3s ease;
        }
        
        .faq-question[aria-expanded="true"] i {
            transform: rotate(180deg);
        }
        
        .faq-answer {
            padding: 0 1.5rem 1.5rem;
            color: #6c757d;
            line-height: 1.6;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .phone-mockup {
                width: 250px;
                height: 450px;
            }
            
            .dashboard-mockup {
                max-width: 100%;
            }
            
            .desktop-mockup {
                max-width: 100%;
            }
            
            /* Ajustes específicos para móviles */
            body {
                padding-top: 60px; /* Reducir el padding-top en móviles */
                overflow-x: hidden; /* Prevenir scroll horizontal */
            }
            
            .navbar {
                padding: 0.5rem 1rem; /* Reducir padding del navbar */
            }
            
            .navbar-brand {
                font-size: 1.2rem; /* Reducir tamaño del logo */
            }
            
            .hero {
                min-height: 100vh;
                padding: 2rem 0; /* Agregar padding vertical */
            }
            
            .hero h1 {
                font-size: 2.5rem; /* Reducir tamaño del título */
                margin-bottom: 1rem;
            }
            
            .hero p {
                font-size: 1.1rem; /* Reducir tamaño del texto */
                margin-bottom: 1.5rem;
            }
            
            .btn-hero {
                padding: 12px 30px; /* Reducir padding del botón */
                font-size: 1rem;
            }
            
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .features {
                padding: 60px 0; /* Reducir padding de secciones */
            }
            
            .pricing {
                padding: 60px 0;
            }
            
            .testimonials {
                padding: 60px 0;
            }
            
            .cta {
                padding: 60px 0;
            }
            
            .faq-section {
                padding: 60px 0;
            }
            
            .how-it-works {
                padding: 60px 0;
            }
            
            .dashboard-section {
                padding: 60px 0;
            }
            
            .store-preview {
                padding: 60px 0;
            }
            
            .video-section {
                padding: 60px 0;
            }
            
            /* Ajustes para elementos específicos */
            .feature-card {
                padding: 1.5rem; /* Reducir padding de las tarjetas */
            }
            
            .pricing-card {
                padding: 1.5rem;
            }
            
            .testimonial-card {
                padding: 1.5rem;
            }
            
            /* Asegurar que no haya márgenes o paddings excesivos */
            .row {
                margin-left: 0;
                margin-right: 0;
            }
            
            .col-12, .col-lg-6, .col-lg-4, .col-md-6 {
                padding-left: 15px;
                padding-right: 15px;
            }
        }
        
        /* Ajustes adicionales para pantallas muy pequeñas */
        @media (max-width: 576px) {
            body {
                padding-top: 56px; /* Aún menos padding para pantallas muy pequeñas */
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .btn-hero {
                padding: 10px 25px;
                font-size: 0.9rem;
            }
            
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .feature-card, .pricing-card, .testimonial-card {
                padding: 1rem;
            }
        }
    </style>

</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top" role="navigation" aria-label="Navegación principal">
        <div class="container">
            <a class="navbar-brand" href="#home" aria-label="Tumenufast - Inicio">
                <i class="fas fa-utensils" aria-hidden="true"></i> Tumenufast
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Características</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Precios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Testimonios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/juan" target="_blank">Ver Ejemplo</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <!-- Usuario logueado -->
                        <li class="nav-item">
                            <a class="btn btn-primary ms-2" href="/restaurante/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Ir al Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/restaurante/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Usuario no logueado -->
                        <li class="nav-item">
                            <a class="nav-link" href="/restaurante/login.php">Iniciar Sesión</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-outline-primary ms-2" href="/restaurante/registro.php">Comenzar Gratis</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero" role="banner" aria-labelledby="hero-title">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 id="hero-title">Digitaliza tu Restaurante en Minutos</h1>
                        <p>Convierte tu restaurante en una tienda online en minutos. Recibe pedidos por WhatsApp sin comisiones ni apps.</p>
                        
                        
                        
                        <div class="btn-hero-container">
                            <?php if ($isLoggedIn): ?>
                                <a href="/restaurante/dashboard.php" class="btn-hero" aria-label="Ir al dashboard">
                                    <i class="fas fa-tachometer-alt"></i> Ir al Dashboard
                                </a>
                            <?php else: ?>
                                <a href="/restaurante/registro.php" class="btn-hero" aria-label="Probar TuMenu ahora gratis">
                                    🔸 Probar TuMenu Ahora Gratis
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4">
                            <?php if ($isLoggedIn): ?>
                                <small class="opacity-75">
                                    <i class="fas fa-user" aria-hidden="true"></i> Bienvenido de vuelta • 
                                    <i class="fas fa-tachometer-alt" aria-hidden="true"></i> Gestiona tu menú • 
                                    <i class="fas fa-chart-line" aria-hidden="true"></i> Ve tus estadísticas
                                </small>
                            <?php else: ?>
                                <small class="opacity-75">
                                    <i class="fas fa-check" aria-hidden="true"></i> 7 días gratis • 
                                    <i class="fas fa-check" aria-hidden="true"></i> Sin tarjeta de crédito • 
                                    <i class="fas fa-check" aria-hidden="true"></i> Menú online listo en minutos
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="image-wrapper text-center fade-in">
                        <div class="mockup-container">
                            <img src="/uploads/mockup-img/mockup1.png""
                                 alt="Vista previa del menú digital en móvil" 
                                 class="img-fluid hero-mockup-image"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='/assets/img/no-image.png';">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features" role="region" aria-labelledby="features-title">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 id="features-title" class="display-4 fw-bold mb-3 fade-in">Todo lo que necesitas para vender más y atender mejor</h2>
                    <p class="lead text-muted fade-in">Herramientas profesionales diseñadas específicamente para el sector gastronómico</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <article class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-qrcode" aria-hidden="true"></i>
                        </div>
                        <h3>Menú Digital QR</h3>
                        <p>Los clientes escanean y ven el menú sin apps desde cualquier celular. Interfaz limpia y rápida para una mejor experiencia.</p>
                    </article>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <article class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-store" aria-hidden="true"></i>
                        </div>
                        <h3>Múltiples Sucursales</h3>
                        <p>Maneja varias ubicaciones con su propio menú y configuración. Control centralizado para todas tus operaciones.</p>
                    </article>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <article class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-palette" aria-hidden="true"></i>
                        </div>
                        <h3>Personalización Total</h3>
                        <p>Personaliza colores, logos, banners y toda la apariencia para que refleje la identidad de tu marca. Diseño único para tu negocio.</p>
                    </article>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <article class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line" aria-hidden="true"></i>
                        </div>
                        <h3>Estadísticas Avanzadas</h3>
                        <p>Analiza el rendimiento de tu menú, productos más vendidos y comportamiento de tus clientes. Toma decisiones basadas en datos.</p>
                    </article>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <article class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt" aria-hidden="true"></i>
                        </div>
                        <h3>100% Responsive</h3>
                        <p>Tu menú se ve perfecto en cualquier dispositivo: móviles, tablets y computadoras. Experiencia optimizada para todos.</p>
                    </article>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <article class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-headset" aria-hidden="true"></i>
                        </div>
                        <h3>Soporte 24/7</h3>
                        <p>Siempre disponible por WhatsApp, chat o email. Nuestro equipo está listo para ayudarte en cualquier momento del día.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <!-- How it Works Section -->
    <section class="how-it-works" role="region" aria-labelledby="how-it-works-title">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="content-wrapper">
                        <h2 id="how-it-works-title" class="display-5 fw-bold mb-4 fade-in">¿Cómo funciona TuMenuFast?</h2>
                        <p class="lead text-muted mb-4 fade-in">En solo 3 pasos, puedes empezar a recibir pedidos por WhatsApp desde tu menú web.</p>
                        
                        <div class="steps-list">
                            <div class="step-item fade-in">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h5>Crea tu menú en minutos</h5>
                                    <p>crea tu cuenta de forma gratuita y comienza a crear tu menú en minutos.</p>
                                </div>
                            </div>
                            
                            <div class="step-item fade-in">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h5>Configura tu menú</h5>
                                    <p>Sube tus productos, precios e imágenes. Personaliza colores, logo de tu marca y compartelo</p>
                                </div>
                            </div>
                            
                            <div class="step-item fade-in">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h5>Recibe pedidos</h5>
                                    <p>Los clientes agregan productos al carrito y te envían el pedido por WhatsApp automáticamente.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="image-wrapper text-center fade-in">
                        <div class="mockup-container">
                            <img src="/uploads/mockup-img/movil1.gif" 
                                 alt="Vista previa del menú digital en móvil" 
                                 class="img-fluid mobile-menu-preview"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='/assets/img/no-image.png';">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Dashboard Section -->
    <section class="dashboard-section" role="region" aria-labelledby="dashboard-title">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 order-lg-2">
                    <div class="content-wrapper">
                        <h2 id="dashboard-title" class="display-5 fw-bold mb-4 fade-in">Gestiona todo desde un solo lugar</h2>
                        <p class="lead text-muted mb-4 fade-in">Edita tu menú, cambia tus horarios, crea sucursales y recibe estadísticas en tiempo real. Todo desde tu panel simple y rápido.</p>
                        
                        <div class="features-grid">
                            <div class="feature-item fade-in">
                                <i class="fas fa-edit text-primary"></i>
                                <h5>Edita tu menú</h5>
                                <p>Agrega, modifica o elimina productos en segundos</p>
                            </div>
                            
                            <div class="feature-item fade-in">
                                <i class="fas fa-clock text-primary"></i>
                                <h5>Gestiona horarios</h5>
                                <p>Configura horarios de atención por sucursal</p>
                            </div>
                            
                            <div class="feature-item fade-in">
                                <i class="fas fa-chart-bar text-primary"></i>
                                <h5>Estadísticas en tiempo real</h5>
                                <p>Ve qué productos se venden más y cuándo</p>
                            </div>
                            
                            <div class="feature-item fade-in">
                                <i class="fas fa-store text-primary"></i>
                                <h5>Múltiples sucursales</h5>
                                <p>Administra varias ubicaciones desde un solo panel</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 order-lg-1">
                    <img src="/uploads/mockup-img/mockup2.png" width="100%" height="100%"
                    class="img-fluid hero-mockup-image"
                    loading="lazy"
                    onerror="this.onerror=null; this.src='/assets/img/no-image.png';">
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing" role="region" aria-labelledby="pricing-title">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 id="pricing-title" class="display-4 fw-bold mb-3 fade-in">Planes que se adaptan a tu negocio</h2>
                    <p class="lead text-muted fade-in">Elige el plan ideal para tu negocio. Todos incluyen 7 días de prueba gratis.</p>
                </div>
            </div>
            
            <!-- Pricing Toggle -->
            <div class="pricing-toggle-container fade-in">
                <div class="pricing-toggle">
                    <span class="form-check-label">Mensual</span>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="pricingToggle" aria-label="Cambiar entre precios mensuales y anuales">
                    </div>
                    <span class="form-check-label">Anual</span>
                </div>
                <div class="annual-savings-badge" id="annualSavings" style="display: none;">
                    <i class="fas fa-gift" aria-hidden="true"></i> ¡Ahorra 40% con plan anual!
                </div>
            </div>
            
            <!-- Desktop Pricing -->
            <div class="pricing-desktop">
                <div class="row g-4 justify-content-center">
                    <!-- Plan Básico -->
                    <div class="col-lg-4 col-md-6">
                        <article class="pricing-card fade-in" itemscope itemtype="https://schema.org/Product">
                            <meta itemprop="name" content="Plan Básico Tumenufast">
                            <meta itemprop="description" content="Plan básico para restaurantes pequeños con menú digital QR y hasta 50 productos">
                            <div class="pricing-header">
                                <h3 class="pricing-name">Básico</h3>
                                <p class="pricing-description">Perfecto para restaurantes pequeños</p>
                                <div class="pricing-price monthly-price">
                                    <span itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                                        <span itemprop="price" content="7990">$7.990</span><small>/mes</small>
                                        <meta itemprop="priceCurrency" content="CLP">
                                        <meta itemprop="priceSpecification" itemscope itemtype="https://schema.org/UnitPriceSpecification">
                                        <meta itemprop="price" content="7990">
                                        <meta itemprop="priceCurrency" content="CLP">
                                        <meta itemprop="unitText" content="MONTH">
                                    </span>
                                </div>
                                <div class="pricing-price annual-price" style="display: none;">
                                    $57.528<small>pago único anual</small>
                                    <div class="mt-2">
                                        <small class="text-muted">¡Ahorra 40% con plan anual!</small>
                                    </div>
                                </div>
                            </div>
                            
                            <ul class="pricing-features">
                                <li><i class="fas fa-check"></i><span>Menú Digital QR</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Pedidos por WhatsApp</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>1 Sucursal</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Hasta 20 menus</span></li>
                                <li><i class="fas fa-times" aria-hidden="true"></i><span>Personalización básica</span></li>
                                <li><i class="fas fa-times" aria-hidden="true"></i><span>Sin Estadísticas </span></li>
                            </ul>
                            
                            <div class="pricing-cta">
                                <a href="https://wa.link/740d30" class="btn-pricing outline monthly-btn" data-plan="basico" data-duration="monthly">
                                    Suscribirse
                                </a>
                                <a href="https://wa.link/a33otx" class="btn-pricing outline annual-btn" data-plan="basico" data-duration="annual" style="display: none;">
                                    Suscribirse
                                </a>
                            </div>
                        </article>
                    </div>
                    
                    <!-- Plan Premium -->
                    <div class="col-lg-4 col-md-6">
                        <article class="pricing-card featured fade-in" itemscope itemtype="https://schema.org/Product">
                            <meta itemprop="name" content="Plan Profesional Tumenufast">
                            <meta itemprop="description" content="Plan profesional para restaurantes en crecimiento con múltiples sucursales y productos ilimitados">
                            <div class="pricing-badge">Más Popular</div>
                            <div class="pricing-header">
                                <h3 class="pricing-name">Premium</h3>
                                <p class="pricing-description">Ideal para restaurantes en crecimiento</p>
                                <div class="pricing-price monthly-price">
                                    <span itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                                        <span itemprop="price" content="11000">$11.000</span><small>/mes</small>
                                        <meta itemprop="priceCurrency" content="CLP">
                                        <meta itemprop="priceSpecification" itemscope itemtype="https://schema.org/UnitPriceSpecification">
                                        <meta itemprop="price" content="11000">
                                        <meta itemprop="priceCurrency" content="CLP">
                                        <meta itemprop="unitText" content="MONTH">
                                    </span>
                                </div>
                                <div class="pricing-price annual-price" style="display: none;">
                                    $72.000<small>pago único anual</small>
                                    <div class="mt-2">
                                        <small class="text-muted">¡Ahorra 40% con plan anual!</small>
                                    </div>
                                </div>
                            </div>
                            
                            <ul class="pricing-features">
                                <li><i class="fas fa-check"></i><span>Menú Digital QR</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Pedidos por WhatsApp</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Hasta 2 Sucursales</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Hasta 50 menus</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Personalización avanzada</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Estadísticas avanzadas</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Soporte prioritario</span></li>
                                
                            </ul>
                            
                            <div class="pricing-cta">
                                <a href="https://api.whatsapp.com/send/?phone=56932094742" class="btn-pricing primary monthly-btn" data-plan="premium" data-duration="monthly">
                                    Suscribirse
                                </a>
                                <a href="https://api.whatsapp.com/send/?phone=56932094742" class="btn-pricing primary annual-btn" data-plan="premium" data-duration="annual" style="display: none;">
                                    Suscribirse
                                </a>
                            </div>
                        </article>
                    </div>
                    
                    <!-- Plan Empresarial -->
                    <div class="col-lg-4 col-md-6">
                        <article class="pricing-card fade-in" itemscope itemtype="https://schema.org/Product">
                            <meta itemprop="name" content="Plan Empresarial Tumenufast">
                            <meta itemprop="description" content="Plan empresarial para cadenas y grandes restaurantes con sucursales ilimitadas y soporte 24/7">
                            <div class="pricing-header">
                                <h3 class="pricing-name">Premium Pro</h3>
                                <p class="pricing-description">Para cadenas y grandes restaurantes</p>
                                <div class="pricing-price monthly-price">
                                    <span itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                                        <span itemprop="price" content="20000">$20.000</span><small>/mes</small>
                                        <meta itemprop="priceCurrency" content="CLP">
                                        <meta itemprop="priceSpecification" itemscope itemtype="https://schema.org/UnitPriceSpecification">
                                        <meta itemprop="price" content="20000">
                                        <meta itemprop="priceCurrency" content="CLP">
                                        <meta itemprop="unitText" content="MONTH">
                                    </span>
                                </div>
                                <div class="pricing-price annual-price" style="display: none;">
                                    $115.200<small>pago único anual</small>
                                    <div class="mt-2">
                                        <small class="text-muted">¡Ahorra 40% con plan anual!</small>
                                    </div>
                                </div>
                            </div>
                            
                            <ul class="pricing-features">
                                <li><i class="fas fa-check"></i><span>Menú Digital QR</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Pedidos por WhatsApp</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Hasta 4 sucursales</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Hasta 120 menus</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Personalización avanzada</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Estadísticas avanzadas</span></li>
                                <li><i class="fas fa-check" aria-hidden="true"></i><span>Soporte prioritario</span></li>
                                
                            </ul>
                            
                            <div class="pricing-cta">
                                <a href="https://api.whatsapp.com/send/?phone=56932094742" class="btn-pricing outline monthly-btn" data-plan="premium-pro" data-duration="monthly">
                                    Suscribirse
                                </a>
                                <a href="https://api.whatsapp.com/send/?phone=56932094742" class="btn-pricing outline annual-btn" data-plan="premium-pro" data-duration="annual" style="display: none;">
                                    Suscribirse
                                </a>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
            
            
                    
                    <button class="carousel-control-prev" type="button" data-bs-target="#pricingCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#pricingCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                    
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#pricingCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                        <button type="button" data-bs-target="#pricingCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                        <button type="button" data-bs-target="#pricingCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Carousel -->
            <div class="pricing-carousel">
                <div id="pricingCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <!-- Plan Básico -->
                        <div class="carousel-item active">
                            <div class="pricing-card">
                                <div class="pricing-header">
                                    <h3 class="pricing-name">Básico</h3>
                                    <p class="pricing-description">Perfecto para restaurantes pequeños</p>
                                    <div class="pricing-price monthly-price">
                                        $7.990<small>/mes</small>
                                    </div>
                                    <div class="pricing-price annual-price" style="display: none;">
                                        $57.528<small>pago único anual</small>
                                        <div class="mt-2">
                                            <small class="text-muted">¡Ahorra 40% con plan anual!</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <ul class="pricing-features">
                                    <li><i class="fas fa-check"></i><span>Menú Digital QR</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Pedidos por WhatsApp</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>1 Sucursal</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Hasta 20 menus</span></li>
                                    <li><i class="fas fa-times" aria-hidden="true"></i><span>Personalización básica</span></li>
                                    <li><i class="fas fa-times" aria-hidden="true"></i><span>Sin Estadísticas </span></li>
                                </ul>
                                
                                <div class="pricing-cta">
                                    <a href="https://wa.link/740d30" class="btn-pricing outline monthly-btn" data-plan="basico" data-duration="monthly">
                                        Suscribirse
                                    </a>
                                    <a href="https://wa.link/a33otx" class="btn-pricing outline annual-btn" data-plan="basico" data-duration="annual" style="display: none;">
                                        Suscribirse
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Plan Premium -->
                        <div class="carousel-item">
                            <div class="pricing-card featured">
                                <div class="pricing-badge">Más Popular</div>
                                <div class="pricing-header">
                                    <h3 class="pricing-name">Premium</h3>
                                    <p class="pricing-description">Ideal para restaurantes en crecimiento</p>
                                    <div class="pricing-price monthly-price">
                                        $11.000<small>/mes</small>
                                    </div>
                                    <div class="pricing-price annual-price" style="display: none;">
                                        $72.000<small>pago único anual</small>
                                        <div class="mt-2">
                                            <small class="text-muted">¡Ahorra 40% con plan anual!</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <ul class="pricing-features">
                                    <li><i class="fas fa-check"></i><span>Menú Digital QR</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Pedidos por WhatsApp</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Hasta 2 Sucursales</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Hasta 50 menus</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Personalización avanzada</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Estadísticas avanzadas</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Soporte prioritario</span></li>
                                </ul>
                                
                                <div class="pricing-cta">
                                    <a href="https://api.whatsapp.com/send/?phone=56932094742" class="btn-pricing primary monthly-btn" data-plan="premium" data-duration="monthly">
                                        Suscribirse
                                    </a>
                                    <a href="https://api.whatsapp.com/send/?phone=56932094742" class="btn-pricing primary annual-btn" data-plan="premium" data-duration="annual" style="display: none;">
                                        Suscribirse
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Plan Premium Pro -->
                        <div class="carousel-item">
                            <div class="pricing-card">
                                <div class="pricing-header">
                                    <h3 class="pricing-name">Premium Pro</h3>
                                    <p class="pricing-description">Para cadenas y grandes restaurantes</p>
                                    <div class="pricing-price monthly-price">
                                        $20.000<small>/mes</small>
                                    </div>
                                    <div class="pricing-price annual-price" style="display: none;">
                                        $115.200<small>pago único anual</small>
                                        <div class="mt-2">
                                            <small class="text-muted">¡Ahorra 40% con plan anual!</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <ul class="pricing-features">
                                    <li><i class="fas fa-check"></i><span>Menú Digital QR</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Pedidos por WhatsApp</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Hasta 4 sucursales</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Hasta 120 menus</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Personalización avanzada</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Estadísticas avanzadas</span></li>
                                    <li><i class="fas fa-check" aria-hidden="true"></i><span>Soporte prioritario</span></li>
                                </ul>
                                
                                <div class="pricing-cta">
                                    <a href="https://api.whatsapp.com/send/?phone=56932094742" class="btn-pricing outline monthly-btn" data-plan="premium-pro" data-duration="monthly">
                                        Suscribirse
                                    </a>
                                    <a href="https://api.whatsapp.com/send/?phone=56932094742" class="btn-pricing outline annual-btn" data-plan="premium-pro" data-duration="annual" style="display: none;">
                                        Suscribirse
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button class="carousel-control-prev" type="button" data-bs-target="#pricingCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#pricingCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                    
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#pricingCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                        <button type="button" data-bs-target="#pricingCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                        <button type="button" data-bs-target="#pricingCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                    </div>
                </div>
            </div>
            
            <!-- Sección de confianza -->
            <div class="row mt-5">
                <div class="col-12 text-center">
                    <div class="pricing-info fade-in">
                        <h5 class="mb-3"><i class="fas fa-question-circle"></i> ¿Dudas antes de probar?</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="fas fa-shield-alt text-success me-2"></i>
                                    <span>Sin contrato fijo</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="fas fa-times-circle text-success me-2"></i>
                                    <span>Cancela cuando quieras</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="fas fa-headset text-success me-2"></i>
                                    <span>Soporte incluido en todos los planes</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Store Preview Section -->
    <section class="store-preview" role="region" aria-labelledby="store-preview-title">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="content-wrapper">
                        <h2 id="store-preview-title" class="display-5 fw-bold mb-4 fade-in">Así se ve tu tienda digital</h2>
                        <p class="lead text-muted mb-4 fade-in">Tus clientes podrán ver tu menú en cualquier dispositivo, agregar al carrito y enviarte el pedido por WhatsApp sin necesidad de apps.</p>
                        
                        <div class="benefits-list">
                            <div class="benefit-item fade-in">
                                <i class="fas fa-mobile-alt text-success"></i>
                                <div>
                                    <h5>100% Responsive</h5>
                                    <p>Se ve perfecto en móviles, tablets y computadoras</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item fade-in">
                                <i class="fas fa-shopping-cart text-success"></i>
                                <div>
                                    <h5>Carrito inteligente</h5>
                                    <p>Los clientes pueden agregar productos y ver el total</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item fade-in">
                                <i class="fab fa-whatsapp text-success"></i>
                                <div>
                                    <h5>Pedido directo por WhatsApp</h5>
                                    <p>Sin apps externas, todo llega a tu WhatsApp configurado</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="image-wrapper text-center fade-in">
                        <img src="/uploads/mockup-img/videoweb.gif" 
                             alt="Vista previa de la tienda digital en desktop" 
                             class="img-fluid hero-mockup-image"
                             loading="lazy"
                             onerror="this.onerror=null; this.src='/assets/img/no-image.png';">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="testimonials" role="region" aria-labelledby="testimonials-title">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 id="testimonials-title" class="display-4 fw-bold mb-3 fade-in">⭐ ¿Funciona? Ellos ya digitalizaron su restaurante con éxito</h2>
                    <p class="lead opacity-75 fade-in">Más de 1.000 restaurantes usan TuMenuFast para recibir pedidos por WhatsApp.</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <article class="testimonial-card fade-in" itemscope itemtype="https://schema.org/Review">
                        <meta itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                        <meta itemprop="ratingValue" content="5">
                        <meta itemprop="bestRating" content="5">
                        <div class="testimonial-avatar">
                            <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?w=80&h=80&fit=crop&crop=face" alt="María González" class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                        </div>
                        <blockquote itemprop="reviewBody">
                            <p>"Increíble plataforma. En solo 2 horas teníamos nuestro menú digital funcionando. Los clientes lo aman y los pedidos por WhatsApp llegan directo."</p>
                        </blockquote>
                        <footer>
                            <h5 itemprop="author" itemscope itemtype="https://schema.org/Person">
                                <span itemprop="name">María González</span>
                            </h5>
                            <small itemprop="reviewBody">Propietaria de Sabores Mexicanos</small>
                        </footer>
                    </article>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <article class="testimonial-card fade-in" itemscope itemtype="https://schema.org/Review">
                        <meta itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                        <meta itemprop="ratingValue" content="5">
                        <meta itemprop="bestRating" content="5">
                        <div class="testimonial-avatar">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&h=80&fit=crop&crop=face" alt="Carlos Rodríguez" class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                        </div>
                        <blockquote itemprop="reviewBody">
                            <p>"Perfecto para nuestras 3 sucursales. Podemos gestionar todo desde un solo lugar y los pedidos llegan organizados por WhatsApp. Muy recomendado."</p>
                        </blockquote>
                        <footer>
                            <h5 itemprop="author" itemscope itemtype="https://schema.org/Person">
                                <span itemprop="name">Carlos Rodríguez</span>
                            </h5>
                            <small>Gerente de Pizzería Italiana</small>
                        </footer>
                    </article>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <article class="testimonial-card fade-in" itemscope itemtype="https://schema.org/Review">
                        <meta itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                        <meta itemprop="ratingValue" content="5">
                        <meta itemprop="bestRating" content="5">
                        <div class="testimonial-avatar">
                            <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=80&h=80&fit=crop&crop=face" alt="Ana Martínez" class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                        </div>
                        <blockquote itemprop="reviewBody">
                            <p>"El soporte es excepcional. Siempre están disponibles para ayudar. La plataforma es muy intuitiva y los clientes pueden hacer pedidos fácilmente."</p>
                        </blockquote>
                        <footer>
                            <h5 itemprop="author" itemscope itemtype="https://schema.org/Person">
                                <span itemprop="name">Ana Martínez</span>
                            </h5>
                            <small>Chef Ejecutiva de Gourmet Bistro</small>
                        </footer>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <!-- Video Section -->
    <section class="video-section" role="region" aria-labelledby="video-title">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h2 id="video-title" class="display-4 fw-bold mb-3 fade-in">🎥 Conoce TuMenuFast en acción</h2>
                    <p class="lead text-muted mb-5 fade-in">Mira cómo funciona nuestro sistema con este breve video. Sin complicaciones, 100% enfocado en ayudarte a vender más.</p>
                    
                    <div class="video-container fade-in">
                        <div class="video-wrapper">
                            <!-- Placeholder para video - reemplazar con video real -->
                            <div class="video-placeholder">
                                <div class="video-thumbnail">
                                    <div class="play-button">
                                        <i class="fas fa-play"></i>
                                    </div>
                                    <div class="video-info">
                                        <h4>TuMenuFast - Demo Completo</h4>
                                        <p>45 segundos • Cómo funciona el sistema</p>
                                    </div>
                                </div>
                                <div class="video-description">
                                    <p><strong>En este video verás:</strong></p>
                                    <ul>
                                        <li>✅ Cómo se ve el menú digital</li>
                                        <li>✅ Cómo se hace un pedido</li>
                                        <li>✅ Cómo llega el pedido a WhatsApp</li>
                                        <li>✅ Cómo se ve el panel de control</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Video real (descomentar cuando tengas el video) -->
                            <!--
                            <div class="embed-responsive embed-responsive-16by9">
                                <iframe class="embed-responsive-item" src="https://www.youtube.com/embed/TU_VIDEO_ID" 
                                        title="TuMenuFast Demo" frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen></iframe>
                            </div>
                            -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" role="region" aria-labelledby="cta-title">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <?php if ($isLoggedIn): ?>
                        <h2 id="cta-title" class="fade-in">¡Tu menú digital está listo!</h2>
                        <p class="lead mb-4 fade-in">Gestiona tus productos, recibe pedidos y analiza tus ventas desde tu panel</p>
                    <?php else: ?>
                        <h2 id="cta-title" class="fade-in">¿Listo para recibir pedidos por WhatsApp en minutos?</h2>
                        <p class="lead mb-4 fade-in">Únete a miles de restaurantes que ya están creciendo con nosotros</p>
                    <?php endif; ?>
                    
                    <!-- Mensaje de urgencia -->
                    <div class="urgency-message mb-4 fade-in">
                        <?php if ($isLoggedIn): ?>
                            <p class="h5 text-warning">
                                🎯 ¡Tu menú está listo! Gestiona tus productos y recibe pedidos ahora mismo
                            </p>
                        <?php else: ?>
                            <p class="h5 text-warning">
                                🎯 ¡Activa hoy tu menú web y comienza a vender en línea desde mañana!
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="btn-group-vertical btn-group-lg gap-3 fade-in">
                        <?php if ($isLoggedIn): ?>
                            <div class="btn-hero-container">
                                <a href="/restaurante/dashboard.php" class="btn-hero" aria-label="Ir al dashboard">
                                    <i class="fas fa-tachometer-alt"></i> Ir al Dashboard
                                </a>
                            </div>
                            <div class="mt-3">
                                <a href="/restaurante/menu.php" class="btn btn-outline-light btn-lg" aria-label="Gestionar menú">
                                    <i class="fas fa-edit"></i> Gestionar Menú
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="btn-hero-container">
                                <a href="/restaurante/registro.php" class="btn-hero" aria-label="Activar TuMenú gratis por 7 días">
                                    Activar TuMenú Gratis (7 días)
                                </a>
                            </div>
                            <div class="mt-3">
                                <a href="#pricing" class="btn btn-outline-light btn-lg" aria-label="Ver planes de precios">
                                    Ver Planes
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4 fade-in">
                        <small class="opacity-75">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i> ¿Necesitas ayuda? Escribenos: <a href="https://wa.me/56932094742" aria-label="Escribenos al número">+569 32094742</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section" role="region" aria-labelledby="faq-title">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 id="faq-title" class="display-4 fw-bold mb-3 fade-in">Preguntas Frecuentes</h2>
                    <p class="lead text-muted fade-in">Resolvemos las dudas más comunes sobre TuMenuFast</p>
                </div>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="faq-container">
                        <div class="faq-item fade-in">
                            <div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="false" aria-controls="faq1">
                                <h5>¿Qué necesito para comenzar?</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="collapse" id="faq1">
                                <div class="faq-answer">
                                    <p>Nada más que tu nombre, correo y nombre del restaurante. ¡En 5 minutos tienes todo listo!</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="faq-item fade-in">
                            <div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                                <h5>¿Puedo cancelar cuando quiera?</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="collapse" id="faq2">
                                <div class="faq-answer">
                                    <p>Sí. No hay contratos ni compromiso. Tú decides cuándo cancelar tu suscripción.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="faq-item fade-in">
                            <div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                                <h5>¿Puedo recibir pedidos por WhatsApp sin apps externas?</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="collapse" id="faq3">
                                <div class="faq-answer">
                                    <p>Sí. Todo el pedido llega directamente al WhatsApp que tú configures. No necesitas ninguna app adicional.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="faq-item fade-in">
                            <div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                                <h5>¿La prueba gratis incluye todas las funciones?</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="collapse" id="faq4">
                                <div class="faq-answer">
                                    <p>Sí. Podrás probar todo sin restricciones por 7 días. Incluye todas las funciones del plan que elijas.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="faq-item fade-in">
                            <div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false" aria-controls="faq5">
                                <h5>¿Necesito conocimientos técnicos?</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="collapse" id="faq5">
                                <div class="faq-answer">
                                    <p>No. TuMenuFast está diseñado para ser súper fácil de usar. Cualquier persona puede configurar su menú en minutos.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="faq-item fade-in">
                            <div class="faq-question" data-bs-toggle="collapse" data-bs-target="#faq6" aria-expanded="false" aria-controls="faq6">
                                <h5>¿Puedo personalizar los colores y logo?</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="collapse" id="faq6">
                                <div class="faq-answer">
                                    <p>¡Por supuesto! Puedes personalizar colores, logo, banner y toda la apariencia para que refleje tu marca.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" role="contentinfo" itemscope itemtype="https://schema.org/Organization">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5><i class="fas fa-utensils" aria-hidden="true"></i> <span itemprop="name">Tumenufast</span></h5>
                    <p itemprop="description">TuMenuFast es la plataforma simple y rápida para digitalizar tu restaurante. Crea tu menú online, recibe pedidos por WhatsApp y aumenta tus ventas sin comisiones.</p>
                    
                    <!-- Enlace de soporte WhatsApp -->
                    <div class="whatsapp-support mt-3">
                        <a href="https://wa.me/56932094742" target="_blank" class="btn btn-success btn-sm" aria-label="Contactar soporte por WhatsApp">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i> Soporte en línea
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5>Producto</h5>
                    <ul class="list-unstyled">
                        <li><a href="#features">Características</a></li>
                        <li><a href="#pricing">Precios</a></li>
                        <li><a href="#testimonials">Testimonios</a></li>
                        <li><a href="/restaurante/registro.php">Comenzar Gratis</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5>Síguenos en nuestras redes sociales</h5>
                    <div class="social-links">
                    
                        <a href="https://instagram.com/tumenufast" aria-label="Seguir en Instagram" itemprop="sameAs">
                            <i class="fab fa-instagram" aria-hidden="true"></i>
                        </a>
                        
                        </a>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <span itemprop="name">Tumenufast</span>. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">
                        <i class="fas fa-envelope" aria-hidden="true"></i> 
                        <a href="mailto:tumenufast@gmail.com" itemprop="email">tumenufast@gmail.com</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" defer></script>
    
    <script>
        // Optimización de rendimiento y SEO
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scrolling for navigation links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                        
                        // Actualizar URL para SEO
                        const url = new URL(window.location);
                        url.hash = this.getAttribute('href');
                        window.history.pushState({}, '', url);
                    }
                });
            });

            // Navbar background on scroll with throttling
            let ticking = false;
            function updateNavbar() {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) {
                    navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                } else {
                    navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                }
                ticking = false;
            }

            window.addEventListener('scroll', function() {
                if (!ticking) {
                    requestAnimationFrame(updateNavbar);
                    ticking = true;
                }
            });

            // Intersection Observer para animaciones con mejor rendimiento
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        // Una vez que se muestra, dejar de observar
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            // Observe all fade-in elements
            document.querySelectorAll('.fade-in').forEach(el => {
                observer.observe(el);
            });

            // Add interactive effects with performance optimization
            const cards = document.querySelectorAll('.feature-card, .pricing-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('featured')) {
                        this.style.transform = 'translateY(0)';
                    } else {
                        this.style.transform = 'scale(1.05)';
                    }
                });
            });

            // Pricing toggle functionality with accessibility improvements
            const toggleButton = document.getElementById('pricingToggle');
            const monthlyPrices = document.querySelectorAll('.monthly-price');
            const annualPrices = document.querySelectorAll('.annual-price');
            const annualSavings = document.getElementById('annualSavings');
            const monthlyButtons = document.querySelectorAll('.monthly-btn');
            const annualButtons = document.querySelectorAll('.annual-btn');
            
            // Mensajes personalizados de WhatsApp para cada plan
            const whatsappMessages = {
                'basico': {
                    'monthly': 'Hola! Me interesa el plan Básico mensual de TuMenuFast por $7.990/mes. ¿Podrían ayudarme a activarlo?',
                    'annual': 'Hola! Me interesa el plan Básico anual de TuMenuFast por $57.528 (ahorro del 40%). ¿Podrían ayudarme a activarlo?'
                },
                'premium': {
                    'monthly': 'Hola! Me interesa el plan Premium mensual de TuMenuFast por $11.000/mes. ¿Podrían ayudarme a activarlo?',
                    'annual': 'Hola! Me interesa el plan Premium anual de TuMenuFast por $72.000 (ahorro del 40%). ¿Podrían ayudarme a activarlo?'
                },
                'premium-pro': {
                    'monthly': 'Hola! Me interesa el plan Premium Pro mensual de TuMenuFast por $20.000/mes. ¿Podrían ayudarme a activarlo?',
                    'annual': 'Hola! Me interesa el plan Premium Pro anual de TuMenuFast por $115.200 (ahorro del 40%). ¿Podrían ayudarme a activarlo?'
                }
            };
            
            if (toggleButton) {
                toggleButton.addEventListener('change', function() {
                    const isAnnual = this.checked;
                    
                    // Toggle price visibility with smooth transition
                    monthlyPrices.forEach(price => {
                        price.style.display = isAnnual ? 'none' : 'block';
                        price.style.opacity = isAnnual ? '0' : '1';
                    });
                    
                    annualPrices.forEach(price => {
                        price.style.display = isAnnual ? 'block' : 'none';
                        price.style.opacity = isAnnual ? '1' : '0';
                        if (isAnnual) {
                            price.classList.add('annual');
                        } else {
                            price.classList.remove('annual');
                        }
                    });
                    
                    // Toggle savings badge
                    if (annualSavings) {
                        annualSavings.style.display = isAnnual ? 'block' : 'none';
                    }
                    
                    // Toggle button visibility
                    toggleSubscriptionButtons(isAnnual);
                    
                    // Announce change to screen readers
                    const announcement = document.createElement('div');
                    announcement.setAttribute('aria-live', 'polite');
                    announcement.setAttribute('aria-atomic', 'true');
                    announcement.style.position = 'absolute';
                    announcement.style.left = '-10000px';
                    announcement.style.width = '1px';
                    announcement.style.height = '1px';
                    announcement.style.overflow = 'hidden';
                    announcement.textContent = isAnnual ? 'Mostrando precios anuales' : 'Mostrando precios mensuales';
                    document.body.appendChild(announcement);
                    setTimeout(() => document.body.removeChild(announcement), 1000);
                });
            }
            
            function toggleSubscriptionButtons(isAnnual) {
                if (isAnnual) {
                    // Mostrar botones anuales, ocultar mensuales
                    monthlyButtons.forEach(btn => {
                        btn.style.display = 'none';
                    });
                    annualButtons.forEach(btn => {
                        btn.style.display = 'inline-block';
                    });
                } else {
                    // Mostrar botones mensuales, ocultar anuales
                    monthlyButtons.forEach(btn => {
                        btn.style.display = 'inline-block';
                    });
                    annualButtons.forEach(btn => {
                        btn.style.display = 'none';
                    });
                }
            }
            
            // Agregar event listeners a todos los botones de suscripción
            function setupSubscriptionButtons() {
                const allButtons = document.querySelectorAll('.monthly-btn, .annual-btn');
                
                allButtons.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        const plan = this.getAttribute('data-plan');
                        const duration = this.getAttribute('data-duration');
                        const currentHref = this.getAttribute('href');
                        
                        // Si el botón ya tiene un enlace directo de WhatsApp (wa.link), usarlo directamente
                        if (currentHref && currentHref.includes('wa.link')) {
                            window.open(currentHref, '_blank');
                            return;
                        }
                        
                        // Si tiene enlace de API de WhatsApp sin mensaje, agregar el mensaje personalizado
                        if (currentHref && currentHref.includes('api.whatsapp.com/send/?phone=')) {
                            const message = whatsappMessages[plan][duration];
                            const encodedMessage = encodeURIComponent(message);
                            const whatsappUrl = `${currentHref}&text=${encodedMessage}`;
                            window.open(whatsappUrl, '_blank');
                            return;
                        }
                        
                        // Si no tiene enlace directo, usar el mensaje personalizado con el número por defecto
                        const message = whatsappMessages[plan][duration];
                        const encodedMessage = encodeURIComponent(message);
                        const whatsappUrl = `https://wa.me/56932094742?text=${encodedMessage}`;
                        
                        // Abrir WhatsApp
                        window.open(whatsappUrl, '_blank');
                    });
                });
            }
            
            // Inicializar botones
            setupSubscriptionButtons();
            toggleSubscriptionButtons(false); // Comenzar con botones mensuales visibles
            
            // Lazy loading para imágenes (si se agregan en el futuro)
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
            
            // Preload critical resources
            const criticalResources = [
                '/restaurante/registro.php',
                '/restaurante/login.php'
            ];
            
            criticalResources.forEach(url => {
                const link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = url;
                document.head.appendChild(link);
            });
        });
        
        // Service Worker registration for PWA capabilities (opcional)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('SW registered: ', registration);
                    })
                    .catch(function(registrationError) {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }
    </script>
</body>
</html>
