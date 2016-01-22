<?php
/**
 * Use WP-API at the command line.
 */

require_once __DIR__ . '/inc/RestCommand.php';
require_once __DIR__ . '/inc/Runner.php';

WP_CLI::add_hook( 'before_wp_load', '\WP_REST_CLI\Runner::before_wp_load' );
WP_CLI::add_hook( 'after_wp_load', '\WP_REST_CLI\Runner::after_wp_load' );

