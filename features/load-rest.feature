Feature: Manage WordPress through endpoints locally

  Background:
    Given a WP install
	When I run `wp core version`
	Then STDOUT should be a version string >= 4.7

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

  Scenario: Debug flag should identify errored parts of the bootstrap process
    Given a wp-content/mu-plugins/rest-endpoint.php file:
      """
      <?php
      add_action( 'rest_api_init', function() {
        register_rest_route( 'myplugin/v1', '/books', array(
          'methods' => 'GET',
          'callback' => '__return_true',
        ) );
      });
      """

    When I run `wp rest --debug`
    Then STDERR should contain:
      """
      Debug (rest): No schema title found for /myplugin/v1/books, skipping REST command registration.
      """
