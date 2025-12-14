<?php

class Runner_Test extends PHPUnit_Framework_TestCase {

	public function setUp() {
		$runner     = new \WP_REST_CLI\Runner();
		$reflection = new \ReflectionClass( $runner );
		$method     = $reflection->getMethod( 'discover_wp_api' );
		$method->setAccessible( true );

		$this->method = $method;
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
}
