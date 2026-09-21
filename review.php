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
 * Video review workspace.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$submissionid = optional_param('submissionid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('videoreview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videoreview', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videoreview:view', $context);
\mod_videoreview\review_manager::require_review_access($activity, $submissionid, $USER->id, $context);

$submission = null;
if ($activity->videomode === 'submission') {
    $submission = $DB->get_record('videoreview_submission', [
        'id' => $submissionid,
        'videoreviewid' => $activity->id,
        'status' => 'submitted',
    ], '*', MUST_EXIST);
    $player = \mod_videoreview\player::for_submission($submission, $context);
    $videotitle = $submission->title;
} else {
    $submissionid = 0;
    $player = \mod_videoreview\player::for_activity($activity, $context);
    $videotitle = $activity->name;
}

$player['ishtml5'] = $player['type'] === 'html5';
$player['isyoutube'] = $player['type'] === 'youtube';
$player['isvimeo'] = $player['type'] === 'vimeo';
$player['ismissing'] = $player['type'] === 'missing';

$PAGE->set_url('/mod/videoreview/review.php', ['id' => $cm->id, 'submissionid' => $submissionid]);
$PAGE->set_title(get_string('reviewvideo', 'videoreview'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$review = \mod_videoreview\review_manager::get_or_create((int)$activity->id, $submissionid, (int)$USER->id);
$criteria = $DB->get_records('videoreview_criterion', ['videoreviewid' => $activity->id], 'sortorder ASC, id ASC');

if (data_submitted() && confirm_sesskey() && optional_param('submitreview', 0, PARAM_BOOL)) {
    $reviewdata = ['overallfeedback' => optional_param('overallfeedback', '', PARAM_TEXT)];
    foreach ($criteria as $criterion) {
        $reviewdata['criterion_' . $criterion->id] = optional_param('criterion_' . $criterion->id, '', PARAM_RAW_TRIMMED);
        $reviewdata['feedback_' . $criterion->id] = optional_param('feedback_' . $criterion->id, '', PARAM_TEXT);
    }
    \mod_videoreview\review_manager::submit($activity, $review, $reviewdata);
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm)) {
        $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
    }
    redirect($PAGE->url, get_string('reviewsubmitted', 'videoreview'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$storedscores = $DB->get_records('videoreview_score', ['reviewid' => $review->id], '', 'criterionid,id,score,concept,feedback');
$criteriondata = [];
foreach ($criteria as $criterion) {
    $stored = $storedscores[$criterion->id] ?? null;
    $item = [
        'id' => (int)$criterion->id,
        'name' => format_string($criterion->name),
        'description' => format_text($criterion->description, FORMAT_PLAIN),
        'required' => (bool)$criterion->required,
        'isscore' => $criterion->criteriontype === 'score',
        'isconcept' => $criterion->criteriontype === 'concept',
        'maxscore' => format_float($criterion->maxscore, 2),
        'scorevalue' => $stored ? format_float($stored->score, 2) : '',
        'feedback' => $stored ? (string)$stored->feedback : '',
        'options' => [],
    ];
    if ($criterion->criteriontype === 'concept') {
        foreach (\mod_videoreview\review_manager::concept_options($criterion) as $label => $score) {
            $item['options'][] = [
                'value' => $label,
                'label' => format_string($label) . ' (' . format_float($score, 2) . '/' .
                    format_float($criterion->maxscore, 2) . ')',
                'selected' => $stored && $stored->concept === $label,
            ];
        }
    }
    $criteriondata[] = $item;
}

$comments = [];
foreach (\mod_videoreview\review_manager::comments((int)$review->id) as $comment) {
    $comments[] = [
        'id' => (int)$comment->id,
        'timecode' => (float)$comment->timecode,
        'timeformatted' => gmdate($comment->timecode >= 3600 ? 'H:i:s' : 'i:s', (int)$comment->timecode),
        'comment' => format_text($comment->comment, FORMAT_PLAIN),
    ];
}

$progress = $DB->get_record('videoreview_progress', [
    'videoreviewid' => $activity->id,
    'submissionid' => $submissionid,
    'userid' => $USER->id,
]);
$config = [
    'cmid' => (int)$cm->id,
    'submissionid' => $submissionid,
    'player' => $player,
    'lastposition' => $progress ? (float)$progress->lastposition : 0,
    'segments' => $progress ? (json_decode((string)$progress->watchedsegments, true) ?: []) : [],
    'track' => true,
];

$PAGE->requires->js_call_amd('mod_videoreview/review', 'init');
$PAGE->requires->strings_for_js(['addcomment', 'commentplaceholder', 'deletecomment', 'trackingerror'], 'videoreview');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoreview/review', [
    'videotitle' => format_string($videotitle),
    'player' => $player,
    'configjson' => json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
    'comments' => $comments,
    'hascomments' => (bool)$comments,
    'criteria' => $criteriondata,
    'hascriteria' => (bool)$criteriondata,
    'overallfeedback' => (string)$review->overallfeedback,
    'submitted' => $review->status === 'submitted',
    'score' => format_float($review->score, 2),
    'sesskey' => sesskey(),
]);
echo $OUTPUT->footer();
