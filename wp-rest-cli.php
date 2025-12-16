<?php
/**
 * Use WP-API at the command line.
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

require_once __DIR__ . '/inc/RestCommand.php';
require_once __DIR__ . '/inc/Runner.php';

WP_REST_CLI\Runner::load_remote_commands();
WP_CLI::add_hook( 'after_wp_load', [ WP_REST_CLI\Runner::class, 'after_wp_load' ] );
