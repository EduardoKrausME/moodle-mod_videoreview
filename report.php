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
 * Teacher report for Video Peer Review.
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
require_capability('mod/videoreview:viewreport', $context);

$PAGE->set_url('/mod/videoreview/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('report', 'videoreview'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$rows = [];
if ($activity->videomode === 'submission') {
    $users = get_enrolled_users($context, 'mod/videoreview:submitvideo', 0,
        "u.id,u.firstname,u.lastname,u.email,u.picture,u.imagealt,u.firstnamephonetic," .
        "u.lastnamephonetic,u.middlename,u.alternatename",
        "u.lastname,u.firstname");
    foreach ($users as $user) {
        $submission = $DB->get_record('videoreview_submission', [
            'videoreviewid' => $activity->id,
            'userid' => $user->id,
        ]);
        $assigned = $DB->count_records('videoreview_allocation', [
            'videoreviewid' => $activity->id,
            'reviewerid' => $user->id,
        ]);
        $performed = $DB->count_records('videoreview_review', [
            'videoreviewid' => $activity->id,
            'reviewerid' => $user->id,
            'status' => 'submitted',
        ]);
        $received = 0;
        $average = null;
        $watchpercent = null;
        if ($submission) {
            $received = $DB->count_records('videoreview_review', [
                'videoreviewid' => $activity->id,
                'submissionid' => $submission->id,
                'status' => 'submitted',
            ]);
            $average = $DB->get_field_sql(
                '
                    SELECT AVG(score)
                      FROM {videoreview_review}
                     WHERE videoreviewid = :activityid
                       AND submissionid = :submissionid
                       AND status = :status',
                ['activityid' => $activity->id, 'submissionid' => $submission->id, 'status' => 'submitted']
            );
            $watchpercent = $DB->get_field_sql(
                'SELECT AVG(percent)
                       FROM {videoreview_progress}
                       WHERE videoreviewid = :activityid
                         AND submissionid = :submissionid',
                ['activityid' => $activity->id, 'submissionid' => $submission->id]
            );
        }
        $finalgrade = $average === false || $average === null ? null : ((float)$average / 100) * (float)$activity->grade;
        $rows[] = [
            'user' => fullname($user),
            'submission' => $submission ? format_string($submission->title) : get_string('notsubmitted', 'videoreview'),
            'assigned' => $assigned,
            'performed' => $performed,
            'received' => $received,
            'average' => $average === false || $average === null ? '-' : format_float($average, 2) . '%',
            'grade' => $finalgrade === null ? '-' : format_float($finalgrade, 2) . '/' . format_float($activity->grade, 2),
            'watchpercent' => $watchpercent === false || $watchpercent === null ? '-' : format_float($watchpercent, 2) . '%',
        ];
    }
} else {
    $users = get_enrolled_users($context, 'mod/videoreview:review', 0,
        "u.id,u.firstname,u.lastname,u.email,u.picture,u.imagealt,u.firstnamephonetic," .
        "u.lastnamephonetic,u.middlename,u.alternatename",
        "u.lastname,u.firstname");
    foreach ($users as $user) {
        $review = $DB->get_record('videoreview_review', [
            'videoreviewid' => $activity->id,
            'submissionid' => 0,
            'reviewerid' => $user->id,
        ]);
        $progress = $DB->get_record('videoreview_progress', [
            'videoreviewid' => $activity->id,
            'submissionid' => 0,
            'userid' => $user->id,
        ]);
        $rows[] = [
            'user' => fullname($user),
            'submission' => get_string('commonvideo', 'videoreview'),
            'assigned' => '-',
            'performed' => $review && $review->status === 'submitted' ? 1 : 0,
            'received' => '-',
            'average' => $review && $review->status === 'submitted' ? format_float($review->score, 2) . '%' : '-',
            'grade' => '-',
            'watchpercent' => $progress ? format_float($progress->percent, 2) . '%' : '-',
        ];
    }
}

$details = [];
$reviews = $DB->get_records('videoreview_review', [
    'videoreviewid' => $activity->id,
    'status' => 'submitted',
], 'timesubmitted DESC');
foreach ($reviews as $review) {
    $reviewer = $DB->get_record('user', ['id' => $review->reviewerid], 'id,firstname,lastname', MUST_EXIST);
    $authorname = get_string('commonvideo', 'videoreview');
    $videotitle = format_string($activity->name);
    if ($review->submissionid > 0) {
        $submission = $DB->get_record('videoreview_submission', ['id' => $review->submissionid], '*', MUST_EXIST);
        $author = $DB->get_record('user', ['id' => $submission->userid], 'id,firstname,lastname', MUST_EXIST);
        $authorname = fullname($author);
        $videotitle = format_string($submission->title);
    }
    $progress = $DB->get_record('videoreview_progress', [
        'videoreviewid' => $activity->id,
        'submissionid' => $review->submissionid,
        'userid' => $review->reviewerid,
    ]);
    $details[] = [
        'video' => $videotitle,
        'author' => $authorname,
        'reviewer' => fullname($reviewer),
        'score' => format_float($review->score, 2) . '%',
        'comments' => $DB->count_records('videoreview_comment', ['reviewid' => $review->id]),
        'watchpercent' => $progress ? format_float($progress->percent, 2) . '%' : '-',
        'date' => userdate($review->timesubmitted),
    ];
}

$criterionstats = [];
$criteria = $DB->get_records('videoreview_criterion', ['videoreviewid' => $activity->id], 'sortorder,id');
foreach ($criteria as $criterion) {
    $average = $DB->get_field_sql(
        'SELECT AVG(s.score)
               FROM {videoreview_score} s
               JOIN {videoreview_review} r ON r.id = s.reviewid
              WHERE s.criterionid = :criterionid
                AND r.status = :status',
        ['criterionid' => $criterion->id, 'status' => 'submitted']
    );
    $criterionstats[] = [
        'name' => format_string($criterion->name),
        'average' => $average === false || $average === null ? '-' : format_float($average, 2),
        'maxscore' => format_float($criterion->maxscore, 2),
        'type' => get_string('criteriontype' . $criterion->criteriontype, 'videoreview'),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoreview/report', [
    'rows' => $rows,
    'hasrows' => (bool)$rows,
    'details' => $details,
    'hasdetails' => (bool)$details,
    'criterionstats' => $criterionstats,
    'hascriterionstats' => (bool)$criterionstats,
]);
echo $OUTPUT->footer();
