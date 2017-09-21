<?php
include ("../../../inc/includes.php");

$table   = PluginFieldsField::getTable();

// Récupération de l'ID du champ à modifier
$query   = "SELECT id FROM $table
            WHERE plugin_fields_containers_id = {$_POST['container_id']}
            AND `ranking` = {$_POST['old_order']}";
$result  = $DB->queryOrDie($query, 'Erreur');
$first   = $result->fetch_assoc();
$id_item = $first['id'];

// Réorganisation de tout les champs
if ($_POST['old_order'] < $_POST['new_order']) {
   $DB->query("UPDATE $table SET
               `ranking` = `ranking`-1
               WHERE plugin_fields_containers_id = {$_POST['container_id']}
               AND `ranking` > {$_POST['old_order']}
               AND `ranking` <= {$_POST['new_order']}");
} else {
   $DB->query("UPDATE $table SET
               `ranking` = `ranking`+1
               WHERE plugin_fields_containers_id = {$_POST['container_id']}
               AND `ranking` < {$_POST['old_order']}
               AND `ranking` >= {$_POST['new_order']}");
}

$DB->query("UPDATE $table SET
            `ranking` = {$_POST['new_order']}
            WHERE id = $id_item");
