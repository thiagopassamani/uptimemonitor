<?php
class PluginUptimemonitorProfile extends CommonDBTM {

    // Define o nome da aba no menu lateral de Perfis
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item->getType() == 'Profile') {
            return 'Uptime Monitor';
        }
        return '';
    }

    // Renderiza o formulário
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        global $CFG_GLPI;

        // ID do perfil que estamos a editar (ex: ID 4 para Super-Admin)
        $profile_id = $item->getField('id');
        
        // Vai buscar os direitos atuais deste perfil para o plugin na base de dados
        $right = ProfileRight::getProfileRight($profile_id, 'plugin_uptimemonitor');
        
        // Inicia o formulário a apontar para o ficheiro que vai processar os dados
        echo "<form action='" . $CFG_GLPI["root_doc"] . "/plugins/uptimemonitor/front/profile.form.php' method='post'>";
        echo "<div class='center'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'><th colspan='2'>Permissões do Uptime Monitor</th></tr>";
        
        // Permissão de Leitura (READ = 1 no GLPI)
        echo "<tr class='tab_bg_2'>";
        echo "<td width='50%'>Ler (Visualizar dashboard e monitores)</td>";
        echo "<td>";
        Html::showCheckbox(['name' => 'read', 'checked' => ($right & READ)]);
        echo "</td></tr>";
        
        // Permissão de Escrita (UPDATE = 2 no GLPI)
        echo "<tr class='tab_bg_2'>";
        echo "<td>Escrever (Criar, editar ou apagar monitores)</td>";
        echo "<td>";
        Html::showCheckbox(['name' => 'write', 'checked' => ($right & UPDATE)]);
        echo "</td></tr>";

        // Botão de Guardar e campos ocultos de segurança
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' class='center'>";
        echo "<input type='hidden' name='profiles_id' value='$profile_id'>";
        Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<input type='submit' name='update' class='submit' value='" . __("Save") . "'>";
        echo "</td></tr>";
        
        echo "</table>";
        echo "</div>";
        Html::closeForm();
        
        return true;
    }
}