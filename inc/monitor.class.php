<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginUptimemonitorMonitor extends CommonDBTM {

   // Permissão de acesso (definida como "uptimemonitor" para facilitar o controle via interface de perfis do GLPI)
   static $rightname = 'uptimemonitor';

   // Indica que este item pode ser atribuído a uma entidade
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

        echo "<tbody>";
        echo "<tr>";
        echo "<th colspan='4'>" . __('Dados do host', 'uptimemonitor') . "</th>";
        echo "</tr>";
        echo "</tbody>";

        // Nome e Status
        echo "<tr class='tab_bg_1'>";
        echo "<td>Nome do Serviço:</td>";
        echo "<td>";
        echo Html::input("name", ['value' => $this->fields['name']]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>Ativo:</td>";
        echo "<td>";
        Dropdown::showYesNo("is_active", $this->fields["is_active"] ?? 1);
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
        echo Html::input("url", [ 'value'       => $this->fields['url'] ?? '', 'placeholder' => __('HTTP: https://... | Ping: 192.168... | Porta: IP:PORTA', 'uptimemonitor'), 'class' => 'form-control', 'size' => 60 ]);
        echo "</td>";
        echo "</tr>";

        // Criticidade
        echo "<tr class='tab_bg_1'>";
        echo "<td>Criticidade</td>";
        echo "<td>";
        echo "<select name='criticality'>";
        $criticality_levels = [
            'test'   => __('Teste (Intervalo de 30 segundos)', 'uptimemonitor'),
            'low'    => __('Baixa (Ambientes de Teste/Dev)', 'uptimemonitor'),
            'medium' => __('Média (Serviços Internos)', 'uptimemonitor'),
            'high'   => __('Alta (Produção / Missão Crítica)', 'uptimemonitor')
        ];
        foreach ($criticality_levels as $key => $label) {
            $selected = ($this->fields['criticality'] ?? '') == $key ? 'selected' : '';
            echo "<option value='$key' $selected>$label</option>";
        }
        echo "</select>";
        echo "</td>";
        echo "<td>Intervalo (minutos):</td>";
        echo "<td>";
        echo Html::input("check_interval", ['type' => 'number', 'min' => 1, 'value' => $this->fields['check_interval'] ?? 5]);
        echo "</td>";
        echo "</tr>";

        // Integração ITIL (Ativos e Grupos)
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Vincular ao Ativo (Inventário):', 'uptimemonitor') . "</td>";
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

        echo "<td>" . __('Grupo Técnico Responsável:', 'uptimemonitor') . "</td>";
        echo "<td>";
        Group::dropdown([
            'name'      => 'groups_id_tech',
            'value'     => $this->fields['groups_id_tech'] ?? 0,
            'condition' => ['is_assign' => 1]
        ]);
        echo "</td>";
        echo "</tr>";
        
        echo "<tr>";
        echo "<th colspan='4'>" . __('NOC', 'uptimemonitor'). "</th>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Exibir no Monitor TV (NOC)', 'uptimemonitor') . "</td>";
        echo "<td>";
        Dropdown::showYesNo("is_noc", $this->fields['is_noc'] ?? 1); 
        echo "</td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Registrar Chamado Automático', 'uptimemonitor') . "</td>";
        echo "<td>";
        Dropdown::showYesNo("auto_create_ticket", $this->fields['auto_create_ticket'] ?? 0);
        echo "</td>";
        echo "<td>" . __('Categoria do Chamado:', 'uptimemonitor') . "</td>";
        echo "<td>";
        ITILCategory::dropdown([
            'name'  => 'itilcategories_id',
            'value' => $this->fields['itilcategories_id'] ?? 0,
        ]);
        echo "</td>";
        echo "</tr>";       

        echo "<tr>";
        echo "<th colspan='4'>" . __('Agendamento de Manutenção (Silenciar Alertas)', 'uptimemonitor'). "</th>";
        echo "</tr>";


        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Ativar Janela de Manutenção:', 'uptimemonitor') . "</td>";
        echo "<td>";
        Dropdown::showYesNo("is_maintenance", $this->fields["is_maintenance"] ?? 0);
        echo "</td>";

        echo "<td>" . __('Período da Manutenção:', 'uptimemonitor') . "</td>";
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
        // Assegurar entidade padrão (necessário no GLPI para validar o contexto)
        if (empty($input['entities_id']) && isset($_SESSION['glpiactive_entity'])) {
            $input['entities_id'] = $_SESSION['glpiactive_entity'];
        }

        // Garantir valor padrão para check_interval se vazio
        if (empty($input['check_interval']) || !is_numeric($input['check_interval'])) {
            $input['check_interval'] = 5; // valor padrão de 5 minutos
        }

        // Se a data de manutenção estiver vazia, removemos do INSERT para o MySQL usar o padrão (NULL)
        if (isset($input['maintenance_start']) && empty($input['maintenance_start'])) {
            unset($input['maintenance_start']);
        }
        if (isset($input['maintenance_end']) && empty($input['maintenance_end'])) {
            unset($input['maintenance_end']);
        }

        // Garantir valores booleanos inteiros para campos tinyint
        $input['is_active']      = !empty($input['is_active']) ? 1 : 0;
        $input['is_noc']         = !empty($input['is_noc']) ? 1 : 0;
        $input['is_maintenance'] = !empty($input['is_maintenance']) ? 1 : 0;

        // Garantir valores corretos para campos de relacionamento
        if (empty($input['itemtype']) || $input['itemtype'] === '0') {
            $input['itemtype'] = null;
        }
        if (empty($input['items_id']) || !is_numeric($input['items_id'])) {
            $input['items_id'] = 0;
        }
        if (empty($input['groups_id_tech']) || !is_numeric($input['groups_id_tech'])) {
            $input['groups_id_tech'] = 0;
        }

        $input['auto_create_ticket'] = !empty($input['auto_create_ticket']) ? 1 : 0;

        if (empty($input['itilcategories_id']) || !is_numeric($input['itilcategories_id'])) {
            $input['itilcategories_id'] = 0;
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
        return PluginUptimemonitorCron::cronCheckUptime($task);
    }

    /**
     * Lógica de teste por tipo
     */
    static function testTarget($type, $url) {
        return PluginUptimemonitorCron::testTarget($type, $url);
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
            'tickets' => __('Tickets Relacionados', 'uptimemonitor')
        ];
    } else {
        return [
            'stats' => __('Estatísticas', 'uptimemonitor')
        ];
    }
    return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($tabnum == 'stats') {
           self::showStats($item);
      } elseif ($tabnum == 'tickets') {
           self::showTickets($item);
      } else {
           self::showStats($item);
      }
      return true;

    }

    static function showStats($item) {
        global $DB;

        $start = (isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0);
        $limit = $_SESSION['glpilist_limit'] ?? 50; // Usa o limite padrão do usuário no GLPI

        // Conta o total absoluto de logs para este monitor (necessário para calcular as páginas)
        $total_number = countElementsInTable(
            'glpi_plugin_uptimemonitor_logs', 
            ['plugin_uptimemonitor_monitors_id' => $item->getID()]
        );

        // Busca os últimos 50 logs
        $iterator = $DB->request([
            'FROM'      => 'glpi_plugin_uptimemonitor_logs',
            'WHERE'     => ['plugin_uptimemonitor_monitors_id' => $item->getID()],
            'ORDER'     => 'date_creation DESC',
            'START'     => $start,
            'LIMIT'     => $limit
        ]);
                
        $logs_array = [];
        $labels     = [];
        $data       = [];
                
        foreach ($iterator as $log) {
            $logs_array[] = $log;
            
            // Prepara os dados do gráfico (Data/Hora e Tempo de Resposta)
            $labels[] = date("H:i", strtotime($log['date_creation']));
            $data[]   = (int)$log['response_time_ms'];
        }       

        echo "<h3>" . __('Histórico de Disponibilidade', 'uptimemonitor') . "</h3>";

        if (empty($logs_array)) {
            echo "<div class='center shadow' style='padding:20px; background-color: #fff; border-radius: 4px;'>";
            echo "<i class='fas fa-exclamation-triangle' style='font-size:30px; color:orange;'></i>";
            echo "<h4>Ainda não existem dados de histórico para este monitor.</h4>";
            echo "<p>Aguarde a execução da Ação Automática (Cron) ou force a execução.</p>";
            echo "</div>";
            return;
        }
                
        // Inverte para o gráfico ir da esquerda (antigo) para a direita (novo)
        $labels = array_reverse($labels);
        $data   = array_reverse($data);
        
        echo "<div class='center' style='width: 95%; height: 300px; margin: 10px auto; background: #fff; padding: 10px; border: 1px solid #ccc; border-radius: 4px;'>";
        echo "<canvas id='uptimeChart'></canvas>";
        echo "</div>";
        
        // Converte os arrays do PHP para JSON
        $json_labels = json_encode($labels);
        $json_data   = json_encode($data);

        // Injeta o script e faz o jQuery gerenciar o carregamento seguro da biblioteca externa
        echo "<script type='text/javascript'>
            $(function() {
                // Função principal que desenha o gráfico
                function initUptimeChart() {
                    var canvas = document.getElementById('uptimeChart');
                    if (!canvas) return; // Aborta se o elemento não existir
                    
                    var ctx = canvas.getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: $json_labels,
                            datasets: [{
                                label: 'Tempo de Resposta (ms)',
                                data: $json_data,
                                borderColor: '#2ecc71',
                                backgroundColor: 'rgba(46, 204, 113, 0.2)',
                                borderWidth: 2,
                                fill: true,
                                pointRadius: 4,
                                tension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { 
                                    beginAtZero: true, 
                                    title: { display: true, text: 'Milissegundos' } 
                                }
                            }
                        }
                    });
                }

                // Verifica se a biblioteca Chart já está carregada no GLPI
                if (typeof Chart === 'undefined') {
                    // Usa o jQuery para carregar a biblioteca de forma segura via AJAX
                    $.getScript('https://cdn.jsdelivr.net/npm/chart.js')
                        .done(function() {
                            // Sucesso! Desenha o gráfico
                            setTimeout(initUptimeChart, 200);
                        })
                        .fail(function(jqxhr, settings, exception) {
                            console.error('Uptime Monitor: Falha ao carregar o Chart.js', exception);
                        });
                } else {
                    // Se já estiver na memória, desenha direto
                    setTimeout(initUptimeChart, 200);
                }
            });
        </script>";
        
        echo "<div class='center' style='width: 95%; margin: 20px auto;'>";
        echo "<table class='tab_cadre_fixehov'>"; // Classe padrão do GLPI para tabelas
        echo "<tr class='tab_bg_2'>";
        echo "<th>" . __('Data / Hora', 'uptimemonitor') . "</th>";
        echo "<th>" . __('Status', 'uptimemonitor') . "</th>";
        echo "<th>" . __('Tempo de Resposta', 'uptimemonitor') . "</th>";
        echo "</tr>";

        // Faz o loop para imprimir as linhas da tabela
        foreach ($logs_array as $log) {
            echo "<tr class='tab_bg_1 center'>";
            
            // Coluna 1: Data formatada padrão GLPI
            echo "<td>" . Html::convDateTime($log['date_creation']) . "</td>";
            
            // Coluna 2: Status com cor
            if ($log['status'] === 'UP') {
                echo "<td><span style='color: #1e7e34; font-weight: bold;'><i class='fas fa-check-circle'></i> " . __('UP', 'uptimemonitor') . "</span></td>";
            } elseif ($log['status'] === 'MAINT') {
                echo "<td><span style='color: #f39c12; font-weight: bold;'><i class='fas fa-tools'></i> " . __('MANUTENÇÃO', 'uptimemonitor') . "</span></td>";
            } else {
                echo "<td><span style='color: #dc3545; font-weight: bold;'><i class='fas fa-times-circle'></i> " . __('DOWN', 'uptimemonitor') . "</span></td>";
            }
            
            // Coluna 3: Tempo de Resposta (Se for DOWN, exibe tracejado)
            if ($log['status'] === 'DOWN') {
                echo "<td>-</td>";
            } else {
                echo "<td>" . $log['response_time_ms'] . " ms</td>";
            }
            
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
        // Renderiza o controle de paginação (RODAPÉ)
        Html::printAjaxPager(__('Histórico de Disponibilidade', 'uptimemonitor'), $start, $total_number);
    }

    // Exibe os tickets relacionados (baseado no nome do monitor)
    static function showTickets($item) {
        
        global $DB; 
        
        $start = (isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0);
        $limit = $_SESSION['glpilist_limit'] ?? 50; // Usa o limite padrão do usuário no GLPI

        $total_number = countElementsInTable(
            'glpi_tickets', 
            ['name' => ['LIKE', '%' . $item->getName() . '%'], 'is_deleted' => 0]
        );

        // Busca os últimos 50 logs
        $iterator = $DB->request([
            'FROM'      => 'glpi_tickets',
            'WHERE'     =>  ['name' => ['LIKE', '%' . $item->getName() . '%'], 'is_deleted' => 0],
            'START'     => $start,
            'LIMIT'     => $limit,
            'ORDER'     => 'id DESC'
        ]);

        echo "<h3>" . __('Tickets Relacionados', 'uptimemonitor') . "</h3>";
        if (empty($iterator)) {
            echo "<div class='center shadow' style='padding:20px; background-color: #fff; border-radius: 4px;'>";
            echo "<i class='fas fa-exclamation-triangle' style='font-size:30px; color:orange;'></i>";
            echo "<h4>Ainda não existem tickets relacionados para este monitor.</h4>";
            echo "</div>";
            return;
        }
        
        $tickets_array = [];
        foreach ($iterator as $ticket) {
            $tickets_array[] = $ticket;
        }
        echo "<div class='center' style='width: 95%; margin: 20px auto;'>";
        echo "<table class='tab_cadre_fixehov'>"; // Classe padrão do GLPI para tabelas
        echo "<tr class='tab_bg_2'>";
        echo "<th>" . __('ID', 'uptimemonitor') . "</th>";
        echo "<th>" . __('Título', 'uptimemonitor') . "</th>";
        echo "<th>" . __('Status', 'uptimemonitor') . "</th>";
        echo "<th>" . __('Criado em', 'uptimemonitor') . "</th>";
        echo "<th>" . __('Fechado em', 'uptimemonitor') . "</th>";
        echo "</tr>";

        // Faz o loop para imprimir as linhas da tabela
        foreach ($tickets_array as $ticket) {         
            echo "<tr class='tab_bg_1'>";
            echo "<td><a href='" . Toolbox::getItemTypeFormURL('Ticket') . "?id=" . $ticket['id'] . "'>" . $ticket['id'] . "</a></td>";
            echo "<td>" . $ticket['name'] . "</td>";
            echo "<td>" . $ticket['status'] . "</td>"; // Adicionar nome do status
            echo "<td>" . Html::convDateTime($ticket['date_creation']) . "</td>";
            echo "<td>" . Html::convDateTime($ticket['solvedate']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
        // Renderiza o controle de paginação (RODAPÉ)
        Html::printAjaxPager(__('Tickets Relacionados', 'uptimemonitor'), $start, $total_number);        
    }

    static function getTargets() {
        return ['PluginUptimemonitorNotificationTargetMonitor'];
    }

    /**
     * Informações da Ação Automática para aparecer no painel do GLPI
     */
    static function cronInfo($name) {
        return PluginUptimemonitorCron::cronInfo($name);
    }

    /**
     * A função que realmente executa o teste e consolida Timer, Notificações e Logs
     */
    static function cronCheck($task) {
        return PluginUptimemonitorCron::cronCheck($task);
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
     * Define o conteúdo do menu no GLPI 10
     */
    static function getMenuContent() {
        $menu = [
            'title' => __("Uptime Monitor", 'uptimemonitor'),
            'page'  => "/plugins/uptimemonitor/front/monitor.php",
            'icon'  => 'fas fa-heartbeat',
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

    /**
     * Conteúdo do menu personalizado para as páginas do plugin (Adicionar, Dashboard, TV, NOC, Report)
     */
    static function getMenuContentPluginCustom() {
        echo "<div class='btn-group flex-wrap mb-3'>";
        echo "<span class='btn bg-blue-lt pe-none' aria-disabled='true'>Ações</span>";
        echo "  <a href='monitor.form.php' class='btn btn-outline-secondary'>";
        echo "      <i class='fas fa-plus fa-lg me-2'></i> " . __("Adicionar", "uptimemonitor");
        echo "  </a>";
        echo "  <a href='dashboard.php' class='btn btn-outline-secondary'>";
        echo "      <i class='fas fa-gauge fa-lg me-2'></i> " . __("Dashboard", "uptimemonitor");
        echo "  </a>";
        echo "  <a href='monitortv.php' class='btn btn-outline-secondary'>";
        echo "      <i class='fas fa-gauge fa-lg me-2'></i> " . __("TV", "uptimemonitor");
        echo "  </a>";
        echo "  <a href='monitor.noc.php' class='btn btn-outline-secondary'>";
        echo "      <i class='fas fa-gauge fa-lg me-2'></i> " . __("NOC", "uptimemonitor");
        echo "  </a>";
        echo "  <a href='report.php' class='btn btn-outline-secondary'>";
        echo "      <i class='fas fa-chart-line fa-lg me-2'></i> " . __("Report SLA", "uptimemonitor");
        echo "  </a>";
        // Verifica se o usuário tem direitos 
        if (Session::haveRight('uptimemonitor', READ)) {
            echo "  <a href='config.form.php' class='btn btn-outline-warning'>";
            echo "      <i class='fas fa-cogs fa-lg me-2'></i> " . __("Configurações", "uptimemonitor");
            echo "  </a>";
        }
        echo "  <a href='#' class='btn btn-outline-secondary' onclick='window.print();'>";
        echo "      <i class='fas fa-print fa-lg me-2'></i> " . __("Imprimir", "uptimemonitor");
        echo "  </a>";                    
        echo "</div>";
    }

}