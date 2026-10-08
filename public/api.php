<?php
// api.php
// Endpoints assíncronos para chamada diária, afastamentos, efetivo completo (Visão Geral) e seções

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Sessão expirada ou não autenticada. Por favor, faça login novamente.',
        'error' => 'Não autorizado'
    ]);
    exit;
}

$action = $_GET['action'] ?? '';
$user = getCurrentUser();
$isAdmin = ($user && $user['perfil'] === 'admin');
$isChefiaOrAdmin = ($user && in_array($user['perfil'], ['admin', 'chefia']));
$isEncarregadoOrAdmin = ($user && in_array($user['perfil'], ['admin', 'encarregado']));

function formatDateToSQL($dateStr) {
    if (empty($dateStr)) return null;
    $dateStr = trim($dateStr);
    if ($dateStr === '-' || $dateStr === 'N/A' || $dateStr === 'null' || $dateStr === 'undefined') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) return $dateStr;
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $dateStr, $matches)) {
        return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
    }
    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $dateStr, $matches)) {
        return sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
    }
    return null;
}

function formatSQLToDate($sqlStr) {
    if (empty($sqlStr)) return '';
    $sqlStr = trim($sqlStr);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $sqlStr, $matches)) {
        return sprintf('%02d/%02d/%04d', $matches[3], $matches[2], $matches[1]);
    }
    return $sqlStr;
}

