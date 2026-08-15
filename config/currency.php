<?php
// config/currency.php

// إعدادات العملة الليبية
define('CURRENCY_SYMBOL', 'د.ل');
define('CURRENCY_NAME', 'دينار ليبي');
define('CURRENCY_CODE', 'LYD');
define('DECIMAL_PLACES', 2);
define('DECIMAL_SEPARATOR', '.');
define('THOUSAND_SEPARATOR', ',');

// أسعار الصرف (اختياري)
define('EXCHANGE_RATE_USD', 4.80); // 1 دولار = 4.80 دينار ليبي (مثال)
define('EXCHANGE_RATE_EUR', 5.20); // 1 يورو = 5.20 دينار ليبي (مثال)

function formatCurrency($amount) {
    return number_format($amount, DECIMAL_PLACES, DECIMAL_SEPARATOR, THOUSAND_SEPARATOR) . ' ' . CURRENCY_SYMBOL;
}

function getCurrencySymbol() {
    return CURRENCY_SYMBOL;
}

function getCurrencyName() {
    return CURRENCY_NAME;
}

function convertToLYD($amount, $fromCurrency = 'USD') {
    if ($fromCurrency == 'USD') {
        return $amount * EXCHANGE_RATE_USD;
    } elseif ($fromCurrency == 'EUR') {
        return $amount * EXCHANGE_RATE_EUR;
    }
    return $amount;
}
?>