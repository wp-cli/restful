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

    When I run `wp help rest comment`
    Then STDOUT should contain:
      """
      wp rest comment <command>
      """

    When I run `wp help rest post`
    Then STDOUT should contain:
      """
      wp rest post <command>
      """

    When I run `wp help rest user`
    Then STDOUT should contain:
      """
      wp rest user <command>
      """