try {
    // -------------------------------------------------------------
    // 1. LISTAR EFETIVO COMPLETO (VISÃO GERAL)
    // -------------------------------------------------------------
    if ($action === 'list_personnel' || $action === 'list') {
        if (!$isChefiaOrAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso não autorizado. Apenas Chefia e Administração podem visualizar o efetivo geral.']);
            exit;
        }
        $sec = getSecaoInfo($db);
        $secTable = $sec['table'];
        $secCol = $sec['name_col'];
        $codeCol = $sec['code_col'];

        $stmt = $db->query("
            SELECT u.*, 
                   COALESCE(s.`$codeCol`, s.`$secCol`, 'SEM_SECAO') AS secao_sigla,
                   COALESCE(s.`$secCol`, 'Sem Seção') AS secao_nome
            FROM users u 
            LEFT JOIN `$secTable` s ON u.section_id = s.id 
            ORDER BY (u.deleted_at IS NOT NULL) ASC, u.name ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int)$row['id'],
                'posto_grad' => $row['grade'] ?? '',
                'especialidade' => $row['specialty'] ?? '',
                'secao' => $row['secao_sigla'] ?? '',
                'secao_nome' => $row['secao_nome'] ?? '',
                'nome' => $row['name'] ?? '',
                'nome_guerra' => $row['war_name'] ?? '',
                'identidade' => $row['identidade'] ?? '',
                'cpf' => $row['cpf'] ?? '',
                'saram' => $row['saram'] ?? '',
                'nascimento' => formatSQLToDate($row['birth_date'] ?? ''),
                'praca' => formatSQLToDate($row['praca'] ?? ''),
                'ult_promocao' => formatSQLToDate($row['ult_promocao'] ?? ''),
                'apresentacao_dtcea' => formatSQLToDate($row['apresentacao_dtcea'] ?? ''),
                'data_insp_saude' => formatSQLToDate($row['data_insp_saude'] ?? ''),
                'validade_insp_saude' => formatSQLToDate($row['validade_insp_saude'] ?? ''),
                'prorrogacao' => formatSQLToDate($row['prorrogacao'] ?? ''),
                'observacoes' => $row['observacoes'] ?? '',
                'is_admin' => (int)($row['is_admin'] ?? 0),
                'person_type' => !empty($row['person_type']) ? $row['person_type'] : 'MILITAR',
                'is_active' => ($row['deleted_at'] === null),
                'deleted_at' => !empty($row['deleted_at']) ? formatSQLToDate($row['deleted_at']) : null
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $list,
            'is_admin' => $isAdmin
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // 2. LISTAR SEÇÕES
    // -------------------------------------------------------------
    elseif ($action === 'list_secoes') {
        $sec = getSecaoInfo($db);
        $secTable = $sec['table'];
        $secCol = $sec['name_col'];
        $codeCol = $sec['code_col'];

        $stmt = $db->query("SELECT id, `$codeCol` AS sigla, `$secCol` AS nome FROM `$secTable` ORDER BY `$codeCol` ASC");
        $secoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $secoes
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // 3. SALVAR MILITAR / USUÁRIO (NOVO OU EDIÇÃO)
    // -------------------------------------------------------------
    elseif ($action === 'save_person' || ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST')) {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem executar esta ação.']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos recebidos.']);
            exit;
        }

        $id = (int)($data['id'] ?? 0);
        $grade = sanitize($data['posto_grad'] ?? '');
        $specialty = sanitize($data['especialidade'] ?? '');
        $name = mb_strtoupper(sanitize($data['nome'] ?? ''), 'UTF-8');
        $warName = mb_strtoupper(sanitize($data['nome_guerra'] ?? ''), 'UTF-8');
        $identidade = sanitize($data['identidade'] ?? '');
        $cpf = sanitize($data['cpf'] ?? '');
        $saram = sanitize($data['saram'] ?? '');
        $secaoSigla = mb_strtoupper(sanitize($data['secao'] ?? ''), 'UTF-8');

        $birthDate = formatDateToSQL($data['nascimento'] ?? '');
        $praca = formatDateToSQL($data['praca'] ?? '');
        $ultPromocao = formatDateToSQL($data['ult_promocao'] ?? '');
        $apresentacaoDtcea = formatDateToSQL($data['apresentacao_dtcea'] ?? '');
        $dataInspSaude = formatDateToSQL($data['data_insp_saude'] ?? '');
        $validadeInspSaude = formatDateToSQL($data['validade_insp_saude'] ?? '');
        $prorrogacao = formatDateToSQL($data['prorrogacao'] ?? '');

        $observacoes = sanitize($data['observacoes'] ?? '');
        $isAdminFlag = !empty($data['is_admin']) ? 1 : 0;
        $personType = ($data['person_type'] ?? 'MILITAR') === 'CIVIL' ? 'CIVIL' : 'MILITAR';

        if (empty($name) || empty($grade)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nome e Posto/Graduação são obrigatórios.']);
            exit;
        }

        // Localizar ID da seção
        $sec = getSecaoInfo($db);
        $secTable = $sec['table'];
        $secCol = $sec['name_col'];
        $codeCol = $sec['code_col'];

        $sectionId = null;
        if (!empty($secaoSigla)) {
            $stmtSec = $db->prepare("SELECT id FROM `$secTable` WHERE `$codeCol` = ? OR `$secCol` = ? LIMIT 1");
            $stmtSec->execute([$secaoSigla, $secaoSigla]);
            $secFound = $stmtSec->fetch(PDO::FETCH_ASSOC);
            if ($secFound) {
                $sectionId = (int)$secFound['id'];
            }
        }
        if (!$sectionId) {
            $firstSec = $db->query("SELECT id FROM `$secTable` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $sectionId = $firstSec ? (int)$firstSec['id'] : 1;
        }

        if ($id > 0) {
            // Atualizar registro existente
            $stmtUpdate = $db->prepare("
                UPDATE users SET 
                    grade = ?, specialty = ?, name = ?, war_name = ?,
                    identidade = ?, cpf = ?, saram = ?, birth_date = ?, praca = ?,
                    ult_promocao = ?, apresentacao_dtcea = ?, data_insp_saude = ?,
                    validade_insp_saude = ?, section_id = ?, prorrogacao = ?,
                    observacoes = ?, is_admin = ?, person_type = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([
                $grade, $specialty, $name, $warName,
                $identidade, $cpf, $saram, $birthDate, $praca,
                $ultPromocao, $apresentacaoDtcea, $dataInspSaude,
                $validadeInspSaude, $sectionId, $prorrogacao,
                $observacoes, $isAdminFlag, $personType, $id
            ]);

            echo json_encode(['success' => true, 'message' => 'Ficha do militar atualizada com sucesso!', 'id' => $id]);
            exit;
        } else {
            // Verificar se já existe cadastro (ativo ou desativado) por SARAM, CPF ou Nome
            $cleanSaram = preg_replace('/[\.\-\s\/]/', '', $saram);
            $cleanCpf = preg_replace('/[\.\-\s\/]/', '', $cpf);

            $checkStmt = $db->prepare("
                SELECT id, name, grade, specialty, saram, cpf, deleted_at 
                FROM users 
                WHERE (
                    (? != '' AND (saram = ? OR REPLACE(REPLACE(REPLACE(saram, '.', ''), '-', ''), ' ', '') = ?))
                    OR (? != '' AND (cpf = ? OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?))
                    OR (name = ?)
                )
                LIMIT 1
            ");
            $checkStmt->execute([
                $saram, $saram, $cleanSaram,
                $cpf, $cpf, $cleanCpf,
                $name
            ]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['deleted_at'] !== null) {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'is_inactive_conflict' => true,
                        'inactive_id' => (int)$existing['id'],
                        'inactive_name' => "{$existing['grade']} {$existing['name']}",
                        'message' => "O militar {$existing['grade']} {$existing['name']} (SARAM: {$existing['saram']}) já possui cadastro no sistema, porém encontra-se DESATIVADO. Utilize o filtro de 'Desativados' para visualizá-lo e reativá-lo.",
                        'error' => "Cadastro já existente e desativado"
                    ]);
                    exit;
                } else {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Já existe um militar ativo cadastrado com este SARAM/CPF ou Nome: {$existing['grade']} {$existing['name']}.",
                        'error' => "Militar já cadastrado"
                    ]);
                    exit;
                }
            }

            // Inserir novo militar
            $email = !empty($saram) ? $saram . '@fab.mil.br' : uniqid('militar_') . '@fab.mil.br';
            $passwordHash = password_hash('123456', PASSWORD_DEFAULT);

            $stmtInsert = $db->prepare("
                INSERT INTO users (
                    grade, specialty, name, war_name, identidade, cpf, saram,
                    birth_date, praca, ult_promocao, apresentacao_dtcea,
                    data_insp_saude, validade_insp_saude, section_id, prorrogacao,
                    observacoes, email, password, escala, is_admin, person_type, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, 0, ?, ?, NOW(), NOW()
                )
            ");
            $stmtInsert->execute([
                $grade, $specialty, $name, $warName, $identidade, $cpf, $saram,
                $birthDate, $praca, $ultPromocao, $apresentacaoDtcea,
                $dataInspSaude, $validadeInspSaude, $sectionId, $prorrogacao,
                $observacoes, $email, $passwordHash, $isAdminFlag, $personType
            ]);
            $newId = (int)$db->lastInsertId();

            echo json_encode(['success' => true, 'message' => 'Militar cadastrado com sucesso!', 'id' => $newId]);
            exit;
        }
    }

    // -------------------------------------------------------------
    // 4. DESATIVAR MILITAR (SOFT DELETE)
    // -------------------------------------------------------------
    elseif ($action === 'delete_person' || $action === 'inactivate_person' || ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST')) {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem desativar registros.', 'error' => 'Acesso negado']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID inválido para desativação.', 'error' => 'ID inválido']);
            exit;
        }

        if ($id === (int)$user['id']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Você não pode desativar sua própria conta conectada.', 'error' => 'Auto-desativação proibida']);
            exit;
        }

        $stmt = $db->prepare("UPDATE users SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Militar desativado com sucesso. As informações continuam salvas para consulta no filtro de desativados.']);
        exit;
    }

    // -------------------------------------------------------------
    // 4.1 REATIVAR MILITAR
    // -------------------------------------------------------------
    elseif ($action === 'reactivate_person' || $action === 'activate_person') {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem reativar registros.', 'error' => 'Acesso negado']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID inválido para reativação.', 'error' => 'ID inválido']);
            exit;
        }

        $stmt = $db->prepare("UPDATE users SET deleted_at = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Militar reativado no efetivo ativo com sucesso!']);
        exit;
    }

    // -------------------------------------------------------------
    // 5. SALVAR SEÇÃO
    // -------------------------------------------------------------
    elseif ($action === 'save_secao') {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem gerenciar seções.']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $sigla = mb_strtoupper(sanitize($data['sigla'] ?? ''), 'UTF-8');
        $nome = !empty($data['nome']) ? mb_strtoupper(sanitize($data['nome']), 'UTF-8') : $sigla;

        if (empty($sigla)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A sigla da seção é obrigatória.']);
            exit;
        }

        $sec = getSecaoInfo($db);
        $secTable = $sec['table'];
        $secCol = $sec['name_col'];
        $codeCol = $sec['code_col'];

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE `$secTable` SET `$codeCol` = ?, `$secCol` = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$sigla, $nome, $id]);
            echo json_encode(['success' => true, 'message' => 'Seção atualizada com sucesso.']);
            exit;
        } else {
            $stmt = $db->prepare("INSERT INTO `$secTable` (`$codeCol`, `$secCol`, extension_line, created_at, updated_at) VALUES (?, ?, '0000', NOW(), NOW())");
            $stmt->execute([$sigla, $nome]);
            echo json_encode(['success' => true, 'message' => 'Seção cadastrada com sucesso.']);
            exit;
        }
    }

    // -------------------------------------------------------------
    // 6. EXCLUIR SEÇÃO
    // -------------------------------------------------------------
    elseif ($action === 'delete_secao') {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem excluir seções.']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de seção inválido.']);
            exit;
        }

        // Verificar se há militares vinculados
        $countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE section_id = ? AND deleted_at IS NULL");
        $countStmt->execute([$id]);
        $linked = (int)$countStmt->fetchColumn();

        if ($linked > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Não é possível excluir: existem $linked militares vinculados a esta seção."]);
            exit;
        }

        $sec = getSecaoInfo($db);
        $secTable = $sec['table'];
        $stmt = $db->prepare("DELETE FROM `$secTable` WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Seção removida com sucesso.']);
        exit;
    }

    // -------------------------------------------------------------
    // 7. SALVAR CHAMADA DIÁRIA (LANÇAMENTO DE CHAMADA)
    // -------------------------------------------------------------
    elseif ($action === 'save_call' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$isEncarregadoOrAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas Encarregados e Administradores podem realizar lançamentos de chamada.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $date = sanitize($input['date'] ?? '');
        $presenceData = $input['presencas'] ?? [];

        if (empty($date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data inválida.', 'error' => 'Data inválida']);
            exit;
        }

        // Se for perfil encarregado, validar que só pode lançar para militares de sua própria seção
        if ($user['perfil'] === 'encarregado') {
            $userSecId = (int)($user['secao_id'] ?? 0);
            $userSecNome = $user['secao'] ?? '';
            $sec = getSecaoInfo($db);
            $secTable = $sec['table'];
            $secCol = $sec['name_col'];
            
            $validStmt = $db->prepare("
                SELECT u.id 
                FROM users u 
                LEFT JOIN `$secTable` s ON u.section_id = s.id
                WHERE u.deleted_at IS NULL AND (u.section_id = ? OR s.`$secCol` = ?)
            ");
            $validStmt->execute([$userSecId, $userSecNome]);
            $allowedIds = $validStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach (array_keys($presenceData) as $mId) {
                if (!in_array((int)$mId, $allowedIds)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Você só possui permissão para lançar chamada para militares de sua própria seção.']);
                    exit;
                }
            }
        }

        $db->beginTransaction();
        $stmt = $db->prepare("REPLACE INTO presencas (militar_id, data, status) VALUES (?, ?, ?)");
        
        foreach ($presenceData as $militarId => $status) {
            $status = sanitize($status);
            if ($status !== '') {
                $stmt->execute([(int)$militarId, $date, $status]);
            }
        }
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Chamada salva com sucesso!']);
        exit;
    }

    // -------------------------------------------------------------
    // 8. SALVAR PERÍODO DE AFASTAMENTO
    // -------------------------------------------------------------
    elseif ($action === 'save_period' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$isEncarregadoOrAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas Encarregados e Administradores podem lançar períodos de afastamento.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $militarId = (int)($input['militar_id'] ?? 0);
        $status = sanitize($input['status'] ?? '');
        $dateStart = sanitize($input['date_start'] ?? '');
        $dateEnd = sanitize($input['date_end'] ?? '');

        if ($militarId <= 0 || empty($status) || empty($dateStart) || empty($dateEnd)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.', 'error' => 'Parâmetros inválidos']);
            exit;
        }

        // Se for perfil encarregado, validar que o militar selecionado é de sua seção
        if ($user['perfil'] === 'encarregado') {
            $userSecId = (int)($user['secao_id'] ?? 0);
            $userSecNome = $user['secao'] ?? '';
            $sec = getSecaoInfo($db);
            $secTable = $sec['table'];
            $secCol = $sec['name_col'];

            $validStmt = $db->prepare("
                SELECT u.id 
                FROM users u 
                LEFT JOIN `$secTable` s ON u.section_id = s.id
                WHERE u.id = ? AND u.deleted_at IS NULL AND (u.section_id = ? OR s.`$secCol` = ?)
            ");
            $validStmt->execute([$militarId, $userSecId, $userSecNome]);
            if (!$validStmt->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Você só possui permissão para lançar afastamento para militares de sua própria seção.']);
                exit;
            }
        }

        $start = new DateTime($dateStart);
        $end = new DateTime($dateEnd);
        
        if ($start > $end) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data de início deve ser anterior ou igual à data de término.', 'error' => 'Data de início deve ser anterior ou igual à data de término']);
            exit;
        }

        $end->modify('+1 day');
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);

        $db->beginTransaction();
        $stmt = $db->prepare("REPLACE INTO presencas (militar_id, data, status) VALUES (?, ?, ?)");
        
        foreach ($period as $dt) {
            $formattedDate = $dt->format("Y-m-d");
            $stmt->execute([$militarId, $formattedDate, $status]);
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Período de indisponibilidade gravado com sucesso!']);
        exit;
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno no servidor: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Ação não encontrada.', 'error' => 'Ação não encontrada']);
exit;
