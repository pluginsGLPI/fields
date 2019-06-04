<?php
include ("../../../inc/includes.php");

if (!array_key_exists('container_id', $_POST)
    || !array_key_exists('old_order', $_POST)
    || !array_key_exists('new_order', $_POST)) {
   // Missing input
   exit();
}

$table        = PluginFieldsField::getTable();
$container_id = (int)$_POST['container_id'];
$old_order    = (int)$_POST['old_order'];
$new_order    = (int)$_POST['new_order'];

// Retrieve id of field to update
$field_iterator = $DB->request(
   [
      'SELECT' => 'id',
      'FROM'   => $table,
      'WHERE'  => [
         'plugin_fields_containers_id' => $container_id,
         'ranking'                     => $old_order,
      ],
   ]
);

if (0 === $field_iterator->count()) {
   // Unknown field
   exit();
}

$field_id = $field_iterator->next()['id'];

// Move all elements to their new ranking
if ($old_order < $new_order) {
   $DB->update(
      $table,
      [
         'ranking' => new \QueryExpression($DB->quoteName('ranking') . ' - 1'),
      ],
      [
         'plugin_fields_containers_id' => $container_id,
         ['ranking' => ['>',  $old_order]],
         ['ranking' => ['<=', $new_order]],
      ]
   );
} else {
   $DB->update(
      $table,
      [
         'ranking' => new \QueryExpression($DB->quoteName('ranking') . ' + 1'),
      ],
      [
         'plugin_fields_containers_id' => $container_id,
         ['ranking' => ['<',  $old_order]],
         ['ranking' => ['>=', $new_order]],
      ]
   );
}

// Update current element
$DB->update(
   $table,
   [
      'ranking' => $new_order,
   ],
   [
      'id' => $field_id,
   ]
);
