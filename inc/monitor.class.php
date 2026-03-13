<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginUptimemonitorMonitor extends CommonDBTM {

   // 1. O Direito para segurança continua estático
   static $rightname = 'config';

   // Removido o "static" destas funções
   function isEntityAssign() {
      return true;
   }

   function maybeRecursive() {
      return true;
   }

   static function getTypeName($nb = 0) {
      return _n('Uptime Monitor', 'Uptime Monitor', $nb, 'uptimemonitor');
   }

   function rawSearchOptions() {
        $tab = [];

        $tab[] = [
            'id'    => '1',
            'table' => $this->getTable(),
            'field' => 'name',
            'name'  => __('Nome'),
            'datatype'           => 'itemlink', // Isto força o GLPI a gerar o link de edição
            'itemlink_type'      => 'PluginUptimemonitorMonitor',
            'massiveaction'      => true
        ];

        $tab[] = [
            'id'    => '2',
            'table' => $this->getTable(),
            'field' => 'url',
            'name'  => __('Alvo (URL/IP)')
        ];

        $tab[] = [
            'id'    => '3',
            'table' => $this->getTable(),
            'field' => 'last_status',
            'name'  => __('Status Atual'),
            'datatype' => 'specific' // Isso permite formatar a cor na lista
        ];

        $tab[] = [
            'id'    => '4',
            'table' => $this->getTable(),
            'field' => 'type',
            'name'  => __('Tipo'),
            'datatype' => 'specific'
        ];

        $tab[] = [
            'id'    => '5',
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name'  => __('Entidade')
        ];

        $tab[] = [
            'id'    => '6',
            'table' => $this->getTable(),
            'field' => 'criticality',
            'name'  => __('Criticidade'),
            'datatype' => 'specific'
        ];

        return $tab;
    }

    static function displaySpecificValue($options = []) {
        switch ($options['field']) {
            case 'last_status':
                $value = $options['value'];
                if ($value == 'UP') {
                    return "<span class='badge bg-green-900 text-green-fg'>UP</span>";
                } elseif ($value == 'DOWN') {
                    return "<span class='badge bg-red-900 text-red-fg'>DOWN</span>";
                }
                return "<span class='badge bg-grey-500'>PENDENTE</span>";

            case 'type':
                $tipos = [
                    'http' => 'Página Web',
                    'ping' => 'Ping (ICMP)',
                    'port' => 'Porta TCP'
                ];
                return $tipos[$options['value']] ?? $options['value'];
        }
        return parent::displaySpecificValue($options);
    }

    function showForm($ID, array $options = []) {
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // Nome e Status
        echo "<tr class='tab_bg_1'>";
        echo "<td>Nome do Serviço:</td>";
        echo "<td>";
        echo Html::input("name", ['value' => $this->fields['name']]);
        echo "</td>";
        
        echo "<td>Ativo:</td>";
        echo "<td>";
        Dropdown::showYesNo("is_active", $this->fields["is_active"] ?? 1);
        echo "</td>";
        echo "</tr>";

        // Tipo de Verificação e URL
        echo "<tr class='tab_bg_1'>";
        echo "<td>Tipo de Verificação:</td>";
        echo "<td>";
        $tipos_verificacao = [
            'http' => 'Página Web (HTTP / HTTPS)',
            'ping' => 'Ping (ICMP)',
            'port' => 'Porta TCP'
        ];
        Dropdown::showFromArray('type', $tipos_verificacao, [
            'value'   => $this->fields['type'] ?? 'http',
            'display' => true
        ]);
        echo "</td>";
        
        // Host
        echo "<td>Alvo (URL ou IP):</td>";
        echo "<td>";
        echo Html::input("url", ['value' => $this->fields['url']]);
        echo "<br><span style='font-size: 0.8em; color: #666;'>HTTP: https://... | Ping: 192.168... | Porta: IP:PORTA</span>";
        echo "</td>";
        echo "</tr>";

        // Criticidade
        echo "<tr class='tab_bg_1'>";
        echo "<td>Criticidade</td>";
        echo "<td>";
        echo "<select name='criticality'>";
        $criticality_levels = [
            'low'    => 'Baixa (Ambientes de Teste/Dev)',
            'medium' => 'Média (Serviços Internos)',
            'high'   => 'Alta (Produção / Missão Crítica)'
        ];
        foreach ($criticality_levels as $key => $label) {
            $selected = ($this->fields['criticality'] ?? '') == $key ? 'selected' : '';
            echo "<option value='$key' $selected>$label</option>";
        }
        echo "</select>";
        echo "</td>";
        echo "</tr>";

        // Intervalo e Badge de Status
        echo "<tr class='tab_bg_1'>";
        echo "<td>Intervalo (minutos):</td>";
        echo "<td>";
        echo Html::input("check_interval", ['type' => 'number', 'min' => 1, 'value' => $this->fields['check_interval'] ?? 5]);
        echo "</td>";

        echo "<td>Status Atual:</td>";
        echo "<td>";
        $status_atual = $this->fields["last_status"] ?? 'PENDING';
        if ($status_atual == 'UP') {
            echo "<span style='background-color: #28a745; color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold;'>UP</span>";
        } elseif ($status_atual == 'DOWN') {
            echo "<span style='background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold;'>DOWN</span>";
        } else {
            echo "<span style='background-color: #6c757d; color: white; padding: 5px 10px; border-radius: 4px;'>Aguardando Teste</span>";
        }
        echo "</td>";
        echo "</tr>";

        // Integração ITIL (Ativos e Grupos)
        echo "<tr class='tab_bg_1'>";
        echo "<td>Vincular ao Ativo (Inventário):</td>";
        echo "<td>";
        Dropdown::showSelectItemFromItemtypes([
            'itemtypes'        => ['Computer', 'NetworkEquipment', 'Software'],
            'itemtype_name'    => 'itemtype',
            'items_id_name'    => 'items_id',
            'itemtype_default' => $this->fields['itemtype'] ?? '',
            'items_id_default' => $this->fields['items_id'] ?? 0,
            'entity_restrict'  => $_SESSION['glpiactive_entity'] ?? -1
        ]);
        echo "</td>";

        echo "<td>Grupo Técnico Responsável:</td>";
        echo "<td>";
        Group::dropdown([
            'name'      => 'groups_id_tech',
            'value'     => $this->fields['groups_id_tech'] ?? 0,
            'condition' => ['is_assign' => 1]
        ]);
        echo "</td>";
        echo "</tr>";

        // Janela de Manutenção
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4' class='center b'>Agendamento de Manutenção (Silenciar Alertas)</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>Ativar Janela de Manutenção:</td>";
        echo "<td>";
        Dropdown::showYesNo("is_maintenance", $this->fields["is_maintenance"] ?? 0);
        echo "</td>";

        echo "<td>Período da Manutenção:</td>";
        echo "<td>";
        echo "Início: ";
        Html::showDateTimeField("maintenance_start", ['value' => $this->fields["maintenance_start"] ?? '']);
        echo "<br>Fim: ";
        Html::showDateTimeField("maintenance_end", ['value' => $this->fields["maintenance_end"] ?? '']);
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);

        return true;
    }

    public function prepareInputForAdd($input) {
        // Se a data de manutenção estiver vazia, removemos do INSERT para o MySQL usar o padrão (NULL)
        if (isset($input['maintenance_start']) && empty($input['maintenance_start'])) {
            unset($input['maintenance_start']);
        }
        if (isset($input['maintenance_end']) && empty($input['maintenance_end'])) {
            unset($input['maintenance_end']);
        }
        return $input;
    }

    public function prepareInputForUpdate($input) {
        return $this->prepareInputForAdd($input);
    }


    /**
     * Método chamado pela Ação Automática do GLPI
     */
    static function cronCheckUptime($task) {
        global $DB;
        $inst = new self();

        // Busca monitores ativos que não estão em manutenção
        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_uptimemonitor_monitors',
            'WHERE' => [
                'is_active' => 1,
                '_OR' => [
                    ['is_maintenance' => 0],
                    ['maintenance_end' => ['<', date('Y-m-d H:i:s')]]
                ]
            ]
        ]);

        $count = 0;
        foreach ($iterator as $row) {

            // Dentro do foreach do cronCheckUptime em monitor.class.php
            $old_status = $row['last_status'];
            $new_status = self::testTarget($row['type'], $row['url']);

            if ($old_status !== $new_status) {
                // Se o status mudou para DOWN, dispara o evento
                if ($new_status === 'DOWN') {
                    NotificationEvent::raiseEvent('status_down', $inst);
                } 
                // Se o status voltou para UP, dispara o evento de recuperação
                elseif ($new_status === 'UP' && $old_status === 'DOWN') {
                    NotificationEvent::raiseEvent('status_up', $inst);
                }
            }

           // $new_status = self::testTarget($row['type'], $row['url']);
            
            // Atualiza o banco de dados
            $DB->update('glpi_plugin_uptimemonitor_monitors', [
                'last_status' => $new_status,
                'last_check'  => date('Y-m-d H:i:s')
            ], ['id' => $row['id']]);
            
            $count++;

            
        }

        $task->addVolume($count); // Log de quantos foram verificados
        return 1; // Sucesso
    }

    /**
     * Lógica de teste por tipo
     */
    static function testTarget($type, $url) {
        switch ($type) {
            case 'http':
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return ($code >= 200 && $code < 400) ? 'UP' : 'DOWN';

            case 'ping':
                $host = parse_url($url, PHP_URL_HOST) ?: $url;
                $exec = stristr(PHP_OS, 'WIN') ? "ping -n 1 -w 1000 $host" : "ping -c 1 -W 1 $host";
                exec($exec, $output, $result);
                return ($result === 0) ? 'UP' : 'DOWN';
 
               case 'port':
                // Espera formato IP:PORTA
                $parts = explode(':', $url);
                $ip = $parts[0];
                $port = $parts[1] ?? 80;
                $connection = @fsockopen($ip, $port, $errno, $errstr, 5);
                if (is_resource($connection)) {
                    fclose($connection);
                    return 'UP';
                }
                return 'DOWN';
        }
        return 'DOWN';
    }

    // Dentro da classe PluginUptimemonitorMonitor
   static function getEvents() {
      return [
         'status_down' => __('Serviço Fora do Ar', 'uptimemonitor'),
         'status_up'   => __('Serviço Restabelecido', 'uptimemonitor')
      ];
   }
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
    if ($item->getType() == __CLASS__) {
        return [
            'stats' => __('Estatísticas', 'uptimemonitor'),
        ];
    }
    return '';
}

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($tabnum == 'stats') {
           self::showStats($item);
      }
      return true;

   }


