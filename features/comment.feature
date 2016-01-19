Feature: Manage WordPress comments through the REST API

  Background:
    Given a WP install
    And I run `wp plugin install rest-api --activate`

  Scenario: List all WordPress comments
    When I run `wp rest comment list --fields=id,author_name`
    Then STDOUT should be a table containing rows:
    | id     | author_name    |
    | 1      | Mr WordPress   |
