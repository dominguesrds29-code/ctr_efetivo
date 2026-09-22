<?php
// db_init.php
// Script de inicialização da infraestrutura do banco de dados efetivosj

require_once __DIR__ . '/config.php';

try {
    echo "Verificando estrutura do banco de dados '" . ($db_name ?? 'efetivosj') . "'...\n";

    $secInfo = getSecaoInfo($db);
    $secTable = $secInfo['table'];
    $secCol = $secInfo['name_col'];

    // 1. Criar tabela de presenças para o controle de efetivo diário
    $db->exec("CREATE TABLE IF NOT EXISTS presencas (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        militar_id BIGINT UNSIGNED NOT NULL,
        data DATE NOT NULL,
        status VARCHAR(20) NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (militar_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY uk_militar_data (militar_id, data)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Tabela 'presencas' verificada/criada com sucesso!\n";

    // 2. Verificar se seções padrão existem se a tabela estiver vazia
    $totalSecoes = $db->query("SELECT COUNT(*) FROM `$secTable`")->fetchColumn();
    if ($totalSecoes == 0) {
        $secoesPadrao = ['SSTI', 'SELT', 'SELM', 'EMS', 'SEC-SO', 'SIATO', 'AIS', 'ASSIPACEA', 'TWR'];
        $stmtSec = $db->prepare("INSERT INTO `$secTable` (`$secCol`) VALUES (?)");
        foreach ($secoesPadrao as $secName) {
            $stmtSec->execute([$secName]);
        }
        echo "Seções padrão inseridas na tabela '$secTable'.\n";
    }

    echo "Banco de dados 'efetivosj' pronto para operação!\n";

} catch (Exception $e) {
    die("Erro na inicialização: " . $e->getMessage() . "\n");
}
