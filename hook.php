<?php

function plugin_uptimemonitor_install() {
    global $DB;
    
    $migration = new Migration(100);

    // Cria a tabela principal de Monitores com todas as colunas corporativas
    if (!$DB->tableExists("glpi_plugin_uptimemonitor_monitors")) {
        $query = "CREATE TABLE `glpi_plugin_uptimemonitor_monitors` ( 
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT, 
            `name` varchar(255) NOT NULL, 
            `url` varchar(255) NOT NULL, 
            `type` varchar(50) DEFAULT 'http', 
            `check_interval` int(11) NOT NULL DEFAULT '5', 
            `is_active` tinyint(1) NOT NULL DEFAULT '1', 
            `last_status` varchar(50) DEFAULT NULL, 
            `last_check` timestamp NULL DEFAULT NULL, 
            `entities_id` int(11) unsigned NOT NULL DEFAULT '0', 
            `is_recursive` tinyint(1) NOT NULL DEFAULT '0', 
            `itemtype` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL, 
            `items_id` int(11) unsigned NOT NULL DEFAULT '0', 
            `current_tickets_id` int(11) unsigned NOT NULL DEFAULT '0', 
            `is_maintenance` tinyint(1) NOT NULL DEFAULT '0', 
            `maintenance_start` timestamp NULL DEFAULT NULL, 
            `maintenance_end` timestamp NULL DEFAULT NULL, 
            `groups_id_tech` int(11) unsigned NOT NULL DEFAULT '0', 
            `criticality` varchar(50) NOT NULL DEFAULT 'low',
            `is_noc` tinyint(1) NOT NULL DEFAULT '0',
            `itilcategories_id` int(11) NOT NULL DEFAULT 0,
            `auto_create_ticket` tinyint(1) NOT NULL DEFAULT '0',
            `failed_attempts` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`) 
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $query_insert = "INSERT INTO `glpi_plugin_uptimemonitor_monitors` (`id`, `name`, `url`, `type`, `check_interval`, `is_active`, `last_status`, `last_check`, `entities_id`, `is_recursive`, `itemtype`, `items_id`, `current_tickets_id`, `is_maintenance`, `maintenance_start`, `maintenance_end`, `groups_id_tech`, `criticality`, `is_noc`, `auto_create_ticket`, `itilcategories_id`, `failed_attempts`) VALUES
            (1, 'Google DNS1', '8.8.8.8', 'ping', 15, 1, 'UP', '2026-03-31 00:03:53', 0, 0, NULL, 0, 0, 0, NULL, NULL, 0, 'low', 1, 0, 0, 0),
            (2, 'Google DNS2', '8.8.4.4', 'ping', 1, 1, 'UP', '2026-03-31 00:09:53', 0, 0, NULL, 0, 0, 0, NULL, NULL, 0, 'test', 1, 0, 0, 0),
            (3, 'Cloudflare DNS1', '1.1.1.1', 'ping', 5, 1, 'UP', '2026-03-31 00:09:53', 0, 0, NULL, 0, 0, 0, NULL, NULL, 0, 'test', 1, 1, 3, 0),
            (4, 'Cloudflare DNS2', '1.1.2.2', 'ping', 5, 1, 'DOWN', '2026-03-31 00:09:54', 0, 0, NULL, 0, 0, 0, NULL, NULL, 0, 'test', 1, 0, 0, 0);";
        
        $DB->queryOrDie($query, $DB->error());
        $DB->queryOrDie($query_insert, $DB->error());
    }

    // Cria a tabela de Histórico (Logs de latência)
    if (!$DB->tableExists("glpi_plugin_uptimemonitor_logs")) {
        $query = "CREATE TABLE `glpi_plugin_uptimemonitor_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `plugin_uptimemonitor_monitors_id` int(11) NOT NULL,
            `status` varchar(50) NOT NULL,
            `in_maintenance` TINYINT(1) NOT NULL DEFAULT 0,
            `response_time_ms` int(11) NOT NULL,
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `monitor_id` (`plugin_uptimemonitor_monitors_id`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $DB->queryOrDie($query, $DB->error());
    }

    // Cria a tabela de Configurações do Plugin
    if (!$DB->tableExists("glpi_plugin_uptimemonitor_configs")) {
        $query = "CREATE TABLE `glpi_plugin_uptimemonitor_configs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `entities_id` int(11) unsigned NOT NULL DEFAULT '0',
            `name` varchar(255) NOT NULL UNIQUE,
            `value` longtext,
            `type` varchar(50) DEFAULT 'string',
            `date_mod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $DB->queryOrDie($query, $DB->error());

        // Insere configurações padrão
        $default_configs = [
            ['name' => 'telegram_enabled', 'value' => '0', 'type' => 'boolean'],
            ['name' => 'telegram_api_key', 'value' => '', 'type' => 'password'],
            ['name' => 'telegram_chat_id', 'value' => '', 'type' => 'string'],
            ['name' => 'email_notifications', 'value' => '1', 'type' => 'boolean'],
            ['name' => 'slack_enabled', 'value' => '0', 'type' => 'boolean'],
            ['name' => 'slack_webhook_url', 'value' => '', 'type' => 'password'],
            ['name' => 'notification_on_down', 'value' => '1', 'type' => 'boolean'],
            ['name' => 'notification_on_up', 'value' => '1', 'type' => 'boolean'],
            ['name' => 'create_ticket_on_down', 'value' => '0', 'type' => 'boolean'],
            ['name' => 'ticket_category', 'value' => '', 'type' => 'string'],
            ['name' => 'sla_response_time', 'value' => '30', 'type' => 'integer'],
        ];

        foreach ($default_configs as $config) {
            $DB->insert('glpi_plugin_uptimemonitor_configs', [
                'name'   => $config['name'],
                'value'  => $config['value'],
                'type'   => $config['type'],
                'entities_id' => 0
            ]);
        }
    }

    $migration->executeMigration();

    // Configurar permissões para o super-admin (ID 4 no GLPI)
    $profileRight = new ProfileRight();
    if (!$profileRight->getFromDBByCrit(['profiles_id' => 4, 'name' => 'uptimemonitor'])) {
        $profileRight->add([
            'profiles_id' => 4, // Super-admin profile ID
            'name'        => 'uptimemonitor',
            'rights'      => (READ | CREATE | UPDATE | PURGE | DELETE) // Todos os direitos
        ]);
    }

    // Regista a Ação Automática
    $cron = new CronTask();
    
    // Verifica se a tarefa já existe para não duplicar
    if (!$cron->getFromDBbyName('PluginUptimemonitorMonitor', 'check')) {
        $cron->add([
            'itemtype'  => 'PluginUptimemonitorMonitor', // A classe onde estão as funções cron
            'name'      => 'check',         // O nome da tarefa (que usamos no switch)
            'frequency' => MINUTE_TIMESTAMP,             // Frequência padrão (A cada 1 minuto)
            'state'     => 1,                            // 1 = Ativo, 0 = Desativado
            'mode'      => 2                             // 1 = GLPI (Navegador), 2 = CLI (Crontab do Linux)
        ]);
    }
    return true;
}

function plugin_uptimemonitor_uninstall() {
    global $DB;
 
    // Remove the tables if the user decides to uninstall and clean up data
    $tables = [
      'monitors',
      'logs',
      'configs'
    ];

    foreach ($tables as $table) {
        $tablename = 'glpi_plugin_uptimemonitor_' . $table;
        //Create table only if it does not exists yet!
        if ($DB->tableExists($tablename)) {
            $DB->queryOrDie(
                "DROP TABLE `$tablename`",
                $DB->error()
            );
        }
    }

    return true;
}

