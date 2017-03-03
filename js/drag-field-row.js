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
