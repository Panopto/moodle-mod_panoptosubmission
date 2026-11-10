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

namespace mod_panoptosubmission\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/html2text/lib.php');

/**
 * Converts feedback HTML to editable plain text without changing the words.
 *
 * Core's converter uppercases bold text and headings and strikes through deleted text,
 * which suits emails but would be saved back if a grader edited the quick grading text.
 *
 * @package    mod_panoptosubmission
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class html2text extends \core_html2text {
    /**
     * Keep the text's case as written.
     *
     * @param string $str The text.
     * @return string The same text.
     */
    protected function toupper($str) {
        return $str;
    }

    /**
     * Keep deleted text plain rather than adding strike-through characters.
     *
     * @param string $str The text.
     * @return string The same text.
     */
    protected function tostrike($str) {
        return $str;
    }
}
