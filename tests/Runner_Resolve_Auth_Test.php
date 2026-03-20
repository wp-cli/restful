<?php

use WP_CLI\Tests\TestCase;

class Runner_Resolve_Auth_Test extends TestCase {

	private $saved_env = array();

	public function set_up() {
		foreach ( array( 'WP_REST_CLI_AUTH_USER', 'WP_REST_CLI_AUTH_PASSWORD' ) as $var ) {
			$val                     = getenv( $var );
			$this->saved_env[ $var ] = false === $val ? false : $val;
			putenv( $var );
		}
	}

	public function tear_down() {
		foreach ( $this->saved_env as $var => $val ) {
			if ( false === $val ) {
				putenv( $var );
			} else {
				putenv( "{$var}={$val}" );
			}
		}
	}

	public function test_no_auth_when_nothing_set() {
		$auth = \WP_REST_CLI\Runner::resolve_auth( 'example.com' );
		$this->assertSame( array(), $auth );
	}

	public function test_auth_from_config() {
		$auth = \WP_REST_CLI\Runner::resolve_auth(
			'example.com',
			array(
				'http_user'     => 'admin',
				'http_password' => 'secret',
			)
		);
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => 'admin',
				'password' => 'secret',
			),
			$auth
		);
	}

	public function test_config_allows_empty_password() {
		$auth = \WP_REST_CLI\Runner::resolve_auth(
			'example.com',
			array( 'http_user' => 'admin' )
		);
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => 'admin',
				'password' => '',
			),
			$auth
		);
	}

	public function test_env_vars_override_config() {
		putenv( 'WP_REST_CLI_AUTH_USER=envuser' );
		putenv( 'WP_REST_CLI_AUTH_PASSWORD=envpass' );
		$auth = \WP_REST_CLI\Runner::resolve_auth(
			'example.com',
			array(
				'http_user'     => 'cfguser',
				'http_password' => 'cfgpass',
			)
		);
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => 'envuser',
				'password' => 'envpass',
			),
			$auth
		);
	}

	public function test_env_user_without_env_password_uses_empty_password() {
		putenv( 'WP_REST_CLI_AUTH_USER=envuser' );
		$auth = \WP_REST_CLI\Runner::resolve_auth( 'example.com' );
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => 'envuser',
				'password' => '',
			),
			$auth
		);
	}

	public function test_empty_env_user_skips_env_auth() {
		putenv( 'WP_REST_CLI_AUTH_USER=' );
		putenv( 'WP_REST_CLI_AUTH_PASSWORD=envpass' );
		$auth = \WP_REST_CLI\Runner::resolve_auth( 'example.com' );
		$this->assertSame( array(), $auth );
	}

	public function test_url_credentials_override_env_vars() {
		putenv( 'WP_REST_CLI_AUTH_USER=envuser' );
		putenv( 'WP_REST_CLI_AUTH_PASSWORD=envpass' );
		$auth = \WP_REST_CLI\Runner::resolve_auth( 'http://urluser:urlpass@example.com' );
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => 'urluser',
				'password' => 'urlpass',
			),
			$auth
		);
	}

	public function test_url_credentials_without_scheme() {
		$auth = \WP_REST_CLI\Runner::resolve_auth( 'urluser:urlpass@example.com' );
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => 'urluser',
				'password' => 'urlpass',
			),
			$auth
		);
	}

	public function test_url_credentials_with_https_scheme() {
		$auth = \WP_REST_CLI\Runner::resolve_auth( 'https://urluser:urlpass@example.com' );
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => 'urluser',
				'password' => 'urlpass',
			),
			$auth
		);
	}

	public function test_url_credentials_override_config() {
		$auth = \WP_REST_CLI\Runner::resolve_auth(
			'http://urluser:urlpass@example.com',
			array(
				'http_user'     => 'cfguser',
				'http_password' => 'cfgpass',
			)
		);
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => 'urluser',
				'password' => 'urlpass',
			),
			$auth
		);
	}
}
