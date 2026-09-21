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
 * AJAX services for Video Peer Review.
 *
 * @package   mod_videoreview
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_videoreview_save_comment' => [
        'classname' => 'mod_videoreview\\external\\save_comment',
        'methodname' => 'execute',
        'description' => 'Save a timestamped review comment.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/videoreview:review',
    ],
    'mod_videoreview_delete_comment' => [
        'classname' => 'mod_videoreview\\external\\delete_comment',
        'methodname' => 'execute',
        'description' => 'Delete a timestamped review comment owned by the current reviewer.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/videoreview:review',
    ],
    'mod_videoreview_save_progress' => [
        'classname' => 'mod_videoreview\\external\\save_progress',
        'methodname' => 'execute',
        'description' => 'Store video viewing progress for a review.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/videoreview:view',
    ],
];
