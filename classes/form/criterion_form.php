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
 * Teacher form for one assessment criterion.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class criterion_form extends \moodleform {
    /**
     * Defines criterion fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'criterionid', 0);
        $mform->setType('criterionid', PARAM_INT);
        $mform->addElement('text', 'name', get_string('criterionname', 'videoreview'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('textarea', 'description',
            get_string('criteriondescription', 'videoreview'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement('select', 'criteriontype', get_string('criteriontype', 'videoreview'), [
            'score' => get_string('criteriontypescore', 'videoreview'),
            'concept' => get_string('criteriontypeconcept', 'videoreview'),
        ]);
        $mform->addElement('text', 'maxscore', get_string('maxscore', 'videoreview'), ['size' => 8]);
        $mform->setType('maxscore', PARAM_FLOAT);
        $mform->setDefault('maxscore', 10);
        $mform->addElement('textarea', 'conceptoptions', get_string('conceptoptions', 'videoreview'), ['rows' => 6, 'cols' => 70]);
        $mform->setType('conceptoptions', PARAM_TEXT);
        $mform->hideIf('conceptoptions', 'criteriontype', 'neq', 'concept');
        $mform->addHelpButton('conceptoptions', 'conceptoptions', 'videoreview');
        $mform->addElement('selectyesno', 'required', get_string('criterionrequiredlabel', 'videoreview'));
        $mform->setDefault('required', 1);
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'videoreview'), ['size' => 5]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
        $this->add_action_buttons(true, get_string('savecriterion', 'videoreview'));
    }

    /**
     * Validates criterion configuration.
     *
     * @param array $data Form data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((float)($data['maxscore'] ?? 0) <= 0) {
            $errors['maxscore'] = get_string('invalidmaxscore', 'videoreview');
        }
        if (($data['criteriontype'] ?? '') === 'concept') {
            $valid = 0;
            $lines = preg_split('/\R/u', (string)($data['conceptoptions'] ?? '')) ?: [];
            foreach ($lines as $line) {
                $parts = array_map('trim', explode('|', $line, 2));
                if (count($parts) === 2 && $parts[0] !== '' && is_numeric($parts[1])) {
                    $valid++;
                }
            }
            if ($valid < 2) {
                $errors['conceptoptions'] = get_string('invalidconceptoptions', 'videoreview');
            }
        }
        return $errors;
    }
}
