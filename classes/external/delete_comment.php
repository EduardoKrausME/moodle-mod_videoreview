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

namespace mod_videoreview\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * AJAX endpoint for deleting the current reviewer's comment.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_comment extends external_api {
    /**
     * Defines input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'commentid' => new external_value(PARAM_INT, 'Comment id'),
        ]);
    }

    /**
     * Deletes one comment.
     *
     * @param int $cmid Course module id.
     * @param int $commentid Comment id.
     * @return array
     */
    public static function execute(int $cmid, int $commentid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'commentid'));
        $cm = get_coursemodule_from_id('videoreview', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videoreview:review', $context);

        $comment = $DB->get_record_sql(
            'SELECT c.*
                   FROM {videoreview_comment} c
                   JOIN {videoreview_review} r ON r.id = c.reviewid
                  WHERE c.id = :commentid
                   AND r.videoreviewid = :activityid
                   AND r.reviewerid = :reviewerid',
            ['commentid' => $params['commentid'], 'activityid' => $cm->instance, 'reviewerid' => $USER->id],
            MUST_EXIST
        );
        $DB->delete_records('videoreview_comment', ['id' => $comment->id]);
        return ['deleted' => true];
    }

    /**
     * Defines returned data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'Whether the comment was deleted'),
        ]);
    }
}
