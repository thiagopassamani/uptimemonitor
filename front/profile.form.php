<?php
// Carrega o núcleo do GLPI (ajuste o caminho se a sua estrutura de pastas for diferente)
include("../../../inc/includes.php");

// 1. Verificação de Segurança de Sessão
// Garante que quem está acessando este arquivo tem permissão nativa para atualizar perfis no GLPI
Session::checkRight("profile", UPDATE);

Session::checkCSRF($_POST);

if (isset($_POST["update"])) {
    // Resgata o ID do perfil que foi enviado pelo formulário (evita o aviso de undefined variable)
    $profile_id = $_POST["profiles_id"] ?? 0;

    if ($profile_id > 0) {
        // Calcula o valor numérico dos direitos
        $rights = 0;
        if (isset($_POST['read'])) {
            $rights += READ;   // READ = 1
        }
        if (isset($_POST['write'])) {
            $rights += UPDATE; // UPDATE = 2
        }

        // 3. Atualiza os direitos no banco de dados usando o padrão GLPI 10
        $profRight = new ProfileRight();
        
        // Tenta buscar se já existe um registro de direito desse plugin para este perfil
        if ($profRight->getFromDBByCrit(['profiles_id' => $profile_id, 'name' => 'plugin_uptimemonitor'])) {
            // Se já existe, apenas atualiza o valor
            $profRight->update([
                'id'     => $profRight->fields['id'],
                'rights' => $rights
            ]);
        } else {
            // Se não existe, cria um novo registro
            $profRight->add([
                'profiles_id' => $profile_id,
                'name'        => 'plugin_uptimemonitor',
                'rights'      => $rights
            ]);
        }
    }

    // Redireciona o usuário de volta para a tela de onde ele veio (a aba do perfil)
    Html::back();
}