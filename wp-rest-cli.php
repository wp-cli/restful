<?php
/**
 * Use WP-API at the command line.
 */

require_once __DIR__ . '/inc/RestCommand.php';

WP_CLI::add_hook( 'after_wp_load', function(){

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

			$rest_command = new WP_REST_CLI\RestCommand( $route_data['schema']['title'], $trimmed_route, $resource_id, $fields );

			// List a collection
			if ( array( 'GET' => true ) == $endpoint['methods']
				&& ! $is_singular ) {
				WP_CLI::add_command( "{$parent} list", array( $rest_command, 'list_items' ) );
			}

			// Create a specific resource
			if ( array( 'POST' => true ) == $endpoint['methods']
				&& ! $is_singular ) {
				WP_CLI::add_command( "{$parent} create", array( $rest_command, 'create_item' ) );
			}

			// Get a specific resource
			if ( array( 'GET' => true ) == $endpoint['methods']
				&& $is_singular ) {
				WP_CLI::add_command( "{$parent} get", array( $rest_command, 'get_item' ) );
			}

			// Update a specific resource
			if ( array_key_exists( 'POST', $endpoint['methods'] )
				&& $is_singular ) {
				WP_CLI::add_command( "{$parent} update", array( $rest_command, 'update_item' ) );
			}

			// Delete a specific resource
			if ( array( 'DELETE' => true ) == $endpoint['methods']
				&& $is_singular ) {
				WP_CLI::add_command( "{$parent} delete", array( $rest_command, 'delete_item' ) );
			}

		}

	}

});
