<?php

namespace WP_REST_CLI;

use WP_CLI;
use WP_CLI\Utils;

class RestCommand {

	private $scope = 'internal';
	private $api_url = '';
	private $name;
	private $route;
	private $resource_identifier;
	private $default_fields = array();

	public function __construct( $name, $route ) {
		$this->name = $name;
		$parsed_args = preg_match_all( '#\([^\)]+\)#', $route, $matches );
		$this->resource_identifier = ! empty( $matches[0] ) ? array_pop( $matches[0] ) : null;
		$this->route = rtrim( $route );
	}
	
	/**
	 * Set the scope of the REST requests
	 *
	 * @param string $scope
	 */
	public function set_scope( $scope ) {
		$this->scope = $scope;
	}
	
	/**
	 * Set the API url for the REST requests
	 *
	 * @param string $api_url
	 */
	public function set_api_url( $api_url ) {
		$this->api_url = $api_url;
	}
	
	/**
	 * Set the default fields on this resource
	 *
	 * @param array $fields
	 */
	public function set_default_fields( $fields ) {
		$this->default_fields = $fields;
	}

	/**
	 * Create a new item.
	 *
	 * @subcommand create
	 */
	public function create_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'POST', $this->get_base_route(), $assoc_args );
		WP_CLI::success( "Created {$this->name}." );
	}

	/**
	 * Delete an existing item.
	 *
	 * @subcommand delete
	 */
	public function delete_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'DELETE', $this->get_filled_route( $args ), $assoc_args );
		WP_CLI::success( "Deleted {$this->name}." );
	}

	/**
	 * Get a single item.
	 *
	 * @subcommand get
	 */
	public function get_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'GET', $this->get_filled_route( $args ), $assoc_args );
		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $body );
	}

	/**
	 * List all items.
	 *
	 * @subcommand list
	 */
	public function list_items( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'GET', $this->get_base_route(), $assoc_args );
		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $body );
	}

	/**
	 * Update an existing item.
	 *
	 * @subcommand update
	 */
	public function update_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'POST', $this->get_filled_route( $args ), $assoc_args );
		WP_CLI::success( "Updated {$this->name}." );
	}

	/**
	 * Do a REST Request
	 *
	 * @param string $method
	 * 
	 */
	private function do_request( $method, $route, $assoc_args ) {
		if ( 'internal' === $this->scope ) {
			$request = new \WP_REST_Request( $method, $route );
			if ( in_array( $method, array( 'POST', 'PUT' ) ) ) {
				$request->set_body_params( $assoc_args );
			} else {
				$request->set_url_params( $assoc_args );
			}
			$response = rest_do_request( $request );
			if ( $error = $response->as_error() ) {
				WP_CLI::error( $error );
			}
			return array( $response->get_status(), $response->get_data() );
		} else if ( 'http' === $this->scope ) {
			$response = Utils\http_request( $method, rtrim( $this->api_url, '/' ) . $route, $assoc_args );
			if ( $response->status_code >= 400 ) {
				switch( $response->status_code ) {
					case 404:
						WP_CLI::error( "No {$this->name} found." );
						break;
					default:
						WP_CLI::error( 'Could not complete request.' );
						break;
				}
			}
			return array( $response->status_code, json_decode( $response->body, true ) );
		}
		WP_CLI::error( 'Invalid scope for REST command.' );
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
