<?php
include ("../../../inc/includes.php");

// 1. Segurança e Cabeçalho
Session::checkLoginUser();

// Adiciona o Refresh Automático (60 segundos)
header("Refresh: 60");

Html::header(
    PluginUptimemonitorMonitor::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'], 
    "plugins", 
    "PluginUptimemonitorMonitor" // <--- O nome exato da classe liga ao menu
);

global $DB;

// 2. Estatísticas Rápidas (Contadores)
$stats = $DB->request([
    'SELECT' => [
        new \QueryExpression("COUNT(*) AS total"),
        new \QueryExpression("SUM(CASE WHEN last_status = 'UP' THEN 1 ELSE 0 END) AS up"),
        new \QueryExpression("SUM(CASE WHEN last_status = 'DOWN' AND is_maintenance = 0 THEN 1 ELSE 0 END) AS down"),
        new \QueryExpression("SUM(CASE WHEN last_status IS NULL THEN 1 ELSE 0 END) AS pending"),
        new \QueryExpression("ROUND((SUM(CASE WHEN last_status = 'UP' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) AS sla"),
        new \QueryExpression("SUM(CASE WHEN is_maintenance = 1 THEN 1 ELSE 0 END) AS in_maintenance")
    ],
    'FROM' => 'glpi_plugin_uptimemonitor_monitors',
    'WHERE' => ['is_active' => 1]
])->current();

echo "<div class='center' style='padding: 20px;'>";
echo "    <div style='display: flex; justify-content: center; gap: 30px; margin-bottom: 30px;'>
            <div class='card' style='padding:15px; border-left: 5px solid #3498db;'>
                <b>Total:</b> ".($stats['total'] ?? 0)."
            </div>
            <div class='card' style='padding:15px; border-left: 5px solid #2ecc71;'>
                <b>Online:</b> ".($stats['up'] ?? 0)."
            </div>
            <div class='card' style='padding:15px; border-left: 5px solid #e74c3c;'>
                <b>Offline:</b> ".($stats['down'] ?? 0)."
            </div>
            <div class='card' style='padding:15px; border-left: 5px solid #bfbf0d;'>
                <b>Em Manutenção:</b> ".($stats['in_maintenance'] ?? 0)."
            </div>
          </div>";

// 3. Barra de Ações (Estilo Nativo GLPI 10)
echo "<div class='d-flex align-items-center mb-4 gap-2' style='padding: 0 10px;'>";

// Botão Adicionar (Azul, idêntico ao da imagem)
echo "  <a href='monitor.form.php' class='btn btn-primary'>";
echo "      <i class='fas fa-plus me-1'></i> " . __("Adicionar", "uptimemonitor");
echo "  </a>";

echo "  <a href='dashboard.php' class='btn btn-outline-secondary'>";
echo "      <i class='fas fa-gauge me-1'></i> " . __("Dashboard", "uptimemonitor");
echo "  </a>";

echo "  <a href='report.php' class='btn btn-outline-secondary'>";
echo "      <i class='fas fa-chart-line me-1'></i> " . __("Report SLA", "uptimemonitor");
echo "  </a>";

// Exemplo de botão secundário estilo GLPI (Pesquisar, Listas, etc) - Opcional
echo "  <a href='#' class='btn btn-outline-secondary' onclick='window.print();'>";
echo "      <i class='fas fa-print me-1'></i> " . __("Imprimir Relatório", "uptimemonitor");
echo "  </a>";

echo "</div>";

// 4. Grid de Monitores
$res = $DB->request(['FROM' => 'glpi_plugin_uptimemonitor_monitors', 'ORDER' => 'name ASC']);

echo "<div style='display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:20px; padding:10px;'>";

foreach ($res as $row) {
    $status = $row['last_status'] ?? 'PENDING';
    $is_down = ($status === 'DOWN');
    $color = $is_down ? '#e74c3c' : '#2ecc71';
    $bg_light = $is_down ? '#fdf2f2' : '#f2fdf5';
    $icon = $is_down ? 'fa-exclamation-triangle' : 'fa-check-circle';

    // Link para o formulário de edição/detalhes
    $link = "monitor.form.php?id=" . $row['id'];

    echo "<a href='$link' style='text-decoration:none; color:inherit;'>";
    echo "  <div class='card' style='border: 1px solid #ddd; border-top: 4px solid $color; background: $bg_light; transition: transform 0.2s;'>
                <div class='card-body' style='padding: 15px;'>
                    <div style='display:flex; justify-content:space-between; align-items:center;'>
                        <h3 style='margin:0; font-size:1.1rem;'>".htmlspecialchars($row['name'])."</h3>
                        <i class='fas $icon' style='color:$color; font-size:1.2rem;'></i>
                    </div>
                    
                    <div style='margin-top:10px; font-size:0.85rem; color:#666;'>
                        <i class='fas fa-link'></i> ".htmlspecialchars($row['url'])."<br>
                        <i class='fas fa-clock'></i> ".(Html::convDateTime($row['last_check']) ?: '--')."
                    </div>";
    
    // Se houver um chamado aberto, mostra o ID
    if ($row['current_tickets_id'] > 0) {
        echo "<div style='margin-top:10px; padding:5px; background:#fff; border:1px solid #e74c3c; border-radius:4px; font-size:0.8rem; color:#e74c3c; font-weight:bold;'>
                <i class='fas fa-ticket-alt'></i> Chamado #".$row['current_tickets_id']."
              </div>";
    }

    echo "      </div>";
    echo "   </div>";
    echo "</a>";
}

echo "</div></div>";

// 5. Motor de Busca nativo (opcional, logo abaixo do Dashboard)
echo "<div style='margin-top:50px;'>";
Search::show('PluginUptimemonitorMonitor');
echo "</div>";

Html::footer();