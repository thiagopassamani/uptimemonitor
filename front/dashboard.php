<?php

include ("../../../inc/includes.php");

Session::checkLoginUser();

// BARRAGEM DE LEITURA: Qualquer perfil que possa ler tickets tem acesso ao NOC
Session::checkRight('ticket', READ);

echo Html::script('public/lib/chart.js'); // Caminho nativo do GLPI 10

Html::header(
    __('Dashboard - Uptime Monitor', 'uptimemonitor'), 
    $_SERVER['PHP_SELF'], 
    "plugins", 
    "PluginUptimemonitorMonitor" // <--- O nome exato da classe liga ao menu
);
global $DB;

// 1. O CSS para recriar o visual do Uptime Kuma
echo "
<style>
    .uptime-container { max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 15px; padding: 20px; font-family: sans-serif; }
    .monitor-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .monitor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .monitor-name { font-size: 1.2em; font-weight: 600; color: #333; }
    
    /* Badges de Status Atual */
    .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.9em; font-weight: bold; }
    .status-badge.up { background-color: #e6f4ea; color: #1e8e3e; }
    .status-badge.down { background-color: #fce8e6; color: #d93025; }
    .status-badge.pending { background-color: #f1f3f4; color: #5f6368; }

    /* A Barra de Histórico (Coração do visual) */
    .heartbeat-bar { display: flex; gap: 4px; height: 35px; align-items: flex-end; }
    .beat { flex: 1; min-width: 6px; border-radius: 100px; height: 100%; transition: opacity 0.2s; cursor: pointer; }
    .beat:hover { opacity: 0.7; }
    .beat.up { background-color: #50e3c2; } /* Verde estilo Kuma */
    .beat.down { background-color: #ff4d4d; } /* Vermelho */
    .beat.empty { background-color: #f0f0f0; padding: 0px !important} /* Sem dados ainda */
    .beat.pending { background-color: #f0f0f0; } /* Sem dados ainda */
</style>
";

// 2. Busca os monitores ativos respeitando a Entidade atual do usuário
$query_monitors = "SELECT * FROM `glpi_plugin_uptimemonitor_monitors` 
                   WHERE `is_active` = 1 " . 
                   getEntitiesRestrictRequest("AND", "glpi_plugin_uptimemonitor_monitors", "entities_id", $_SESSION['glpiactiveentities'], true) . " 
                   ORDER BY `name` ASC";

$monitors = $DB->request($query_monitors);

echo "<div class='uptime-container'>";

foreach ($monitors as $monitor) {
    // Define a classe CSS baseada no status atual
    // No seu dashboard.php, verifique se está a usar:
    $status = $monitor['last_status'];
    $status_class = strtolower($monitor['last_status'] ?? 'pending');
    $status_text = $monitor['last_status'] ?? 'Aguardando';
    
    echo "<div class='monitor-card'>";
    
    // Cabeçalho do Card (Nome e Status Atual)
    echo "<div class='monitor-header'>";
    echo "<div class='monitor-name'>" . htmlspecialchars($monitor['name']);

    // Verifica se está em manutenção agora para exibir um ícone/aviso
    $agora = date("Y-m-d H:i:s");
    if ($monitor['is_maintenance'] == 1 && $agora >= $monitor['maintenance_start'] && $agora <= $monitor['maintenance_end']) {
        echo " <span style='background-color: #f39c12; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7em; margin-left: 10px;'>EM MANUTENÇÃO 🛠️</span>";
    }
    
    // Se o monitor estiver vinculado a um ativo, cria o link para ele
    if (!empty($monitor['itemtype']) && $monitor['items_id'] > 0) {
        $item = new $monitor['itemtype']();
        if ($item->getFromDB($monitor['items_id'])) {
            $link_ativo = $item->getLink(); // Função nativa que gera a tag <a> com o nome e link do ativo
            echo " <span style='font-size: 0.8em; font-weight: normal; margin-left: 10px;'>| Ativo: " . $link_ativo . "</span>";
        }
    }
    
    echo "</div>"; // Fecha monitor-name
    echo "<div class='status-badge {$status_class}'>" . htmlspecialchars($status_text) . "</div>";
    echo "</div>";

    // 3. Busca o histórico de testes (Últimos 40 registros)
    $query_logs = "SELECT * FROM `glpi_plugin_uptimemonitor_logs` 
                   WHERE `plugin_uptimemonitor_monitors_id` = " . $monitor['id'] . " 
                   ORDER BY `date_creation` DESC LIMIT 40";
    
    $logs = $DB->request($query_logs);
    
    // Colocamos num array e invertemos para a ordem cronológica
    $beats = [];
    foreach ($logs as $log) {
        $beats[] = [
            'status' => strtolower($log['status']),
            'time' => $log['date_creation'],
            'ms' => $log['response_time_ms']
        ];
    }
    $beats = array_reverse($beats);

    // 4. Desenha a barra de blocos (Heartbeat)
    echo "<div class='heartbeat-bar'>";
    
    // Preenche com blocos cinzas se tivermos menos de 40 testes salvos
    $missing_beats = 40 - count($beats);
    for ($i = 0; $i < $missing_beats; $i++) {
        echo "<div class='beat empty' title='Aguardando dados...'></div>";
    }

    // Desenha os blocos com os dados reais
    foreach ($beats as $beat) {
        $b_class = $beat['status'];
        $tooltip = "Data: " . $beat['time'] . " | Status: " . strtoupper($b_class) . " | Ping: " . $beat['ms'] . "ms";
        echo "<div class='beat {$b_class}' title='{$tooltip}'></div>";
    }
    
    echo "</div>"; // Fecha heartbeat-bar
    echo "</div>"; // Fecha monitor-card
}

echo "</div>"; // Fecha uptime-container

echo "
<script>
    // Atualiza a página inteira a cada 60 segundos
    setTimeout(function() {
        window.location.reload();
    }, 60000);
</script>
";

Html::footer();