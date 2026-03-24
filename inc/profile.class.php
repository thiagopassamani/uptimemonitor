<?php
class PluginUptimemonitorProfile extends CommonDBTM {

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'Profile') {
         return 'Uptime Monitor';
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI;

      $profile_id = $item->getField('id');
      $profRight  = new ProfileRight();
      
      $profRight->getFromDBByCrit(['profiles_id' => $profile_id, 'name' => 'uptimemonitor']);
      $rights = $profRight->isNewItem() ? 0 : $profRight->fields['rights'];

      echo "<form action='" . $CFG_GLPI["root_doc"] . "/plugins/uptimemonitor/front/profile.form.php' method='post'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_1'><th colspan='2'>Permissões</th></tr>";
      
      echo "<tr class='tab_bg_2'><td>Ler</td><td>";
      Html::showCheckbox(['name' => 'read', 'checked' => ($rights & READ)]);
      echo "</td></tr>";
      
      echo "<tr class='tab_bg_2'><td>Escrever</td><td>";
      Html::showCheckbox(['name' => 'write', 'checked' => ($rights & (CREATE | UPDATE | PURGE))]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td colspan='2' class='center'>";
      echo "<input type='hidden' name='profiles_id' value='$profile_id'>";
      echo "<input type='submit' name='update' class='btn btn-primary' value='Salvar'>";
      echo "</td></tr></table>";
      Html::closeForm();
      return true;
   }
}