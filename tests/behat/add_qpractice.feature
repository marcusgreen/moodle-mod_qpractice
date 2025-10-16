@mod @mod_qpractice
Feature: Add a qpractice
    In order to allow students to practice
    As a teacher
    I need to create a qpractice

  @javascript @_file_upload
  Scenario: Add a qpractice
    Given the following "users" exist:
          | username | firstname | lastname | email                |
          | teacher1 | Teacher   | 1        | teacher1@example.com |
          | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
          | fullname | shortname | category |
          | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
          | user     | course | role           |
          | teacher1 | C1     | editingteacher |
          | student1 | C1     | student        |
    And the following "activity" exists:
          | activity | qpractice                  |
          | course   | C1                         |
          | idnumber | 00001                      |
          | name     | QPracticeTest              |
          | intro    | Test qpractice description |
          | section  | 1                          |
          | grade    | 10                         |
    When I am on the "Course 1" "core_question > course question import" page logged in as "teacher1"
    And I set the field "id_format_xml" to "1"

    And I upload "mod/qpractice/tests/fixtures/category_quesitons.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I press "Continue"
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "QPracticeTest" "qpractice activity" page
    And I navigate to "Settings" in current page administration
