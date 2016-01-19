<?php

namespace WP_REST_CLI;

use WP_CLI;
use WP_REST_Request;
use WP_REST_Response;

class RestCommand {

	private $name;
	private $route;
	private $resource_identifier;
	private $default_fields;

	public function __construct( $name, $route, $resource_identifier = '', $default_fields = array() ) {
		$this->name = $name;
		$this->route = $route;
		$this->resource_identifier = $resource_identifier;
		$this->default_fields = $default_fields;
	}

	/**
	 * Create a new item.
	 *
	 * @subcommand create
	 */
	public function create_item( $args, $assoc_args ) {

	}

	/**
	 * Delete an existing item.
	 *
	 * @subcommand delete
	 */
	public function delete_item( $args, $assoc_args ) {
		$request = new WP_REST_Request( 'DELETE', $this->get_filled_route( $args ) );
		$response = $this->do_request( $request );
		WP_CLI::success( "Deleted {$this->name}." );
	}

	/**
	 * Get a single item.
	 *
	 * @subcommand get
	 */
	public function get_item( $args, $assoc_args ) {
		$request = new WP_REST_Request( 'GET', $this->get_filled_route( $args ) );
		$response = $this->do_request( $request );
		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $response->get_data() );
	}

	/**
	 * List all items.
	 *
	 * @subcommand list
	 */
	public function list_items( $args, $assoc_args ) {
		$request = new WP_REST_Request( 'GET', $this->get_base_route() );
		$response = $this->do_request( $request );
		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $response->get_data() );
	}

	/**
	 * Update an existing item.
	 *
	 * @subcommand update
	 */
	public function update_item( $args, $assoc_args ) {
		$request = new WP_REST_Request( 'POST', $this->get_filled_route( $args ) );
		$response = $this->do_request( $request );
		WP_CLI::success( "Deleted {$this->name}." );
	}

	/**
	 * Do a REST Request
	 *
	 * @param WP_REST_Request
	 * @return WP_REST_Response
	 */
	private function do_request( $request ) {
		$response = rest_do_request( $request );
		if ( $error = $response->as_error() ) {
			WP_CLI::error( $error );
		}
		return $response;
	}

	/**
	 * Get Formatter object based on supplied parameters.
	 *
	 * @param array $assoc_args Parameters passed to command. Determines formatting.
	 * @return \WP_CLI\Formatter
	 */
	protected function get_formatter( &$assoc_args ) {
		if ( ! empty( $assoc_args['fields'] ) ) {
			if ( is_string( $assoc_args['fields'] ) ) {
				$fields = explode( ',', $assoc_args['fields'] );
			} else {
				$fields = $assoc_args['fields'];
			}
		} else {
			$fields = $this->default_fields;
		}
		return new \WP_CLI\Formatter( $assoc_args, $fields );
	}

	/**
	 * Get the base route for this resource
	 *
	 * @return string
	 */
	private function get_base_route() {
		return substr( $this->route, 0, strlen( $this->route ) - strlen( $this->resource_identifier ) );
	}

	/**
	 * Fill the route based on provided $args
	 */
	private function get_filled_route( $args ) {
		return rtrim( $this->get_base_route(), '/' ) . '/' . $args[0];
	}

}
