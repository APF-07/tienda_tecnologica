<?php
// =====================================================
// FUNCIONES GENERALES DEL SISTEMA
// =====================================================

/**
 * Formatea un número decimal como precio en dólares
 */
function formatearPrecio($precio) {
    return '$ ' . number_format($precio, 2);
}

/**
 * Limpia entradas de texto para prevenir XSS básico
 */
function limpiarDato($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}
?>