# 📈 GLPI Uptime Monitor (Kuma-Style)

![GLPI Version](https://img.shields.io/badge/GLPI-10.0.0%2B-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-8892BF.svg)
![License](https://img.shields.io/badge/License-MIT-green.svg)

Um plugin nativo para GLPI que transforma o seu sistema de ITSM num poderoso Centro de Operações de Rede (NOC). Inspirado no visual e na simplicidade do Uptime Kuma, este plugin monitoriza ativamente a disponibilidade de infraestrutura, integrando-se nativamente com o ciclo de vida de incidentes, entidades e perfis do GLPI.

## 🚀 Principais Funcionalidades

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
2. **Ativar o Motor:** Vá a **Configurar > Ações Automáticas**, procure por `PluginUptimemonitorPoller`. Altere a frequência (ex: 1 minuto), defina o estado para **Programado** e guarde.
3. **Monitorizar:** Aceda ao Dashboard a partir do menu ou coloque o link nas TVs da sua operação de TI.

## 🏗️ Estrutura do Projeto

* `setup.php` / `hook.php`: Registo do plugin, verificação de requisitos (cURL, versão) e migrações de base de dados (Tabelas, Entidades, Manutenção).
* `inc/monitor.class.php`: Controlador da interface gráfica e formulários nativos GLPI (com suporte a Dropdowns dinâmicos).
* `inc/poller.class.php`: O "Cérebro". Executa o loop de testes de conectividade, calcula latências, gere as janelas de manutenção e o ciclo de vida ITIL dos Tickets.
* `front/dashboard.php`: Painel visual isolado por entidade.
* `front/monitor.form.php`: Roteador protegido de validação de formulários.