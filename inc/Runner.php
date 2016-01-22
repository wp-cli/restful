<?php 

namespace WP_REST_CLI;

use WP_CLI;
use WP_CLI\Utils;
use WP_REST_Request;
use WP_REST_Server;

class Runner {
	
	/**
	 * When --http=domain.com is passed as global arg, register REST for it
	 */
	public static function load_remote_commands() {
		
		$global_args = array();
		foreach( array_slice( $GLOBALS['argv'], 1 ) as $maybe_arg ) {
			if ( 0 === strpos( $maybe_arg, '--' ) ) {
				$global_args[] = $maybe_arg;
			} else {
				break;
			}
		}
		if ( ! empty( $global_args ) ) {
			$configurator = WP_CLI::get_configurator();
			list( $args, $assoc_args, $runtime_config ) = $configurator->parse_args( $global_args );
			if ( ! empty( $assoc_args['http'] ) ) {
				$api_url = self::auto_discover_api( $assoc_args['http'] );
				if ( $api_url ) {
					$api_index = self::get_api_index( $api_url );
					if ( $api_index ) {
						foreach( $api_index['routes'] as $route => $route_data ) {
							if ( false === stripos( $route, '/wp/v2/comments' )
								&& false === stripos( $route, '/wp/v2/tags' ) ) {
								continue;
							}
							$name = $route_data['schema']['title'];
							$rest_command = new RESTCommand( $name, $route );
							$rest_command->set_scope( 'http' );
							$rest_command->set_api_url( $api_url );
							self::register_route_commands( $rest_command, $route, $route_data, array( 'when' => 'before_wp_load' ) );
						}
					}
				}
			}
		}
		
	}
	
	public static function after_wp_load() {
		if ( ! class_exists( 'WP_REST_Server' ) ) {
			return;
		}

		global $wp_rest_server;

		define( 'REST_REQUEST', true );

		$wp_rest_server = new WP_REST_Server;
		do_action( 'rest_api_init', $wp_rest_server );
		
		$request = new WP_REST_Request( 'GET', '/' );
		$request->set_param( 'context', 'help' );
		$response = $wp_rest_server->dispatch( $request );
		$response_data = $response->get_data();
		if ( empty( $response_data ) ) {
			return;
		}
		
		foreach( $response_data['routes'] as $route => $route_data ) {
			if ( false === stripos( $route, '/wp/v2/comments' )
				&& false === stripos( $route, '/wp/v2/tags' ) ) {
				continue;
			}
			$name = $route_data['schema']['title'];
			$rest_command = new RESTCommand( $name, $route );
			self::register_route_commands( $rest_command, $route, $route_data );
		}
	}
	
	/**
	 * Auto-discover the WP-API index from a given URL
	 *
	 * @param string $url
	 * @return string|false
	 */
	private static function auto_discover_api( $url ) {
		if ( false === stripos( $url, 'http://' ) && false === stripos( $url, 'https://' ) ) {
			$url = 'http://' . $url;
		}
		$response = Utils\http_request( 'HEAD', $url );
		if ( empty( $response->headers['link'] ) ) {
			return false;
		}
		$bits = explode( ';', $response->headers['link'] );
		if ( 'rel="https://api.w.org/"' !== trim( $bits[1] ) ) {
			return false;
		}
		return trim( $bits[0], '<>' );
	}
	
	/**
	 * Get the index data from an API url
	 *
	 * @param string $api_url
	 * @return array|false
	 */
	private static function get_api_index( $api_url ) {
		$query_char = false !== strpos( $api_url, '?' ) ? '&' : '?';
		$api_url .= $query_char . 'context=help';
		$response = Utils\http_request( 'GET', $api_url );
		if ( empty( $response->body ) ) {
			return false;
		}
		return json_decode( $response->body, true ); 
	}
	
	/**
	 * Register WP-CLI commands for all endpoints on a route
	 *
	 * @param string
	 * @param array $endpoints
	 */
	private function register_route_commands( $rest_command, $route, $route_data, $command_args = array() ) {
		
		$parent = "rest {$route_data['schema']['title']}";
		$fields = array();
		foreach( $route_data['schema']['properties'] as $key => $args ) {
			if ( in_array( 'embed', $args['context'] ) ) {
				$fields[] = $key;
			}
		}
		$rest_command->set_default_fields( $fields );

		foreach( $route_data['endpoints'] as $endpoint ) {

			$parsed_args = preg_match_all( '#\([^\)]+\)#', $route, $matches );
			$resource_id = ! empty( $matches[0] ) ? array_pop( $matches[0] ) : null;
			$trimmed_route = rtrim( $route );
			$is_singular = $resource_id === substr( $trimmed_route, - strlen( $resource_id ) );

			$command = $method = '';
			// List a collection
			if ( array( 'GET' ) == $endpoint['methods']
				&& ! $is_singular ) {
				$command = 'list';
			}

			// Create a specific resource
			if ( array( 'POST' ) == $endpoint['methods']
				&& ! $is_singular ) {
				$command = 'create';
			}

			// Get a specific resource
			if ( array( 'GET' ) == $endpoint['methods']
				&& $is_singular ) {
				$command = 'get';
			}

			// Update a specific resource
			if ( in_array( 'POST', $endpoint['methods'] )
				&& $is_singular ) {
				$command = 'update';
			}

			// Delete a specific resource
			if ( array( 'DELETE' ) == $endpoint['methods']
				&& $is_singular ) {
				$command = 'delete';
			}

			if ( empty( $command ) ) {
				continue;
			}

			$synopsis = array();
			if ( in_array( $command, array( 'delete', 'get', 'update' ) ) ) {
				$synopsis[] = array(
					'name'        => 'id',
					'type'        => 'positional',
					'description' => 'The id for the resource.',
					'optional'    => false,
				);
			}

			if ( ! empty( $endpoint['args'] ) ) {
				foreach( $endpoint['args'] as $name => $args ) {
					$synopsis[] = array(
						'name'        => $name,
						'type'        => 'assoc',
						'description' => ! empty( $args['description'] ) ? $args['description'] : '',
						'optional'    => empty( $args['required'] ) ? true : false,
					);
				}
			}

			if ( in_array( $command, array( 'list', 'get' ) ) ) {
				$synopsis[] = array(
					'name'        => 'fields',
					'type'        => 'assoc',
					'description' => 'Limit response to specific fields. Defaults to all fields.',
					'optional'    => true,
				);
				$synopsis[] = array(
					'name'        => 'format',
					'type'        => 'assoc',
					'description' => 'Limit response to specific fields. Defaults to all fields.',
					'optional'    => true,
				);
			}

			// @todo this is a hack
			$synopsis[] = array(
				'name'        => 'http',
				'type'        => 'assoc',
				'optional'    => true,
			);

			$methods = array(
				'list'       => 'list_items',
				'create'     => 'create_item',
				'delete'     => 'delete_item',
				'get'        => 'get_item',
				'update'     => 'update_item',
			);

			WP_CLI::add_command( "{$parent} {$command}", array( $rest_command, $methods[ $command ] ), array(
				'synopsis' => $synopsis,
				'when'     => ! empty( $command_args['when'] ) ? $command_args['when'] : '',
			) );
		}
	}
	
}