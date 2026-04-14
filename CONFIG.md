# Configurações - Uptime Monitor

## Resumo

O sistema de configurações do Uptime Monitor permite gerenciar:

- **Telegram**: Notificações via bot Telegram
- **Slack**: Notificações via webhook Slack
- **Email**: Notificações por email do GLPI
- **Tickets**: Criação automática de tickets quando serviço fica DOWN
- **SLA**: Tempo de resposta para SLA

---

## Arquivos Criados

### 1. **inc/config.class.php**
Classe que gerencia todas as configurações do plugin.

**Métodos principais:**
- `getConfigValue($name, $default)` - Obter valor de configuração
- `setConfigValue($name, $value, $type)` - Salvar/atualizar configuração
- `getAllConfigs()` - Obter todas as configurações
- `testTelegramConnection($api_key, $chat_id)` - Testar conexão Telegram
- `testSlackConnection($webhook_url)` - Testar conexão Slack
- `sendTelegramNotification($message)` - Enviar mensagem via Telegram
- `sendSlackNotification($message, $color)` - Enviar mensagem via Slack

### 2. **front/config.form.php**
Interface web para editar configurações.

**Funcionalidades:**
- Formulário com abas para cada serviço
- Botões de teste de conexão
- Validação de permissões (apenas admin)
- Mensagens de sucesso/erro após salvar

### 3. **hook.php (atualizado)**
Criação automática da tabela `glpi_plugin_uptimemonitor_configs` com configurações padrão.

### 4. **setup.php (atualizado)**
Registro da classe `PluginUptimemonitorConfig` no GLPI.

### 5. **inc/monitor.class.php (atualizado)**
Adicionado botão "Configurações" ao menu do plugin.

---

## Tabela de Banco de Dados

```sql
CREATE TABLE `glpi_plugin_uptimemonitor_configs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `entities_id` int(11) unsigned NOT NULL DEFAULT '0',
    `name` varchar(255) NOT NULL UNIQUE,
    `value` longtext,
    `type` varchar(50) DEFAULT 'string',
    `date_mod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Configurações Armazenadas

| Nome | Tipo | Padrão | Descrição |
|------|------|--------|-----------|
| `telegram_enabled` | boolean | 0 | Ativar notificações Telegram |
| `telegram_api_key` | password | '' | API Key do bot Telegram |
| `telegram_chat_id` | string | '' | Chat ID para notificações |
| `slack_enabled` | boolean | 0 | Ativar notificações Slack |
| `slack_webhook_url` | password | '' | URL do webhook Slack |
| `email_notifications` | boolean | 1 | Ativar notificações por email |
| `notification_on_down` | boolean | 1 | Notificar quando DOWN |
| `notification_on_up` | boolean | 1 | Notificar quando UP |
| `create_ticket_on_down` | boolean | 0 | Criar ticket automaticamente |
| `ticket_category` | string | '' | Categoria do ticket |
| `sla_response_time` | integer | 30 | Tempo SLA em minutos |

---

## Como Usar

### Para Acessar as Configurações:
1. Acesse: `http://localhost/glpi/plugins/uptimemonitor/front/config.form.php`
2. Ou clique no botão "Configurações" no menu do plugin

### Telegram:
1. Crie um bot com **@BotFather** no Telegram
2. Copie a **API Key** fornecida
3. Inicie o bot enviando `/start`
4. Use **@userinfobot** para descobrir seu **Chat ID**
5. Cole a API Key e Chat ID no formulário
6. Clique em "Testar Conexão Telegram"

### Slack:
1. Acesse **https://api.slack.com/apps**
2. Crie uma nova app
3. Ative **Incoming Webhooks**
4. Crie um webhook para seu canal
5. Cole a URL no formulário
6. Clique em "Testar Conexão Slack"

---

## Como Usar em Código

### Obter uma Configuração:
```php
$value = PluginUptimemonitorConfig::getConfigValue('telegram_api_key');
```

### Salvar uma Configuração:
```php
PluginUptimemonitorConfig::setConfigValue('telegram_enabled', 1, 'boolean');
```

### Enviar Notificação via Telegram:
```php
$message = "<b>Alerta!</b>\n";
$message .= "Google DNS (8.8.8.8) está DOWN\n";
$message .= "Status: <i>teste</i>";

PluginUptimemonitorConfig::sendTelegramNotification($message);
```

### Enviar Notificação via Slack:
```php
$message = "Serviço DOWN: Google DNS (8.8.8.8)";
PluginUptimemonitorConfig::sendSlackNotification($message, 'danger');
```

---

## Segurança

- Senhas são armazenadas com tipo `password` (em produção, usar criptografia)
- Apenas usuários com permissão `UPDATE` em config podem editar
- Testes de conexão são isolados e não afetam o resto do sistema
- URLs de webhooks usam `file_get_contents` com timeout de 10 segundos

---

## Próximas Melhorias

- [ ] Criptografar senhas/tokens no banco de dados
- [ ] Suporte a Discord
- [ ] Suporte a Microsoft Teams
- [ ] Backup/restauração de configurações
- [ ] Histórico de alterações de configuração
- [ ] Testes de notificação com logs detalhados

---

Versão: 1.0.0  
Última atualização: 2026-03-26
