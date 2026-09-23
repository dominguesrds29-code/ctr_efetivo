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

    // 2. Garantir estrutura e dados da tabela sections oficial
    $db->exec("CREATE TABLE IF NOT EXISTS `sections` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `extension_line` varchar(255) DEFAULT NULL,
        `code` varchar(255) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `build` varchar(255) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT NULL,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $sectionsRef = [
        [1, 'Sem Seção', '0000', 'SEM_SECAO', null, null, '2022-08-23 20:09:00', '2022-08-23 20:09:00'],
        [2, 'COMANDO', '6350', 'CMD', 'comando.dtceasj@fab.mil.br', 'CMD', '2022-08-24 10:47:47', '2022-08-24 11:32:33'],
        [3, 'ASSIPACEA', '3452', 'ASSIPACEA', '_assipacea.dtceasj@fab.mil.br', 'AUDITÓRIO', '2022-08-24 10:48:26', '2022-08-24 11:32:01'],
        [4, 'SECRETARIA ADMINISTRATIVA', '3412', 'SEC_SA', '_sec.sa.dtceasj@fab.mil.br', 'ADMINISTRATIVO', '2022-08-24 10:51:20', '2022-08-24 11:33:56'],
        [5, 'INFRAESTRUTURA', '6349', 'SA_SSIS', '_ssie.dtceasj@fab.mil.br', 'ADMINISTRATIVO', '2022-08-24 11:18:09', '2026-07-01 14:37:20'],
        [6, 'SIATO', '3413', 'SA_SIATO', '_siato.dtceasj@fab.mil.br', 'FERRAMENTARIA', '2022-08-24 11:18:49', '2022-08-24 11:33:32'],
        [7, 'TRANSPORTES', '6377', 'SA_SSTS', '_ssts.dtceasj@fab.mil.br', 'ADMINISTRATIVO', '2022-08-24 11:20:03', '2022-08-24 18:58:18'],
        [8, 'TORRE DE CONTROLE', '3411/3496', 'SO_TWR', '_so.dtceasj@fab.mil.br', 'TWR', '2022-08-24 11:22:38', '2022-08-24 11:22:38'],
        [9, 'SECRETARIA OPERACIONAL', '3499', 'SO_SECSO', '_so.dtceasj@fab.mil.br', 'FERRAMENTARIA', '2022-08-24 11:23:41', '2022-08-24 11:23:41'],
        [10, 'EMS-1 / CMA-2', '3414', 'SO_EMS1_CMA2', '_so.dtceasj@fab.mil.br', 'TWR', '2022-08-24 11:26:25', '2022-08-24 11:26:25'],
        [11, 'SALA AIS', '4017', 'SO_AIS', '_so.dtceasj@fab.mil.br', 'TWR', '2022-08-24 11:27:17', '2022-08-24 11:27:17'],
        [12, 'ELETRÔNICA', '4018/4019', 'ST_SELT', '_selt.dtceasj@fab.mil.br', 'SUPRIMENTO', '2022-08-24 11:28:22', '2022-08-24 11:28:22'],
        [13, 'ELETROMECÂNICA', '4020', 'ST_SELM', '_sele.dtceasj@fab.mil.br', 'SUPRIMENTO', '2022-08-24 11:29:11', '2026-07-01 14:38:14'],
        [14, 'SUPRIMENTO', '3416', 'ST_SSUP', '_ssup.dtceasj@fab.mil.br', 'SUPRIMENTO', '2022-08-24 11:30:27', '2026-07-01 14:38:00'],
        [15, 'INFORMÁTICA', '3079/6378', 'ST_SSTI', '_informatica.dtceasj@fab.mil.br', 'ADMINISTRATIVO', '2022-08-24 11:31:19', '2022-08-24 11:31:19']
    ];

    $stmtSec = $db->prepare("REPLACE INTO `sections` (`id`, `name`, `extension_line`, `code`, `email`, `build`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($sectionsRef as $secRow) {
        $stmtSec->execute($secRow);
    }
    echo "Tabela 'sections' sincronizada com sucesso (15 seções).\n";

    echo "Banco de dados 'efetivosj' pronto para operação!\n";

} catch (Exception $e) {
    die("Erro na inicialização: " . $e->getMessage() . "\n");
}

