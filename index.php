<?php
/**
 * SOLUÇÃO DE REDIRECIONAMENTO COM LOG E CONSENTIMENTO (ARQUIVO ÚNICO)
 * Foco: LGPD, Privacidade e Simplicidade.
 */

// ==========================================
// 1. CONFIGURAÇÃO
// ==========================================
$URL_DESTINO = "https://www.google.com.br"; // Para onde o usuário vai após o processo
$ARQUIVO_LOG = "registros_seguros.php";     // Nome do arquivo de log (extensão .php para segurança)

// ==========================================
// 2. LÓGICA DE BACKEND (Processa o POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Recebe o JSON do JavaScript
    $input = json_decode(file_get_contents('php://input'), true);

    // Se o usuário recusou ou não enviou consentimento, apenas devolve a URL
    if (!isset($input['consent']) || $input['consent'] !== true) {
        echo json_encode(['status' => 'skipped', 'url' => $URL_DESTINO]);
        exit;
    }

    // Coleta de IP (Tenta pegar o real caso use Cloudflare/Proxy)
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
    }

    // Prepara os dados
    $timestamp = date('Y-m-d H:i:s');
    $fingerprint = isset($input['data']) ? $input['data'] : [];
    
    // Sanitiza para evitar XSS no log se for lido em painel web futuro
    $fingerprint = array_map('htmlspecialchars', $fingerprint);

    // Cria a linha de log
    $logLine = sprintf("[%s] IP: %s | DADOS: %s" . PHP_EOL, $timestamp, $ip, json_encode($fingerprint, JSON_UNESCAPED_UNICODE));

    // TRUQUE DE SEGURANÇA:
    // Se o arquivo não existe, cria com um cabeçalho PHP que impede leitura via navegador
    if (!file_exists($ARQUIVO_LOG)) {
        $header = "<?php header('HTTP/1.0 403 Forbidden'); exit; ?>\n";
        $header .= "# LOG DE ACESSOS - INICIO\n";
        file_put_contents($ARQUIVO_LOG, $header);
    }

    // Grava o log (Append)
    if (file_put_contents($ARQUIVO_LOG, $logLine, FILE_APPEND | LOCK_EX)) {
        echo json_encode(['status' => 'success', 'url' => $URL_DESTINO]);
    } else {
        // Se falhar o log, redireciona mesmo assim para não travar o usuário
        echo json_encode(['status' => 'error', 'url' => $URL_DESTINO]);
    }
    exit; // Encerra o script para não renderizar o HTML abaixo
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Segurança</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            color: #333;
        }
        .card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 480px;
            width: 90%;
            text-align: center;
        }
        h1 { font-size: 1.5rem; margin-bottom: 1rem; color: #1a1a1a; }
        p { margin-bottom: 1.5rem; line-height: 1.6; color: #555; }
        .details {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 1rem;
            text-align: left;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }
        .details ul { margin: 0; padding-left: 20px; }
        .details li { margin-bottom: 4px; }
        .btn-group { display: flex; gap: 10px; justify-content: center; }
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: opacity 0.2s;
            font-weight: 500;
        }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-accept { background-color: #0070f3; color: white; }
        .btn-deny { background-color: #e5e5e5; color: #333; }
        .status { margin-top: 15px; font-size: 0.9rem; color: #666; min-height: 20px; }
    </style>
</head>
<body>

<div class="card">
    <h1>Controle de Acesso</h1>
    <p>Para prosseguir, solicitamos seu consentimento para registrar dados técnicos de acesso. Isso nos ajuda a manter a segurança e gerar estatísticas.</p>
    
    <div class="details">
        <strong>O que será coletado:</strong>
        <ul>
            <li>Endereço IP e Provedor (ISP)</li>
            <li>Tipo de dispositivo e Sistema Operacional</li>
            <li>Resolução da tela e Fuso horário</li>
        </ul>
        <div style="margin-top:8px; font-size:0.75rem; color:#888;">
            * Nenhum dado pessoal direto (nome, email, cpf) é solicitado.
        </div>
    </div>

    <div class="btn-group">
        <button class="btn-deny" onclick="handleConsent(false)">Recusar</button>
        <button class="btn-accept" onclick="handleConsent(true)">Aceitar e Continuar</button>
    </div>
    
    <div class="status" id="statusMsg"></div>
</div>

<script>
    function handleConsent(allowed) {
        const statusDiv = document.getElementById('statusMsg');
        const btns = document.querySelectorAll('button');
        
        // Bloqueia botões para evitar duplo clique
        btns.forEach(b => b.disabled = true);
        
        statusDiv.innerText = allowed ? "Registrando acesso..." : "Redirecionando sem registro...";

        // Dados do Fingerprint (só gerados se aceitar, mas a estrutura vai vazia se negar)
        let payload = { consent: allowed };

        if (allowed) {
            payload.data = {
                userAgent: navigator.userAgent,
                language: navigator.language,
                platform: navigator.platform,
                screen: window.screen.width + 'x' + window.screen.height,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                referrer: document.referrer
            };
        }

        // Envia para o PRÓPRIO arquivo PHP
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.url) {
                window.location.replace(data.url);
            } else {
                // Fallback de segurança
                statusDiv.innerText = "Erro. Redirecionando manual...";
                setTimeout(() => window.location.href = "<?php echo $URL_DESTINO; ?>", 1500);
            }
        })
        .catch(err => {
            console.error(err);
            statusDiv.innerText = "Erro de conexão. Redirecionando...";
            setTimeout(() => window.location.href = "<?php echo $URL_DESTINO; ?>", 1000);
        });
    }
</script>

</body>
</html>