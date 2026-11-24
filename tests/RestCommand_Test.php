<?php

use WP_CLI\Tests\TestCase;
use WP_REST_CLI\RestCommand;

class RestCommand_Test extends TestCase {

	/** @var \ReflectionMethod */
	protected $method;

	/** @var RestCommand */
	protected $command;

	public function set_up() {
		$this->command = new RestCommand( 'post', '/wp/v2/posts/(?P<id>[\\d]+)', array() );
		$this->command->set_scope( 'http' );

		$reflection   = new \ReflectionClass( $this->command );
		$this->method = $reflection->getMethod( 'get_http_request_args' );
		$this->method->setAccessible( true );
	}

	public function test_http_request_args_include_basic_auth_header() {
		$this->command->set_api_url( 'https://example.com/wp-json' );
		$this->command->set_auth(
			array(
				'type'     => 'basic',
				'username' => 'wpuser',
				'password' => 'wppass',
			)
		);

		$result = $this->method->invokeArgs(
			$this->command,
			array(
				'OPTIONS',
				'/wp/v2/posts',
				array( 'context' => 'edit' ),
			)
		);

		$this->assertSame( 'GET', $result[0] );
		$this->assertSame( 'https://example.com/wp-json/wp/v2/posts', $result[1] );
		$this->assertSame(
			array(
				'context' => 'edit',
				'_method' => 'OPTIONS',
			),
			$result[2]
		);
		$this->assertSame( 'Basic d3B1c2VyOndwcGFzcw==', $result[3]['Authorization'] );
	}

	public function test_http_request_args_can_be_filtered_for_custom_auth() {
		$this->command->set_api_url( 'https://hooked.example/wp-json' );
		$this->command->set_auth(
			array(
				'type'     => 'basic',
				'username' => 'old-user',
				'password' => 'old-pass',
			)
		);

		\WP_CLI::add_hook(
			'restful_http_request_args',
			function ( $request_args ) {
				if ( ! isset( $request_args['url'] ) || 'https://hooked.example/wp-json/wp/v2/posts' !== $request_args['url'] ) {
					return $request_args;
				}
				$request_args['headers']    = array(
					'Authorization' => '******',
					'X-Custom-Auth' => 'jwt',
				);
				$request_args['assoc_args'] = array(
					'token' => 'abc123',
				);
				return $request_args;
			}
		);

		$result = $this->method->invokeArgs(
			$this->command,
			array(
				'GET',
				'/wp/v2/posts',
				array(),
			)
		);

		$this->assertSame(
			array(
				'Authorization' => '******',
				'X-Custom-Auth' => 'jwt',
			),
			$result[3]
		);
		$this->assertSame( array( 'token' => 'abc123' ), $result[2] );
	}
}
