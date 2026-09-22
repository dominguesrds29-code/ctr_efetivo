<?php
// admin.php
// Painel Administrativo do Sistema - Gestão de Efetivo e Usuários no Banco EfetivoSJ

require_once __DIR__ . '/auth.php';
requireAdmin(); // Apenas administradores podem acessar esta página

$user = getCurrentUser();
$successMsg = '';
$errorMsg = '';
$secTable = getSecoesTableName($db);

// Processar formulários POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');

    // --- MILITARES: EDITAR SEÇÃO E ESCALA ---
    if ($action === 'edit_militar') {
        $id = (int)($_POST['id'] ?? 0);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $escala = (int)($_POST['escala'] ?? 0);

        if ($id > 0 && $sectionId > 0) {
            try {
                $stmt = $db->prepare("UPDATE users SET section_id = ?, escala = ? WHERE id = ?");
                $stmt->execute([$sectionId, $escala, $id]);
                $successMsg = "Dados do militar atualizados com sucesso!";
            } catch (PDOException $e) {
                $errorMsg = "Erro ao atualizar militar: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Por favor, selecione uma seção válida para o militar.";
        }
    }

    // --- USUÁRIOS: CRIAR NOVO USUÁRIO ---
    elseif ($action === 'add_user') {
        $saram = sanitize($_POST['saram'] ?? '');
        $nome = sanitize($_POST['nome'] ?? '');
        $warName = sanitize($_POST['war_name'] ?? '');
        $grade = sanitize($_POST['grade'] ?? 'SO');
        $email = sanitize($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $perfil = sanitize($_POST['perfil'] ?? 'encarregado');
        $sectionId = (int)($_POST['section_id'] ?? 1);
        $escala = (int)($_POST['escala'] ?? 0);

        $isAdmin = ($perfil === 'admin') ? 1 : (($perfil === 'chefia') ? 2 : 0);

        if (empty($email) && !empty($saram)) {
            $email = $saram . '@fab.mil.br';
        }

        if (!empty($saram) && !empty($nome) && !empty($senha)) {
            try {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO users (saram, name, war_name, grade, section_id, email, password, is_admin, escala, person_type, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'MILITAR', NOW(), NOW())
                ");
                $stmt->execute([$saram, $nome, $warName, $grade, $sectionId, $email, $hash, $isAdmin, $escala]);
                $successMsg = "Militar/Usuário '$nome' cadastrado com sucesso!";
            } catch (Exception $e) {
                $errorMsg = "Erro ao cadastrar usuário: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Preencha todos os campos obrigatórios (SARAM, Nome, Senha).";
        }
    } 

    // --- USUÁRIOS: EDITAR DADOS ---
    elseif ($action === 'edit_user') {
        $id = (int)($_POST['id'] ?? 0);
        $saram = sanitize($_POST['saram'] ?? '');
        $nome = sanitize($_POST['nome'] ?? '');
        $warName = sanitize($_POST['war_name'] ?? '');
        $grade = sanitize($_POST['grade'] ?? 'SO');
        $email = sanitize($_POST['email'] ?? '');
        $perfil = sanitize($_POST['perfil'] ?? 'encarregado');
        $sectionId = (int)($_POST['section_id'] ?? 1);
        $escala = (int)($_POST['escala'] ?? 0);

        $isAdmin = ($perfil === 'admin') ? 1 : (($perfil === 'chefia') ? 2 : 0);

        if ($id > 0 && !empty($saram) && !empty($nome)) {
            try {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET saram = ?, name = ?, war_name = ?, grade = ?, section_id = ?, email = ?, is_admin = ?, escala = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$saram, $nome, $warName, $grade, $sectionId, $email, $isAdmin, $escala, $id]);
                $successMsg = "Dados de '$nome' atualizados com sucesso!";
            } catch (Exception $e) {
                $errorMsg = "Erro ao atualizar usuário: " . $e->getMessage();
            }
        }
    } 

    // --- USUÁRIOS: ALTERAR SENHA ---
    elseif ($action === 'change_password') {
        $id = (int)($_POST['id'] ?? 0);
        $novaSenha = $_POST['nova_senha'] ?? '';

        if ($id > 0 && !empty($novaSenha)) {
            try {
                $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hash, $id]);
                $successMsg = "Senha do usuário alterada com sucesso!";
            } catch (PDOException $e) {
                $errorMsg = "Erro ao alterar senha: " . $e->getMessage();
            }
        } else {
            $errorMsg = "A senha não pode ser vazia.";
        }
    } 

    // --- USUÁRIOS: EXCLUIR / DESATIVAR ---
    elseif ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$user['id']) {
            $errorMsg = "Você não pode excluir a si mesmo.";
        } elseif ($id > 0) {
            try {
                // Soft delete
                $stmt = $db->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                $successMsg = "Militar/Usuário desativado com sucesso!";
            } catch (PDOException $e) {
                $errorMsg = "Erro ao desativar usuário: " . $e->getMessage();
            }
        }
    }
}

