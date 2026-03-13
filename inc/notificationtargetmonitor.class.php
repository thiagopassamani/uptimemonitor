<?php

class PluginUptimemonitorNotificationTargetMonitor extends NotificationTarget {

   function getEvents() {
      return PluginUptimemonitorMonitor::getEvents();
   }

   function addDataForObject(CommonDBTM $item, array $options) {
      // Define as variáveis que o usuário poderá usar no modelo de e-mail
      $this->data['##monitor.name##']  = $item->fields['name'];
      $this->data['##monitor.url##']   = $item->fields['url'];
      $this->data['##monitor.status##'] = $item->fields['last_status'];
      $this->data['##monitor.date##']   = Html::convDateTime(date("Y-m-d H:i:s"));
   }

   function getTags() {
      return [
         '##monitor.name##'   => __('Nome', 'uptimemonitor'),
         '##monitor.url##'    => __('URL/IP', 'uptimemonitor'),
         '##monitor.status##' => __('Status', 'uptimemonitor'),
         '##monitor.date##'   => __('Data/Hora', 'uptimemonitor'),
      ];
   }
}