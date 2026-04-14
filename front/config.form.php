<?php
/**
 * Uptime Monitor Plugin for GLPI
 * Author: Thiago Passamani
 * @class PluginUptimemonitorConfigForm
 * @description Formulário de Configurações do Plugin Uptime Monitor
 */

include("../../../inc/includes.php");

// Verifica se o usuário está logado
Session::checkLoginUser();

// Verifica se o usuário tem direitos de config ou uptimemonitor com permissão READ
if (!Session::haveRight('uptimemonitor', READ)) {
    Html::displayRightError();
    exit;
}

// Garante que a variável sempre esteja definida
if (!isset($monitor_is_readonly)) {
    $monitor_is_readonly = false;
}

// Carrega todas as configurações
try {
    $all_configs = PluginUptimemonitorConfig::getAllConfigs();
    error_log('DEBUG: Loaded ' . count($all_configs) . ' config items');
} catch (Exception $e) {
    error_log('DEBUG: Error loading configs: ' . $e->getMessage());
    $all_configs = [];
}
// Processa POST
if (isset($_POST["update"])) {
    // NOVA VERIFICAÇÃO DE SEGURANÇA:
    if (!Session::haveRight('uptimemonitor', UPDATE)) {
        Session::addMessageAfterRedirect(__('Você não tem permissão para alterar as configurações.', 'uptimemonitor'), true, SESSION_MSG_ERROR);
        Html::redirect($_SERVER['REQUEST_URI']);
    }

    try {
        // Valida token CSRF
        //Session::checkCSRF($_POST);

        error_log('DEBUG: Starting config save for user ' . $_SESSION['glpiID']);
        //error_log('DEBUG: POST data: ' . print_r($_POST, true));
        $debug_post = $_POST;
        $debug_post['telegram_api_key'] = str_repeat('*', 8);
        $debug_post['slack_webhook_url'] = str_repeat('*', 8);
        error_log('DEBUG: POST data: ' . print_r($debug_post, true));

        // Telegram
        $result1 = PluginUptimemonitorConfig::setConfigValue('telegram_enabled', isset($_POST['telegram_enabled']) ? 1 : 0, 'boolean');
        $result2 = PluginUptimemonitorConfig::setConfigValue('telegram_api_key', $_POST['telegram_api_key'] ?? '', 'password');
        $result3 = PluginUptimemonitorConfig::setConfigValue('telegram_chat_id', $_POST['telegram_chat_id'] ?? '', 'string');

        // Slack
        $result4 = PluginUptimemonitorConfig::setConfigValue('slack_enabled', isset($_POST['slack_enabled']) ? 1 : 0, 'boolean');
        $result5 = PluginUptimemonitorConfig::setConfigValue('slack_webhook_url', $_POST['slack_webhook_url'] ?? '', 'password');

        // Notificações
        $result6 = PluginUptimemonitorConfig::setConfigValue('email_notifications', isset($_POST['email_notifications']) ? 1 : 0, 'boolean');
        $result7 = PluginUptimemonitorConfig::setConfigValue('notification_on_down', isset($_POST['notification_on_down']) ? 1 : 0, 'boolean');
        $result8 = PluginUptimemonitorConfig::setConfigValue('notification_on_up', isset($_POST['notification_on_up']) ? 1 : 0, 'boolean');

        // Tickets
        $result9 = PluginUptimemonitorConfig::setConfigValue('create_ticket_on_down', isset($_POST['create_ticket_on_down']) ? 1 : 0, 'boolean');
        //$result10 = PluginUptimemonitorConfig::setConfigValue('ticket_category', $_POST['ticket_category'] ?? '', 'string');
        $result10 = PluginUptimemonitorConfig::setConfigValue('ticket_category', intval($_POST['ticket_category'] ?? 0), 'integer');
        // SLA
        $result11 = PluginUptimemonitorConfig::setConfigValue('sla_response_time', intval($_POST['sla_response_time'] ?? 30), 'integer');

        // Carrega configs novamente para exibição
        $all_configs = PluginUptimemonitorConfig::getAllConfigs();
        
        error_log('DEBUG: Config save completed - Results: ' . implode(',', [$result1, $result2, $result3, $result4, $result5, $result6, $result7, $result8, $result9, $result10, $result11]));
        Session::addMessageAfterRedirect(__('Configurações salvas com sucesso!', 'uptimemonitor'), true, INFO);
        Html::redirect($_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        error_log('DEBUG: Error saving config: ' . $e->getMessage());
        error_log('DEBUG: Exception trace: ' . $e->getTraceAsString());
        Session::addMessageAfterRedirect(__('Erro ao salvar configurações: ' . $e->getMessage(), 'uptimemonitor'), true, ERROR);
        // Repita o carregamento em caso de erro
        $all_configs = PluginUptimemonitorConfig::getAllConfigs();
    }
}

// Processa testes de conexão
if (isset($_POST["test_telegram"])) {
    
    $result = PluginUptimemonitorConfig::testTelegramConnection(
        $_POST['telegram_api_key'] ?? '',
        $_POST['telegram_chat_id'] ?? ''
    );
    
    $message_type = $result['success'] ? INFO : WARNING;
    Session::addMessageAfterRedirect($result['message'], true, $message_type);
    Html::redirect($_SERVER['REQUEST_URI']);
}

if (isset($_POST["test_slack"])) {
    // Valida token CSRF
    Session::checkCSRF($_POST);
    
    $result = PluginUptimemonitorConfig::testSlackConnection(
        $_POST['slack_webhook_url'] ?? ''
    );
    
    $message_type = $result['success'] ? INFO : WARNING;
    Session::addMessageAfterRedirect($result['message'], true, $message_type);
    Html::redirect($_SERVER['REQUEST_URI']);
}

// Headers GLPI
Html::header(
    __('Configurações - Uptime Monitor', 'uptimemonitor'), 
    $_SERVER['PHP_SELF'], 
    "plugins", 
    "PluginUptimemonitorMonitor"
);

?>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-xxl-8 col-lg-12">
            <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>" class="card">
                <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
                
                <!-- Telegram Configuration -->
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="fas fa-paper-plane"></i>
                        <?php echo __('Configuração do Telegram', 'uptimemonitor'); ?>
                    </h4>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="telegram_enabled" 
                               name="telegram_enabled" value="1"
                               <?php echo (($all_configs['telegram_enabled'] ?? 0) == 1 ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="telegram_enabled">
                            <?php echo __('Ativar notificações por Telegram', 'uptimemonitor'); ?>
                        </label>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="telegram_api_key" class="form-label">
                                <?php echo __('API Key do Bot Telegram', 'uptimemonitor'); ?>
                            </label>
                            <input type="password" class="form-control" id="telegram_api_key" 
                                   name="telegram_api_key" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
                                   value="<?php echo htmlspecialchars($all_configs['telegram_api_key'] ?? ''); ?>">
                            <small class="form-text text-muted">
                                <?php echo __('Obtenha em: https://t.me/BotFather', 'uptimemonitor'); ?>
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label for="telegram_chat_id" class="form-label">
                                <?php echo __('Chat ID', 'uptimemonitor'); ?>
                            </label>
                            <input type="text" class="form-control" id="telegram_chat_id" 
                                   name="telegram_chat_id" placeholder="-1001234567890"
                                   value="<?php echo htmlspecialchars($all_configs['telegram_chat_id'] ?? ''); ?>">
                            <small class="form-text text-muted">
                                <?php echo __('ID do chat/grupo ou forward de @userinfobot', 'uptimemonitor'); ?>
                            </small>
                        </div>
                    </div>

                    <button type="submit" name="test_telegram" class="btn btn-info btn-sm mb-3">
                        <i class="fas fa-plug"></i>
                        <?php echo __('Testar Conexão Telegram', 'uptimemonitor'); ?>
                    </button>
                </div>

                <hr>

                <!-- Slack Configuration -->
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="fab fa-slack"></i>
                        <?php echo __('Configuração do Slack', 'uptimemonitor'); ?>
                    </h4>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="slack_enabled" 
                               name="slack_enabled" value="1"
                               <?php echo (($all_configs['slack_enabled'] ?? 0) == 1 ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="slack_enabled">
                            <?php echo __('Ativar notificações por Slack', 'uptimemonitor'); ?>
                        </label>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="slack_webhook_url" class="form-label">
                                <?php echo __('Webhook URL do Slack', 'uptimemonitor'); ?>
                            </label>
                            <input type="password" class="form-control" id="slack_webhook_url" 
                                   name="slack_webhook_url" placeholder="https://hooks.slack.com/services/..."
                                   value="<?php echo htmlspecialchars($all_configs['slack_webhook_url'] ?? ''); ?>">
                            <small class="form-text text-muted">
                                <?php echo __('Obtenha em: https://api.slack.com/apps (Incoming Webhooks)', 'uptimemonitor'); ?>
                            </small>
                        </div>
                    </div>

                    <button type="submit" name="test_slack" class="btn btn-info btn-sm mb-3">
                        <i class="fas fa-plug"></i>
                        <?php echo __('Testar Conexão Slack', 'uptimemonitor'); ?>
                    </button>
                </div>

                <hr>

                <!-- Notification Configuration -->
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="fas fa-bell"></i>
                        <?php echo __('Configurações de Notificação', 'uptimemonitor'); ?>
                    </h4>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="email_notifications" 
                               name="email_notifications" value="1"
                               <?php echo (($all_configs['email_notifications'] ?? 1) == 1 ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="email_notifications">
                            <?php echo __('Ativar notificações por Email', 'uptimemonitor'); ?>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notification_on_down" 
                               name="notification_on_down" value="1"
                               <?php echo (($all_configs['notification_on_down'] ?? 1) == 1 ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="notification_on_down">
                            <?php echo __('Notificar quando serviço fica DOWN', 'uptimemonitor'); ?>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notification_on_up" 
                               name="notification_on_up" value="1"
                               <?php echo (($all_configs['notification_on_up'] ?? 1) == 1 ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="notification_on_up">
                            <?php echo __('Notificar quando serviço volta ao ar', 'uptimemonitor'); ?>
                        </label>
                    </div>
                </div>

                <hr>

                <!-- Ticket Configuration -->
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        <i class="fas fa-ticket-alt"></i>
                        <?php echo __('Configurações de Tickets', 'uptimemonitor'); ?>
                    </h4>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="create_ticket_on_down" 
                               name="create_ticket_on_down" value="1"
                               <?php echo (($all_configs['create_ticket_on_down'] ?? 0) == 1 ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="create_ticket_on_down">
                            <?php echo __('Criar ticket automaticamente quando serviço fica DOWN', 'uptimemonitor'); ?>
                        </label>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ticket_category" class="form-label">
                                <?php echo __('Categoria do Ticket', 'uptimemonitor'); ?>
                            </label>
                            <?php
                            // O GLPI gera o select nativo automaticamente com esta função
                            ITILCategory::dropdown([
                                'name'                => 'ticket_category',
                                'value'               => htmlspecialchars($all_configs['ticket_category'] ?? 0),
                                'display_emptychoice' => true,
                                'width'               => '100%'
                            ]);
                            ?>
                        </div>                        
                        <div class="col-md-6">
                            <label for="sla_response_time" class="form-label">
                                <?php echo __('Tempo de Resposta SLA (minutos)', 'uptimemonitor'); ?>
                            </label>
                            <input type="number" class="form-control" id="sla_response_time" 
                                   name="sla_response_time" min="1" value="<?php echo $all_configs['sla_response_time'] ?? 30; ?>">
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Submit Buttons -->
                <div class="card-body">
                    <button type="submit" name="update" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <?php echo __('Salvar Configurações', 'uptimemonitor'); ?>
                    </button>
                    <a href="config.form.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <?php echo __('Cancelar', 'uptimemonitor'); ?>
                    </a>
                </div>

            </form>
        </div>

        <!-- Info Panel -->
        <div class="col-xxl-4 col-lg-12">
            <div class="card bg-light-blue">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle"></i>
                        <?php echo __('Ajuda', 'uptimemonitor'); ?>
                    </h5>
                    
                    <hr>
                    
                    <h6><?php echo __('Telegram', 'uptimemonitor'); ?></h6>
                    <p>
                        <?php echo __('Para configurar o Telegram:', 'uptimemonitor'); ?> <br>
                        1. <?php echo __('Crie um bot com @BotFather no Telegram', 'uptimemonitor'); ?> <br>
                        2. <?php echo __('Copie a API Key fornecida', 'uptimemonitor'); ?> <br>
                        3. <?php echo __('Inicie o bot enviando /start', 'uptimemonitor'); ?> <br>
                        4. <?php echo __('Envie a mensagem de um bot para descobrir seu Chat ID', 'uptimemonitor'); ?>
                    </p>

                    <hr>

                    <h6><?php echo __('Slack', 'uptimemonitor'); ?></h6>
                    <p>
                        <?php echo __('Para configurar o Slack:', 'uptimemonitor'); ?> <br>
                        1. <?php echo __('Acesse https://api.slack.com/apps', 'uptimemonitor'); ?> <br>
                        2. <?php echo __('Crie uma nova app', 'uptimemonitor'); ?> <br>
                        3. <?php echo __('Ative "Incoming Webhooks"', 'uptimemonitor'); ?> <br>
                        4. <?php echo __('Crie um webhook para seu canal', 'uptimemonitor'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
Html::footer();