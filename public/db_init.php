<?php
// db_init.php
// Script de inicialização do banco de dados MySQL e importação de dados do CSV de forma integrada ao SGP

require_once __DIR__ . '/config.php';
$csvFile = __DIR__ . '/Controle de Efetivo JANEIRO-JUNHO 2026 - JUN26.csv';

if (!file_exists($csvFile)) {
    die("Erro: Arquivo CSV não encontrado em: $csvFile\n");
}

try {
    // 1. Adaptar tabela 'militares' existente do SGP e criar novas tabelas
    echo "Verificando e adaptando estrutura de tabelas...\n";

    // Adiciona a coluna 'secao' se não existir
    $colsSecao = $db->query("SHOW COLUMNS FROM militares LIKE 'secao'")->fetchAll();
    if (empty($colsSecao)) {
        $db->exec("ALTER TABLE militares ADD COLUMN secao VARCHAR(100) DEFAULT NULL");
        echo "Coluna 'secao' adicionada à tabela militares.\n";
    }

    // Adiciona a coluna 'escala' se não existir
    $colsEscala = $db->query("SHOW COLUMNS FROM militares LIKE 'escala'")->fetchAll();
    if (empty($colsEscala)) {
        $db->exec("ALTER TABLE militares ADD COLUMN escala TINYINT(1) DEFAULT 0 COMMENT '0: Expediente, 1: Operacional'");
        echo "Coluna 'escala' adicionada à tabela militares.\n";
    }

    // Criar tabelas adicionais se não existirem
    $db->exec("CREATE TABLE IF NOT EXISTS secoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) UNIQUE NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(100) UNIQUE NOT NULL,
        senha_hash VARCHAR(255) NOT NULL,
        nome VARCHAR(255) NOT NULL,
        perfil ENUM('encarregado', 'chefia', 'admin') NOT NULL,
        secao VARCHAR(100) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS presencas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        militar_id INT NOT NULL,
        data DATE NOT NULL,
        status VARCHAR(20) NOT NULL,
        FOREIGN KEY(militar_id) REFERENCES militares(id) ON DELETE CASCADE,
        UNIQUE KEY uk_militar_data (militar_id, data)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 2. Inserir usuários padrão (caso não existam)
    echo "Criando/Verificando usuários de acesso...\n";
    $stmtUser = $db->prepare("INSERT IGNORE INTO usuarios (usuario, senha_hash, nome, perfil, secao) VALUES (?, ?, ?, ?, ?)");
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

    // 3. Ler CSV e importar/atualizar militares e presenças do histórico de Junho de 2026
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

    $stmtSecao = $db->prepare("INSERT IGNORE INTO secoes (nome) VALUES (?)");
    $stmtCheckMilitar = $db->prepare("SELECT id FROM militares WHERE UPPER(TRIM(nome)) = ?");
    $stmtUpdateMilitar = $db->prepare("UPDATE militares SET secao = ?, escala = ? WHERE id = ?");
    $stmtInsertMilitar = $db->prepare("INSERT INTO militares (nome, secao, escala, posto_grad) VALUES (?, ?, ?, ?)");
    $stmtPresenca = $db->prepare("REPLACE INTO presencas (militar_id, data, status) VALUES (?, ?, ?)");

    while (($row = fgetcsv($handle, 0, ",")) !== false) {
        $lineCount++;
        if ($lineCount < 5) {
            continue;
        }

        if ($lineCount == 57 || (isset($row[3]) && trim($row[3]) == 'TWR')) {
            $isOperational = 1;
        }

        $section = isset($row[3]) ? trim($row[3]) : '';
        $name = isset($row[4]) ? trim($row[4]) : '';

        if (strpos(strtolower($name), 'presentes') !== false ||
            strpos(strtolower($name), 'férias') !== false ||
            strpos(strtolower($name), 'dispensa') !== false ||
            strpos(strtolower($name), 'escala opr') !== false ||
            strpos(strtolower($name), 'curso ou missão') !== false ||
            ($name === '' && $section === '')) {
            continue;
        }

        if ($section !== '') {
            $currentSection = $section;
            $stmtSecao->execute([$currentSection]);
            if (in_array($currentSection, ['TWR', 'AIS', 'EMS'])) {
                $isOperational = 1;
            } else {
                // Se voltou para seções administrativas depois da TWR/AIS/EMS na leitura
                if ($lineCount < 57 && !in_array($currentSection, ['TWR', 'AIS', 'EMS'])) {
                    $isOperational = 0;
                }
            }
        }

        if ($name !== '') {
            $normalizedName = mb_strtoupper($name, 'UTF-8');
            
            // Verifica se o militar já existe no banco do SGP
            $stmtCheckMilitar->execute([$normalizedName]);
            $existingMilitar = $stmtCheckMilitar->fetch();

            if ($existingMilitar) {
                $militarId = $existingMilitar['id'];
                // Apenas atualiza a seção e a escala na ficha dele
                $stmtUpdateMilitar->execute([$currentSection, $isOperational, $militarId]);
            } else {
                // Insere novo militar (adicionando posto_grad padrão obrigatório)
                $stmtInsertMilitar->execute([$normalizedName, $currentSection, $isOperational, 'MILITAR']);
                $militarId = $db->lastInsertId();
            }

            // Lançar históricos de presenças
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
    echo "Banco de dados integrado ao MySQL do SGP com sucesso!\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    die("Erro na inicialização: " . $e->getMessage() . "\n");
}
