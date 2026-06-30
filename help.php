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
        'usuario' => $_SESSION['user_usuario'],
        'nome' => $_SESSION['user_nome'],
        'perfil' => $_SESSION['user_perfil']
    ];
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
        .help-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .help-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 20px var(--shadow);
            padding: 30px;
            margin-bottom: 25px;
            border-left: 5px solid var(--primary);
        }
        .help-card.chefia { border-left-color: var(--info); }
        .help-card.admin { border-left-color: var(--danger); }
        .help-card.legendas { border-left-color: var(--warning); }
        
        .help-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }
        .help-title h3 {
            font-size: 1.3rem;
            margin: 0;
        }
        .profile-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
            color: white;
        }
        .badge-enc { background-color: var(--primary); }
        .badge-chefe { background-color: var(--info); }
        .badge-adm { background-color: var(--danger); }

        .help-list {
            margin-left: 20px;
            margin-top: 10px;
        }
        .help-list li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .status-table th, .status-table td {
            padding: 10px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .status-table th {
            background-color: rgba(0, 0, 0, 0.02);
            font-weight: 600;
        }
        .step-box {
            background-color: rgba(0, 47, 108, 0.03);
            border: 1px dashed var(--primary);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .step-title {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 8px;
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
            <?php if ($user): ?>
                <a href="index.php" class="nav-link">Lançar Chamada</a>
                <?php if ($user['perfil'] === 'chefia' || $user['perfil'] === 'admin'): ?>
                    <a href="dashboard.php" class="nav-link">Painel da Chefia</a>
                <?php endif; ?>
                <?php if ($user['perfil'] === 'admin'): ?>
                    <a href="admin.php" class="nav-link">Administração</a>
                <?php endif; ?>
                <a href="help.php" class="nav-link active">Ajuda</a>
                
                <div class="nav-user">
                    <span>Olá, <strong><?= $user['nome'] ?></strong></span>
                    <span class="badge-profile"><?= $user['perfil'] ?></span>
                </div>
                <a href="logout.php" class="btn-logout">Sair</a>
            <?php else: ?>
                <a href="login.php" class="btn-logout" style="background-color: var(--primary); color: white; display: inline-flex; align-items: center; justify-content: center;">Acessar o Sistema</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="help-container">
        <!-- Cabeçalho -->
        <div class="page-header" style="margin-bottom: 30px;">
            <div class="page-title">
                <h2>Manual do Usuário</h2>
                <p>Guia de referência rápida e instruções de uso do Sistema de Controle de Efetivo (DTCEA-SJ).</p>
            </div>
        </div>

        <!-- Card: Visão Geral -->
        <div class="help-card legendas">
            <div class="help-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <h3>Visão Geral do Sistema</h3>
            </div>
            <p style="line-height: 1.6;">
                O Sistema de Controle de Efetivo do DTCEA-SJ foi projetado para descentralizar o registro de presenças e ausências diárias do efetivo militar, fornecendo à chefia indicadores de disponibilidade em tempo real e permitindo aos administradores gerenciarem a estrutura de seções, usuários e militares.
            </p>
        </div>

        <!-- Card: Encarregados -->
        <div class="help-card">
            <div class="help-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                <h3>Funcionalidades do Perfil <span class="profile-badge badge-enc">Encarregado</span></h3>
            </div>
            <p>O Encarregado é o responsável por gerenciar a presença diária e os afastamentos do efetivo da sua seção.</p>
            <ul class="help-list">
                <li><strong>Filtro Automático de Seção:</strong> Ao fazer o login, o encarregado visualiza apenas os militares pertencentes à sua respectiva seção (ex: SELM, SSTI, etc.), facilitando a localização. Se o encarregado possuir acesso geral (seção vinculada em branco), ele poderá ver e registrar presenças de todas as seções.</li>
                <li><strong>Chamada Diária:</strong> Clicando nos botões coloridos de status de cada militar, a alteração é gravada instantaneamente e salva de forma automática no banco.</li>
                <li><strong>Lançamento por Período (Afastamento Programado):</strong> Se um militar for entrar de férias, curso, licença ou missão, use o formulário de <em>"Lançar Período de Indisponibilidade"</em>. Informe o militar, a situação e o intervalo de datas (início e fim).
                    <div class="step-box">
                        <div class="step-title">Como funciona a indisponibilidade automática:</div>
                        Se você lançar que o militar estará de <strong>Férias</strong> de 06/07 a 15/07, ao abrir a chamada de qualquer um desses dias, o sistema marcará o militar automaticamente como <strong>"F - Férias"</strong>, não sendo necessário o preenchimento manual dia a dia.
                    </div>
                </li>
            </ul>
        </div>

        <!-- Card: Chefia -->
        <div class="help-card chefia">
            <div class="help-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                <h3>Funcionalidades do Perfil <span class="profile-badge badge-chefe">Chefia</span></h3>
            </div>
            <p>O perfil Chefia possui acesso estratégico focado em relatórios e visibilidade consolidada de todo o DTCEA-SJ.</p>
            <ul class="help-list">
                <li><strong>Acesso ao Dashboard:</strong> Exibe a taxa geral de disponibilidade do dia, gráficos de barra de presença por seção e número absoluto de militares presentes e indisponíveis.</li>
                <li><strong>Afastamentos Ativos:</strong> Um painel central detalhado mostra todos os militares que estão atualmente indisponíveis por motivos especiais (Férias, Licenças, Dispensa, etc.) com seus respectivos períodos e seções correspondentes.</li>
                <li><strong>Listagem Geral:</strong> Permite consultar o histórico e ver a chamada de qualquer dia do ano, facilitando auditorias e acompanhamentos.</li>
            </ul>
        </div>

        <!-- Card: Administrador -->
        <div class="help-card admin">
            <div class="help-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                <h3>Funcionalidades do Perfil <span class="profile-badge badge-adm">Administrador</span></h3>
            </div>
            <p>O Administrador possui controle total sobre os dados cadastrais estruturais do sistema na aba <em>Administração</em>.</p>
            <ul class="help-list">
                <li><strong>Gerenciar Seções:</strong> Cadastro centralizado de seções. Renomear uma seção atualiza automaticamente todos os militares e usuários nela alocados. A exclusão de uma seção é bloqueada caso existam militares vinculados a ela para garantir a integridade dos relatórios.</li>
                <li><strong>Efetivo Militar (CRUD):</strong> Criação, edição e exclusão de militares. O campo seção obrigatoriamente exige a seleção de uma das seções criadas no sistema. Também define se o militar concorre à Escala Operacional (TWR, AIS, EMS) ou de Expediente Administrativo.</li>
                <li><strong>Usuários & Acessos (Gestão de Contas):</strong> Permite criar novas contas de acesso e redefinir senhas esquecidas de Encarregados ou Chefias. Ao associar uma seção para um usuário "Encarregado", a visão dele será restrita a essa seção. Se deixado em branco, ele terá acesso total.</li>
            </ul>
        </div>

        <!-- Card: Legendas de Status -->
        <div class="help-card legendas" style="margin-bottom: 50px;">
            <div class="help-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <h3>Legendas e Siglas de Chamada</h3>
            </div>
            <p>Abaixo estão detalhados todos os status possíveis utilizados no lançamento da chamada:</p>
            <table class="status-table">
                <thead>
                    <tr>
                        <th style="width: 100px;">Sigla</th>
                        <th>Descrição da Situação</th>
                        <th>Impacto no Dashboard</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="status-badge status-p">P</span></td>
                        <td><strong>Presente</strong> (Trabalho normal)</td>
                        <td>Disponível (100%)</td>
                    </tr>
                    <tr>
                        <td><span class="status-badge status-s">S</span></td>
                        <td><strong>Serviço</strong> (Escala de 24h ou plantão)</td>
                        <td>Disponível (100%)</td>
                    </tr>
                    <tr>
                        <td><span class="status-badge status-f">F</span></td>
                        <td><strong>Férias</strong> Regulamentares</td>
                        <td>Indisponível (0%)</td>
                    </tr>
                    <tr>
                        <td><span class="status-badge status-c">C</span></td>
                        <td><strong>Curso</strong> (Instruções fora do DTCEA)</td>
                        <td>Indisponível (0%)</td>
                    </tr>
                    <tr>
                        <td><span class="status-badge status-m">M</span></td>
                        <td><strong>Missão</strong> de Serviço</td>
                        <td>Indisponível (0%)</td>
                    </tr>
                    <tr>
                        <td><span class="status-badge status-l">L</span></td>
                        <td><strong>Licença</strong> (Especial, casamento, etc.)</td>
                        <td>Indisponível (0%)</td>
                    </tr>
                    <tr>
                        <td><span class="status-badge status-lts">LTS</span></td>
                        <td><strong>Licença Tratamento Saúde</strong> (Afastamento médico)</td>
                        <td>Indisponível (0%)</td>
                    </tr>
                    <tr>
                        <td><span class="status-badge status-lpm">LPM</span></td>
                        <td><strong>Licença Paternidade / Maternidade</strong></td>
                        <td>Indisponível (0%)</td>
                    </tr>
                    <tr>
                        <td><span class="status-badge status-d">D</span></td>
                        <td><strong>Dispensado</strong> do expediente</td>
                        <td>Indisponível (0%)</td>
                    </tr>
                    <tr>
                        <td><span class="status-badge status-dp">DP</span></td>
                        <td><strong>Dispensa como Recompensa</strong></td>
                        <td>Indisponível (0%)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
