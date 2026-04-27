<?php

include ("../../../inc/includes.php");

Session::checkLoginUser();
Session::checkRight('uptimemonitor', READ);

// --- 1. MOTOR DE DADOS (AJAX) ---
if (isset($_GET['get_data'])) {
    header('Content-Type: application/json');
    global $DB;
    
    $data = ['summary' => ['total' => 0, 'up' => 0, 'down' => 0, 'avg_sla' => 0], 'monitors' => []];
    
    $query = "SELECT * FROM `glpi_plugin_uptimemonitor_monitors` WHERE `is_active` = 1";
    $monitors = $DB->request($query);
    
    $total_sla = 0;
    foreach ($monitors as $m) {
        $data['summary']['total']++;
        (strtoupper($m['last_status']) == 'UP') ? $data['summary']['up']++ : $data['summary']['down']++;

        // Logs para o Heartbeat
        $mid = $m['id'];
        $logs = $DB->request([
            'FROM'  => 'glpi_plugin_uptimemonitor_logs',
            'WHERE' => ['plugin_uptimemonitor_monitors_id' => $mid],
            'ORDER' => 'date_creation DESC',
            'LIMIT' => 40
        ]);

        $beats = [];
        $up_in_period = 0;
        foreach ($logs as $l) {
            $st = strtolower($l['status']);
            if ($st == 'up') $up_in_period++;
            $beats[] = ['s' => $st, 't' => date('H:i', strtotime($l['date_creation'])), 'm' => $l['response_time_ms']];
        }

        $sla = (count($beats) > 0) ? round(($up_in_period / count($beats)) * 100, 1) : 100;
        $total_sla += $sla;

        $data['monitors'][] = [
            'name'   => $m['name'],
            'status' => strtolower($m['last_status']),
            'sla'    => $sla,
            'beats'  => array_reverse($beats)
        ];
    }
    if ($data['summary']['total'] > 0) $data['summary']['avg_sla'] = round($total_sla / $data['summary']['total'], 1);

    echo json_encode($data);
    exit;
}

// --- 2. INTERFACE (HTML/JS) ---
Html::header(__('NOC Dashboard', 'uptimemonitor'), $_SERVER['PHP_SELF'], "plugins", "PluginUptimemonitorMonitor");

?>
<style>
    .noc-canvas { background: #1a1a1a; color: #eee; padding: 20px; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
    .kpi-row { display: flex; gap: 20px; margin-bottom: 25px; }
    .kpi-card { background: #252525; flex: 1; padding: 15px; border-radius: 8px; border-left: 5px solid #3498db; }
    .kpi-card h3 { margin: 0; font-size: 12px; color: #888; text-transform: uppercase; }
    .kpi-card div { font-size: 28px; font-weight: bold; margin-top: 5px; }

    .mon-row { background: #252525; margin-bottom: 10px; padding: 15px; border-radius: 6px; display: flex; align-items: center; gap: 20px; }
    .mon-info { width: 250px; font-weight: bold; }
    .status-led { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 10px; }
    .led-up { background: #2ecc71; box-shadow: 0 0 8px #2ecc71; }
    .led-down { background: #e74c3c; box-shadow: 0 0 8px #e74c3c; }

    .hb-container { flex: 1; display: flex; gap: 3px; height: 30px; }
    .hb-bit { flex: 1; border-radius: 2px; }
    .bit-up { background: #2ecc71; opacity: 0.6; }
    .bit-down { background: #e74c3c; }
</style>

<div class="noc-canvas">
    <div id="kpis" class="kpi-row">Carregando...</div>
    <div id="monitors">Aguardando dados do servidor...</div>
</div>

<script>
function loadNoc() {
    // Forçamos a URL sem parâmetros de busca do GLPI para evitar conflito
    const url = window.location.pathname + '?get_data=1';
    
    fetch(url)
    .then(r => r.json())
    .then(data => {
        // Render KPIs
        document.getElementById('kpis').innerHTML = `
            <div class="kpi-card"><h3>Total</h3><div>\${data.summary.total}</div></div>
            <div class="kpi-card" style="border-color:#2ecc71"><h3>Online</h3><div style="color:#2ecc71">\${data.summary.up}</div></div>
            <div class="kpi-card" style="border-color:#e74c3c"><h3>Offline</h3><div style="color:#e74c3c">\${data.summary.down}</div></div>
            <div class="kpi-card"><h3>SLA Médio</h3><div>\${data.summary.avg_sla}%</div></div>
        `;

        // Render Lista
        document.getElementById('monitors').innerHTML = data.monitors.map(m => `
            <div class="mon-row">
                <div class="mon-info">
                    <span class="status-led led-\${m.status}"></span>
                    \${m.name}
                </div>
                <div style="width:100px; font-size:12px">\${m.sla}% SLA</div>
                <div class="hb-container">
                    \${m.beats.map(b => \`<div class="hb-bit bit-\${b.s}" title="\${b.t}"></div>\`).join('')}
                </div>
            </div>
        `).join('');
    })
    .catch(err => {
        document.getElementById('monitors').innerHTML = "<div style='color:red'>Erro ao processar dados: " + err + "</div>";
    });
}

loadNoc();
setInterval(loadNoc, 30000);
</script>

<?php Html::footer(); ?>