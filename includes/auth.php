<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o usuário está autenticado
 */
function verificarAutenticacao() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: /login');
        exit;
    }
}

/**
 * Verifica se o usuário logado é Administrador
 */
function verificarAdmin() {
    verificarAutenticacao();

    if (($_SESSION['usuario_tipo'] ?? '') !== 'admin') {
        header('Location: /portal');
        exit;
    }
}

/**
 * Verifica se o usuário logado é Cliente
 */
function verificarCliente() {
    verificarAutenticacao();

    if (($_SESSION['usuario_tipo'] ?? '') !== 'cliente') {
        header('Location: /dashboard');
        exit;
    }
}