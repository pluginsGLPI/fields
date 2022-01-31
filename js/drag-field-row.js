/**
 * -------------------------------------------------------------------------
 * Fields plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Fields.
 *
 * Fields is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Fields is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Fields. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2013-2022 by Fields plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/fields
 * -------------------------------------------------------------------------
 */

/* enable strict mode */
"use strict";

var redipsInit;   // function sets dropMode parameter

// redips initialization
redipsInit = function () {
   // reference to the REDIPS.drag lib
   var rd = REDIPS.drag;
   // initialization
   rd.init();

   rd.event.rowDroppedBefore = function (sourceTable, sourceRowIndex) {
      var pos = rd.getPosition();

      var old_index = sourceRowIndex;
      var new_index = pos[1];
      var container = document.getElementById('plugin_fields_containers_id').value;

      jQuery.ajax({
         type: "POST",
         url: "../ajax/reorder.php",
         data: {
            old_order:     old_index,
            new_order:     new_index,
            container_id:  container
         }
      })
      .fail(function() {
         return false;
      });
   }
};