static function showStats($item) {
      global $DB;

      // Busca os últimos 50 logs
      $iterator = $DB->request([
         'FROM'     => 'glpi_plugin_uptimemonitor_logs',
         'WHERE'    => ['plugin_uptimemonitor_monitors_id' => $item->getID()],
         'ORDER'    => 'date_creation DESC',
         'LIMIT'    => 50
      ]);
                
      $labels = [];
      $data   = [];
                
      foreach ($iterator as $log) {
         $labels[] = date("H:i", strtotime($log['date_creation']));
         $data[]   = (int)$log['response_time_ms'];
      }
                
      // Inverte para o gráfico ir da esquerda (antigo) para a direita (novo)
      $labels = array_reverse($labels);
      $data   = array_reverse($data);

      // Se não houver dados, avisa o usuário
      if (empty($data)) {
          echo "<div class='center shadow' style='padding:20px;'>";
          echo "<i class='fas fa-exclamation-triangle' style='font-size:30px; color:orange;'></i>";
          echo "<h4>Ainda não existem dados de histórico para este monitor.</h4>";
          echo "<p>Certifique-se de que a Ação Automática está a rodar.</p></div>";
          return;
      }
                
      echo "<div class='center' style='width: 95%; height: 300px; margin: 20px auto;'>";
      echo "<canvas id='uptimeChart'></canvas>";
      echo "</div>";
                
      // Injeção de JS garantindo que o Chart.js está carregado
      echo Html::scriptBlock("
          // Pequeno delay para garantir que o elemento existe no DOM do GLPI
          setTimeout(function() {
              const ctx = document.getElementById('uptimeChart');
              if (ctx) {
                  new Chart(ctx, {
                      type: 'line',
                      data: {
                          labels: " . json_encode($labels) . ",
                          datasets: [{
                              label: 'Tempo de Resposta (ms)',
                              data: " . json_encode($data) . ",
                              borderColor: '#2ecc71',
                              backgroundColor: 'rgba(46, 204, 113, 0.1)',
                              borderWidth: 2,
                              fill: true,
                              pointRadius: 3
                          }]
                      },
                      options: {
                          responsive: true,
                          maintainAspectRatio: false,
                          scales: {
                              y: { beginAtZero: true, title: { display: true, text: 'Milissegundos' } }
                          }
                      }
                  });
              }
          }, 500);
      ");
   }

   static function getTargets() {
      return ['PluginUptimemonitorNotificationTargetMonitor'];
   }

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
     * A função que realmente executa o teste e consolida Timer, Notificações e Logs
     */
    static function cronCheck($task) {
        global $DB;
        $inst = new self(); 

        // 1. Busca monitores ativos que NÃO estão em janela de manutenção
        $query = "SELECT * FROM `glpi_plugin_uptimemonitor_monitors` 
                  WHERE `is_active` = 1 
                  AND (`is_maintenance` = 0 OR `maintenance_end` < '" . date('Y-m-d H:i:s') . "')";
        $monitors = $DB->request($query);
        
        $total_processados = 0;

        foreach ($monitors as $monitor) {
            $id = $monitor['id'];
            $url = $monitor['url'];
            $type = $monitor['type']; 
            $old_status = $monitor['last_status'];
            
            // 2. INICIA O CRONÓMETRO ANTES DO TESTE
            $start_time = microtime(true);
            
            // 3. EXECUTA O TESTE DINÂMICO
            $new_status = self::testTarget($type, $url);
            
            // 4. PARA O CRONÓMETRO E CALCULA
            $end_time = microtime(true);
            $response_time = round(($end_time - $start_time) * 1000); 
            
            if ($new_status === 'DOWN') {
                $response_time = 0; 
            }

            // 5. DISPARA NOTIFICAÇÕES SE O STATUS MUDAR
            if ($old_status !== $new_status) {
                if ($inst->getFromDB($id)) {
                    if ($new_status === 'DOWN') {
                        NotificationEvent::raiseEvent('status_down', $inst);
                    } elseif ($new_status === 'UP' && $old_status === 'DOWN') {
                        NotificationEvent::raiseEvent('status_up', $inst);
                    }
                }
            }
            
            // 6. ATUALIZA O STATUS NO MONITOR PRINCIPAL
            $DB->update('glpi_plugin_uptimemonitor_monitors', [
                'last_status' => $new_status,
                'last_check'  => date('Y-m-d H:i:s')
            ], [
                'id' => $id
            ]);

            // 7. GRAVA O LOG NO HISTÓRICO
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
    
    // Retorna a URL do formulário de criação/edição
    static function getFormURL($full = true) {
        return Toolbox::getItemTypeFormURL(__CLASS__, $full);
    }

    // Retorna a URL da lista de pesquisa
    static function getSearchURL($full = true) {
        return Toolbox::getItemTypeSearchURL(__CLASS__, $full);
    }
    
    /**
     * Define o conteúdo do menu no GLPI 10 (Substitui a necessidade do Superasset)
     */
    static function getMenuContent() {
        $menu = [
            'title' => __("Uptime Monitor", 'uptimemonitor'),
            'page'  => "/plugins/uptimemonitor/front/monitor.php",
            'icon'  => 'fas fa-heartbeat', // Ícone de batimento cardíaco
            'options' => [
                'monitor' => [
                    'title' => __('Uptime Monitor', 'uptimemonitor'),
                    'page'  => "/plugins/uptimemonitor/front/monitor.php",
                    'links' => [
                        'add'    => "/plugins/uptimemonitor/front/monitor.form.php",
                        'search' => "/plugins/uptimemonitor/front/monitor.php",
                    ]
                ]
            ]
        ];
        return $menu;
    }
}