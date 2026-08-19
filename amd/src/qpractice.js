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
    setupOtherBanks();

    /**
     * Sets up event listeners for selecting all or none of the checkboxes.
     *
     */
    function setupSelectAll() {
        document.getElementById('id_select_all_none').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll(`[id^="id_categories"]`);
            checkboxes.forEach(checkbox =>{
                checkbox.checked = !checkbox.checked;
            });

        });
    }

    /**
     * Reveal the categories of the "other question bank" chosen from the dropdown.
     *
     */
    function setupOtherBanks() {
        const select = document.getElementById('id_otherbankselect');
        if (!select) {
            return;
        }
        const blocks = document.querySelectorAll('.qp-otherbank');
        const update = () => {
            blocks.forEach(block => {
                block.hidden = true;
            });
            const target = document.getElementById('qp-otherbank-' + select.value);
            if (target) {
                target.hidden = false;
            }
        };
        select.addEventListener('change', update);
        update();
    }

};


