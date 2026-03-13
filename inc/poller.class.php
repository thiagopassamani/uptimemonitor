<?php

if (!defined('GLPI_ROOT')) { die("Access denied"); }

class PluginUptimemonitorPoller extends CommonDBTM {

    static function cronInfo($name) {
        return [
            'description' => 'Verifica o status dos monitores e gere chamados automáticos',
            'state'       => 1,
            'mode'        => 2  // CLI
        ];
    }

    static function cronUptimeCheck($task) {
        global $DB;

        // Busca monitores ativos
        $result = $DB->request(['FROM' => 'glpi_plugin_uptimemonitor_monitors', 'WHERE' => ['is_active' => 1]]);

        foreach ($result as $monitor) {
            $url            = $monitor['url'];
            $tipo           = strtolower($monitor['type']);
            $status_atual   = 'DOWN';
            $tempo_resposta = 0;
            $start_time     = microtime(true); // Definido aqui para todos os testes

            // --- EXECUÇÃO DO TESTE BASEADO NO TIPO ---
            switch ($tipo) {
                case 'http':
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_exec($ch);
                    
                    if (!curl_errno($ch)) {
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        if ($http_code >= 200 && $http_code < 400) {
                            $status_atual = 'UP';
                        }
                        $tempo_resposta = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
                    }
                    curl_close($ch);
                    break;

                case 'port':
                    $parts = explode(':', $url);
                    $host = trim($parts[0]);
                    $port = isset($parts[1]) ? (int)trim($parts[1]) : 80;
                    $fp = @fsockopen($host, $port, $errno, $errstr, 5);
                    if ($fp) {
                        $status_atual = 'UP';
                        $tempo_resposta = round((microtime(true) - $start_time) * 1000);
                        fclose($fp);
                    }
                    break;

                case 'ping':
                    $host = trim(preg_replace('/^https?:\/\//', '', $url));
                    $cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
                           ? "ping -n 1 -w 1000 " . escapeshellarg($host) 
                           : "ping -c 1 -W 1 " . escapeshellarg($host);
                    exec($cmd, $output, $result_code);
                    if ($result_code === 0) {
                        $status_atual = 'UP';
                        $tempo_resposta = round((microtime(true) - $start_time) * 1000);
                    }
                    break;
                
            }

            // Grava o log de performance/disponibilidade
            $DB->insert('glpi_plugin_uptimemonitor_logs', [
                'plugin_uptimemonitor_monitors_id' => $monitor['id'],
                'status'        => $status_atual,
                'response_time' => $tempo_resposta,
                'date_creation' => date("Y-m-d H:i:s")
            ]);

            // --- VERIFICAÇÃO DE MUDANÇA DE STATUS ---
            if ($monitor['last_status'] !== $status_atual) {
                
                // Verifica Janela de Manutenção
                $agora = date("Y-m-d H:i:s");
                $em_manutencao = ($monitor['is_maintenance'] == 1 && 
                                  $agora >= $monitor['maintenance_start'] && 
                                  $agora <= $monitor['maintenance_end']);

                // Ação: O SERVIÇO CAIU
                if ($status_atual == 'DOWN' && !$em_manutencao) {
                    self::handleServiceDown($monitor);
                } 
                // Ação: O SERVIÇO VOLTOU
                elseif ($status_atual == 'UP') {
                    self::handleServiceUp($monitor);
                }

                // Atualiza o Status Principal
                $DB->update('glpi_plugin_uptimemonitor_monitors', [
                    'last_status' => $status_atual,
                    'last_check'  => $agora
                ], ['id' => $monitor['id']]);
            }
        }

        // Limpeza de logs antigos (mais de 7 dias)
        $DB->delete('glpi_plugin_uptimemonitor_logs', ['date_creation' => ['<', date('Y-m-d H:i:s', strtotime('-7 days'))]]);
        
        $task->addVolume(count($result));
        return 1;
    }

    private static function handleServiceDown($monitor) {
        global $DB;
        
        // Só abre ticket se não houver um aberto
        if (empty($monitor['current_tickets_id'])) {
            $ticket = new Ticket();
            $tickets_id = $ticket->add([
                'name'            => '🚨 ALERTA DE QUEDA: ' . $monitor['name'],
                'content'         => "O serviço <b>{$monitor['name']}</b> ({$monitor['url']}) está fora do ar.",
                'status'          => Ticket::INCOMING,
                'urgency'         => 4,
                'type'            => Ticket::INCIDENT_TYPE,
                'entities_id'     => $monitor['entities_id'],
            ]);

            if ($tickets_id > 0) {
                // Vincula ao Ativo
                if (!empty($monitor['itemtype']) && $monitor['items_id'] > 0) {
                    $it = new Item_Ticket();
                    $it->add(['tickets_id' => $tickets_id, 'itemtype' => $monitor['itemtype'], 'items_id' => $monitor['items_id']]);
                }
                // Atribui ao Grupo
                if ($monitor['groups_id_tech'] > 0) {
                    $gt = new Group_Ticket();
                    $gt->add(['tickets_id' => $tickets_id, 'groups_id' => $monitor['groups_id_tech'], 'type' => CommonITILActor::ASSIGN]);
                }
                
                $DB->update('glpi_plugin_uptimemonitor_monitors', ['current_tickets_id' => $tickets_id], ['id' => $monitor['id']]);
            }
        }
    }

    private static function handleServiceUp($monitor) {
        global $DB;
        if ($monitor['current_tickets_id'] > 0) {
            $ticket_id = $monitor['current_tickets_id'];
            
            // Adiciona Solução
            $sol = new ITILSolution();
            $sol->add([
                'itemtype' => 'Ticket',
                'items_id' => $ticket_id,
                'content'  => 'Serviço restabelecido automaticamente pelo Monitor de Uptime.'
            ]);

            // Resolve o chamado
            $t = new Ticket();
            $t->update(['id' => $ticket_id, 'status' => Ticket::SOLVED]);

            // Limpa ID no monitor
            $DB->update('glpi_plugin_uptimemonitor_monitors', ['current_tickets_id' => 0], ['id' => $monitor['id']]);
        }
    }
}