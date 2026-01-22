document.addEventListener('DOMContentLoaded', () => {
    const btnAccept = document.getElementById('btn-accept');
    const btnRefuse = document.getElementById('btn-refuse');
    const statusMsg = document.getElementById('status-msg');

    // Função para redirecionar
    const doRedirect = (url) => {
        window.location.replace(url); // 'replace' evita que o botão 'voltar' reabra o formulário
    };

    // 1. Fluxo de Recusa
    btnRefuse.addEventListener('click', () => {
        statusMsg.textContent = "Redirecionando sem coleta...";
        statusMsg.classList.remove('hidden');
        
        // Avisa o backend apenas para pegar a URL, sinalizando recusa
        fetch('logger.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ consent: false })
        })
        .then(res => res.json())
        .then(data => doRedirect(data.redirectUrl))
        .catch(() => alert('Erro ao processar solicitação.'));
    });

    // 2. Fluxo de Aceite
    btnAccept.addEventListener('click', () => {
        statusMsg.textContent = "Registrando e redirecionando...";
        statusMsg.classList.remove('hidden');
        btnAccept.disabled = true;
        btnRefuse.disabled = true;

        // Coleta de Fingerprint Básico (Não invasivo)
        const fingerprint = {
            userAgent: navigator.userAgent,
            language: navigator.language,
            platform: navigator.platform,
            screenRes: `${window.screen.width}x${window.screen.height}`,
            colorDepth: window.screen.colorDepth,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            referrer: document.referrer || 'Direto'
        };

        // Envio para o Backend
        fetch('logger.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                consent: true,
                fingerprint: fingerprint
            })
        })
        .then(response => {
            if (!response.ok) throw new Error('Erro na rede');
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                doRedirect(data.redirectUrl);
            } else {
                throw new Error('Erro no registro');
            }
        })
        .catch(error => {
            console.error('Falha:', error);
            // Fallback: Redireciona mesmo se o log falhar (UX preservation)
            // Mas avisa que houve erro
            statusMsg.textContent = "Erro ao registrar. Redirecionando mesmo assim...";
            setTimeout(() => doRedirect('https://www.instagram.com'), 1000); 
        });
    });
});