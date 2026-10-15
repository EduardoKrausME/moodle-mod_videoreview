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
 * Restore task for Video Peer Review.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Video Peer Review restore task.
 */
class restore_videoreview_activity_task extends restore_activity_task {
    /**
     * Defines activity-specific restore settings.
     *
     * @return void
     */
    protected function define_my_settings(): void {
    }

    /**
     * Defines activity-specific restore steps.
     *
     * @return void
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_videoreview_activity_structure_step('videoreview_structure', 'videoreview.xml'));
    }

    /**
     * Defines content link decoding rules.
     *
     * @return array
     */
    public static function define_decode_contents(): array {
        return [];
    }

    /**
     * Defines URL decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('VIDEOREVIEWVIEWBYID', '/mod/videoreview/view.php?id=$1', 'course_module'),
            new restore_decode_rule('VIDEOREVIEWINDEX', '/mod/videoreview/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Defines restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules(): array {
        return [];
    }
}
