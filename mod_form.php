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
 * The main workshep configuration form
 *
 * The UI mockup has been proposed in MDL-18688
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/dev/lib/formslib.php
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Module settings form for Workshop instances
 */
class mod_workshep_mod_form extends moodleform_mod {

    /** @var object the course this instance is part of */
    protected $course = null;

    /**
     * Constructor
     */
    public function __construct($current, $section, $cm, $course) {
        $this->course = $course;
        parent::__construct($current, $section, $cm, $course);
    }

    /**
     * Defines the workshep instance configuration form
     *
     * @return void
     */
    public function definition() {
        global $CFG, $DB, $PAGE;
        
        $PAGE->requires->jquery();
        $PAGE->requires->js('/mod/workshep/mod_form.js');
        $PAGE->requires->js_init_call('M.mod_workshep.mod_form.init');

        $workshepconfig = get_config('workshep');
        $mform = $this->_form;

        // General --------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Workshop name
        $label = get_string('workshepname', 'workshep');
        $mform->addElement('text', 'name', $label, array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Introduction
        $this->standard_intro_elements(get_string('introduction', 'workshep'));

        // Grading settings -----------------------------------------------------------
        $mform->addElement('header', 'gradingsettings', get_string('gradingsettings', 'workshep'));
        $mform->setExpanded('gradingsettings');

        $label = get_string('strategy', 'workshep');
        $mform->addElement('select', 'strategy', $label, workshep::available_strategies_list());
        $mform->setDefault('strategy', $workshepconfig->strategy);
        $mform->addHelpButton('strategy', 'strategy', 'workshep');

        $grades = workshep::available_maxgrades_list();
        $gradecategories = grade_get_categories_menu($this->course->id);

        $label = get_string('submissiongrade', 'workshep');
        $mform->addGroup(array(
            $mform->createElement('select', 'grade', '', $grades),
            $mform->createElement('select', 'gradecategory', '', $gradecategories),
            ), 'submissiongradegroup', $label, ' ', false);
        $mform->setDefault('grade', $workshepconfig->grade);
        $mform->addHelpButton('submissiongradegroup', 'submissiongrade', 'workshep');

        $mform->addElement('text', 'submissiongradepass', get_string('gradetopasssubmission', 'workshep'));
        $mform->addHelpButton('submissiongradepass', 'gradepass', 'grades');
        $mform->setDefault('submissiongradepass', '');
        $mform->setType('submissiongradepass', PARAM_RAW);

        $label = get_string('gradinggrade', 'workshep');
        $mform->addGroup(array(
            $mform->createElement('select', 'gradinggrade', '', $grades),
            $mform->createElement('select', 'gradinggradecategory', '', $gradecategories),
            ), 'gradinggradegroup', $label, ' ', false);
        $mform->setDefault('gradinggrade', $workshepconfig->gradinggrade);
        $mform->addHelpButton('gradinggradegroup', 'gradinggrade', 'workshep');

        $mform->addElement('text', 'gradinggradepass', get_string('gradetopassgrading', 'workshep'));
        $mform->addHelpButton('gradinggradepass', 'gradepass', 'grades');
        $mform->setDefault('gradinggradepass', '');
        $mform->setType('gradinggradepass', PARAM_RAW);

        $options = array();
        for ($i = 5; $i >= 0; $i--) {
            $options[$i] = $i;
        }
        $label = get_string('gradedecimals', 'workshep');
        $mform->addElement('select', 'gradedecimals', $label, $options);
        $mform->setDefault('gradedecimals', $workshepconfig->gradedecimals);

        // Submission settings --------------------------------------------------------
        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'workshep'));

        $label = get_string('instructauthors', 'workshep');
        $mform->addElement('editor', 'instructauthorseditor', $label, null,
                            workshep::instruction_editors_options($this->context));

        $options = array();
        for ($i = 7; $i >= 0; $i--) {
            $options[$i] = $i;
        }
        $label = get_string('nattachments', 'workshep');
        $mform->addElement('select', 'nattachments', $label, $options);
        $mform->setDefault('nattachments', 1);

