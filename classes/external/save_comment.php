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
use mod_videoreview\review_manager;

/**
 * AJAX endpoint for timestamped comments.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_comment extends external_api {
    /**
     * Defines input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'submissionid' => new external_value(PARAM_INT, 'Submission id or zero for the common video'),
            'timecode' => new external_value(PARAM_FLOAT, 'Video time in seconds'),
            'comment' => new external_value(PARAM_TEXT, 'Comment text'),
        ]);
    }

    /**
     * Saves one timestamped comment.
     *
     * @param int $cmid Course module id.
     * @param int $submissionid Submission id.
     * @param float $timecode Video time.
     * @param string $comment Comment.
     * @return array
     */
    public static function execute(int $cmid, int $submissionid, float $timecode, string $comment): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'submissionid', 'timecode', 'comment'));
        $cm = get_coursemodule_from_id('videoreview', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        $activity = $DB->get_record('videoreview', ['id' => $cm->instance], '*', MUST_EXIST);
        review_manager::require_review_access($activity, $params['submissionid'], $USER->id, $context);

        $comment = trim($params['comment']);
        if ($comment === '') {
            throw new \invalid_parameter_exception('Comment cannot be empty.');
        }
        $review = review_manager::get_or_create((int)$activity->id, $params['submissionid'], (int)$USER->id);
        $now = time();
        $record = (object)[
            'reviewid' => $review->id,
            'timecode' => max(0, $params['timecode']),
            'comment' => $comment,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('videoreview_comment', $record);

        return [
            'id' => (int)$record->id,
            'timecode' => (float)$record->timecode,
            'comment' => format_string($record->comment),
        ];
    }

    /**
     * Defines returned data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Comment id'),
            'timecode' => new external_value(PARAM_FLOAT, 'Video time'),
            'comment' => new external_value(PARAM_TEXT, 'Comment text'),
        ]);
    }
}
