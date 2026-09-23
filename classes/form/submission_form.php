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

namespace mod_videoreview\form;

/**
 * Student video submission form.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_form extends \moodleform {
    /**
     * Defines submission fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'title', get_string('submissiontitle', 'videoreview'), ['size' => 64]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('select', 'videosource', get_string('videosource', 'videoreview'), [
            'upload' => get_string('sourceupload', 'videoreview'),
            'url' => get_string('sourceurl', 'videoreview'),
        ]);
        $mform->setDefault('videosource', 'upload');

        $mform->addElement('filemanager', 'submissionvideo', get_string('submissionvideo', 'videoreview'), null, [
            'subdirs' => 0,
            'accepted_types' => ['video'],
        ]);
        $mform->hideIf('submissionvideo', 'videosource', 'neq', 'upload');

        $mform->addElement('url', 'videourl', get_string('videourl', 'videoreview'), ['size' => 80]);
        $mform->setType('videourl', PARAM_URL);
        $mform->hideIf('videourl', 'videosource', 'neq', 'url');
        $mform->addHelpButton('videourl', 'videourl', 'videoreview');
        $this->add_action_buttons(true, get_string('savesubmission', 'videoreview'));
    }

    /**
     * Validates source data.
     *
     * @param array $data Submitted data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (($data['videosource'] ?? '') === 'url' && empty($data['videourl'])) {
            $errors['videourl'] = get_string('required');
        }
        if (($data['videosource'] ?? '') === 'upload') {
            $draftid = (int)($data['submissionvideo'] ?? 0);
            $info = $draftid > 0 ? file_get_draft_area_info($draftid) : ['filecount' => 0];
            if (empty($info['filecount'])) {
                $errors['submissionvideo'] = get_string('submissionvideorequired', 'videoreview');
            }
        }
        foreach (['submissionvideo'] as $field) {
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
}
