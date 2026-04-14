<?php
include ("../../../inc/includes.php");

// 1. Segurança e Cabeçalho
Session::checkLoginUser();

Session::checkRight('uptimemonitor', READ);

Html::header(
    PluginUptimemonitorMonitor::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'], 
    "plugins", 
    "PluginUptimemonitorMonitor" // <--- O nome exato da classe liga ao menu
);

PluginUptimemonitorMonitor::getMenuContentPluginCustom();

// 5. Motor de Busca nativo (opcional, logo abaixo do Dashboard)
echo "<div class='mt-3'>";
Search::show('PluginUptimemonitorMonitor');
echo "</div>";
Html::footer();