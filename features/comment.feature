Feature: Manage WordPress comments through the REST API

  Background:
    Given a WP install
    When I run `wp core version`
	Then STDOUT should be a version string >= 4.7

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
    And STDOUT should contain:
      """
      edit
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
    | id     | author_name           |
    | 1      | A WordPress Commenter |

    When I run `wp rest comment list --format=ids`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp rest comment list --format=body`
    Then STDOUT should be JSON containing:
      """
      [{"author_name":"A WordPress Commenter"}]
      """

    When I run `wp rest comment list --format=headers`
    Then STDOUT should be JSON containing:
      """
      {"x-wp-totalpages":1}
      """

    When I run `wp rest comment list --format=envelope`
    Then STDOUT should be JSON containing:
      """
      {"headers":{"x-wp-totalpages":1}}
      """

  Scenario: List comments with different contexts
    When I run `wp rest comment list --context=embed --format=csv`
    Then STDOUT should contain:
      """
      id,author,author_name,author_url,content,date,link,parent,type,author_avatar_urls
      """
    When I run `wp rest comment list --format=csv`
    Then STDOUT should contain:
      """
      id,author,author_name,author_url,content,date,date_gmt,link,parent,post,status,type,author_avatar_urls
      """

  Scenario: Get the count of WordPress comments
    When I run `wp comment generate --count=10`
    Then STDERR should be empty

    When I run `wp rest comment list --format=count`
    Then STDOUT should be:
      """
      11
      """

  Scenario: Get the value of an individual comment field
    When I run `wp rest comment get 1 --field=author_name`
    Then STDOUT should be:
      """
      A WordPress Commenter
      """

  Scenario: Get a specific comment
    When I run `wp rest comment get 1 --fields=id,author_name`
    Then STDOUT should be a table containing rows:
    | Field       | Value                 |
    | author_name | A WordPress Commenter |
    | id          | 1                     |

    When I run `wp rest comment get 1 --format=body`
    Then STDOUT should be JSON containing:
      """
      {"author_name":"A WordPress Commenter"}
      """

    When I run `wp rest comment get 1 --format=envelope`
    Then STDOUT should be JSON containing:
      """
      {"body":{"author_name":"A WordPress Commenter"}}
      """

  Scenario: Create a comment
    When I run `wp rest comment create --post=1 --content="Hello World, again" --user=1`
    Then STDOUT should contain:
      """
      Success: Created comment 2
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
      Error: Sorry, you are not allowed to edit this comment. HTTP code: 401
      """
    Then the return code should be 145

    When I run `wp rest comment update 1 --content="Hello World" --user=1`
    Then STDOUT should contain:
      """
      Success: Updated comment 1.
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
      Error: Sorry, you are not allowed to delete this comment. HTTP code: 401
      """
    Then the return code should be 145

    When I run `wp rest comment delete 1 --user=1`
    Then STDOUT should contain:
      """
      Success: Trashed comment 1.
      """

    When I run `wp rest comment delete 1 --user=1 --force=true`
    Then STDOUT should contain:
      """
      Success: Deleted comment 1.
      """
