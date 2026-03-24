<?php
include ("../../../inc/includes.php");

if (isset($_POST["update"])) {
   $profile_id = $_POST['profiles_id'];
   $rights = 0;

   // Se o checkbox 'read' foi marcado, adiciona permissão de leitura
   if (isset($_POST['read'])) {
      $rights |= READ;
   }

   // Se o checkbox 'write' foi marcado, adiciona o pacote de escrita
   if (isset($_POST['write'])) {
      $rights |= (CREATE | UPDATE | PURGE);
   }

   $profRight = new ProfileRight();
   
   if (!$profRight->getFromDBByCrit(['profiles_id' => $profile_id, 'name' => 'uptimemonitor'])) {
      $profRight->add([
         'profiles_id' => $profile_id, 
         'name'        => 'uptimemonitor', 
         'rights'      => $rights
      ]);
   } else {
      $profRight->update([
         'id'     => $profRight->fields['id'], 
         'rights' => $rights
      ]);
   }

   Html::back();
}