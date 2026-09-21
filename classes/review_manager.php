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

use context_module;
use moodle_exception;

/**
 * Review persistence and access rules.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_manager {
    /**
     * Verifies that a user may review a specific video.
     *
     * @param object $activity Activity.
     * @param int $submissionid Submission id, or zero for common video.
     * @param int $userid Reviewer id.
     * @param context_module $context Module context.
     * @return void
     */
    public static function require_review_access(object $activity, int $submissionid, int $userid, context_module $context): void {
        global $DB;

        require_capability('mod/videoreview:review', $context, $userid);
        if (has_capability('mod/videoreview:viewall', $context, $userid)) {
            if ($activity->videomode === 'common' && $submissionid !== 0) {
                throw new moodle_exception('invalidsubmission', 'videoreview');
            }
            if ($submissionid > 0) {
                $DB->get_record('videoreview_submission', [
                    'id' => $submissionid,
                    'videoreviewid' => $activity->id,
                ], '*', MUST_EXIST);
            }
            return;
        }

        if ($activity->videomode === 'common') {
            if ($submissionid !== 0) {
                throw new moodle_exception('invalidsubmission', 'videoreview');
            }
            return;
        }

        if ($submissionid <= 0 || !$DB->record_exists('videoreview_allocation', [
                'videoreviewid' => $activity->id,
                'submissionid' => $submissionid,
                'reviewerid' => $userid,
            ])) {
            throw new moodle_exception('notallocated', 'videoreview');
        }
    }

    /**
     * Returns or creates the review header for a reviewer and video.
     *
     * @param int $activityid Activity id.
     * @param int $submissionid Submission id.
     * @param int $reviewerid Reviewer id.
     * @return object
     */
    public static function get_or_create(int $activityid, int $submissionid, int $reviewerid): object {
        global $DB;

        $params = [
            'videoreviewid' => $activityid,
            'submissionid' => $submissionid,
            'reviewerid' => $reviewerid,
        ];
        $review = $DB->get_record('videoreview_review', $params);
        if ($review) {
            return $review;
        }

        $now = time();
        $record = (object)($params + [
                'status' => 'draft',
                'overallfeedback' => '',
                'score' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
                'timesubmitted' => 0,
            ]);
        $record->id = $DB->insert_record('videoreview_review', $record);
        return $record;
    }

    /**
     * Returns comments for a review in chronological order.
     *
     * @param int $reviewid Review id.
     * @return array
     */
    public static function comments(int $reviewid): array {
        global $DB;
        return array_values($DB->get_records('videoreview_comment', ['reviewid' => $reviewid], 'timecode ASC, id ASC'));
    }

    /**
     * Saves and submits criterion scores.
     *
     * @param object $activity Activity.
     * @param object $review Review record.
     * @param array $data Submitted data.
     * @return object Updated review.
     */
    public static function submit(object $activity, object $review, array $data): object {
        global $DB;

        $criteria = $DB->get_records('videoreview_criterion', ['videoreviewid' => $activity->id], 'sortorder ASC, id ASC');
        $totalnormalized = 0.0;
        $count = 0;

        $transaction = $DB->start_delegated_transaction();
        foreach ($criteria as $criterion) {
            $scorekey = 'criterion_' . $criterion->id;
            $feedbackkey = 'feedback_' . $criterion->id;
            $rawvalue = isset($data[$scorekey]) ? trim((string)$data[$scorekey]) : '';
            if ($criterion->required && $rawvalue === '') {
                throw new moodle_exception('criterionrequired', 'videoreview', '', format_string($criterion->name));
            }
            if ($rawvalue === '') {
                continue;
            }

            $score = 0.0;
            $concept = '';
            if ($criterion->criteriontype === 'concept') {
                $options = self::concept_options($criterion);
                if (!array_key_exists($rawvalue, $options)) {
                    throw new moodle_exception('invalidconcept', 'videoreview');
                }
                $concept = $rawvalue;
                $score = (float)$options[$rawvalue];
            } else {
                if (!is_numeric($rawvalue)) {
                    throw new moodle_exception('invalidscore', 'videoreview');
                }
                $score = (float)$rawvalue;
                if ($score < 0 || $score > (float)$criterion->maxscore) {
                    throw new moodle_exception('invalidscore', 'videoreview');
                }
            }

            $existing = $DB->get_record('videoreview_score', [
                'reviewid' => $review->id,
                'criterionid' => $criterion->id,
            ]);
            $record = (object)[
                'reviewid' => $review->id,
                'criterionid' => $criterion->id,
                'score' => $score,
                'concept' => $concept,
                'feedback' => trim((string)($data[$feedbackkey] ?? '')),
            ];
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('videoreview_score', $record);
            } else {
                $DB->insert_record('videoreview_score', $record);
            }

            $maxscore = max(0.00001, (float)$criterion->maxscore);
            $totalnormalized += min(1, max(0, $score / $maxscore));
            $count++;
        }

        $review->overallfeedback = trim((string)($data['overallfeedback'] ?? ''));
        $review->score = $count > 0 ? ($totalnormalized / $count) * 100 : 0;
        $review->status = 'submitted';
        $review->timesubmitted = time();
        $review->timemodified = time();
        $DB->update_record('videoreview_review', $review);

        if ($review->submissionid > 0) {
            $DB->set_field('videoreview_allocation', 'status', 'completed', [
                'videoreviewid' => $activity->id,
                'submissionid' => $review->submissionid,
                'reviewerid' => $review->reviewerid,
            ]);
        }
        $transaction->allow_commit();

        if ($review->submissionid > 0) {
            grade_manager::update_submission_grade($activity, $review->submissionid);
        }
        return $review;
    }

    /**
     * Recalculates normalized scores for all submitted reviews after criterion changes.
     *
     * Existing reviews remain submitted; only criteria that still have a stored score
     * participate in the recalculated total.
     *
     * @param object $activity Activity record.
     * @return void
     */
    public static function recalculate_activity_scores(object $activity): void {
        global $DB;

        $criteria = $DB->get_records('videoreview_criterion', [
            'videoreviewid' => $activity->id,
        ], '', 'id,maxscore');
        $reviews = $DB->get_records('videoreview_review', [
            'videoreviewid' => $activity->id,
            'status' => 'submitted',
        ]);

        foreach ($reviews as $review) {
            $scores = $DB->get_records('videoreview_score', ['reviewid' => $review->id]);
            $totalnormalized = 0.0;
            $count = 0;
            foreach ($scores as $score) {
                if (!isset($criteria[$score->criterionid])) {
                    continue;
                }
                $maxscore = max(0.00001, (float)$criteria[$score->criterionid]->maxscore);
                $totalnormalized += min(1, max(0, (float)$score->score / $maxscore));
                $count++;
            }
            $review->score = $count > 0 ? ($totalnormalized / $count) * 100 : 0;
            $review->timemodified = time();
            $DB->update_record('videoreview_review', $review);
        }

        grade_manager::update_all($activity);
    }

    /**
     * Parses configured concept options.
     *
     * Stored format is one option per line: Label|score.
     *
     * @param object $criterion Criterion record.
     * @return array Label => numeric score.
     */
    public static function concept_options(object $criterion): array {
        $result = [];
        $lines = preg_split('/\R/u', (string)$criterion->conceptoptions) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $label = $parts[0];
            $score = isset($parts[1]) && is_numeric($parts[1]) ? (float)$parts[1] : 0.0;
            $result[$label] = min((float)$criterion->maxscore, max(0.0, $score));
        }
        return $result;
    }
}
