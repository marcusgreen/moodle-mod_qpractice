@mod @mod_qpractice
Feature: Edit category path stages
    In order to build a category path
    As a teacher
    I need to add, remove and identify path stages on the settings form

  Background:
    Given the following "users" exist:
          | username | firstname | lastname | email                |
          | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
          | fullname | shortname | category |
          | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
          | user     | course | role           |
          | teacher1 | C1     | editingteacher |
    And the following "activity" exists:
          | activity | qpractice                  |
          | course   | C1                         |
          | idnumber | 00001                      |
          | name     | QPracticeTest              |
          | intro    | Test qpractice description |
          | section  | 1                          |

  @javascript
  Scenario: Stages are numbered, can be added and removed, and hide with path mode
    Given I am on the "QPracticeTest" "qpractice activity editing" page logged in as "teacher1"
    When I set the field "Enable category path" to "1"
    Then I should see "Stage 1"
    And "Stage 1 category" "field" should be visible
    And I should see "Final stage: students keep practising this category"
    And "Stage 1 target to advance (%)" "field" should not exist
    And I should not see "Stage 2"
    When I press "Add stage"
    Then I should see "Stage 2"
    And "Stage 2 category" "field" should be visible
    And "Stage 1 target to advance (%)" "field" should be visible
    And "Stage 2 target to advance (%)" "field" should not exist
    And "Stage 1 minimum questions answered" "field" should be visible
    And "Stage 2 minimum questions answered" "field" should not exist
    When I set the field "Stage 1 when target reached" to "Stay on this stage"
    Then "Stage 1 target to advance (%)" "field" should not be visible
    And "Stage 1 minimum questions answered" "field" should not be visible
    When I set the field "Stage 1 when target reached" to "Move to next stage"
    Then "Stage 1 target to advance (%)" "field" should be visible
    When I press "Remove stage 1"
    Then I should see "Stage 1"
    And "Stage 1 category" "field" should be visible
    And I should not see "Stage 2"
    When I set the field "Enable category path" to "0"
    Then I should not see "Stage 1"

  @javascript
  Scenario: Stages can be moved up and down
    Given I am on the "QPracticeTest" "qpractice activity editing" page logged in as "teacher1"
    When I set the field "Enable category path" to "1"
    And I press "Add stage"
    And I press "Add stage"
    Then the "Move stage 1 up" "button" should be disabled
    And the "Move stage 3 down" "button" should be disabled
    And the "Move stage 2 up" "button" should be enabled
    When I set the field "Stage 1 target to advance (%)" to "20"
    And I set the field "Stage 2 target to advance (%)" to "30"
    And I press "Move stage 1 down"
    Then the field "Stage 1 target to advance (%)" matches value "30"
    And the field "Stage 2 target to advance (%)" matches value "20"
