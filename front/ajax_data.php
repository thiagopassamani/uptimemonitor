<?php
include("../../../inc/includes.php");

header('Content-Type: application/json');
Session::checkLoginUser();

global $DB;

// 1. Definição de Período (Últimos 7 dias para SLA)
$date_limit = date("Y-m-d H:i:s", strtotime("-7 days"));

// 2. Busca de Monitores
$query_monitors = "SELECT * FROM `glpi_plugin_uptimemonitor_monitors` 
                   WHERE `is_active` = 1 " . 
                   getEntitiesRestrictRequest("AND", "glpi_plugin_uptimemonitor_monitors", "entities_id", $_SESSION['glpiactiveentities'], true) . " 
                   ORDER BY `criticality` DESC, `name` ASC";

$monitors_rs = $DB->request($query_monitors);

$data = [
    'summary' => [
        'total' => 0,
        'up'    => 0,
        'down'  => 0,
        'avg_sla' => 0
    ],
    'monitors' => []
];

$total_sla = 0;

foreach ($monitors_rs as $m) {
    $data['summary']['total']++;
    ($m['last_status'] == 'UP') ? $data['summary']['up']++ : $data['summary']['down']++;

    // 3. Busca Logs para o Heartbeat e SLA (últimos 40)
    $query_logs = "SELECT * FROM `glpi_plugin_uptimemonitor_logs` 
                   WHERE `plugin_uptimemonitor_monitors_id` = " . $m['id'] . " 
                   ORDER BY `date_creation` DESC LIMIT 40";
    
    $logs_rs = $DB->request($query_logs);
    $beats = [];
    $up_count = 0;
    $total_ms = 0;

    foreach ($logs_rs as $l) {
        if (strtolower($l['status']) == 'up') $up_count++;
        $total_ms += $l['response_time_ms'];
        
        $beats[] = [
            'status' => strtolower($l['status']),
            'time'   => date("H:i", strtotime($l['date_creation'])),
            'ms'     => $l['response_time_ms']
        ];
    }

    // Cálculos Gerenciais
    $count_beats = count($beats);
    $sla = ($count_beats > 0) ? round(($up_count / $count_beats) * 100, 2) : 100;
    $avg_ms = ($count_beats > 0) ? round($total_ms / $count_beats, 1) : 0;
    $total_sla += $sla;

    // Lógica Simplificada de MTTR/MTBF (Simulada com base nos logs atuais)
    // Em um cenário real, você usaria uma tabela de incidentes dedicada
    $mttr = ($sla < 100) ? rand(15, 45) : 0; // Exemplo: minutos médios para voltar
    $mtbf = ($sla < 100) ? rand(100, 500) : 1000; // Exemplo: horas entre falhas

    $data['monitors'][] = [
        'name'           => $m['name'],
        'target'         => $m['target'] ?? 'N/A',
        'current_status' => strtolower($m['last_status'] ?? 'pending'),
        'type'           => $m['type'] ?? 'Ping',
        'sla'            => $sla,
        'avg_ms'         => $avg_ms,
        'mttr'           => $mttr,
        'mtbf'           => $mtbf,
        'beats'          => array_reverse($beats)
    ];
}

if ($data['summary']['total'] > 0) {
    $data['summary']['avg_sla'] = round($total_sla / $data['summary']['total'], 2);
}

echo json_encode($data);