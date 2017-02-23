Feature: Manage WordPress posts through the REST API

  Background:
    Given a WP install
    When I run `wp core version`
    Then STDOUT should be a version string >= 4.7

  Scenario: Get the value of an individual post field
    When I run `wp rest post get 1 --field=title`
    Then STDOUT should be JSON containing:
      """
      {"rendered":"Hello world!"}
      """

  Scenario: CUD a post with `--porcelain`
    When I run `wp rest post create --user=admin --title="Test Post" --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {POST_ID}

    When I run `wp rest post update {POST_ID} --user=admin --title="Test Post Two" --porcelain`
    Then STDOUT should be:
      """
      {POST_ID}
      """

    When I run `wp rest post delete {POST_ID} --user=admin --force=true --porcelain`
    Then STDOUT should be:
      """
      {POST_ID}
      """

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
	Then STDERR should be empty

    When I run `wp rest post list --format=count`
    Then STDOUT should be:
      """
      20
      """

Scenario: Manipulate post revisions
    When I run `wp rest post create --user=admin --title="Test Post for Revisions" --content="This is test content." --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {POST_ID}

    When I run `wp rest post update {POST_ID} --user=admin --status=publish --porcelain`
    Then STDOUT should be:
      """
      {POST_ID}
      """

    When I run `wp rest post update {POST_ID} --user=admin --content="This is my first revision of test content." --porcelain`
    Then STDOUT should be:
      """
      {POST_ID}
      """

    When I run `wp rest post update {POST_ID} --user=admin --content="This is my second revision of test content." --porcelain`
    Then STDOUT should be a number
    Then STDOUT should be:
      """
      {POST_ID}
      """

    When I run `wp rest post-revision list {POST_ID} --user=admin --fields=parent,content`
    Then STDOUT should be a table containing rows:
    | parent    | content                                                              |
    | {POST_ID} | {"rendered":"<p>This is my second revision of test content.<\/p>\n"} |
    | {POST_ID} | {"rendered":"<p>This is my first revision of test content.<\/p>\n"}  |

    When I run `wp rest post list --format=count`
    Then STDOUT should be:
      """
      2
      """

    When I run `wp rest post-revision list {POST_ID} --user=admin --format=count`
    Then STDOUT should be:
      """
      3
      """

    When I run `wp rest post-revision list {POST_ID} --user=admin --format=ids`
    Then save STDOUT '(\d+)' as {REV_ID}

    When I run `wp rest post-revision get {POST_ID} {REV_ID} --user=admin`
    Then STDOUT should be a table containing rows:
    | Field  | Value     |
    | author | 1         |
    | id     | {REV_ID}  |
    | parent | {POST_ID} |

    When I run `wp rest post-revision delete {POST_ID} {REV_ID} --user=admin --force=true --porcelain`
    Then STDOUT should be:
      """
      {REV_ID}
      """

    When I try `wp rest post-revision delete {POST_ID} {REV_ID} --user=admin --force=true --porcelain`
    Then the return code should be 148
    Then STDERR should contain:
      """
      Error: Invalid revision ID. HTTP code: 404
      """

    When I run `wp rest post-revision list {POST_ID} --user=admin --format=count`
    Then STDOUT should be:
      """
      2
      """

    When I run `wp rest post list --format=count`
    Then STDOUT should be:
      """
      2
      """

    When I run `wp rest post delete {POST_ID} --user=admin --force=true --porcelain`
    Then STDOUT should be:
      """
      {POST_ID}
      """

    When I run `wp rest post list --format=count`
    Then STDOUT should be:
      """
      1
      """
