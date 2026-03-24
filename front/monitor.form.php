<?php
include ("../../../inc/includes.php");
Session::checkLoginUser();

Session::checkRight('uptimemonitor', READ);

$monitor = new PluginUptimemonitorMonitor();

// Se for uma edição (ID > 0), tenta carregar o item
if (isset($_GET["id"]) && $_GET["id"] > 0) {
   if (!$monitor->getFromDB($_GET["id"])) {
      Html::displayNotFoundError(); // Se o ID não existir no banco
   }
}

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
    "PluginUptimemonitorMonitor"
);

$id = $_GET["id"] ?? -1;
$monitor->display(['id' => $id]);
Html::footer();