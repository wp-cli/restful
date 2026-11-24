<?php

use WP_CLI\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

	public function test_config_casts_scalar_values() {
		$auth = \WP_REST_CLI\Runner::resolve_auth(
			'example.com',
			array(
				'http_user'     => 'admin',
				'http_password' => 0,
			)
		);
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => 'admin',
				'password' => '0',
			),
			$auth
		);
	}

	public function test_config_allows_username_zero() {
		$auth = \WP_REST_CLI\Runner::resolve_auth(
			'example.com',
			array(
				'http_user'     => '0',
				'http_password' => 'cfgpass',
			)
		);
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => '0',
				'password' => 'cfgpass',
			),
			$auth
		);
	}

	public function test_config_values_are_not_url_decoded() {
		$auth = \WP_REST_CLI\Runner::resolve_auth(
			'example.com',
			array(
				'http_user'     => 'cfguser',
				'http_password' => 'pa%20ss',
			)
		);
		$this->assertSame( 'pa%20ss', $auth['password'] );
	}

	public function test_config_ignores_non_scalar_user() {
		$auth = \WP_REST_CLI\Runner::resolve_auth(
			'example.com',
			array( 'http_user' => array( 'admin' ) )
		);
		$this->assertSame( array(), $auth );
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

	/**
	 * @dataProvider provide_url_credentials
	 */
	#[DataProvider( 'provide_url_credentials' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function test_url_credentials( $url, $expected_user, $expected_pass ) {
		$auth = \WP_REST_CLI\Runner::resolve_auth( $url );
		$this->assertSame(
			array(
				'type'     => 'basic',
				'username' => $expected_user,
				'password' => $expected_pass,
			),
			$auth
		);
	}

	public static function provide_url_credentials() {
		return array(
			'no scheme'        => array( 'urluser:urlpass@example.com', 'urluser', 'urlpass' ),
			'https scheme'     => array( 'https://urluser:urlpass@example.com', 'urluser', 'urlpass' ),
			'user only'        => array( 'urluser@example.com', 'urluser', '' ),
			'user only, https' => array( 'https://urluser@example.com', 'urluser', '' ),
			'username "0"'     => array( 'https://0:urlpass@example.com', '0', 'urlpass' ),
			'percent-encoded'  => array( 'https://admin:abcd%20EFGH%201234%20ijkl@example.com', 'admin', 'abcd EFGH 1234 ijkl' ),
			'encoded @ and :'  => array( 'https://us%40er:p%3Ass@example.com', 'us@er', 'p:ss' ),
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
