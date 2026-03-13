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

global $DB;

// Filtro de data (Padrão: mês atual)
$month = $_GET['month'] ?? date('m');
$year  = $_GET['year'] ?? date('Y');

echo "<div class='center'>";

echo "<div class='d-flex align-items-center mb-4 gap-2' style='padding: 0 10px;'>";
echo "  <a href='dashboard.php' class='btn btn-outline-secondary'>";
echo "      <i class='fas fa-gauge me-1'></i> " . __("Dashboard", "uptimemonitor");
echo "  </a>";
echo "  <a href='report.php' class='btn btn-outline-secondary'>";
echo "      <i class='fas fa-chart-line me-1'></i> " . __("Report SLA", "uptimemonitor");
echo "  </a>";
echo "  <a href='#' class='btn btn-outline-secondary' onclick='window.print();'>";
echo "      <i class='fas fa-print me-1'></i> " . __("Imprimir", "uptimemonitor");
echo "  </a>";
echo "</div>";


echo "<h2>" . __("Disponibilidade Mensal - $month/$year", "uptimemonitor") . "</h2>";

// Formulário simples de filtro
echo "<form method='get' style='margin-bottom: 20px;'>";
echo "Mês: <input type='number' name='month' value='$month' min='1' max='12'> ";
echo "Ano: <input type='number' name='year' value='$year' min='2024'> ";
echo "<button type='submit' class='btn btn-primary'>Filtrar</button>";
echo "</form>";

$monitors = $DB->request(['FROM' => 'glpi_plugin_uptimemonitor_monitors']);

echo "<table class='tab_cadre_fixehov'>";
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

// Adicione este link antes do <table> no arquivo acima
echo "<div style='margin: 10px;' class='right'>";
echo " <a href='report.php?month=$month&year=$year&export=csv' class='btn btn-success'>
        <i class='fas fa-file-excel'></i> Exportar CSV</a>";
echo "</div>";

// Lógica de exportação no topo do arquivo (antes do Html::header)
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="uptime_report_'.$month.'_'.$year.'.csv"');
    // ... lógica de loop fputcsv aqui ...
    exit();
}

echo "</table>";
echo "</div>";

Html::footer();