        $label = get_string('allowedfiletypesforsubmission', 'workshep');
        $mform->addElement('filetypes', 'submissionfiletypes', $label);
        $mform->addHelpButton('submissionfiletypes', 'allowedfiletypesforsubmission', 'workshep');
        $mform->disabledIf('submissionfiletypes', 'nattachments', 'eq', 0);

        $options = get_max_upload_sizes($CFG->maxbytes, $this->course->maxbytes, 0, $workshepconfig->maxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maxbytes', 'workshep'), $options);
        $mform->setDefault('maxbytes', $workshepconfig->maxbytes);
        $mform->disabledIf('maxbytes', 'nattachments', 'eq', 0);

        $label = get_string('latesubmissions', 'workshep');
        $text = get_string('latesubmissions_desc', 'workshep');
        $mform->addElement('checkbox', 'latesubmissions', $label, $text);
        $mform->addHelpButton('latesubmissions', 'latesubmissions', 'workshep');
        
        $numgroups = $DB->count_records('groups',array('courseid' => $this->course->id));
        $disabled = (bool)($numgroups == 0);
        
        $label = get_string('teammode', 'workshep');
        if ($disabled) {
            $text = get_string('teammode_disabled','workshep');
        } else {
            $text = get_string('teammode_desc', 'workshep');
        }
        $params = $disabled ? array('disabled' => 'disabled') : array();
        $mform->addElement('checkbox','teammode',$label,$text,$params);
        $mform->addHelpButton('teammode','teammode','workshep');

        $label = get_string('nosubmissionrequired', 'workshep');
        $text = get_string('nosubmissionrequired_desc', 'workshep');
        $mform->addElement('checkbox', 'nosubmissionrequired', $label, $text);
        $mform->addHelpButton('nosubmissionrequired','nosubmissionrequired','workshep');

        // Assessment settings --------------------------------------------------------
        $mform->addElement('header', 'assessmentsettings', get_string('assessmentsettings', 'workshep'));

        $label = get_string('instructreviewers', 'workshep');
        $mform->addElement('editor', 'instructreviewerseditor', $label, null,
                            workshep::instruction_editors_options($this->context));
                            
        $label = get_string('useselfassessment', 'workshep');
        $text = get_string('useselfassessment_desc', 'workshep');
        $mform->addElement('checkbox', 'useselfassessment', $label, $text);
        $mform->addHelpButton('useselfassessment', 'useselfassessment', 'workshep');

        // Feedback -------------------------------------------------------------------
        $mform->addElement('header', 'feedbacksettings', get_string('feedbacksettings', 'workshep'));

        $mform->addElement('select', 'overallfeedbackmode', get_string('overallfeedbackmode', 'mod_workshep'), array(
            0 => get_string('overallfeedbackmode_0', 'mod_workshep'),
            1 => get_string('overallfeedbackmode_1', 'mod_workshep'),
            2 => get_string('overallfeedbackmode_2', 'mod_workshep')));
        $mform->addHelpButton('overallfeedbackmode', 'overallfeedbackmode', 'mod_workshep');
        $mform->setDefault('overallfeedbackmode', 1);

        $options = array();
        for ($i = 7; $i >= 0; $i--) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'overallfeedbackfiles', get_string('overallfeedbackfiles', 'workshep'), $options);
        $mform->setDefault('overallfeedbackfiles', 0);
        $mform->disabledIf('overallfeedbackfiles', 'overallfeedbackmode', 'eq', 0);

        $label = get_string('allowedfiletypesforoverallfeedback', 'workshep');
        $mform->addElement('filetypes', 'overallfeedbackfiletypes', $label);
        $mform->addHelpButton('overallfeedbackfiletypes', 'allowedfiletypesforoverallfeedback', 'workshep');
        $mform->disabledIf('overallfeedbackfiletypes', 'overallfeedbackfiles', 'eq', 0);

        $options = get_max_upload_sizes($CFG->maxbytes, $this->course->maxbytes);
        $mform->addElement('select', 'overallfeedbackmaxbytes', get_string('overallfeedbackmaxbytes', 'workshep'), $options);
        $mform->setDefault('overallfeedbackmaxbytes', $workshepconfig->maxbytes);
        $mform->disabledIf('overallfeedbackmaxbytes', 'overallfeedbackmode', 'eq', 0);
        $mform->disabledIf('overallfeedbackmaxbytes', 'overallfeedbackfiles', 'eq', 0);

