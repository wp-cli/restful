<?php
/**
 * Use WP-API at the command line.
 */

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

			if ( array( 'GET' => true ) == $endpoint['methods']
				&& '(?P<id>[\d]+)' !== substr( $route, -13 ) ) {

				$callable = function( $args, $assoc_args ) use( $route, $fields ){

					$defaults = array(
						'fields'      => $fields,
						);
					$assoc_args = array_merge( $defaults, $assoc_args );

					$response = rest_do_request( new WP_REST_Request( 'GET', $route ) );
					if ( $error = $response->as_error() ) {
						WP_CLI::error( $error );
					}
					WP_CLI\Utils\format_items( 'table', $response->get_data(), $assoc_args['fields'] );
				};

				WP_CLI::add_command( "{$parent} list", $callable );
			}

		}

	}

});
