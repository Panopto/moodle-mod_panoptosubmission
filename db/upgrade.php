<?php
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
 * Panopto Student Submission upgrade script.
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This is ran when the plugin is upgraded
 * @param string $oldversion the version previously installed
 * @return whether the upgrade was a success
 */
function xmldb_panoptosubmission_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();


    if ($oldversion < 2022070704) {
        // Define table importmap where we will place all of our imports.
        

        // Define field creator_mapping to be added to block_panopto_foldermap.
        $table = new xmldb_table('panoptosubmission');
        $field = new xmldb_field('cutofftime', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'timemodified');

        // Conditionally launch add field creator_mapping.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Panopto savepoint reached.
        upgrade_mod_savepoint(true, 2022070704, 'panoptosubmission');
    }
    return true;
}
