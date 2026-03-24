<?php
include ("../../../inc/includes.php");

// Verificação de permissão
Session::checkLoginUser();
Session::checkRight("config", READ);

Html::header(
    __('Report - Uptime Monitor', 'uptimemonitor'), 
    $_SERVER['PHP_SELF'], 
    "plugins", 
    "PluginUptimemonitorMonitor"
);

PluginUptimemonitorMonitor::getMenuContentPluginCustom();

global $DB;

// Filtro de data (Padrão: mês atual)
$month = $_GET['month'] ?? date('m');
$year  = $_GET['year'] ?? date('Y');

echo "<div class='mt-3 table-responsive-lg '>";
echo "<div class='card card-sm mt-0 search-card' style='padding: 20px; margin-bottom: 20px;'>";
echo "<div class='card-header d-flex justify-content-between search-header pe-0'>";               

echo "<div class='header'>";
echo "<h1><span class='plugin-icon-container'>
            <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' width='24' height='24'>
                <rect width='100' height='100' rx='22' fill='#2d3e50' />  
                <polyline points='15,50 35,50 45,25 60,75 70,50 85,50' fill='none' stroke='#28a745' stroke-width='8' stroke-linecap='round' stroke-linejoin='round' />            
                <circle cx='35' cy='85' r='4' fill='#28a745' />
                <circle cx='50' cy='85' r='4' fill='#28a745' />
                <circle cx='65' cy='85' r='4' fill='#dc3545' />
            </svg>
        </span>";
echo __('Disponibilidade Mensal - ' . $month . '/' . $year, 'uptimemonitor') . "</h1>";
echo "</div>";

echo "<div class='search-filters gap-2'>";
    echo "<form method='get' style='margin-bottom: 20px;'>";
    echo "<label>Mês: <input type='number' name='month' value='$month' min='1' max='12'></label>";
    echo "<label>Ano: <input type='number' name='year' value='$year' min='2024'></label>";
    echo "<button type='submit' class='btn btn-primary'>Filtrar</button>";
    echo "</form>";
echo "</div>";
echo "</div>";

$monitors = $DB->request(['FROM' => 'glpi_plugin_uptimemonitor_monitors']);

echo "<table class='search-results table card-table table-hover table-striped'>";
echo "<tr>
        <th>" . __("Serviço") . "</th>
        <th>" . __("Total de Testes") . "</th>
        <th>" . __("UP") . "</th>
        <th>" . __("Manutenção") . "</th>
        <th>" . __("Quedas Reais") . "</th>
        <th>" . __("SLA Real (%)") . "</th>
      </tr>";

foreach ($monitors as $monitor) {
    // Consulta para obter os logs do monitor filtrados por mês/ano
    $stats = $DB->request([
        'SELECT' => [
            new \QueryExpression('COUNT(*) AS total'),
            new \QueryExpression('SUM(CASE WHEN status = "UP" THEN 1 ELSE 0 END) AS up_count'),
            //new \QueryExpression('SUM(CASE WHEN status = "DOWN" AND in_maintenance = 1 THEN 1 ELSE 0 END) AS maint_count'),
            new \QueryExpression("SUM(CASE WHEN status = 'MAINT' OR status IS NULL THEN 1 ELSE 0 END) AS maint_count"),
            new \QueryExpression('SUM(CASE WHEN status = "DOWN" AND in_maintenance = 0 THEN 1 ELSE 0 END) AS incident_count')
        ],
        'FROM'  => 'glpi_plugin_uptimemonitor_logs',
        'WHERE' => [
            'plugin_uptimemonitor_monitors_id' => $monitor['id'], // Ajustado conforme estrutura comum
            'date_creation' => ['LIKE', "$year-$month%"]
        ]
    ])->current();

    $total     = $stats['total'] ?? 0;
    $up_count  = $stats['up_count'] ?? 0;
    $maint     = $stats['maint_count'] ?? 0;
    $incidents = $stats['incident_count'] ?? 0;
    
    /** 
     * Consideramos o tempo UP + o tempo de Manutenção Programada como "Disponível"
     */
    $sla = ($total > 0) ? round((($up_count + $maint) / $total) * 100, 2) : 100;

    // Cores de status
    $color_sla = ($sla >= 99) ? "green" : "red";
    $maint_style = ($maint > 0) ? "color: orange; font-weight: bold;" : "color: #999;";

    echo "<tr class='tab_bg_1'>";
    echo "<td>" . $monitor['name'] . "</td>";
    echo "<td>$total</td>";
    echo "<td><span style='color: green;'>$up_count</span></td>";
    echo "<td><span style='$maint_style'>$maint</span></td>";
    echo "<td><span style='color: red;'>$incidents</span></td>";
    echo "<td><b style='color: $color_sla;'>$sla%</b></td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

Html::footer();