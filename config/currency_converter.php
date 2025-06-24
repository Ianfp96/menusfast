<?php
/**
 * Sistema de conversión de monedas para planes
 * Las tasas de cambio están basadas en valores aproximados (actualizar según sea necesario)
 */

class CurrencyConverter {
    // Tasas de cambio desde CLP (Peso Chileno) - valores aproximados
    private static $exchangeRates = [
        'CLP' => 1.0,      // Moneda base
        'USD' => 0.0011,   // 1 CLP = 0.0011 USD (aproximadamente 900 CLP = 1 USD)
        'EUR' => 0.0010,   // 1 CLP = 0.0010 EUR (aproximadamente 1000 CLP = 1 EUR)
        'GBP' => 0.00086,  // 1 CLP = 0.00086 GBP (aproximadamente 1160 CLP = 1 GBP)
        'ARS' => 0.95,     // 1 CLP = 0.95 ARS (aproximadamente 1.05 CLP = 1 ARS)
        'PEN' => 0.0041,   // 1 CLP = 0.0041 PEN (aproximadamente 240 CLP = 1 PEN)
        'COP' => 2.8,      // 1 CLP = 2.8 COP (aproximadamente 0.36 CLP = 1 COP)
        'MXN' => 0.020,    // 1 CLP = 0.020 MXN (aproximadamente 50 CLP = 1 MXN)
        'BRL' => 0.0055,   // 1 CLP = 0.0055 BRL (aproximadamente 180 CLP = 1 BRL)
        'VES' => 0.0000001 // 1 CLP = 0.0000001 VES (aproximadamente 10,000,000 CLP = 1 VES)
    ];

    /**
     * Convertir precio desde CLP a la moneda especificada
     */
    public static function convertFromCLP($priceCLP, $targetCurrency) {
        if (!isset(self::$exchangeRates[$targetCurrency])) {
            return $priceCLP; // Retornar precio original si no se encuentra la moneda
        }
        
        $rate = self::$exchangeRates[$targetCurrency];
        return $priceCLP * $rate;
    }

    /**
     * Convertir precio desde cualquier moneda a CLP
     */
    public static function convertToCLP($price, $sourceCurrency) {
        if (!isset(self::$exchangeRates[$sourceCurrency])) {
            return $price; // Retornar precio original si no se encuentra la moneda
        }
        
        $rate = self::$exchangeRates[$sourceCurrency];
        return $price / $rate;
    }

    /**
     * Convertir entre dos monedas
     */
    public static function convert($price, $fromCurrency, $toCurrency) {
        if ($fromCurrency === $toCurrency) {
            return $price;
        }
        
        // Primero convertir a CLP, luego a la moneda objetivo
        $priceInCLP = self::convertToCLP($price, $fromCurrency);
        return self::convertFromCLP($priceInCLP, $toCurrency);
    }

    /**
     * Obtener todas las tasas de cambio disponibles
     */
    public static function getExchangeRates() {
        return self::$exchangeRates;
    }

    /**
     * Obtener la tasa de cambio para una moneda específica
     */
    public static function getExchangeRate($currency) {
        return self::$exchangeRates[$currency] ?? null;
    }

    /**
     * Verificar si una moneda es válida
     */
    public static function isValidCurrency($currency) {
        return isset(self::$exchangeRates[$currency]);
    }

    /**
     * Obtener el precio formateado en la moneda especificada
     */
    public static function formatPriceInCurrency($priceCLP, $currency, $conn = null) {
        $convertedPrice = self::convertFromCLP($priceCLP, $currency);
        
        // Fallback con configuración por defecto (más simple y confiable)
        $symbols = [
            'CLP' => '$',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'ARS' => '$',
            'PEN' => 'S/',
            'COP' => '$',
            'MXN' => '$',
            'BRL' => 'R$',
            'VES' => 'Bs.'
        ];
        
        $symbol = $symbols[$currency] ?? '$';
        $decimals = in_array($currency, ['USD', 'EUR', 'GBP', 'PEN', 'MXN', 'BRL']) ? 2 : 0;
        
        $formattedNumber = number_format($convertedPrice, $decimals, '.', ',');
        return $symbol . $formattedNumber;
    }
} 
