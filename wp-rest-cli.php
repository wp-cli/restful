<?php
/**
 * Use WP-API at the command line.
 */

require_once __DIR__ . '/inc/RestCommand.php';
require_once __DIR__ . '/inc/Runner.php';

if ( class_exists( 'WP_CLI' ) ) {
	\WP_REST_CLI\Runner::deregister_core_commands();
	\WP_REST_CLI\Runner::load_remote_commands();
	WP_CLI::add_hook( 'after_wp_load', '\WP_REST_CLI\Runner::after_wp_load' );
}
