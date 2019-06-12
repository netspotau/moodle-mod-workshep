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
 * Steps definitions related to mod_workshep.
 *
 * @package    mod_workshep
 * @category   test
 * @copyright  2014 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode as TableNode;

/**
 * Steps definitions related to mod_workshep.
 *
 * @package    mod_workshep
 * @category   test
 * @copyright  2014 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_workshep extends behat_base {
    /**
     * Changes the submission phase for the workshep.
     *
     * @When /^I change phase in workshep "(?P<workshep_name_string>(?:[^"]|\\")*)" to "(?P<phase_name_string>(?:[^"]|\\")*)"$/
     * @param string $questiontype
     * @param string $workshepname
     */
    public function i_change_phase_in_workshep_to($workshepname, $phase) {
        $workshepname = $this->escape($workshepname);
        $phaseliteral = behat_context_helper::escape($phase);
        $phasenumber = $this->get_phase_number($phase);
        $switchphase = behat_context_helper::escape(get_string('switchphase' . $phasenumber, 'workshep'));

        $xpath = "//*[@class='userplan']/descendant::div[./span[contains(.,$phaseliteral)]]/".
                "descendant-or-self::a[./i[@title=$switchphase]]";
        $continue = $this->escape(get_string('continue'));

        $this->execute('behat_general::click_link', $workshepname);

        $this->execute("behat_general::i_click_on", array($xpath, "xpath_element"));

        $this->execute("behat_forms::press_button", $continue);
    }

    /**
     * Translate Moodle string to phase number
     *
     * @param string $phase;
     * @return int $phasenumber;
     */

    private function get_phase_number($phase) {
        $phasenumber = 0;
        switch ($phase) {
            case get_string('phasesetup', 'workshep'):
                $phasenumber = workshop::PHASE_SETUP;
                break;
            case get_string('phasesubmission', 'workshep'):
                $phasenumber = workshop::PHASE_SUBMISSION;
                break;
            case get_string('phaseassessment', 'workshep'):
                $phasenumber = workshop::PHASE_ASSESSMENT;
                break;
            case get_string('phaseevaluation', 'workshep'):
                $phasenumber = workshop::PHASE_EVALUATION;
                break;
            case get_string('phaseclosed', 'workshep'):
                $phasenumber = workshop::PHASE_CLOSED;
                break;
        }
        return $phasenumber;
    }


    /**
     * Adds or edits a student workshep submission.
     *
     * @When /^I add a submission in workshep "(?P<workshep_name_string>(?:[^"]|\\")*)" as:"$/
     * @param string $workshepname
     * @param TableNode $table data to fill the submission form with, must contain 'Title'
     */
    public function i_add_a_submission_in_workshep_as($workshepname, $table) {
        $workshepname = $this->escape($workshepname);
        $savechanges = $this->escape(get_string('savechanges'));
        $xpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' ownsubmission ')]/descendant::*[@type='submit']";

        $this->execute('behat_general::click_link', $workshepname);

        $this->execute("behat_general::i_click_on", array($xpath, "xpath_element"));

        $this->execute("behat_forms::i_set_the_following_fields_to_these_values", $table);

        $this->execute("behat_forms::press_button", $savechanges);
    }

    /**
     * Sets the workshep assessment form.
     *
     * @When /^I edit assessment form in workshep "(?P<workshep_name_string>(?:[^"]|\\")*)" as:"$/
     * @param string $workshepname
     * @param TableNode $table data to fill the submission form with, must contain 'Title'
     */
    public function i_edit_assessment_form_in_workshep_as($workshepname, $table) {

        $this->execute('behat_general::click_link', $workshepname);

        $this->execute('behat_navigation::i_navigate_to_in_current_page_administration',
            get_string('editassessmentform', 'workshep'));

        $this->execute("behat_forms::i_set_the_following_fields_to_these_values", $table);

        $this->execute("behat_forms::press_button", get_string('saveandclose', 'workshep'));
    }

    /**
     * Peer-assesses a workshep submission.
     *
     * @When /^I assess submission "(?P<submission_string>(?:[^"]|\\")*)" in workshep "(?P<workshep_name_string>(?:[^"]|\\")*)" as:"$/
     * @param string $submission
     * @param string $workshepname
     * @param TableNode $table
     */
    public function i_assess_submission_in_workshep_as($submission, $workshepname, TableNode $table) {
        $workshepname = $this->escape($workshepname);
        $submissionliteral = behat_context_helper::escape($submission);
        $xpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' assessment-summary ') ".
                "and contains(.,$submissionliteral)]";
        $assess = $this->escape(get_string('assess', 'workshep'));
        $saveandclose = $this->escape(get_string('saveandclose', 'workshep'));

        $this->execute('behat_general::click_link', $workshepname);

        $this->execute('behat_general::i_click_on_in_the',
            array($assess, "button", $xpath, "xpath_element")
        );

        $this->execute("behat_forms::i_set_the_following_fields_to_these_values", $table);

        $this->execute("behat_forms::press_button", $saveandclose);
    }

    /**
     * Checks that the user has particular grade set by his reviewing peer in workshep
     *
     * @Then /^I should see grade "(?P<grade_string>[^"]*)" for workshep participant "(?P<participant_name_string>(?:[^"]|\\")*)" set by peer "(?P<reviewer_name_string>(?:[^"]|\\")*)"$/
     * @param string $grade
     * @param string $participant
     * @param string $reviewer
     */
    public function i_should_see_grade_for_workshep_participant_set_by_peer($grade, $participant, $reviewer) {
        $participantliteral = behat_context_helper::escape($participant);
        $reviewerliteral = behat_context_helper::escape($reviewer);
        $gradeliteral = behat_context_helper::escape($grade);
        $participantselector = "contains(concat(' ', normalize-space(@class), ' '), ' participant ') ".
                "and contains(.,$participantliteral)";
        $trxpath = "//table/tbody/tr[td[$participantselector]]";
        $tdparticipantxpath = "//table/tbody/tr/td[$participantselector]";
        $tdxpath = "/td[contains(concat(' ', normalize-space(@class), ' '), ' receivedgrade ') and contains(.,$reviewerliteral)]/".
                "descendant::span[contains(concat(' ', normalize-space(@class), ' '), ' grade ') and .=$gradeliteral]";

        $tr = $this->find('xpath', $trxpath);
        $rowspan = $this->find('xpath', $tdparticipantxpath)->getAttribute('rowspan');

        $xpath = $trxpath.$tdxpath;
        if (!empty($rowspan)) {
            for ($i = 1; $i < $rowspan; $i++) {
                $xpath .= ' | '.$trxpath."/following-sibling::tr[$i]".$tdxpath;
            }
        }
        $this->find('xpath', $xpath);
    }

    /**
     * Configure portfolio plugin, set value for portfolio instance
     *
     * @When /^I set portfolio instance "(?P<portfolioinstance_string>(?:[^"]|\\")*)" to "(?P<value_string>(?:[^"]|\\")*)"$/
     * @param string $portfolioinstance
     * @param string $value
    public function i_set_portfolio_instance_to($portfolioinstance, $value) {

        $rowxpath = "//table[contains(@class, 'generaltable')]//tr//td[contains(text(), '"
            . $portfolioinstance . "')]/following-sibling::td";

        $selectxpath = $rowxpath.'//select';
        $select = $this->find('xpath', $selectxpath);
        $select->selectOption($value);

        if (!$this->running_javascript()) {
            $this->execute('behat_general::i_click_on_in_the',
                array(get_string('go'), "button", $rowxpath, "xpath_element")
            );
        }
    }

    Commented out to fix this issue:

     - Step "/^I set portfolio instance "(?P<portfolioinstance_string>(?:[^"]|\\")*)" to "(?P<value_string>(?:[^"]|\\")*)"$/" is already defined in behat_mod_workshep::i_set_portfolio_instance_to()
     - behat_mod_workshep::i_set_portfolio_instance_to()
     - behat_mod_workshop::i_set_portfolio_instance_to()

     */
}
