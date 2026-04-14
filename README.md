# 📈 GLPI Uptime Monitor (inspirado em Uptime Kuma)

## 🚧 EM DESENVOLVIMENTO (Beta)

![GLPI Version](https://img.shields.io/badge/GLPI-10.0.0%2B-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-8892BF.svg)
![License](https://img.shields.io/badge/License-MIT-green.svg)
  
Última atualização: março 2026

Um plugin nativo para GLPI que transforma o seu sistema de ITSM num poderoso Centro de Operações de Rede (NOC). Inspirado no visual e na simplicidade do Uptime Kuma, este plugin monitoriza ativamente a disponibilidade de infraestrutura, integrando-se nativamente com o ciclo de vida de incidentes, entidades e perfis do GLPI.

OBS: Auxilio do Google Gemini e Copilot.

## � Instalação e Configuração

### Permissões Automáticas
- **Super-admin**: Acesso total concedido automaticamente na instalação
- **Outros perfis**: Configure via **Administração > Perfis > [Perfil] > Uptime Monitor**

### Ativação do Plugin
1. **Instalar:** Descompacte em `plugins/uptimemonitor/` e ative via **Configurar > Plugins**
2. **Ativar o Motor:** Vá a **Configurar > Ações Automáticas**, procure por `PluginUptimemonitorCron`. Altere a frequência (ex: 1 minuto), defina o estado para **Programado** e guarde.
3. **Acesso:** O super-admin tem acesso imediato. Para outros usuários, configure os perfis.

## �🚀 Principais Funcionalidades

* **Motor Multiprotocolo (Poller Nativo):** Testes automatizados executados de forma assíncrona via CronTask do GLPI.
  * `HTTP/HTTPS`: Validação de código 200 e medição de latência em ms via cURL.
  * `Ping (ICMP)`: Teste de conectividade nativo para ativos físicos.
  * `Porta TCP`: Verificação de sockets para serviços específicos (ex: Banco de Dados, RDP, SSH).
* **Dashboard NOC em Tempo Real:** Painel visual responsivo com atualização automática (AJAX) e barra de status estilo *Heartbeat* (blocos verdes e vermelhos) projetado para ecrãs de monitorização.
* **Automação ITIL Completa:**
  * **Abertura e Resolução Inteligentes:** Abre tickets na queda de um serviço (evitando duplicidades) e resolve-os automaticamente com uma `ITILSolution` quando o serviço é restabelecido.
  * **Vínculo Dinâmico de Ativos:** O incidente é automaticamente associado ao Servidor, Switch ou Software correspondente no inventário (`Item_Ticket`).
  * **Roteamento Inteligente (Escalonamento):** Atribui automaticamente o chamado ao Grupo Técnico responsável pela aplicação ou infraestrutura afetada (`Group_Ticket`).
* **Governança e Segurança Corporativa:**
  * **Suporte Multi-Entidade:** Isolamento total de dados. Técnicos de uma entidade (ou empresa cliente) não veem os monitores nem os painéis de outras entidades.
  * **Controlo de Acesso por Perfil:** A configuração (criação/edição de monitores) é restrita a perfis com permissão global de configuração (Super-Admins). O acesso de leitura ao Dashboard é libertado para perfis operacionais (Técnicos).
* **Janelas de Manutenção (Scheduled Downtime):** Agendamento de paragens programadas, silenciando alertas e suprimindo a abertura de tickets durante atualizações.
* **Alertas Externos:** Estrutura nativa pronta para acionar gatilhos de notificação (como envio de mensagens via WhatsApp) nas mudanças de estado (UP/DOWN).
* **Self-Healing:** Rotina de limpeza (Purge) integrada que elimina logs de latência com mais de 7 dias, otimizando o tamanho da base de dados.

## 📋 Pré-requisitos

* GLPI 10.0.0 ou superior.
* Extensão PHP `curl` ativada.
* Servidor Host Linux (para a execução segura do comando de `ping`).
* Ação Automática (Cron) do GLPI configurada em modo CLI.

## 🔧 Instalação

1. Clone ou descarregue este repositório.
2. Certifique-se de que a pasta se chama estritamente `uptimemonitor`.
3. Mova a pasta para o diretório de plugins do seu servidor GLPI: `/var/www/html/glpi/plugins/uptimemonitor/`.
4. Aceda ao GLPI com um perfil de **Super-Admin**.
5. Navegue até **Configurar > Plugins**.
6. Encontre o "Uptime Monitor" na lista e clique em **Instalar**.
7. Clique em **Ativar**.

## ⚙️ Configuração Básica

1. **Configurar Monitores:** Como Super-Admin, vá a **Plugins > Uptime Monitor** e adicione os URLs, IPs ou Portas. Defina os grupos técnicos responsáveis, as entidades e os vínculos de inventário.
2. **Ativar o Motor:** Vá a **Configurar > Ações Automáticas**, procure por `PluginUptimemonitorCron`. Altere a frequência (ex: 1 minuto), defina o estado para **Programado** e guarde.
3. **Monitorizar:** Aceda ao Dashboard a partir do menu ou coloque o link nas TVs da sua operação de TI.

## 🏗️ Estrutura do Projeto

* `setup.php` / `hook.php`: Registo do plugin, verificação de requisitos (cURL, versão) e migrações de base de dados (Tabelas, Entidades, Manutenção).
* `inc/monitor.class.php`: Controlador da interface gráfica e formulários nativos GLPI (com suporte a Dropdowns dinâmicos).
* `inc/poller.class.php`: O "Cérebro". Executa o loop de testes de conectividade, calcula latências, gere as janelas de manutenção e o ciclo de vida ITIL dos Tickets.
* `front/dashboard.php`: Painel visual isolado por entidade.
* `front/monitor.noc.php`: Painel de visualização na TV. Sugestão para uso na equipe NOC.
* `front/monitor.form.php`: Roteador protegido de validação de formulários.

## 🧩 Implementação técnica detalhada

- `setup.php`: define versões, requisitos (GLPI >= 10.0.0, cURL) e registra os hooks do plugin (menus, direitos, notificações e cron).
- `inc/poller.class.php` e `inc/monitor.class.php`: núcleo da execução assíncrona via CronTask.
- Verificações de tipo suportadas: `http`, `https`, `ping` (exec ping shell), `port` (fsockopen).
- Estados de monitor: `UP`, `DOWN`, `MAINT` (manutenção) e `PENDING` no form.
- Criticidade controla frequência do poller:
  - `test`: 30s
  - `high`: 60s
  - `medium`: 300s
  - `low`: 900s
- Logs em `glpi_plugin_uptimemonitor_logs` com `response_time_ms` e detecção de manutenção.
- Gestão de tickets:
  - `handleServiceDown`: cria ticket de incidente e vincula a Item_Ticket / Group_Ticket, evita duplicados.
  - `handleServiceUp`: resolve ticket com ITILSolution e limpa `current_tickets_id`.
- Manutenção: `is_maintenance` + janela `maintenance_start` / `maintenance_end` suprimem alertas e gravam logs `MAINT`.
- Disponibiliza aba “Estatísticas” com gráfico Chart.js carregado via CDN.
- Função de notificação Telegram embutida, pode ser customizada em produção.

## 🛣️ Roadmap

- [x] Poller básico HTTP/HTTPS, Ping, TCP
- [x] Dashboard e relatórios por entidade
- [x] Abertura/fechamento de tickets ITIL com regras de deduplicação
- [x] Janelas de manutenção (Scheduled Downtime)
- [ ] Integração com notificações externas (WhatsApp, Telegram, Slack)
- [ ] Relatórios históricos de SLA e métricas de disponibilidade
- [ ] Redundância de múltiplos data centers
- [ ] Modo de failover e alertas desenhados por perfil

## 🤝 Como contribuir

1. Fork do repositório e crie um branch para a feature/bugfix (`git checkout -b feature/nome-da-funcao`).
2. Execute testes locais e verifique módulos do GLPI.
3. Abra um pull request com descrição clara de reprodução e validação.
4. Inclua documentação atualizada em `README.md` ou em `docs/` quando necessário.
5. Para casos de segurança ou bugs críticos, abra issue com nível de urgência e logs de erro.

---

## 📌 Notas de publicação

- Release 0.1.0: versão inicial com poller funcional e painel (março 2026).
- Próximas versões: melhorias na confiabilidade do ciclo ITIL, suporte ao modo TV+AC e integrações de notificação.
