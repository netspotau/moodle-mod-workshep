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
 * Definition of log events
 *
 * @package    mod_workshep
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    // workshep instance log actions
    array('module'=>'workshep', 'action'=>'add', 'mtable'=>'workshep', 'field'=>'name'),
    array('module'=>'workshep', 'action'=>'update', 'mtable'=>'workshep', 'field'=>'name'),
    array('module'=>'workshep', 'action'=>'view', 'mtable'=>'workshep', 'field'=>'name'),
    array('module'=>'workshep', 'action'=>'view all', 'mtable'=>'workshep', 'field'=>'name'),
    // submission log actions
    array('module'=>'workshep', 'action'=>'add submission', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    array('module'=>'workshep', 'action'=>'update submission', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    array('module'=>'workshep', 'action'=>'view submission', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    // assessment log actions
    array('module'=>'workshep', 'action'=>'add assessment', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    array('module'=>'workshep', 'action'=>'update assessment', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    // example log actions
    array('module'=>'workshep', 'action'=>'add example', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    array('module'=>'workshep', 'action'=>'update example', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    array('module'=>'workshep', 'action'=>'view example', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    // example assessment log actions
    array('module'=>'workshep', 'action'=>'add reference assessment', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    array('module'=>'workshep', 'action'=>'update reference assessment', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    array('module'=>'workshep', 'action'=>'add example assessment', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    array('module'=>'workshep', 'action'=>'update example assessment', 'mtable'=>'workshep_submissions', 'field'=>'title'),
    // grading evaluation log actions
    array('module'=>'workshep', 'action'=>'update aggregate grades', 'mtable'=>'workshep', 'field'=>'name'),
    array('module'=>'workshep', 'action'=>'update clear aggregated grades', 'mtable'=>'workshep', 'field'=>'name'),
    array('module'=>'workshep', 'action'=>'update clear assessments', 'mtable'=>'workshep', 'field'=>'name'),
);
