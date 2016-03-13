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

    When I run `wp rest comment list --help`
    Then STDOUT should contain:
      """
      [--context=<context>]
          Scope under which the request is made; determines fields present in
          response.
          ---
          default: view
          options:
            - view
            - embed
            - edit
          ---
      """

  Scenario: List all WordPress comments
    When I run `wp rest comment list --fields=id,author_name`
    Then STDOUT should be a table containing rows:
    | id     | author_name    |
    | 1      | Mr WordPress   |

    When I run `wp rest comment list --format=ids`
    Then STDOUT should be:
      """
      1
      """

  Scenario: List comments with different contexts
    When I run `wp rest comment list --format=csv`
    Then STDOUT should contain:
      """
      id,author,author_avatar_urls,author_name,author_url,content,date,link
      """
    When I run `wp rest comment list --context=view --format=csv`
    Then STDOUT should contain:
      """
      id,author,author_avatar_urls,author_name,author_url,content,date,date_gmt,link,parent,post,status,type
      """

  Scenario: Get the count of WordPress comments
    When I run `wp comment generate --count=10`
    Then STDERR should be empty

    When I run `wp rest comment list --format=count`
    Then STDOUT should be:
      """
      11
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

  Scenario: Update a comment
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
      Error: Sorry, you can not delete this comment
      """

    When I run `wp rest comment delete 1 --user=1`
    Then STDOUT should contain:
      """
      Success: Trashed comment.
      """

    When I run `wp rest comment delete 1 --user=1 --force=true`
    Then STDOUT should contain:
      """
      Success: Deleted comment.
      """
