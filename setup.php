<?php

// Define a versão atual do plugin
define('PLUGIN_UPTIMEMONITOR_VERSION', '1.0.0');
define('PLUGIN_UPTIMEMONITOR_DIR', __DIR__);
define('PLUGIN_UPTIMEMONITOR_NAME', 'Uptime Monitor');

// Função principal de inicialização do plugin
function plugin_init_uptimemonitor() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['uptimemonitor'] = true; // Segurança CSRF

   // 2. Registro de Classes (Sem repetições)
   Plugin::registerClass('PluginUptimemonitorMonitor', [
      'addtabon' => ['PluginUptimemonitorMonitor']
   ]);
   Plugin::registerClass('PluginUptimemonitorCron');
   Plugin::registerClass('PluginUptimemonitorLog');
   Plugin::registerClass('PluginUptimemonitorConfig');
   Plugin::registerClass('PluginUptimemonitorProfile', [
       'addtabon' => ['Profile']
   ]);

   // Gestão de Direitos
   //$PLUGIN_HOOKS['rights']['uptimemonitor'] = 'Uptime Monitor';
   $PLUGIN_HOOKS['rights']['uptimemonitor'] = 'uptimemonitor';

   // 4. Menus (Aparecerá em Plugins > Uptime Monitor)
   // Alterado para 'ticket' para que o pessoal do NOC consiga ver o menu e breadcrumb
   if (Session::haveRight('uptimemonitor', READ)) {
      $PLUGIN_HOOKS['menu_toadd']['uptimemonitor'] = [
         'plugins' => 'PluginUptimemonitorMonitor'
      ];
   }

   //Notificações
   $PLUGIN_HOOKS['item_get_events']['uptimemonitor'] = [
      'PluginUptimemonitorMonitor' => 'getEvents'
   ];
   $PLUGIN_HOOKS['item_get_targets']['uptimemonitor'] = [
      'PluginUptimemonitorMonitor' => 'getTargets'
   ];

   // Ações Automáticas (Cron)
   // Aponta para a classe dedicada ao cron do plugin (agora em inc/cron.class.php)
   $PLUGIN_HOOKS['cron']['uptimemonitor'] = ['PluginUptimemonitorCron'];
}

/**
 * Informações estruturais do plugin
 */
function plugin_version_uptimemonitor() {
   return [
      'name'           => PLUGIN_UPTIMEMONITOR_NAME,
      'version'        => PLUGIN_UPTIMEMONITOR_VERSION,
      'author'         => 'Thiago Passamani <thiagopassamani@gmail.com>',
      'license'        => 'GPLv2+',
      'homepage'       => 'https://github.com/thiagopassamani/uptimemonitor',
      'requirements'   => [
         'glpi' => [
            'min' => '10.0.0'
         ]
      ]
   ];
}

/**
 * Pré-requisitos
 */
function plugin_uptimemonitor_check_prerequisites() {
   if (version_compare(GLPI_VERSION, '10.0.0', 'lt')) {
      echo "Este plugin requer o GLPI versão 10.0.0 ou superior.";
      return false;
   }
   if (!extension_loaded('curl')) {
      echo "A extensão 'curl' do PHP é obrigatória.";
      return false;
   }
   return true;
}

function plugin_uptimemonitor_check_config() {
   return true;
}