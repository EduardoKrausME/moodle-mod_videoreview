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
 * Backup task for Video Peer Review.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Video Peer Review backup task.
 */
class backup_videoreview_activity_task extends backup_activity_task {
    /**
     * Defines activity-specific backup settings.
     *
     * @return void
     */
    protected function define_my_settings(): void {
    }

    /**
     * Defines activity-specific backup steps.
     *
     * @return void
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_videoreview_activity_structure_step('videoreview_structure', 'videoreview.xml'));
    }

    /**
     * Encodes links inside activity content.
     *
     * @param string $content Content.
     * @return string
     */
    public static function encode_content_links($content): string {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace(
            "/({$base}\\/mod\\/videoreview\\/index.php\\?id=)([0-9]+)/",
            '$@VIDEOREVIEWINDEX*$2@$',
            $content
        );
        return preg_replace(
            "/({$base}\\/mod\\/videoreview\\/view.php\\?id=)([0-9]+)/",
            '$@VIDEOREVIEWVIEWBYID*$2@$',
            $content
        );
    }
}
