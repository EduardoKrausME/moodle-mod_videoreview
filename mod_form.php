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
 * Activity settings form for Video Peer Review.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Main module settings form.
 */
class mod_videoreview_mod_form extends moodleform_mod {
    /**
     * Defines the form.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('videoreviewname', 'videoreview'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $mform->addElement('html', '<h3>' . get_string('videoconfiguration', 'videoreview') . '</h3>');
        $mform->addElement('select', 'videomode', get_string('videomode', 'videoreview'), [
            'common' => get_string('videomodecommon', 'videoreview'),
            'submission' => get_string('videomodesubmission', 'videoreview'),
        ]);
        $mform->setDefault('videomode', 'submission');

        $mform->addElement('select', 'commonvideosource', get_string('videosource', 'videoreview'), [
            'upload' => get_string('sourceupload', 'videoreview'),
            'url' => get_string('sourceurl', 'videoreview'),
        ]);
        $mform->setDefault('commonvideosource', 'upload');
        $mform->hideIf('commonvideosource', 'videomode', 'neq', 'common');

        $fileoptions = ['subdirs' => 0, 'accepted_types' => ['video']];
        $mform->addElement('filemanager', 'commonvideo', get_string('commonvideo', 'videoreview'), null, $fileoptions);
        $mform->hideIf('commonvideo', 'videomode', 'neq', 'common');
        $mform->hideIf('commonvideo', 'commonvideosource', 'neq', 'upload');

        $mform->addElement('url', 'commonvideourl', get_string('videourl', 'videoreview'), ['size' => 80]);
        $mform->setType('commonvideourl', PARAM_URL);
        $mform->hideIf('commonvideourl', 'videomode', 'neq', 'common');
        $mform->hideIf('commonvideourl', 'commonvideosource', 'neq', 'url');
        $mform->addHelpButton('commonvideourl', 'videourl', 'videoreview');

        $mform->addElement('html', '<h3>' . get_string('peerreviewsettings', 'videoreview') . '</h3>');
        $mform->addElement('text', 'peerreviewcount', get_string('peerreviewcount', 'videoreview'), ['size' => 5]);
        $mform->setType('peerreviewcount', PARAM_INT);
        $mform->setDefault('peerreviewcount', 2);
        $mform->hideIf('peerreviewcount', 'videomode', 'neq', 'submission');
        $mform->addHelpButton('peerreviewcount', 'peerreviewcount', 'videoreview');

        $mform->addElement('selectyesno', 'anonymous', get_string('anonymousreviews', 'videoreview'));
        $mform->setDefault('anonymous', 0);
        $mform->addHelpButton('anonymous', 'anonymousreviews', 'videoreview');

        $mform->addElement('date_time_selector', 'releasedate', get_string('releasedate', 'videoreview'), ['optional' => true]);
        $mform->addHelpButton('releasedate', 'releasedate', 'videoreview');

        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 100);
        $mform->hideIf('grade', 'videomode', 'eq', 'common');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds custom completion controls.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $submissionfield = $this->completion_field('completionrequiresubmission');
        $reviewsfield = $this->completion_field('completionrequirereviews');

        $mform->addElement('checkbox', $submissionfield, '', get_string('completionrequiresubmission', 'videoreview'));
        $mform->hideIf($submissionfield, 'videomode', 'neq', 'submission');
        $mform->addElement('checkbox', $reviewsfield, '', get_string('completionrequirereviews', 'videoreview'));
        return [$submissionfield, $reviewsfield];
    }

    /**
     * Returns whether any custom completion rule is enabled.
     *
     * @param object $data Form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data[$this->completion_field('completionrequiresubmission')]) ||
            !empty($data[$this->completion_field('completionrequirereviews')]);
    }

    /**
     * Prepares existing values and common video draft files.
     *
     * @param array $defaultvalues Existing form values.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        foreach (['completionrequiresubmission', 'completionrequirereviews'] as $field) {
            if (array_key_exists($field, $defaultvalues)) {
                $defaultvalues[$this->completion_field($field)] = $defaultvalues[$field];
            }
        }
        if (!empty($defaultvalues['releasedate'])) {
            $defaultvalues['releasedate_enabled'] = 1;
        }
        if (empty($this->current->instance)) {
            return;
        }
        $draftid = file_get_submitted_draft_itemid('commonvideo');
        file_prepare_draft_area(
            $draftid,
            $this->context->id,
            'mod_videoreview',
            'commonvideo',
            0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['video']]
        );
        $defaultvalues['commonvideo'] = $draftid;
    }

    /**
     * Normalises custom completion fields after form submission.
     *
     * @return object|false
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }
        foreach (['completionrequiresubmission', 'completionrequirereviews'] as $field) {
            $suffixed = $this->completion_field($field);
            $data->{$field} = empty($data->{$suffixed}) ? 0 : 1;
            unset($data->{$suffixed});
        }
        return $data;
    }

    /**
     * Performs server-side validation.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (($data['videomode'] ?? '') === 'common') {
            if (($data['commonvideosource'] ?? '') === 'url' && empty($data['commonvideourl'])) {
                $errors['commonvideourl'] = get_string('required');
            }
            if (($data['commonvideosource'] ?? '') === 'upload') {
                $draftid = (int)($data['commonvideo'] ?? 0);
                $info = $draftid > 0 ? file_get_draft_area_info($draftid) : ['filecount' => 0];
                if (empty($info['filecount'])) {
                    $errors['commonvideo'] = get_string('commonvideorequired', 'videoreview');
                }
            }
        }
        if (isset($data['peerreviewcount']) && ((int)$data['peerreviewcount'] < 0 || (int)$data['peerreviewcount'] > 50)) {
            $errors['peerreviewcount'] = get_string('invalidpeerreviewcount', 'videoreview');
        }
        foreach (['commonvideo'] as $field) {
            $draftid = (int)($data[$field] ?? 0);
            if ($draftid > 0) {
                $draftinfo = file_get_draft_area_info($draftid);
                if ((int)$draftinfo['filecount'] > 1) {
                    $errors[$field] = get_string('errormaxfiles', 'videoreview');
                }
            }
        }
        return $errors;
    }

    /**
     * Returns a namespaced custom-completion field name.
     *
     * @param string $field Field name.
     * @return string
     */
    private function completion_field(string $field): string {
        return $field . '_videoreview';
    }
}