        $label = get_string('conclusion', 'workshep');
        $mform->addElement('editor', 'conclusioneditor', $label, null,
                            workshep::instruction_editors_options($this->context));
        $mform->addHelpButton('conclusioneditor', 'conclusion', 'workshep');

        // Example submissions --------------------------------------------------------
        $mform->addElement('header', 'examplesubmissionssettings', get_string('examplesubmissions', 'workshep'));

        $label = get_string('useexamples', 'workshep');
        $text = get_string('useexamples_desc', 'workshep');
        $mform->addElement('checkbox', 'useexamples', $label, $text);
        $mform->addHelpButton('useexamples', 'useexamples', 'workshep');

        $label = get_string('examplesmode', 'workshep');
        $options = workshep::available_example_modes_list();
        $mform->addElement('select', 'examplesmode', $label, $options);
        $mform->setDefault('examplesmode', $workshepconfig->examplesmode);
        $mform->disabledIf('examplesmode', 'useexamples');
        $mform->disabledIf('examplesmode', 'usecalibration', 'checked');

        $label = get_string('numexamples', 'workshep');
        $mform->addElement('select', 'numexamples', $label, array('All',1,2,3,4,5,6,7,8,9,10,11,12,13,14,15));
        $mform->disabledIf('numexamples', 'useexamples');
        $mform->setDefault('numexamples', 0);
        $mform->addHelpButton('numexamples','numexamples','workshep');
                
        $label = get_string('examplescompare', 'workshep');
        $text = get_string('examplescompare_desc', 'workshep');
        $mform->addElement('checkbox', 'examplescompare', $label, $text);
        $mform->disabledIf('examplescompare', 'useexamples');
        $mform->setDefault('examplescompare', true);

        $label = get_string('examplesreassess', 'workshep');
        $text = get_string('examplesreassess_desc', 'workshep');
        $mform->addElement('checkbox', 'examplesreassess', $label, $text);
        $mform->disabledIf('examplesreassess', 'useexamples');
        $mform->setDefault('examplesreassess', true);

        // Calibration ----------------------------------------------------------------
        $mform->addElement('header', 'examplesubmissionssettings', get_string('calibration', 'workshep'));
        
        $label = get_string('usecalibration', 'workshep');
        $mform->disabledIf('usecalibration', 'useexamples');
        $text = get_string('usecalibration_desc', 'workshep');
        $mform->addElement('checkbox', 'usecalibration', $label, $text);
        $mform->addHelpButton('usecalibration', 'usecalibration', 'workshep');
        
        $workshepcalibrationconfig = get_config('workshepcalibration_examples');
        $label = get_string('autorecalculate', 'workshepcalibration_examples');
        $mform->addElement('checkbox', 'autorecalculate', $label);
        $mform->setDefault('autorecalculate', $workshepcalibrationconfig->autorecalculate);

        $label = get_string('calibrationphase', 'workshep');
        $values = array(
            workshep::PHASE_SETUP => get_string('beforesubmission', 'workshep'),
            workshep::PHASE_SUBMISSION => get_string('beforeassessment', 'workshep')
        );
        $mform->addElement('select', 'calibrationphase', $label, $values);
        $mform->disabledIf('calibrationphase', 'useexamples');
        $mform->disabledIf('calibrationphase', 'usecalibration');
        $mform->setDefault('calibrationphase', workshep::PHASE_SUBMISSION);
        $mform->addHelpButton('calibrationphase','calibrationphase','workshep');

        $options = array();
        for ($i = 9; $i >= 1; $i--) {
            $options[$i] = get_string('comparisonlevel' . $i, 'workshepcalibration_examples');
        }
        $label = get_string('comparison', 'workshepcalibration_examples');
        $mform->addElement('select', 'calibrationcomparison', $label, $options);
        $mform->disabledIf('calibrationcomparison', 'usecalibration');
        $mform->addHelpButton('calibrationcomparison', 'comparison', 'workshepcalibration_examples');
        $mform->setDefault('calibrationcomparison', (empty($this->current->calibrationcomparison) ?
                                                     $workshepcalibrationconfig->accuracy : $this->current->calibrationcomparison));

