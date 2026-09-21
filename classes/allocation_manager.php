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
 * Peer-review allocation service.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class allocation_manager {
    /**
     * Rebuilds peer allocations using a balanced cyclic assignment.
     *
     * @param object $activity Activity record.
     * @return int Number of allocations created.
     */
    public static function rebuild(object $activity): int {
        global $DB;

        if ($activity->videomode !== 'submission') {
            return 0;
        }
        $submissions = array_values($DB->get_records('videoreview_submission', [
            'videoreviewid' => $activity->id,
            'status' => 'submitted',
        ], 'userid ASC'));
        $count = count($submissions);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('videoreview_allocation', ['videoreviewid' => $activity->id]);
        if ($count < 2) {
            $transaction->allow_commit();
            return 0;
        }

        $reviewsperuser = min(max(0, (int)$activity->peerreviewcount), $count - 1);
        $created = 0;
        $now = time();

        for ($reviewerindex = 0; $reviewerindex < $count; $reviewerindex++) {
            $reviewerid = (int)$submissions[$reviewerindex]->userid;
            for ($offset = 1; $offset <= $reviewsperuser; $offset++) {
                $submission = $submissions[($reviewerindex + $offset) % $count];
                $completed = $DB->record_exists('videoreview_review', [
                    'videoreviewid' => $activity->id,
                    'submissionid' => $submission->id,
                    'reviewerid' => $reviewerid,
                    'status' => 'submitted',
                ]);
                $DB->insert_record('videoreview_allocation', (object)[
                    'videoreviewid' => $activity->id,
                    'submissionid' => $submission->id,
                    'reviewerid' => $reviewerid,
                    'status' => $completed ? 'completed' : 'assigned',
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $created++;
            }
        }
        $transaction->allow_commit();
        return $created;
    }

    /**
     * Returns whether all allocations for a user have been completed.
     *
     * @param int $activityid Activity id.
     * @param int $userid User id.
     * @return bool
     */
    public static function user_completed(int $activityid, int $userid): bool {
        global $DB;
        $total = $DB->count_records('videoreview_allocation', [
            'videoreviewid' => $activityid,
            'reviewerid' => $userid,
        ]);
        if ($total === 0) {
            $required = (int)$DB->get_field('videoreview', 'peerreviewcount', ['id' => $activityid]);
            return $required === 0;
        }
        $completed = $DB->count_records('videoreview_allocation', [
            'videoreviewid' => $activityid,
            'reviewerid' => $userid,
            'status' => 'completed',
        ]);
        return $completed >= $total;
    }
}
