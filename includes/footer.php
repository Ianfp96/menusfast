    <!-- Footer mejorado -->
    <footer class="restaurant-footer">
        <div class="footer-content">
            <div class="footer-grid">
                <!-- Información de contacto -->
                <div class="footer-section">
                    <h3>Contacto</h3>
                    
                    <?php if ($restaurant['phone']): ?>
                    <div class="footer-info">
                        <i class="fas fa-phone"></i>
                        <span><?= htmlspecialchars($restaurant['phone']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($restaurant['address']): ?>
                    <div class="footer-info">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?= htmlspecialchars($restaurant['address']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($restaurant['whatsapp_url']): ?>
                    <div class="footer-info">
                        <i class="fab fa-whatsapp"></i>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $restaurant['whatsapp_url']) ?>" 
                           target="_blank" 
                           style="color: var(--gray-300); text-decoration: none;">
                            <?= htmlspecialchars($restaurant['whatsapp_url']) ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($restaurant['has_delivery'] || $restaurant['has_physical_store']): ?>
                    <h4>Servicios Disponibles</h4>
                    <div class="services-grid">
                        <?php if ($restaurant['has_delivery']): ?>
                        <div class="service-item">
                            <i class="fas fa-motorcycle"></i>
                            <div class="service-info">
                                <h5>Delivery</h5>
                                <p>Servicio a domicilio disponible</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($restaurant['has_physical_store']): ?>
                        <div class="service-item">
                            <i class="fas fa-store"></i>
                            <div class="service-info">
                                <h5>Tienda Física</h5>
                                <p>Visítanos en nuestro local</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($restaurant['facebook_url'] || $restaurant['instagram_url'] || $restaurant['tiktok_url']): ?>
                    <h4>Síguenos</h4>
                    <div class="social-links">
                        <?php if ($restaurant['facebook_url']): ?>
                        <a href="<?= htmlspecialchars($restaurant['facebook_url']) ?>" 
                           target="_blank" 
                           class="social-link">
                            <div class="social-icon">
                                <i class="fab fa-facebook-f"></i>
                            </div>
                            <span class="social-name">Facebook</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($restaurant['instagram_url']): ?>
                        <a href="<?= htmlspecialchars($restaurant['instagram_url']) ?>" 
                           target="_blank" 
                           class="social-link">
                            <div class="social-icon">
                                <i class="fab fa-instagram"></i>
                            </div>
                            <span class="social-name">Instagram</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($restaurant['tiktok_url']): ?>
                        <a href="<?= htmlspecialchars($restaurant['tiktok_url']) ?>" 
                           target="_blank" 
                           class="social-link">
                            <div class="social-icon">
                                <i class="fab fa-tiktok"></i>
                            </div>
                            <span class="social-name">TikTok</span>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>

    <style>
        .restaurant-footer {
            background: var(--dark);
            color: white;
            padding: 60px 0 40px;
            margin-top: 40px;
            position: relative;
        }

        .restaurant-footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
        }

        .footer-section {
            position: relative;
        }

        .footer-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 24px;
            color: var(--primary);
            position: relative;
            padding-bottom: 12px;
        }

        .footer-section h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--primary);
            border-radius: 2px;
        }

        .footer-section h4 {
            font-size: 1rem;
            color: var(--primary);
            margin-bottom: 16px;
            padding: 15px;
            font-weight: 600;
        }

        .footer-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            color: var(--gray-300);
            transition: var(--transition);
        }

        .footer-info:hover {
            color: white;
            transform: translateX(5px);
        }

        .footer-info i {
            color: var(--primary);
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .service-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 16px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
        }

        .service-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .service-item i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .service-info {
            flex: 1;
        }

        .service-info h5 {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 0;
            color: white;
        }

        .service-info p {
            font-size: 0.8rem;
            color: var(--gray-400);
            margin: 4px 0 0;
        }

        .social-links {
            display: flex;
            gap: 16px;
            margin-top: 8px;
        }

        .social-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray-300);
            transition: var(--transition);
        }

        .social-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            color: var(--gray-300);
            font-size: 1.25rem;
            transition: var(--transition);
        }

        .social-name {
            font-size: 0.7rem;
            margin-top: 4px;
            color: var(--gray-300);
            text-align: center;
            max-width: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .social-link:hover .social-icon {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }

        .social-link:hover .social-name {
            color: white;
        }

        @media (max-width: 768px) {
            .restaurant-footer {
                padding: 40px 0 30px;
            }

            .footer-content {
                padding: 0 16px;
            }

            .footer-grid {
                gap: 32px;
            }

            .footer-section h3 {
                font-size: 1.1rem;
                margin-bottom: 20px;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .social-links {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .restaurant-footer {
                padding: 30px 0 20px;
            }

            .footer-section h3 {
                font-size: 1rem;
            }

            .footer-info {
                font-size: 0.9rem;
            }

            .service-item {
                padding: 12px;
            }

            .service-item i {
                font-size: 1.25rem;
            }
        }
    </style>
</body>
</html> 
