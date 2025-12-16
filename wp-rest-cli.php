<?php
/**
 * Use WP-API at the command line.
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$wp_rest_cli_autoloader = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wp_rest_cli_autoloader ) ) {
	require_once $wp_rest_cli_autoloader;
}

WP_REST_CLI\Runner::load_remote_commands();
WP_CLI::add_hook( 'after_wp_load', [ WP_REST_CLI\Runner::class, 'after_wp_load' ] );
