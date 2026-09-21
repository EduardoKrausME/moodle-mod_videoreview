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
 * Backup structure for Video Peer Review.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the XML structure used for backup.
 */
class backup_videoreview_activity_structure_step extends backup_activity_structure_step {
    /**
     * Builds the backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $activity = new backup_nested_element('videoreview', ['id'], [
            'name', 'intro', 'introformat', 'videomode', 'commonvideosource', 'commonvideourl',
            'peerreviewcount', 'anonymous', 'releasedate', 'completionrequiresubmission',
            'completionrequirereviews', 'grade', 'timecreated', 'timemodified',
        ]);
        $criteria = new backup_nested_element('criteria');
        $criterion = new backup_nested_element('criterion', ['id'], [
            'name', 'description', 'criteriontype', 'maxscore', 'conceptoptions', 'required',
            'sortorder', 'timecreated', 'timemodified',
        ]);
        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', ['id'], [
            'userid', 'title', 'videosource', 'videourl', 'status', 'timecreated', 'timemodified',
        ]);
        $allocations = new backup_nested_element('allocations');
        $allocation = new backup_nested_element('allocation', ['id'], [
            'submissionid', 'reviewerid', 'status', 'timecreated', 'timemodified',
        ]);
        $reviews = new backup_nested_element('reviews');
        $review = new backup_nested_element('review', ['id'], [
            'submissionid', 'reviewerid', 'status', 'overallfeedback', 'score',
            'timecreated', 'timemodified', 'timesubmitted',
        ]);
        $scores = new backup_nested_element('scores');
        $score = new backup_nested_element('score', ['id'], [
            'criterionid', 'score', 'concept', 'feedback',
        ]);
        $comments = new backup_nested_element('comments');
        $comment = new backup_nested_element('comment', ['id'], [
            'timecode', 'comment', 'timecreated', 'timemodified',
        ]);
        $progressrecords = new backup_nested_element('progressrecords');
        $progress = new backup_nested_element('progress', ['id'], [
            'submissionid', 'userid', 'duration', 'lastposition', 'uniquewatched',
            'totalwatchtime', 'percent', 'watchedsegments', 'timecreated', 'timemodified',
        ]);

        $activity->add_child($criteria);
        $criteria->add_child($criterion);
        $activity->add_child($submissions);
        $submissions->add_child($submission);
        $activity->add_child($allocations);
        $allocations->add_child($allocation);
        $activity->add_child($reviews);
        $reviews->add_child($review);
        $review->add_child($scores);
        $scores->add_child($score);
        $review->add_child($comments);
        $comments->add_child($comment);
        $activity->add_child($progressrecords);
        $progressrecords->add_child($progress);

        $activity->set_source_table('videoreview', ['id' => backup::VAR_ACTIVITYID]);
        $criterion->set_source_table('videoreview_criterion', ['videoreviewid' => backup::VAR_PARENTID]);
        $submission->set_source_table('videoreview_submission', ['videoreviewid' => backup::VAR_PARENTID]);
        $allocation->set_source_table('videoreview_allocation', ['videoreviewid' => backup::VAR_PARENTID]);
        $review->set_source_table('videoreview_review', ['videoreviewid' => backup::VAR_PARENTID]);
        $score->set_source_table('videoreview_score', ['reviewid' => backup::VAR_PARENTID]);
        $comment->set_source_table('videoreview_comment', ['reviewid' => backup::VAR_PARENTID]);
        $progress->set_source_table('videoreview_progress', ['videoreviewid' => backup::VAR_PARENTID]);

        $submission->annotate_ids('user', 'userid');
        $allocation->annotate_ids('user', 'reviewerid');
        $review->annotate_ids('user', 'reviewerid');
        $progress->annotate_ids('user', 'userid');
        $activity->annotate_files('mod_videoreview', 'intro', null);
        $activity->annotate_files('mod_videoreview', 'commonvideo', null);
        $submission->annotate_files('mod_videoreview', 'submissionvideo', 'id');

        return $this->prepare_activity_structure($activity);
    }
}
