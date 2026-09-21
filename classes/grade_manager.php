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

namespace mod_videoreview;

/**
 * Gradebook integration for student video submissions.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_manager {
    /**
     * Recalculates the author grade for one submission.
     *
     * @param object $activity Activity record.
     * @param int $submissionid Submission id.
     * @return void
     */
    public static function update_submission_grade(object $activity, int $submissionid): void {
        global $DB;

        if ($activity->videomode !== 'submission') {
            return;
        }
        $submission = $DB->get_record('videoreview_submission', [
            'id' => $submissionid,
            'videoreviewid' => $activity->id,
        ]);
        if (!$submission) {
            return;
        }
        $average = $DB->get_field_sql(
            'SELECT AVG(score)
                   FROM {videoreview_review}
                  WHERE videoreviewid = :activityid
                    AND submissionid = :submissionid
                    AND status = :status',
            ['activityid' => $activity->id, 'submissionid' => $submissionid, 'status' => 'submitted']
        );
        if ($average === false || $average === null) {
            videoreview_grade_item_update($activity, ['userid' => $submission->userid, 'rawgrade' => null]);
            return;
        }
        $rawgrade = ((float)$average / 100) * (float)$activity->grade;
        videoreview_grade_item_update($activity, ['userid' => $submission->userid, 'rawgrade' => $rawgrade]);
    }

    /**
     * Recalculates every grade in an activity.
     *
     * @param object $activity Activity record.
     * @return void
     */
    public static function update_all(object $activity): void {
        global $DB;
        $submissions = $DB->get_records('videoreview_submission', ['videoreviewid' => $activity->id]);
        foreach ($submissions as $submission) {
            self::update_submission_grade($activity, (int)$submission->id);
        }
    }
}
