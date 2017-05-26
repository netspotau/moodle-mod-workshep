@mod @mod_workshep
Feature: Workshop submission and calibration
  In order to use workshep activity
  As a student
  I need to be able to add a submission and assess those of my peers

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | student1 | Sam1      | Student1 | student1@example.com |
      | student2 | Sam2      | Student2 | student2@example.com |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname  | shortname |
      | Course1   | c1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | c1     | student        |
      | student2 | c1     | student        |
      | teacher1 | c1     | editingteacher |
    And the following "activities" exist:
      | activity | name         | intro                     | course | idnumber  | useexamples |
      | workshep | TestWorkshop | Test workshep description | c1     | workshep1 | 1           |
# teacher1 sets up assessment form, sample example submission and changes the phase to submission
    When I log in as "teacher1"
    And I follow "Course1"
    And I follow "TestWorkshop"
    And I click on "Edit settings" "link" in the "Administration" "block"
    And I set the field "Use Calibration" to "1"
    And I set the field "Place calibration phase..." to "20"
    And I press "Save and display"
    And I edit assessment form in workshep "TestWorkshop" as:"
      | id_description__idx_0_editor | Aspect1 |
      | id_description__idx_1_editor | Aspect2 |
      | id_description__idx_2_editor |         |
    And I press "Add example submission"
    And I set the field "Title" to "Example Title"
    And I set the field "Submission content" to "Example Submission content"
    And I press "Save changes"
    And I press "Continue"
    And I set the field "Grade for Aspect 1" to "5"
    And I set the field "Comment for Aspect 1" to "Example Comment for Aspect 1"
    And I set the field "Grade for Aspect 2" to "5"
    And I set the field "Comment for Aspect 2" to "Example Comment for Aspect 2"
    And I press "Save and close"
    And I change phase in workshep "TestWorkshop" to "Submission phase"
    And I log out
# student1 submits
    And I log in as "student1"
    And I follow "Course1"
    And I follow "TestWorkshop"
    Then I should see "Submit your work"
    And I add a submission in workshep "TestWorkshop" as:"
      | Title              | Submission1  |
      | Submission content | Some content |
    And "//div[@class='submission-full' and contains(.,'Submission1') and contains(.,'submitted on')]" "xpath_element" should exist
    And I log out
# teacher1 allocates reviewers and runs the calculate calibration scores
    And I log in as "teacher1"
    And I follow "Course1"
    And I follow "TestWorkshop"
    Then I should see "Workshop submissions report"
    And I allocate submissions in workshep "TestWorkshop" as:"
      | Participant   | Reviewer      |
      | Sam1 Student1 | Sam2 Student2 |
    And I follow "TestWorkshop"
    And I should see "to allocate: 0"
    And I change phase in workshep "TestWorkshop" to "Calibration phase"
    And the field "id_comparison" matches value "5"
    And the field "id_consistency" matches value "5"
    And I set the following fields to these values:
      | id_comparison            | 7                      |
      | id_consistency           | 4                      |
    And I press "Calculate Calibration Scores"
    And the field "id_comparison" matches value "7"
    And the field "id_consistency" matches value "4"
    And I log out
