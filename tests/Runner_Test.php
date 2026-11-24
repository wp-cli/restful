<?php

use WP_CLI\Tests\TestCase;

class Runner_Test extends TestCase {
	protected $method;
	protected $http_method;
	protected $runner;
	protected $original_runner_state;

	public function set_up() {
		$runner     = new \WP_REST_CLI\Runner();
		$reflection = new \ReflectionClass( $runner );
		$method     = $reflection->getMethod( 'discover_wp_api' );
		$method->setAccessible( true );
		$http_method = $reflection->getMethod( 'get_http_target' );
		$http_method->setAccessible( true );

		$this->runner = \WP_CLI::get_runner();
		$this->store_runner_state();

		$this->method      = $method;
		$this->http_method = $http_method;
	}

	public function tear_down() {
		$this->restore_runner_state();
	}

	private function store_runner_state() {
		$runner_ref = new \ReflectionObject( $this->runner );
		$keys       = array( 'config', 'alias', 'aliases' );
		foreach ( $keys as $key ) {
			$property = $runner_ref->getProperty( $key );
			$property->setAccessible( true );
			$this->original_runner_state[ $key ] = $property->getValue( $this->runner );
		}
	}

	private function restore_runner_state() {
		$runner_ref = new \ReflectionObject( $this->runner );
		foreach ( $this->original_runner_state as $key => $value ) {
			$property = $runner_ref->getProperty( $key );
			$property->setAccessible( true );
			$property->setValue( $this->runner, $value );
		}
	}

	private function set_runner_state( $config, $alias, $aliases ) {
		$runner_ref = new \ReflectionObject( $this->runner );
		$state      = array(
			'config'  => $config,
			'alias'   => $alias,
			'aliases' => $aliases,
		);
		foreach ( $state as $key => $value ) {
			$property = $runner_ref->getProperty( $key );
			$property->setAccessible( true );
			$property->setValue( $this->runner, $value );
		}
	}

	public function test_single_link_header() {
		$link_headers = '<https://example.com/wp-json/>; rel="https://api.w.org/"';
		$res          = $this->method->invokeArgs( null, array( $link_headers ) );
		$this->assertSame( 'https://example.com/wp-json/', $res );
	}


	public function test_multiple_link_header() {
		$link_headers = '<https://example.com/wp-json/>;rel="https://api.w.org/",<https://wp.me/8laBl>;rel=shortlink';
		$res          = $this->method->invokeArgs( null, array( $link_headers ) );
		$this->assertSame( 'https://example.com/wp-json/', $res );
	}

	public function test_multiple_link_header_with_space() {
		$link_headers = ' <https://example.com/wp-json/> ; rel="https://api.w.org/", <https://wp.me/8laBl>; rel=shortlink';
		$res          = $this->method->invokeArgs( null, array( $link_headers ) );
		$this->assertSame( 'https://example.com/wp-json/', $res );
	}

	public function test_wp_api_not_found() {
		$link_headers = '<https://wp.me/8laBl>; rel=shortlink';
		$res          = $this->method->invokeArgs( null, array( $link_headers ) );
		$this->assertFalse( $res );
	}

	public function test_get_http_target_from_runtime_config() {
		$this->set_runner_state( array( 'http' => 'wordpress.com' ), null, array() );
		$res = $this->http_method->invokeArgs( null, array() );
		$this->assertSame( 'wordpress.com', $res );
	}

	public function test_get_http_target_from_alias_config() {
		$this->set_runner_state(
			array( 'http' => null ),
			'test-http',
			array(
				'test-http' => array(
					'http' => 'wordpress.com',
				),
			)
		);
		$res = $this->http_method->invokeArgs( null, array() );
		$this->assertSame( 'wordpress.com', $res );
	}

	public function test_get_http_target_not_found() {
		$this->set_runner_state( array( 'http' => null ), null, array() );
		$res = $this->http_method->invokeArgs( null, array() );
		$this->assertFalse( $res );
	}
}
