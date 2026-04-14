<?php
/**
 * Uptime Monitor Plugin for GLPI
 * Author: Thiago Passamani
 * @class PluginUptimemonitorConfig
 * @description Classe de Configurações do Plugin Uptime Monitor. Gerencia as configurações do plugin, incluindo integração com Telegram e Slack.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginUptimemonitorConfig extends CommonDBTM {

    public static $rightname = 'uptimemonitor';
    public $dohistory = false;

    public static function getTypeName($nb = 0) {
        return _n('Configuration', 'Configurations', $nb, 'uptimemonitor');
    }

    //private static $cache = [];

    /**
     * Get a configuration value by name
     * 
     * @param string $name Configuration name
     * @param mixed $default Default value if not found
     * @return mixed Configuration value or default
     */
    public static function getConfigValue($name, $default = '') {
        global $DB;

        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        try {
            if (!$DB->tableExists('glpi_plugin_uptimemonitor_configs')) {
                return $default;
            }

            $iterator = $DB->request([
                'FROM'   => 'glpi_plugin_uptimemonitor_configs',
                'WHERE'  => ['name' => $name]
            ]);

            if (count($iterator) > 0) {
                foreach ($iterator as $row) {
                    return $row['value'];
                }
            }
        } catch (Exception $e) {
            error_log('Error getting config value: ' . $e->getMessage());
        }

        return $default;
// Novo
        self::$cache[$name] = $value;
        return $value;
    }

    /**
     * Set or update a configuration value
     * 
     * @param string $name Configuration name
     * @param mixed $value Configuration value
     * @param string $type Configuration type (string, boolean, password, integer)
     * @return bool
     */
    public static function setConfigValue($name, $value, $type = 'string') {
        global $DB;

        try {
            if (!$DB->tableExists('glpi_plugin_uptimemonitor_configs')) {
                return false;
            }

            $iterator = $DB->request([
                'FROM'   => 'glpi_plugin_uptimemonitor_configs',
                'WHERE'  => ['name' => $name]
            ]);

            if (count($iterator) > 0) {
                // Update existing
                $result = $DB->update('glpi_plugin_uptimemonitor_configs', [
                    'value'    => $value,
                    'type'     => $type,
                    'date_mod' => date('Y-m-d H:i:s')
                ], [
                    'name' => $name
                ]);
                error_log("DEBUG: Updated config '$name' = '$value' (type: $type), result: $result");
            } else {
                // Insert new
                $result = $DB->insert('glpi_plugin_uptimemonitor_configs', [
                    'name'        => $name,
                    'value'       => $value,
                    'type'        => $type,
                    'entities_id' => 0
                ]);
                error_log("DEBUG: Inserted config '$name' = '$value' (type: $type), result: $result");
            }

            return true;
        } catch (Exception $e) {
            error_log('ERROR: Failed to set config value: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all configurations as an array
     * 
     * @return array All configurations with name => value
     */
    public static function getAllConfigs() {
        global $DB;

        $configs = [];
        
        try {
            if (!$DB->tableExists('glpi_plugin_uptimemonitor_configs')) {
                return $configs;
            }

            $iterator = $DB->request([
                'FROM' => 'glpi_plugin_uptimemonitor_configs',
                'ORDER' => 'name ASC'
            ]);

            foreach ($iterator as $row) {
                $configs[$row['name']] = $row['value'];
            }
        } catch (Exception $e) {
            error_log('Error getting all configs: ' . $e->getMessage());
        }

        return $configs;
    }

    /**
     * Test Telegram connection
     * 
     * @param string $api_key Telegram Bot API Key
     * @param string $chat_id Chat ID
     * @return array Result array with 'success' and 'message'
     */
    public static function testTelegramConnection($api_key, $chat_id) {
        if (empty($api_key) || empty($chat_id)) {
            return [
                'success' => false,
                'message' => __('API Key e Chat ID são obrigatórios', 'uptimemonitor')
            ];
        }

        $url = "https://api.telegram.org/bot{$api_key}/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => __('✅ Teste de Conexão - Uptime Monitor', 'uptimemonitor')
        ];

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return [
                'success' => false,
                'message' => __('Erro ao conectar com Telegram API', 'uptimemonitor')
            ];
        }

        $result = json_decode($response, true);

        if (isset($result['ok']) && $result['ok'] === true) {
            return [
                'success' => true,
                'message' => __('Conexão com Telegram estabelecida com sucesso!', 'uptimemonitor')
            ];
        }

        return [
            'success' => false,
            'message' => isset($result['description']) ? $result['description'] : __('Erro desconhecido', 'uptimemonitor')
        ];
    }

    /**
     * Test Slack connection
     * 
     * @param string $webhook_url Slack Webhook URL
     * @return array Result array with 'success' and 'message'
     */
    public static function testSlackConnection($webhook_url) {
        if (empty($webhook_url)) {
            return [
                'success' => false,
                'message' => __('Webhook URL é obrigatório', 'uptimemonitor')
            ];
        }

        $data = json_encode([
            'text' => '✅ Teste de Conexão - Uptime Monitor'
        ]);

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => $data,
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($webhook_url, false, $context);

        if ($response === false) {
            return [
                'success' => false,
                'message' => __('Erro ao conectar com Slack', 'uptimemonitor')
            ];
        }

        return [
            'success' => true,
            'message' => __('Conexão com Slack estabelecida com sucesso!', 'uptimemonitor')
        ];
    }

    /**
     * Send notification via Telegram
     * 
     * @param string $message Message to send
     * @return bool Success status
     */
    public static function sendTelegramNotification($message) {
        if (self::getConfigValue('telegram_enabled') != '1') {
            return false;
        }

        $api_key = self::getConfigValue('telegram_api_key');
        $chat_id = self::getConfigValue('telegram_chat_id');

        if (empty($api_key) || empty($chat_id)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$api_key}/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        return $response !== false;
    }

    /**
     * Send notification via Slack
     * 
     * @param string $message Message to send
     * @param string $color Message color (good, warning, danger)
     * @return bool Success status
     */
    public static function sendSlackNotification($message, $color = 'good') {
        if (self::getConfigValue('slack_enabled') != '1') {
            return false;
        }

        $webhook_url = self::getConfigValue('slack_webhook_url');

        if (empty($webhook_url)) {
            return false;
        }

        $data = json_encode([
            'attachments' => [
                [
                    'color' => $color,
                    'title' => __('Uptime Monitor', 'uptimemonitor'),
                    'text' => $message
                ]
            ]
        ]);

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => $data,
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($webhook_url, false, $context);

        return $response !== false;
    }
}
