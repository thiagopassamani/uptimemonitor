
<?php
// Carrega o motor do GLPI (o caminho assume que o arquivo está na pasta /front/)
include ("../../../inc/includes.php");

// Verifica se o usuário está logado no GLPI (mesmo para a TV, é uma boa prática de segurança)
Session::checkLoginUser();

// =========================================================================
// 1. MODO AJAX: Retorna apenas os dados em JSON (Atualização em Background)
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    global $DB;
    $monitors = [];

    // Busca apenas monitores ativos
    $query = "SELECT id, name, type, url, last_status, is_maintenance 
              FROM glpi_plugin_uptimemonitor_monitors 
              WHERE is_active = 1 AND is_noc = 1
              ORDER BY last_status ASC, name ASC"; // Ordena OFFLINE primeiro
    
    $iterator = $DB->request($query);
    foreach ($iterator as $row) {
        $monitors[] = $row;
    }

    // Retorna os dados para o JavaScript e encerra a execução do PHP
    header('Content-Type: application/json');
    echo json_encode($monitors);
    exit; 
}

// =========================================================================
// 2. MODO VISUAL: Renderiza a Interface da TV (Carrega apenas 1 vez)
// =========================================================================
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

        /* Animação de Alerta Crítico */
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.5); }
            70% { box-shadow: 0 0 0 15px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1><i class="fas fa-desktop" style="color: #3498db;"></i> NOC Dashboard - Status dos Serviços</h1>
        <div class="clock" id="clock">00:00:00</div>
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
                    }

                    const cardHTML = `
                        <div class="card ${cardClass}">
                            <div class="card-title">${monitor.name}</div>
                            <div class="card-icon"><i class="fas ${icon}"></i></div>
                            <div class="status-text">${statusText}</div>
                            <div class="card-url">${monitor.url || ''}</div>
                        </div>
                    `;
                    grid.innerHTML += cardHTML;
                });
            })
            .catch(error => {
                console.error('Erro no Fetch:', error);
                const grid = document.getElementById('monitor-grid');
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:red;">Erro ao carregar dados. Verifique o console (F12).</div>';
            });
    }

    fetchMonitors();
    setInterval(fetchMonitors, 15000);
</script>
</body>
</html>