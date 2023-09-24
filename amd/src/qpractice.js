// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript code for the gapfill question type.
 *
 * @package    qtype
 * @subpackage gapfill
 * @copyright  2023 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        init: function() {
        document.getElementById('id_select_all_none').addEventListener('click', e => {
                var checkboxes = document.querySelectorAll("input[type='checkbox'].question_category");
                checkboxes.forEach(checkbox =>{
                    checkbox.checked = !checkbox.checked;
                });
        });
        document.querySelectorAll("input[type='checkbox'].question_category").forEach(
                input => input.addEventListener('click', function(event) {
                    var checkboxid = event.target.id.split('_')[2];
                    if(event.target.checked == true) {
                    selectChildren(checkboxid, true);
                    } else{
                        selectChildren(checkboxid, false);

                    }
                })
            );

        }
    };
    function selectChildren(checkboxid, isChecked){
        // var parent = document.getElementById(parentid);
        var checkboxes =  document.querySelectorAll("input[type='checkbox'].question_category");
        checkboxes.forEach(function(checkbox) {
            var thisparentid = checkbox.id.split('_')[4];
            if(thisparentid == checkboxid){
                checkbox.checked= isChecked;
            }

        });
    }

});