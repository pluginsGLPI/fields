<?php

class PluginFieldsNotificationTargetTicket
{

    public static function addNotificationDatas(NotificationTargetTicket $target) {

        $event = $target->raiseevent;
        if (isset($target->obj->fields['id'])) {
            $tickets_id  = $target->obj->fields['id'];

            $containers = PluginFieldsContainer::findContainers('Ticket', 'dom', '', $tickets_id);
    
            foreach ($containers as $c_id) {
    
                $container_obj = new PluginFieldsContainer();
                $container_obj->getFromDB($c_id);

                if (empty($container_obj->fields)) {
                    continue;
                }

                $container_name = $container_obj->fields['name'];
                $bloc_classname = PluginFieldsContainer::getClassname('Ticket', $container_name);

                if (class_exists($bloc_classname)) {

                    $bloc_obj = new $bloc_classname();

                    $values = $bloc_obj->find([
                        'plugin_fields_containers_id' => $c_id,
                        'items_id'                    => $tickets_id,
                    ]);
                    $values = array_shift($values);

                    foreach ($values as $field_name => $value) {
                        if (in_array($field_name, ['id', 'items_id', 'itemtype', 'plugin_fields_containers_id', 'entities_id'])) {
                            continue;
                        }
                        $key = "##fields.{$container_name}.{$field_name}##";
                        $target->data[$key] = is_array($value) ? implode(', ', $value) : $value;
                    }
                }
            }
        }
    }
}
