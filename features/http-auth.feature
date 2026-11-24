Feature: Authenticate remote requests with application passwords

  Background:
    Given a WP install
    And a PHP built-in web server
    And I run `wp option update home http://localhost:8080`
    And I run `wp option update siteurl http://localhost:8080`
    And I run `wp config set WP_ENVIRONMENT_TYPE local`
    And I run `wp user application-password create 1 restful --porcelain`
    And save STDOUT as {APP_PASSWORD}

  Scenario: Requests without credentials are not authenticated
    When I try `wp --http=localhost:8080 rest user get 1 --context=edit --field=email`
    Then STDERR should contain:
      """
      {"status":401}
      """
    And the return code should be 1

  Scenario: Credentials embedded in the URL without a scheme
    When I run `wp --http="admin:{APP_PASSWORD}@localhost:8080" rest user get 1 --context=edit --field=email`
    Then STDOUT should be:
      """
      admin@example.com
      """

  Scenario: Credentials from environment variables
    When I run `WP_REST_CLI_AUTH_USER=admin WP_REST_CLI_AUTH_PASSWORD="{APP_PASSWORD}" wp --http=localhost:8080 rest user get 1 --context=edit --field=email`
    Then STDOUT should be:
      """
      admin@example.com
      """

  Scenario: Credentials from wp-cli.yml
    Given a wp-cli.yml file:
      """
      http_user: admin
      http_password: "{APP_PASSWORD}"
      """

    When I run `wp --http=localhost:8080 rest user get 1 --context=edit --field=email`
    Then STDOUT should be:
      """
      admin@example.com
      """

  Scenario: Environment variables take precedence over wp-cli.yml
    Given a wp-cli.yml file:
      """
      http_user: admin
      http_password: wrong-password
      """

    When I run `WP_REST_CLI_AUTH_USER=admin WP_REST_CLI_AUTH_PASSWORD="{APP_PASSWORD}" wp --http=localhost:8080 rest user get 1 --context=edit --field=email`
    Then STDOUT should be:
      """
      admin@example.com
      """
