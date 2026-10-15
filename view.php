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
 * Main activity page.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videoreview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videoreview', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videoreview:view', $context);

$PAGE->set_url('/mod/videoreview/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
}
$event = \mod_videoreview\event\course_module_viewed::create([
    'objectid' => $activity->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('videoreview', $activity);
$event->trigger();

$canreview = has_capability('mod/videoreview:review', $context);
$canviewall = has_capability('mod/videoreview:viewall', $context);
$cansubmit = has_capability('mod/videoreview:submitvideo', $context);
$released = $canviewall || empty($activity->releasedate) || time() >= (int)$activity->releasedate;

$data = [
    'name' => format_string($activity->name),
    'intro' => format_module_intro('videoreview', $activity, $cm->id),
    'hasintro' => trim((string)$activity->intro) !== '',
    'commonmode' => $activity->videomode === 'common',
    'submissionmode' => $activity->videomode === 'submission',
    'canreview' => $canreview,
    'canviewall' => $canviewall,
    'cansubmit' => $cansubmit,
    'released' => $released,
    'releasedate' => $activity->releasedate ? userdate($activity->releasedate) : '',
    'reviewcommonurl' => (new moodle_url('/mod/videoreview/review.php', ['id' => $cm->id]))->out(false),
    'submissionurl' => (new moodle_url('/mod/videoreview/submission.php', ['id' => $cm->id]))->out(false),
    'criteriaurl' => (new moodle_url('/mod/videoreview/criteria.php', ['id' => $cm->id]))->out(false),
    'allocationsurl' => (new moodle_url('/mod/videoreview/allocations.php', ['id' => $cm->id]))->out(false),
    'reporturl' => (new moodle_url('/mod/videoreview/report.php', ['id' => $cm->id]))->out(false),
    'allocations' => [],
    'receivedreviews' => [],
    'teachersubmissions' => [],
];

if ($activity->videomode === 'submission') {
    $ownsubmission = $DB->get_record('videoreview_submission', [
        'videoreviewid' => $activity->id,
        'userid' => $USER->id,
    ]);
    $data['hassubmission'] = (bool)$ownsubmission;
    if ($ownsubmission) {
        $data['submissiontitle'] = format_string($ownsubmission->title);
        $data['submissionmodified'] = userdate($ownsubmission->timemodified);
    }

    if ($canreview && !$canviewall) {
        $sql = "SELECT a.id, a.status, s.id AS submissionid, s.title, s.userid,
                       u.firstname, u.lastname, r.status AS reviewstatus, r.score
                  FROM {videoreview_allocation} a
                  JOIN {videoreview_submission} s ON s.id = a.submissionid
                  JOIN {user} u ON u.id = s.userid
             LEFT JOIN {videoreview_review} r ON r.videoreviewid = a.videoreviewid
                       AND r.submissionid = a.submissionid AND r.reviewerid = a.reviewerid
                 WHERE a.videoreviewid = :activityid AND a.reviewerid = :reviewerid
              ORDER BY a.id";
        $allocations = $DB->get_records_sql($sql, ['activityid' => $activity->id, 'reviewerid' => $USER->id]);
        foreach ($allocations as $allocation) {
            $data['allocations'][] = [
                'title' => format_string($allocation->title),
                'author' => fullname((object)['firstname' => $allocation->firstname, 'lastname' => $allocation->lastname]),
                'completed' => ($allocation->reviewstatus ?? '') === 'submitted',
                'score' => isset($allocation->score) ? format_float($allocation->score, 2) : '',
                'url' => (new moodle_url('/mod/videoreview/review.php', [
                    'id' => $cm->id,
                    'submissionid' => $allocation->submissionid,
                ]))->out(false),
            ];
        }
    }

    if ($ownsubmission && $released) {
        $reviews = $DB->get_records('videoreview_review', [
            'videoreviewid' => $activity->id,
            'submissionid' => $ownsubmission->id,
            'status' => 'submitted',
        ], 'timesubmitted ASC');
        $reviewindex = 0;
        foreach ($reviews as $review) {
            $reviewindex++;
            $reviewer = $DB->get_record('user', ['id' => $review->reviewerid], 'id,firstname,lastname');
            $comments = [];
            foreach (\mod_videoreview\review_manager::comments((int)$review->id) as $comment) {
                $comments[] = [
                    'timeformatted' => gmdate($comment->timecode >= 3600 ? 'H:i:s' : 'i:s', (int)$comment->timecode),
                    'comment' => format_text($comment->comment, FORMAT_PLAIN),
                ];
            }
            $scores = [];
            $scoreentries = $DB->get_records_sql(
                'SELECT s.*, c.name, c.maxscore, c.criteriontype
                       FROM {videoreview_score} s
                       JOIN {videoreview_criterion} c ON c.id = s.criterionid
                      WHERE s.reviewid = :reviewid
                   ORDER BY c.sortorder, c.id',
                ['reviewid' => $review->id]
            );
            foreach ($scoreentries as $entry) {
                $scores[] = [
                    'name' => format_string($entry->name),
                    'value' => $entry->criteriontype === 'concept' && $entry->concept !== ''
                        ? format_string($entry->concept)
                        : format_float($entry->score, 2) . '/' . format_float($entry->maxscore, 2),
                    'feedback' => format_text($entry->feedback, FORMAT_PLAIN),
                ];
            }
            $data['receivedreviews'][] = [
                'reviewer' => $activity->anonymous && !$canviewall
                    ? get_string('anonymousreviewer', 'videoreview', $reviewindex)
                    : fullname($reviewer),
                'score' => format_float($review->score, 2),
                'overallfeedback' => format_text($review->overallfeedback, FORMAT_PLAIN),
                'comments' => $comments,
                'hascomments' => (bool)$comments,
                'scores' => $scores,
                'hasscores' => (bool)$scores,
                'timesubmitted' => userdate($review->timesubmitted),
                'url' => (new moodle_url('/mod/videoreview/feedback.php', [
                    'id' => $cm->id,
                    'reviewid' => $review->id,
                ]))->out(false),
            ];
        }
    }

    if ($canviewall) {
        $submissions = $DB->get_records('videoreview_submission', ['videoreviewid' => $activity->id], 'timemodified DESC');
        foreach ($submissions as $submission) {
            $author = $DB->get_record('user', ['id' => $submission->userid], 'id,firstname,lastname');
            $data['teachersubmissions'][] = [
                'title' => format_string($submission->title),
                'author' => fullname($author),
                'reviewcount' => $DB->count_records('videoreview_review', [
                    'videoreviewid' => $activity->id,
                    'submissionid' => $submission->id,
                    'status' => 'submitted',
                ]),
                'url' => (new moodle_url('/mod/videoreview/review.php', [
                    'id' => $cm->id,
                    'submissionid' => $submission->id,
                ]))->out(false),
            ];
        }
    }
}

$data['hasallocations'] = !empty($data['allocations']);
$data['hasreceivedreviews'] = !empty($data['receivedreviews']);
$data['hasteachersubmissions'] = !empty($data['teachersubmissions']);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoreview/view', $data);
echo $OUTPUT->footer();
