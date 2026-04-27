<?php
include ("../../../inc/includes.php");

// Verificação de permissão
Session::checkLoginUser();
Session::checkRight("uptimemonitor", READ);

Html::header(
    __('Report - Uptime Monitor', 'uptimemonitor'), 
    $_SERVER['PHP_SELF'], 
    "plugins", 
    "PluginUptimemonitorMonitor"
);

// Menu customizado para o plugin
PluginUptimemonitorMonitor::getMenuContentPluginCustom();

global $DB;

// Tratamento de Mês e Ano
$month = str_pad(intval($_GET['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);
$year  = intval($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
    $month = date('m');
    $year  = date('Y');
}

// Configuração da Paginação
$parameters = "month=$month&year=$year";
$start = (isset($_GET['start']) ? intval($_GET['start']) : 0);
$limit = $_SESSION['glpilist_limit'] ?? 20;

$count_monitors = $DB->request([
    'SELECT' => [new \QueryExpression('COUNT(*) AS total')],
    'FROM'   => 'glpi_plugin_uptimemonitor_monitors'
])->current();
$total_rows = $count_monitors['total'];

// CONTAINER PRINCIPAL 
echo "<div class='container-fluid mt-3'>";

echo "<div class='card card-sm mt-0 mb-3 search-card'>"; // Margem reduzida para colar mais na paginação
echo "<div class='card-header d-flex align-items-center search-header gap-2'>";       

echo "<span class='plugin-icon-container d-flex'>
        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' width='20' height='20'>
            <rect width='100' height='100' rx='22' fill='#2d3e50' />  
            <polyline points='15,50 35,50 45,25 60,75 70,50 85,50' fill='none' stroke='#28a745' stroke-width='8' stroke-linecap='round' stroke-linejoin='round' />            
            <circle cx='35' cy='85' r='4' fill='#28a745' />
            <circle cx='50' cy='85' r='4' fill='#28a745' />
            <circle cx='65' cy='85' r='4' fill='#dc3545' />
        </svg>
      </span>";
echo "<h3 class='card-title m-0'>" . __('Disponibilidade Mensal - ', 'uptimemonitor') . $month . '/' . $year . "</h3>";
echo "</div>";

echo "<div class='card-body mb-3 d-print-none'>";
echo "<form method='get' class='row g-3 align-items-end mb-0'>";
echo "  <div class='col-auto'>";
echo "    <label class='form-label mb-1 text-muted'>" . __('Mês') . "</label>";
echo "    <input class='form-control form-control-sm' type='number' name='month' value='$month' min='1' max='12'>";
echo "  </div>";
echo "  <div class='col-auto'>";
echo "    <label class='form-label mb-1 text-muted'>" . __('Ano') . "</label>";
echo "    <input class='form-control form-control-sm' type='number' name='year' value='$year' min='2024'>";
echo "  </div>";
echo "  <div class='col-auto'>";
echo "    <button type='submit' class='btn btn-sm btn-primary'>" . __('Filtrar') . "</button>";
echo "  </div>";
echo "</form>";
echo "</div>";
echo "</div>";
echo "<div class='card mb-3'>";
echo "<div class='table-responsive'>";
echo "<table class='table table-hover table-striped card-table align-middle mb-0'>";
echo "<thead>";
echo "<tr class='text-center text-uppercase text-muted' style='font-size: 0.85rem;'>
        <th class='text-start'>" . __("Serviço") . "</th>
        <th>" . __("Total de Testes") . "</th>
        <th>" . __("UP") . "</th>
        <th>" . __("Quedas Reais") . "</th>
        <th>" . __("Manutenção") . "</th>
        <th>" . __("SLA Real (%)") . "</th>
        <th>" . __("Qtd. Tickets") . "</th>
        </tr>";
echo "</thead>";
echo "<tbody>";

$monitors = $DB->request([
    'FROM'  => 'glpi_plugin_uptimemonitor_monitors',
    'START' => $start,
    'LIMIT' => $limit
]);

foreach ($monitors as $monitor) {
    $stats = $DB->request([
        'SELECT' => [
            new \QueryExpression('COUNT(*) AS total'),
            new \QueryExpression('SUM(CASE WHEN status = "UP" THEN 1 ELSE 0 END) AS up_count'),
            new \QueryExpression("SUM(CASE WHEN status = 'MAINT' OR status IS NULL THEN 1 ELSE 0 END) AS maint_count"),
            new \QueryExpression('SUM(CASE WHEN status = "DOWN" AND in_maintenance = 0 THEN 1 ELSE 0 END) AS incident_count')
        ],
        'FROM'  => 'glpi_plugin_uptimemonitor_logs',
        'WHERE' => [
            'plugin_uptimemonitor_monitors_id' => $monitor['id'], 
            'date_creation' => ['LIKE', "$year-$month%"]
        ]
    ])->current();

    $total     = $stats['total'] ?? 0;
    $up_count  = $stats['up_count'] ?? 0;
    $maint     = $stats['maint_count'] ?? 0;
    $incidents = $stats['incident_count'] ?? 0;
    
    $sla = ($total > 0) ? round((($up_count + $maint) / $total) * 100, 2) : 100;

    $color_sla_class  = ($sla >= 99) ? "text-success" : "text-danger";
    $maint_text_class = ($maint > 0) ? "text-warning fw-bold" : "text-secondary";

    // Linha centralizada, mantendo o nome do serviço à esquerda
    echo "<tr class='text-center'>";
    echo "  <td class='text-start fw-bold'>" . htmlspecialchars($monitor['name']) . "</td>";
    echo "  <td class='text-secondary'>$total</td>";
    echo "  <td class='text-success'>$up_count</td>";
    echo "  <td class='text-danger'>$incidents</td>";
    echo "  <td class='$maint_text_class'>$maint</td>";
    echo "  <td class='$color_sla_class fw-bold'>$sla%</td>";
    // Contagem de tickets relacionados
    $ticket_count = $DB->request([
        'SELECT' => [new \QueryExpression('COUNT(*) AS total')],
        'FROM'      => 'glpi_tickets',
        'WHERE'     =>  ['name' => ['LIKE', '%' . htmlspecialchars($monitor['name']) . '%'], 
            'is_deleted' => 0, 
            new \QueryExpression("DATE_FORMAT(`date`, '%Y-%m') = '$year-$month'")
        ],
        'ORDER'     => 'id DESC'
        ])->current()['total'];
    echo "  <td class='text-secondary'>$ticket_count</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";
echo "</div>";
echo "</div>";

echo "<div class='card-footer search-footer'>";     
echo "<div class='flex-grow-1'>";
Html::printPager($start, $total_rows, $_SERVER['PHP_SELF'], $parameters, $itemstype = 0, $display = true);
echo "</div></div>";

echo "</div>";

Html::footer();
?>