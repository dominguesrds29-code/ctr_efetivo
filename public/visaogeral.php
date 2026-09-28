<?php
// visaogeral.php
// Visão Geral & Gerenciamento de Pessoal Integrado (DTCEA-SJ)

require_once __DIR__ . '/auth.php';
requireLogin();

$user = getCurrentUser();
$isAdmin = ($user && in_array($user['perfil'], ['admin', 'chefia']));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visão Geral do Efetivo - DTCEA-SJ</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
            <a href="index.php" class="nav-link">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                Lançar Chamada
            </a>
            <a href="visaogeral.php" class="nav-link active">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                Visão Geral
            </a>
            <?php if (in_array($user['perfil'], ['chefia', 'admin'])): ?>
                <a href="dashboard.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2a2 2 0 00-2 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Painel da Chefia
                </a>
            <?php endif; ?>
            <?php if ($user['perfil'] === 'admin'): ?>
                <a href="admin.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    Administração
                </a>
            <?php endif; ?>
            <a href="help.php" class="nav-link">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Ajuda
            </a>
            
            <div class="nav-user">
                <span>Olá, <strong><?= $user['nome'] ?></strong></span>
                <span class="badge-profile"><?= $user['perfil'] ?></span>
                <a href="alterarsenha.php" style="color: var(--accent); margin-left: 10px; text-decoration: underline; font-size: 0.8rem;">Alterar Senha</a>
            </div>
            <a href="logout.php" class="btn-logout">Sair</a>
        </nav>
    </header>

    <div class="container container-wide">
        <!-- Cabeçalho da Página -->
        <div class="page-header">
            <div class="page-title">
                <h2>Visão Geral & Gestão de Pessoal</h2>
                <p>Controle de efetivo, inspeções de saúde, tempos de serviço, reserva e estrutura de seções do DTCEA-SJ.</p>
            </div>
        </div>

        <!-- DASHBOARD KPI CARDS -->
        <div class="dashboard-grid">
            <div class="stat-card primary">
                <div class="stat-label">Efetivo Cadastrado</div>
                <div class="stat-value" id="dash-total">0</div>
                <div class="stat-footer">Militares e civis no DTCEA-SJ</div>
            </div>

            <div class="stat-card warning">
                <div class="stat-label">Inspeção de Saúde</div>
                <div class="stat-value" id="dash-inspections" style="display: flex; align-items: center;">
                    <span class="indicator online"></span> <span>0</span>
                </div>
                <div class="stat-footer" id="dash-inspections-detail">Verificando vencimentos...</div>
            </div>

            <div class="stat-card info">
                <div class="stat-label">Próximos da Reserva</div>
                <div class="stat-value" id="dash-reserves">0</div>
                <div class="stat-footer" id="dash-reserves-detail">Militares c/ &le; 3 anos faltantes</div>
            </div>

            <div class="stat-card success">
                <div class="stat-label">Permanência Média</div>
                <div class="stat-value" id="dash-avg-time">0.0 Anos</div>
                <div class="stat-footer">Tempo médio no DTCEA-SJ</div>
            </div>
        </div>

        <!-- BARRA DE CONTROLES & FILTROS -->
        <div class="controls-bar">
            <div class="search-filter-group">
                <div class="input-container">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="search-input" class="search-input" placeholder="Buscar por Nome, SARAM, CPF, Identidade, Especialidade...">
                </div>

                <select id="filter-status" class="filter-select" style="font-weight: 700; color: var(--primary);">
                    <option value="ACTIVE" selected>Status: Ativos</option>
                    <option value="INACTIVE">Status: Desativados</option>
                    <option value="ALL">Status: Todos (Geral)</option>
                </select>

                <select id="filter-type" class="filter-select">
                    <option value="MILITAR" selected>Militares</option>
                    <option value="CIVIL">Civis</option>
                    <option value="TODOS">Todos (Militares e Civis)</option>
                </select>

                <select id="filter-rank" class="filter-select">
                    <option value="">Todos os Postos</option>
                </select>

                <select id="filter-specialty" class="filter-select">
                    <option value="">Todas as Especialidades</option>
                </select>

                <select id="filter-health" class="filter-select">
                    <option value="">Insp. Saúde (Todas)</option>
                    <option value="VALID">Válida</option>
                    <option value="WARNING">Expira em breve (&le; 60 dias)</option>
                    <option value="EXPIRED">Vencida / Pendente</option>
                </select>

                <select id="filter-reserve" class="filter-select">
                    <option value="">Tempo Reserva (Todos)</option>
                    <option value="UPCOMING">Próxima (&le; 2 anos)</option>
                    <option value="MID">Médio (2 a 5 anos)</option>
                    <option value="LONG">Longo (&gt; 5 anos)</option>
                </select>
            </div>

            <div class="btn-group">
                <?php if ($isAdmin): ?>
                    <button type="button" class="btn btn-primary" onclick="openSecoesModal()" title="Gerenciar Seções">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                        Seções
                    </button>
                    <button type="button" class="btn btn-gold" onclick="openAddModal()">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7.5" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                        Novo Militar
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" onclick="exportToCSV()" title="Exportar Dados em CSV">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Exportar CSV
                </button>
            </div>
        </div>

        <!-- TABELA DE EFETIVO -->
        <div class="section-card">
            <div class="section-title">
                <span>Relação Geral do Efetivo</span>
                <span class="section-badge" id="table-count-badge">0 Registros</span>
            </div>
            <div class="table-responsive">
                <table class="visaogeral-table" id="personnel-table">
                    <thead>
                        <tr>
                            <th>SARAM</th>
                            <th style="min-width: 140px;">Posto / Grad / Nome</th>
                            <th>Especialidade</th>
                            <th>Seção</th>
                            <th>Identidade</th>
                            <th>CPF</th>
                            <th>Tempo Serviço</th>
                            <th>Para Reserva</th>
                            <th>Tempo DTCEA-SJ</th>
                            <th>Prorrogação</th>
                            <th>Insp. Saúde Val.</th>
                            <?php if ($isAdmin): ?>
                                <th style="width: 60px; text-align: center;">Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="<?= $isAdmin ? 12 : 11 ?>" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                                Carregando dados do efetivo...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL CADASTRO / EDIÇÃO DE MILITAR -->
    <div id="person-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title" id="person-modal-title">Ficha Cadastral do Militar</h3>
                <button type="button" class="modal-close-btn" id="close-modal-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <form id="person-form" class="modal-form-scrollable">
                <input type="hidden" id="modal-action" value="add">
                <input type="hidden" id="modal-edit-id" value="">

                <div class="modal-body">
                    <div class="form-grid">
                        <!-- Identificação Básica -->
                        <div class="form-group">
                            <label for="field-id">ID (Sistema)</label>
                            <input type="text" id="field-id" class="form-control" placeholder="Automático" readonly>
                        </div>
                        <div class="form-group">
                            <label for="field-personType">Tipo</label>
                            <select id="field-personType" class="form-control">
                                <option value="MILITAR" selected>Militar</option>
                                <option value="CIVIL">Civil</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="field-rank">Posto/Grad. *</label>
                            <input type="text" id="field-rank" class="form-control" placeholder="Ex: SO, 1S, 2S, CAP..." required>
                        </div>
                        <div class="form-group">
                            <label for="field-specialty">Especialidade *</label>
                            <input type="text" id="field-specialty" class="form-control" style="text-transform: uppercase;" placeholder="Ex: BCT, BCO, BET, SAI..." required>
                        </div>
                        <div class="form-group col-full">
                            <label for="field-secao">Seção *</label>
                            <select id="field-secao" class="form-control" required>
                                <option value="" disabled selected>Selecione a seção</option>
                            </select>
                        </div>
                        <div class="form-group col-full">
                            <label for="field-name">Nome Completo *</label>
                            <input type="text" id="field-name" class="form-control" style="text-transform: uppercase;" placeholder="NOME COMPLETO DO MILITAR" required>
                        </div>
                        <div class="form-group col-full">
                            <label for="field-warName">Nome de Guerra *</label>
                            <input type="text" id="field-warName" class="form-control" style="text-transform: uppercase;" placeholder="NOME DE GUERRA DO MILITAR" required>
                        </div>
                        <div class="form-group">
                            <label for="field-identity">Identidade</label>
                            <input type="text" id="field-identity" class="form-control" placeholder="Ex: 515.740">
                        </div>
                        <div class="form-group">
                            <label for="field-cpf">CPF</label>
                            <input type="text" id="field-cpf" class="form-control" placeholder="Ex: 000.000.000-00">
                        </div>
                        <div class="form-group">
                            <label for="field-saram">SARAM</label>
                            <input type="text" id="field-saram" class="form-control" placeholder="Ex: 393.068-8">
                        </div>

                        <!-- Administrativo -->
                        <div class="form-group">
                            <label for="field-birthDate">Data de Nascimento</label>
                            <input type="text" id="field-birthDate" class="form-control" placeholder="DD/MM/AAAA" required>
                        </div>
                        <div class="form-group">
                            <label for="field-age">Idade (Cálculo Automático)</label>
                            <input type="number" id="field-age" class="form-control" placeholder="Preencha Nascimento" readonly>
                        </div>
                        <div class="form-group">
                            <label for="field-pracaDate">Data de Praça</label>
                            <input type="text" id="field-pracaDate" class="form-control" placeholder="DD/MM/AAAA" required>
                        </div>
                        <div class="form-group">
                            <label for="field-lastPromotionDate">Última Promoção</label>
                            <input type="text" id="field-lastPromotionDate" class="form-control" placeholder="DD/MM/AAAA">
                        </div>
                        <div class="form-group">
                            <label for="field-presentationDate">Apresentação no DTCEA</label>
                            <input type="text" id="field-presentationDate" class="form-control" placeholder="DD/MM/AAAA" required>
                        </div>

                        <!-- Inspeção de Saúde -->
                        <div class="form-group">
                            <label for="field-healthInspectionDate">Realização Insp. Saúde</label>
                            <input type="text" id="field-healthInspectionDate" class="form-control" placeholder="DD/MM/AAAA">
                        </div>
                        <div class="form-group">
                            <label for="field-healthInspectionValidity">Validade Insp. Saúde</label>
                            <input type="text" id="field-healthInspectionValidity" class="form-control" placeholder="DD/MM/AAAA">
                        </div>
                        <div class="form-group">
                            <label for="field-prorrogacao">Prorrogação</label>
                            <input type="text" id="field-prorrogacao" class="form-control" placeholder="DD/MM/AAAA">
                        </div>

                        <div class="form-group col-full" style="margin-top: 10px; border-top: 1px solid var(--border); padding-top: 10px;">
                            <label style="font-weight: 700; color: var(--primary);">Informações de Serviço Calculadas</label>
                        </div>
                        <div class="form-group">
                            <label for="field-serviceTime">Tempo de Serviço</label>
                            <input type="text" id="field-serviceTime" class="form-control" placeholder="Cálculo automático" readonly>
                        </div>
                        <div class="form-group">
                            <label for="field-timeToReserve">Anos para Reserva</label>
                            <input type="number" id="field-timeToReserve" class="form-control" placeholder="Cálculo automático" readonly>
                        </div>
                        <div class="form-group col-full">
                            <label for="field-timeDtceaSj">Tempo no DTCEA-SJ</label>
                            <input type="text" id="field-timeDtceaSj" class="form-control" placeholder="Cálculo automático" readonly>
                        </div>

                        <!-- Observações e Acesso -->
                        <div class="form-group col-full">
                            <label for="field-observations">Observações</label>
                            <textarea id="field-observations" class="form-control" rows="2" placeholder="Anotações internas..."></textarea>
                        </div>
                        <?php if ($isAdmin): ?>
                            <div class="form-group col-full" style="display: flex; flex-direction: row; align-items: center; gap: 8px; background: #F8FAFC; padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
                                <input type="checkbox" id="field-isAdmin" style="width: 18px; height: 18px; cursor: pointer;">
                                <label for="field-isAdmin" style="margin: 0; font-weight: 600; cursor: pointer; color: var(--primary);">Conceder privilégios de Administrador</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancel-modal-btn">Cancelar</button>
                    <button type="submit" class="btn btn-gold">Salvar Registro</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL GERENCIAMENTO DE SEÇÕES -->
    <div id="secoes-modal" class="modal-overlay">
        <div class="modal-container" style="max-width: 580px;">
            <div class="modal-header">
                <h3 class="modal-title">Gerenciar Estrutura de Seções</h3>
                <button type="button" class="modal-close-btn" onclick="closeSecoesModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <form id="secao-form" style="display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap;">
                    <input type="hidden" id="secao-id" value="">
                    <div class="form-group" style="flex: 1; min-width: 160px;">
                        <label for="secao-sigla">Sigla da Seção</label>
                        <input type="text" id="secao-sigla" class="form-control" style="text-transform: uppercase;" placeholder="Ex: TWR" required>
                    </div>
                    <div class="form-group" style="flex: 1.5; min-width: 200px;">
                        <label for="secao-nome">Nome por Extenso</label>
                        <input type="text" id="secao-nome" class="form-control" style="text-transform: uppercase;" placeholder="Ex: TORRE DE CONTROLE">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-gold" style="height: 38px;">Salvar</button>
                        <button type="button" class="btn btn-secondary" onclick="resetSecaoForm()" style="height: 38px;" title="Cancelar Edição">✕</button>
                    </div>
                </form>

                <div class="table-responsive" style="max-height: 350px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px;">
                    <table class="efetivo-table" id="secoes-table">
                        <thead>
                            <tr>
                                <th>Sigla</th>
                                <th>Nome Completo</th>
                                <th style="width: 80px; text-align: center;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Preenchido via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT PRINCIPAL INTEGRADO -->
    <script>
    let personnelData = [];
    let secoesData = [];
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

    document.addEventListener('DOMContentLoaded', () => {
        setupEventListeners();
        loadAllData();
    });

    async function loadAllData() {
        await fetchSecoes();
        await fetchPersonnel();
    }

    // Auxiliar de Formatação de CPF
    function formatCPF(cpfStr) {
        if (!cpfStr) return '-';
        const digits = String(cpfStr).replace(/\D/g, '');
        if (digits.length === 11) {
            return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }
        return cpfStr;
    }

    // Auxiliar de Data
    function parseDateString(dateStr) {
        if (!dateStr) return null;
        const cleanStr = dateStr.trim();
        const parts = cleanStr.split(/[\/\-]/).map(p => p.trim());
        if (parts.length !== 3) return null;

        let day, month, year;
        if (parts[0].length === 4) { // AAAA-MM-DD
            year = parseInt(parts[0], 10);
            month = parseInt(parts[1], 10) - 1;
            day = parseInt(parts[2], 10);
        } else if (parts[2].length === 4) { // DD/MM/AAAA
            day = parseInt(parts[0], 10);
            month = parseInt(parts[1], 10) - 1;
            year = parseInt(parts[2], 10);
        } else {
            return null;
        }

        if (isNaN(day) || isNaN(month) || isNaN(year)) return null;
        const dateObj = new Date(year, month, day);
        if (isNaN(dateObj.getTime())) return null;
        return dateObj;
    }

    function calculateDateDifference(startDateStr, endDateObj) {
        const start = parseDateString(startDateStr);
        if (!start) return { years: 0, months: 0, days: 0 };
        const end = endDateObj;

        if (start > end) return { years: 0, months: 0, days: 0 };

        let years = end.getFullYear() - start.getFullYear();
        let months = end.getMonth() - start.getMonth();
        let days = end.getDate() - start.getDate();

        if (days < 0) {
            months--;
            const prevMonth = new Date(end.getFullYear(), end.getMonth(), 0);
            days += prevMonth.getDate();
        }

        if (months < 0) {
            years--;
            months += 12;
        }

        return { years, months, days };
    }

    function calculateAge(birthDateStr, today) {
        const birth = parseDateString(birthDateStr);
        if (!birth) return null;
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age;
    }

    function recalculateItemFields(item) {
        const today = new Date();

        // 1. Idade
        item.age = item.birthDate ? calculateAge(item.birthDate, today) : null;

        // 2. Tempo de Serviço (Praça) & Reserva (meta 30 anos)
        if (item.pracaDate) {
            const timeDiff = calculateDateDifference(item.pracaDate, today);
            item.serviceTime = `${timeDiff.years}A ${timeDiff.months}M ${timeDiff.days}D`;
            item.serviceTimeYears = timeDiff.years;
            item.timeToReserve = Math.max(0, 30 - timeDiff.years);
        } else {
            item.serviceTime = '';
            item.serviceTimeYears = null;
            item.timeToReserve = null;
        }

        // 3. Tempo DTCEA-SJ
        if (item.presentationDate) {
            const timeDiff = calculateDateDifference(item.presentationDate, today);
            item.timeDtceaSj = `${timeDiff.years}A ${timeDiff.months}M ${timeDiff.days}D`;
        } else {
            item.timeDtceaSj = '';
        }

        // 4. Inspeção de Saúde
        if (item.healthInspectionValidity) {
            const validityDate = parseDateString(item.healthInspectionValidity);
            if (validityDate) {
                const diffTime = validityDate - today;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                item.healthInspectionDaysLeft = diffDays;
                if (diffDays < 0) {
                    item.healthInspectionObs = "EXPIRED";
                } else if (diffDays <= 60) {
                    item.healthInspectionObs = "WARNING";
                } else {
                    item.healthInspectionObs = "VALID";
                }
            } else {
                item.healthInspectionDaysLeft = null;
                item.healthInspectionObs = "NOT_DONE";
            }
        } else {
            item.healthInspectionDaysLeft = null;
            item.healthInspectionObs = "NOT_DONE";
        }

        // 5. Prorrogação
        item.prorrogacaoDaysLeft = null;
        item.prorrogacaoWarning = false;
        if (item.prorrogacaoDate) {
            const prorrogacaoObj = parseDateString(item.prorrogacaoDate);
            if (prorrogacaoObj) {
                const diffTime = prorrogacaoObj - today;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                item.prorrogacaoDaysLeft = diffDays;
                if (diffDays <= 90 && diffDays >= 0) {
                    item.prorrogacaoWarning = true;
                }
            }
        }
    }

    async function fetchPersonnel() {
        try {
            const response = await fetch('api.php?action=list_personnel');
            const result = await response.json();
            if (result.success) {
                personnelData = result.data.map(item => {
                    const p = {
                        id: item.id,
                        rank: item.posto_grad,
                        specialty: item.especialidade,
                        secao: item.secao,
                        secao_nome: item.secao_nome,
                        name: item.nome,
                        warName: item.nome_guerra,
                        identity: item.identidade,
                        cpf: item.cpf,
                        saram: item.saram,
                        birthDate: item.nascimento,
                        pracaDate: item.praca,
                        lastPromotionDate: item.ult_promocao,
                        presentationDate: item.apresentacao_dtcea,
                        healthInspectionDate: item.data_insp_saude,
                        healthInspectionValidity: item.validade_insp_saude,
                        prorrogacaoDate: item.prorrogacao,
                        observations: item.observacoes,
                        is_admin: item.is_admin,
                        personType: item.person_type,
                        isActive: (item.is_active !== false),
                        deletedAt: item.deleted_at || null
                    };
                    recalculateItemFields(p);
                    return p;
                });

                renderDashboard();
                renderTable();
                populateFilters();
            } else {
                alert("Erro ao carregar dados: " + (result.message || 'Falha na resposta'));
            }
        } catch (e) {
            console.error("Erro ao carregar efetivo:", e);
        }
    }

    function renderDashboard() {
        const activePersonnel = personnelData.filter(p => p.isActive);
        const totalCount = activePersonnel.length;
        document.getElementById('dash-total').innerText = totalCount;

        let expiredInspections = 0;
        let warningInspections = 0;
        activePersonnel.forEach(p => {
            if (p.healthInspectionObs === 'EXPIRED' || p.healthInspectionObs === 'NOT_DONE') {
                expiredInspections++;
            } else if (p.healthInspectionObs === 'WARNING') {
                warningInspections++;
            }
        });

        const inspValueEl = document.getElementById('dash-inspections');
        const inspDetailEl = document.getElementById('dash-inspections-detail');
        if (expiredInspections > 0) {
            inspValueEl.innerHTML = `<span class="indicator offline"></span> <span>${expiredInspections}</span>`;
            inspDetailEl.innerHTML = `<span style="color: var(--danger); font-weight: 700;">Pendentes / Vencidas</span>`;
        } else if (warningInspections > 0) {
            inspValueEl.innerHTML = `<span class="indicator running"></span> <span>${warningInspections}</span>`;
            inspDetailEl.innerHTML = `<span style="color: var(--warning); font-weight: 700;">Expira em até 60 dias</span>`;
        } else {
            inspValueEl.innerHTML = `<span class="indicator online"></span> <span>0</span>`;
            inspDetailEl.innerText = "Tudo em conformidade";
        }

        const activeReserves = activePersonnel.filter(p => p.timeToReserve !== null && p.timeToReserve <= 3).length;
        document.getElementById('dash-reserves').innerText = activeReserves;
        document.getElementById('dash-reserves-detail').innerText = `${activeReserves} militares c/ menos de 3 anos`;

        let totalSjMonths = 0;
        let validCount = 0;
        activePersonnel.forEach(p => {
            if (p.presentationDate) {
                const diff = calculateDateDifference(p.presentationDate, new Date());
                totalSjMonths += (diff.years * 12) + diff.months;
                validCount++;
            }
        });
        const avgSjYears = validCount > 0 ? ((totalSjMonths / validCount) / 12).toFixed(1) : "0.0";
        document.getElementById('dash-avg-time').innerText = `${avgSjYears} Anos`;
    }

    function renderTable() {
        const tbody = document.querySelector('#personnel-table tbody');
        tbody.innerHTML = '';

        const searchQuery = document.getElementById('search-input').value.toLowerCase().trim();
        const cleanQuery = searchQuery.replace(/[\.\-\/\s]/g, '');
        const statusFilter = document.getElementById('filter-status') ? document.getElementById('filter-status').value : 'ACTIVE';
        const rankFilter = document.getElementById('filter-rank').value;
        const specialtyFilter = document.getElementById('filter-specialty').value;
        const healthFilter = document.getElementById('filter-health').value;
        const reserveFilter = document.getElementById('filter-reserve').value;
        const typeFilter = document.getElementById('filter-type').value;

        const filtered = personnelData.filter(p => {
            const pName = (p.name || '').toLowerCase();
            const pWar = (p.warName || '').toLowerCase();
            const pRank = (p.rank || '').toLowerCase();
            const pSpec = (p.specialty || '').toLowerCase();
            const pSecao = (p.secao || '').toLowerCase();
            const pSecaoNome = (p.secao_nome || '').toLowerCase();
            const pSaram = (p.saram || '').toLowerCase();
            const pCpf = (p.cpf || '').toLowerCase();
            const pIdentity = (p.identity || '').toLowerCase();
            const pPostoNome = `${pRank} ${pWar} ${pName}`.toLowerCase();

            const cleanSaram = pSaram.replace(/[\.\-\/\s]/g, '');
            const cleanCpf = pCpf.replace(/[\.\-\/\s]/g, '');
            const cleanIdentity = pIdentity.replace(/[\.\-\/\s]/g, '');

            const matchSearch = !searchQuery || 
                pName.includes(searchQuery) ||
                pWar.includes(searchQuery) ||
                pPostoNome.includes(searchQuery) ||
                pSpec.includes(searchQuery) ||
                pSecao.includes(searchQuery) ||
                pSecaoNome.includes(searchQuery) ||
                pIdentity.includes(searchQuery) ||
                pCpf.includes(searchQuery) ||
                pSaram.includes(searchQuery) ||
                (cleanQuery && (
                    cleanSaram.includes(cleanQuery) ||
                    cleanCpf.includes(cleanQuery) ||
                    cleanIdentity.includes(cleanQuery) ||
                    pName.includes(cleanQuery) ||
                    pWar.includes(cleanQuery)
                ));

            const matchStatus = (statusFilter === 'ALL') ||
                                (statusFilter === 'ACTIVE' && p.isActive) ||
                                (statusFilter === 'INACTIVE' && !p.isActive);

            const matchRank = !rankFilter || p.rank === rankFilter;
            const matchSpecialty = !specialtyFilter || p.specialty === specialtyFilter;
            const matchType = typeFilter === 'TODOS' || p.personType === typeFilter;

            let matchHealth = true;
            if (healthFilter) {
                if (healthFilter === 'VALID') matchHealth = p.healthInspectionObs === 'VALID';
                if (healthFilter === 'WARNING') matchHealth = p.healthInspectionObs === 'WARNING';
                if (healthFilter === 'EXPIRED') matchHealth = (p.healthInspectionObs === 'EXPIRED' || p.healthInspectionObs === 'NOT_DONE');
            }

            let matchReserve = true;
            if (reserveFilter) {
                if (reserveFilter === 'UPCOMING') matchReserve = p.timeToReserve !== null && p.timeToReserve <= 2;
                if (reserveFilter === 'MID') matchReserve = p.timeToReserve !== null && p.timeToReserve > 2 && p.timeToReserve <= 5;
                if (reserveFilter === 'LONG') matchReserve = p.timeToReserve !== null && p.timeToReserve > 5;
            }

            return matchSearch && matchStatus && matchRank && matchSpecialty && matchHealth && matchReserve && matchType;
        });

        document.getElementById('table-count-badge').innerText = `${filtered.length} Registros`;

        if (filtered.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${isAdmin ? 12 : 11}" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                        Nenhum registro localizado com os filtros selecionados.
                    </td>
                </tr>
            `;
            return;
        }

        filtered.forEach(p => {
            const tr = document.createElement('tr');
            if (p.prorrogacaoWarning) {
                tr.classList.add('row-prorrogacao-warning');
            }
            if (!p.isActive) {
                tr.classList.add('row-inactive');
            }

            let healthCell = '';
            if (p.healthInspectionObs === 'VALID') {
                healthCell = `<span class="health-badge valid">✔ Válida (${p.healthInspectionValidity})</span>`;
            } else if (p.healthInspectionObs === 'WARNING') {
                healthCell = `<span class="health-badge warning">⚠ Expira em ${p.healthInspectionDaysLeft}d</span>`;
            } else if (p.healthInspectionObs === 'EXPIRED') {
                healthCell = `<span class="health-badge expired">✖ Vencida (${p.healthInspectionValidity})</span>`;
            } else {
                healthCell = `<span class="health-badge not-done">? Não realizada</span>`;
            }

            const reserveText = p.timeToReserve !== null ? `${p.timeToReserve} Anos` : 'N/A';
            let prorrogacaoCell = p.prorrogacaoDate || '-';
            if (p.prorrogacaoWarning) {
                prorrogacaoCell = `<span style="color: var(--warning); font-weight: 700;">${p.prorrogacaoDate} <small>(${p.prorrogacaoDaysLeft}d)</small></span>`;
            }

            let actionsCell = '';
            if (isAdmin) {
                if (p.isActive) {
                    actionsCell = `
                        <td style="text-align: center;">
                            <button class="btn-icon" onclick="editPerson('${p.id}')" title="Editar Ficha">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            </button>
                            <button class="btn-icon delete" onclick="inactivatePerson('${p.id}')" title="Desativar Militar">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path><line x1="12" y1="2" x2="12" y2="12"></line></svg>
                            </button>
                        </td>
                    `;
                } else {
                    actionsCell = `
                        <td style="text-align: center;">
                            <button class="btn-icon" onclick="editPerson('${p.id}')" title="Visualizar / Editar Ficha">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            </button>
                            <button class="btn-icon activate" onclick="reactivatePerson('${p.id}')" title="Reativar no Efetivo Ativo">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            </button>
                        </td>
                    `;
                }
            }

            const warOrName = p.warName && p.warName !== '-' ? p.warName : p.name;
            const postoGradNome = `${p.rank ? p.rank + ' ' : ''}${warOrName}`.trim();
            const inactiveBadge = !p.isActive ? '<span class="badge-inactive">DESATIVADO</span>' : '';
            const fullNameSub = (p.name && p.name !== postoGradNome) ? `<div style="font-size: 0.67rem; color: var(--text-muted); text-transform: uppercase;">${p.name}</div>` : '';
            const secaoDisplay = p.secao_nome || p.secao || '-';

            tr.innerHTML = `
                <td><strong>${p.saram || '-'}</strong></td>
                <td class="col-posto-nome">
                    <strong style="color: var(--primary-dark); font-size: 0.81rem;">${postoGradNome}</strong>
                    ${inactiveBadge}
                    ${fullNameSub}
                </td>
                <td>${p.specialty || '-'}</td>
                <td>${secaoDisplay}</td>
                <td>${p.identity || '-'}</td>
                <td>${formatCPF(p.cpf)}</td>
                <td>${p.serviceTime || '-'}</td>
                <td>${reserveText}</td>
                <td>${p.timeDtceaSj || '-'}</td>
                <td>${prorrogacaoCell}</td>
                <td>${healthCell}</td>
                ${actionsCell}
            `;
            tbody.appendChild(tr);
        });
    }

    function populateFilters() {
        const rankFilter = document.getElementById('filter-rank');
        const currentRank = rankFilter.value;
        const ranks = [...new Set(personnelData.map(p => p.rank))].filter(Boolean).sort();
        rankFilter.innerHTML = '<option value="">Todos os Postos</option>';
        ranks.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r;
            opt.innerText = r;
            rankFilter.appendChild(opt);
        });
        rankFilter.value = currentRank;

        const specFilter = document.getElementById('filter-specialty');
        const currentSpec = specFilter.value;
        const specs = [...new Set(personnelData.map(p => p.specialty))].filter(Boolean).sort();
        specFilter.innerHTML = '<option value="">Todas as Especialidades</option>';
        specs.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.innerText = s;
            specFilter.appendChild(opt);
        });
        specFilter.value = currentSpec;
    }

    function setupEventListeners() {
        document.getElementById('search-input').addEventListener('input', renderTable);
        if (document.getElementById('filter-status')) document.getElementById('filter-status').addEventListener('change', renderTable);
        document.getElementById('filter-rank').addEventListener('change', renderTable);
        document.getElementById('filter-specialty').addEventListener('change', renderTable);
        document.getElementById('filter-health').addEventListener('change', renderTable);
        document.getElementById('filter-reserve').addEventListener('change', renderTable);
        document.getElementById('filter-type').addEventListener('change', renderTable);

        const modal = document.getElementById('person-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const cancelModalBtn = document.getElementById('cancel-modal-btn');
        const personForm = document.getElementById('person-form');

        const closeModal = () => {
            modal.classList.remove('active');
            personForm.reset();
            document.getElementById('modal-action').value = 'add';
            document.getElementById('modal-edit-id').value = '';
        };

        if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
        if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

        if (personForm) {
            personForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const ok = await savePerson();
                if (ok) {
                    closeModal();
                }
            });
        }

        // Live calculation preview in modal
        const birthInput = document.getElementById('field-birthDate');
        const pracaInput = document.getElementById('field-pracaDate');
        const presInput = document.getElementById('field-presentationDate');

        const triggerRecalculatePreview = () => {
            const today = new Date();
            const birth = birthInput.value;
            const praca = pracaInput.value;
            const pres = presInput.value;

            const birthDateObj = parseDateString(birth);
            if (birthDateObj) {
                const age = calculateAge(birth, today);
                document.getElementById('field-age').value = age !== null ? age : '';
            } else {
                document.getElementById('field-age').value = '';
            }

            const pracaDateObj = parseDateString(praca);
            if (pracaDateObj) {
                const diff = calculateDateDifference(praca, today);
                document.getElementById('field-serviceTime').value = `${diff.years}A ${diff.months}M ${diff.days}D`;
                document.getElementById('field-timeToReserve').value = Math.max(0, 30 - diff.years);
            } else {
                document.getElementById('field-serviceTime').value = '';
                document.getElementById('field-timeToReserve').value = '';
            }

            const presDateObj = parseDateString(pres);
            if (presDateObj) {
                const diff = calculateDateDifference(pres, today);
                document.getElementById('field-timeDtceaSj').value = `${diff.years}A ${diff.months}M ${diff.days}D`;
            } else {
                document.getElementById('field-timeDtceaSj').value = '';
            }
        };

        if (birthInput) birthInput.addEventListener('input', triggerRecalculatePreview);
        if (pracaInput) pracaInput.addEventListener('input', triggerRecalculatePreview);
        if (presInput) presInput.addEventListener('input', triggerRecalculatePreview);

        // Máscara automática de CPF
        const cpfInput = document.getElementById('field-cpf');
        if (cpfInput) {
            cpfInput.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '').substring(0, 11);
                if (v.length > 9) {
                    v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
                } else if (v.length > 6) {
                    v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
                } else if (v.length > 3) {
                    v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
                }
                e.target.value = v;
            });
        }
    }

    function openAddModal() {
        if (!isAdmin) return;
        document.getElementById('modal-action').value = 'add';
        document.getElementById('modal-edit-id').value = '';
        document.getElementById('person-modal-title').innerText = "Novo Cadastro de Militar";
        document.getElementById('field-id').value = "";
        document.getElementById('field-specialty').value = "";
        document.getElementById('field-secao').value = "";
        document.getElementById('field-prorrogacao').value = "";
        document.getElementById('field-personType').value = 'MILITAR';
        if (document.getElementById('field-isAdmin')) document.getElementById('field-isAdmin').checked = false;
        document.getElementById('person-modal').classList.add('active');
    }

    function editPerson(id) {
        const p = personnelData.find(item => item.id == id);
        if (!p) return;

        document.getElementById('modal-action').value = 'edit';
        document.getElementById('modal-edit-id').value = id;
        const statusLabel = !p.isActive ? " (DESATIVADO)" : "";
        document.getElementById('person-modal-title').innerText = `Ficha - ${p.rank} ${p.specialty || ''} ${p.name}${statusLabel}`;

        document.getElementById('field-id').value = p.id;
        document.getElementById('field-personType').value = p.personType || 'MILITAR';
        document.getElementById('field-rank').value = p.rank;
        document.getElementById('field-specialty').value = p.specialty || '';
        document.getElementById('field-secao').value = p.secao || '';
        document.getElementById('field-name').value = p.name;
        document.getElementById('field-warName').value = p.warName || '';
        document.getElementById('field-identity').value = p.identity || '';
        document.getElementById('field-cpf').value = formatCPF(p.cpf) !== '-' ? formatCPF(p.cpf) : '';
        document.getElementById('field-saram').value = p.saram || '';
        document.getElementById('field-birthDate').value = p.birthDate || '';
        document.getElementById('field-age').value = p.age || '';
        document.getElementById('field-pracaDate').value = p.pracaDate || '';
        document.getElementById('field-lastPromotionDate').value = p.lastPromotionDate || '';
        document.getElementById('field-presentationDate').value = p.presentationDate || '';
        document.getElementById('field-healthInspectionDate').value = p.healthInspectionDate || '';
        document.getElementById('field-healthInspectionValidity').value = p.healthInspectionValidity || '';
        document.getElementById('field-prorrogacao').value = p.prorrogacaoDate || '';
        document.getElementById('field-serviceTime').value = p.serviceTime || '';
        document.getElementById('field-timeToReserve').value = p.timeToReserve !== null ? p.timeToReserve : '';
        document.getElementById('field-timeDtceaSj').value = p.timeDtceaSj || '';
        document.getElementById('field-observations').value = p.observations || '';
        if (document.getElementById('field-isAdmin')) document.getElementById('field-isAdmin').checked = (p.is_admin === 1);

        document.getElementById('person-modal').classList.add('active');
    }

    async function savePerson() {
        const action = document.getElementById('modal-action').value;
        const editId = document.getElementById('modal-edit-id').value;

        const payload = {
            posto_grad: document.getElementById('field-rank').value.trim(),
            especialidade: document.getElementById('field-specialty').value.trim(),
            nome: document.getElementById('field-name').value.trim().toUpperCase(),
            nome_guerra: document.getElementById('field-warName').value.trim().toUpperCase(),
            identidade: document.getElementById('field-identity').value.trim(),
            cpf: document.getElementById('field-cpf').value.trim(),
            saram: document.getElementById('field-saram').value.trim(),
            secao: document.getElementById('field-secao').value.trim().toUpperCase(),
            nascimento: document.getElementById('field-birthDate').value.trim(),
            praca: document.getElementById('field-pracaDate').value.trim(),
            ult_promocao: document.getElementById('field-lastPromotionDate').value.trim(),
            apresentacao_dtcea: document.getElementById('field-presentationDate').value.trim(),
            data_insp_saude: document.getElementById('field-healthInspectionDate').value.trim(),
            validade_insp_saude: document.getElementById('field-healthInspectionValidity').value.trim(),
            prorrogacao: document.getElementById('field-prorrogacao').value.trim(),
            observacoes: document.getElementById('field-observations').value.trim(),
            is_admin: document.getElementById('field-isAdmin') && document.getElementById('field-isAdmin').checked ? 1 : 0,
            person_type: document.getElementById('field-personType').value
        };

        if (!payload.secao) {
            alert("A Seção é obrigatória. Por favor, selecione uma seção.");
            return false;
        }

        if (action === 'edit') {
            payload.id = editId;
        }

        const submitBtn = document.querySelector('#person-form button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerText : 'Salvar';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Salvando...';
        }

        try {
            const response = await fetch('api.php?action=save_person', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (result.success) {
                await fetchPersonnel();
                return true;
            } else if (result.is_inactive_conflict && result.inactive_id) {
                if (confirm(`${result.message}\n\nDeseja REATIVAR a ficha deste militar no efetivo agora?`)) {
                    await reactivatePerson(result.inactive_id, false);
                    document.getElementById('filter-status').value = 'ALL';
                    await fetchPersonnel();
                    editPerson(result.inactive_id);
                    return true;
                }
                return false;
            } else {
                const errorMsg = result.message || result.error || 'Erro desconhecido ao processar requisição.';
                alert("Erro ao salvar: " + errorMsg);
                return false;
            }
        } catch (e) {
            console.error(e);
            alert("Erro de comunicação ao salvar militar: " + e.message);
            return false;
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
            }
        }
    }

    async function inactivatePerson(id) {
        const p = personnelData.find(item => item.id == id);
        if (!p) return;

        if (confirm(`Deseja realmente DESATIVAR o militar ${p.rank} ${p.name}?\n\nEle não constará mais nas chamadas diárias e no efetivo ativo, mas suas informações ficarão preservadas para consulta no filtro "Desativados".`)) {
            try {
                const response = await fetch('api.php?action=inactivate_person', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const result = await response.json();
                if (result.success) {
                    await fetchPersonnel();
                } else {
                    const errorMsg = result.message || result.error || 'Erro ao desativar.';
                    alert("Erro ao desativar: " + errorMsg);
                }
            } catch (e) {
                console.error(e);
                alert("Erro de comunicação ao desativar militar: " + e.message);
            }
        }
    }

    async function reactivatePerson(id, showConfirm = true) {
        const p = personnelData.find(item => item.id == id);
        const name = p ? `${p.rank} ${p.name}` : 'o militar';

        if (!showConfirm || confirm(`Deseja REATIVAR ${name} no efetivo ativo?`)) {
            try {
                const response = await fetch('api.php?action=reactivate_person', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const result = await response.json();
                if (result.success) {
                    await fetchPersonnel();
                } else {
                    const errorMsg = result.message || result.error || 'Erro ao reativar.';
                    alert("Erro ao reativar: " + errorMsg);
                }
            } catch (e) {
                console.error(e);
                alert("Erro de comunicação ao reativar militar: " + e.message);
            }
        }
    }

    function exportToCSV() {
        const headers = [
            "Posto/Grad.", "Especialidade", "Seção", "Nome", "Nome de Guerra", "Identidade", "CPF", "SARAM", "Nascimento", "Idade", "Praça", 
            "Últ. Promoção", "Apresentação no DTCEA", "Prorrogação", "Data Realização Insp. Saúde", "Validade Insp. Saúde",
            "Tempo de Serviço", "Tempo faltante para reserva", "Tempo DTCEA-SJ", "OBSERVAÇÕES"
        ];

        const rows = personnelData.map(p => {
            return [
                p.rank,
                p.specialty || '',
                p.secao || '',
                p.name,
                p.warName || '',
                p.identity || '',
                p.cpf || '',
                p.saram || '',
                p.birthDate || '',
                p.age || '',
                p.pracaDate || '',
                p.lastPromotionDate || '',
                p.presentationDate || '',
                p.prorrogacaoDate || '',
                p.healthInspectionDate || '',
                p.healthInspectionValidity || '',
                p.serviceTime || '',
                p.timeToReserve !== null ? p.timeToReserve : '',
                p.timeDtceaSj || '',
                p.observations || ''
            ];
        });

        let csvContent = headers.join(",") + "\n";
        rows.forEach(row => {
            const parsedRow = row.map(val => {
                let strVal = val === null || val === undefined ? '' : String(val);
                if (strVal.includes(',') || strVal.includes('"') || strVal.includes('\n')) {
                    return `"${strVal.replace(/"/g, '""')}"`;
                }
                return strVal;
            });
            csvContent += parsedRow.join(",") + "\n";
        });

        const blob = new Blob([new Uint8Array([0xEF, 0xBB, 0xBF]), csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", "efetivo_geral_dtceasj.csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Seções
    async function fetchSecoes() {
        try {
            const response = await fetch('api.php?action=list_secoes');
            const result = await response.json();
            if (result.success) {
                secoesData = result.data;
                populateSecoesDropdown();
            }
        } catch (e) {
            console.error("Erro ao listar seções:", e);
        }
    }

    function populateSecoesDropdown() {
        const select = document.getElementById('field-secao');
        if (!select) return;
        const currentVal = select.value;
        select.innerHTML = '<option value="" disabled selected>Selecione a seção</option>';
        secoesData.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.sigla;
            opt.innerText = `${s.sigla} - ${s.nome}`;
            select.appendChild(opt);
        });
        if (currentVal) select.value = currentVal;
    }

    function openSecoesModal() {
        renderSecoesTable();
        document.getElementById('secoes-modal').classList.add('active');

        const form = document.getElementById('secao-form');
        if (!form.hasAttribute('data-initialized')) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await saveSecao();
            });
            form.setAttribute('data-initialized', 'true');
        }
    }

    function closeSecoesModal() {
        document.getElementById('secoes-modal').classList.remove('active');
        resetSecaoForm();
    }

    function resetSecaoForm() {
        document.getElementById('secao-id').value = '';
        document.getElementById('secao-sigla').value = '';
        document.getElementById('secao-nome').value = '';
    }

    function renderSecoesTable() {
        const tbody = document.querySelector('#secoes-table tbody');
        tbody.innerHTML = '';

        if (secoesData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color: var(--text-muted); padding: 1rem;">Nenhuma seção cadastrada</td></tr>';
            return;
        }

        secoesData.forEach(s => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight: 700; color: var(--primary);">${s.sigla}</td>
                <td>${s.nome || '-'}</td>
                <td style="text-align: center;">
                    <button class="btn-icon" onclick="editSecao('${s.id}')" title="Editar">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                    <button class="btn-icon delete" onclick="deleteSecao('${s.id}')" title="Excluir">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function editSecao(id) {
        const s = secoesData.find(item => item.id == id);
        if (!s) return;
        document.getElementById('secao-id').value = s.id;
        document.getElementById('secao-sigla').value = s.sigla;
        document.getElementById('secao-nome').value = s.nome || '';
    }

    async function saveSecao() {
        const id = document.getElementById('secao-id').value;
        const sigla = document.getElementById('secao-sigla').value.trim();
        const nome = document.getElementById('secao-nome').value.trim();

        if (!sigla) return alert("A sigla é obrigatória.");

        try {
            const response = await fetch('api.php?action=save_secao', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, sigla, nome })
            });
            const result = await response.json();
            if (result.success) {
                resetSecaoForm();
                await fetchSecoes();
                renderSecoesTable();
            } else {
                alert("Erro: " + (result.message || result.error || 'Falha ao salvar seção.'));
            }
        } catch (e) {
            console.error(e);
            alert("Erro de comunicação ao salvar seção: " + e.message);
        }
    }

    async function deleteSecao(id) {
        if (confirm("Tem certeza que deseja excluir esta seção?")) {
            try {
                const response = await fetch('api.php?action=delete_secao', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const result = await response.json();
                if (result.success) {
                    await fetchSecoes();
                    renderSecoesTable();
                } else {
                    alert("Erro: " + (result.message || result.error || 'Falha ao excluir seção.'));
                }
            } catch (e) {
                console.error(e);
                alert("Erro de comunicação ao excluir seção: " + e.message);
            }
        }
    }
    </script>
</body>
</html>
