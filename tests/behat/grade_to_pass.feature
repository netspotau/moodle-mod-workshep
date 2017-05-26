@mod @mod_workshep
Feature: Setting grades to pass via workshep editing form
  In order to define grades to pass
  As a teacher
  I can set them in the workshep settings form, without the need to go to the gradebook

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname  | shortname |
      | Course1   | c1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | c1     | editingteacher |

  Scenario: Adding a new workshep with grade to pass field set
    Given I log in as "teacher1"
    And I follow "Course1"
    And I turn editing mode on
    When I add a "Enhanced Workshop" to section "1" and I fill the form with:
      | Workshop name | Awesome workshep |
      | Description | Grades to pass are set here |
      | Submission grade to pass | 45   |
      | Assessment grade to pass | 10.5 |
    Then I should not see "Adding a new Enhanced Workshop"
    And I follow "Awesome workshep"
    And I navigate to "Edit settings" node in "Enhanced Workshop administration"
    And the field "Submission grade to pass" matches value "45.00"
    And the field "Assessment grade to pass" matches value "10.50"

  Scenario: Adding a new workshep with grade to pass fields left empty
    Given I log in as "teacher1"
    And I follow "Course1"
    And I turn editing mode on
    When I add a "Enhanced Workshop" to section "1" and I fill the form with:
      | Workshop name | Another awesome workshep |
      | Description | No grades to pass are set here |
      | Submission grade to pass |    |
      | Assessment grade to pass |    |
    Then I should not see "Adding a new Enhanced Workshop"
    And I follow "Another awesome workshep"
    And I navigate to "Edit settings" node in "Enhanced Workshop administration"
    And the field "Submission grade to pass" matches value "0.00"
    And the field "Assessment grade to pass" matches value "0.00"

  Scenario: Adding a new workshep with non-numeric value of a grade to pass
    Given I log in as "teacher1"
    And I follow "Course1"
    And I turn editing mode on
    When I add a "Enhanced Workshop" to section "1" and I fill the form with:
      | Workshop name | Almost awesome workshep |
      | Description | Invalid grade to pass is set here |
      | Assessment grade to pass | You shall not pass! |
    Then I should see "Adding a new Enhanced Workshop"
    And I should see "You must enter a number here"

  Scenario: Adding a new workshep with invalid value of a grade to pass
    Given I log in as "teacher1"
    And I follow "Course1"
    And I turn editing mode on
    When I add a "Enhanced Workshop" to section "1" and I fill the form with:
      | Workshop name | Almost awesome workshep |
      | Description | Invalid grade to pass is set here |
      | Assessment grade to pass | 10000000 |
    Then I should see "Adding a new Enhanced Workshop"
    And I should see "The grade to pass can not be greater than the maximum possible grade"

  Scenario: Emptying grades to pass fields sets them to zero
    Given I log in as "teacher1"
    And I follow "Course1"
    And I turn editing mode on
    And I add a "Enhanced Workshop" to section "1" and I fill the form with:
      | Workshop name | Super awesome workshep |
      | Description | Grade to pass are set and then unset here |
      | Submission grade to pass | 59.99 |
      | Assessment grade to pass | 0.000 |
    And I should not see "Adding a new Enhanced Workshop"
    And I follow "Super awesome workshep"
    And I navigate to "Edit settings" node in "Enhanced Workshop administration"
    And the field "Submission grade to pass" matches value "59.99"
    And the field "Assessment grade to pass" matches value "0.00"
    When I set the field "Submission grade to pass" to ""
    And I set the field "Assessment grade to pass" to ""
    And I press "Save and display"
    Then I should not see "Adding a new Enhanced Workshop"
    And I follow "Super awesome workshep"
    And I navigate to "Edit settings" node in "Enhanced Workshop administration"
    And the field "Submission grade to pass" matches value "0.00"
    And the field "Assessment grade to pass" matches value "0.00"
