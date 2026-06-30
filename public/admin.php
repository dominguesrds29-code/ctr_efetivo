<?php
// admin.php
// Painel Administrativo do Sistema - CRUD de Militares e Usuários

require_once __DIR__ . '/auth.php';
requireAdmin(); // Apenas administradores podem acessar esta página

$user = getCurrentUser();
$successMsg = '';
$errorMsg = '';

// Processar formulários POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');

    // --- MILITARES CRUD ---
    if ($action === 'add_militar') {
        $nome = sanitize($_POST['nome'] ?? '');
        $secao = sanitize($_POST['secao'] ?? '');
        $escala = (int)($_POST['escala'] ?? 0);

        if (!empty($nome) && !empty($secao)) {
            try {
                // Validar se a seção existe no banco
                $stmtCheckSec = $db->prepare("SELECT COUNT(*) FROM secoes WHERE nome = ?");
                $stmtCheckSec->execute([$secao]);
                if ($stmtCheckSec->fetchColumn() == 0) {
                    $errorMsg = "A seção selecionada é inválida.";
                } else {
                    $stmt = $db->prepare("INSERT INTO militares (nome, secao, escala, posto_grad) VALUES (?, ?, ?, 'MILITAR')");
                    $stmt->execute([$nome, $secao, $escala]);
                    $successMsg = "Militar '$nome' cadastrado com sucesso!";
                }
            } catch (PDOException $e) {
                $errorMsg = "Erro ao cadastrar militar: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Preencha todos os campos obrigatórios do militar.";
        }
    } 
    elseif ($action === 'edit_militar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = sanitize($_POST['nome'] ?? '');
        $secao = sanitize($_POST['secao'] ?? '');
        $escala = (int)($_POST['escala'] ?? 0);

        if ($id > 0 && !empty($nome) && !empty($secao)) {
            try {
                // Validar se a seção existe no banco
                $stmtCheckSec = $db->prepare("SELECT COUNT(*) FROM secoes WHERE nome = ?");
                $stmtCheckSec->execute([$secao]);
                if ($stmtCheckSec->fetchColumn() == 0) {
                    $errorMsg = "A seção selecionada é inválida.";
                } else {
                    $stmt = $db->prepare("UPDATE militares SET nome = ?, secao = ?, escala = ? WHERE id = ?");
                    $stmt->execute([$nome, $secao, $escala, $id]);
                    $successMsg = "Militar '$nome' atualizado com sucesso!";
                }
            } catch (PDOException $e) {
                $errorMsg = "Erro ao atualizar militar: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Dados inválidos para edição do militar.";
        }
    } 
    elseif ($action === 'delete_militar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $db->beginTransaction();
                // Deletar registros de presença associados primeiro
                $stmtPres = $db->prepare("DELETE FROM presencas WHERE militar_id = ?");
                $stmtPres->execute([$id]);

                $stmtMil = $db->prepare("DELETE FROM militares WHERE id = ?");
                $stmtMil->execute([$id]);

                $db->commit();
                $successMsg = "Militar excluído com sucesso!";
            } catch (PDOException $e) {
                if ($db->inTransaction()) $db->rollBack();
                $errorMsg = "Erro ao excluir militar: " . $e->getMessage();
            }
        }
    }

    // --- USUÁRIOS CRUD ---
    elseif ($action === 'add_user') {
        $usuario = sanitize($_POST['usuario'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $nome = sanitize($_POST['nome'] ?? '');
        $perfil = sanitize($_POST['perfil'] ?? 'encarregado');
        $secao = sanitize($_POST['secao'] ?? null);

        if (empty($secao)) $secao = null;

        if (!empty($usuario) && !empty($senha) && !empty($nome)) {
            try {
                // Validar seção se for encarregado limitado
                if ($perfil === 'encarregado' && $secao !== null) {
                    $stmtCheckSec = $db->prepare("SELECT COUNT(*) FROM secoes WHERE nome = ?");
                    $stmtCheckSec->execute([$secao]);
                    if ($stmtCheckSec->fetchColumn() == 0) {
                        throw new Exception("A seção selecionada é inválida.");
                    }
                }
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO usuarios (usuario, senha_hash, nome, perfil, secao) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$usuario, $hash, $nome, $perfil, $secao]);
                $successMsg = "Usuário '$usuario' criado com sucesso!";
            } catch (Exception $e) {
                $errorMsg = "Erro ao criar usuário: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Preencha todos os campos obrigatórios do usuário.";
        }
    } 
    elseif ($action === 'edit_user') {
        $id = (int)($_POST['id'] ?? 0);
        $usuario = sanitize($_POST['usuario'] ?? '');
        $nome = sanitize($_POST['nome'] ?? '');
        $perfil = sanitize($_POST['perfil'] ?? 'encarregado');
        $secao = sanitize($_POST['secao'] ?? null);

        if (empty($secao)) $secao = null;

        if ($id > 0 && !empty($usuario) && !empty($nome)) {
            try {
                // Validar seção se for encarregado limitado
                if ($perfil === 'encarregado' && $secao !== null) {
                    $stmtCheckSec = $db->prepare("SELECT COUNT(*) FROM secoes WHERE nome = ?");
                    $stmtCheckSec->execute([$secao]);
                    if ($stmtCheckSec->fetchColumn() == 0) {
                        throw new Exception("A seção selecionada é inválida.");
                    }
                }
                $stmt = $db->prepare("UPDATE usuarios SET usuario = ?, nome = ?, perfil = ?, secao = ? WHERE id = ?");
                $stmt->execute([$usuario, $nome, $perfil, $secao, $id]);
                $successMsg = "Dados do usuário '$usuario' atualizados!";
            } catch (Exception $e) {
                $errorMsg = "Erro ao atualizar usuário: " . $e->getMessage();
            }
        }
    } 
    elseif ($action === 'change_password') {
        $id = (int)($_POST['id'] ?? 0);
        $novaSenha = $_POST['nova_senha'] ?? '';

        if ($id > 0 && !empty($novaSenha)) {
            try {
                $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
                $stmt->execute([$hash, $id]);
                $successMsg = "Senha do usuário alterada com sucesso!";
            } catch (PDOException $e) {
                $errorMsg = "Erro ao alterar senha: " . $e->getMessage();
            }
        } else {
            $errorMsg = "A senha não pode ser vazia.";
        }
    } 
    elseif ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$user['id']) {
            $errorMsg = "Você não pode excluir a si mesmo.";
        } elseif ($id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $successMsg = "Usuário excluído com sucesso!";
            } catch (PDOException $e) {
                $errorMsg = "Erro ao excluir usuário: " . $e->getMessage();
            }
        }
    }

    // --- SEÇÕES CRUD ---
    elseif ($action === 'add_secao') {
        $nome = strtoupper(sanitize($_POST['nome'] ?? ''));
        if (!empty($nome)) {
            try {
                $stmt = $db->prepare("INSERT INTO secoes (nome) VALUES (?)");
                $stmt->execute([$nome]);
                $successMsg = "Seção '$nome' cadastrada com sucesso!";
            } catch (PDOException $e) {
                $errorMsg = "Erro ao cadastrar seção (provavelmente já existe): " . $e->getMessage();
            }
        } else {
            $errorMsg = "O nome da seção não pode ser vazio.";
        }
    }
    elseif ($action === 'edit_secao') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = strtoupper(sanitize($_POST['nome'] ?? ''));

        if ($id > 0 && !empty($nome)) {
            try {
                $db->beginTransaction();
                // Pegar o nome antigo da seção
                $stmtOld = $db->prepare("SELECT nome FROM secoes WHERE id = ?");
                $stmtOld->execute([$id]);
                $oldName = $stmtOld->fetchColumn();

                if ($oldName) {
                    // Atualizar tabela de seções
                    $stmtUpdateSec = $db->prepare("UPDATE secoes SET nome = ? WHERE id = ?");
                    $stmtUpdateSec->execute([$nome, $id]);

                    // Atualizar militares vinculados
                    $stmtUpdateMil = $db->prepare("UPDATE militares SET secao = ? WHERE secao = ?");
                    $stmtUpdateMil->execute([$nome, $oldName]);

                    // Atualizar usuários vinculados
                    $stmtUpdateUsr = $db->prepare("UPDATE usuarios SET secao = ? WHERE secao = ?");
                    $stmtUpdateUsr->execute([$nome, $oldName]);

                    $db->commit();
                    $successMsg = "Seção renomeada de '$oldName' para '$nome' com sucesso!";
                } else {
                    $db->rollBack();
                    $errorMsg = "Seção não encontrada.";
                }
            } catch (PDOException $e) {
                if ($db->inTransaction()) $db->rollBack();
                $errorMsg = "Erro ao editar seção: " . $e->getMessage();
            }
        }
    }
    elseif ($action === 'delete_secao') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmtOld = $db->prepare("SELECT nome FROM secoes WHERE id = ?");
                $stmtOld->execute([$id]);
                $secName = $stmtOld->fetchColumn();

                if ($secName) {
                    // Verificar militares vinculados
                    $stmtCount = $db->prepare("SELECT COUNT(*) FROM militares WHERE secao = ?");
                    $stmtCount->execute([$secName]);
                    $countMil = $stmtCount->fetchColumn();

                    if ($countMil > 0) {
                        $errorMsg = "Não é possível excluir a seção '$secName' porque ela possui $countMil militares vinculados.";
                    } else {
                        $stmtDel = $db->prepare("DELETE FROM secoes WHERE id = ?");
                        $stmtDel->execute([$id]);
                        $successMsg = "Seção excluída com sucesso!";
                    }
                }
            } catch (PDOException $e) {
                $errorMsg = "Erro ao excluir seção: " . $e->getMessage();
            }
        }
    }
}

