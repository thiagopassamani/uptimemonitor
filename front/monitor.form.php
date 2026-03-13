<?php
include ("../../../inc/includes.php");
Session::checkLoginUser();
Session::checkRight('config', UPDATE);

$monitor = new PluginUptimemonitorMonitor();

if (isset($_POST["add"])) {
   $monitor->add($_POST);
   Html::redirect("monitor.php");
} else if (isset($_POST["update"])) {
   $monitor->update($_POST);
   Html::back();
} else if (isset($_POST["purge"])) {
   $monitor->delete($_POST, 1);
   Html::redirect("monitor.php");
}

Html::header(
    __('Formulário do Monitor', 'uptimemonitor'), 
    $_SERVER['PHP_SELF'], 
    "plugins", 
    "PluginUptimemonitorMonitor" // <--- O nome exato da classe liga ao menu
);

$id = $_GET["id"] ?? -1;
$monitor->display(['id' => $id]);
Html::footer();