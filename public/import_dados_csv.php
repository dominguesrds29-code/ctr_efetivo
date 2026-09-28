<?php
// import_dados_csv.php
// Script de importação e sincronização dos dados do dados.csv para o banco de dados MySQL (efetivosj)

require_once __DIR__ . '/config.php';

function cleanDigits($str) {
    return preg_replace('/\D/', '', (string)$str);
}

function normalizeName($str) {
    $str = mb_strtoupper(trim((string)$str), 'UTF-8');
    $str = preg_replace('/\s+/', ' ', $str);
    return $str;
}

function parsePostoSpec($raw) {
    $raw = trim($raw);
    
    if (preg_match('/^CAP\s+(?:QOE\s+)?([A-Z0-9]+)/i', $raw, $m)) {
        return ['CAP', $m[1]];
    }
    if (preg_match('/^(?:1[º°]\s*TEN|1T)\s+(?:QOEA\s+|ESP\s+AER\s+)?([A-Z0-9]+)/i', $raw, $m)) {
        return ['1T', $m[1]];
    }
    if (preg_match('/^SO\s+R\/1\s+([A-Z0-9]+)/i', $raw, $m)) {
        return ['SO R/1', $m[1]];
    }
    if (preg_match('/^(SO|1S|2S|3S|CB|S1|S2|TEN|1T|2T|CAP|MAJ|TC|CEL)\s+(.+)$/i', $raw, $m)) {
        return [trim($m[1]), trim($m[2])];
    }
    
    $parts = explode(' ', $raw);
    $grade = array_shift($parts);
    $spec = implode(' ', $parts);
    return [$grade ?: 'SO', $spec ?: ''];
}

function parseDateBr($dateStr) {
    $dateStr = trim((string)$dateStr);
    if (empty($dateStr) || $dateStr === '-' || $dateStr === 'N/A' || $dateStr === 'null') return null;
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $dateStr, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }
    return null;
}

function inferWarName($fullName) {
    $parts = explode(' ', trim($fullName));
    $parts = array_filter($parts, function($p) {
        $p = mb_strtoupper($p, 'UTF-8');
        return !in_array($p, ['DE', 'DA', 'DO', 'DOS', 'DAS', 'E', 'JUNIOR', 'JÚNIOR', 'FILHO', 'NETO', 'SOBRINHO']);
    });
    $last = end($parts);
    return $last ? mb_strtoupper($last, 'UTF-8') : '';
}

