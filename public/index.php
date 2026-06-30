<?php
// index.php
// Página Principal - Chamada Diária e Lançamento de Presença

require_once __DIR__ . '/auth.php';
requireLogin();

$user = getCurrentUser();
$selectedDate = sanitize($_GET['date'] ?? date('Y-m-d'));

// Buscar todos os militares com suas respectivas presenças no dia selecionado
try {
    if (!empty($user['secao'])) {
        $stmt = $db->prepare("
            SELECT m.*, p.status 
            FROM militares m 
            LEFT JOIN presencas p ON m.id = p.militar_id AND p.data = ?
            WHERE m.secao = ?
            ORDER BY m.escala ASC, m.secao ASC, m.id ASC
        ");
        $stmt->execute([$selectedDate, $user['secao']]);
    } else {
        $stmt = $db->prepare("
            SELECT m.*, p.status 
            FROM militares m 
            LEFT JOIN presencas p ON m.id = p.militar_id AND p.data = ?
            ORDER BY m.escala ASC, m.secao ASC, m.id ASC
        ");
        $stmt->execute([$selectedDate]);
    }
    $militares = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erro ao buscar dados do efetivo: " . $e->getMessage());
}

// Agrupar militares por seção
$secoes = [];
foreach ($militares as $m) {
    $secoes[$m['secao']][] = $m;
}

$errorMsg = sanitize($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Controle de Efetivo - DTCEA-SJ</title>
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
            <a href="index.php" class="nav-link active">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                Lançar Chamada
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
            </div>
            <a href="logout.php" class="btn-logout">Sair</a>
        </nav>
    </header>

    <div class="container">
        <?php if ($errorMsg): ?>
            <div class="alert">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <span><?= $errorMsg ?></span>
            </div>
        <?php endif; ?>

        <!-- Cabeçalho da Página -->
        <div class="page-header">
            <div class="page-title">
                <h2>Lançamento de Presença Diária</h2>
                <p>Selecione a data para lançar ou alterar a chamada do efetivo militar.</p>
            </div>
            <form action="index.php" method="GET" class="date-selector">
                <label for="dateInput">Data da Chamada:</label>
                <input type="date" name="date" id="dateInput" class="date-input" value="<?= $selectedDate ?>" onchange="this.form.submit()">
            </form>
        </div>

        <!-- Painel de Legendas -->
        <div class="legend-box">
            <h3>Legenda de Siglas de Presença</h3>
            <div class="legend-list">
                <div class="legend-item"><span class="legend-color p">P</span> Presente</div>
                <div class="legend-item"><span class="legend-color a">A</span> Ausente</div>
                <div class="legend-item"><span class="legend-color f">F</span> Férias</div>
                <div class="legend-item"><span class="legend-color dm">DM</span> Dispensa Médica</div>
                <div class="legend-item"><span class="legend-color sv">SV</span> Serviço</div>
                <div class="legend-item"><span class="legend-color c">C</span> Curso</div>
                <div class="legend-item"><span class="legend-color c">M</span> Missão</div>
                <div class="legend-item"><span class="legend-color p">HO</span> Home Office</div>
                <div class="legend-item"><span class="legend-color p">EA</span> Expediente Admin</div>
            </div>
        </div>

        <!-- Ferramenta de Lançamento de Período -->
        <div class="legend-box" style="margin-bottom: 25px;">
            <h3>Lançar Período de Indisponibilidade / Afastamento</h3>
            <form id="periodForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; align-items: end;">
                <div>
                    <label for="periodMilitar" style="display:block; font-size: 0.85rem; font-weight: 600; color: var(--primary); margin-bottom: 5px;">Militar</label>
                    <select id="periodMilitar" class="status-select" style="max-width: 100%; border-color: var(--border);" required>
                        <option value="">-- Selecione o Militar --</option>
                        <?php foreach ($militares as $membro): ?>
                            <option value="<?= $membro['id'] ?>"><?= formatarNomeMilitar($membro) ?> (<?= $membro['secao'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="periodStatus" style="display:block; font-size: 0.85rem; font-weight: 600; color: var(--primary); margin-bottom: 5px;">Situação / Motivo</label>
                    <select id="periodStatus" class="status-select" style="max-width: 100%; border-color: var(--border);" required>
                        <option value="">-- Selecione o Motivo --</option>
                        <?php foreach ($statusList as $sigla => $descricao): ?>
                            <option value="<?= $sigla ?>"><?= $sigla ?> - <?= $descricao ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="periodInicio" style="display:block; font-size: 0.85rem; font-weight: 600; color: var(--primary); margin-bottom: 5px;">Data de Início</label>
                    <input type="date" id="periodInicio" class="date-input" style="width: 100%; padding: 8px 12px;" required>
                </div>
                <div>
                    <label for="periodFim" style="display:block; font-size: 0.85rem; font-weight: 600; color: var(--primary); margin-bottom: 5px;">Data de Término</label>
                    <input type="date" id="periodFim" class="date-input" style="width: 100%; padding: 8px 12px;" required>
                </div>
                <div>
                    <button type="submit" id="btnLaunchPeriod" class="btn-primary" style="margin-top: 0; padding: 10px 15px; height: 42px;">Gravar Período</button>
                </div>
            </form>
        </div>

        <!-- Listagem do Efetivo por Seção -->
        <?php foreach ($secoes as $secao => $membros): ?>
            <div class="section-card">
                <div class="section-title">
                    <span>Seção: <?= $secao ?></span>
                    <span class="section-badge"><?= count($membros) ?> Militares</span>
                </div>
                <div class="table-responsive">
                    <table class="efetivo-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Nº</th>
                                <th>Posto / Graduação / Nome</th>
                                <th style="width: 250px;">Status de Presença</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($membros as $index => $membro): 
                                // Se for escala operacional e não tiver status ainda, o padrão sugerido pode ser 'O' (Operacional) ou '-'
                                $status = $membro['status'] ?? '';
                            ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong><?= formatarNomeMilitar($membro) ?></strong></td>
                                    <td>
                                        <select class="status-select" data-militar-id="<?= $membro['id'] ?>">
                                            <option value="" <?= $status === '' ? 'selected' : '' ?>>-- Selecionar --</option>
                                            <?php foreach ($statusList as $sigla => $descricao): ?>
                                                <option value="<?= $sigla ?>" <?= $status === $sigla ? 'selected' : '' ?>>
                                                    <?= $sigla ?> - <?= $descricao ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Botão de Ação Flutuante para Salvar -->
    <div class="floating-actions">
        <button id="saveCallBtn" class="btn-save-call">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
            Salvar Chamada
        </button>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
