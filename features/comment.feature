Feature: Manage WordPress comments through the REST API

  Background:
    Given a WP install
    And I run `wp plugin install rest-api --activate`

  Scenario: Help for all available commands
    When I run `wp rest comment --help`
    Then STDOUT should contain:
      """
      wp rest comment <command>
      """
    And STDOUT should contain:
      """
      create
      """
    And STDOUT should contain:
      """
      update
      """

  Scenario: List all WordPress comments
    When I run `wp rest comment list --fields=id,author_name`
    Then STDOUT should be a table containing rows:
    | id     | author_name    |
    | 1      | Mr WordPress   |

    When I run `wp rest comment list --format=count`
    Then STDOUT should be:
      """
      1
      """

  Scenario: Get a specific comment
    When I run `wp rest comment get 1 --fields=id,author_name`
    Then STDOUT should be a table containing rows:
    | Field       | Value         |
    | author_name | Mr WordPress  |
    | id          | 1             |
