<?php
/**
 * Uptime Monitor Plugin for GLPI
 * Author: Thiago Passamani
 * @class PluginUptimemonitorMonitorForm
 * @description Formulário do Monitor do Plugin Uptime Monitor
 */

include ("../../../inc/includes.php");

// Verifica se o usuário está logado
Session::checkLoginUser();

// Verifica se o usuário tem direitos básicos (ticket) ou é super-admin
if (!Session::haveRight('uptimemonitor', READ) && !Session::isSuperAdmin()) {
    Html::displayRightError();
    exit;
}

$monitor = new PluginUptimemonitorMonitor();

// Se for uma edição (ID > 0), tenta carregar o item
$id = isset($_GET["id"]) ? (int) $_GET["id"] : -1;
if ($id > 0) {
   if (!$monitor->getFromDB($id)) {
         Html::displayNotFoundError(); // Se o ID não existir no banco
   }
}

if (isset($_POST["add"])) {
   try {
      $monitor->check(-1, CREATE, $_POST);
      $newId = $monitor->add($_POST);
      if ($newId !== false) {
         Html::redirect("monitor.php");
      }
      Session::addMessage(__('Falha ao criar monitor. Verifique os campos e tente novamente.', 'uptimemonitor'), false, ERROR);
   } catch (Exception $e) {
      Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
   }
   Html::back();
} else if (isset($_POST["update"])) {
   try {
      $monitor->check($_POST['id'], UPDATE, $_POST);
      $monitor->update($_POST);
      Html::redirect("monitor.php");
   } catch (Exception $e) {
      Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
      Html::back();
   }
} else if (isset($_POST["purge"])) {
   try {
      $monitor->check($_POST['id'], PURGE, $_POST);
      $result = $monitor->delete($_POST, 1);
      if ($result !== false) {
         Html::redirect("monitor.php");
      }
      Session::addMessageAfterRedirect(
         __('Falha ao excluir monitor. Tente novamente.', 'uptimemonitor'),
         false,
         ERROR
      );
   } catch (Exception $e) {
      Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
   }
   Html::back();
}

Html::header(
    __('Formulário do Monitor', 'uptimemonitor'), 
    $_SERVER['PHP_SELF'], 
    "plugins", 
    "PluginUptimemonitorMonitor"
);

$monitor->display(['id' => $id]);
Html::footer();