// Carregar listas para exibição
try {
    $militares = $db->query("SELECT * FROM militares ORDER BY secao ASC, nome ASC")->fetchAll();
    $usuarios = $db->query("SELECT * FROM usuarios ORDER BY perfil ASC, usuario ASC")->fetchAll();
    $secoesList = $db->query("SELECT * FROM secoes ORDER BY nome ASC")->fetchAll();
} catch (PDOException $e) {
    die("Erro ao ler dados: " . $e->getMessage());
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
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-edit { background-color: var(--info); color: white; }
        .btn-delete { background-color: var(--danger); color: white; }
        .btn-pass { background-color: var(--warning); color: white; }
        .btn-action-small:hover { opacity: 0.85; }
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
                <p>Gerencie o efetivo do DTCEA-SJ, crie e edite seções e contas de encarregados.</p>
            </div>
        </div>

        <!-- Abas -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab(this, 'tab-efetivo')">Efetivo Militar</button>
            <button class="tab-btn" onclick="switchTab(this, 'tab-secoes')">Gerenciar Seções</button>
            <button class="tab-btn" onclick="switchTab(this, 'tab-usuarios')">Usuários & Acessos</button>
        </div>

        <!-- CONTEÚDO 1: EFETIVO MILITAR -->
        <div id="tab-efetivo" class="tab-content active">
            <div class="admin-flex">
                <!-- Coluna Esquerda: Cadastro e Edição -->
                <div class="legend-box" style="height: fit-content;">
                    <h3 id="formMilitarTitle">Cadastrar Novo Militar</h3>
                    <form action="admin.php" method="POST" id="formMilitar" style="margin-top: 15px;">
                        <input type="hidden" name="action" id="militarAction" value="add_militar">
                        <input type="hidden" name="id" id="militarId" value="">

                        <div class="form-group">
                            <label for="mNome">Nome Completo</label>
                            <input type="text" name="nome" id="mNome" class="form-input" placeholder="Ex: S1 BORBONHA" required autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label for="mSecao">Seção</label>
                            <select name="secao" id="mSecao" class="form-input" style="padding: 10px;" required>
                                <option value="">-- Selecione a Seção --</option>
                                <?php foreach ($secoesList as $sec): ?>
                                    <option value="<?= $sec['nome'] ?>"><?= $sec['nome'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="mEscala">Tipo de Escala</label>
                            <select name="escala" id="mEscala" class="form-input" style="padding: 10px;" required>
                                <option value="0">Expediente Administrativo</option>
                                <option value="1">Escala Operacional (TWR/AIS/EMS)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-primary" id="btnSubmitMilitar">Cadastrar Militar</button>
                        <button type="button" class="btn-logout" id="btnCancelEditMilitar" style="display: none; width: 100%; margin-top: 10px; color: var(--text)">Cancelar Edição</button>
                    </form>
                </div>

                <!-- Coluna Direita: Listagem -->
                <div class="section-card" style="margin-bottom: 0;">
                    <div class="section-title">
                        <span>Militares Registrados</span>
                        <span class="section-badge"><?= count($militares) ?> Totais</span>
                    </div>
                    <div class="table-responsive">
                        <table class="efetivo-table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Seção</th>
                                    <th>Escala</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($militares as $m): ?>
                                    <tr>
                                        <td><strong><?= $m['nome'] ?></strong></td>
                                        <td><?= $m['secao'] ?></td>
                                        <td><?= $m['escala'] == 1 ? 'Operacional' : 'Expediente' ?></td>
                                        <td>
                                            <div class="action-btn-group">
                                                <button class="btn-action-small btn-edit" onclick="editMilitar(<?= $m['id'] ?>, '<?= addslashes($m['nome']) ?>', '<?= addslashes($m['secao']) ?>', <?= $m['escala'] ?>)">Editar</button>
                                                <form action="admin.php" method="POST" onsubmit="return confirm('Deseja realmente excluir este militar? Todos os registros de presença dele serão apagados.');" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_militar">
                                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                    <button type="submit" class="btn-action-small btn-delete">Excluir</button>
                                                </form>
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

        <!-- CONTEÚDO 2: GERENCIAR SEÇÕES -->
        <div id="tab-secoes" class="tab-content">
            <div class="admin-flex">
                <!-- Coluna Esquerda: Cadastro e Edição -->
                <div class="legend-box" style="height: fit-content;">
                    <h3 id="formSecaoTitle">Cadastrar Nova Seção</h3>
                    <form action="admin.php" method="POST" id="formSecao" style="margin-top: 15px;">
                        <input type="hidden" name="action" id="secaoAction" value="add_secao">
                        <input type="hidden" name="id" id="secaoId" value="">

                        <div class="form-group">
                            <label for="sNome">Nome da Seção</label>
                            <input type="text" name="nome" id="sNome" class="form-input" placeholder="Ex: SSTI" required autocomplete="off" style="text-transform: uppercase;">
                        </div>

                        <button type="submit" class="btn-primary" id="btnSubmitSecao">Cadastrar Seção</button>
                        <button type="button" class="btn-logout" id="btnCancelEditSecao" style="display: none; width: 100%; margin-top: 10px; color: var(--text)">Cancelar Edição</button>
                    </form>
                </div>

                <!-- Coluna Direita: Listagem -->
                <div class="section-card" style="margin-bottom: 0;">
                    <div class="section-title">
                        <span>Seções Cadastradas</span>
                        <span class="section-badge"><?= count($secoesList) ?> Seções</span>
                    </div>
                    <div class="table-responsive">
                        <table class="efetivo-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th>Nome da Seção</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($secoesList as $sec): ?>
                                    <tr>
                                        <td><?= $sec['id'] ?></td>
                                        <td><strong><?= $sec['nome'] ?></strong></td>
                                        <td>
                                            <div class="action-btn-group">
                                                <button class="btn-action-small btn-edit" onclick="editSecao(<?= $sec['id'] ?>, '<?= addslashes($sec['nome']) ?>')">Editar</button>
                                                <form action="admin.php" method="POST" onsubmit="return confirm('Deseja realmente excluir esta seção? Apenas seções vazias podem ser apagadas.');" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_secao">
                                                    <input type="hidden" name="id" value="<?= $sec['id'] ?>">
                                                    <button type="submit" class="btn-action-small btn-delete">Excluir</button>
                                                </form>
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

        <!-- CONTEÚDO 3: USUÁRIOS E ACESSOS -->
        <div id="tab-usuarios" class="tab-content">
            <div class="admin-flex">
                <!-- Coluna Esquerda: Cadastro de Usuário e Alteração de Senha -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <!-- Cadastro -->
                    <div class="legend-box" style="height: fit-content; margin-bottom: 0;">
                        <h3 id="formUserTitle">Criar Novo Usuário</h3>
                        <form action="admin.php" method="POST" id="formUser" style="margin-top: 15px;">
                            <input type="hidden" name="action" id="userAction" value="add_user">
                            <input type="hidden" name="id" id="userId" value="">

                            <div class="form-group">
                                <label for="uNome">Nome de Exibição</label>
                                <input type="text" name="nome" id="uNome" class="form-input" placeholder="Ex: Encarregado TWR" required autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label for="uUsuario">Nome de Usuário (Login)</label>
                                <input type="text" name="usuario" id="uUsuario" class="form-input" placeholder="Ex: encarregado_twr" required autocomplete="off">
                            </div>

                            <div class="form-group" id="senhaGroup">
                                <label for="uSenha">Senha</label>
                                <input type="password" name="senha" id="uSenha" class="form-input" placeholder="Defina a senha" required>
                            </div>

                            <div class="form-group">
                                <label for="uPerfil">Perfil de Acesso</label>
                                <select name="perfil" id="uPerfil" class="form-input" style="padding: 10px;" required onchange="toggleSecaoField()">
                                    <option value="encarregado">Encarregado (Chamada)</option>
                                    <option value="chefia">Chefia (Chamada + Dashboard)</option>
                                    <option value="admin">Administrador (Gestão Total)</option>
                                </select>
                            </div>

                            <div class="form-group" id="secaoVinculadaGroup">
                                <label for="uSecao">Seção Vinculada (Exclusivo para Encarregados)</label>
                                <select name="secao" id="uSecao" class="form-input" style="padding: 10px;">
                                    <option value="">-- Acesso Geral (Todas) --</option>
                                    <?php foreach ($secoesList as $sec): ?>
                                        <option value="<?= $sec['nome'] ?>"><?= $sec['nome'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn-primary" id="btnSubmitUser">Criar Usuário</button>
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
                                <label>Usuário selecionado</label>
                                <input type="text" id="passUserDisplay" class="form-input" style="background-color: var(--border);" readonly>
                            </div>

                            <div class="form-group">
                                <label for="newPass">Nova Senha</label>
                                <input type="password" name="nova_senha" id="newPass" class="form-input" placeholder="Nova senha" required>
                            </div>

                            <button type="submit" class="btn-primary">Gravar Nova Senha</button>
                            <button type="button" class="btn-logout" onclick="closePasswordBox()" style="width: 100%; margin-top: 10px; color: var(--text)">Cancelar</button>
                        </form>
                    </div>
                </div>

                <!-- Coluna Direita: Listagem de Usuários -->
                <div class="section-card" style="margin-bottom: 0;">
                    <div class="section-title">
                        <span>Usuários do Sistema</span>
                        <span class="section-badge"><?= count($usuarios) ?> Contas</span>
                    </div>
                    <div class="table-responsive">
                        <table class="efetivo-table">
                            <thead>
                                <tr>
                                    <th>Nome / Login</th>
                                    <th>Perfil</th>
                                    <th>Seção Limitada</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td>
                                            <strong><?= $u['nome'] ?></strong><br>
                                            <small style="color: var(--text-muted);">@<?= $u['usuario'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge-admin-profile <?= $u['perfil'] ?>"><?= $u['perfil'] ?></span>
                                        </td>
                                        <td><?= $u['secao'] ? $u['secao'] : '<em style="color: var(--text-muted);">Nenhuma (Acesso total)</em>' ?></td>
                                        <td>
                                            <div class="action-btn-group">
                                                <button class="btn-action-small btn-edit" onclick="editUser(<?= $u['id'] ?>, '<?= addslashes($u['nome']) ?>', '<?= addslashes($u['usuario']) ?>', '<?= $u['perfil'] ?>', '<?= addslashes($u['secao']) ?>')">Editar</button>
                                                <button class="btn-action-small btn-pass" onclick="openPasswordBox(<?= $u['id'] ?>, '<?= addslashes($u['usuario']) ?>')">Senha</button>
                                                <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                                    <form action="admin.php" method="POST" onsubmit="return confirm('Deseja realmente excluir este usuário?');" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="btn-action-small btn-delete">Excluir</button>
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

        // Funções do CRUD de Efetivo
        function editMilitar(id, nome, secao, escala) {
            document.getElementById('militarAction').value = 'edit_militar';
            document.getElementById('militarId').value = id;
            document.getElementById('mNome').value = nome;
            document.getElementById('mSecao').value = secao;
            document.getElementById('mEscala').value = escala;
            
            document.getElementById('formMilitarTitle').innerText = 'Editar Militar';
            document.getElementById('btnSubmitMilitar').innerText = 'Gravar Alterações';
            document.getElementById('btnCancelEditMilitar').style.display = 'block';
            
            // Focar o campo nome
            document.getElementById('mNome').focus();
        }

        document.getElementById('btnCancelEditMilitar').addEventListener('click', () => {
            document.getElementById('militarAction').value = 'add_militar';
            document.getElementById('militarId').value = '';
            document.getElementById('formMilitar').reset();
            
            document.getElementById('formMilitarTitle').innerText = 'Cadastrar Novo Militar';
            document.getElementById('btnSubmitMilitar').innerText = 'Cadastrar Militar';
            document.getElementById('btnCancelEditMilitar').style.display = 'none';
        });

        // Funções do CRUD de Seções
        function editSecao(id, nome) {
            document.getElementById('secaoAction').value = 'edit_secao';
            document.getElementById('secaoId').value = id;
            document.getElementById('sNome').value = nome;
            
            document.getElementById('formSecaoTitle').innerText = 'Editar Seção';
            document.getElementById('btnSubmitSecao').innerText = 'Gravar Alterações';
            document.getElementById('btnCancelEditSecao').style.display = 'block';
            
            document.getElementById('sNome').focus();
        }

        document.getElementById('btnCancelEditSecao').addEventListener('click', () => {
            document.getElementById('secaoAction').value = 'add_secao';
            document.getElementById('secaoId').value = '';
            document.getElementById('formSecao').reset();
            
            document.getElementById('formSecaoTitle').innerText = 'Cadastrar Nova Seção';
            document.getElementById('btnSubmitSecao').innerText = 'Cadastrar Seção';
            document.getElementById('btnCancelEditSecao').style.display = 'none';
        });

        // Funções do CRUD de Usuários
        function toggleSecaoField() {
            const perfil = document.getElementById('uPerfil').value;
            const secaoGroup = document.getElementById('secaoVinculadaGroup');
            if (perfil === 'encarregado') {
                secaoGroup.style.display = 'block';
            } else {
                secaoGroup.style.display = 'none';
                document.getElementById('uSecao').value = '';
            }
        }

        function editUser(id, nome, usuario, perfil, secao) {
            document.getElementById('userAction').value = 'edit_user';
            document.getElementById('userId').value = id;
            document.getElementById('uNome').value = nome;
            document.getElementById('uUsuario').value = usuario;
            document.getElementById('uPerfil').value = perfil;
            document.getElementById('uSecao').value = secao;
            
            // Ocultar campo de senha ao editar dados básicos
            document.getElementById('senhaGroup').style.display = 'none';
            document.getElementById('uSenha').removeAttribute('required');
            
            document.getElementById('formUserTitle').innerText = 'Editar Usuário';
            document.getElementById('btnSubmitUser').innerText = 'Gravar Alterações';
            document.getElementById('btnCancelEditUser').style.display = 'block';
            
            toggleSecaoField();
            document.getElementById('uNome').focus();
        }

        document.getElementById('btnCancelEditUser').addEventListener('click', () => {
            document.getElementById('userAction').value = 'add_user';
            document.getElementById('userId').value = '';
            document.getElementById('formUser').reset();
            
            document.getElementById('senhaGroup').style.display = 'block';
            document.getElementById('uSenha').setAttribute('required', 'required');
            
            document.getElementById('formUserTitle').innerText = 'Criar Novo Usuário';
            document.getElementById('btnSubmitUser').innerText = 'Criar Usuário';
            document.getElementById('btnCancelEditUser').style.display = 'none';
            toggleSecaoField();
        });

        // Funções de Alteração de Senha
        function openPasswordBox(id, usuario) {
            document.getElementById('passUserId').value = id;
            document.getElementById('passUserDisplay').value = usuario;
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
