Feature: Manage WordPress posts through the REST API

  Background:
    Given a WP install
    And I run `wp plugin install rest-api --activate`

  Scenario: Generate posts
    When I run `wp rest post list --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp rest post generate --user=admin --status=publish --title="Test Post"`
    Then STDERR should be empty

    When I run `wp rest post list --format=count`
    Then STDOUT should be:
      """
      11
      """

    When I run `wp rest post generate --user=admin --status=publish --count=9 --title="Test Post"`
    Then STDOUT should be empty

    When I run `wp rest post list --format=count`
    Then STDOUT should be:
      """
      20
      """
