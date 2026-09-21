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

namespace mod_videoreview\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for Video Peer Review.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describes stored personal data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videoreview_submission', [
            'userid' => 'privacy:metadata:submission:userid',
            'title' => 'privacy:metadata:submission:title',
            'videourl' => 'privacy:metadata:submission:videourl',
            'timecreated' => 'privacy:metadata:submission:timecreated',
            'timemodified' => 'privacy:metadata:submission:timemodified',
        ], 'privacy:metadata:submission');
        $collection->add_database_table('videoreview_allocation', [
            'reviewerid' => 'privacy:metadata:allocation:reviewerid',
            'submissionid' => 'privacy:metadata:allocation:submissionid',
            'status' => 'privacy:metadata:allocation:status',
        ], 'privacy:metadata:allocation');
        $collection->add_database_table('videoreview_review', [
            'reviewerid' => 'privacy:metadata:review:reviewerid',
            'submissionid' => 'privacy:metadata:review:submissionid',
            'overallfeedback' => 'privacy:metadata:review:overallfeedback',
            'score' => 'privacy:metadata:review:score',
            'timesubmitted' => 'privacy:metadata:review:timesubmitted',
        ], 'privacy:metadata:review');
        $collection->add_database_table('videoreview_score', [
            'score' => 'privacy:metadata:score:score',
            'concept' => 'privacy:metadata:score:concept',
            'feedback' => 'privacy:metadata:score:feedback',
        ], 'privacy:metadata:score');
        $collection->add_database_table('videoreview_comment', [
            'timecode' => 'privacy:metadata:comment:timecode',
            'comment' => 'privacy:metadata:comment:comment',
            'timecreated' => 'privacy:metadata:comment:timecreated',
        ], 'privacy:metadata:comment');
        $collection->add_database_table('videoreview_progress', [
            'userid' => 'privacy:metadata:progress:userid',
            'lastposition' => 'privacy:metadata:progress:lastposition',
            'uniquewatched' => 'privacy:metadata:progress:uniquewatched',
            'totalwatchtime' => 'privacy:metadata:progress:totalwatchtime',
            'percent' => 'privacy:metadata:progress:percent',
            'watchedsegments' => 'privacy:metadata:progress:watchedsegments',
        ], 'privacy:metadata:progress');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        return $collection;
    }

    /**
     * Returns contexts containing data for a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videoreview} v ON v.id = cm.instance
             LEFT JOIN {videoreview_submission} s ON s.videoreviewid = v.id
             LEFT JOIN {videoreview_review} r ON r.videoreviewid = v.id
             LEFT JOIN {videoreview_allocation} a ON a.videoreviewid = v.id
             LEFT JOIN {videoreview_progress} p ON p.videoreviewid = v.id
                 WHERE s.userid = :submissionuser
                    OR r.reviewerid = :reviewer
                    OR a.reviewerid = :allocationuser
                    OR p.userid = :progressuser";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videoreview',
            'submissionuser' => $userid,
            'reviewer' => $userid,
            'allocationuser' => $userid,
            'progressuser' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Adds users with data in a module context to a user list.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videoreview', $context->instanceid);
        if (!$cm) {
            return;
        }
        $params = ['activityid' => $cm->instance];
        $userlist->add_from_sql('userid',
            'SELECT s.userid FROM {videoreview_submission} s WHERE s.videoreviewid = :activityid', $params);
        $userlist->add_from_sql('reviewerid', '
        SELECT r.reviewerid FROM {videoreview_review} r WHERE r.videoreviewid = :activityid', $params);
        $userlist->add_from_sql('reviewerid',
            'SELECT a.reviewerid FROM {videoreview_allocation} a WHERE a.videoreviewid = :activityid', $params);
        $userlist->add_from_sql('userid',
            'SELECT p.userid FROM {videoreview_progress} p WHERE p.videoreviewid = :activityid', $params);
    }

    /**
     * Exports personal data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videoreview', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $activity = $DB->get_record('videoreview', ['id' => $cm->instance]);
            if (!$activity) {
                continue;
            }
            $writer = writer::with_context($context);
            $writer->export_data([], (object)[
                'activity' => format_string($activity->name),
            ]);

            $submission = $DB->get_record('videoreview_submission', [
                'videoreviewid' => $activity->id,
                'userid' => $userid,
            ]);
            if ($submission) {
                $writer->export_data([get_string('submission', 'videoreview')], (object)[
                    'title' => $submission->title,
                    'videosource' => $submission->videosource,
                    'videourl' => $submission->videourl,
                    'timecreated' => transform::datetime($submission->timecreated),
                    'timemodified' => transform::datetime($submission->timemodified),
                ]);
                $writer->export_area_files([], 'mod_videoreview', 'submissionvideo', $submission->id);
            }

            $reviews = $DB->get_records('videoreview_review', [
                'videoreviewid' => $activity->id,
                'reviewerid' => $userid,
            ]);
            $exportedreviews = [];
            foreach ($reviews as $review) {
                $scores = $DB->get_records('videoreview_score', ['reviewid' => $review->id]);
                $comments = $DB->get_records('videoreview_comment', ['reviewid' => $review->id]);
                $exportedreviews[] = (object)[
                    'submissionid' => $review->submissionid,
                    'status' => $review->status,
                    'score' => $review->score,
                    'overallfeedback' => $review->overallfeedback,
                    'timesubmitted' => transform::datetime($review->timesubmitted),
                    'criteria' => array_values($scores),
                    'comments' => array_values($comments),
                ];
            }
            if ($exportedreviews) {
                $writer->export_data([get_string('reviewsperformed', 'videoreview')], (object)[
                    'reviews' => $exportedreviews,
                ]);
            }

            $progress = $DB->get_records('videoreview_progress', [
                'videoreviewid' => $activity->id,
                'userid' => $userid,
            ]);
            if ($progress) {
                $writer->export_data([get_string('viewingprogress', 'videoreview')], (object)[
                    'records' => array_values($progress),
                ]);
            }
        }
    }

    /**
     * Deletes all user data from a module context.
     *
     * @param \context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videoreview', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_activity_data((int)$cm->instance, $context);
    }

    /**
     * Deletes one user's data from approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('videoreview', $context->instanceid);
            if ($cm) {
                self::delete_user_data((int)$cm->instance, (int)$contextlist->get_user()->id, $context);
            }
        }
    }

    /**
     * Deletes data for users approved through the user-list API.
     *
     * @param approved_userlist $userlist Approved user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('videoreview', $context->instanceid);
        if (!$cm) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_data((int)$cm->instance, (int)$userid, $context);
        }
    }

    /**
     * Deletes every data row for an activity while preserving the activity configuration itself.
     *
     * @param int $activityid Activity id.
     * @param \context_module $context Module context.
     * @return void
     */
    private static function delete_activity_data(int $activityid, \context_module $context): void {
        global $DB;
        $reviews = $DB->get_records('videoreview_review', ['videoreviewid' => $activityid], '', 'id');
        if ($reviews) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($reviews), SQL_PARAMS_NAMED, 'review');
            $DB->delete_records_select('videoreview_comment', "reviewid {$insql}", $params);
            $DB->delete_records_select('videoreview_score', "reviewid {$insql}", $params);
        }
        $DB->delete_records('videoreview_review', ['videoreviewid' => $activityid]);
        $DB->delete_records('videoreview_allocation', ['videoreviewid' => $activityid]);
        $DB->delete_records('videoreview_progress', ['videoreviewid' => $activityid]);
        $DB->delete_records('videoreview_submission', ['videoreviewid' => $activityid]);
        get_file_storage()->delete_area_files($context->id, 'mod_videoreview', 'submissionvideo');
    }

    /**
     * Deletes all data owned by or directly assigned to one user.
     *
     * @param int $activityid Activity id.
     * @param int $userid User id.
     * @param \context_module $context Module context.
     * @return void
     */
    private static function delete_user_data(int $activityid, int $userid, \context_module $context): void {
        global $DB;

        $ownreviews = $DB->get_records('videoreview_review', [
            'videoreviewid' => $activityid,
            'reviewerid' => $userid,
        ], '', 'id');
        self::delete_reviews(array_keys($ownreviews));
        $DB->delete_records('videoreview_allocation', ['videoreviewid' => $activityid, 'reviewerid' => $userid]);
        $DB->delete_records('videoreview_progress', ['videoreviewid' => $activityid, 'userid' => $userid]);

        $submission = $DB->get_record('videoreview_submission', [
            'videoreviewid' => $activityid,
            'userid' => $userid,
        ]);
        if ($submission) {
            $receivedreviews = $DB->get_records('videoreview_review', [
                'videoreviewid' => $activityid,
                'submissionid' => $submission->id,
            ], '', 'id');
            self::delete_reviews(array_keys($receivedreviews));
            $DB->delete_records('videoreview_allocation', ['submissionid' => $submission->id]);
            $DB->delete_records('videoreview_progress', ['videoreviewid' => $activityid, 'submissionid' => $submission->id]);
            get_file_storage()->delete_area_files($context->id, 'mod_videoreview', 'submissionvideo', $submission->id);
            $DB->delete_records('videoreview_submission', ['id' => $submission->id]);
        }
    }

    /**
     * Deletes review rows and their child data.
     *
     * @param array $reviewids Review ids.
     * @return void
     */
    private static function delete_reviews(array $reviewids): void {
        global $DB;
        if (!$reviewids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($reviewids, SQL_PARAMS_NAMED, 'review');
        $DB->delete_records_select('videoreview_comment', "reviewid {$insql}", $params);
        $DB->delete_records_select('videoreview_score', "reviewid {$insql}", $params);
        $DB->delete_records_select('videoreview_review', "id {$insql}", $params);
    }
}
