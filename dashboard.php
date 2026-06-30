<?php
// dashboard.php
// Painel de Chefia - Indicadores de Presença e Estatísticas por Seção

require_once __DIR__ . '/auth.php';
requireChefia(); // Apenas perfil chefia pode visualizar esta página

$user = getCurrentUser();
$selectedDate = sanitize($_GET['date'] ?? date('Y-m-d'));

try {
    // 1. Total do Efetivo Geral
    $stmtGeral = $db->query("SELECT COUNT(*) as total FROM militares");
    $totalEfetivo = $stmtGeral->fetch()['total'];

    // 2. Presença Geral do dia Selecionado
    $stmtPresenca = $db->prepare("
        SELECT 
            SUM(CASE WHEN status IN ('P', 'EA', 'HO', 'O') THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN status IN ('A', 'PA', 'PB') THEN 1 ELSE 0 END) as ausentes,
            SUM(CASE WHEN status = 'F' THEN 1 ELSE 0 END) as ferias,
            SUM(CASE WHEN status IN ('DM', 'INS', 'LPM', 'D', 'DP') THEN 1 ELSE 0 END) as dm,
            SUM(CASE WHEN status IN ('C', 'M') THEN 1 ELSE 0 END) as afastados, -- Curso/Missão
            SUM(CASE WHEN status IS NOT NULL THEN 1 ELSE 0 END) as total_respondido
        FROM presencas 
        WHERE data = ?
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

    // 3. Detalhamento por Seção
    // Vamos trazer a lista de militares agrupando por seção e calculando os totais
    $stmtSecoes = $db->prepare("
        SELECT 
            m.secao,
            COUNT(m.id) as total_secao,
            SUM(CASE WHEN p.status IN ('P', 'EA', 'HO', 'O') THEN 1 ELSE 0 END) as presentes_secao,
            SUM(CASE WHEN p.status IN ('A', 'PA', 'PB') THEN 1 ELSE 0 END) as ausentes_secao,
            SUM(CASE WHEN p.status = 'F' THEN 1 ELSE 0 END) as ferias_secao,
            SUM(CASE WHEN p.status IN ('DM', 'INS', 'LPM', 'D', 'DP') THEN 1 ELSE 0 END) as dm_secao,
            SUM(CASE WHEN p.status IN ('C', 'M') THEN 1 ELSE 0 END) as afastados_secao
        FROM militares m
        LEFT JOIN presencas p ON m.id = p.militar_id AND p.data = ?
        GROUP BY m.secao
        ORDER BY m.secao ASC
    ");
    $stmtSecoes->execute([$selectedDate]);
    $secoesData = $stmtSecoes->fetchAll();

    // 4. Militares Afastados / Condições Especiais Hoje
    $stmtAfastados = $db->prepare("
        SELECT m.nome, m.secao, p.status
        FROM militares m
        JOIN presencas p ON m.id = p.militar_id
        WHERE p.data = ? AND p.status NOT IN ('P', 'EA', 'HO', 'O')
        ORDER BY p.status ASC, m.secao ASC, m.nome ASC
    ");
    $stmtAfastados->execute([$selectedDate]);
    $listaAfastados = $stmtAfastados->fetchAll();

} catch (PDOException $e) {
    die("Erro ao calcular indicadores: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Painel da Chefia - Controle de Efetivo DTCEA-SJ</title>
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
            <a href="dashboard.php" class="nav-link active">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2a2 2 0 00-2 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Painel da Chefia
            </a>
            <?php if ($user['perfil'] === 'admin'): ?>
                <a href="admin.php" class="nav-link">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    Administração
                </a>
            <?php endif; ?>
            
            <div class="nav-user">
                <span>Olá, <strong><?= $user['nome'] ?></strong></span>
                <span class="badge-profile"><?= $user['perfil'] ?></span>
            </div>
            <a href="logout.php" class="btn-logout">Sair</a>
        </nav>
    </header>

    <div class="container">
        <!-- Cabeçalho da Página -->
        <div class="page-header">
            <div class="page-title">
                <h2>Painel Estratégico do Efetivo</h2>
                <p>Análise consolidada e taxas de presença do efetivo militar por seção.</p>
            </div>
            <form action="dashboard.php" method="GET" class="date-selector">
                <label for="dateInput">Visualizar Data:</label>
                <input type="date" name="date" id="dateInput" class="date-input" value="<?= $selectedDate ?>" onchange="this.form.submit()">
            </form>
        </div>

        <!-- Bento Grid de Estatísticas Rápidas -->
        <div class="dashboard-grid">
            <div class="stat-card primary">
                <div class="stat-label">Efetivo Cadastrado</div>
                <div class="stat-value"><?= $totalEfetivo ?></div>
                <div class="stat-footer">Militares ativos no sistema</div>
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
                    <span>Afastamentos & Situações Especiais</span>
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
                                        <td><strong><?= $la['nome'] ?></strong></td>
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

        </div>
    </div>
</body>
</html>
