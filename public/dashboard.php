<?php
// dashboard.php
// Painel de Chefia - Indicadores de Presença e Estatísticas por Seção

require_once __DIR__ . '/auth.php';
requireChefia(); // Apenas perfil chefia pode visualizar esta página

$user = getCurrentUser();
$selectedDate = sanitize($_GET['date'] ?? date('Y-m-d'));

try {
    $sec = getSecaoInfo($db);
    $secTable = $sec['table'];
    $secCol = $sec['name_col'];

    // Filtro para incluir apenas pessoal do expediente e excluir seções operacionais / sem seção
    $filterExpediente = "
        AND u.escala = 0 
        AND u.section_id IS NOT NULL 
        AND u.section_id > 1
        AND s.id IS NOT NULL
        AND s.id NOT IN (1, 8, 10, 11)
        AND TRIM(COALESCE(s.`$secCol`, '')) NOT IN ('Torre de Controle', 'TORRE DE CONTROLE', 'TWR', 'EMS', 'EMS1', 'EMS-1 / CMA-2', 'Sala AIS', 'SALA AIS', 'AIS', 'Sem Seção', '')
    ";

    // 1. Total do Efetivo do Expediente
    $stmtGeral = $db->query("
        SELECT COUNT(u.id) as total 
        FROM users u 
        JOIN `$secTable` s ON u.section_id = s.id 
        WHERE u.deleted_at IS NULL $filterExpediente
    ");
    $totalEfetivo = $stmtGeral->fetch()['total'];

    // 2. Presença Geral do dia Selecionado (Apenas Expediente)
    $stmtPresenca = $db->prepare("
        SELECT 
            SUM(CASE WHEN p.status IN ('P', 'EA', 'HO', 'O') THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN p.status IN ('A', 'PA', 'PB') THEN 1 ELSE 0 END) as ausentes,
            SUM(CASE WHEN p.status = 'F' THEN 1 ELSE 0 END) as ferias,
            SUM(CASE WHEN p.status IN ('DM', 'INS', 'LPM', 'D', 'DP') THEN 1 ELSE 0 END) as dm,
            SUM(CASE WHEN p.status IN ('C', 'M') THEN 1 ELSE 0 END) as afastados,
            SUM(CASE WHEN p.status IS NOT NULL THEN 1 ELSE 0 END) as total_respondido
        FROM presencas p
        JOIN users u ON p.militar_id = u.id
        JOIN `$secTable` s ON u.section_id = s.id
        WHERE p.data = ? 
          AND u.deleted_at IS NULL 
          $filterExpediente
    ");
    $stmtPresenca->execute([$selectedDate]);
    $stats = $stmtPresenca->fetch();

    $presentes = $stats['presentes'] ?? 0;
    $ausentes = $stats['ausentes'] ?? 0;
    $ferias = $stats['ferias'] ?? 0;
    $dm = $stats['dm'] ?? 0;
    $afastados = $stats['afastados'] ?? 0;
    $totalRespondido = $stats['total_respondido'] ?? 0;

    $taxaPresenca = $totalRespondido > 0 ? round(($presentes / $totalRespondido) * 100, 1) : 0;

    // 3. Detalhamento por Seção (Apenas Expediente)
    $stmtSecoes = $db->prepare("
        SELECT 
            s.`$secCol` as secao,
            COUNT(u.id) as total_secao,
            SUM(CASE WHEN p.status IN ('P', 'EA', 'HO', 'O') THEN 1 ELSE 0 END) as presentes_secao,
            SUM(CASE WHEN p.status IN ('A', 'PA', 'PB') THEN 1 ELSE 0 END) as ausentes_secao,
            SUM(CASE WHEN p.status = 'F' THEN 1 ELSE 0 END) as ferias_secao,
            SUM(CASE WHEN p.status IN ('DM', 'INS', 'LPM', 'D', 'DP') THEN 1 ELSE 0 END) as dm_secao,
            SUM(CASE WHEN p.status IN ('C', 'M') THEN 1 ELSE 0 END) as afastados_secao
        FROM users u
        JOIN `$secTable` s ON u.section_id = s.id
        LEFT JOIN presencas p ON u.id = p.militar_id AND p.data = ?
        WHERE u.deleted_at IS NULL 
          $filterExpediente
        GROUP BY u.section_id, secao
        ORDER BY secao ASC
    ");
    $stmtSecoes->execute([$selectedDate]);
    $secoesData = $stmtSecoes->fetchAll();

    // 4. Militares Afastados / Condições Especiais Hoje (Apenas Expediente)
    $stmtAfastados = $db->prepare("
        SELECT 
            u.id, u.name, u.war_name, u.grade, u.saram,
            s.`$secCol` as secao, 
            p.status
        FROM users u
        JOIN presencas p ON u.id = p.militar_id
        JOIN `$secTable` s ON u.section_id = s.id
        WHERE p.data = ? 
          AND p.status NOT IN ('P', 'EA', 'HO', 'O')
          AND u.deleted_at IS NULL 
          $filterExpediente
        ORDER BY p.status ASC, secao ASC, u.name ASC
    ");
    $stmtAfastados->execute([$selectedDate]);
    $listaAfastados = $stmtAfastados->fetchAll();

    // 5. Prazos Críticos & Próximas Entregas
    $stmtPrazos = $db->query("
        SELECT t.*, 
               COALESCE(s.`$secCol`, 'Geral') as secao_nome
        FROM tarefas_prazos t
        LEFT JOIN `$secTable` s ON t.secao_id = s.id
        WHERE t.status IN ('pendente', 'em_andamento')
        ORDER BY (t.data_limite < CURDATE()) DESC, t.data_limite ASC, (t.prioridade = 'critica') DESC
        LIMIT 6
    ");
    $prazosCriticos = $stmtPrazos->fetchAll();

} catch (PDOException $e) {
    die("Erro ao calcular indicadores: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel da Chefia - Controle de Efetivo DTCEA-SJ</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .prazos-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .prazo-item {
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            transition: all 0.2s;
        }
        .prazo-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.06);
            background: #FFFFFF;
        }
        .prazo-item.critica { border-left-color: var(--danger); }
        .prazo-item.alta { border-left-color: var(--warning); }
        .prazo-item.media { border-left-color: var(--info); }
        .prazo-item.baixa { border-left-color: var(--success); }
        .prazo-item.atrasada { background-color: #FFF5F5; border-left-color: var(--danger); }

        .prazo-badge-rec {
            font-size: 0.72rem;
            background: #F3E8FF;
            color: #7E22CE;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
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
            <?php if ($user['perfil'] === 'admin'): ?>
                <a href="index.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Lançar Chamada
                </a>
            <?php endif; ?>
            <a href="dashboard.php" class="nav-link active">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2a2 2 0 00-2 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Painel da Chefia
            </a>
            <a href="tarefas.php" class="nav-link">
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

    <div class="container">
        <!-- Cabeçalho da Página -->
        <div class="page-header">
            <div class="page-title">
                <h2>Painel do Efetivo</h2>
                <p>Análise consolidada e taxas de presença do efetivo militar do expediente por seção.</p>
            </div>
            <form action="dashboard.php" method="GET" class="date-selector">
                <label for="dateInput">Visualizar Data:</label>
                <input type="date" name="date" id="dateInput" class="date-input" value="<?= $selectedDate ?>" onchange="this.form.submit()">
            </form>
        </div>

        <!-- Bento Grid de Estatísticas Rápidas -->
        <div class="dashboard-grid">
            <div class="stat-card primary">
                <div class="stat-label">Efetivo do Expediente</div>
                <div class="stat-value"><?= $totalEfetivo ?></div>
                <div class="stat-footer">Militares do expediente ativos</div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Disponíveis / Presentes</div>
                <div class="stat-value"><?= $presentes ?></div>
                <div class="stat-footer"><?= $totalRespondido ?> chamadas realizadas hoje</div>
            </div>
            <div class="stat-card info">
                <div class="stat-label">Taxa de Presença</div>
                <div class="stat-value"><?= $taxaPresenca ?>%</div>
                <div class="stat-footer">Relação Presentes/Respondidos</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">Férias / Dispensas</div>
                <div class="stat-value"><?= $ferias + $dm ?></div>
                <div class="stat-footer"><?= $ferias ?> Férias, <?= $dm ?> Disp. Médica</div>
            </div>
        </div>

        <!-- Layout de Colunas do Dashboard -->
        <div class="dashboard-layout">
            
            <!-- Coluna Esquerda: Resumo por Seção -->
            <div class="section-card" style="margin-bottom: 0;">
                <div class="section-title">
                    <span>Distribuição de Presença por Seção</span>
                    <span class="section-badge"><?= count($secoesData) ?> Seções</span>
                </div>
                <div class="table-responsive">
                    <table class="efetivo-table dashboard-table">
                        <thead>
                            <tr>
                                <th>Seção</th>
                                <th style="text-align: center;">Efetivo</th>
                                <th style="text-align: center;">Pres.</th>
                                <th style="text-align: center;">Aus.</th>
                                <th style="text-align: center;">Férias</th>
                                <th style="text-align: center;">Afast.</th>
                                <th>Disponibilidade (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($secoesData as $sd): 
                                $secaoTotal = $sd['total_secao'];
                                $secaoPresente = $sd['presentes_secao'];
                                $percentualSecao = $secaoTotal > 0 ? round(($secaoPresente / $secaoTotal) * 100) : 0;
                            ?>
                                <tr>
                                    <td><strong><?= $sd['secao'] ?></strong></td>
                                    <td style="text-align: center;"><?= $secaoTotal ?></td>
                                    <td style="text-align: center;" class="color-success"><?= $sd['presentes_secao'] ?></td>
                                    <td style="text-align: center;" class="color-danger"><?= $sd['ausentes_secao'] ?></td>
                                    <td style="text-align: center;"><?= $sd['ferias_secao'] ?></td>
                                    <td style="text-align: center;"><?= $sd['dm_secao'] + $sd['afastados_secao'] ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="font-weight: 700; width: 40px; text-align: right;"><?= $percentualSecao ?>%</span>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar" style="width: <?= $percentualSecao ?>%; background-color: <?= $percentualSecao > 75 ? 'var(--success)' : ($percentualSecao > 40 ? 'var(--warning)' : 'var(--danger)') ?>;"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Coluna Direita: Condições Especiais / Ausências -->
            <div class="section-card" style="margin-bottom: 0;">
                <div class="section-title">
                    <span>Afastamentos e Situações Especiais</span>
                    <span class="section-badge" style="background-color: var(--danger); color: white;"><?= count($listaAfastados) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="efetivo-table">
                        <thead>
                            <tr>
                                <th>Militar</th>
                                <th>Seção</th>
                                <th>Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($listaAfastados) === 0): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                        Nenhum militar afastado nesta data.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($listaAfastados as $la): ?>
                                    <tr>
                                        <td><strong><?= formatarNomeMilitar($la) ?></strong></td>
                                        <td><?= $la['secao'] ?></td>
                                        <td>
                                            <span class="status-badge <?= strtolower($la['status']) ?>">
                                                <?= $statusList[$la['status']] ?? $la['status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bloco Prazos Críticos & Próximas Entregas -->
            <div class="section-card" style="margin-top: 25px; grid-column: 1 / -1;">
                <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <svg width="20" height="20" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M9 16l2 2 4-4"></path></svg>
                        <span>Prazos Críticos e Entregas</span>
                    </div>
                    <a href="tarefas.php" style="font-size: 0.85rem; font-weight: 700; color: var(--primary); text-decoration: underline;">Gerenciar Todas as Entregas &rarr;</a>
                </div>
                
                <?php if (empty($prazosCriticos)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 25px 0; margin: 0;">Nenhuma entrega pendente cadastrada no momento.</p>
                <?php else: ?>
                    <div class="prazos-list">
                        <?php 
                        $hojeDt = new DateTime(date('Y-m-d'));
                        foreach ($prazosCriticos as $pz): 
                            $dtPrazo = new DateTime($pz['data_limite']);
                            $diffPz = $hojeDt->diff($dtPrazo);
                            $diasRestPz = (int)$diffPz->format('%r%a');
                            $isAtrasadaPz = ($diasRestPz < 0);
                            $classeItem = $isAtrasadaPz ? 'atrasada' : ($pz['prioridade'] ?? 'media');
                        ?>
                            <div class="prazo-item <?= $classeItem ?>">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <strong style="font-size: 1rem; color: var(--primary);"><?= htmlspecialchars($pz['titulo']) ?></strong>
                                        <span class="status-badge" style="background: #EDF2F7; color: #4A5568; font-size: 0.72rem;"><?= htmlspecialchars($pz['categoria'] ?? 'Geral') ?></span>
                                        <?php if (!empty($pz['is_periodica'])): ?>
                                            <span class="prazo-badge-rec">
                                                🔄 <?= ucfirst($pz['periodicidade']) ?><?= !empty($pz['dia_lembrete']) ? ' - ' . htmlspecialchars($pz['dia_lembrete']) : '' ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="status-badge <?= $pz['prioridade'] === 'critica' ? 'dm' : ($pz['prioridade'] === 'alta' ? 'sv' : 'p') ?>" style="font-size: 0.72rem; text-transform: uppercase;">
                                            <?= $pz['prioridade'] ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($pz['descricao'])): ?>
                                        <div style="font-size: 0.85rem; color: #4A5568;"><?= htmlspecialchars($pz['descricao']) ?></div>
                                    <?php endif; ?>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                        Seção: <strong><?= htmlspecialchars($pz['secao_nome']) ?></strong>
                                    </div>
                                </div>
                                <div style="text-align: right; min-width: 140px;">
                                    <div style="font-weight: 700; font-size: 0.92rem;">
                                        <?= date('d/m/Y', strtotime($pz['data_limite'])) ?>
                                    </div>
                                    <div style="margin-top: 4px;">
                                        <?php if ($isAtrasadaPz): ?>
                                            <span style="background: #FEE2E2; color: #DC2626; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 10px;">
                                                ⚠️ Atrasada (<?= abs($diasRestPz) ?>d)
                                            </span>
                                        <?php elseif ($diasRestPz === 0): ?>
                                            <span style="background: #FEF3C7; color: #B45309; font-size: 0.75rem; font-weight: 800; padding: 2px 8px; border-radius: 10px;">
                                                🔥 Vence Hoje!
                                            </span>
                                        <?php elseif ($diasRestPz <= 3): ?>
                                            <span style="background: #FFEDD5; color: #EA580C; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 10px;">
                                                ⏳ Vence em <?= $diasRestPz ?>d
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #E2E8F0; color: #475569; font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 10px;">
                                                📅 Em <?= $diasRestPz ?> dias
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</body>
</html>
