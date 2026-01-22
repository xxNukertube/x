<?php
// logger.php
// Configurações básicas
header('Content-Type: application/json');

// Defina aqui a URL de destino final
$DESTINO_FINAL = "https://www.instagram.com/"; // Exemplo

// 1. Verificar método da requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

// 2. Receber dados do Frontend (JSON)
$input = json_decode(file_get_contents('php://input'), true);

// Se o usuário recusou, retornamos apenas a URL para redirecionamento sem logar
if (isset($input['consent']) && $input['consent'] === false) {
    echo json_encode(['status' => 'skipped', 'redirectUrl' => $DESTINO_FINAL]);
    exit;
}

// 3. Coleta de Dados (Backend)
$ip = $_SERVER['REMOTE_ADDR'];
// Tenta pegar IP real se estiver atrás de proxy (Cloudflare/Load Balancer)
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
}

$timestamp = date('Y-m-d H:i:s');
$fingerprint = isset($input['fingerprint']) ? $input['fingerprint'] : [];

// Sanitização básica do fingerprint para evitar injeção de caracteres de controle
$fingerprintSafe = array_map('htmlspecialchars', $fingerprint);

// 4. Formatação da linha de log (Formato TXT legível)
// Estrutura: [DATA] - IP - {DADOS_JSON}
$logEntry = sprintf(
    "[%s] - IP: %s - INFO: %s" . PHP_EOL,
    $timestamp,
    $ip,
    json_encode($fingerprintSafe, JSON_UNESCAPED_UNICODE)
);

// 5. Gravação no Arquivo
$logFile = __DIR__ . '/data/logs.txt';

// Verifica se a pasta data existe, se não, tenta criar
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

// Escreve com LOCK_EX para evitar conflito de escrita simultânea
if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX)) {
    echo json_encode(['status' => 'success', 'redirectUrl' => $DESTINO_FINAL]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Falha na gravação']);
}
?>