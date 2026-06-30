<?php
// config.php
// Configurações globais e conexão com o banco SQLite

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

$dbFile = __DIR__ . '/database.sqlite';

try {
    $db = new PDO("sqlite:$dbFile");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}

// Lista de status válidos
$statusList = [
    'P' => 'Presente',
    'A' => 'Ausente',
    'F' => 'Férias',
    'DM' => 'Dispensa Médica',
    'LPM' => 'Licença',
    'SV' => 'Serviço',
    'SSV' => 'Saindo de Serviço',
    'C' => 'Curso',
    'M' => 'Missão',
    'D' => 'Dispensado',
    'EA' => 'Expediente Admin',
    'HO' => 'Home Office',
    'O' => 'Operacional',
    'FR' => 'Feriado',
    'PB' => 'Falta Alerta B',
    'PA' => 'Falta Alerta A',
    'FM' => 'Formatura',
    'FS' => 'Folga Sobreaviso',
    'DP' => 'Dispensa Parcial',
    'INS' => 'Instalação'
];

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
