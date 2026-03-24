<?php

if (!defined('GLPI_ROOT')) { die("Access denied"); }

class PluginUptimemonitorPoller extends CommonDBTM {

static function cronInfo($name) {
        return [
            'description' => 'Verifica o estado dos monitores e gere chamados automáticos',
            'state'       => 1,
            'mode'        => 2  // CLI
        ];
    }

    static function cronUptimeCheck($task) {
        global $DB;

        // Busca apenas os monitores ativos
        $result = $DB->request([
            'FROM'  => 'glpi_plugin_uptimemonitor_monitors',
            'WHERE' => ['is_active' => 1, 'is_maintenance' => 0]
        ]);

        $total_processados = 0;

        foreach ($result as $monitor) {
            // Verifica se o monitor está em janela de manutenção (caso a manutenção seja programada, mas o campo is_maintenance ainda esteja 0)
            if (self::isInMaintenanceWindow($monitor)) {
                continue; 
            }
            // Determina o intervalo de teste com base na urgência
            $intervalo_minutos = 15; // Padrão
            if (isset($monitor['urgency'])) {
                switch ($monitor['urgency']) {
                    case 5: case 4: $intervalo_minutos = 1; break;
                    case 3: $intervalo_minutos = 5; break;
                }
            }

            $ultimo_teste = strtotime($monitor['last_check'] ?? '1970-01-01');
            $agora = time();

            if (($agora - $ultimo_teste) >= ($intervalo_minutos * 60)) {
                
                // Executar o Teste
                $resultado_teste = self::testTarget($monitor['type'], $monitor['url']);
                $novo_status = $resultado_teste['status'];
                $tempo_resposta = $resultado_teste['tempo_ms'];

                // Atualização e Logs
                $DB->update('glpi_plugin_uptimemonitor_monitors', [
                    'status'     => $novo_status,
                    'last_check' => date('Y-m-d H:i:s')
                ], [
                    'id'         => $monitor['id']
                ]);

                // Verifica se está em manutenção (usando a função que discutimos anteriormente)
                $em_manutencao = self::isInMaintenanceWindow($monitor);

                $DB->insert('glpi_plugin_uptimemonitor_logs', [
                    'monitors_id'      => $monitor['id'],
                    'status'           => $novo_status,
                    'response_time_ms' => $tempo_resposta,
                    'is_maintenance'   => $em_manutencao ? 1 : 0, // Flag para relatórios futuros
                    'date_creation'    => date('Y-m-d H:i:s')
                ]);

                // Gatilhos (Notificações e ITIL)
                $estado_anterior = $monitor['status'] ?? 'UP';       

                if ($estado_anterior !== $novo_status) {

                    // Se estiver em manutenção, ignoramos a abertura/fechamento de chamados
                    if (!$em_manutencao) {
                        if ($novo_status === 'DOWN') {
                            self::handleServiceDown($monitor);
                            NotificationEvent::raiseEvent('plugin_uptimemonitor_down', $monitor);
                            self::sendTelegramNotification("🚨 O serviço {$monitor['name']} está OFFLINE!");
                        } else if ($novo_status === 'UP') {
                            self::handleServiceUp($monitor);
                            NotificationEvent::raiseEvent('plugin_uptimemonitor_up', $monitor);
                            self::sendTelegramNotification("✅ O serviço {$monitor['name']} está ONLINE!");
                        }
                    } else {
                        // Opcional: Logar no log do GLPI ou no arquivo de log que o status mudou, 
                        // mas o alerta foi suprimido por manutenção.
                        Toolbox::logInFile('uptimemonitor', "Mudança de status ignorada para {$monitor['name']} (Em Manutenção)\n");
                    }
                }

                if ($estado_anterior !== $novo_status) {
                    if ($novo_status === 'DOWN') {
                        self::handleServiceDown($monitor);
                        NotificationEvent::raiseEvent('plugin_uptimemonitor_down', $monitor);
                        self::sendTelegramNotification("🚨 O serviço {$monitor['name']} está OFFLINE!");
                    } else if ($novo_status === 'UP') {
                        self::handleServiceUp($monitor);
                        NotificationEvent::raiseEvent('plugin_uptimemonitor_up', $monitor);
                        self::sendTelegramNotification("✅ O serviço {$monitor['name']} está ONLINE!");
                    }
                }

                $total_processados++;
            }
        }

        $task->setVolume($total_processados);
        return ($total_processados > 0) ? 1 : 0;
    }

    /**
    * Verifica se o monitor está dentro de uma janela de manutenção
    */
    private static function isInMaintenanceWindow($monitor) {
        $agora = date('Y-m-d H:i:s');

        // Se o monitor tem datas específicas de manutenção
        if (!empty($monitor['begin_maintenance']) && !empty($monitor['end_maintenance'])) {
            if ($agora >= $monitor['begin_maintenance'] && $agora <= $monitor['end_maintenance']) {
                return true;
            }
        }

        // Opcional: Verificar se o ATIVO vinculado está com status "Manutenção"
        if ($monitor['itemtype'] && $monitor['items_id'] > 0) {
            $item = new $monitor['itemtype']();
            if ($item->getFromDB($monitor['items_id'])) {
                // Se o status do item no GLPI (states_id) for o ID de "Manutenção"
                // Supondo que o ID do status manutenção seja 2 (ajuste conforme seu GLPI)
                if (isset($item->fields['states_id']) && $item->fields['states_id'] == 2) {
                    return true;
                }
            }
        }

        return false;
    }

    // Função isolada para executar o teste (cURL)
    private static function testTarget($tipo, $url) {
        $start_time = microtime(true);
        $status = 'DOWN';
        
        $tipo = strtolower($tipo);
        if ($tipo === 'http' || $tipo === 'https') {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // No método testTarget--- 
            $curl_result = curl_exec($ch);
            if ($curl_result === false) {
                $error_message = curl_error($ch);
            }

            if ($http_code >= 200 && $http_code < 400) {
                $status = 'UP';
            }
        }

        $end_time = microtime(true);
        $tempo_ms = round(($end_time - $start_time) * 1000);

        return [
            'status'   => $status,
            'tempo_ms' => $tempo_ms
        ];
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