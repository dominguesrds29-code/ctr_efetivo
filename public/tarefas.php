<?php
// tarefas.php
// Sistema de Gestão de Tarefas com Prazo, Periodicidades e Entregas Críticas (DTCEA-SJ)

require_once __DIR__ . '/auth.php';
requireLogin();

$user = getCurrentUser();
$isAdmin = ($user && $user['perfil'] === 'admin');
$isChefiaOrAdmin = ($user && in_array($user['perfil'], ['chefia', 'admin']));

// Buscar seções e militares para os selects do formulário
try {
    $sec = getSecaoInfo($db);
    $secTable = $sec['table'];
    $secCol = $sec['name_col'];

    $secoes = $db->query("SELECT id, `$secCol` as nome FROM `$secTable` ORDER BY `$secCol` ASC")->fetchAll();
    
    $militares = $db->query("
        SELECT id, name, war_name, grade, section_id 
        FROM users 
        WHERE deleted_at IS NULL 
        ORDER BY name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $secoes = [];
    $militares = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prazos e Entregas - DTCEA-SJ</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-new-task {
            background: linear-gradient(135deg, var(--accent) 0%, #E5AE0F 100%);
            color: var(--primary-dark);
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(254, 193, 17, 0.35);
            transition: all 0.25s ease;
            font-size: 0.95rem;
        }

        .btn-new-task:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(254, 193, 17, 0.45);
        }

        /* Filter Controls */
        .task-filters {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow);
        }

        .search-box-task {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 14px;
            flex: 1;
            min-width: 260px;
        }

        .search-box-task input {
            border: none;
            background: transparent;
            outline: none;
            font-size: 0.9rem;
            width: 100%;
            font-family: inherit;
            color: var(--text);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-pill {
            padding: 7px 14px;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: #FFFFFF;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }

        .filter-pill:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-pill.active {
            background: var(--primary);
            color: #FFFFFF;
            border-color: var(--primary);
            box-shadow: 0 2px 6px rgba(0, 47, 108, 0.25);
        }

        /* Tasks Container */
        .tasks-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .task-card {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.04);
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 18px;
            align-items: flex-start;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .task-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 6px;
            background-color: var(--border);
            transition: background-color 0.2s;
        }

        .task-card.priority-critica::before { background-color: var(--danger); }
        .task-card.priority-alta::before { background-color: var(--warning); }
        .task-card.priority-media::before { background-color: var(--info); }
        .task-card.priority-baixa::before { background-color: var(--success); }

        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: #CBD5E1;
        }

        .task-card.completed {
            background-color: #F8FAFC;
            opacity: 0.75;
        }

        .task-card.completed .task-title {
            text-decoration: line-through;
            color: var(--text-muted);
        }

        .task-checkbox-wrapper {
            margin-top: 3px;
        }

        .task-checkbox {
            width: 22px;
            height: 22px;
            accent-color: var(--success);
            cursor: pointer;
            border-radius: 6px;
        }

        .task-main {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .task-header-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .task-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        .task-badges {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .badge-cat {
            background: #EDF2F7;
            color: #4A5568;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .badge-priority {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .badge-priority.critica { background: #FEE2E2; color: #B91C1C; }
        .badge-priority.alta { background: #FFEDD5; color: #C2410C; }
        .badge-priority.media { background: #E0F2FE; color: #0369A1; }
        .badge-priority.baixa { background: #DCFCE7; color: #15803D; }

        .badge-periodic {
            background: #F3E8FF;
            color: #7E22CE;
            border: 1px solid #E9D5FF;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .task-desc {
            font-size: 0.92rem;
            color: #4A5568;
            line-height: 1.5;
            white-space: pre-line;
        }

        .task-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 4px;
            flex-wrap: wrap;
        }

        .task-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .task-due-box {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            min-width: 150px;
        }

        .due-date {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .due-countdown {
            font-size: 0.8rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            display: inline-block;
        }

        .due-countdown.atrasada {
            background: #FEE2E2;
            color: #DC2626;
            animation: pulseWarning 2s infinite;
        }

        .due-countdown.hoje {
            background: #FEF3C7;
            color: #B45309;
            font-weight: 800;
        }

        .due-countdown.urgente {
            background: #FFEDD5;
            color: #EA580C;
        }

        .due-countdown.normal {
            background: #E2E8F0;
            color: #475569;
        }

        .due-countdown.concluida {
            background: #DCFCE7;
            color: #16A34A;
        }

        @keyframes pulseWarning {
            0% { opacity: 1; }
            50% { opacity: 0.65; }
            100% { opacity: 1; }
        }

        .task-actions {
            display: flex;
            gap: 8px;
            margin-top: 6px;
        }

        .btn-icon-task {
            background: #F1F5F9;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon-task:hover {
            background: var(--primary);
            color: #FFFFFF;
            border-color: var(--primary);
        }

        .btn-icon-task.delete:hover {
            background: var(--danger);
            color: #FFFFFF;
            border-color: var(--danger);
        }

        /* Preset Chips in Modal */
        .preset-chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .preset-chip {
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
            cursor: pointer;
            transition: all 0.2s;
        }

        .preset-chip:hover {
            background: var(--primary-light);
            color: #FFFFFF;
            border-color: var(--primary);
        }

        /* Modal specific styling */
        .modal-large {
            max-width: 680px;
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .form-row-2, .form-row-3 {
                grid-template-columns: 1fr;
            }
            .task-card {
                grid-template-columns: auto 1fr;
            }
            .task-due-box {
                grid-column: 2;
                align-items: flex-start;
                text-align: left;
                margin-top: 10px;
            }
        }

        .periodic-panel {
            background: #FAF5FF;
            border: 1px solid #E9D5FF;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            display: none;
            animation: fadeInModal 0.25s ease;
        }

        .periodic-panel.active {
            display: block;
        }

        /* Empty state */
        .empty-tasks {
            background: white;
            border-radius: 12px;
            padding: 50px 20px;
            text-align: center;
            border: 1px dashed var(--border);
            color: var(--text-muted);
        }

        .empty-tasks svg {
            stroke: #94A3B8;
            margin-bottom: 15px;
        }

        /* Toast notifications */
        .toast-container {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: #1E293B;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.3s ease-out;
        }

        .toast.success { border-left: 4px solid var(--success); }
        .toast.error { border-left: 4px solid var(--danger); }
        .toast.info { border-left: 4px solid var(--info); }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
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
            <?php if (in_array($user['perfil'], ['chefia', 'admin'])): ?>
                <a href="visaogeral.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    Visão Geral
                </a>
            <?php endif; ?>
            <?php if (in_array($user['perfil'], ['encarregado', 'admin'])): ?>
                <a href="index.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Lançar Chamada
                </a>
            <?php endif; ?>
            <?php if (in_array($user['perfil'], ['chefia', 'admin'])): ?>
                <a href="dashboard.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2a2 2 0 00-2 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Painel da Chefia
                </a>
            <?php endif; ?>
            <a href="tarefas.php" class="nav-link active">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M9 16l2 2 4-4"></path></svg>
                Prazos e Entregas
            </a>
            <?php if ($user['perfil'] === 'admin'): ?>
                <a href="admin.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    Administração
                </a>
            <?php endif; ?>
            <?php if (in_array($user['perfil'], ['chefia', 'admin'])): ?>
                <a href="help.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Ajuda
                </a>
            <?php endif; ?>
            
            <div class="nav-user">
                <span>Olá, <strong><?= $user['nome'] ?></strong></span>
                <span class="badge-profile"><?= $user['perfil'] ?></span>
                <a href="alterarsenha.php" style="color: var(--accent); margin-left: 10px; text-decoration: underline; font-size: 0.8rem;">Alterar Senha</a>
            </div>
            <a href="logout.php" class="btn-logout">Sair</a>
        </nav>
    </header>

    <div class="container container-wide">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h2>Prazos Críticos & Entregas</h2>
                <p>Controle centralizado de tarefas com prazo, lançamentos periódicos (mensal/semanal), lembretes e entregas do DTCEA-SJ.</p>
            </div>
            <div class="page-header-actions">
                <button type="button" class="btn-new-task" onclick="openNewTaskModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Lançar Tarefa com Prazo
                </button>
            </div>
        </div>

        <!-- Bento Grid KPI Summary -->
        <div class="dashboard-grid">
            <div class="stat-card primary">
                <div class="stat-label">Tarefas Ativas</div>
                <div class="stat-value" id="kpi-ativas">0</div>
                <div class="stat-footer" id="kpi-total-detail">Total de tarefas em acompanhamento</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">Prazos Críticos</div>
                <div class="stat-value" id="kpi-criticas">0</div>
                <div class="stat-footer">Vencendo nos próximos dias ou prioridade máxima</div>
            </div>
            <div class="stat-card danger" style="background: linear-gradient(135deg, #FFF5F5 0%, #FED7D7 100%); border-left: 4px solid var(--danger);">
                <div class="stat-label" style="color: #9B1C1C;">Tarefas Atrasadas</div>
                <div class="stat-value" id="kpi-atrasadas" style="color: var(--danger);">0</div>
                <div class="stat-footer" style="color: #C53030;">Atenção imediata requerida</div>
            </div>
            <div class="stat-card info">
                <div class="stat-label">Periódicas (Recorrentes)</div>
                <div class="stat-value" id="kpi-periodicas">0</div>
                <div class="stat-footer">Lembretes mensais e semanais automáticos</div>
            </div>
        </div>

        <!-- Filter Controls Bar -->
        <div class="task-filters">
            <div class="search-box-task">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="taskSearchInput" placeholder="Buscar por título (ex: Lançar férias), descrição, categoria ou responsável..." oninput="filterTasks()">
            </div>

            <div class="filter-group">
                <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary);">Status:</span>
                <div class="filter-pill active" data-filter="status" data-val="all" onclick="setFilter('status', 'all', this)">Todas</div>
                <div class="filter-pill" data-filter="status" data-val="pendentes" onclick="setFilter('status', 'pendentes', this)">Pendentes</div>
                <div class="filter-pill" data-filter="status" data-val="atrasadas" onclick="setFilter('status', 'atrasadas', this)">Atrasadas</div>
                <div class="filter-pill" data-filter="status" data-val="concluidas" onclick="setFilter('status', 'concluidas', this)">Concluídas</div>
            </div>

            <div class="filter-group">
                <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary);">Periodicidade:</span>
                <div class="filter-pill active" data-filter="periodicidade" data-val="all" onclick="setFilter('periodicidade', 'all', this)">Todas</div>
                <div class="filter-pill" data-filter="periodicidade" data-val="periodicas" onclick="setFilter('periodicidade', 'periodicas', this)">Periódicas</div>
                <div class="filter-pill" data-filter="periodicidade" data-val="mensal" onclick="setFilter('periodicidade', 'mensal', this)">Mensais</div>
                <div class="filter-pill" data-filter="periodicidade" data-val="semanal" onclick="setFilter('periodicidade', 'semanal', this)">Semanais</div>
            </div>

            <div class="filter-group">
                <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary);">Prioridade:</span>
                <select id="selectPriorityFilter" class="filter-select" onchange="filterTasks()" style="padding: 6px 12px; border-radius: 20px; font-weight: 600;">
                    <option value="all">Todas as Prioridades</option>
                    <option value="critica">Crítica (Urgente)</option>
                    <option value="alta">Alta</option>
                    <option value="media">Média</option>
                    <option value="baixa">Baixa</option>
                </select>
            </div>
        </div>

        <!-- Task List Area -->
        <div id="tasksList" class="tasks-container">
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                Carregando tarefas e prazos...
            </div>
        </div>
    </div>

    <!-- MODAL DE CADASTRO / EDIÇÃO DE TAREFA -->
    <div id="taskModal" class="modal-overlay">
        <div class="modal-dialog modal-large">
            <div class="modal-header">
                <h3 id="taskModalTitle" class="modal-title">Lançar Tarefa com Prazo</h3>
                <button type="button" class="modal-close" onclick="closeTaskModal()">&times;</button>
            </div>
            <form id="taskForm" onsubmit="submitTaskForm(event)">
                <input type="hidden" id="taskId" name="id" value="0">
                <div class="modal-body">
                    
                    <!-- Sugestões Rápidas (Presets) -->
                    <div style="margin-bottom: 8px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Sugestões Rápidas:</label>
                        <div class="preset-chips">
                            <span class="preset-chip" onclick="applyPreset('ferias')">📅 Lançar Férias (Mensal)</span>
                            <span class="preset-chip" onclick="applyPreset('escala')">📋 Conferência de Escala (Semanal)</span>
                            <span class="preset-chip" onclick="applyPreset('relatorio')">📊 Relatório Mensal de Efetivo</span>
                            <span class="preset-chip" onclick="applyPreset('saude')">🏥 Inspeção de Saúde</span>
                            <span class="preset-chip" onclick="applyPreset('licenca')">📝 Controle de Licenças</span>
                        </div>
                    </div>

                    <!-- Título da Tarefa -->
                    <div class="form-group">
                        <label for="inputTitulo" class="form-label">Título da Tarefa *</label>
                        <input type="text" id="inputTitulo" name="titulo" class="form-control" placeholder="Ex: Lançar férias do efetivo" required>
                    </div>

                    <!-- Descrição / Detalhes -->
                    <div class="form-group">
                        <label for="inputDescricao" class="form-label">Descrição da Tarefa & Instruções</label>
                        <textarea id="inputDescricao" name="descricao" class="form-control" rows="3" placeholder="Detalhes, passos a serem realizados, procedimentos ou links de apoio..."></textarea>
                    </div>

                    <!-- Datas e Horários -->
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="inputDataLimite" class="form-label">Data Limite (Prazo) *</label>
                            <input type="date" id="inputDataLimite" name="data_limite" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="inputHoraLimite" class="form-label">Horário Limite</label>
                            <input type="time" id="inputHoraLimite" name="hora_limite" class="form-control" value="23:59">
                        </div>
                        <div class="form-group">
                            <label for="inputCategoria" class="form-label">Categoria</label>
                            <select id="inputCategoria" name="categoria" class="form-control">
                                <option value="Férias">Férias</option>
                                <option value="Escala">Escala</option>
                                <option value="Pessoal / Efetivo">Pessoal / Efetivo</option>
                                <option value="Inspeção de Saúde">Inspeção de Saúde</option>
                                <option value="Administrativo">Administrativo</option>
                                <option value="Manutenção">Manutenção</option>
                                <option value="Comando">Comando</option>
                                <option value="Geral" selected>Geral</option>
                            </select>
                        </div>
                    </div>

                    <!-- Configuração de Tarefa Periódica -->
                    <div style="background: #F8FAFC; border: 1px solid var(--border); border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <strong style="color: var(--primary); font-size: 0.95rem;">Tarefa Periódica / Recorrente?</strong>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Ative caso esta tarefa se repita periodicamente (ex: todo mês ou toda semana).</p>
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 700;">
                                <input type="checkbox" id="checkIsPeriodica" name="is_periodica" value="1" style="width: 20px; height: 20px; accent-color: var(--primary);" onchange="togglePeriodicPanel()">
                                <span>Sim, é periódica</span>
                            </label>
                        </div>

                        <!-- Painel de Detalhes da Periodicidade -->
                        <div id="periodicPanel" class="periodic-panel">
                            <div class="form-row-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="inputPeriodicidade" class="form-label">Repetição *</label>
                                    <select id="inputPeriodicidade" name="periodicidade" class="form-control" onchange="updateLembreteSuggestions()">
                                        <option value="semanal">Semanal (Toda Semana)</option>
                                        <option value="quinzenal">Quinzenal (A cada 15 dias)</option>
                                        <option value="mensal" selected>Mensal (Todo Mês)</option>
                                        <option value="anual">Anual</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="inputDiaLembrete" class="form-label">Regra / Dia de Lembrete</label>
                                    <input type="text" id="inputDiaLembrete" name="dia_lembrete" class="form-control" placeholder="Ex: Todo dia 20 de cada mês">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="inputAntecedencia" class="form-label">Alerta Crítico</label>
                                    <select id="inputAntecedencia" name="lembrete_antecedencia_dias" class="form-control">
                                        <option value="1">1 dia antes</option>
                                        <option value="2">2 dias antes</option>
                                        <option value="3" selected>3 dias antes</option>
                                        <option value="5">5 dias antes</option>
                                        <option value="7">7 dias antes (1 semana)</option>
                                        <option value="15">15 dias antes</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Prioridade, Status, Seção e Responsável -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="inputPrioridade" class="form-label">Prioridade</label>
                            <select id="inputPrioridade" name="prioridade" class="form-control">
                                <option value="baixa">Baixa</option>
                                <option value="media" selected>Média</option>
                                <option value="alta">Alta</option>
                                <option value="critica">Crítica (Máxima Urgência)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="inputStatus" class="form-label">Status da Tarefa</label>
                            <select id="inputStatus" name="status" class="form-control">
                                <option value="pendente" selected>Pendente</option>
                                <option value="em_andamento">Em Andamento</option>
                                <option value="concluida">Concluída</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="inputSecao" class="form-label">Seção Vinculada (Opcional)</label>
                            <select id="inputSecao" name="secao_id" class="form-control">
                                <option value="">Todas as Seções / Geral</option>
                                <?php foreach ($secoes as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= $s['nome'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="inputResponsavel" class="form-label">Militar Responsável (Opcional)</label>
                            <select id="inputResponsavel" name="responsavel_id" class="form-control">
                                <option value="">Nenhum / Atribuir depois</option>
                                <?php foreach ($militares as $m): 
                                    $nomeExib = (!empty($m['grade']) && strtoupper($m['grade']) !== 'MILITAR' ? $m['grade'] . ' ' : '') . (!empty($m['war_name']) && $m['war_name'] !== '-' ? $m['war_name'] : $m['name']);
                                ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($nomeExib) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer" style="padding: 16px 24px; background: #F8FAFC; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeTaskModal()" style="padding: 8px 16px; border-radius: 6px; border: 1px solid #CBD5E1; background: white; cursor: pointer;">Cancelar</button>
                    <button type="submit" id="btnSaveTask" class="btn btn-primary" style="background: var(--primary); color: white; padding: 8px 20px; border-radius: 6px; border: none; font-weight: 700; cursor: pointer;">Salvar Tarefa</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script>
        let allTasks = [];
        let activeFilters = {
            status: 'all',
            periodicidade: 'all'
        };

        // Carregar tarefas ao inicializar a página
        document.addEventListener('DOMContentLoaded', () => {
            loadTasks();
        });

        // Carregar tarefas da API
        async function loadTasks() {
            try {
                const response = await fetch('api.php?action=list_tasks');
                const data = await response.json();
                
                if (data.success) {
                    allTasks = data.tasks || [];
                    updateKPIs(data.summary);
                    renderTasks();
                } else {
                    showToast(data.message || 'Erro ao carregar tarefas.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Falha na comunicação com o servidor.', 'error');
            }
        }

        // Atualizar os KPIs da tela
        function updateKPIs(summary) {
            if (!summary) return;
            document.getElementById('kpi-ativas').innerText = summary.ativas || 0;
            document.getElementById('kpi-criticas').innerText = summary.criticas || 0;
            document.getElementById('kpi-atrasadas').innerText = summary.atrasadas || 0;
            document.getElementById('kpi-periodicas').innerText = summary.periodicas || 0;
            document.getElementById('kpi-total-detail').innerText = `${summary.total || 0} cadastradas (${summary.concluidas || 0} concluídas)`;
        }

        // Renderizar lista filtrada de tarefas
        function renderTasks() {
            const listEl = document.getElementById('tasksList');
            const searchVal = document.getElementById('taskSearchInput').value.toLowerCase().trim();
            const priorityVal = document.getElementById('selectPriorityFilter').value;

            let filtered = allTasks.filter(t => {
                // Filtro Status
                if (activeFilters.status === 'pendentes' && (t.status === 'concluida' || t.is_atrasada)) return false;
                if (activeFilters.status === 'atrasadas' && (!t.is_atrasada || t.status === 'concluida')) return false;
                if (activeFilters.status === 'concluidas' && t.status !== 'concluida') return false;

                // Filtro Periodicidade
                if (activeFilters.periodicidade === 'periodicas' && !t.is_periodica) return false;
                if (activeFilters.periodicidade === 'mensal' && t.periodicidade !== 'mensal') return false;
                if (activeFilters.periodicidade === 'semanal' && t.periodicidade !== 'semanal') return false;

                // Filtro Prioridade
                if (priorityVal !== 'all' && t.prioridade !== priorityVal) return false;

                // Busca Textual
                if (searchVal) {
                    const text = `${t.titulo} ${t.descricao} ${t.categoria} ${t.secao_nome} ${t.responsavel_nome}`.toLowerCase();
                    if (!text.includes(searchVal)) return false;
                }

                return true;
            });

            if (filtered.length === 0) {
                listEl.innerHTML = `
                    <div class="empty-tasks">
                        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <h3 style="color: var(--primary); margin-bottom: 6px;">Nenhuma tarefa encontrada</h3>
                        <p style="margin: 0; font-size: 0.9rem;">Não há tarefas que correspondam aos filtros selecionados. Clique em "Lançar Tarefa com Prazo" para criar uma nova.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            filtered.forEach(t => {
                const isChecked = (t.status === 'concluida');
                
                // Formatação de contagem regressiva
                let countdownHtml = '';
                if (isChecked) {
                    countdownHtml = `<span class="due-countdown concluida">✓ Concluída (${t.concluido_em_fmt || 'OK'})</span>`;
                } else if (t.is_atrasada) {
                    const dias = Math.abs(t.dias_restantes);
                    countdownHtml = `<span class="due-countdown atrasada">⚠️ Atrasada há ${dias} ${dias === 1 ? 'dia' : 'dias'}</span>`;
                } else if (t.dias_restantes === 0) {
                    countdownHtml = `<span class="due-countdown hoje">🔥 Vence HOJE!</span>`;
                } else if (t.dias_restantes === 1) {
                    countdownHtml = `<span class="due-countdown urgente">⚡ Vence AMANHÃ</span>`;
                } else if (t.dias_restantes <= t.lembrete_antecedencia_dias) {
                    countdownHtml = `<span class="due-countdown urgente">⏳ Vence em ${t.dias_restantes} dias</span>`;
                } else {
                    countdownHtml = `<span class="due-countdown normal">📅 Em ${t.dias_restantes} dias</span>`;
                }

                // Badge de Periodicidade
                let periodicBadge = '';
                if (t.is_periodica) {
                    const labelPeriod = t.periodicidade === 'mensal' ? 'Mensal' : (t.periodicidade === 'semanal' ? 'Semanal' : t.periodicidade);
                    const ruleDetail = t.dia_lembrete ? ` (${t.dia_lembrete})` : '';
                    periodicBadge = `
                        <span class="badge-periodic" title="Lembrete Recorrente">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            ${labelPeriod}${ruleDetail}
                        </span>
                    `;
                }

                html += `
                    <div class="task-card priority-${t.prioridade} ${isChecked ? 'completed' : ''}" id="task-card-${t.id}">
                        <div class="task-checkbox-wrapper">
                            <input type="checkbox" class="task-checkbox" ${isChecked ? 'checked' : ''} onchange="toggleTaskStatus(${t.id})" title="Marcar como concluída / pendente">
                        </div>

                        <div class="task-main">
                            <div class="task-header-row">
                                <h4 class="task-title">${escapeHtml(t.titulo)}</h4>
                                <div class="task-badges">
                                    <span class="badge-priority ${t.prioridade}">${t.prioridade}</span>
                                    <span class="badge-cat">${escapeHtml(t.categoria)}</span>
                                    ${periodicBadge}
                                </div>
                            </div>

                            ${t.descricao ? `<div class="task-desc">${escapeHtml(t.descricao)}</div>` : ''}

                            <div class="task-meta">
                                <div class="task-meta-item">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    <span>${escapeHtml(t.secao_nome)}</span>
                                </div>
                                ${t.responsavel_nome ? `
                                    <div class="task-meta-item">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        <span>Resp: <strong>${escapeHtml(t.responsavel_nome)}</strong></span>
                                    </div>
                                ` : ''}
                                ${t.is_periodica && t.dia_lembrete ? `
                                    <div class="task-meta-item" style="color: #7E22CE;">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                        <span>Lembrete: ${escapeHtml(t.dia_lembrete)} (Alerta ${t.lembrete_antecedencia_dias}d antes)</span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>

                        <div class="task-due-box">
                            <div class="due-date">
                                <span>${t.data_limite_fmt}</span>
                                ${t.hora_limite && t.hora_limite !== '23:59' ? `<span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">às ${t.hora_limite}</span>` : ''}
                            </div>
                            ${countdownHtml}
                            <div class="task-actions">
                                <button type="button" class="btn-icon-task" onclick="editTask(${t.id})" title="Editar Tarefa">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <button type="button" class="btn-icon-task delete" onclick="deleteTask(${t.id}, '${escapeHtml(t.titulo)}')" title="Excluir Tarefa">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            listEl.innerHTML = html;
        }

        // Filtros Pills
        function setFilter(type, value, element) {
            activeFilters[type] = value;
            document.querySelectorAll(`.filter-pill[data-filter="${type}"]`).forEach(p => p.classList.remove('active'));
            element.classList.add('active');
            renderTasks();
        }

        function filterTasks() {
            renderTasks();
        }

        // Alternar Painel de Periodicidade
        function togglePeriodicPanel() {
            const isChecked = document.getElementById('checkIsPeriodica').checked;
            const panel = document.getElementById('periodicPanel');
            if (isChecked) {
                panel.classList.add('active');
                if (!document.getElementById('inputDiaLembrete').value) {
                    updateLembreteSuggestions();
                }
            } else {
                panel.classList.remove('active');
            }
        }

        function updateLembreteSuggestions() {
            const per = document.getElementById('inputPeriodicidade').value;
            const diaField = document.getElementById('inputDiaLembrete');
            if (per === 'mensal' && (!diaField.value || diaField.value.includes('Toda'))) {
                diaField.value = 'Todo dia 20 de cada mês';
            } else if (per === 'semanal' && (!diaField.value || diaField.value.includes('mês'))) {
                diaField.value = 'Toda sexta-feira';
            } else if (per === 'quinzenal') {
                diaField.value = 'A cada 15 dias (dias 05 e 20)';
            }
        }

        // Aplicar Presets Rápidos
        function applyPreset(type) {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');

            if (type === 'ferias') {
                document.getElementById('inputTitulo').value = 'Lançar Férias';
                document.getElementById('inputDescricao').value = 'Realizar a conferência e lançamento dos períodos de fruição de férias do efetivo no sistema para o próximo mês de referência.';
                document.getElementById('inputCategoria').value = 'Férias';
                document.getElementById('inputPrioridade').value = 'alta';
                
                // Data limite para dia 20
                let nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 20);
                document.getElementById('inputDataLimite').value = nextMonth.toISOString().split('T')[0];
                
                document.getElementById('checkIsPeriodica').checked = true;
                togglePeriodicPanel();
                document.getElementById('inputPeriodicidade').value = 'mensal';
                document.getElementById('inputDiaLembrete').value = 'Todo dia 20 de cada mês';
                document.getElementById('inputAntecedencia').value = '5';
            } 
            else if (type === 'escala') {
                document.getElementById('inputTitulo').value = 'Conferência Semanal da Escala';
                document.getElementById('inputDescricao').value = 'Conferir, ajustar e validar as escalas operacionais e de sobreaviso da próxima semana para publicação oficial.';
                document.getElementById('inputCategoria').value = 'Escala';
                document.getElementById('inputPrioridade').value = 'critica';
                
                // Próxima sexta
                let nextFriday = new Date();
                nextFriday.setDate(today.getDate() + ((7 - today.getDay() + 5) % 7 || 7));
                document.getElementById('inputDataLimite').value = nextFriday.toISOString().split('T')[0];

                document.getElementById('checkIsPeriodica').checked = true;
                togglePeriodicPanel();
                document.getElementById('inputPeriodicidade').value = 'semanal';
                document.getElementById('inputDiaLembrete').value = 'Toda sexta-feira';
                document.getElementById('inputAntecedencia').value = '2';
            }
            else if (type === 'relatorio') {
                document.getElementById('inputTitulo').value = 'Relatório Mensal de Efetivo';
                document.getElementById('inputDescricao').value = 'Consolidar os indicadores mensais de presenças, faltas, afastamentos e inspeções de saúde para apreciação do Comando.';
                document.getElementById('inputCategoria').value = 'Administrativo';
                document.getElementById('inputPrioridade').value = 'critica';
                
                let lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                document.getElementById('inputDataLimite').value = lastDay.toISOString().split('T')[0];

                document.getElementById('checkIsPeriodica').checked = true;
                togglePeriodicPanel();
                document.getElementById('inputPeriodicidade').value = 'mensal';
                document.getElementById('inputDiaLembrete').value = 'Último dia útil do mês';
                document.getElementById('inputAntecedencia').value = '3';
            }
            else if (type === 'saude') {
                document.getElementById('inputTitulo').value = 'Inspeção de Saúde - Revisão Periódica';
                document.getElementById('inputDescricao').value = 'Acompanhar militares com inspeção de saúde com vencimento nos próximos 30/60 dias para agendamento na Junta de Saúde.';
                document.getElementById('inputCategoria').value = 'Inspeção de Saúde';
                document.getElementById('inputPrioridade').value = 'alta';
                
                let in30Days = new Date();
                in30Days.setDate(today.getDate() + 30);
                document.getElementById('inputDataLimite').value = in30Days.toISOString().split('T')[0];
            }
            else if (type === 'licenca') {
                document.getElementById('inputTitulo').value = 'Controle e Registro de Licenças';
                document.getElementById('inputDescricao').value = 'Registrar e validar os afastamentos médicos, licenças especiais e dispensas concedidas no período.';
                document.getElementById('inputCategoria').value = 'Pessoal / Efetivo';
                document.getElementById('inputPrioridade').value = 'media';
                
                let in15Days = new Date();
                in15Days.setDate(today.getDate() + 15);
                document.getElementById('inputDataLimite').value = in15Days.toISOString().split('T')[0];
            }
        }

        // Modal Controls
        function openNewTaskModal() {
            document.getElementById('taskForm').reset();
            document.getElementById('taskId').value = '0';
            document.getElementById('taskModalTitle').innerText = 'Lançar Tarefa com Prazo';
            document.getElementById('checkIsPeriodica').checked = false;
            document.getElementById('periodicPanel').classList.remove('active');
            
            // Default data limite = hoje + 7 dias
            const d = new Date();
            d.setDate(d.getDate() + 7);
            document.getElementById('inputDataLimite').value = d.toISOString().split('T')[0];
            document.getElementById('inputHoraLimite').value = '23:59';
            
            document.getElementById('taskModal').style.display = 'flex';
        }

        function closeTaskModal() {
            document.getElementById('taskModal').style.display = 'none';
        }

        function editTask(id) {
            const t = allTasks.find(item => item.id == id);
            if (!t) return;

            document.getElementById('taskId').value = t.id;
            document.getElementById('taskModalTitle').innerText = 'Editar Tarefa com Prazo';
            document.getElementById('inputTitulo').value = t.titulo;
            document.getElementById('inputDescricao').value = t.descricao || '';
            document.getElementById('inputDataLimite').value = t.data_limite;
            document.getElementById('inputHoraLimite').value = t.hora_limite || '23:59';
            document.getElementById('inputCategoria').value = t.categoria || 'Geral';
            document.getElementById('inputPrioridade').value = t.prioridade || 'media';
            document.getElementById('inputStatus').value = t.status || 'pendente';
            document.getElementById('inputSecao').value = t.secao_id || '';
            document.getElementById('inputResponsavel').value = t.responsavel_id || '';

            document.getElementById('checkIsPeriodica').checked = t.is_periodica;
            if (t.is_periodica) {
                document.getElementById('periodicPanel').classList.add('active');
                document.getElementById('inputPeriodicidade').value = t.periodicidade || 'mensal';
                document.getElementById('inputDiaLembrete').value = t.dia_lembrete || '';
                document.getElementById('inputAntecedencia').value = t.lembrete_antecedencia_dias || '3';
            } else {
                document.getElementById('periodicPanel').classList.remove('active');
            }

            document.getElementById('taskModal').style.display = 'flex';
        }

        // Submeter formulário de cadastro / edição
        async function submitTaskForm(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveTask');
            btn.disabled = true;
            btn.innerText = 'Gravando...';

            const form = document.getElementById('taskForm');
            const formData = new FormData(form);

            try {
                const response = await fetch('api.php?action=save_task', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    closeTaskModal();
                    loadTasks();
                } else {
                    showToast(data.message || 'Erro ao salvar tarefa.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Erro de conexão ao salvar tarefa.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerText = 'Salvar Tarefa';
            }
        }

        // Alternar status (Concluir / Reabrir)
        async function toggleTaskStatus(id) {
            const formData = new FormData();
            formData.append('id', id);

            try {
                const response = await fetch('api.php?action=toggle_task_status', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    loadTasks();
                } else {
                    showToast(data.message || 'Erro ao atualizar status.', 'error');
                    loadTasks();
                }
            } catch (err) {
                console.error(err);
                showToast('Falha na conexão.', 'error');
                loadTasks();
            }
        }

        // Excluir Tarefa
        async function deleteTask(id, titulo) {
            if (!confirm(`Tem certeza que deseja excluir a tarefa:\n"${titulo}"?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('id', id);

            try {
                const response = await fetch('api.php?action=delete_task', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    loadTasks();
                } else {
                    showToast(data.message || 'Erro ao excluir tarefa.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Falha na comunicação ao excluir.', 'error');
            }
        }

        // Sistema de Toasts
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerText = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 400);
            }, 3500);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.innerText = text;
            return div.innerHTML;
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('taskModal');
            if (event.target === modal) {
                closeTaskModal();
            }
        }
    </script>
</body>
</html>
