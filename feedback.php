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
 * Read-only received-review viewer with timestamp seeking.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$reviewid = required_param('reviewid', PARAM_INT);
$cm = get_coursemodule_from_id('videoreview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videoreview', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/videoreview:view', $context);

$review = $DB->get_record('videoreview_review', [
    'id' => $reviewid,
    'videoreviewid' => $activity->id,
    'status' => 'submitted',
], '*', MUST_EXIST);
if ($review->submissionid <= 0) {
    throw new moodle_exception('invalidsubmission', 'videoreview');
}
$submission = $DB->get_record('videoreview_submission', [
    'id' => $review->submissionid,
    'videoreviewid' => $activity->id,
], '*', MUST_EXIST);
$canviewall = has_capability('mod/videoreview:viewall', $context);
if ((int)$submission->userid !== (int)$USER->id && !$canviewall) {
    throw new required_capability_exception($context, 'mod/videoreview:viewall', 'nopermissions', '');
}
if (!$canviewall && !empty($activity->releasedate) && time() < (int)$activity->releasedate) {
    throw new moodle_exception('reviewsnotreleased', 'videoreview');
}

$player = \mod_videoreview\player::for_submission($submission, $context);
$player['ishtml5'] = $player['type'] === 'html5';
$player['isyoutube'] = $player['type'] === 'youtube';
$player['isvimeo'] = $player['type'] === 'vimeo';
$player['ismissing'] = $player['type'] === 'missing';

$comments = [];
foreach (\mod_videoreview\review_manager::comments((int)$review->id) as $comment) {
    $comments[] = [
        'id' => (int)$comment->id,
        'timecode' => (float)$comment->timecode,
        'timeformatted' => gmdate($comment->timecode >= 3600 ? 'H:i:s' : 'i:s', (int)$comment->timecode),
        'comment' => format_text($comment->comment, FORMAT_PLAIN),
    ];
}

$scores = [];
$entries = $DB->get_records_sql(
    'SELECT s.*, c.name, c.maxscore, c.criteriontype
           FROM {videoreview_score} s
           JOIN {videoreview_criterion} c ON c.id = s.criterionid
          WHERE s.reviewid = :reviewid
       ORDER BY c.sortorder, c.id',
    ['reviewid' => $review->id]
);
foreach ($entries as $entry) {
    $scores[] = [
        'name' => format_string($entry->name),
        'value' => $entry->criteriontype === 'concept' && $entry->concept !== ''
            ? format_string($entry->concept)
            : format_float($entry->score, 2) . '/' . format_float($entry->maxscore, 2),
        'feedback' => format_text($entry->feedback, FORMAT_PLAIN),
    ];
}
$reviewer = $DB->get_record('user', ['id' => $review->reviewerid], 'id,firstname,lastname', MUST_EXIST);
$reviewername = $activity->anonymous && !$canviewall
    ? get_string('anonymousreviewer', 'videoreview', 1)
    : fullname($reviewer);
$config = [
    'cmid' => (int)$cm->id,
    'submissionid' => (int)$submission->id,
    'player' => $player,
    'lastposition' => 0,
    'segments' => [],
    'track' => false,
];

$PAGE->set_url('/mod/videoreview/feedback.php', ['id' => $cm->id, 'reviewid' => $review->id]);
$PAGE->set_title(get_string('receivedreview', 'videoreview'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('mod_videoreview/review', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoreview/feedback', [
    'title' => format_string($submission->title),
    'reviewer' => $reviewername,
    'score' => format_float($review->score, 2),
    'overallfeedback' => format_text($review->overallfeedback, FORMAT_PLAIN),
    'comments' => $comments,
    'hascomments' => (bool)$comments,
    'scores' => $scores,
    'hasscores' => (bool)$scores,
    'player' => $player,
    'configjson' => json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
]);
echo $OUTPUT->footer();
