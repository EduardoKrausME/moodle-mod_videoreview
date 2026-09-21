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
 * Core callbacks for Video Peer Review.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declares Moodle features supported by the activity.
 *
 * @param string $feature Feature constant.
 * @return bool|null
 */
function videoreview_supports($feature) {
    switch ($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

/**
 * Adds an activity instance.
 *
 * @param object $data Form data.
 * @param moodleform|null $mform Form instance.
 * @return int
 */
function videoreview_add_instance($data, $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();
    $data->releasedate = empty($data->releasedate_enabled) ? 0 : (int)$data->releasedate;
    $id = $DB->insert_record('videoreview', $data);
    $data->id = $id;

    if (!empty($data->coursemodule)) {
        $context = context_module::instance($data->coursemodule);
        if (isset($data->commonvideo)) {
            file_save_draft_area_files(
                $data->commonvideo,
                $context->id,
                'mod_videoreview',
                'commonvideo',
                0,
                ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video']]
            );
        }
    }
    videoreview_grade_item_update($data);
    return $id;
}

/**
 * Updates an activity instance.
 *
 * @param object $data Form data.
 * @param moodleform|null $mform Form instance.
 * @return bool
 */
function videoreview_update_instance($data, $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $data->releasedate = empty($data->releasedate_enabled) ? 0 : (int)$data->releasedate;
    $result = $DB->update_record('videoreview', $data);

    if (!empty($data->coursemodule) && isset($data->commonvideo)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files(
            $data->commonvideo,
            $context->id,
            'mod_videoreview',
            'commonvideo',
            0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video']]
        );
    }
    videoreview_grade_item_update($data);
    \mod_videoreview\grade_manager::update_all($data);
    return $result;
}

/**
 * Deletes an activity instance and its related data.
 *
 * @param int $id Activity instance id.
 * @return bool
 */
function videoreview_delete_instance($id): bool {
    global $DB;

    $activity = $DB->get_record('videoreview', ['id' => $id]);
    if (!$activity) {
        return false;
    }

    $cm = get_coursemodule_from_instance('videoreview', $id, $activity->course, false, IGNORE_MISSING);
    if ($cm) {
        $context = context_module::instance($cm->id);
        get_file_storage()->delete_area_files($context->id, 'mod_videoreview');
    }

    $reviews = $DB->get_records('videoreview_review', ['videoreviewid' => $id], '', 'id');
    if ($reviews) {
        $reviewids = array_keys($reviews);
        [$insql, $params] = $DB->get_in_or_equal($reviewids, SQL_PARAMS_NAMED, 'review');
        $DB->delete_records_select('videoreview_comment', "reviewid {$insql}", $params);
        $DB->delete_records_select('videoreview_score', "reviewid {$insql}", $params);
    }
    $DB->delete_records('videoreview_review', ['videoreviewid' => $id]);
    $DB->delete_records('videoreview_allocation', ['videoreviewid' => $id]);
    $DB->delete_records('videoreview_progress', ['videoreviewid' => $id]);
    $DB->delete_records('videoreview_criterion', ['videoreviewid' => $id]);
    $DB->delete_records('videoreview_submission', ['videoreviewid' => $id]);
    $DB->delete_records('videoreview', ['id' => $id]);
    videoreview_grade_item_delete($activity);
    return true;
}

/**
 * Creates or updates the grade item.
 *
 * @param object $activity Activity record.
 * @param array|null $grades Grade data.
 * @return int
 */
function videoreview_grade_item_update($activity, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $activity->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademin' => 0,
        'grademax' => max(1, (float)$activity->grade),
    ];
    if ($activity->videomode !== 'submission') {
        $params['hidden'] = 1;
    }
    return grade_update(
        'mod/videoreview',
        $activity->course,
        'mod',
        'videoreview',
        $activity->id,
        0,
        $grades,
        $params
    );
}

/**
 * Deletes the grade item.
 *
 * @param object $activity Activity record.
 * @return int
 */
function videoreview_grade_item_delete($activity): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update(
        'mod/videoreview',
        $activity->course,
        'mod',
        'videoreview',
        $activity->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Updates gradebook grades.
 *
 * @param object $activity Activity record.
 * @param int $userid Optional user id.
 * @param bool $nullifnone Whether to explicitly send null when no grade exists.
 * @return void
 */
function videoreview_update_grades($activity, $userid = 0, $nullifnone = true): void {
    global $DB;

    if ($activity->videomode !== 'submission') {
        videoreview_grade_item_update($activity);
        return;
    }
    if ($userid) {
        $submission = $DB->get_record('videoreview_submission', [
            'videoreviewid' => $activity->id,
            'userid' => $userid,
        ]);
        if ($submission) {
            \mod_videoreview\grade_manager::update_submission_grade($activity, (int)$submission->id);
        } else if ($nullifnone) {
            videoreview_grade_item_update($activity, ['userid' => $userid, 'rawgrade' => null]);
        }
        return;
    }
    \mod_videoreview\grade_manager::update_all($activity);
}

/**
 * Serves uploaded common and student video files.
 *
 * @param stdClass $course Course.
 * @param stdClass $cm Course module.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args File path args.
 * @param bool $forcedownload Force download.
 * @param array $options Options.
 * @return bool
 */
function videoreview_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): bool {
    global $DB, $USER;

    if ($context->contextlevel !== CONTEXT_MODULE || !in_array($filearea, ['commonvideo', 'submissionvideo'], true)) {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/videoreview:view', $context);

    $itemid = (int)array_shift($args);
    if ($filearea === 'submissionvideo') {
        $submission = $DB->get_record('videoreview_submission', [
            'id' => $itemid,
            'videoreviewid' => $cm->instance,
        ]);
        if (!$submission) {
            return false;
        }
        $activity = $DB->get_record('videoreview', ['id' => $cm->instance], '*', MUST_EXIST);
        $isowner = ((int)$submission->userid === (int)$USER->id);
        $canviewall = has_capability('mod/videoreview:viewall', $context);
        $isallocated = $DB->record_exists('videoreview_allocation', [
            'videoreviewid' => $activity->id,
            'submissionid' => $submission->id,
            'reviewerid' => $USER->id,
        ]);
        if (!$isowner && !$canviewall && !$isallocated) {
            return false;
        }
    }

    $relativepath = '/' . implode('/', $args);
    $fullpath = "/{$context->id}/mod_videoreview/{$filearea}/{$itemid}{$relativepath}";
    $file = get_file_storage()->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, false, $options);
    return true;
}

/**
 * Returns active completion rule descriptions for activity settings.
 *
 * @param cm_info|stdClass $cm Course module.
 * @return array
 */
function videoreview_get_completion_active_rule_descriptions($cm): array {
    $rules = [];
    if (!empty($cm->customdata['completionrequiresubmission'])) {
        $rules[] = get_string('completiondetail:submission', 'videoreview');
    }
    if (!empty($cm->customdata['completionrequirereviews'])) {
        $rules[] = get_string('completiondetail:reviews', 'videoreview');
    }
    return $rules;
}

/**
 * Supplies custom data used by completion UI.
 *
 * @param cm_info $coursemodule Course module info.
 * @return cached_cm_info
 */
function videoreview_get_coursemodule_info($coursemodule) {
    global $DB;

    $result = new cached_cm_info();
    $activity = $DB->get_record('videoreview', ['id' => $coursemodule->instance],
        'id,name,intro,introformat,completionrequiresubmission,completionrequirereviews');
    if (!$activity) {
        return null;
    }
    $result->name = $activity->name;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('videoreview', $activity, $coursemodule->id, false);
    }
    $result->customdata = [
        'completionrequiresubmission' => (bool)$activity->completionrequiresubmission,
        'completionrequirereviews' => (bool)$activity->completionrequirereviews,
    ];
    return $result;
}

/**
 * Legacy completion callback for older completion consumers.
 *
 * @param object $course Course.
 * @param object $cm Course module.
 * @param int $userid User id.
 * @param bool $type Expected state.
 * @return bool
 */
function videoreview_get_completion_state($course, $cm, int $userid, bool $type): bool {
    global $DB;

    $activity = $DB->get_record('videoreview', ['id' => $cm->instance], '*', MUST_EXIST);
    if ($activity->completionrequiresubmission && $activity->videomode === 'submission' && !$DB->record_exists(
            'videoreview_submission',
            ['videoreviewid' => $activity->id, 'userid' => $userid, 'status' => 'submitted']
        )) {
        return false;
    }
    if ($activity->completionrequirereviews) {
        if ($activity->videomode === 'common') {
            return $DB->record_exists('videoreview_review', [
                'videoreviewid' => $activity->id,
                'submissionid' => 0,
                'reviewerid' => $userid,
                'status' => 'submitted',
            ]);
        }
        return \mod_videoreview\allocation_manager::user_completed((int)$activity->id, $userid);
    }
    return true;
}
