<?php
// auth.php
// Funções auxiliares de autenticação e sessão

require_once __DIR__ . '/config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function requireChefia() {
    requireLogin();
    if ($_SESSION['user_perfil'] !== 'chefia' && $_SESSION['user_perfil'] !== 'admin') {
        header("Location: index.php?error=Acesso restrito à chefia ou administração.");
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_perfil'] !== 'admin') {
        header("Location: index.php?error=Acesso restrito à administração.");
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'saram' => $_SESSION['user_saram'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'nome' => $_SESSION['user_nome'] ?? '',
        'perfil' => $_SESSION['user_perfil'] ?? 'encarregado',
        'secao_id' => $_SESSION['user_secao_id'] ?? null,
        'secao' => $_SESSION['user_secao'] ?? null
    ];
}
