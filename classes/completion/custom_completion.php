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

namespace mod_videoreview\completion;

use core_completion\activity_custom_completion;
use mod_videoreview\allocation_manager;

/**
 * Custom completion rules for Video Peer Review.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Evaluates a custom completion rule.
     *
     * @param string $rule Rule name.
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);
        $activity = $DB->get_record('videoreview', ['id' => $this->cm->instance], '*', MUST_EXIST);

        if ($rule === 'completionrequiresubmission') {
            if (!$activity->completionrequiresubmission || $activity->videomode !== 'submission') {
                return COMPLETION_COMPLETE;
            }
            return $DB->record_exists('videoreview_submission', [
                'videoreviewid' => $activity->id,
                'userid' => $this->userid,
                'status' => 'submitted',
            ]) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        if (!$activity->completionrequirereviews) {
            return COMPLETION_COMPLETE;
        }
        if ($activity->videomode === 'common') {
            return $DB->record_exists('videoreview_review', [
                'videoreviewid' => $activity->id,
                'submissionid' => 0,
                'reviewerid' => $this->userid,
                'status' => 'submitted',
            ]) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }
        return allocation_manager::user_completed((int)$activity->id, (int)$this->userid)
            ? COMPLETION_COMPLETE
            : COMPLETION_INCOMPLETE;
    }

    /**
     * Returns supported custom completion rules.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionrequiresubmission', 'completionrequirereviews'];
    }

    /**
     * Returns localized rule descriptions.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;
        $activity = $DB->get_record('videoreview', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $descriptions = [];
        if ($activity->completionrequiresubmission && $activity->videomode === 'submission') {
            $descriptions['completionrequiresubmission'] = get_string('completiondetail:submission', 'videoreview');
        }
        if ($activity->completionrequirereviews) {
            $descriptions['completionrequirereviews'] = get_string('completiondetail:reviews', 'videoreview');
        }
        return $descriptions;
    }

    /**
     * Returns rule display order.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionrequiresubmission',
            'completionrequirereviews',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}
