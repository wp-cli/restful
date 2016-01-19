Feature: Manage WordPress comments through the REST API

  Background:
    Given a WP install
    And I run `wp plugin install rest-api --activate`

  Scenario: List all WordPress comments
    When I run `wp rest comment list --fields=id,author_name`
    Then STDOUT should be a table containing rows:
    | id     | author_name    |
    | 1      | Mr WordPress   |

  Scenario: Get a specific comment
    When I run `wp rest comment get 1 --fields=id,author_name`
    Then STDOUT should be a table containing rows:
    | Field       | Value         |
    | author_name | Mr WordPress  |
    | id          | 1             |
