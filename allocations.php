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
 * Peer allocation management page.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$rebuild = optional_param('rebuild', 0, PARAM_BOOL);
$cm = get_coursemodule_from_id('videoreview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('videoreview', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/videoreview:manageallocations', $context);

if ($activity->videomode !== 'submission') {
    throw new moodle_exception('submissionmoderequired', 'videoreview');
}

$PAGE->set_url('/mod/videoreview/allocations.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('allocations', 'videoreview'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($rebuild) {
    require_sesskey();
    $created = \mod_videoreview\allocation_manager::rebuild($activity);
    redirect($PAGE->url, get_string('allocationscreated', 'videoreview', $created));
}

$sql = "SELECT a.id, a.status, a.submissionid, a.reviewerid,
               s.title, s.userid AS authorid,
               au.firstname AS authorfirstname, au.lastname AS authorlastname,
               ru.firstname AS reviewerfirstname, ru.lastname AS reviewerlastname
          FROM {videoreview_allocation} a
          JOIN {videoreview_submission} s ON s.id = a.submissionid
          JOIN {user} au ON au.id = s.userid
          JOIN {user} ru ON ru.id = a.reviewerid
         WHERE a.videoreviewid = :activityid
      ORDER BY ru.lastname, ru.firstname, au.lastname, au.firstname";
$records = $DB->get_records_sql($sql, ['activityid' => $activity->id]);
$rows = [];
foreach ($records as $record) {
    $rows[] = [
        'reviewer' => fullname((object)['firstname' => $record->reviewerfirstname, 'lastname' => $record->reviewerlastname]),
        'author' => fullname((object)['firstname' => $record->authorfirstname, 'lastname' => $record->authorlastname]),
        'title' => format_string($record->title),
        'completed' => $record->status === 'completed',
        'status' => get_string('allocationstatus' . $record->status, 'videoreview'),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_videoreview/allocations', [
    'rows' => $rows,
    'hasrows' => (bool)$rows,
    'reviewcount' => (int)$activity->peerreviewcount,
    'rebuildurl' => (new moodle_url('/mod/videoreview/allocations.php', [
        'id' => $cm->id,
        'rebuild' => 1,
        'sesskey' => sesskey(),
    ]))->out(false),
]);
echo $OUTPUT->footer();
