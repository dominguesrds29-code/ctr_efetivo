<?php
// sync_prod_dados_csv.php
// Script SEGURO para PRODUÇÃO:
// 1. NÃO CRIA NENHUM NOVO USUÁRIO.
// 2. NÃO SOBRESCREVE NENHUM DADO QUE JÁ EXISTA (NÃO VAZIO).
// 3. APENAS PREENCHE CAMPOS VAZIOS / NULOS COM DADOS DO dados.csv.
// 4. Suporta modo SIMULAÇÃO (--dry-run) para auditar antes de aplicar.

require_once __DIR__ . '/config.php';

// Verificar se é execução via CLI ou WEB
$isCli = (php_sapi_name() === 'cli');
$dryRun = false;

if ($isCli) {
    global $argv;
    if (in_array('--dry-run', $argv ?? []) || in_array('-d', $argv ?? [])) {
        $dryRun = true;
    }
} else {
    // Se acessado via navegador, exige autenticação de admin
    require_once __DIR__ . '/auth.php';
    if (!isLoggedIn() || !isAdmin()) {
        die("Acesso restrito a administradores.");
    }
    $dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] == '1';
    header('Content-Type: text/plain; charset=UTF-8');
}

function cleanDigits($str) {
    return preg_replace('/\D/', '', (string)$str);
}

function normalizeName($str) {
    $str = mb_strtoupper(trim((string)$str), 'UTF-8');
    return preg_replace('/\s+/', ' ', $str);
}

