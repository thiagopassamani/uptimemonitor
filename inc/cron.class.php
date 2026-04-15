<?php
/**
 * Plugin Uptime Monitor para GLPI
 * Desenvolvido por: Thiago Passamani 
 * Data: 2024-06
 * Descrição: Este plugin monitora a disponibilidade de sites e servidores, integrando-se ao GLPI para criar tickets automaticamente em caso de falhas e enviar notificações via Telegram. Ele suporta testes HTTP, Ping e de Porta, além de permitir a configuração de janelas de manutenção para evitar falsos positivos durante períodos programados de inatividade.
 * @class PluginUptimemonitorCron
 * @description Classe responsável por executar as verificações de uptime, consolidar logs, gerenciar notificações e integrar com o sistema de tickets do GLPI. Ela é acionada por uma Ação Automática (Cron) e processa os monitores configurados, garantindo que as verificações sejam feitas de acordo com a criticidade definida para cada monitor.
*/

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginUptimemonitorCron {

    /**
     * Informações da Ação Automática para aparecer no painel do GLPI
     */
    static function cronInfo($name) {
        switch ($name) {
            case 'check':
                return [
                    'description' => __('Verifica o status dos sites/servidores', 'uptimemonitor'),
                    'parameter'   => __('Lote', 'uptimemonitor') // Opcional
                ];
        }
        return [];
    }

    /**
     * Função de compatibilidade para quem ainda chama cronCheckUptime
     */
    static function cronCheckUptime($task) {
        return self::cronCheck($task);
    }

    /**
     * A função que realmente executa o teste e consolida Timer, Notificações e Logs
     */
    static function cronCheck($task) {
        global $DB;

        $inst = new PluginUptimemonitorMonitor();

        // 1. Busca monitores ativos que NÃO estão em janela de manutenção

        $date_hora_atual = date('Y-m-d H:i:s');
        $monitors = $DB->request([
            'FROM'  => 'glpi_plugin_uptimemonitor_monitors',
            'WHERE' => ['is_active' => 1]
        ]);

        $total_processados = 0;
        $agora = time();

        // CORREÇÃO DOS TEMPOS: High (Alta) deve ser o mais rápido, Low (Baixa) o mais demorado
        $frequencias = [
            'test'   => 30,  // 30 segundos - Para testes rápidos
            'high'   => 60,  // 1 minuto - Alta (Produção / Missão Crítica)
            'medium' => 300, // 5 minutos - Média (Serviços Internos)
            'low'    => 900  // 15 minutos - Baixa (Ambientes de Teste/Dev)
        ];

        foreach ($monitors as $monitor) {
            $id = $monitor['id'];
            $url = $monitor['url'];
            $type = $monitor['type'];

            // Verifica se o monitor está em janela de manutenção (caso a manutenção seja programada, mas o campo is_maintenance ainda esteja 0)
            if ($monitor['is_maintenance'] == 1) {
                // Tem data de fim e ela já passou?
                if (!empty($monitor['maintenance_end']) && strtotime($monitor['maintenance_end']) <= $agora) {

                    // 1. Tira do modo de manutenção no Banco de Dados
                    $DB->update('glpi_plugin_uptimemonitor_monitors', [
                        'is_maintenance' => 0,
                        'maintenance_start' => null,
                        'maintenance_end' => null
                    ], ['id' => $id]);

                    // 2. Atualiza a variável local para o teste rodar agora
                    $monitor['is_maintenance'] = 0;

                    // 3. (Opcional) Enviar Telegram avisando que a manutenção acabou
                    $host_name = $monitor['name'] ?: $url;

                    PluginUptimemonitorConfig::sendTelegramNotification('service_maintenance_end', $host_name);                    

                } else {
                    // A manutenção ainda está válida!

                    // Verifica a frequência antes de logar para não inundar o banco
                    $criticality = !empty($monitor['criticality']) ? strtolower($monitor['criticality']) : 'medium';
                    $ultimo_check = !empty($monitor['last_check']) ? strtotime($monitor['last_check']) : 0;
                    $intervalo_necessario = (!empty($monitor['check_interval']) && is_numeric($monitor['check_interval'])) ? (int)$monitor['check_interval'] * 60 : (isset($frequencias[$criticality]) ? $frequencias[$criticality] : 300);

                    if (($agora - $ultimo_check) >= $intervalo_necessario) {

                        // Atualiza a data do último check no monitor
                        $DB->update('glpi_plugin_uptimemonitor_monitors', [
                            'last_check'  => date('Y-m-d H:i:s')
                        ], ['id' => $id]);

                        // Grava o log de manutenção com tempo de resposta zerado
                        $DB->insert('glpi_plugin_uptimemonitor_logs', [
                            'plugin_uptimemonitor_monitors_id' => $id,
                            'status'           => 'MAINT',
                            'response_time_ms' => 0,
                            'date_creation'    => date('Y-m-d H:i:s')
                        ]);

                        $total_processados++;
                    }

                    // Pula o teste real de rede (Ping/HTTP) para não gerar alertas falsos
                    continue;
                }
            }
            // Sanitiza a criticidade (garante que seja minúsculo para bater com o array)
            // Se estiver vazio, assume 'medium' como padrão de segurança
            $criticality = !empty($monitor['criticality']) ? strtolower($monitor['criticality']) : 'medium';
            $old_status = $monitor['last_status'];

            // 2. VERIFICAÇÃO DE FREQUÊNCIA (NOVIDADE AQUI)
            // Pega a data do último check. Se for nulo/vazio, assume 0 para testar imediatamente
            $ultimo_check = !empty($monitor['last_check']) ? strtotime($monitor['last_check']) : 0;
            $intervalo_necessario = (!empty($monitor['check_interval']) && is_numeric($monitor['check_interval'])) ? (int)$monitor['check_interval'] * 60 : (isset($frequencias[$criticality]) ? $frequencias[$criticality] : 300);

            // Se ainda não passou o tempo necessário, pula para o próximo host do foreach
            if (($agora - $ultimo_check) < $intervalo_necessario) {
                continue;
            }

            // 3. INICIA O CRONÓMETRO ANTES DO TESTE
            $start_time = microtime(true);

            // 4. EXECUTA O TESTE DINÂMICO
            $new_status = self::testTarget($type, $url);

            // 5. PARA O CRONÓMETRO E CALCULA
            $end_time = microtime(true);
            $response_time = round(($end_time - $start_time) * 1000);

            if ($new_status === 'DOWN') {
                $response_time = 0;
            }

            // 6. DISPARA NOTIFICAÇÕES SE O STATUS MUDAR
            /* 15/04/2025 - Thiago Passamani - Ajuste na lógica de notificações para evitar alertas falsos e garantir que sejam enviados apenas quando houver mudança real de status, considerando também a janela de manutenção. */
            /*
            if ($old_status !== $new_status) {
                if ($inst->getFromDB($id)) {
                    if ($new_status === 'DOWN') {
                        // Abre ticket automaticamente
                        self::handleServiceDown($monitor);

                        NotificationEvent::raiseEvent('status_down', $inst);

                        // Telegram Notification (NOVIDADE)
                        $host_name = $monitor['name'] ?: $monitor['url'];

                        PluginUptimemonitorConfig::sendTelegramNotification('service_down', $host_name);
                        PluginUptimemonitorConfig::sendSlackNotification($message, 'danger');

                    } elseif ($new_status === 'UP' && $old_status === 'DOWN') {
                        // Fecha ticket automaticamente se existir
                        self::handleServiceUp($monitor);
                        NotificationEvent::raiseEvent('status_up', $inst);

                        // Telegram Notification (NOVIDADE)
                        $host_name = $monitor['name'] ?: $monitor['url'];
                                        
                        PluginUptimemonitorConfig::sendTelegramNotification('service_up', $host_name);
                        PluginUptimemonitorConfig::sendSlackNotification($message, 'good');
                    }
                }
            }*/
            // Define o número máximo de falhas antes de acionar o alerta. 
            // No futuro, pode buscar este valor de PluginUptimemonitorConfig
            $max_retries = 3; 
                
            // Recupera as tentativas atuais ou assume 0
            $current_attempts = isset($monitor['failed_attempts']) ? (int)$monitor['failed_attempts'] : 0;
            if ($new_status === 'DOWN') {
                $current_attempts++;
                // Grava a tentativa falhada na base de dados
                $DB->update(
                    'glpi_plugin_uptimemonitor_monitors',
                    ['failed_attempts' => $current_attempts],
                    ['id' => $monitor['id']]
                );
                // Apenas abre ticket e notifica SE atingiu o limite de retries 
                // E se não estava já marcado como DOWN anteriormente (evita flood)
                if ($current_attempts >= $max_retries && $old_status !== 'DOWN') {
                    
                    // Abre ticket automaticamente
                    self::handleServiceDown($monitor);
                    NotificationEvent::raiseEvent('status_down', $inst);
                    // Telegram & Slack Notification
                    $host_name = $monitor['name'] ?: $monitor['url'];
                    PluginUptimemonitorConfig::sendTelegramNotification('service_down', $host_name);
                    PluginUptimemonitorConfig::sendSlackNotification('service_down', $host_name, 'danger');
                    
                    // Força a atualização do status global para DOWN
                    $DB->update(
                        'glpi_plugin_uptimemonitor_monitors',
                        ['status' => 'DOWN'],
                        ['id' => $monitor['id']]
                    );
                }
            } elseif ($new_status === 'UP') {
                // Se o serviço respondeu com sucesso, limpamos imediatamente o contador de falhas
                if ($current_attempts > 0) {
                    $DB->update(
                        'glpi_plugin_uptimemonitor_monitors',
                        ['failed_attempts' => 0],
                        ['id' => $monitor['id']]
                    );
                }
                // Se estava DOWN e agora está UP, tratamos a recuperação
                if ($old_status === 'DOWN') {
                    // Fecha ticket automaticamente se existir
                    self::handleServiceUp($monitor);
                    NotificationEvent::raiseEvent('status_up', $inst);
                    // Telegram & Slack Notification
                    $host_name = $monitor['name'] ?: $monitor['url'];

                    PluginUptimemonitorConfig::sendTelegramNotification('service_up', $host_name);
                    PluginUptimemonitorConfig::sendSlackNotification('service_up', $host_name, 'good');

                    // Força a atualização do status global para UP
                    $DB->update(
                        'glpi_plugin_uptimemonitor_monitors',
                        ['status' => 'UP'],
                        ['id' => $monitor['id']]
                    );
                }
            }

            // 7. ATUALIZA O STATUS NO MONITOR PRINCIPAL
            $DB->update('glpi_plugin_uptimemonitor_monitors', [
                'last_status' => $new_status,
                'last_check'  => date('Y-m-d H:i:s')
            ], [
                'id' => $id
            ]);

            // 
            $DB->insert('glpi_plugin_uptimemonitor_logs', [
                'plugin_uptimemonitor_monitors_id' => $id,
                'status'           => $new_status,
                'response_time_ms' => $response_time,
                'date_creation'    => date('Y-m-d H:i:s')
            ]);

            $total_processados++;
        }

        $task->setVolume($total_processados);
        return ($total_processados > 0) ? 1 : 0;
    }

    /**
     * Lógica de teste por tipo (HTTP, Ping, Porta) - Retorna 'UP' ou 'DOWN'
     * @function testTarget
     * @param string $type Tipo de teste: 'http', 'ping' ou 'port'
     * @param string $url URL ou endereço a ser testado
     */
    static function testTarget($type, $url) {
        if (empty($url)) {
            return 'DOWN';
        }

        switch ($type) {
            case 'http':
                if (!filter_var($url, FILTER_VALIDATE_URL)) return 'DOWN';               

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return ($code >= 200 && $code < 400) ? 'UP' : 'DOWN';

            case 'ping':
                $host = parse_url($url, PHP_URL_HOST) ?: $url;
                if (empty($host) || (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
                    return 'DOWN';
                }
                $exec = stristr(PHP_OS, 'WIN')
                    ? "ping -n 1 -w 1000 " . escapeshellarg($host)
                    : "LC_ALL=C /bin/ping -c 1 -W 3 " . escapeshellarg($host);
                exec($exec, $output, $result);
                return ($result === 0) ? 'UP' : 'DOWN';

            case 'port':
                $parts = parse_url($url);
                $host = $parts['host'] ?? $url;
                $port = $parts['port'] ?? 80;

                if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    return 'DOWN';
                }

                $port = intval($port);
                if ($port < 1 || $port > 65535) {
                    return 'DOWN';
                }

                $connection = @fsockopen($host, $port, $errno, $errstr, 5);
                if ($connection) {
                    fclose($connection);
                    return 'UP';
                }

                return 'DOWN';
        }

        return 'DOWN';
    }

    /**
     * Trata queda de serviço - abre ticket automaticamente
     */
    private static function handleServiceDown($monitor) {
        global $DB;

        // Verifica se o monitor tem configuração explícita (0 ou 1), se não tiver (null/não definido), usa a config global
        if (array_key_exists('auto_create_ticket', $monitor) && $monitor['auto_create_ticket'] !== null) {
            $auto = $monitor['auto_create_ticket'];
        } else {
            $auto = PluginUptimemonitorConfig::getConfigValue('create_ticket_on_down', 0);
        }
    
        if (empty($auto)) return;

        // Evita duplicar chamado se já houver um aberto
        if (!empty($monitor['current_tickets_id'])) {
            return;
        }

        // Categoria: prioriza a do monitor, cai na config global se não definida
        $itilcategories_id = (!empty($monitor['itilcategories_id']) && $monitor['itilcategories_id'] > 0) ? $monitor['itilcategories_id'] : (PluginUptimemonitorConfig::getAllConfigs()['ticket_category'] ?? 0);
        $ticket = new Ticket();
        $tickets_id = $ticket->add([
            'name'              => '🚨 ALERTA DE QUEDA: ' . $monitor['name'],
            'content'           => "O serviço <b>{$monitor['name']}</b> ({$monitor['url']}) está fora do ar.",
            'itilcategories_id' => $itilcategories_id,
            'status'            => Ticket::INCOMING,
            'urgency'           => 4,
            'type'              => Ticket::INCIDENT_TYPE,
            'entities_id'       => $monitor['entities_id'],
            '_auto_import'      => true,
        ]);

        if ($tickets_id > 0) {
            // Vincula ao Ativo
            if (!empty($monitor['itemtype']) && $monitor['items_id'] > 0) {
                $it = new Item_Ticket();
                $it->add([
                    'tickets_id' => $tickets_id, 
                    'itemtype' => $monitor['itemtype'], 
                    'items_id' => $monitor['items_id']
                ]);
            }
            // Atribui ao Grupo
            if ($monitor['groups_id_tech'] > 0) {
                $gt = new Group_Ticket();
                $gt->add([
                    'tickets_id' => $tickets_id, 
                    'groups_id' => $monitor['groups_id_tech'], 
                    'type' => CommonITILActor::ASSIGN
                ]);
            }
            $DB->update(
                'glpi_plugin_uptimemonitor_monitors', 
                ['current_tickets_id' => $tickets_id], 
                ['id' => $monitor['id']]
            );
        }

        $host_name = $monitor['name'] ?: $monitor['url'];

        PluginUptimemonitorConfig::sendTelegramNotification('service_down', $host_name);
        PluginUptimemonitorConfig::sendSlackNotification('service_down', $host_name);
    }

    /**
     * Trata recuperação de serviço - fecha ticket automaticamente
     */
    private static function handleServiceUp($monitor) {
        global $DB;
        
        if ($monitor['current_tickets_id'] > 0) {

            $ticket_id = $monitor['current_tickets_id'];;
            
            $message = " ✅ Monitor de Uptime\n O serviço <b>{$monitor['name']}</b> está restabelecido! Finalizado o atendimento automaticamente pelo Monitor de Uptime.";
            $message .= "\n\n Verificado em: " . date('d/m/Y H:i:s');

            // Adiciona Solução
            $sol = new ITILSolution();
            $sol->add([
                'itemtype' => 'Ticket',
                'items_id' => $ticket_id,
                'content'  => $message,
            ]);

            // Resolve o chamado
            $t = new Ticket();
            $t->update(['id' => $ticket_id, 'status' => Ticket::SOLVED]);

            // Limpa ID no monitor
            $DB->update(
                'glpi_plugin_uptimemonitor_monitors', 
                ['current_tickets_id' => 0], 
                ['id' => $monitor['id']]
            );
        }

        $host_name = $monitor['name'] ?: $monitor['url'];
        
        PluginUptimemonitorConfig::sendTelegramNotification('service_up', $host_name);
        PluginUptimemonitorConfig::sendSlackNotification('service_up', $host_name, 'good');
    }
}