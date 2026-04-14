<?php
/**
 * Uptime Monitor Plugin for GLPI
 * Author: Thiago Passamani
 * @class PluginUptimemonitorNotificationTargetMonitor
 * This class defines a notification target for monitor-related events, allowing the use of monitor data in notification templates.
 */

class PluginUptimemonitorNotificationTargetMonitor extends NotificationTarget {

    public function getEvents() {
        return PluginUptimemonitorMonitor::getEvents();
    }

    public function addDataForTemplate($event, $options = []) {
    //public function addDataForTemplate(CommonDBTM $item, array $options = []) {
    
        $fields = $item->fields ?? [];

        $this->data['##monitor.name##']           = $fields['name']             ?? __('N/A', 'uptimemonitor');
        $this->data['##monitor.url##']            = $fields['url']              ?? __('N/A', 'uptimemonitor');
        $this->data['##monitor.status##']         = $fields['last_status']      ?? __('N/A', 'uptimemonitor');
        $this->data['##monitor.date##']           = Html::convDateTime($fields['last_check'] ?? date("Y-m-d H:i:s"));
        $this->data['##monitor.response_time##']  = $fields['response_time']    ?? __('N/A', 'uptimemonitor');
        $this->data['##monitor.criticality##']    = $fields['criticality']      ?? __('N/A', 'uptimemonitor');
    }

    public function getTags() {
        return [
            '##monitor.name##'          => __('Nome', 'uptimemonitor'),
            '##monitor.url##'           => __('URL/IP', 'uptimemonitor'),
            '##monitor.status##'        => __('Status', 'uptimemonitor'),
            '##monitor.date##'          => __('Data/Hora', 'uptimemonitor'),
            '##monitor.response_time##' => __('Tempo de Resposta', 'uptimemonitor'),
            '##monitor.criticality##'   => __('Criticidade', 'uptimemonitor'),
        ];
    }
}