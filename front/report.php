<?php
include ("../../../inc/includes.php");

// 1. Processamento de Lógica (DEVE VIR ANTES DE TUDO)
if (isset($_POST["export"])) {
    // Se for gerar um PDF ou Excel, não pode haver NENHUM HTML antes
    // O redirecionamento ou geração de arquivo acontece aqui
    Html::redirectJS("página.php"); 
    exit(); 
}

// Verificação de permissão
Session::checkLoginUser();
Session::checkRight("config", READ);

Html::header(
    __('Report - Uptime Monitor', 'uptimemonitor'), 
    $_SERVER['PHP_SELF'], 
    "plugins", 
    "PluginUptimemonitorMonitor" // <--- O nome exato da classe liga ao menu
);

PluginUptimemonitorMonitor::getMenuContentPluginCustom();

global $DB;

// Filtro de data (Padrão: mês atual)
$month = $_GET['month'] ?? date('m');
$year  = $_GET['year'] ?? date('Y');

echo "<div class='mt-3 table-responsive-lg '>";

echo "<div class='card card-sm mt-0 search-card' style='padding: 20px; margin-bottom: 20px;'>";

echo "<div class='card-header d-flex justify-content-between search-header pe-0'>";
                     
echo "<div class='d-inline-flex search-controls'>";
    echo "<h2>" . __("Disponibilidade Mensal - $month/$year", "uptimemonitor") . "</h2>";

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
        <th>" . __("Tempo Online") . "</th>
        <th>" . __("SLA (%)") . "</th>
      </tr>";

foreach ($monitors as $monitor) {
    // Busca estatísticas do mês selecionado
    $stats = $DB->request([
        'SELECT' => [
            new \QueryExpression('COUNT(*) AS total'),
            new \QueryExpression('SUM(CASE WHEN status = "UP" THEN 1 ELSE 0 END) AS up_count')
        ],
        'FROM'  => 'glpi_plugin_uptimemonitor_logs',
        'WHERE' => [
            'plugin_uptimemonitor_monitors_id' => $monitor['id'],
            'date_creation' => ['LIKE', "$year-$month%"]
        ]
    ])->current();

    $total    = $stats['total'] ?? 0;
    $up_count = $stats['up_count'] ?? 0;
    $sla      = ($total > 0) ? round(($up_count / $total) * 100, 2) : 0;

    // Cor baseada no SLA (ex: abaixo de 99% fica vermelho)
    $color = ($sla >= 99) ? "green" : "red";

    echo "<tr class='tab_bg_1'>";
    echo "<td>" . $monitor['name'] . "</td>";
    echo "<td>$total</td>";
    echo "<td>$up_count</td>";
    echo "<td><b style='color: $color;'>$sla%</b></td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

Html::footer();