<?php 

namespace WP_REST_CLI;

use WP_CLI;
use WP_REST_Server;

class Runner {
	
	public static function before_wp_load() {
		
	}
	
	public static function after_wp_load() {
		if ( ! class_exists( 'WP_REST_Server' ) ) {
			return;
		}

		global $wp_rest_server;

		define( 'REST_REQUEST', true );

		$wp_rest_server = new WP_REST_Server;
		do_action( 'rest_api_init', $wp_rest_server );

		foreach( $wp_rest_server->get_routes() as $route => $endpoints ) {

			if ( false === stripos( $route, '/wp/v2/comments' ) ) {
				continue;
			}

			$route_data = $wp_rest_server->get_data_for_route( $route, $endpoints, 'help' );
			$parent = "rest {$route_data['schema']['title']}";
			$fields = array();
			foreach( $route_data['schema']['properties'] as $key => $args ) {
				if ( in_array( 'embed', $args['context'] ) ) {
					$fields[] = $key;
				}
			}

			foreach( $endpoints as $endpoint ) {

				$parsed_args = preg_match_all( '#\([^\)]+\)#', $route, $matches );
				$resource_id = ! empty( $matches[0] ) ? array_pop( $matches[0] ) : null;
				$trimmed_route = rtrim( $route );
				$is_singular = $resource_id === substr( $trimmed_route, - strlen( $resource_id ) );

				$command = $method = '';
				// List a collection
				if ( array( 'GET' => true ) == $endpoint['methods']
					&& ! $is_singular ) {
					$command = 'list';
				}

				// Create a specific resource
				if ( array( 'POST' => true ) == $endpoint['methods']
					&& ! $is_singular ) {
					$command = 'create';
				}

				// Get a specific resource
				if ( array( 'GET' => true ) == $endpoint['methods']
					&& $is_singular ) {
					$command = 'get';
				}

				// Update a specific resource
				if ( array_key_exists( 'POST', $endpoint['methods'] )
					&& $is_singular ) {
					$command = 'update';
				}

				// Delete a specific resource
				if ( array( 'DELETE' => true ) == $endpoint['methods']
					&& $is_singular ) {
					$command = 'delete';
				}

				if ( empty( $command ) ) {
					continue;
				}

				$rest_command = new RestCommand( $route_data['schema']['title'], $trimmed_route, $resource_id, $fields );

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

				$methods = array(
					'list'       => 'list_items',
					'create'     => 'create_item',
					'delete'     => 'delete_item',
					'get'        => 'get_item',
					'update'     => 'update_item',
				);

				WP_CLI::add_command( "{$parent} {$command}", array( $rest_command, $methods[ $command ] ), array(
					'synopsis' => $synopsis,
				) );

			}

		}
	}
	
}