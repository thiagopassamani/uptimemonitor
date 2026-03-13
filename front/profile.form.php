<?php
// Carrega o núcleo do GLPI
include ("../../../inc/includes.php");

// Verifica se o utilizador atual tem permissão para editar perfis
Session::checkRight("profile", UPDATE);

// Verifica se o formulário foi submetido
if (isset($_POST["update"])) {
    // Valida o token de segurança (CSRF)
    Session::checkCSRF($_POST);

    $profile_id = $_POST['profiles_id'];
    $rights = 0; // Começa com 0 (Sem acesso)
    
    // Soma os valores das permissões escolhidas
    if (isset($_POST['read'])) {
        $rights += READ;   // READ equivale a 1
    }
    if (isset($_POST['write'])) {
        $rights += UPDATE; // UPDATE equivale a 2
    }

    // Guarda as permissões nativamente na tabela glpi_profilerights
    ProfileRight::updateProfileRight($profile_id, 'plugin_uptimemonitor', $rights);
    
    // Redireciona de volta para a página do perfil com uma mensagem de sucesso
    Html::back();
}