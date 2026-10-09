<?php
require_once 'config.php';
require_once 'auth.php';

// Iniciar sessao se ainda nao estiver ativa para checar se o usuario esta logado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = null;
if (isset($_SESSION['user_id'])) {
    $user = [
        'id' => $_SESSION['user_id'],
        'usuario' => $_SESSION['user_usuario'] ?? '',
        'nome' => $_SESSION['user_nome'] ?? '',
        'perfil' => $_SESSION['user_perfil'] ?? 'encarregado'
    ];
    if (!in_array($user['perfil'], ['chefia', 'admin'])) {
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual do Usuário - Controle de Efetivo DTCEA-SJ</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .manual-wrapper {
            max-width: 1400px;
            margin: 25px auto 60px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 25px;
            align-items: flex-start;
        }

        /* Sidebar Navegação Rápida (Submenus) */
        .manual-sidebar {
            position: sticky;
            top: 85px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            padding: 20px;
            max-height: calc(100vh - 110px);
            overflow-y: auto;
        }

        .sidebar-header {
            padding-bottom: 12px;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .sidebar-header h3 {
            font-size: 1.05rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            font-weight: 700;
        }

        .sidebar-search {
            margin-bottom: 15px;
        }

        .sidebar-search input {
            width: 100%;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-size: 0.85rem;
            background: var(--bg);
            outline: none;
            transition: border-color 0.2s;
        }

        .sidebar-search input:focus {
            border-color: var(--primary-light);
            background: #fff;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 4px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 6px;
            color: var(--text);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: var(--primary);
            color: #ffffff;
        }

        .sidebar-menu a svg {
            flex-shrink: 0;
        }

        .sidebar-menu .sub-item {
            padding-left: 32px;
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .sidebar-menu .sub-item:hover {
            color: #ffffff;
        }

        /* Conteúdo Principal do Manual */
        .manual-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .manual-hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .manual-hero::after {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 140px;
            height: 140px;
            background: rgba(254, 193, 17, 0.12);
            border-radius: 50%;
            pointer-events: none;
        }

        .manual-hero h2 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .manual-hero p {
            font-size: 0.95rem;
            color: #E2E8F0;
            max-width: 800px;
            line-height: 1.5;
        }

        /* Seção Card */
        .manual-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            padding: 28px;
            scroll-margin-top: 90px;
            position: relative;
        }

        .manual-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }

        .manual-card-header .title-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .manual-card-header .title-group h3 {
            font-size: 1.3rem;
            color: var(--primary);
            margin: 0;
            font-weight: 700;
        }

        .badge-profile {
            font-size: 0.72rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-enc { background-color: rgba(0, 47, 108, 0.12); color: var(--primary); }
        .badge-chefe { background-color: rgba(49, 130, 206, 0.15); color: var(--info); }
        .badge-adm { background-color: rgba(229, 62, 62, 0.15); color: var(--danger); }
        .badge-all { background-color: rgba(56, 161, 105, 0.15); color: var(--success); }

        /* Callouts / Caixas de Destaque */
        .callout {
            border-radius: 8px;
            padding: 14px 18px;
            margin: 16px 0;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .callout-info {
            background: #EBF8FF;
            border-left: 4px solid var(--info);
            color: #2B6CB0;
        }

        .callout-warning {
            background: #FFFAF0;
            border-left: 4px solid var(--warning);
            color: #C05621;
        }

        .callout-success {
            background: #F0FFF4;
            border-left: 4px solid var(--success);
            color: #276749;
        }

        .callout-danger {
            background: #FFF5F5;
            border-left: 4px solid var(--danger);
            color: #C53030;
        }

        /* Passos Numerados */
        .steps-container {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin: 16px 0;
        }

        .step-item {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .step-number {
            background: var(--primary);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .step-body {
            flex: 1;
            font-size: 0.92rem;
        }

        .step-body strong {
            color: var(--primary);
        }

        /* Tabela Estilizada */
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0;
            font-size: 0.88rem;
        }

        .status-table th, .status-table td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .status-table th {
            background: #EDF2F7;
            color: var(--primary);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .status-table tr:hover td {
            background: rgba(0, 0, 0, 0.015);
        }

        /* FAQ Acordeão */
        .faq-item {
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 10px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .faq-question {
            background: #FAFBFD;
            padding: 14px 18px;
            font-weight: 600;
            font-size: 0.92rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
            color: var(--text);
        }

        .faq-question:hover {
            background: #F1F5F9;
            color: var(--primary);
        }

        .faq-question svg {
            transition: transform 0.2s ease;
        }

        .faq-item.open .faq-question svg {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 18px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            background: white;
            font-size: 0.88rem;
            line-height: 1.6;
            color: var(--text);
        }

        .faq-item.open .faq-answer {
            padding: 14px 18px;
            max-height: 350px;
            border-top: 1px solid var(--border);
        }

        /* Grid de recursos */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin: 18px 0;
        }

        .feature-box {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            background: #FAFBFD;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .feature-box:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            border-color: var(--primary-light);
        }

        .feature-box h4 {
            font-size: 0.98rem;
            color: var(--primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .feature-box p {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin: 0;
        }

        /* Botão Flutuante Voltar ao Topo */
        #btn-back-to-top {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background: var(--primary);
            color: white;
            border: none;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 47, 108, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
        }

        #btn-back-to-top.show {
            opacity: 1;
            visibility: visible;
        }

        #btn-back-to-top:hover {
            background: var(--accent);
            color: var(--primary-dark);
            transform: scale(1.08);
        }

        @media (max-width: 992px) {
            .manual-wrapper {
                grid-template-columns: 1fr;
            }
            .manual-sidebar {
                position: static;
                max-height: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <header class="navbar">
        <div class="nav-brand">
            <img src="dtcea_sj_logo.png" alt="Logo DTCEA-SJ" class="nav-logo">
            <div class="nav-title">
                <h1>DTCEA-SJ</h1>
                <p>Força Aérea Brasileira</p>
            </div>
        </div>
        <nav class="nav-menu">
            <?php if (isset($user) && $user && in_array($user['perfil'], ['chefia', 'admin'])): ?>
                <a href="visaogeral.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    Visão Geral
                </a>
            <?php endif; ?>
            <?php if (isset($user) && $user && $user['perfil'] === 'admin'): ?>
                <a href="index.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Lançar Chamada
                </a>
            <?php endif; ?>
            <?php if (isset($user) && $user && in_array($user['perfil'], ['chefia', 'admin'])): ?>
                <a href="dashboard.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2a2 2 0 00-2 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Painel da Chefia
                </a>
            <?php endif; ?>
            <a href="tarefas.php" class="nav-link">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M9 16l2 2 4-4"></path></svg>
                Prazos e Entregas
            </a>
            <?php if (isset($user) && $user && $user['perfil'] === 'admin'): ?>
                <a href="admin.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    Administração
                </a>
            <?php endif; ?>
            <?php if (!isset($user) || !$user || in_array($user['perfil'], ['chefia', 'admin'])): ?>
                <a href="help.php" class="nav-link active">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Ajuda
                </a>
            <?php endif; ?>
            
            <?php if (isset($user) && $user): ?>
            <div class="nav-user">
                <span>Olá, <strong><?= htmlspecialchars($user['nome']) ?></strong></span>
                <span class="badge-profile"><?= htmlspecialchars($user['perfil']) ?></span>
                <a href="alterarsenha.php" style="color: var(--accent); margin-left: 10px; text-decoration: underline; font-size: 0.8rem;">Alterar Senha</a>
            </div>
            <a href="logout.php" class="btn-logout">Sair</a>
            <?php else: ?>
            <a href="login.php" class="btn-logout" style="background-color: var(--accent); color: var(--primary-dark); font-weight: 700;">Acessar o Sistema</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="manual-wrapper">
        <!-- Submenu / Sidebar -->
        <aside class="manual-sidebar">
            <div class="sidebar-header">
                <h3>
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                    Índice do Manual
                </h3>
            </div>

            <div class="sidebar-search">
                <input type="text" id="manual-search-input" placeholder="Buscar no manual..." onkeyup="filterManual(this.value)">
            </div>

            <ul class="sidebar-menu" id="sidebar-menu-list">
                <li><a href="#introducao"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> 1. Introdução e Acesso</a></li>
                <li><a href="#perfis"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg> 2. Perfis de Usuário</a></li>
                <li><a href="#visao-geral"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg> 3. Menu "Visão Geral"</a></li>
                <li><a href="#desativar-reativar" class="sub-item">↳ Desativação e Reativação</a></li>
                <li><a href="#lancar-chamada"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg> 4. Menu "Lançar Chamada"</a></li>
                <li><a href="#afastamentos" class="sub-item">↳ Afastamento por Período</a></li>
                <li><a href="#painel-chefia"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2a2 2 0 00-2 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg> 5. Painel da Chefia</a></li>
                <li><a href="#administracao"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg> 6. Administração</a></li>
                <li><a href="#legendas"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> 7. Tabela de Legendas</a></li>
                <li><a href="#faq"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> 8. Dúvidas Frequentes (FAQ)</a></li>
            </ul>
        </aside>

        <!-- Conteúdo Principal -->
        <main class="manual-content">
            <!-- Hero -->
            <div class="manual-hero">
                <h2>Manual do Usuário e Guia Operacional</h2>
                <p>Instruções completas para gestão diária de efetivo militar, lançamentos de chamada, controle de afastamentos, gestão de usuários e extração de relatórios estatísticos do DTCEA-SJ.</p>
            </div>

            <!-- 1. Introdução -->
            <section id="introducao" class="manual-card">
                <div class="manual-card-header">
                    <div class="title-group">
                        <svg width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <h3>1. Introdução e Acesso ao Sistema</h3>
                    </div>
                    <span class="badge-profile badge-all">Geral</span>
                </div>
                <p>O <strong>Sistema de Controle de Efetivo do DTCEA-SJ</strong> foi desenvolvido para modernizar e descentralizar o controle diário de presença, afastamentos programados e dados cadastrais de todo o efetivo do destacamento, garantindo rastreabilidade e relatórios estatísticos em tempo real.</p>
                
                <div class="feature-grid">
                    <div class="feature-box">
                        <h4>
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                            Autenticação de Acesso
                        </h4>
                        <p>Cada operador possui login individual com senha criptografada. O acesso é restrito aos militares e servidores autorizados da organização.</p>
                    </div>
                    <div class="feature-box">
                        <h4>
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                            Alteração de Senha
                        </h4>
                        <p>No canto superior direito, clique em <em>"Alterar Senha"</em> para atualizar sua credencial sempre que necessário ou no primeiro acesso.</p>
                    </div>
                </div>

                <div class="callout callout-info">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div><strong>Dica de Segurança:</strong> Nunca compartilhe seu usuário ou senha com terceiros. As chamadas e cadastros ficam registrados com a identificação do operador responsável.</div>
                </div>
            </section>

            <!-- 2. Perfis de Usuário -->
            <section id="perfis" class="manual-card">
                <div class="manual-card-header">
                    <div class="title-group">
                        <svg width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <h3>2. Perfis de Usuário e Níveis de Permissão</h3>
                    </div>
                </div>
                <p>O sistema possui 4 níveis de perfis com regras de acesso e permissões claramente definidas:</p>

                <div class="feature-grid">
                    <div class="feature-box" style="border-left: 4px solid var(--primary);">
                        <h4><span class="badge-profile badge-enc">Encarregado</span></h4>
                        <p>Perfil operacional para os encarregados de seção. Não altera dados cadastrais e enxerga exclusivamente o menu <strong>"Lançar Chamada"</strong> apenas com os militares de sua própria seção.</p>
                    </div>
                    <div class="feature-box" style="border-left: 4px solid var(--info);">
                        <h4><span class="badge-profile badge-chefe">Chefia</span></h4>
                        <p>Acesso estratégico de consulta. Enxerga o menu <strong>"Visão Geral"</strong> sem poder editar dados e o menu de <strong>"Painel da Chefia"</strong> com indicadores e relatórios consolidados.</p>
                    </div>
                    <div class="feature-box" style="border-left: 4px solid var(--danger);">
                        <h4><span class="badge-profile badge-adm">Administrador</span></h4>
                        <p>Acesso total de leitura e escrita a todos os dados e módulos (Visão Geral, Lançar Chamada, Painel da Chefia, Administração e Ajuda).</p>
                    </div>
                    <div class="feature-box" style="border-left: 4px solid var(--border);">
                        <h4><span class="badge-profile" style="background: #64748B; color: #fff;">Auxiliar</span></h4>
                        <p>Não possui acesso ao serviço de Controle de Efetivo. Ao tentar autenticar, o sistema exibe uma mensagem educada orientando sobre a restrição de acesso.</p>
                    </div>
                </div>
            </section>

            <!-- 3. Visão Geral -->
            <section id="visao-geral" class="manual-card">
                <div class="manual-card-header">
                    <div class="title-group">
                        <svg width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        <h3>3. Menu "Visão Geral" (Relação Geral do Efetivo)</h3>
                    </div>
                    <span class="badge-profile badge-all">Principal</span>
                </div>
                <p>O menu <strong>Visão Geral</strong> concentra o cadastro e a consulta completa do efetivo militar do DTCEA-SJ, com visualização moderna, indicadores de topo e filtros em tempo real.</p>
                
                <h4 style="color: var(--primary); margin-top: 18px; margin-bottom: 8px;">Principais Recursos da Tela:</h4>
                <ul class="help-list">
                    <li><strong>Cards de Indicadores no Topo:</strong> Exibem o Total de Efetivo Ativo, Militares BCT, e a distribuição instantânea por seções da unidade.</li>
                    <li><strong>Filtros Avançados:</strong> Permite filtrar por Seção, Posto/Graduação, Especialidade BCT e Situação (Ativos vs Desativados).</li>
                    <li><strong>Pesquisa Dinâmica Instantânea:</strong> Digite o Nome de Guerra, Nome Completo ou SARAM para localizar qualquer militar em fração de segundo.</li>
                    <li><strong>Máscara de CPF Automática:</strong> Formatação padronizada e segura no formato <code>000.000.000-00</code>.</li>
                    <li><strong>Inclusão e Edição de Militares:</strong> Modal com formulário estruturado e barra de rolagem dedicada, permitindo preencher todos os dados sem cortes de tela.</li>
                </ul>

                <div id="desativar-reativar" style="margin-top: 25px; scroll-margin-top: 100px;">
                    <h4 style="color: var(--danger); display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        Regra de Desativação (Soft Delete) e Reativação
                    </h4>
                    <p>Para garantir que o histórico das chamadas passadas nunca seja perdido ou corrompido, o sistema utiliza a política de <strong>Desativação</strong> em vez de exclusão física:</p>

                    <div class="steps-container">
                        <div class="step-item">
                            <div class="step-number">1</div>
                            <div class="step-body"><strong>Desativar Militar (Transferência/Desligamento):</strong> Ao clicar no botão vermelho <em>"Desativar"</em>, o militar é movido para o grupo de inativos. Ele deixa de aparecer na lista de chamada diária, mas todo o histórico de presenças anteriores permanece intacto.</div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">2</div>
                            <div class="step-body"><strong>Consultar Militares Desativados:</strong> No topo da tela, mude o filtro de <em>"Situação"</em> para <strong>"Desativados"</strong>. Você poderá visualizar o histórico e dados de todos os militares que já passaram pelo DTCEA-SJ.</div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <div class="step-body"><strong>Reativação Automática / Prevenção de Duplicidade:</strong> Se um militar retornar ao efetivo e você tentar cadastrá-lo novamente com o mesmo SARAM ou CPF, o sistema identificará o registro existente e apresentará um aviso com o botão <strong>"Reativar Cadastro"</strong>, recuperando o cadastro instantaneamente com 1 clique.</div>
                        </div>
                    </div>

                    <div class="callout callout-success">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <div><strong>Vantagem Operacional:</strong> Essa abordagem impede duplicidade de registros e preserva relatórios históricos de anos anteriores sem gerar inconsistências estatísticas.</div>
                    </div>
                </div>
            </section>

            <!-- 4. Lançar Chamada -->
            <section id="lancar-chamada" class="manual-card">
                <div class="manual-card-header">
                    <div class="title-group">
                        <svg width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        <h3>4. Menu "Lançar Chamada" (Rotina Diária)</h3>
                    </div>
                    <span class="badge-profile badge-enc">Encarregado</span>
                </div>
                <p>Nesta área, os encarregados realizam o registro da presença ou indisponibilidade do efetivo no dia selecionado.</p>

                <div class="steps-container">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-body"><strong>Seleção de Data e Seção:</strong> O sistema abre por padrão na data atual e filtra a seção do encarregado logado. Se necessário, selecione outra data para conferência.</div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-body"><strong>Marcação Rápida (1 Clique):</strong> Clique no botão colorido correspondente à situação do militar (ex: <strong>P</strong> para Presente, <strong>S</strong> para Serviço, <strong>D</strong> para Dispensado). O sistema salva a informação automaticamente no banco de dados sem necessidade de recarregar a página.</div>
                    </div>
                </div>

                <div id="afastamentos" style="margin-top: 25px; scroll-margin-top: 100px;">
                    <h4 style="color: var(--primary); display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Lançamento de Afastamento por Período Programado
                    </h4>
                    <p>Para militares que entrarão em períodos contínuos de afastamento (Férias, Cursos, Missões, Licenças Médicas LTS, Licenças Paternidade/Maternidade LPM, etc.):</p>

                    <div class="callout callout-warning">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <div>
                            <strong>Automação de Chamada:</strong> Ao cadastrar um período (ex: Férias de 01/08 a 30/08), o sistema preencherá <strong>automaticamente</strong> a chamada de todos os dias daquele intervalo para o militar. O encarregado não precisa marcar dia a dia!
                        </div>
                    </div>
                </div>
            </section>

            <!-- 5. Painel da Chefia -->
            <section id="painel-chefia" class="manual-card">
                <div class="manual-card-header">
                    <div class="title-group">
                        <svg width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2a2 2 0 00-2 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        <h3>5. Painel da Chefia (Visão Estratégica)</h3>
                    </div>
                    <span class="badge-profile badge-chefe">Chefia / Admin</span>
                </div>
                <p>O <strong>Painel da Chefia</strong> consolida os dados operacionais em indicadores executivos:</p>
                <ul class="help-list">
                    <li><strong>Índice de Disponibilidade Geral:</strong> Percentual em tempo real de militares aptos e presentes no DTCEA-SJ no dia.</li>
                    <li><strong>Distribuição e Comparativo por Seção:</strong> Gráficos que comparam o total previsto versus o efetivo real presente em cada setor (SELM, SSTI, SERPRO, etc.).</li>
                    <li><strong>Quadro de Afastamentos Ativos:</strong> Tabela consolidada de todos os militares que estão ausentes na data por férias, missão, LTS ou licenças, indicando o período de término previsto.</li>
                    <li><strong>Consulta Histórica:</strong> Permite voltar a qualquer data anterior para inspecionar auditorias de chamada e relatórios de disponibilidade.</li>
                </ul>
            </section>

            <!-- 6. Administração -->
            <section id="administracao" class="manual-card">
                <div class="manual-card-header">
                    <div class="title-group">
                        <svg width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                        <h3>6. Módulo de Administração (Exclusivo Administrador)</h3>
                    </div>
                    <span class="badge-profile badge-adm">Administrador</span>
                </div>
                <p>A aba <strong>Administração</strong> permite a gestão da infraestrutura de dados do sistema:</p>

                <div class="feature-grid">
                    <div class="feature-box">
                        <h4>
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            Gestão de Seções
                        </h4>
                        <p>Crie novas seções ou renomeie as existentes. A renomeação atualiza automaticamente os militares associados. A exclusão de seções com militares é protegida para evitar órfãos.</p>
                    </div>
                    <div class="feature-box">
                        <h4>
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            Contas e Acessos
                        </h4>
                        <p>Cadastre novos operadores, vincule a uma seção específica ou dê acesso geral, e execute redefinição de senhas com segurança.</p>
                    </div>
                </div>
            </section>

            <!-- 7. Legendas -->
            <section id="legendas" class="manual-card">
                <div class="manual-card-header">
                    <div class="title-group">
                        <svg width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <h3>7. Tabela Oficial de Legendas  Siglas de Chamada</h3>
                    </div>
                    <span class="badge-profile badge-all">Referência</span>
                </div>
                <p>Abaixo constam as siglas oficiais padronizadas para a chamada no DTCEA-SJ e o impacto de cada uma no cálculo de disponibilidade:</p>

                <table class="status-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Sigla</th>
                            <th>Descrição da Situação</th>
                            <th>Categoria Operacional</th>
                            <th>Disponibilidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="status-badge status-p">P</span></td>
                            <td><strong>Presente</strong> no expediente regular</td>
                            <td>Trabalho Normal</td>
                            <td><strong style="color: var(--success);">100% (Disponível)</strong></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge status-s">S</span></td>
                            <td><strong>Serviço</strong> de escala de 24h ou plantão</td>
                            <td>Escala Operacional</td>
                            <td><strong style="color: var(--success);">100% (Disponível)</strong></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge status-f">F</span></td>
                            <td><strong>Férias</strong> regulamentares</td>
                            <td>Afastamento Programado</td>
                            <td><strong style="color: var(--danger);">0% (Indisponível)</strong></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge status-c">C</span></td>
                            <td><strong>Curso</strong> de capacitação / instrução externa</td>
                            <td>Capacitação</td>
                            <td><strong style="color: var(--danger);">0% (Indisponível)</strong></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge status-m">M</span></td>
                            <td><strong>Missão</strong> de serviço fora da sede</td>
                            <td>Operacional Externa</td>
                            <td><strong style="color: var(--danger);">0% (Indisponível)</strong></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge status-l">L</span></td>
                            <td><strong>Licença</strong> especial / gala / luto</td>
                            <td>Afastamento Regulamentar</td>
                            <td><strong style="color: var(--danger);">0% (Indisponível)</strong></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge status-lts">LTS</span></td>
                            <td><strong>Licença Tratamento Saúde</strong> (Atestado médico)</td>
                            <td>Afastamento de Saúde</td>
                            <td><strong style="color: var(--danger);">0% (Indisponível)</strong></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge status-lpm">LPM</span></td>
                            <td><strong>Licença Paternidade / Maternidade</strong></td>
                            <td>Afastamento Parental</td>
                            <td><strong style="color: var(--danger);">0% (Indisponível)</strong></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge status-d">D</span></td>
                            <td><strong>Dispensado</strong> do expediente</td>
                            <td>Dispensa Administrativa</td>
                            <td><strong style="color: var(--danger);">0% (Indisponível)</strong></td>
                        </tr>
                        <tr>
                            <td><span class="status-badge status-dp">DP</span></td>
                            <td><strong>Dispensa como Recompensa</strong></td>
                            <td>Recompensa Regulamentar</td>
                            <td><strong style="color: var(--danger);">0% (Indisponível)</strong></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- 8. FAQ -->
            <section id="faq" class="manual-card">
                <div class="manual-card-header">
                    <div class="title-group">
                        <svg width="24" height="24" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <h3>8. Perguntas Frequentes (FAQ)</h3>
                    </div>
                    <span class="badge-profile badge-all">Ajuda Rápida</span>
                </div>

                <div class="faq-item open">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Como lançar férias parceladas para o mesmo militar?</span>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                    <div class="faq-answer">
                        Basta realizar um lançamento individual para cada período. Por exemplo: lance o 1º período de 05/03 a 15/03 com a situação "F - Férias" e depois faça outro lançamento para o 2º período de 10/09 a 20/09. O sistema registrará ambos os intervalos perfeitamente.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>O que acontece quando clico em "Desativar" em um militar?</span>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                    <div class="faq-answer">
                        O militar é inativado (soft delete). Ele para de aparecer nas listas ativas e na chamada diária, mas todo o histórico dos dias em que ele serviu no DTCEA-SJ é preservado no banco de dados. Caso ele retorne futuramente, seu cadastro pode ser reativado com 1 clique.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Tentei cadastrar um militar e apareceu o aviso "Militar já cadastrado (Desativado)". O que fazer?</span>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                    <div class="faq-answer">
                        Isso significa que o SARAM ou CPF informado já pertence a um militar que foi desativado anteriormente no sistema. O modal exibirá um botão <strong>"Reativar Cadastro"</strong>. Basta clicar nele para reativá-lo imediatamente sem precisar redigitar tudo.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Esqueci minha senha de acesso, como recuperar?</span>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                    <div class="faq-answer">
                        Entre em contato com o Administrador do Sistema do DTCEA-SJ. Na aba <em>Administração -> Usuários e Acessos</em>, o administrador poderá redefinir sua senha para uma senha temporária ou definitiva.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Como exportar ou imprimir os dados do efetivo?</span>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                    <div class="faq-answer">
                        Você pode utilizar a função de impressão nativa do navegador (pressionando <code>Ctrl + P</code>) tanto na tela de Visão Geral quanto no Painel da Chefia. As páginas possuem folhas de estilo otimizadas para impressão em papel ou exportação em PDF.
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Botão Voltar ao Topo -->
    <button id="btn-back-to-top" title="Voltar ao Topo" onclick="scrollToTop()">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
    </button>

    <script>
        // Acordeão de FAQ
        function toggleFaq(el) {
            const item = el.closest('.faq-item');
            item.classList.toggle('open');
        }

        // Filtro em tempo real no manual
        function filterManual(query) {
            query = query.toLowerCase().trim();
            const cards = document.querySelectorAll('.manual-card');
            
            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                if (query === '' || text.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Botão voltar ao topo & Highlighting do menu ativo
        const btnTop = document.getElementById('btn-back-to-top');
        const sections = document.querySelectorAll('.manual-card');
        const menuLinks = document.querySelectorAll('.sidebar-menu a');

        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                btnTop.classList.add('show');
            } else {
                btnTop.classList.remove('show');
            }

            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 120;
                if (window.scrollY >= sectionTop) {
                    current = section.getAttribute('id');
                }
            });

            menuLinks.forEach(link => {
                link.classList.remove('active');
                if (current && link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
</body>
</html>
