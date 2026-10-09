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

    // -------------------------------------------------------------
    // 6. LISTAR TAREFAS COM PRAZO & ENTREGAS (LIST_TASKS)
    // -------------------------------------------------------------
    if ($action === 'list_tasks') {
        $sec = getSecaoInfo($db);
        $secTable = $sec['table'];
        $secCol = $sec['name_col'];

        $filterStatus = sanitize($_GET['status'] ?? 'all');
        $filterPrioridade = sanitize($_GET['prioridade'] ?? 'all');
        $filterPeriodicidade = sanitize($_GET['periodicidade'] ?? 'all');
        $search = sanitize($_GET['search'] ?? '');
        $secaoId = (int)($_GET['secao_id'] ?? 0);

        $sql = "
            SELECT t.*, 
                   COALESCE(s.`$secCol`, 'Todas as Seções / Geral') as secao_nome,
                   u_resp.name as responsavel_nome,
                   u_resp.war_name as responsavel_war_name,
                   u_resp.grade as responsavel_grade,
                   u_cria.name as criador_nome
            FROM tarefas_prazos t
            LEFT JOIN `$secTable` s ON t.secao_id = s.id
            LEFT JOIN users u_resp ON t.responsavel_id = u_resp.id
            LEFT JOIN users u_cria ON t.criado_por = u_cria.id
            WHERE 1=1
        ";
        $params = [];

        if ($filterStatus === 'pendentes') {
            $sql .= " AND t.status IN ('pendente', 'em_andamento') AND t.data_limite >= CURDATE()";
        } elseif ($filterStatus === 'atrasadas') {
            $sql .= " AND t.status IN ('pendente', 'em_andamento') AND t.data_limite < CURDATE()";
        } elseif ($filterStatus === 'concluidas') {
            $sql .= " AND t.status = 'concluida'";
        } elseif (!empty($filterStatus) && $filterStatus !== 'all') {
            $sql .= " AND t.status = ?";
            $params[] = $filterStatus;
        }

        if (!empty($filterPrioridade) && $filterPrioridade !== 'all') {
            $sql .= " AND t.prioridade = ?";
            $params[] = $filterPrioridade;
        }

        if ($filterPeriodicidade === 'periodicas') {
            $sql .= " AND t.is_periodica = 1";
        } elseif ($filterPeriodicidade === 'pontuais') {
            $sql .= " AND t.is_periodica = 0";
        } elseif (in_array($filterPeriodicidade, ['semanal', 'mensal', 'quinzenal', 'anual'])) {
            $sql .= " AND t.periodicidade = ?";
            $params[] = $filterPeriodicidade;
        }

        if ($secaoId > 0) {
            $sql .= " AND (t.secao_id = ? OR t.secao_id IS NULL)";
            $params[] = $secaoId;
        }

        if (!empty($search)) {
            $sql .= " AND (t.titulo LIKE ? OR t.descricao LIKE ? OR t.categoria LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY (t.status = 'concluida') ASC, (t.data_limite < CURDATE()) DESC, t.data_limite ASC, t.prioridade DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hoje = new DateTime(date('Y-m-d'));
        $tasks = [];
        $totalAtivas = 0;
        $totalCriticas = 0;
        $totalAtrasadas = 0;
        $totalPeriodicas = 0;
        $totalConcluidas = 0;

        foreach ($rows as $r) {
            $dtLimite = new DateTime($r['data_limite']);
            $diff = $hoje->diff($dtLimite);
            $diasRestantes = (int)$diff->format('%r%a');
            
            $isAtrasada = ($diasRestantes < 0 && $r['status'] !== 'concluida');
            $antecedencia = (int)($r['lembrete_antecedencia_dias'] ?? 3);
            $isCriticaPrazo = ($diasRestantes <= $antecedencia && $r['status'] !== 'concluida') || ($r['prioridade'] === 'critica' && $r['status'] !== 'concluida');

            if ($r['status'] === 'concluida') {
                $totalConcluidas++;
            } else {
                $totalAtivas++;
                if ($isAtrasada) {
                    $totalAtrasadas++;
                }
                if ($isCriticaPrazo) {
                    $totalCriticas++;
                }
                if ($r['is_periodica']) {
                    $totalPeriodicas++;
                }
            }

            $respNomeCompleto = '';
            if (!empty($r['responsavel_nome'])) {
                $grade = !empty($r['responsavel_grade']) && strtoupper($r['responsavel_grade']) !== 'MILITAR' ? $r['responsavel_grade'] . ' ' : '';
                $guerra = !empty($r['responsavel_war_name']) && $r['responsavel_war_name'] !== '-' ? $r['responsavel_war_name'] : $r['responsavel_nome'];
                $respNomeCompleto = trim($grade . $guerra);
            }

            $tasks[] = [
                'id' => (int)$r['id'],
                'titulo' => $r['titulo'],
                'descricao' => $r['descricao'] ?? '',
                'data_limite' => $r['data_limite'],
                'data_limite_fmt' => formatSQLToDate($r['data_limite']),
                'hora_limite' => $r['hora_limite'] ? substr($r['hora_limite'], 0, 5) : '23:59',
                'is_periodica' => (bool)$r['is_periodica'],
                'periodicidade' => $r['periodicidade'],
                'dia_lembrete' => $r['dia_lembrete'] ?? '',
                'lembrete_antecedencia_dias' => (int)$r['lembrete_antecedencia_dias'],
                'prioridade' => $r['prioridade'],
                'status' => $r['status'],
                'categoria' => $r['categoria'] ?? 'Geral',
                'secao_id' => $r['secao_id'] ? (int)$r['secao_id'] : null,
                'secao_nome' => $r['secao_nome'] ?? 'Geral / Todas',
                'responsavel_id' => $r['responsavel_id'] ? (int)$r['responsavel_id'] : null,
                'responsavel_nome' => $respNomeCompleto,
                'criado_por_nome' => $r['criador_nome'] ?? '',
                'concluido_em' => $r['concluido_em'],
                'concluido_em_fmt' => $r['concluido_em'] ? date('d/m/Y H:i', strtotime($r['concluido_em'])) : null,
                'created_at_fmt' => $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '',
                'dias_restantes' => $diasRestantes,
                'is_atrasada' => $isAtrasada,
                'is_critica_prazo' => $isCriticaPrazo
            ];
        }

        echo json_encode([
            'success' => true,
            'summary' => [
                'total' => count($rows),
                'ativas' => $totalAtivas,
                'criticas' => $totalCriticas,
                'atrasadas' => $totalAtrasadas,
                'periodicas' => $totalPeriodicas,
                'concluidas' => $totalConcluidas
            ],
            'tasks' => $tasks
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // 7. SALVAR TAREFA (CRIAR OU ATUALIZAR)
    // -------------------------------------------------------------
    if ($action === 'save_task') {
        $id = (int)($_POST['id'] ?? 0);
        $titulo = sanitize($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $dataLimite = formatDateToSQL($_POST['data_limite'] ?? '');
        $horaLimite = sanitize($_POST['hora_limite'] ?? '23:59:00');
        $isPeriodica = !empty($_POST['is_periodica']) ? 1 : 0;
        $periodicidade = sanitize($_POST['periodicidade'] ?? 'nenhuma');
        $diaLembrete = sanitize($_POST['dia_lembrete'] ?? '');
        $antecedencia = (int)($_POST['lembrete_antecedencia_dias'] ?? 3);
        $prioridade = sanitize($_POST['prioridade'] ?? 'media');
        $status = sanitize($_POST['status'] ?? 'pendente');
        $categoria = sanitize($_POST['categoria'] ?? 'Geral');
        $secaoId = !empty($_POST['secao_id']) ? (int)$_POST['secao_id'] : null;
        $responsavelId = !empty($_POST['responsavel_id']) ? (int)$_POST['responsavel_id'] : null;

        if (empty($titulo) || empty($dataLimite)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Título e Data Limite são campos obrigatórios.']);
            exit;
        }

        if (!in_array($prioridade, ['baixa', 'media', 'alta', 'critica'])) {
            $prioridade = 'media';
        }

        if (!in_array($status, ['pendente', 'em_andamento', 'concluida', 'cancelada'])) {
            $status = 'pendente';
        }

        if (!in_array($periodicidade, ['nenhuma', 'semanal', 'quinzenal', 'mensal', 'anual'])) {
            $periodicidade = 'nenhuma';
        }

        if (strlen($horaLimite) === 5) {
            $horaLimite .= ':00';
        }

        $userId = $user['id'] ?? null;

        if ($id > 0) {
            $concluidoEm = ($status === 'concluida') ? date('Y-m-d H:i:s') : null;
            $stmt = $db->prepare("
                UPDATE tarefas_prazos SET 
                    titulo = ?, 
                    descricao = ?, 
                    data_limite = ?, 
                    hora_limite = ?, 
                    is_periodica = ?, 
                    periodicidade = ?, 
                    dia_lembrete = ?, 
                    lembrete_antecedencia_dias = ?, 
                    prioridade = ?, 
                    status = ?, 
                    categoria = ?, 
                    secao_id = ?, 
                    responsavel_id = ?,
                    concluido_em = IF(? = 'concluida', COALESCE(concluido_em, NOW()), NULL)
                WHERE id = ?
            ");
            $stmt->execute([
                $titulo, $descricao, $dataLimite, $horaLimite, $isPeriodica,
                $periodicidade, $diaLembrete, $antecedencia, $prioridade,
                $status, $categoria, $secaoId, $responsavelId, $status, $id
            ]);

            echo json_encode(['success' => true, 'message' => 'Tarefa atualizada com sucesso!', 'id' => $id]);
            exit;
        } else {
            $concluidoEm = ($status === 'concluida') ? date('Y-m-d H:i:s') : null;
            $stmt = $db->prepare("
                INSERT INTO tarefas_prazos 
                    (titulo, descricao, data_limite, hora_limite, is_periodica, periodicidade, dia_lembrete, lembrete_antecedencia_dias, prioridade, status, categoria, secao_id, responsavel_id, criado_por, concluido_em) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $titulo, $descricao, $dataLimite, $horaLimite, $isPeriodica,
                $periodicidade, $diaLembrete, $antecedencia, $prioridade,
                $status, $categoria, $secaoId, $responsavelId, $userId, $concluidoEm
            ]);
            $newId = $db->lastInsertId();

            echo json_encode(['success' => true, 'message' => 'Tarefa com prazo cadastrada com sucesso!', 'id' => $newId]);
            exit;
        }
    }

    // -------------------------------------------------------------
    // 8. ALTERNAR STATUS DA TAREFA (TOGGLE STATUS)
    // -------------------------------------------------------------
    if ($action === 'toggle_task_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de tarefa inválido.']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM tarefas_prazos WHERE id = ?");
        $stmt->execute([$id]);
        $tarefa = $stmt->fetch();

        if (!$tarefa) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada.']);
            exit;
        }

        $novoStatus = ($tarefa['status'] === 'concluida') ? 'pendente' : 'concluida';
        $concluidoEm = ($novoStatus === 'concluida') ? date('Y-m-d H:i:s') : null;

        $update = $db->prepare("UPDATE tarefas_prazos SET status = ?, concluido_em = ? WHERE id = ?");
        $update->execute([$novoStatus, $concluidoEm, $id]);

        echo json_encode([
            'success' => true, 
            'message' => $novoStatus === 'concluida' ? 'Tarefa marcada como CONCLUÍDA!' : 'Tarefa REABERTA como pendente!',
            'status' => $novoStatus,
            'concluido_em' => $concluidoEm ? date('d/m/Y H:i', strtotime($concluidoEm)) : null
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // 9. EXCLUIR TAREFA (DELETE_TASK)
    // -------------------------------------------------------------
    if ($action === 'delete_task') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de tarefa inválido.']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM tarefas_prazos WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Tarefa excluída com sucesso!']);
        exit;
    }

    // -------------------------------------------------------------
    // 10. OBTER PRAZOS CRÍTICOS & ENTREGAS (GET_CRITICAL_DEADLINES)
    // -------------------------------------------------------------
    if ($action === 'get_critical_deadlines') {
        $sec = getSecaoInfo($db);
        $secTable = $sec['table'];
        $secCol = $sec['name_col'];

        $diasAlerta = (int)($_GET['dias'] ?? 7);

        // Busca tarefas pendentes atrasadas OU vencendo nos próximos N dias OU marcadas como críticas
        $stmt = $db->prepare("
            SELECT t.*, 
                   COALESCE(s.`$secCol`, 'Geral / Todas') as secao_nome,
                   u_resp.war_name as responsavel_war_name,
                   u_resp.name as responsavel_nome,
                   u_resp.grade as responsavel_grade
            FROM tarefas_prazos t
            LEFT JOIN `$secTable` s ON t.secao_id = s.id
            LEFT JOIN users u_resp ON t.responsavel_id = u_resp.id
            WHERE t.status IN ('pendente', 'em_andamento')
              AND (
                  t.data_limite <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                  OR t.prioridade = 'critica'
              )
            ORDER BY (t.data_limite < CURDATE()) DESC, t.data_limite ASC, (t.prioridade = 'critica') DESC
            LIMIT 20
        ");
        $stmt->execute([$diasAlerta]);
        $rows = $stmt->fetchAll();

        $hoje = new DateTime(date('Y-m-d'));
        $criticalTasks = [];

        foreach ($rows as $r) {
            $dtLimite = new DateTime($r['data_limite']);
            $diff = $hoje->diff($dtLimite);
            $diasRestantes = (int)$diff->format('%r%a');
            $isAtrasada = ($diasRestantes < 0);

            $criticalTasks[] = [
                'id' => (int)$r['id'],
                'titulo' => $r['titulo'],
                'descricao' => $r['descricao'],
                'data_limite' => $r['data_limite'],
                'data_limite_fmt' => formatSQLToDate($r['data_limite']),
                'hora_limite' => $r['hora_limite'] ? substr($r['hora_limite'], 0, 5) : '23:59',
                'is_periodica' => (bool)$r['is_periodica'],
                'periodicidade' => $r['periodicidade'],
                'dia_lembrete' => $r['dia_lembrete'],
                'prioridade' => $r['prioridade'],
                'status' => $r['status'],
                'categoria' => $r['categoria'],
                'secao_nome' => $r['secao_nome'],
                'dias_restantes' => $diasRestantes,
                'is_atrasada' => $isAtrasada
            ];
        }

        echo json_encode([
            'success' => true,
            'count' => count($criticalTasks),
            'tasks' => $criticalTasks
        ]);
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
