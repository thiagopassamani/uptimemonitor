
<?php
// Carrega o motor do GLPI (o caminho assume que o arquivo está na pasta /front/)
include ("../../../inc/includes.php");

// Verifica se o usuário está logado no GLPI (mesmo para a TV, é uma boa prática de segurança)
Session::checkLoginUser();

// Retorna apenas os dados em JSON (Atualização em Background)
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    global $DB;
    $monitors = [];

    //$date_limit = date("Y-m-d H:i:s", strtotime("-24 hours"));

    $iterator = $DB->request([
        'SELECT' => [
            'm.id', 
            'm.name', 
            'm.type', 
            'm.url', 
            'm.last_status', 
            'm.is_maintenance',
            new \QueryExpression('COUNT(l.id) AS total_logs'),
            new \QueryExpression("SUM(CASE WHEN l.status != 'MAINT' OR l.status IS NULL THEN 1 ELSE 0 END) AS maint_count"),
            new \QueryExpression('SUM(CASE WHEN l.status = "UP" THEN 1 ELSE 0 END) AS up_count')

            //new \QueryExpression("COUNT(CASE WHEN l.date > ". $date_limit ." THEN l.id ELSE NULL END) AS total_logs"),
            //new \QueryExpression("SUM(CASE WHEN l.status = 'UP' AND l.date > " . $date_limit ." THEN 1 ELSE 0 END) AS up_count")
        ],
        'FROM'      => 'glpi_plugin_uptimemonitor_monitors AS m',
        'LEFT JOIN' => [
            'glpi_plugin_uptimemonitor_logs AS l' => [
                'ON' => [
                    'l' => 'plugin_uptimemonitor_monitors_id',
                    'm' => 'id'
                ]
            ]
        ],
        'WHERE'     => [
            'm.is_active' => 1, 
            'm.is_noc'    => 1
        ],
        'GROUPBY'   => [
            'm.id', 'm.name', 'm.type', 'm.url', 'm.last_status', 'm.is_maintenance'
        ],
        'ORDER'     => [
            'm.last_status ASC', 
            'm.name ASC'
        ]
    ]);
    
    // Verificação de erro robusta
    if (!$iterator || $DB->error()) {
        header('Content-Type: application/json', true, 500);
        echo json_encode([
            'error' => true,
            'message' => $DB->error(),
            'sql' => $DB->last_query() // Útil para depurar o que o GLPI montou
        ]);
        exit;
    }

    foreach ($iterator as $row) {
        // Garantir que os valores sejam numéricos para o cálculo
        $total = (int)$row['total_logs'];
        $up    = (int)$row['up_count'];

        $row['uptime_percent'] = ($total > 0) 
            ? round(($up / $total) * 100, 2) 
            : 100; // Se não houver logs nas últimas 24h, exibe 100% ou "-" conforme sua preferência
            
        $monitors[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($monitors);
    exit;
    
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOC - Uptime Monitor</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Estilo Base - Dark Mode */
        body {
            margin: 0;
            padding: 20px;
            background-color: #121212; 
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        /* Cabeçalho Limpo */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            color: #e0e0e0;
        }
        .clock {
            font-size: 24px;
            font-weight: bold;
            color: #aaa;
            letter-spacing: 2px;
        }

        /* Grid Responsiva dos Monitores */
        .grid-container {
            display: grid;
            /* Na TV, os blocos se ajustarão sozinhos. Minimo de 280px por bloco */
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        /* Design dos Cards */
        .card {
            background-color: #1e1e1e;
            border-radius: 10px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
            transition: transform 0.2s;
            border-top: 6px solid #555; /* Borda padrão */
        }
        
        /* Cores Dinâmicas de Status */
        .card.status-up {
            border-top-color: #2ecc71; /* Verde */
            background-color: rgba(46, 204, 113, 0.05);
        }
        .card.status-down {
            border-top-color: #e74c3c; /* Vermelho */
            background-color: rgba(231, 76, 60, 0.15);
            animation: pulse-red 2s infinite; /* Faz piscar suavemente se estiver fora */
        }
        .card.status-maintenance {
            border-top-color: #f39c12; /* Laranja */
            background-color: rgba(243, 156, 18, 0.1);
            opacity: 0.7;
        }

        /* Tipografia dos Cards */
        .card-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .card-icon {
            font-size: 45px;
            margin: 15px 0;
        }
        .card.status-up .card-icon { color: #2ecc71; }
        .card.status-down .card-icon { color: #e74c3c; }
        .card.status-maintenance .card-icon { color: #f39c12; }
        
        .status-text { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .card-url { font-size: 13px; color: #777; margin-top: 15px; word-break: break-all; }

        /* Barra de Progresso no Topo */
        #progress-bar-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px; /* Altura sutil */
            background-color: #333; /* Cor de fundo da trilha */
            z-index: 1000;
        }
        
        #progress-bar {
            height: 100%;
            width: 0%;
            /* Usa o mesmo verde do status UP para harmonizar com o tema */
            background-color: #2ecc71; 
            transition: width 0.1s linear; /* Animação suave */
        }

        /* Estilo do Botão de Mudo */
        #mute-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #333;
            border-radius: 5px;
            color: #e74c3c; /* Vermelho indicando que está mudo por padrão */
            font-size: 20px;
            cursor: pointer;
            padding: 8px 15px;
            transition: all 0.2s;
        }
        #mute-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        #mute-btn.sound-active {
            color: #2ecc71; /* Verde quando o som está ativado */
            border-color: #2ecc71;
        }

        /* Animação de Alerta Crítico */
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.5); }
            70% { box-shadow: 0 0 0 15px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }
    </style>
</head>
<body>
<div id="progress-bar-container">
    <div id="progress-bar"></div>
</div>
<div class="header">
    <h1>
        <span class="plugin-icon-container">
            <a href="monitor.php" style="color: inherit; text-decoration: none;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="24" height="24">
                <rect width="100" height="100" rx="22" fill="#2d3e50" />  
                <polyline points="15,50 35,50 45,25 60,75 70,50 85,50" fill="none" stroke="#28a745" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />            
                <circle cx="35" cy="85" r="4" fill="#28a745" />
                <circle cx="50" cy="85" r="4" fill="#28a745" />
                <circle cx="65" cy="85" r="4" fill="#dc3545" />
            </svg></a>
        </span>
        NOC Dashboard - Status dos Serviços
    </h1>
    <div style="display: flex; align-items: center; gap: 20px;">
        <button id="mute-btn" onclick="toggleMute()" title="Ativar/Desativar Som">
            <i class="fas fa-volume-mute"></i>
        </button>
        <div class="clock" id="clock">00:00:00</div>
    </div>
    <!-- <div class="clock" id="clock">00:00:00</div>-->
</div>

<div class="grid-container" id="monitor-grid">
    <div style="text-align:center; width: 100%; grid-column: 1 / -1; color: #888; margin-top: 50px;">
        <i class="fas fa-circle-notch fa-spin fa-3x"></i><br><br>Carregando monitoramento...
    </div>
</div>

<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('clock').innerText = now.toLocaleTimeString('pt-BR');
    }
    setInterval(updateClock, 1000);
    updateClock();

    let soundEnabled = false; // Começa mudo por exigência dos navegadores
    
    function toggleMute() {
        soundEnabled = !soundEnabled; // Inverte o status
        
        const btn = document.getElementById('mute-btn');
        const icon = btn.querySelector('i');
        
        if (soundEnabled) {
            // Som ativado
            btn.classList.add('sound-active');
            icon.classList.remove('fa-volume-mute');
            icon.classList.add('fa-volume-up');
            
            console.log("Áudio ativado no NOC.");
            playAlertSound(300, 0.1); // Toca um bip curto de confirmação
        } else {
            // Som desativado
            btn.classList.remove('sound-active');
            icon.classList.remove('fa-volume-up');
            icon.classList.add('fa-volume-mute');
            
            console.log("Áudio silenciado no NOC.");
        }
    }

    // Função matemática que cria o "Beep"
    function playAlertSound(frequency = 600, duration = 0.5) {
        if (!soundEnabled) return; // Se não clicou na página ainda, não tenta tocar para não gerar erro no console

        // Inicia o contexto de áudio
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        // Tipo de onda: 'sine' (suave), 'square' (alarme clássico retro), 'triangle' ou 'sawtooth'
        oscillator.type = 'square'; 
        oscillator.frequency.value = frequency; // Tom da frequência (Hz)

        // Ajuste de volume e fade out para não ser muito abrupto
        gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime); // 0.3 = 30% do volume
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);

        // Toca o som
        oscillator.start(audioCtx.currentTime);
        oscillator.stop(audioCtx.currentTime + duration);
    }

    function fetchMonitors() {
        // Usamos uma URL dinâmica para garantir que ele chame o arquivo correto
        const url = window.location.pathname + '?ajax=1';
        
        console.log("Buscando dados em: " + url); // DEBUG

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log("Dados recebidos:", data); // DEBUG
                const grid = document.getElementById('monitor-grid');

                let hasCriticalAlert = false; // Inicialize a variável para verificar se há algum monitor DOWN
                
                if (data.length === 0) {
                    grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center;">Nenhum monitor ativo encontrado no banco.</div>';
                    return;
                }

                grid.innerHTML = ''; 
                data.forEach(monitor => {
                    let cardClass = '';
                    let icon = 'fa-server';
                    let statusText = 'DESCONHECIDO';

                    // Ajuste de ícones por tipo
                    const type = monitor.type ? monitor.type.toLowerCase() : '';
                    if (type.includes('http')) icon = 'fa-globe';
                    if (type.includes('ping')) icon = 'fa-network-wired';
                    if (type.includes('port')) icon = 'fa-door-open';

                    // Lógica de Status (Verifique se esses nomes de colunas existem na sua tabela)
                    if (monitor.is_maintenance == 1) {
                        cardClass = 'status-maintenance';
                        icon = 'fa-tools';
                        statusText = 'MANUTENÇÃO';
                    } else if (monitor.last_status === 'UP') {
                        cardClass = 'status-up';
                        statusText = 'ONLINE';
                    } else if (monitor.last_status === 'DOWN') {
                        cardClass = 'status-down';
                        icon = 'fa-exclamation-triangle';
                        statusText = 'OFFLINE';
                        hasCriticalAlert = true;
                        
                    }

                    const cardHTML = `
                        <div class="card ${cardClass}">
                            <div class="card-title">${monitor.name}</div>
                            <div class="card-icon"><i class="fas ${icon}"></i></div>
                            <div class="status-text">${statusText}</div>
                            <div class="sla-text">SLA: ${monitor.total_logs > 0 ? ((monitor.up_count / monitor.total_logs) * 100).toFixed(2) + '%' : 'N/A'}</div>
                            <div class="card-url">${monitor.url || ''}</div>
                        </div>
                    `;
                    grid.innerHTML += cardHTML;
                });
                if (hasCriticalAlert) {
                    // Toca o alarme (frequência mais alta, 800Hz) a cada ciclo que algo estiver OFF
                    playAlertSound(800, 0.4);
                }
            })
            .catch(error => {
                console.error('Erro no Fetch:', error);
                const grid = document.getElementById('monitor-grid');
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:red;">Erro ao carregar dados. Verifique o console (F12).</div>';
            });
    }

    const refreshInterval = 15000; // 15 segundos
    let timeElapsed = 0;
    const progressBar = document.getElementById('progress-bar');

    function updateProgressBar() {
        // Atualiza a cada 100ms para uma animação fluida
        timeElapsed += 100; 
        
        // Calcula a porcentagem concluída
        const percentage = (timeElapsed / refreshInterval) * 100;
        progressBar.style.width = percentage + '%';

        // Quando bater 100% (15 segundos), busca os dados e reseta a barra
        if (timeElapsed >= refreshInterval) {
            fetchMonitors();
            timeElapsed = 0; 
            progressBar.style.width = '0%'; // Volta a barra para o início instantaneamente
        }
    }

    // Faz a primeira busca imediatamente ao abrir a tela
    fetchMonitors(); 
    
    // Inicia o loop da barra de progresso
    setInterval(updateProgressBar, 100);

    //fetchMonitors();
    //setInterval(fetchMonitors, 15000);
    
</script>
</body>
</html>