Feature: Manage WordPress through endpoints locally

  Background:
    Given a WP install
    And I run `wp plugin install rest-api --activate`

  Scenario: REST endpoints should load as WP-CLI commands
    When I run `wp help rest`
    Then STDOUT should contain:
      """
      wp rest <command>
      """
