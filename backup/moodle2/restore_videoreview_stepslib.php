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
 * Restore structure for Video Peer Review.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores Video Peer Review records and mappings.
 */
class restore_videoreview_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines paths processed during restore.
     *
     * @return array
     */
    protected function define_structure(): array {
        $paths = [];
        $paths[] = new restore_path_element('videoreview', '/activity/videoreview');
        $paths[] = new restore_path_element('videoreview_criterion', '/activity/videoreview/criteria/criterion');
        $paths[] = new restore_path_element('videoreview_submission', '/activity/videoreview/submissions/submission');
        $paths[] = new restore_path_element('videoreview_allocation', '/activity/videoreview/allocations/allocation');
        $paths[] = new restore_path_element('videoreview_review', '/activity/videoreview/reviews/review');
        $paths[] = new restore_path_element('videoreview_score', '/activity/videoreview/reviews/review/scores/score');
        $paths[] = new restore_path_element('videoreview_comment', '/activity/videoreview/reviews/review/comments/comment');
        $paths[] = new restore_path_element('videoreview_progress', '/activity/videoreview/progressrecords/progress');
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the main activity record.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videoreview(array $data): void {
        global $DB;
        $record = (object)$data;
        $oldid = $record->id;
        $record->course = $this->get_courseid();
        $record->id = $DB->insert_record('videoreview', $record);
        $this->apply_activity_instance($record->id);
        $this->set_mapping('videoreview', $oldid, $record->id);
    }

    /**
     * Restores a criterion.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videoreview_criterion(array $data): void {
        global $DB;
        $record = (object)$data;
        $oldid = $record->id;
        $record->videoreviewid = $this->get_new_parentid('videoreview');
        $record->id = $DB->insert_record('videoreview_criterion', $record);
        $this->set_mapping('videoreview_criterion', $oldid, $record->id);
    }

    /**
     * Restores a submission.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videoreview_submission(array $data): void {
        global $DB;
        $record = (object)$data;
        $oldid = $record->id;
        $record->videoreviewid = $this->get_new_parentid('videoreview');
        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        $record->id = $DB->insert_record('videoreview_submission', $record);
        $this->set_mapping('videoreview_submission', $oldid, $record->id, true);
    }

    /**
     * Restores a peer allocation.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videoreview_allocation(array $data): void {
        global $DB;
        $record = (object)$data;
        $record->videoreviewid = $this->get_new_parentid('videoreview');
        $record->submissionid = $this->get_mappingid('videoreview_submission', $record->submissionid, 0);
        $record->reviewerid = $this->get_mappingid('user', $record->reviewerid, 0);
        if ($record->submissionid && $record->reviewerid) {
            $DB->insert_record('videoreview_allocation', $record);
        }
    }

    /**
     * Restores a review.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videoreview_review(array $data): void {
        global $DB;
        $record = (object)$data;
        $oldid = $record->id;
        $record->videoreviewid = $this->get_new_parentid('videoreview');
        if ((int)$record->submissionid > 0) {
            $record->submissionid = $this->get_mappingid('videoreview_submission', $record->submissionid, 0);
        }
        $record->reviewerid = $this->get_mappingid('user', $record->reviewerid, 0);
        if (!$record->reviewerid) {
            return;
        }
        $record->id = $DB->insert_record('videoreview_review', $record);
        $this->set_mapping('videoreview_review', $oldid, $record->id);
    }

    /**
     * Restores one criterion score.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videoreview_score(array $data): void {
        global $DB;
        $record = (object)$data;
        $record->reviewid = $this->get_new_parentid('videoreview_review');
        $record->criterionid = $this->get_mappingid('videoreview_criterion', $record->criterionid, 0);
        if ($record->criterionid) {
            $DB->insert_record('videoreview_score', $record);
        }
    }

    /**
     * Restores one timestamped comment.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videoreview_comment(array $data): void {
        global $DB;
        $record = (object)$data;
        $record->reviewid = $this->get_new_parentid('videoreview_review');
        $DB->insert_record('videoreview_comment', $record);
    }

    /**
     * Restores one viewing progress record.
     *
     * @param array $data Backup data.
     * @return void
     */
    protected function process_videoreview_progress(array $data): void {
        global $DB;
        $record = (object)$data;
        $record->videoreviewid = $this->get_new_parentid('videoreview');
        if ((int)$record->submissionid > 0) {
            $record->submissionid = $this->get_mappingid('videoreview_submission', $record->submissionid, 0);
        }
        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        if ($record->userid) {
            $DB->insert_record('videoreview_progress', $record);
        }
    }

    /**
     * Restores related files after data mappings exist.
     *
     * @return void
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_videoreview', 'intro', null);
        $this->add_related_files('mod_videoreview', 'commonvideo', null);
        $this->add_related_files('mod_videoreview', 'submissionvideo', 'videoreview_submission');
    }
}
