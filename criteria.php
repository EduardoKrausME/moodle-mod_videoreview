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
 * Assessment criteria management.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("{$CFG->libdir}/formslib.php");

$id = required_param('id', PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);

$cm = get_coursemodule_from_id('videoreview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videoreview', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/videoreview:managecriteria', $context);

$PAGE->set_url('/mod/videoreview/criteria.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('criteria', 'videoreview'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($delete) {
    require_sesskey();
    $criterion = $DB->get_record('videoreview_criterion', [
        'id' => $delete,
        'videoreviewid' => $activity->id,
    ], '*', MUST_EXIST);
    $DB->delete_records('videoreview_score', ['criterionid' => $criterion->id]);
    $DB->delete_records('videoreview_criterion', ['id' => $criterion->id]);
    \mod_videoreview\review_manager::recalculate_activity_scores($activity);
    redirect($PAGE->url, get_string('criteriondeleted', 'videoreview'));
}

$form = new \mod_videoreview\form\criterion_form();
if ($form->is_cancelled()) {
    redirect($PAGE->url);
}
if ($data = $form->get_data()) {
    $now = time();
    $record = (object)[
        'videoreviewid' => $activity->id,
        'name' => $data->name,
        'description' => $data->description,
        'criteriontype' => $data->criteriontype,
        'maxscore' => $data->maxscore,
        'conceptoptions' => $data->criteriontype === 'concept' ? $data->conceptoptions : '',
        'required' => $data->required,
        'sortorder' => $data->sortorder,
        'timemodified' => $now,
    ];
    if ($data->criterionid) {
        $existing = $DB->get_record('videoreview_criterion', [
            'id' => $data->criterionid,
            'videoreviewid' => $activity->id,
        ], '*', MUST_EXIST);
        $record->id = $existing->id;
        $DB->update_record('videoreview_criterion', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('videoreview_criterion', $record);
    }
    \mod_videoreview\review_manager::recalculate_activity_scores($activity);
    redirect($PAGE->url, get_string('criterionsaved', 'videoreview'));
}

if ($edit) {
    $criterion = $DB->get_record('videoreview_criterion', [
        'id' => $edit,
        'videoreviewid' => $activity->id,
    ], '*', MUST_EXIST);
    $criterion->criterionid = $criterion->id;
    $form->set_data($criterion);
}

$criteria = $DB->get_records('videoreview_criterion', ['videoreviewid' => $activity->id], 'sortorder ASC, id ASC');
$list = [];
foreach ($criteria as $criterion) {
    $list[] = [
        'name' => format_string($criterion->name),
        'description' => format_text($criterion->description, FORMAT_PLAIN),
        'type' => get_string('criteriontype' . $criterion->criteriontype, 'videoreview'),
        'maxscore' => format_float($criterion->maxscore, 2),
        'required' => (bool)$criterion->required,
        'editurl' => (new moodle_url('/mod/videoreview/criteria.php', ['id' => $cm->id, 'edit' => $criterion->id]))->out(false),
        'deleteurl' => (new moodle_url('/mod/videoreview/criteria.php', [
            'id' => $cm->id,
            'delete' => $criterion->id,
            'sesskey' => sesskey(),
        ]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoreview/criteria', [
    'criteria' => $list,
    'hascriteria' => (bool)$list,
]);
echo $OUTPUT->heading($edit ? get_string('editcriterion', 'videoreview') : get_string('addcriterion', 'videoreview'), 3);
echo $form->render();
echo $OUTPUT->footer();
