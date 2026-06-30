<?php
// api.php
// Endpoints assíncronos para persistência e relatórios

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_call') {
        $input = json_decode(file_get_contents('php://input'), true);
        $date = sanitize($input['date'] ?? '');
        $presenceData = $input['presencas'] ?? [];

        if (empty($date)) {
            http_response_code(400);
            echo json_encode(['error' => 'Data inválida']);
            exit;
        }

        try {
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
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao salvar chamada: ' . $e->getMessage()]);
        }
        exit;
    } elseif ($action === 'save_period') {
        $input = json_decode(file_get_contents('php://input'), true);
        $militarId = (int)($input['militar_id'] ?? 0);
        $status = sanitize($input['status'] ?? '');
        $dateStart = sanitize($input['date_start'] ?? '');
        $dateEnd = sanitize($input['date_end'] ?? '');

        if ($militarId <= 0 || empty($status) || empty($dateStart) || empty($dateEnd)) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros inválidos']);
            exit;
        }

        try {
            $start = new DateTime($dateStart);
            $end = new DateTime($dateEnd);
            
            if ($start > $end) {
                http_response_code(400);
                echo json_encode(['error' => 'Data de início deve ser anterior ou igual à data de término']);
                exit;
            }

            $end->modify('+1 day'); // Inclui a data final no período
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
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao salvar período: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'Ação não encontrada']);
exit;
