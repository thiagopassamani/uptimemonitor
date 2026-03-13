<?php

function plugin_uptimemonitor_install() {
    global $DB;
    
    $migration = new Migration(100);

    // Cria a tabela principal de Monitores com todas as colunas corporativas
    if (!$DB->tableExists("glpi_plugin_uptimemonitor_monitors")) {
        $query = "CREATE TABLE `glpi_plugin_uptimemonitor_monitors` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `url` varchar(255) NOT NULL,
            `type` varchar(50) DEFAULT 'http',
            `check_interval` int(11) NOT NULL DEFAULT '5',
            `is_active` tinyint(1) NOT NULL DEFAULT '1',
            `last_status` varchar(50) DEFAULT NULL,
            `last_check` datetime DEFAULT NULL,
            `entities_id` int(11) NOT NULL DEFAULT '0',
            `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
            `itemtype` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `items_id` int(11) NOT NULL DEFAULT '0',
            `current_tickets_id` int(11) NOT NULL DEFAULT '0',
            `is_maintenance` tinyint(1) NOT NULL DEFAULT '0',
            `maintenance_start` datetime DEFAULT NULL,
            `maintenance_end` datetime DEFAULT NULL,
            `groups_id_tech` int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $DB->queryOrDie($query, $DB->error());
    }

    // Cria a tabela de Histórico (Logs de latência)
    if (!$DB->tableExists("glpi_plugin_uptimemonitor_logs")) {
        $query = "CREATE TABLE `glpi_plugin_uptimemonitor_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `plugin_uptimemonitor_monitors_id` int(11) NOT NULL,
            `status` varchar(50) NOT NULL,
            `response_time_ms` int(11) NOT NULL,
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `monitor_id` (`plugin_uptimemonitor_monitors_id`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $DB->queryOrDie($query, $DB->error());
    }

    $migration->executeMigration();

    // Regista a Ação Automática
    $cron = new CronTask();
    
    // Verifica se a tarefa já existe para não duplicar
    if (!$cron->getFromDBbyName('PluginUptimemonitorMonitor', 'check')) {
        $cron->add([
            'itemtype'  => 'PluginUptimemonitorMonitor', // A classe onde estão as funções cron
            'name'      => 'check',                      // O nome da tarefa (que usamos no switch)
            'frequency' => MINUTE_TIMESTAMP,             // Frequência padrão (A cada 1 minuto)
            'state'     => 1,                            // 1 = Ativo, 0 = Desativado
            'mode'      => 2                             // 1 = GLPI (Navegador), 2 = CLI (Crontab do Linux)
        ]);
    }
    return true;
}

function plugin_uptimemonitor_uninstall() {
    global $DB;

    // Remove as tabelas se o utilizador decidir desinstalar e limpar os dados
    $DB->query("DROP TABLE IF EXISTS `glpi_plugin_uptimemonitor_monitors`");
    $DB->query("DROP TABLE IF EXISTS `glpi_plugin_uptimemonitor_logs`");

    return true;
}

