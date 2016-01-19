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

  Scenario: Create a comment
    When I run `wp rest comment create --post=1 --content="Hello World, again" --user=1`
    Then STDOUT should contain:
      """
      Success: Created comment.
      """

    When I run `wp rest comment get 2 --fields=content --format=json`
    Then STDOUT should contain:
      """
      {"content":{"rendered":"<p>Hello World, again<\/p>\n"}}
      """

  Scenario: Delete a comment
    When I try `wp rest comment update 1 --content="Hello World"`
    Then STDERR should contain:
      """
      Error: Sorry, you can not edit this comment
      """

    When I run `wp rest comment update 1 --content="Hello World" --user=1`
    Then STDOUT should contain:
      """
      Success: Updated comment.
      """

    When I run `wp rest comment get 1 --fields=content --format=json`
    Then STDOUT should contain:
      """
      {"content":{"rendered":"<p>Hello World<\/p>\n"}}
      """

  Scenario: Delete a comment
    When I try `wp rest comment delete 1`
    Then STDERR should contain:
      """
      Error: Sorry, you can not edit this comment
      """

    When I run `wp rest comment delete 1 --user=1`
    Then STDOUT should contain:
      """
      Success: Deleted comment.
      """
