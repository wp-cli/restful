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

		if ( ! isset( WP_CLI::get_runner()->config['http'] ) ) {
			return;
		}

		$http = WP_CLI::get_runner()->config['http'];
		if ( false === stripos( $http, 'http://' ) && false === stripos( $http, 'https://' ) ) {
			$http = 'http://' . $http;
		}
		$api_url = self::auto_discover_api( $http );
		if ( ! $api_url ) {
			WP_CLI::error( "Couldn't auto-discover WP REST API endpoint from {$http}." );
		}
		$bits = parse_url( $http );
		$auth = array();
		if ( ! empty( $bits['user'] ) ) {
			$auth['type'] = 'basic';
			$auth['username'] = $bits['user'];
			$auth['password'] = ! empty( $bits['pass'] ) ? $bits['pass'] : '';
		}
		$api_index = self::get_api_index( $api_url, $auth );
		if ( ! $api_index ) {
			WP_CLI::error( "Couldn't find index data from {$api_url}." );
		}
		foreach( $api_index['routes'] as $route => $route_data ) {
			if ( empty( $route_data['schema']['title'] ) ) {
				WP_CLI::debug( "No schema title found for {$route}, skipping REST command registration.", 'rest' );
				continue;
			}
			$name = $route_data['schema']['title'];
			$rest_command = new RESTCommand( $name, $route, $route_data['schema'] );
			$rest_command->set_scope( 'http' );
			$rest_command->set_api_url( $api_url );
			$rest_command->set_auth( $auth );
			self::register_route_commands( $rest_command, $route, $route_data, array( 'when' => 'before_wp_load' ) );
		}

	}

	public static function after_wp_load() {
		if ( ! class_exists( 'WP_REST_Server' ) ) {
			return;
		}

		global $wp_rest_server;

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
			if ( empty( $route_data['schema']['title'] ) ) {
				WP_CLI::debug( "No schema title found for {$route}, skipping REST command registration.", 'rest' );
				continue;
			}
			$name = $route_data['schema']['title'];
			$rest_command = new RESTCommand( $name, $route, $route_data['schema'] );
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
		$response = Utils\http_request( 'HEAD', $url );
		if ( $response->status_code >= 400 ) {
			return false;
		}
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
	private static function get_api_index( $api_url, $auth ) {
		// this method to add a query is weak; may cause problems, e.g. duplicate keynames
		$query_char = false !== strpos( $api_url, '?' ) ? '&' : '?';
		$api_url .= $query_char . 'context=help';

		$headers = array();
		if ( ! empty( $auth ) && 'basic' === $auth['type'] ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( $auth['username'] . ':' . $auth['password'] );
		}
		$response = Utils\http_request( 'GET', $api_url, null, $headers );
		if ( $response->status_code >= 400 ) {
			return false;
		}
		if ( empty( $response->body ) ) {
			return false;
		}
		return json_decode( $response->body, true );
	}

	private static function endsWith( $needle, $haystack ) {
		$length = strlen( $needle );
		return substr( $haystack, - $length, $length ) === $needle;
	}

	/**
	 * Register WP-CLI commands for all endpoints on a route
	 *
	 * @param string
	 * @param array $endpoints
	 */
	private static function register_route_commands( $rest_command, $route, $route_data, $command_args = array() ) {

		$parent = "rest {$route_data['schema']['title']}";

		$supported_commands = array();
		foreach( $route_data['endpoints'] as $endpoint ) {
			$parsed_args = preg_match_all( '#\(\?P([^\s\)]+)\)#', $route, $all_path_vars );
			$parsed_args = preg_match_all( '#\(\?P<([^>\s]+)>([^\s\)]+)\)#', $route, $named_path_vars );
			$trimmed_route = rtrim( $route );

			// check for unsupported routes
			$me_at_end = ( false !== stripos( $trimmed_route, '/me', strlen( $trimmed_route ) - 3 ) );
			if ( $me_at_end ) {
				// TODO code does not yet support understanding me as a required parameter of another route
				WP_CLI::debug( "Route {$trimmed_route} ends with 'me', skipping REST command registration.", 'rest' );
				continue;
			}
			if ( 0 < ( count( $all_path_vars[0] ) - count( $named_path_vars[0] ) ) ) {
				WP_CLI::debug( "Route {$trimmed_route} has unnamed path variables, skipping REST command registration.", 'rest' );
				continue;
			}

			// test if the route intends to operate on a single item or a collection
			$is_singular = self::endsWith( end( $named_path_vars[0] ), $trimmed_route);

			// save named path variables to later populate with values
			$rest_command->set_named_path_vars( $named_path_vars );

			// make named path variables required positional arguments
			if ( count( $named_path_vars[1] ) && ! empty( $endpoint['args'] ) ) {
				foreach( $endpoint['args'] as $arg_name => $arg_value ) {
					if ( ! is_array( $arg_value ) ) {
						continue;
					}
					if ( in_array( $arg_name, $named_path_vars[1], true ) ) {
						$endpoint['args'][ $arg_name ]['required'] = true;
						$endpoint['args'][ $arg_name ]['_positional'] = true;
					}
				}
			}

			// List a collection
			if ( array( 'GET' ) == $endpoint['methods']
				&& ! $is_singular ) {
				$supported_commands['list'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}

			// Create a specific resource
			if ( array( 'POST' ) == $endpoint['methods']
				&& ! $is_singular ) {
				$supported_commands['create'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}

			// Get a specific resource
			if ( array( 'GET' ) == $endpoint['methods']
				&& $is_singular ) {
				$supported_commands['get'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}

			// Update a specific resource
			if ( in_array( 'POST', $endpoint['methods'] )
				&& $is_singular ) {
				$supported_commands['update'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}

			// Delete a specific resource
			if ( array( 'DELETE' ) == $endpoint['methods']
				&& $is_singular ) {
				$supported_commands['delete'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}

			// Purge a collection of resources
			if ( array( 'DELETE' ) == $endpoint['methods']
				&& ! $is_singular ) {
				$supported_commands['purgeall'] = ! empty( $endpoint['args'] ) ? $endpoint['args'] : array();
			}
		}

		foreach( $supported_commands as $command => $endpoint_args ) {

			$synopsis = array();
			foreach( $endpoint_args as $name => $args ) {
				$arg_reg = array(
					'name'        => $name,
					'type'        => empty( $args['_positional'] ) ? 'assoc' : 'positional',
					'description' => ! empty( $args['description'] ) ? $args['description'] : '',
					'optional'    => empty( $args['required'] )
				);
				foreach( array( 'enum', 'default' ) as $key ) {
					if ( isset( $args[ $key ] ) ) {
						$new_key = 'enum' === $key ? 'options' : $key;
						$arg_reg[ $new_key ] = $args[ $key ];
					}
				}
				$synopsis[] = $arg_reg;
			}

			if ( in_array( $command, array( 'list', 'get' ) ) ) {
				$synopsis[] = array(
					'name'        => 'fields',
					'type'        => 'assoc',
					'description' => 'Limit response to specific fields. Defaults to all fields.',
					'optional'    => true,
				);
				$synopsis[] = array(
					'name'        => 'field',
					'type'        => 'assoc',
					'description' => 'Get the value of an individual field.',
					'optional'    => true,
				);
				$synopsis[] = array(
					'name'        => 'format',
					'type'        => 'assoc',
					'description' => 'Render response in a particular format.',
					'optional'    => true,
					'default'     => 'table',
					'options'     => array(
						'table',
						'json',
						'csv',
						'ids',
						'yaml',
						'count',
						'headers',
						'body',
						'envelope',
					),
				);
			}

			if ( in_array( $command, array( 'create', 'update', 'delete', 'purgeall' ) ) ) {
				$synopsis[] = array(
					'name'        => 'porcelain',
					'type'        => 'flag',
					'description' => 'Output just the id when the operation is successful.',
					'optional'    => true,
				);
			}

			$methods = array(
				'list'       => 'list_items',
				'create'     => 'create_item',
				'delete'     => 'delete_item',
				'get'        => 'get_item',
				'update'     => 'update_item',
				'purgeall'   => 'purgeall_items',
			);

			$before_invoke = null;
			if ( empty( $command_args['when'] ) && WP_CLI::get_config( 'debug' ) ) {
				$before_invoke = function() {
					if ( ! defined( 'SAVEQUERIES' ) ) {
						define( 'SAVEQUERIES', true );
					}
				};
			}

			WP_CLI::add_command( "{$parent} {$command}", array( $rest_command, $methods[ $command ] ), array(
				'synopsis'      => $synopsis,
				'when'          => ! empty( $command_args['when'] ) ? $command_args['when'] : '',
				'before_invoke' => $before_invoke,
			) );

			if ( 'list' === $command ) {
				WP_CLI::add_command( "{$parent} diff", array( $rest_command, 'diff_items' ), array(
					'when' => ! empty( $command_args['when'] ) ? $command_args['when'] : '',
				) );
			}

			if ( 'create' === $command ) {
				// Reuse synopsis from 'create' command
				$generate_synopsis = array();
				$generate_synopsis[] = array(
					'name'        => 'count',
					'type'        => 'assoc',
					'description' => 'Number of items to generate.',
					'optional'    => true,
					'default'     => 10,
				);
				$generate_synopsis[] = array(
					'name'        => 'format',
					'type'        => 'assoc',
					'description' => 'Render generation in specific format.',
					'optional'    => true,
					'default'     => 'progress',
					'options'     => array(
						'progress',
						'ids',
					)
				);
				// Reuse synopsis from 'create' command
				$generate_synopsis = array_merge( $generate_synopsis, $synopsis );
				WP_CLI::add_command( "{$parent} generate", array( $rest_command, 'generate_items' ), array(
					'synopsis'    => $generate_synopsis,
					'when'        => ! empty( $command_args['when'] ) ? $command_args['when'] : '',
				) );
			}

			if ( 'get' === $command && array_key_exists( 'update', $supported_commands ) ) {
				$edit_synopsis = $synopsis;
				foreach ( $edit_synopsis as $syn_key => $syn_value ) {
					if ( true === $syn_value['optional'] ) {
						unset( $edit_synopsis[ $syn_key ] );
					}
				}
				WP_CLI::add_command( "{$parent} edit", array( $rest_command, 'edit_item' ), array(
					'synopsis'      => $edit_synopsis,
					'when'          => ! empty( $command_args['when'] ) ? $command_args['when'] : '',
				) );
			}
		}
	}
}
