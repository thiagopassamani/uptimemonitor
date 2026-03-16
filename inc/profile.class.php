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

        // ID do perfil que estamos a editar
        $profile_id = $item->getField('id');
        
        // Busca os direitos atuais no GLPI 10
        $profRight = new ProfileRight();
        $profRight->getFromDBByCrit([
            'profiles_id' => $profile_id,
            'name'        => 'plugin_uptimemonitor'
        ]);
        
        $right = $profRight->isNewItem() ? 0 : $profRight->fields['rights'];
        
        // Formulário
        echo "<form action='" . $CFG_GLPI["root_doc"] . "/plugins/uptimemonitor/front/profile.form.php' method='post'>";
        echo "<div class='center'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'><th colspan='2'>". __('Permissões do Uptime Monitor', 'uptimemonitor') . "</th></tr>";
        
        echo "<tr class='tab_bg_2'>";
        echo "<td width='50%'>". __('Ler (Visualizar dashboard e monitores)', 'uptimemonitor') . "</td>";
        echo "<td>";
        Html::showCheckbox(['name' => 'read', 'checked' => ($right & READ)]);
        echo "</td></tr>";
        
        echo "<tr class='tab_bg_2'>";
        echo "<td>". __('Escrever (Criar, editar ou apagar monitores)', 'uptimemonitor') . "</td>";
        echo "<td>";
        Html::showCheckbox(['name' => 'write', 'checked' => ($right & UPDATE)]);
        echo "</td></tr>";

        // Botão de Guardar e campos ocultos de segurança em HTML puro
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' class='center'>";
        echo "<input type='hidden' name='profiles_id' value='$profile_id'>";
        
        $token = Session::getNewCSRFToken();
        echo "<input type='hidden' name='_glpi_csrf_token' value='$token'>";
        
        echo "<input type='submit' name='update' class='submit' value='" . __("Save") . "'>";
        echo "</td></tr>";
        
        echo "</table>";
        echo "</div>";
        Html::closeForm();
        
        return true;
    }
}