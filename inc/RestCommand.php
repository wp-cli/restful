<?php

namespace WP_REST_CLI;

use Spyc;
use WP_CLI;
use WP_CLI\Utils;

class RestCommand {

	private $scope = 'internal';
	private $api_url = '';
	private $auth = array();
	private $name;
	private $route;
	private $resource_identifier;
	private $schema;
	private $default_context = '';

	public function __construct( $name, $route, $schema ) {
		$this->name = $name;
		$parsed_args = preg_match_all( '#\([^\)]+\)#', $route, $matches );
		$this->resource_identifier = ! empty( $matches[0] ) ? array_pop( $matches[0] ) : null;
		$this->route = rtrim( $route );
		$this->schema = $schema;
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
	 * Set the authentication for the API requests
	 *
	 * @param array $auth
	 */
	public function set_auth( $auth ) {
		$this->auth = $auth;
	}

	/**
	 * Create a new item.
	 *
	 * @subcommand create
	 */
	public function create_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'POST', $this->get_base_route(), $assoc_args );
		if ( Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
			WP_CLI::line( $body['id'] );
		} else {
			WP_CLI::success( "Created {$this->name} {$body['id']}." );
		}
	}

	/**
	 * Generate some items.
	 *
	 * @subcommand generate
	 */
	public function generate_items( $args, $assoc_args ) {

		$count = $assoc_args['count'];
		unset( $assoc_args['count'] );
		$format = $assoc_args['format'];
		unset( $assoc_args['format'] );

		$notify = false;
		if ( 'progress' === $format ) {
			$notify = \WP_CLI\Utils\make_progress_bar( 'Generating items', $count );
		}

		for ( $i = 0; $i < $count; $i++ ) {

			list( $status, $body ) = $this->do_request( 'POST', $this->get_base_route(), $assoc_args );

			if ( 'progress' === $format ) {
				$notify->tick();
			} else if ( 'ids' === $format ) {
				echo $body['id'];
				if ( $i < $count - 1 ) {
					echo ' ';
				}
			}
		}

		if ( 'progress' === $format ) {
			$notify->finish();
		}
	}

	/**
	 * Delete an existing item.
	 *
	 * @subcommand delete
	 */
	public function delete_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'DELETE', $this->get_filled_route( $args ), $assoc_args );
		if ( Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
			WP_CLI::line( $body['id'] );
		} else {
			if ( empty( $assoc_args['force'] ) ) {
				WP_CLI::success( "Trashed {$this->name} {$body['id']}." );
			} else {
				WP_CLI::success( "Deleted {$this->name} {$body['id']}." );
			}
		}
	}

	/**
	 * Get a single item.
	 *
	 * @subcommand get
	 */
	public function get_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'GET', $this->get_filled_route( $args ), $assoc_args );

		if ( ! empty( $assoc_args['fields'] ) ) {
			$fields = explode( ',', $assoc_args['fields'] );
			foreach( $body as $i => $field ) {
				if ( ! in_array( $i, $fields ) ) {
					unset( $body[ $i ] );
				}
			}
		}

		if ( ! empty( $assoc_args['format'] ) && 'body' === $assoc_args['format'] ) {
			echo json_encode( $body );
		} else {
			$formatter = $this->get_formatter( $assoc_args );
			$formatter->display_item( $body );
		}
	}

	/**
	 * List all items.
	 *
	 * @subcommand list
	 */
	public function list_items( $args, $assoc_args ) {
		if ( ! empty( $assoc_args['format'] ) && 'count' === $assoc_args['format'] ) {
			$method = 'HEAD';
		} else {
			$method = 'GET';
		}
		list( $status, $body, $headers ) = $this->do_request( $method, $this->get_base_route(), $assoc_args );
		if ( ! empty( $assoc_args['format'] ) && 'ids' === $assoc_args['format'] ) {
			$items = array_column( $body, 'id' );
		} else {
			$items = $body;
		}

		if ( ! empty( $assoc_args['fields'] ) ) {
			$fields = explode( ',', $assoc_args['fields'] );
			foreach( $items as $key => $item ) {
				foreach( $item as $i => $field ) {
					if ( ! in_array( $i, $fields ) ) {
						unset( $item[ $i ] );
					}
				}
				$items[ $key ] = $item;
			}
		}

		if ( ! empty( $assoc_args['format'] ) && 'count' === $assoc_args['format'] ) {
			echo (int) $headers['X-WP-Total'];
		} else if ( ! empty( $assoc_args['format'] ) && 'body' === $assoc_args['format'] ) {
			echo json_encode( $body );
		} else {
			$formatter = $this->get_formatter( $assoc_args );
			$formatter->display_items( $items );
		}
	}

	/**
	 * Update an existing item.
	 *
	 * @subcommand update
	 */
	public function update_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'POST', $this->get_filled_route( $args ), $assoc_args );
		if ( Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
			WP_CLI::line( $body['id'] );
		} else {
			WP_CLI::success( "Updated {$this->name} {$body['id']}." );
		}
	}

	/**
	 * Open an existing item in the editor
	 *
	 * @subcommand edit
	 */
	public function edit_item( $args, $assoc_args ) {
		$assoc_args['context'] = 'edit';
		list( $status, $options_body ) = $this->do_request( 'OPTIONS', $this->get_filled_route( $args ), $assoc_args );
		if ( empty( $options_body['schema'] ) ) {
			WP_CLI::error( "Cannot edit - no schema found for resource." );
		}
		$schema = $options_body['schema'];
		list( $status, $resource_fields ) = $this->do_request( 'GET', $this->get_filled_route( $args ), $assoc_args );
		$editable_fields = array();
		foreach( $resource_fields as $key => $value ) {
			if ( ! isset( $schema['properties'][ $key ] ) || ! empty( $schema['properties'][ $key ]['readonly'] ) ) {
				continue;
			}
			$properties = $schema['properties'][ $key ];
			if ( isset( $properties['properties'] ) ) {
				$parent_key = $key;
				$properties = $properties['properties'];
				foreach( $value as $key => $value ) {
					if ( isset( $properties[ $key ] ) && empty( $properties[ $key ]['readonly'] ) ) {
						if ( ! isset( $editable_fields[ $parent_key ] ) ) {
							$editable_fields[ $parent_key ] = array();
						}
						$editable_fields[ $parent_key ][ $key ] = $value;
					}
				}
				continue;
			}
			if ( empty( $properties['readonly'] ) ) {
				$editable_fields[ $key ] = $value;
			}
		}
		if ( empty( $editable_fields ) ) {
			WP_CLI::error( "Cannot edit - no editable fields found on schema." );
		}
		$ret = Utils\launch_editor_for_input( Spyc::YAMLDump( $editable_fields ), sprintf( 'Editing %s %s', $schema['title'], $args[0] ) );
		if ( false === $ret ) {
			WP_CLI::warning( "No edits made." );
		} else {
			list( $status, $body ) = $this->do_request( 'POST', $this->get_filled_route( $args ),Spyc::YAMLLoadString( $ret ) );
			WP_CLI::success( "Updated {$schema['title']} {$args[0]}." );
		}
	}

	/**
	 * Do a REST Request
	 *
	 * @param string $method
	 *
	 */
	private function do_request( $method, $route, $assoc_args ) {
		if ( 'internal' === $this->scope ) {
			if ( ! defined( 'REST_REQUEST' ) ) {
				define( 'REST_REQUEST', true );
			}
			$request = new \WP_REST_Request( $method, $route );
			if ( in_array( $method, array( 'POST', 'PUT' ) ) ) {
				$request->set_body_params( $assoc_args );
			} else {
				foreach( $assoc_args as $key => $value ) {
					$request->set_param( $key, $value );
				}
			}
			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
				$original_queries = is_array( $GLOBALS['wpdb']->queries ) ? array_keys( $GLOBALS['wpdb']->queries ) : array();
			}
			$response = rest_do_request( $request );
			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
				$performed_queries = array();
				foreach( (array) $GLOBALS['wpdb']->queries as $key => $query ) {
					if ( in_array( $key, $original_queries ) ) {
						continue;
					}
					$performed_queries[] = $query;
				}
				usort( $performed_queries, function( $a, $b ){
					if ( $a[1] === $b[1] ) {
						return 0;
					}
					return ( $a[1] > $b[1] ) ? -1 : 1;
				});

				$query_count = count( $performed_queries );
				$query_total_time = 0;
				foreach( $performed_queries as $query ) {
					$query_total_time += $query[1];
				}
				$slow_query_message = '';
				if ( $performed_queries && 'rest' === WP_CLI::get_config( 'debug' ) ) {
					$slow_query_message .= '. Ordered by slowness, the queries are:' . PHP_EOL;
					foreach( $performed_queries as $i => $query ) {
						$i++;
						$bits = explode( ', ', $query[2] );
						$backtrace = implode( ', ', array_slice( $bits, 13 ) );
						$seconds = round( $query[1], 6 );
						$slow_query_message .= <<<EOT
{$i}:
  - {$seconds} seconds
  - {$backtrace}
  - {$query[0]}
EOT;
						$slow_query_message .= PHP_EOL;
					}
				} else if ( 'rest' !== WP_CLI::get_config( 'debug' ) ) {
					$slow_query_message = '. Use --debug=rest to see all queries.';
				}
				$query_total_time = round( $query_total_time, 6 );
				WP_CLI::debug( "REST command executed {$query_count} queries in {$query_total_time} seconds{$slow_query_message}", 'rest' );
			}
			if ( $error = $response->as_error() ) {
				WP_CLI::error( $error );
			}
			return array( $response->get_status(), $response->get_data(), $response->get_headers() );
		} else if ( 'http' === $this->scope ) {
			$headers = array();
			if ( ! empty( $this->auth ) && 'basic' === $this->auth['type'] ) {
				$headers['Authorization'] = 'Basic ' . base64_encode( $this->auth['username'] . ':' . $this->auth['password'] );
			}
			if ( 'OPTIONS' === $method ) {
				$method = 'GET';
				$assoc_args['_method'] = 'OPTIONS';
			}
			$response = Utils\http_request( $method, rtrim( $this->api_url, '/' ) . $route, $assoc_args, $headers );
			$body = json_decode( $response->body, true );
			if ( $response->status_code >= 400 ) {
				if ( ! empty( $body['message'] ) ) {
					WP_CLI::error( $body['message'] . ' ' . json_encode( array( 'status' => $response->status_code ) ) );
				} else {
					switch( $response->status_code ) {
						case 404:
							WP_CLI::error( "No {$this->name} found." );
							break;
						default:
							WP_CLI::error( 'Could not complete request.' );
							break;
					}
				}
			}
			return array( $response->status_code, json_decode( $response->body, true ), $response->headers );
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
			if ( ! empty( $assoc_args['context'] ) ) {
				$fields = $this->get_context_fields( $assoc_args['context'] );
			} else {
				$fields = $this->get_context_fields( 'view' );
			}
		}
		return new \WP_CLI\Formatter( $assoc_args, $fields );
	}

	/**
	 * Get a list of fields present in a given context
	 *
	 * @param string $context
	 * @return array
	 */
	private function get_context_fields( $context ) {
		$fields = array();
		foreach( $this->schema['properties'] as $key => $args ) {
			if ( empty( $args['context'] ) || in_array( $context, $args['context'] ) ) {
				$fields[] = $key;
			}
		}
		return $fields;
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