try {
    echo "========================================================\n";
    echo " INICIANDO CARGA E SINCRONIZAÇÃO DE DADOS (dados.csv)   \n";
    echo "========================================================\n\n";

    $csvFile = __DIR__ . '/dados.csv';
    if (!file_exists($csvFile)) {
        die("ERRO: Arquivo dados.csv não encontrado no diretório " . __DIR__ . "\n");
    }

    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        die("ERRO: Não foi possível abrir o arquivo dados.csv.\n");
    }

    // Carregar todos os usuários atuais para indexação em memória
    $stmtUsers = $db->query("SELECT * FROM users");
    $dbUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $dbBySaram = [];
    $dbByCpf = [];
    $dbByName = [];

    foreach ($dbUsers as $u) {
        $cleanS = cleanDigits($u['saram']);
        if (!empty($cleanS)) $dbBySaram[$cleanS] = $u;

        $cleanC = cleanDigits($u['cpf']);
        if (!empty($cleanC)) $dbByCpf[$cleanC] = $u;

        $normN = normalizeName($u['name']);
        if (!empty($normN)) $dbByName[$normN] = $u;
    }

    $header = fgetcsv($handle, 1000, ",");
    
    $updatedCount = 0;
    $insertedCount = 0;
    $errors = [];

    $stmtUpdate = $db->prepare("
        UPDATE users SET 
            grade = COALESCE(NULLIF(?, ''), grade),
            specialty = COALESCE(NULLIF(?, ''), specialty),
            name = ?,
            war_name = COALESCE(NULLIF(war_name, ''), ?),
            identidade = COALESCE(NULLIF(?, ''), identidade),
            cpf = COALESCE(NULLIF(?, ''), cpf),
            saram = COALESCE(NULLIF(?, ''), saram),
            birth_date = COALESCE(?, birth_date),
            praca = COALESCE(?, praca),
            ult_promocao = COALESCE(?, ult_promocao),
            apresentacao_dtcea = COALESCE(?, apresentacao_dtcea),
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmtInsert = $db->prepare("
        INSERT INTO users (
            grade, specialty, name, war_name, identidade, cpf, saram,
            birth_date, praca, ult_promocao, apresentacao_dtcea,
            section_id, email, password, is_admin, escala, person_type, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, 0, 0, 'MILITAR', NOW(), NOW()
        )
    ");

    $db->beginTransaction();

    $rowIdx = 1; // após o cabeçalho
    while (($row = fgetcsv($handle, 1000, ",")) !== false) {
        $rowIdx++;
        if (empty(array_filter($row))) continue;

        $rawPosto = trim($row[1] ?? '');
        list($grade, $spec) = parsePostoSpec($rawPosto);

        $nome = normalizeName($row[2] ?? '');
        $identidade = trim($row[3] ?? '');
        $cpf = trim($row[4] ?? '');
        $saram = trim($row[5] ?? '');
        $nasc = parseDateBr($row[6] ?? '');
        $praca = parseDateBr($row[7] ?? '');
        $ultProm = parseDateBr($row[8] ?? '');
        $apres = parseDateBr($row[9] ?? '');

        if (empty($nome)) {
            continue;
        }

        $cleanS = cleanDigits($saram);
        $cleanC = cleanDigits($cpf);

        // Busca por correspondência
        $matchedUser = null;
        $matchType = '';

        if (!empty($cleanS) && isset($dbBySaram[$cleanS])) {
            $matchedUser = $dbBySaram[$cleanS];
            $matchType = "SARAM";
        } elseif (!empty($cleanC) && isset($dbByCpf[$cleanC])) {
            $matchedUser = $dbByCpf[$cleanC];
            $matchType = "CPF";
        } elseif (isset($dbByName[$nome])) {
            $matchedUser = $dbByName[$nome];
            $matchType = "NOME";
        }

        $warName = inferWarName($nome);

        if ($matchedUser) {
            // ATUALIZAR USUÁRIO EXISTENTE
            $userId = (int)$matchedUser['id'];
            $stmtUpdate->execute([
                $grade,
                $spec,
                $nome,
                $warName,
                $identidade,
                $cpf,
                $saram,
                $nasc,
                $praca,
                $ultProm,
                $apres,
                $userId
            ]);

            echo "[ATUALIZADO] ID #{$userId} | {$grade} {$spec} - {$nome} (Match via {$matchType})\n";
            $updatedCount++;
        } else {
            // CADASTRAR NOVO USUÁRIO
            $email = !empty($saram) ? $saram . '@fab.mil.br' : uniqid('militar_') . '@fab.mil.br';
            $passwordHash = password_hash('123456', PASSWORD_DEFAULT);
            $defaultSectionId = 1; // Sem Seção

            $stmtInsert->execute([
                $grade,
                $spec,
                $nome,
                $warName,
                $identidade,
                $cpf,
                $saram,
                $nasc,
                $praca,
                $ultProm,
                $apres,
                $defaultSectionId,
                $email,
                $passwordHash
            ]);

            $newId = (int)$db->lastInsertId();
            echo "[CADASTRADO] ID #{$newId} | {$grade} {$spec} - {$nome} (SARAM: {$saram})\n";
            $insertedCount++;

            // Adicionar ao mapa em memória para evitar duplicatas dentro do próprio CSV
            $newUserRow = [
                'id' => $newId,
                'saram' => $saram,
                'cpf' => $cpf,
                'name' => $nome
            ];
            if (!empty($cleanS)) $dbBySaram[$cleanS] = $newUserRow;
            if (!empty($cleanC)) $dbByCpf[$cleanC] = $newUserRow;
            $dbByName[$nome] = $newUserRow;
        }
    }

    fclose($handle);

    $db->commit();

    echo "\n========================================================\n";
    echo " IMPORTAÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "--------------------------------------------------------\n";
    echo " Militares atualizados (já existentes): {$updatedCount}\n";
    echo " Novos militares cadastrados          : {$insertedCount}\n";
    echo " Total processado                     : " . ($updatedCount + $insertedCount) . "\n";
    echo "========================================================\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n[ERRO CRÍTICO] " . $e->getMessage() . "\n";
}
