@clean @phantomjs @dump_action
Feature: Cirrus dump
  Scenario: Can dump pages
    When I dump the cirrus data for Main Page
    Then the page text contains Main Page
    And the page text contains template
    And the page text contains namespace
