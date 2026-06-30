<?php
// db_init.php
// Script de inicialização do banco de dados SQLite e importação de dados do CSV

$dbFile = __DIR__ . '/database.sqlite';
$csvFile = __DIR__ . '/Controle de Efetivo JANEIRO-JUNHO 2026 - JUN26.csv';

if (!file_exists($csvFile)) {
    die("Erro: Arquivo CSV não encontrado em: $csvFile\n");
}

try {
    $db = new PDO("sqlite:$dbFile");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Criar tabelas
    echo "Criando tabelas...\n";
    $db->exec("DROP TABLE IF EXISTS presencas");
    $db->exec("DROP TABLE IF EXISTS militares");
    $db->exec("DROP TABLE IF EXISTS usuarios");

    $db->exec("CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario TEXT UNIQUE NOT NULL,
        senha_hash TEXT NOT NULL,
        nome TEXT NOT NULL,
        perfil TEXT NOT NULL CHECK(perfil IN ('encarregado', 'chefia', 'admin')),
        secao TEXT
    )");

    $db->exec("CREATE TABLE militares (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        secao TEXT NOT NULL,
        escala INTEGER DEFAULT 0 -- 0: Expediente, 1: Operacional (TWR, AIS, EMS)
    )");

    $db->exec("CREATE TABLE presencas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        militar_id INTEGER NOT NULL,
        data TEXT NOT NULL, -- Formato YYYY-MM-DD
        status TEXT NOT NULL,
        FOREIGN KEY(militar_id) REFERENCES militares(id),
        UNIQUE(militar_id, data)
    )");

    // 2. Inserir usuários padrão
    echo "Criando usuários de acesso...\n";
    $stmtUser = $db->prepare("INSERT INTO usuarios (usuario, senha_hash, nome, perfil, secao) VALUES (?, ?, ?, ?, ?)");
    // Senha padrão: senha123
    $hash = password_hash('senha123', PASSWORD_DEFAULT);
    $stmtUser->execute(['admin', $hash, 'Administrador do Sistema', 'admin', null]);
    $stmtUser->execute(['chefe', $hash, 'Chefe do DTCEA-SJ', 'chefia', null]);
    $stmtUser->execute(['encarregado', $hash, 'Encarregado Geral', 'encarregado', null]);
    $stmtUser->execute(['encarregado_selm', $hash, 'Encarregado da SELM', 'encarregado', 'SELM']);
    $stmtUser->execute(['encarregado_selt', $hash, 'Encarregado da SELT', 'encarregado', 'SELT']);
    $stmtUser->execute(['encarregado_ssti', $hash, 'Encarregado da SSTI', 'encarregado', 'SSTI']);
    $stmtUser->execute(['encarregado_twr', $hash, 'Encarregado da TWR', 'encarregado', 'TWR']);
    $stmtUser->execute(['encarregado_ais', $hash, 'Encarregado da AIS', 'encarregado', 'AIS']);
    $stmtUser->execute(['encarregado_ems', $hash, 'Encarregado da EMS', 'encarregado', 'EMS']);

    // 3. Ler CSV e importar militares e presenças do histórico de Junho de 2026
    echo "Importando dados do CSV...\n";
    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        throw new Exception("Não foi possível abrir o arquivo CSV.");
    }

    $currentSection = '';
    $isOperational = 0;
    
    // Mapeamento das colunas de dias para o respectivo dia de Junho de 2026
    $dayColumns = [
        5 => '01', 6 => '02', 7 => '03', 8 => '04', 9 => '05',
        12 => '08', 13 => '09', 14 => '10', 15 => '11', 16 => '12',
        19 => '15', 20 => '16', 21 => '17', 22 => '18', 23 => '19',
        26 => '22', 27 => '23', 28 => '24', 29 => '25', 30 => '26',
        33 => '29', 34 => '30'
    ];

    $lineCount = 0;
    $db->beginTransaction();

    $stmtMilitar = $db->prepare("INSERT INTO militares (nome, secao, escala) VALUES (?, ?, ?)");
    $stmtPresenca = $db->prepare("INSERT OR REPLACE INTO presencas (militar_id, data, status) VALUES (?, ?, ?)");

    while (($row = fgetcsv($handle, 0, ",")) !== false) {
        $lineCount++;
        // Pular cabeçalhos iniciais até a linha 5 (onde começa a listagem administrativa)
        if ($lineCount < 5) {
            continue;
        }

        // Detectar se chegamos na seção de escalas operacionais
        // Linhas de separação ou cabeçalho de escala operacional
        if ($lineCount == 57 || (isset($row[3]) && trim($row[3]) == 'TWR')) {
            $isOperational = 1;
        }

        $section = isset($row[3]) ? trim($row[3]) : '';
        $name = isset($row[4]) ? trim($row[4]) : '';

        // Ignorar linhas de resumo ou rodapé
        if (strpos(strtolower($name), 'presentes') !== false ||
            strpos(strtolower($name), 'férias') !== false ||
            strpos(strtolower($name), 'dispensa') !== false ||
            strpos(strtolower($name), 'escala opr') !== false ||
            strpos(strtolower($name), 'curso ou missão') !== false ||
            ($name === '' && $section === '')) {
            continue;
        }

        // Atualizar seção se houver uma nova informada
        if ($section !== '') {
            $currentSection = $section;
            // Se for TWR, AIS ou EMS, força escala operacional
            if (in_array($currentSection, ['TWR', 'AIS', 'EMS'])) {
                $isOperational = 1;
            }
        }

        if ($name !== '') {
            // Inserir militar
            $stmtMilitar->execute([$name, $currentSection, $isOperational]);
            $militarId = $db->lastInsertId();

            // Lançar históricos de presenças para o mês de Junho/2026
            foreach ($dayColumns as $colIdx => $day) {
                if (isset($row[$colIdx]) && trim($row[$colIdx]) !== '' && trim($row[$colIdx]) !== '-') {
                    $status = trim($row[$colIdx]);
                    $date = "2026-06-$day";
                    $stmtPresenca->execute([$militarId, $date, $status]);
                }
            }
        }
    }

    $db->commit();
    fclose($handle);
    echo "Banco de dados inicializado e populado com sucesso!\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    die("Erro na inicialização: " . $e->getMessage() . "\n");
}
