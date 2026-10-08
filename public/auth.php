<?php
// auth.php
// Funções auxiliares de autenticação e sessão com controle de perfis

require_once __DIR__ . '/config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }

    if (isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === 'auxiliar') {
        session_unset();
        session_destroy();
        header("Location: login.php?error=" . urlencode("O perfil de Auxiliar não possui acesso ao sistema de Controle de Efetivo."));
        exit;
    }
}

function requireChefia() {
    requireLogin();
    if (!in_array($_SESSION['user_perfil'], ['chefia', 'admin'])) {
        header("Location: index.php?error=" . urlencode("Acesso restrito à Chefia ou Administração."));
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_perfil'] !== 'admin') {
        if ($_SESSION['user_perfil'] === 'chefia') {
            header("Location: dashboard.php?error=" . urlencode("Acesso restrito à Administração."));
        } else {
            header("Location: index.php?error=" . urlencode("Acesso restrito à Administração."));
        }
        exit;
    }
}

function requireChamada() {
    requireLogin();
    if (!in_array($_SESSION['user_perfil'], ['encarregado', 'admin'])) {
        if ($_SESSION['user_perfil'] === 'chefia') {
            header("Location: dashboard.php");
        } else {
            header("Location: login.php?error=" . urlencode("Acesso não autorizado."));
        }
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

