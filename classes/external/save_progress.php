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
use mod_videoreview\progress_manager;
use mod_videoreview\review_manager;

/**
 * AJAX endpoint for viewing progress.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_progress extends external_api {
    /**
     * Defines input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'submissionid' => new external_value(PARAM_INT, 'Submission id or zero for common video'),
            'duration' => new external_value(PARAM_FLOAT, 'Video duration'),
            'position' => new external_value(PARAM_FLOAT, 'Current position'),
            'segmentsjson' => new external_value(PARAM_RAW, 'JSON array of watched intervals'),
        ]);
    }

    /**
     * Stores one progress update.
     *
     * @param int $cmid Course module id.
     * @param int $submissionid Submission id.
     * @param float $duration Duration.
     * @param float $position Current position.
     * @param string $segmentsjson Watched intervals JSON.
     * @return array
     */
    public static function execute(
        int    $cmid,
        int    $submissionid,
        float  $duration,
        float  $position,
        string $segmentsjson
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('cmid', 'submissionid', 'duration', 'position', 'segmentsjson')
        );
        $cm = get_coursemodule_from_id('videoreview', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videoreview:view', $context);
        $activity = $DB->get_record('videoreview', ['id' => $cm->instance], '*', MUST_EXIST);

        if ($activity->videomode === 'common') {
            if ($params['submissionid'] !== 0) {
                throw new \invalid_parameter_exception('Invalid submission id.');
            }
        } else if (!has_capability('mod/videoreview:viewall', $context)) {
            review_manager::require_review_access($activity, $params['submissionid'], $USER->id, $context);
        } else if ($params['submissionid'] > 0) {
            $DB->get_record('videoreview_submission', [
                'id' => $params['submissionid'],
                'videoreviewid' => $activity->id,
            ], '*', MUST_EXIST);
        }

        $segments = json_decode($params['segmentsjson'], true);
        if (!is_array($segments)) {
            throw new \invalid_parameter_exception('Invalid segments JSON.');
        }
        $progress = progress_manager::save(
            (int)$activity->id,
            $params['submissionid'],
            (int)$USER->id,
            $params['duration'],
            $params['position'],
            $segments
        );
        return [
            'percent' => (float)$progress->percent,
            'uniquewatched' => (float)$progress->uniquewatched,
        ];
    }

    /**
     * Defines returned data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'percent' => new external_value(PARAM_FLOAT, 'Unique watched percentage'),
            'uniquewatched' => new external_value(PARAM_FLOAT, 'Unique watched seconds'),
        ]);
    }
}