function isEmptyField($val) {
    if ($val === null) return true;
    $val = trim((string)$val);
    return ($val === '' || $val === '0000-00-00' || $val === '-' || $val === 'null');
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

echo "======================================================================\n";
echo "  SINCRONIZAÇÃO SEGURA PARA PRODUÇÃO (dados.csv -> MySQL)            \n";
echo "======================================================================\n";
echo " MODO: " . ($dryRun ? "SIMULAÇÃO / AUDITORIA (--dry-run) - NADA SERÁ SALVO" : "APLICAÇÃO REAL NO BANCO DE DADOS") . "\n";
echo " REGRAS:\n";
echo "   1. [x] NENHUM novo usuário será cadastrado.\n";
echo "   2. [x] NENHUM dado preenchido anteriormente será sobrescrito.\n";
echo "   3. [x] APENAS campos VAZIOS/NULOS receberão novos dados do CSV.\n";
echo "======================================================================\n\n";

$csvFile = __DIR__ . '/dados.csv';
if (!file_exists($csvFile)) {
    die("ERRO: Arquivo dados.csv não encontrado no diretório " . __DIR__ . "\n");
}

$handle = fopen($csvFile, 'r');
if (!$handle) {
    die("ERRO: Não foi possível abrir o arquivo dados.csv.\n");
}

// 1. Carregar todos os usuários do banco em memória
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

$totalEncontrados = 0;
$totalIgnoradosNaoExistem = 0;
$totalComCamposAtualizados = 0;
$totalSemNecessidadeAtualizacao = 0;

if (!$dryRun) {
    $db->beginTransaction();
}

$rowIdx = 0;
while (($row = fgetcsv($handle, 1000, ",")) !== false) {
    $rowIdx++;
    if (empty(array_filter($row))) continue;

    $rawPosto = trim($row[1] ?? '');
    list($gradeCsv, $specCsv) = parsePostoSpec($rawPosto);

    $nomeCsv = normalizeName($row[2] ?? '');
    $identidadeCsv = trim($row[3] ?? '');
    $cpfCsv = trim($row[4] ?? '');
    $saramCsv = trim($row[5] ?? '');
    $nascCsv = parseDateBr($row[6] ?? '');
    $pracaCsv = parseDateBr($row[7] ?? '');
    $ultPromCsv = parseDateBr($row[8] ?? '');
    $apresCsv = parseDateBr($row[9] ?? '');

    if (empty($nomeCsv)) continue;

    $cleanS = cleanDigits($saramCsv);
    $cleanC = cleanDigits($cpfCsv);

    // Buscar no banco
    $user = null;
    $matchType = '';

    if (!empty($cleanS) && isset($dbBySaram[$cleanS])) {
        $user = $dbBySaram[$cleanS];
        $matchType = "SARAM";
    } elseif (!empty($cleanC) && isset($dbByCpf[$cleanC])) {
        $user = $dbByCpf[$cleanC];
        $matchType = "CPF";
    } elseif (isset($dbByName[$nomeCsv])) {
        $user = $dbByName[$nomeCsv];
        $matchType = "NOME";
    }

    // REGRA 1: SE NÃO EXISTIR NO BANCO, IGNORAR (NÃO CRIAR)
    if (!$user) {
        $totalIgnoradosNaoExistem++;
        echo "[IGNORADO - NÃO CADASTRADO NO BANCO] {$rawPosto} {$nomeCsv} (SARAM: {$saramCsv})\n";
        continue;
    }

    $totalEncontrados++;
    $userId = (int)$user['id'];
    $fieldsToUpdate = [];
    $updateParams = [];
    $changesSummary = [];

    // REGRA 2 e 3: APENAS PREENCHER SE O CAMPO ESTIVER VAZIO NO BANCO E HOUVER VALOR NO CSV

    // 1. SARAM
    if (isEmptyField($user['saram']) && !empty($saramCsv)) {
        $fieldsToUpdate[] = "saram = ?";
        $updateParams[] = $saramCsv;
        $changesSummary[] = "SARAM preenchido: '{$saramCsv}'";
    }

    // 2. CPF
    if (isEmptyField($user['cpf']) && !empty($cpfCsv)) {
        $fieldsToUpdate[] = "cpf = ?";
        $updateParams[] = $cpfCsv;
        $changesSummary[] = "CPF preenchido: '{$cpfCsv}'";
    }

    // 3. Identidade
    if (isEmptyField($user['identidade']) && !empty($identidadeCsv)) {
        $fieldsToUpdate[] = "identidade = ?";
        $updateParams[] = $identidadeCsv;
        $changesSummary[] = "Identidade preenchida: '{$identidadeCsv}'";
    }

    // 4. Data de Nascimento
    if (isEmptyField($user['birth_date']) && !empty($nascCsv)) {
        $fieldsToUpdate[] = "birth_date = ?";
        $updateParams[] = $nascCsv;
        $changesSummary[] = "Nascimento preenchido: '{$nascCsv}'";
    }

    // 5. Praça
    if (isEmptyField($user['praca']) && !empty($pracaCsv)) {
        $fieldsToUpdate[] = "praca = ?";
        $updateParams[] = $pracaCsv;
        $changesSummary[] = "Praça preenchida: '{$pracaCsv}'";
    }

    // 6. Última Promoção
    if (isEmptyField($user['ult_promocao']) && !empty($ultPromCsv)) {
        $fieldsToUpdate[] = "ult_promocao = ?";
        $updateParams[] = $ultPromCsv;
        $changesSummary[] = "Últ. Promoção preenchida: '{$ultPromCsv}'";
    }

    // 7. Apresentação no DTCEA
    if (isEmptyField($user['apresentacao_dtcea']) && !empty($apresCsv)) {
        $fieldsToUpdate[] = "apresentacao_dtcea = ?";
        $updateParams[] = $apresCsv;
        $changesSummary[] = "Apresentação DTCEA preenchida: '{$apresCsv}'";
    }

    // 8. Posto / Graduação
    if (isEmptyField($user['grade']) && !empty($gradeCsv)) {
        $fieldsToUpdate[] = "grade = ?";
        $updateParams[] = $gradeCsv;
        $changesSummary[] = "Posto/Grad preenchido: '{$gradeCsv}'";
    }

    // 9. Especialidade
    if (isEmptyField($user['specialty']) && !empty($specCsv)) {
        $fieldsToUpdate[] = "specialty = ?";
        $updateParams[] = $specCsv;
        $changesSummary[] = "Especialidade preenchida: '{$specCsv}'";
    }

    // 10. Nome de Guerra
    if (isEmptyField($user['war_name'])) {
        $warName = inferWarName($user['name'] ?: $nomeCsv);
        if (!empty($warName)) {
            $fieldsToUpdate[] = "war_name = ?";
            $updateParams[] = $warName;
            $changesSummary[] = "Nome de Guerra preenchido: '{$warName}'";
        }
    }

    if (empty($fieldsToUpdate)) {
        $totalSemNecessidadeAtualizacao++;
        // Todos os campos já estavam preenchidos
        // echo "[INTACTO] ID #{$userId} | {$user['grade']} {$user['name']} (Todos os dados já preenchidos)\n";
    } else {
        $totalComCamposAtualizados++;
        $fieldsToUpdate[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(", ", $fieldsToUpdate) . " WHERE id = ?";
        $updateParams[] = $userId;

        if (!$dryRun) {
            $stmtUp = $db->prepare($sql);
            $stmtUp->execute($updateParams);
        }

        echo "[ENRIQUECIDO] ID #{$userId} | {$user['grade']} {$user['name']} (Match: {$matchType}):\n";
        foreach ($changesSummary as $change) {
            echo "   -> {$change}\n";
        }
    }
}

fclose($handle);

if (!$dryRun) {
    $db->commit();
}

echo "\n======================================================================\n";
echo " RELATÓRIO FINAL DE SINCRONIZAÇÃO\n";
echo "======================================================================\n";
echo " Militares localizados no banco        : {$totalEncontrados}\n";
echo "   - Com campos vazios preenchidos     : {$totalComCamposAtualizados}\n";
echo "   - Já estavam 100% preenchidos       : {$totalSemNecessidadeAtualizacao}\n";
echo " Militares no CSV ignorados (não criados): {$totalIgnoradosNaoExistem}\n";
echo " Novos usuários criados                : 0 (ZERO - Garantido por regra)\n";
echo " Dados sobrescritos                    : 0 (ZERO - Nenhum dado apagado)\n";
echo "======================================================================\n";
if ($dryRun) {
    echo " [AVISO] Executado em modo SIMULAÇÃO (--dry-run). Nada foi gravado.\n";
    echo " Para aplicar no banco de produção, execute sem a flag --dry-run.\n";
} else {
    echo " [SUCESSO] Alterações gravadas com segurança no banco de dados.\n";
}
echo "======================================================================\n";
