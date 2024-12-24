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
 * JavaScript code for the qpractice activity.
 *
 * @subpackage qpractice
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export const init = () => {

    setupSelectAll();


    function setupSelectAll() {
    document.getElementById('id_select_all_none').addEventListener('click', function() {
            var controlCheckboxes = document.querySelectorAll("input[type='checkbox'].question_category");
            controlCheckboxes.forEach(controlCheckbox =>{
                controlCheckbox.checked = !controlCheckbox.checked;
            });
            const formCheckboxes = document.querySelectorAll('[name^="form_category["]');
            formCheckboxes.forEach(formCheckbox =>{
                formCheckbox.checked = !formCheckbox.checked;
            });

    });
``}

    function setupChildren() {
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
    };
}
    setupCategories();
    function setupCategories() {
        const categoryElements = document.querySelectorAll('[name^="form_category["]');
        categoryElements.forEach(function(element) {
            const categoryId = element.name.match(/\d+/)[0];
            const matchingCheckbox = document.querySelector(`[id^="id_categories_${categoryId}"]`);
            if (matchingCheckbox) {
                matchingCheckbox.checked = element.checked;
            }
        });
    }

    syncCategories();
    function syncCategories() {
        document.querySelectorAll('input[id^="id_categories_"]').forEach(
            input => input.addEventListener('click', function(event) {
                var id = input.id.split('_')[2];
                var target = document.querySelector(`#id_form_category_${id}`);
                if (target) {
                    target.checked = !target.checked;
                }

            })
        );

    }

/**
 * Select or deselect child checkboxes
 * @param {string} checkboxid - The ID of the parent checkbox
 * @param {boolean} isChecked - Whether to check or uncheck the children
 */
function selectChildren(checkboxid, isChecked){
    var checkboxes =  document.querySelectorAll("input[type='checkbox'].question_category");
    checkboxes.forEach(function(checkbox) {
        var thisparentid = checkbox.id.split('_')[4];
        if(thisparentid == checkboxid){
            checkbox.checked= isChecked;
        }

    });
}