        $label = get_string('consistency', 'workshepcalibration_examples');
        $mform->addElement('select', 'calibrationconsistency', $label, $options);
        $mform->disabledIf('calibrationconsistency', 'usecalibration');
        $mform->addHelpButton('calibrationconsistency', 'consistency', 'workshepcalibration_examples');
        $mform->setDefault('calibrationconsistency', (empty($this->current->calibrationconsistency) ?
                                                      $workshepcalibrationconfig->consistence : $this->current->calibrationconsistency));

        // Access control -------------------------------------------------------------
        $mform->addElement('header', 'accesscontrol', get_string('availability', 'core'));

        $label = get_string('submissionstart', 'workshep');
        $mform->addElement('date_time_selector', 'submissionstart', $label, array('optional' => true));

        $label = get_string('submissionend', 'workshep');
        $mform->addElement('date_time_selector', 'submissionend', $label, array('optional' => true));

        $label = get_string('submissionendswitch', 'mod_workshep');
        $mform->addElement('checkbox', 'phaseswitchassessment', $label);
        $mform->disabledIf('phaseswitchassessment', 'submissionend[enabled]');
        $mform->addHelpButton('phaseswitchassessment', 'submissionendswitch', 'mod_workshep');

        $label = get_string('assessmentstart', 'workshep');
        $mform->addElement('date_time_selector', 'assessmentstart', $label, array('optional' => true));

        $label = get_string('assessmentend', 'workshep');
        $mform->addElement('date_time_selector', 'assessmentend', $label, array('optional' => true));

        $coursecontext = context_course::instance($this->course->id);
        plagiarism_get_form_elements_module($mform, $coursecontext, 'mod_workshep');

        // Common module settings, Restrict availability, Activity completion etc. ----
        $features = array('groups'=>true, 'groupings'=>true, 'groupmembersonly'=>true,
                'outcomes' => true, 'gradecat' => false, 'idnumber' => false);

        $this->standard_coursemodule_elements();

