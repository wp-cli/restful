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

	$resources = array();
	foreach( $wp_rest_server->get_routes() as $route => $endpoints ) {

		if ( false === stripos( $route, '/wp/v2/comments' ) ) {
			continue;
		}

		$command = str_replace( '/wp/v2/', 'rest ', $route );

		if ( false !== stripos( $command, '(?P<id>[\d]+)' ) ) {
			$command = str_replace( '/(?P<id>[\d]+)', '', $command );
			foreach( $endpoints as $endpoint_args ) {
				$endpoint_args['route'] = $route;
				if ( isset( $endpoint_args['methods']['GET'] ) && $endpoint_args['methods']['GET'] ) {
					$resources[ $command ]['get_item'] = $endpoint_args;
				} else if ( isset( $endpoint_args['methods']['PUT'] ) && $endpoint_args['methods']['PUT'] ) {
					$resources[ $command ]['update_item'] = $endpoint_args;
				} else if ( isset( $endpoint_args['methods']['DELETE'] ) && $endpoint_args['methods']['DELETE'] ) {
					$resources[ $command ]['delete_item'] = $endpoint_args;
				}
			}
		} else {

		}

	}

	foreach( $resources as $command_name => $details ) {

		$methods = array();

		$methods[] = <<<EOT
/**
 * Make a request to WP JSON Server
 */
private function dispatch( \$method, \$route, \$args, \$assoc_args ) {
	global \$wp_rest_server;
	if ( ! empty( \$args ) ) {
		\$route = str_replace( '(?P<id>[\d]+)', \$args[0], \$route );
	}
	\$request = new WP_REST_Request( \$method, \$route );
	return \$wp_rest_server->dispatch( \$request );
}
EOT;

		if ( ! empty( $details['get_item'] ) ) {
			$methods[] = <<<EOT
/**
 * Get item
 */
public function get( \$args, \$assoc_args ) {
	\$response = \$this->dispatch( 'GET', '{$details['get_item']['route']}', \$args, \$assoc_args );
	if ( \$response->is_error() ) {
		WP_CLI::error( \$response->as_error() );
	} else {
		\$formatter = new \WP_CLI\Formatter( \$assoc_args, array( 'id', 'post', 'author_name' ) );
		\$formatter->display_item( \$response->get_data() );
	}
}
EOT;
		}

		if ( ! empty( $details['update_item'] ) ) {
			$methods[] = <<<EOT
/**
 * Update item
 */
public function update( \$args, \$assoc_args ) {
	\$this->dispatch( 'UPDATE', '{$details['update_item']['route']}', \$args, \$assoc_args );
}
EOT;
		}

		if ( ! empty( $details['delete_item'] ) ) {
			$methods[] = <<<EOT
/**
 * Delete item
 */
public function delete( \$args, \$assoc_args ) {
	\$this->dispatch( 'DELETE', '{$details['delete_item']['route']}', \$args, \$assoc_args );
}
EOT;
		}

		$class_name = 'wp_rest_cli_' . md5( serialize( $details ) );
		$methods = implode( PHP_EOL . PHP_EOL, $methods );
		$class_format = <<<EOT
class {$class_name} extends WP_CLI_Command {

	{$methods}

}
EOT;
		eval( $class_format );
		WP_CLI::add_command( $command_name, $class_name );

	}

});
