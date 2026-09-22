<?php
// login.php
// Tela de Login / Logon para Controle de Efetivo (Integrado ao EfetivoSJ)

require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = sanitize($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!empty($loginInput) && !empty($senha)) {
        try {
            $sec = getSecaoInfo($db);
            $secTable = $sec['table'];
            $secCol = $sec['name_col'];
            $cleanLogin = str_replace(['.', '-', '/', ' '], '', $loginInput);

            $stmt = $db->prepare("
                SELECT u.*, 
                       COALESCE(s.`$secCol`, 'Sem Seção') AS secao_nome
                FROM users u
                LEFT JOIN `$secTable` s ON u.section_id = s.id
                WHERE (
                    u.saram = ? 
                    OR REPLACE(REPLACE(REPLACE(u.saram, '.', ''), '-', ''), ' ', '') = ?
                    OR u.email = ? 
                    OR u.war_name = ?
                    OR u.name = ?
                )
                AND u.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$loginInput, $cleanLogin, $loginInput, $loginInput, $loginInput]);
            $user = $stmt->fetch();

            if ($user && password_verify($senha, $user['password'])) {
                // Determinar perfil do usuário
                $perfil = 'auxiliar';
                if (!empty($user['is_admin']) && (int)$user['is_admin'] === 1) {
                    $perfil = 'admin';
                } elseif (!empty($user['is_admin']) && (int)$user['is_admin'] === 2) {
                    $perfil = 'chefia';
                } elseif (!empty($user['is_admin']) && (int)$user['is_admin'] === 3) {
                    $perfil = 'encarregado';
                } else {
                    // Verificar se o militar é chefe de alguma seção
                    try {
                        $secCols = $db->query("SHOW COLUMNS FROM `$secTable`")->fetchAll(PDO::FETCH_COLUMN);
                        if (in_array('chefe_id', $secCols)) {
                            $stmtChefe = $db->prepare("SELECT id FROM `$secTable` WHERE chefe_id = ? LIMIT 1");
                            $stmtChefe->execute([$user['id']]);
                            if ($stmtChefe->fetch()) {
                                $perfil = 'chefia';
                            }
                        }
                    } catch (Exception $e) {}
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_saram'] = $user['saram'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_nome'] = formatarNomeMilitar($user);
                $_SESSION['user_perfil'] = $perfil;
                $_SESSION['user_secao_id'] = $user['section_id'];
                $_SESSION['user_secao'] = $user['secao_nome'];

                if ($perfil === 'admin') {
                    header("Location: admin.php");
                } elseif ($perfil === 'chefia') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $error = 'SARAM, E-mail ou senha incorretos.';
            }
        } catch (PDOException $e) {
            $error = 'Erro no servidor: ' . $e->getMessage();
        }
    } else {
        $error = 'Por favor, preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logon - Controle de Efetivo DTCEA-SJ</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
    <div class="login-card">
        <img src="dtcea_sj_logo.png" alt="Logo DTCEA-SJ" class="login-logo">
        <h2>DTCEA-SJ</h2>
        <p class="subtitle">Controle de Efetivo de Pessoal</p>

        <?php if ($error): ?>
            <div class="alert">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="usuario">SARAM ou E-mail FAB</label>
                <input type="text" name="usuario" id="usuario" class="form-input" placeholder="Ex: 393.068-8 ou email@fab.mil.br" required autofocus autocomplete="off">
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" name="senha" id="senha" class="form-input" placeholder="Sua senha do sistema" required>
            </div>

            <button type="submit" class="btn-primary">Acessar Sistema</button>
        </form>
    </div>
</body>
</html>