        // Standard buttons, common to all modules ------------------------------------
        $this->add_action_buttons();
    }

    /**
     * Prepares the form before data are set
     *
     * Additional wysiwyg editor are prepared here, the introeditor is prepared automatically by core.
     * Grade items are set here because the core modedit supports single grade item only.
     *
     * @param array $data to be set
     * @return void
     */
    public function data_preprocessing(&$data) {
        if ($this->current->instance) {
            // editing an existing workshep - let us prepare the added editor elements (intro done automatically)
            $draftitemid = file_get_submitted_draft_itemid('instructauthors');
            $data['instructauthorseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_workshep', 'instructauthors', 0,
                                workshep::instruction_editors_options($this->context),
                                $data['instructauthors']);
            $data['instructauthorseditor']['format'] = $data['instructauthorsformat'];
            $data['instructauthorseditor']['itemid'] = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('instructreviewers');
            $data['instructreviewerseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_workshep', 'instructreviewers', 0,
                                workshep::instruction_editors_options($this->context),
                                $data['instructreviewers']);
            $data['instructreviewerseditor']['format'] = $data['instructreviewersformat'];
            $data['instructreviewerseditor']['itemid'] = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid('conclusion');
            $data['conclusioneditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_workshep', 'conclusion', 0,
                                workshep::instruction_editors_options($this->context),
                                $data['conclusion']);
            $data['conclusioneditor']['format'] = $data['conclusionformat'];
            $data['conclusioneditor']['itemid'] = $draftitemid;
        } else {
            // adding a new workshep instance
            $draftitemid = file_get_submitted_draft_itemid('instructauthors');
            file_prepare_draft_area($draftitemid, null, 'mod_workshep', 'instructauthors', 0);    // no context yet, itemid not used
            $data['instructauthorseditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('instructreviewers');
            file_prepare_draft_area($draftitemid, null, 'mod_workshep', 'instructreviewers', 0);    // no context yet, itemid not used
            $data['instructreviewerseditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('conclusion');
            file_prepare_draft_area($draftitemid, null, 'mod_workshep', 'conclusion', 0);    // no context yet, itemid not used
            $data['conclusioneditor'] = array('text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid);
        }
    }

    /**
     * Set the grade item categories when editing an instance
     */
    public function definition_after_data() {

        $mform =& $this->_form;

        if ($id = $mform->getElementValue('update')) {
            $instance   = $mform->getElementValue('instance');

            $gradeitems = grade_item::fetch_all(array(
                'itemtype'      => 'mod',
                'itemmodule'    => 'workshep',
                'iteminstance'  => $instance,
                'courseid'      => $this->course->id));

            if (!empty($gradeitems)) {
                foreach ($gradeitems as $gradeitem) {
                    // here comes really crappy way how to set the value of the fields
                    // gradecategory and gradinggradecategory - grrr QuickForms
                    $decimalpoints = $gradeitem->get_decimals();
                    if ($gradeitem->itemnumber == 0) {
                        $submissiongradepass = $mform->getElement('submissiongradepass');
                        $submissiongradepass->setValue(format_float($gradeitem->gradepass, $decimalpoints));
                        $group = $mform->getElement('submissiongradegroup');
                        $elements = $group->getElements();
                        foreach ($elements as $element) {
                            if ($element->getName() == 'gradecategory') {
                                $element->setValue($gradeitem->categoryid);
                            }
                        }
                    } else if ($gradeitem->itemnumber == 1) {
                        $gradinggradepass = $mform->getElement('gradinggradepass');
                        $gradinggradepass->setValue(format_float($gradeitem->gradepass, $decimalpoints));
                        $group = $mform->getElement('gradinggradegroup');
                        $elements = $group->getElements();
                        foreach ($elements as $element) {
                            if ($element->getName() == 'gradinggradecategory') {
                                $element->setValue($gradeitem->categoryid);
                            }
                        }
                    }
                }
            }
        }

        parent::definition_after_data();
    }

    /**
     * Validates the form input
     *
     * @param array $data submitted data
     * @param array $files submitted files
     * @return array eventual errors indexed by the field name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // check the phases borders are valid
        if ($data['submissionstart'] > 0 and $data['submissionend'] > 0 and $data['submissionstart'] >= $data['submissionend']) {
            $errors['submissionend'] = get_string('submissionendbeforestart', 'mod_workshep');
        }
        if ($data['assessmentstart'] > 0 and $data['assessmentend'] > 0 and $data['assessmentstart'] >= $data['assessmentend']) {
            $errors['assessmentend'] = get_string('assessmentendbeforestart', 'mod_workshep');
        }

        // check the phases do not overlap
        if (max($data['submissionstart'], $data['submissionend']) > 0 and max($data['assessmentstart'], $data['assessmentend']) > 0) {
            $phasesubmissionend = max($data['submissionstart'], $data['submissionend']);
            $phaseassessmentstart = min($data['assessmentstart'], $data['assessmentend']);
            if ($phaseassessmentstart == 0) {
                $phaseassessmentstart = max($data['assessmentstart'], $data['assessmentend']);
            }
            if ($phasesubmissionend > 0 and $phaseassessmentstart > 0 and $phaseassessmentstart < $phasesubmissionend) {
                foreach (array('submissionend', 'submissionstart', 'assessmentstart', 'assessmentend') as $f) {
                    if ($data[$f] > 0) {
                        $errors[$f] = get_string('phasesoverlap', 'mod_workshep');
                        break;
                    }
                }
            }
        }

        // Check that the submission grade pass is a valid number.
        if (!empty($data['submissiongradepass'])) {
            $submissiongradefloat = unformat_float($data['submissiongradepass'], true);
            if ($submissiongradefloat === false) {
                $errors['submissiongradepass'] = get_string('err_numeric', 'form');
            } else {
                if ($submissiongradefloat > $data['grade']) {
                    $errors['submissiongradepass'] = get_string('gradepassgreaterthangrade', 'grades', $data['grade']);
                }
            }
        }

        // Check that the grade pass is a valid number.
        if (!empty($data['gradinggradepass'])) {
            $gradepassfloat = unformat_float($data['gradinggradepass'], true);
            if ($gradepassfloat === false) {
                $errors['gradinggradepass'] = get_string('err_numeric', 'form');
            } else {
                if ($gradepassfloat > $data['gradinggrade']) {
                    $errors['gradinggradepass'] = get_string('gradepassgreaterthangrade', 'grades', $data['gradinggrade']);
                }
            }
        }

        return $errors;
    }
}
