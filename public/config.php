<?php
// config.php
// Configurações globais e conexão com o banco MySQL

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

// Carregar variáveis de ambiente do .env (idêntico ao SGP)
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            $value = trim($value, "\"'");
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_DATABASE') ?: 'sgp_dtceasj';
$db_user = getenv('DB_USERNAME') ?: 'root';
$db_pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

try {
    $db = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados MySQL: " . $e->getMessage());
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
    'PB' => 'Falert B',
    'PA' => 'Falert A',
    'FM' => 'Formatura',
    'FS' => 'Folga Sobreaviso',
    'DP' => 'Dispensa Parcial',
    'INS' => 'Instalação'
];

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
