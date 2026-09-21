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
 * Student video submission page.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("{$CFG->libdir}/formslib.php");

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videoreview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videoreview', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/videoreview:submitvideo', $context);
if ($activity->videomode !== 'submission') {
    throw new moodle_exception('submissionmoderequired', 'videoreview');
}

$PAGE->set_url('/mod/videoreview/submission.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('mysubmission', 'videoreview'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$submission = $DB->get_record('videoreview_submission', [
    'videoreviewid' => $activity->id,
    'userid' => $USER->id,
]);

$draftid = file_get_submitted_draft_itemid('submissionvideo');
if ($submission) {
    file_prepare_draft_area(
        $draftid,
        $context->id,
        'mod_videoreview',
        'submissionvideo',
        $submission->id,
        ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video']]
    );
}

$form = new \mod_videoreview\form\submission_form();
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/videoreview/view.php', ['id' => $cm->id]));
}

if ($data = $form->get_data()) {
    $now = time();
    if ($submission) {
        $submission->title = $data->title;
        $submission->videosource = $data->videosource;
        $submission->videourl = $data->videosource === 'url' ? $data->videourl : '';
        $submission->status = 'submitted';
        $submission->timemodified = $now;
        $DB->update_record('videoreview_submission', $submission);
    } else {
        $submission = (object)[
            'videoreviewid' => $activity->id,
            'userid' => $USER->id,
            'title' => $data->title,
            'videosource' => $data->videosource,
            'videourl' => $data->videosource === 'url' ? $data->videourl : '',
            'status' => 'submitted',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $submission->id = $DB->insert_record('videoreview_submission', $submission);
    }

    file_save_draft_area_files(
        $data->submissionvideo,
        $context->id,
        'mod_videoreview',
        'submissionvideo',
        $submission->id,
        ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video']]
    );
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm)) {
        $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
    }
    redirect(
        new moodle_url('/mod/videoreview/view.php', ['id' => $cm->id]),
        get_string('submissionsaved', 'videoreview'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$formdata = $submission ? clone $submission : (object)['videosource' => 'upload', 'title' => '', 'videourl' => ''];
$formdata->submissionvideo = $draftid;
$form->set_data($formdata);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mysubmission', 'videoreview'));
echo $form->render();
echo $OUTPUT->footer();