// Carregar listas para exibição
try {
    $militares = $db->query("
        SELECT u.*, 
               COALESCE(s.sigla, s.nome, 'Sem Seção') AS secao_nome 
        FROM users u 
        LEFT JOIN $secTable s ON u.section_id = s.id 
        WHERE u.deleted_at IS NULL
        ORDER BY secao_nome ASC, u.name ASC
    ")->fetchAll();

    $secoesList = $db->query("SELECT id, sigla, nome FROM $secTable ORDER BY sigla ASC")->fetchAll();
} catch (PDOException $e) {
    die("Erro ao ler dados do banco: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=device-width, initial-scale=1.0">
    <title>Administração - Controle de Efetivo DTCEA-SJ</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border);
            padding-bottom: 10px;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background-color: var(--primary);
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .admin-flex {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        @media (max-width: 900px) {
            .admin-flex {
                grid-template-columns: 1fr;
            }
        }
        .badge-admin-profile {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
            text-transform: uppercase;
        }
        .badge-admin-profile.admin { background-color: var(--danger); color: white; }
        .badge-admin-profile.chefia { background-color: var(--info); color: white; }
        .badge-admin-profile.encarregado { background-color: var(--success); color: white; }
        
        .action-btn-group {
            display: flex;
            gap: 8px;
        }
        .btn-action-small {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-edit { background-color: var(--info); color: white; }
        .btn-delete { background-color: var(--danger); color: white; }
        .btn-pass { background-color: var(--warning); color: white; }
        .btn-action-small:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Badges de Seção e Escala */
        .badge-secao {
            display: inline-block;
            padding: 4px 10px;
            background-color: #EDF2F7;
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        .badge-escala {
            display: inline-block;
            padding: 4px 12px;
            font-size: 0.82rem;
            font-weight: 600;
            border-radius: 20px;
        }
        .badge-escala.escala-operacional {
            background-color: #EBF8FF;
            color: #2B6CB0;
            border: 1px solid #BEE3F8;
        }
        .badge-escala.escala-expediente {
            background-color: #F7FAFC;
            color: #4A5568;
            border: 1px solid #E2E8F0;
        }

        /* Modal de Edição de Militar */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 20, 50, 0.55);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
            animation: fadeInModal 0.2s ease-out;
        }
        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-dialog {
            background: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 480px;
            border-top: 5px solid var(--primary);
            overflow: hidden;
            animation: slideDownModal 0.25s ease-out;
        }
        @keyframes slideDownModal {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            background-color: #F8FAFC;
        }
        .modal-close-btn {
            background: none;
            border: none;
            font-size: 1.6rem;
            color: var(--text-muted);
            cursor: pointer;
            line-height: 1;
            padding: 2px 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .modal-close-btn:hover {
            color: var(--danger);
            background-color: #FEE2E2;
        }
        .modal-body {
            padding: 24px;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
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
            <a href="index.php" class="nav-link">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                Lançar Chamada
            </a>
            <a href="dashboard.php" class="nav-link">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2a2 2 0 00-2 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Painel da Chefia
            </a>
            <a href="admin.php" class="nav-link active">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                Administração
            </a>
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

    <div class="container">
        <!-- Notificações -->
        <?php if ($successMsg): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= $successMsg ?></span>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <span><?= $errorMsg ?></span>
            </div>
        <?php endif; ?>

        <!-- Cabeçalho -->
        <div class="page-header">
            <div class="page-title">
                <h2>Painel de Controle e Administração</h2>
                <p>Gerencie o efetivo do DTCEA-SJ, atribuições de seções, escalas e permissões.</p>
            </div>
        </div>

        <!-- Abas -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab(this, 'tab-efetivo')">Efetivo Militar (<?= count($militares) ?>)</button>
            <button class="tab-btn" onclick="switchTab(this, 'tab-usuarios')">Cadastrar / Editar Usuário</button>
        </div>

        <!-- CONTEÚDO 1: EFETIVO MILITAR -->
        <div id="tab-efetivo" class="tab-content active">
            <div class="section-card" style="margin-bottom: 0;">
                <div class="section-title">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span>Militares Cadastrados</span>
                        <span class="section-badge" id="militarBadgeCount"><?= count($militares) ?> Totais</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="text" id="filtroMilitar" placeholder="Buscar por nome, SARAM ou seção..." 
                               style="padding: 7px 14px; font-size: 0.85rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.15); color: white; outline: none; width: 280px; font-family: inherit;"
                               oninput="filtrarTabelaMilitares()">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="efetivo-table" id="tabelaMilitares">
                        <thead>
                            <tr>
                                <th style="width: 15%;">SARAM</th>
                                <th style="width: 35%;">Posto / Grad / Nome</th>
                                <th style="width: 20%;">Seção</th>
                                <th style="width: 15%;">Escala</th>
                                <th style="width: 15%; text-align: center;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($militares as $m): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($m['saram'] ?? '-') ?></strong></td>
                                    <td>
                                        <strong style="color: var(--primary-dark); font-size: 0.95rem;"><?= formatarNomeMilitar($m) ?></strong>
                                        <?php if (!empty($m['name']) && $m['name'] !== formatarNomeMilitar($m)): ?>
                                            <div style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($m['name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-secao"><?= htmlspecialchars($m['secao_nome']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($m['escala'] == 1): ?>
                                            <span class="badge-escala escala-operacional">Operacional</span>
                                        <?php else: ?>
                                            <span class="badge-escala escala-expediente">Expediente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="action-btn-group" style="justify-content: center;">
                                            <button type="button" class="btn-action-small btn-edit" onclick="editMilitar(<?= $m['id'] ?>, '<?= addslashes(formatarNomeMilitar($m)) ?>', <?= (int)$m['section_id'] ?>, <?= (int)$m['escala'] ?>)">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                Seção/Escala
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL: EDITAR SEÇÃO E ESCALA DO MILITAR -->
        <div class="modal-overlay" id="boxMilitar" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-header">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: rgba(0, 47, 108, 0.1); padding: 8px; border-radius: 8px; color: var(--primary);">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </div>
                        <h3 id="formMilitarTitle" style="margin: 0; font-size: 1.15rem; color: var(--primary);">Definir Seção e Escala</h3>
                    </div>
                    <button type="button" class="modal-close-btn" id="btnCloseModalMilitar" aria-label="Fechar">&times;</button>
                </div>
                <form action="admin.php" method="POST" id="formMilitar" class="modal-body">
                    <input type="hidden" name="action" id="militarAction" value="edit_militar">
                    <input type="hidden" name="id" id="militarId" value="">

                    <div class="form-group">
                        <label for="mNome" style="font-weight: 600; color: var(--text); font-size: 0.9rem;">Militar</label>
                        <input type="text" name="nome" id="mNome" class="form-input" readonly style="background-color: var(--border); font-weight: 600; color: var(--primary-dark);">
                    </div>

                    <div class="form-group">
                        <label for="mSecaoId" style="font-weight: 600; color: var(--text); font-size: 0.9rem;">Seção</label>
                        <select name="section_id" id="mSecaoId" class="form-input" style="padding: 10px;" required>
                            <?php foreach ($secoesList as $sec): ?>
                                <option value="<?= $sec['id'] ?>"><?= htmlspecialchars($sec['sigla']) ?> - <?= htmlspecialchars($sec['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="mEscala" style="font-weight: 600; color: var(--text); font-size: 0.9rem;">Tipo de Escala</label>
                        <select name="escala" id="mEscala" class="form-input" style="padding: 10px;" required>
                            <option value="0">Expediente Administrativo</option>
                            <option value="1">Escala Operacional (TWR / AIS / EMS)</option>
                        </select>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-logout" id="btnCancelEditMilitar" style="flex: 1; color: var(--text); border: 1px solid var(--border); background: #F8FAFC;">Cancelar</button>
                        <button type="submit" class="btn-primary" id="btnSubmitMilitar" style="flex: 1;">Gravar Alterações</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- CONTEÚDO 2: USUÁRIOS E GESTÃO COMPLETA -->
        <div id="tab-usuarios" class="tab-content">
            <div class="admin-flex">
                <!-- Coluna Esquerda: Cadastro / Edição -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div class="legend-box" style="height: fit-content; margin-bottom: 0;">
                        <h3 id="formUserTitle">Cadastrar Novo Usuário / Militar</h3>
                        <form action="admin.php" method="POST" id="formUser" style="margin-top: 15px;">
                            <input type="hidden" name="action" id="userAction" value="add_user">
                            <input type="hidden" name="id" id="userId" value="">

                            <div class="form-group">
                                <label for="uSaram">SARAM</label>
                                <input type="text" name="saram" id="uSaram" class="form-input" placeholder="Ex: 393.068-8" required autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label for="uNome">Nome Completo</label>
                                <input type="text" name="nome" id="uNome" class="form-input" placeholder="Ex: JOAO DA SILVA" required autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label for="uWarName">Nome de Guerra</label>
                                <input type="text" name="war_name" id="uWarName" class="form-input" placeholder="Ex: SILVA" autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label for="uGrade">Posto / Graduação</label>
                                <input type="text" name="grade" id="uGrade" class="form-input" placeholder="Ex: SO, 1S, 2S, 3S, CB, S1, S2, CAP..." required>
                            </div>

                            <div class="form-group">
                                <label for="uEmail">E-mail</label>
                                <input type="email" name="email" id="uEmail" class="form-input" placeholder="Ex: saram@fab.mil.br" autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label for="uSecao">Seção</label>
                                <select name="section_id" id="uSecao" class="form-input" style="padding: 10px;" required>
                                    <?php foreach ($secoesList as $sec): ?>
                                        <option value="<?= $sec['id'] ?>"><?= htmlspecialchars($sec['sigla']) ?> - <?= htmlspecialchars($sec['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="uPerfil">Perfil de Acesso</label>
                                <select name="perfil" id="uPerfil" class="form-input" style="padding: 10px;" required>
                                    <option value="encarregado">Encarregado (Lançar chamada de sua seção)</option>
                                    <option value="chefia">Chefia (Visualizar Painel e Indicadores)</option>
                                    <option value="admin">Administrador (Acesso total)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="uUserEscala">Tipo de Escala</label>
                                <select name="escala" id="uUserEscala" class="form-input" style="padding: 10px;" required>
                                    <option value="0">Expediente Administrativo</option>
                                    <option value="1">Escala Operacional (TWR / AIS / EMS)</option>
                                </select>
                            </div>

                            <div class="form-group" id="senhaGroup">
                                <label for="uSenha">Senha de Acesso</label>
                                <input type="password" name="senha" id="uSenha" class="form-input" placeholder="Defina a senha inicial" required>
                            </div>

                            <button type="submit" class="btn-primary" id="btnSubmitUser">Salvar Cadastro</button>
                            <button type="button" class="btn-logout" id="btnCancelEditUser" style="display: none; width: 100%; margin-top: 10px; color: var(--text)">Cancelar Edição</button>
                        </form>
                    </div>

                    <!-- Alteração de Senha -->
                    <div class="legend-box" id="passChangeBox" style="height: fit-content; display: none;">
                        <h3>Redefinir Senha</h3>
                        <form action="admin.php" method="POST" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="id" id="passUserId" value="">
                            
                            <div class="form-group">
                                <label>Militar selecionado</label>
                                <input type="text" id="passUserDisplay" class="form-input" style="background-color: var(--border);" readonly>
                            </div>

                            <div class="form-group">
                                <label for="newPass">Nova Senha</label>
                                <input type="password" name="nova_senha" id="newPass" class="form-input" placeholder="Digite a nova senha" required minlength="6">
                            </div>

                            <button type="submit" class="btn-primary">Gravar Nova Senha</button>
                            <button type="button" class="btn-logout" onclick="closePasswordBox()" style="width: 100%; margin-top: 10px; color: var(--text)">Cancelar</button>
                        </form>
                    </div>
                </div>

                <!-- Coluna Direita: Listagem e Ações -->
                <div class="section-card" style="margin-bottom: 0;">
                    <div class="section-title">
                        <span>Usuários / Militares Registrados</span>
                        <span class="section-badge"><?= count($militares) ?> Contas</span>
                    </div>
                    <div class="table-responsive">
                        <table class="efetivo-table">
                            <thead>
                                <tr>
                                    <th>Militar / SARAM</th>
                                    <th>Perfil</th>
                                    <th>Seção</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($militares as $u): 
                                    $perf = ($u['is_admin'] == 1) ? 'admin' : (($u['is_admin'] == 2) ? 'chefia' : 'encarregado');
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= formatarNomeMilitar($u) ?></strong><br>
                                            <small style="color: var(--text-muted);"><?= $u['saram'] ?> | <?= $u['email'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge-admin-profile <?= $perf ?>"><?= $perf ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($u['secao_nome']) ?></td>
                                        <td>
                                            <div class="action-btn-group">
                                                <button class="btn-action-small btn-edit" onclick="editUser(<?= $u['id'] ?>, '<?= addslashes($u['saram'] ?? '') ?>', '<?= addslashes($u['name'] ?? '') ?>', '<?= addslashes($u['war_name'] ?? '') ?>', '<?= addslashes($u['grade'] ?? '') ?>', '<?= addslashes($u['email'] ?? '') ?>', <?= (int)$u['section_id'] ?>, '<?= $perf ?>', <?= (int)$u['escala'] ?>)">Editar</button>
                                                <button class="btn-action-small btn-pass" onclick="openPasswordBox(<?= $u['id'] ?>, '<?= addslashes(formatarNomeMilitar($u)) ?>')">Senha</button>
                                                <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                                    <form action="admin.php" method="POST" onsubmit="return confirm('Deseja realmente desativar este militar?');" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="btn-action-small btn-delete">Desativar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Alternador de Abas
        function switchTab(btn, tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        // Funções do Modal de Edição de Militar
        function editMilitar(id, nome, sectionId, escala) {
            const modal = document.getElementById('boxMilitar');
            modal.style.display = 'flex';
            document.getElementById('militarId').value = id;
            document.getElementById('mNome').value = nome;
            document.getElementById('mSecaoId').value = sectionId;
            document.getElementById('mEscala').value = escala;
        }

        function closeEditMilitar() {
            document.getElementById('militarId').value = '';
            document.getElementById('formMilitar').reset();
            document.getElementById('boxMilitar').style.display = 'none';
        }

        document.getElementById('btnCancelEditMilitar').addEventListener('click', closeEditMilitar);
        const btnCloseModalMilitar = document.getElementById('btnCloseModalMilitar');
        if (btnCloseModalMilitar) btnCloseModalMilitar.addEventListener('click', closeEditMilitar);

        // Fechar modal ao clicar fora ou pressionar ESC
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('boxMilitar');
            if (e.target === modal) {
                closeEditMilitar();
            }
        });
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById('boxMilitar');
                if (modal && modal.style.display === 'flex') {
                    closeEditMilitar();
                }
            }
        });

        // Filtragem instantânea na tabela de militares
        function filtrarTabelaMilitares() {
            const input = document.getElementById('filtroMilitar').value.toUpperCase().trim();
            const trs = document.querySelectorAll('#tabelaMilitares tbody tr');
            let visiveis = 0;

            trs.forEach(tr => {
                const text = tr.innerText.toUpperCase();
                if (text.includes(input)) {
                    tr.style.display = '';
                    visiveis++;
                } else {
                    tr.style.display = 'none';
                }
            });

            const badge = document.getElementById('militarBadgeCount');
            if (badge) {
                badge.innerText = input ? `${visiveis} Encontrados` : '<?= count($militares) ?> Totais';
            }
        }

        // Funções do CRUD de Usuários
        function editUser(id, saram, nome, warName, grade, email, sectionId, perfil, escala) {
            document.querySelectorAll('.tab-btn')[1].click(); // Troca para aba de Usuários
            document.getElementById('userAction').value = 'edit_user';
            document.getElementById('userId').value = id;
            document.getElementById('uSaram').value = saram;
            document.getElementById('uNome').value = nome;
            document.getElementById('uWarName').value = warName;
            document.getElementById('uGrade').value = grade;
            document.getElementById('uEmail').value = email;
            document.getElementById('uSecao').value = sectionId;
            document.getElementById('uPerfil').value = perfil;
            document.getElementById('uUserEscala').value = escala;
            
            // Ocultar campo de senha ao editar dados básicos
            document.getElementById('senhaGroup').style.display = 'none';
            document.getElementById('uSenha').removeAttribute('required');
            
            document.getElementById('formUserTitle').innerText = 'Editar Usuário / Militar';
            document.getElementById('btnSubmitUser').innerText = 'Gravar Alterações';
            document.getElementById('btnCancelEditUser').style.display = 'block';
            
            document.getElementById('uSaram').focus();
        }

        document.getElementById('btnCancelEditUser').addEventListener('click', () => {
            document.getElementById('userAction').value = 'add_user';
            document.getElementById('userId').value = '';
            document.getElementById('formUser').reset();
            
            document.getElementById('senhaGroup').style.display = 'block';
            document.getElementById('uSenha').setAttribute('required', 'required');
            
            document.getElementById('formUserTitle').innerText = 'Cadastrar Novo Usuário / Militar';
            document.getElementById('btnSubmitUser').innerText = 'Salvar Cadastro';
            document.getElementById('btnCancelEditUser').style.display = 'none';
        });

        // Funções de Alteração de Senha
        function openPasswordBox(id, militarNome) {
            document.querySelectorAll('.tab-btn')[1].click();
            document.getElementById('passUserId').value = id;
            document.getElementById('passUserDisplay').value = militarNome;
            document.getElementById('newPass').value = '';
            document.getElementById('passChangeBox').style.display = 'block';
            document.getElementById('newPass').focus();
        }

        function closePasswordBox() {
            document.getElementById('passChangeBox').style.display = 'none';
        }
    </script>
</body>
</html>
