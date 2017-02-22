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
	private $schema;
	private $named_path_vars = array( array() );
	private $default_context = '';
	private $output_nesting_level = 0;

	public function __construct( $name, $route, $schema ) {
		$this->name = $name;
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
	 * Set the named_path_vars of the REST requests
	 *
	 * @param string $named_path_vars
	 */
	public function set_named_path_vars( $named_path_vars ) {
		$this->named_path_vars = $named_path_vars;
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
		list( $status, $body ) = $this->do_request( 'POST', $this->get_filled_route( $args ), $assoc_args );
		if ( Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
			if ( ( $status < 400 ) && isset( $body['id'] ) ) {
				WP_CLI::line( $body['id'] );
			} else {
				WP_CLI::halt( $status );
			}
		} else {
			if ( ( $status < 400 ) && isset( $body['id'] ) ) {
				WP_CLI::success( "Created {$this->name} {$body['id']}." );
			} else {
				WP_CLI::error( "Could not complete request. HTTP code: {$status}", $status );
			}
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
		if ( ( 'progress' === $format ) && !Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
			$notify = \WP_CLI\Utils\make_progress_bar( 'Generating items', $count );
		}

		for ( $i = 0; $i < $count; $i++ ) {
			list( $status, $body ) = $this->do_request( 'POST', $this->get_filled_route( $args ), $assoc_args );
			if ( $status < 400 ) {
				if ( Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
					WP_CLI::line( $body['id'] );
				} elseif ( 'progress' === $format ) {
					$notify->tick();
				} elseif ( 'ids' === $format ) {
					echo $body['id'];
					if ( $i < $count - 1 ) {
						echo ' ';
					}
				}
			} else {
				if ( Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
					WP_CLI::halt( $status );
				}
				WP_CLI::error( "Could not complete request. HTTP code: {$status}", $status );
			}
		}

		if ( ( 'progress' === $format ) && !Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
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
			if ( ( $status < 400 ) && ( isset( $body['previous']['id'] ) || isset( $body['id'] ) ) ) {
				// handles cases where user forget to put a value for --force=<value>
				WP_CLI::line( isset( $body['previous'] ) ? $body['previous']['id'] : $body['id'] );
			} else {
				WP_CLI::halt( $status );
			}
		} else {
			if ( ( $status < 400 ) && ( isset( $body['previous']['id'] ) || isset( $body['id'] ) ) ) {
				if ( isset( $body['previous'] ) ) {
					WP_CLI::success( "Deleted {$this->name} {$body['previous']['id']}." );
				} else {
					WP_CLI::success( "Trashed {$this->name} {$body['id']}." );
				}
			} else {
				WP_CLI::error( "Could not complete request. HTTP code: {$status}", $status );
			}
		}
	}

	/**
	 * Purge all (aka delete all) items in a collection
	 *
	 * @subcommand purgeall
	 */
	public function purgeall_items( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'DELETE', $this->get_filled_route( $args ), $assoc_args );
		if ( Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
			if ( $status < 400 ) {
				WP_CLI::halt( 0 );
			} else {
				WP_CLI::halt( $status );
			}
		} else {
			if ( $status < 400 ) {
				WP_CLI::success( "Purged all {$this->name}." );
			} else {
				WP_CLI::error( "Could not complete request. HTTP code: {$status}", $status );
			}
		}
	}

	/**
	 * Get a single item.
	 *
	 * @subcommand get
	 */
	public function get_item( $args, $assoc_args ) {
		list( $status, $body, $headers ) = $this->do_request( 'GET', $this->get_filled_route( $args ), $assoc_args );

		if ( ! empty( $assoc_args['fields'] ) ) {
			$body = self::limit_item_to_fields( $body, $fields );
		}

		if ( 'headers' === $assoc_args['format'] ) {
			echo json_encode( $headers );
		} elseif ( 'body' === $assoc_args['format'] ) {
			echo json_encode( $body );
		} elseif ( 'envelope' === $assoc_args['format'] ) {
			echo json_encode( array(
				'body'        => $body,
				'headers'     => $headers,
				'status'      => $status,
				'api_url'     => $this->api_url,
			) );
		} else {
			if ( empty( $body ) ) {
				return;
			}
			$formatter = $this->get_formatter( $assoc_args );
			$formatter->display_item( $body );
		}
	}

	/**
	 * Compare the keys of the array of arrays for same size and keys
	 *
	 * @param array $toparray
	 * @return boolean
	 */
	private static function same_array_array_keys( $toparray ) {
		list( $key_first_child, $val_first_child ) = each( $toparray );
		if ( ! is_array( $val_first_child ) ) {
			return false;
		}
		$num_keys_first_child = count($val_first_child);
		while ( list( $key_sibling, $val_sibling ) = each( $toparray ) ) {
			if ( count($val_sibling) !== $num_keys_first_child ) {
				return false;
			}
			if ( array_diff_key( $val_first_child, $val_sibling ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * List all items.
	 *
	 * @subcommand list
	 */
	public function list_items( $args, $assoc_args ) {
		if ( ( ! empty( $assoc_args['format'] ) && 'count' === $assoc_args['format'] ) && ! empty( $assoc_args['per_page'] ) ) {
			// only pageable routes will return the X-WP-Total header
			$method = 'HEAD';
		} else {
			$method = 'GET';
		}
		list( $status, $body, $headers ) = $this->do_request( $method, $this->get_filled_route( $args ), $assoc_args );

		// shortcut --format=count for pageable routes
		if ( 'HEAD' === $method ) {
			if ( ! isset( $headers['x-wp-total'] ) ) {
				// fallback to GET all content and let the wp-cli formatter handle the counting
				list( $status, $body, $headers ) = $this->do_request( 'GET', $this->get_filled_route( $args ), $assoc_args );
			} else {
				echo (int) $headers['x-wp-total'];
				return;
			}
		}

		// modify content for the simplified --format=ids
		if ( ! empty( $assoc_args['format'] ) && 'ids' === $assoc_args['format'] ) {
			$items = array_column( $body, 'id' );
		} else {
			$items = $body;
		}

		// filter the columns
		if ( ! empty( $assoc_args['fields'] ) ) {
			foreach( $items as $key => $item ) {
				$items[ $key ] = self::limit_item_to_fields( $item, $fields );
			}
		}

		if ( 'headers' === $assoc_args['format'] ) {
			echo json_encode( $headers );
		} elseif ( 'body' === $assoc_args['format'] ) {
			echo json_encode( $body );
		} elseif ( 'envelope' === $assoc_args['format'] ) {
			echo json_encode( array(
				'body'        => $body,
				'headers'     => $headers,
				'status'      => $status,
				'api_url'     => $this->api_url,
			) );
		} else {
			if ( empty( $items ) ) {
				return;
			}
			$formatter = $this->get_formatter( $assoc_args );
			if ( isset( $items[0] ) )
				$formatter->display_items( $items );
			elseif ( self::same_array_array_keys( $items ) )
				$formatter->display_items( $items );
			else
				$formatter->display_item( $items );
		}
	}

	/**
	 * Compare items between environments.
	 *
	 * <alias>
	 * : Alias for the WordPress site to compare to.
	 *
	 * [<resource>]
	 * : Limit comparison to a specific resource, instead of the collection.
	 *
	 * [--fields=<fields>]
	 * : Limit comparison to specific fields.
	 *
	 * @subcommand diff
	 */
	public function diff_items( $args, $assoc_args ) {

		list( $alias ) = $args;
		if ( ! array_key_exists( $alias, WP_CLI::get_runner()->aliases ) ) {
			WP_CLI::error( "Alias '{$alias}' not found." );
		}
		$resource = isset( $args[1] ) ? $args[1] : null;
		$fields = Utils\get_flag_value( $assoc_args, 'fields', null );

		list( $from_status, $from_body, $from_headers ) = $this->do_request( 'GET', $this->get_filled_route( $args ), array() );

		$php_bin = WP_CLI::get_php_binary();
		$script_path = $GLOBALS['argv'][0];
		$other_args = implode( ' ', array_map( 'escapeshellarg', array( $alias, 'rest', $this->name, 'list' ) ) );
		$other_assoc_args = Utils\assoc_args_to_str( array( 'format' => 'envelope' ) );
		$full_command = "{$php_bin} {$script_path} {$other_args} {$other_assoc_args}";
		$process = \WP_CLI\Process::create( $full_command, null, array(
			'HOME'                 => getenv( 'HOME' ),
			'WP_CLI_PACKAGES_DIR'  => getenv( 'WP_CLI_PACKAGES_DIR' ),
			'WP_CLI_CONFIG_PATH'   => getenv( 'WP_CLI_CONFIG_PATH' ),
		) );
		$result = $process->run();
		$response = json_decode( $result->stdout, true );
		$to_headers = $response['headers'];
		$to_body = $response['body'];
		$to_api_url = $response['api_url'];

		if ( ! is_null( $resource ) ) {
			$field = is_numeric( $resource ) ? 'id' : 'slug';
			$callback = function( $value ) use ( $field, $resource ) {
				if ( isset( $value[ $field ] ) && $resource == $value[ $field ] ) {
					return true;
				}
				return false;
			};
			foreach( array( 'to_body', 'from_body' ) as $response_type ) {
				$$response_type = array_filter( $$response_type, $callback );
			}
		}

		$display_items = array();
		do {
			$from_item = $to_item = array();
			if ( ! empty( $from_body ) ) {
				$from_item = array_shift( $from_body );
				if ( ! empty( $to_body ) && ! empty( $from_item['slug'] ) ) {
					foreach( $to_body as $i => $item ) {
						if ( ! empty( $item['slug'] ) && $item['slug'] === $from_item['slug'] ) {
							$to_item = $item;
							unset( $to_body[ $i ] );
							break;
						}
					}
				}
			} elseif ( ! empty( $to_body ) ) {
				$to_item = array_shift( $to_body );
			}

			if ( ! empty( $to_item ) ) {
				foreach( array( 'to_item', 'from_item' ) as $item ) {
					if ( isset( $$item['_links'] ) ) {
						unset( $$item['_links'] );
					}
				}
				$display_items[] = array(
					'from'       => self::limit_item_to_fields( $from_item, $fields ),
					'to'         => self::limit_item_to_fields( $to_item, $fields ),
				);
			}
		} while( count( $from_body ) || count( $to_body ) );

		WP_CLI::line( \cli\Colors::colorize( "%R(-) {$this->api_url} %G(+) {$to_api_url}%n" ) );
		foreach( $display_items as $display_item ) {
			$this->show_difference( $this->name, array( 'from' => $display_item['from'], 'to' => $display_item['to'] ) );
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
			if ( ( $status < 400 ) && isset( $body['id'] ) ) {
				WP_CLI::line( $body['id'] );
			} else {
				WP_CLI::halt( $status );
			}
		} else {
			if ( ( $status < 400 ) && isset( $body['id'] ) ) {
				WP_CLI::success( "Updated {$this->name} {$body['id']}." );
			} else {
				WP_CLI::error( "Could not complete request. HTTP code: {$status}", $status );
			}
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
			list( $status, $body ) = $this->do_request( 'POST', $this->get_filled_route( $args ), Spyc::YAMLLoadString( $ret ) );
			if ( ( $status < 400 ) && isset( $body['id'] ) ) {
				WP_CLI::success( "Updated {$schema['title']} {$args[0]}." );
			} else {
				WP_CLI::error( "Could not complete request. HTTP code: {$status}", $status );
			}
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
				} elseif ( 'rest' !== WP_CLI::get_config( 'debug' ) ) {
					$slow_query_message = '. Use --debug=rest to see all queries.';
				}
				$query_total_time = round( $query_total_time, 6 );
				WP_CLI::debug( "REST command executed {$query_count} queries in {$query_total_time} seconds{$slow_query_message}", 'rest' );
			}
			if ( $error = $response->as_error() ) {
				$error_status = $error->get_error_data();
				if (is_array( $error_status ) && array_key_exists( 'status', $error_status ) ) {
					WP_CLI::error( $error->get_error_message() . " HTTP code: {$error_status['status']}", $error_status['status'] );
				}
				WP_CLI::error( $error->get_error_message() );
			}

			// normalize headers and return result
			$norm_headers = array_change_key_case( $response->get_headers(), CASE_LOWER );
			return array( $response->get_status(), $response->get_data(), $norm_headers );
		} elseif ( 'http' === $this->scope ) {
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
					WP_CLI::error( $body['message'], $response->status_code );
				} else {
					switch( $response->status_code ) {
						case 404:
							WP_CLI::error( "No {$this->name} found.", $response->status_code );
							break;
						default:
							WP_CLI::error( "Could not complete request. HTTP code: {$response->status_code}", $response->status_code );
							break;
					}
				}
			}

			// normalize headers and return result
			$norm_headers = array();
			foreach ( $response->headers->getAll() as $key => $value ) {
				$norm_headers[ $key ] = $response->headers->flatten( $value );
			}
			return array( $response->status_code, $body, $norm_headers );
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
			if ( !array_key_exists( 'context', $args) || in_array( $context, $args['context'] ) ) {
				$fields[] = $key;
			}
		}
		return $fields;
	}

	/**
	 * Fill the route based on provided $args
	 */
	private function get_filled_route( $args ) {
		if ( count( $args ) !==  count( $this->named_path_vars[0] ) ) {
			WP_CLI::error( "Wrong number of arguments for {$this->name} command." );
		}
		$filled_route = str_replace( $this->named_path_vars[0], $args, $this->route);
		if ( empty( $filled_route ) ) {
			WP_CLI::error( "Unexpected error creating url for {$this->name} command." );
		}
		WP_CLI::debug( "REST command url: {$filled_route}", 'rest' );
		return $filled_route;
	}

	/**
	 * Visually depict the difference between "dictated" and "current"
	 *
	 * @param array
	 */
	private function show_difference( $slug, $difference ) {
		$this->output_nesting_level = 0;
		$this->nested_line( $slug . ': ' );
		$this->recursively_show_difference( $difference['to'], $difference['from'] );
		$this->output_nesting_level = 0;
	}

	/**
	 * Recursively output the difference between "dictated" and "current"
	 */
	private function recursively_show_difference( $dictated, $current = null ) {

		$this->output_nesting_level++;

		if ( $this->is_assoc_array( $dictated ) ) {

			foreach( $dictated as $key => $value ) {

				if ( $this->is_assoc_array( $value ) || is_array( $value ) ) {

					$new_current = isset( $current[ $key ] ) ? $current[ $key ] : null;
					if ( $new_current ) {
						$this->nested_line( $key . ': ' );
					} else {
						$this->add_line( $key . ': ' );
					}

					$this->recursively_show_difference( $value, $new_current );

				} elseif ( is_string( $value ) ) {

					$pre = $key . ': ';

					if ( isset( $current[ $key ] ) && $current[ $key ] !== $value ) {

						$this->remove_line( $pre . $current[ $key ] );
						$this->add_line( $pre . $value );

					} elseif ( ! isset( $current[ $key ] ) ) {

						$this->add_line( $pre . $value );

					}

				}

			}

		} elseif ( is_array( $dictated ) ) {

			foreach( $dictated as $value ) {

				if ( ! $current 
					|| ! in_array( $value, $current ) ) {
					$this->add_line( '- ' . $value );
				}

			}

		} elseif ( is_string( $value ) ) {

			$pre = $key . ': ';

			if ( isset( $current[ $key ] ) && $current[ $key ] !== $value ) {

				$this->remove_line( $pre . $current[ $key ] );
				$this->add_line( $pre . $value );

			} elseif ( ! isset( $current[ $key ] ) ) {

				$this->add_line( $pre . $value );

			} else {

				$this->nested_line( $pre );

			}

		}

		$this->output_nesting_level--;

	}

	/**
	 * Output a line to be added
	 *
	 * @param string
	 */
	private function add_line( $line ) {
		$this->nested_line( $line, 'add' );
	}

	/**
	 * Output a line to be removed
	 *
	 * @param string
	 */
	private function remove_line( $line ) {
		$this->nested_line( $line, 'remove' );
	}

	/**
	 * Output a line that's appropriately nested
	 */
	private function nested_line( $line, $change = false ) {

		if ( 'add' == $change ) {
			$color = '%G';
			$label = '+ ';
		} elseif ( 'remove' == $change ) {
			$color = '%R';
			$label = '- ';
		} else {
			$color = false;
			$label = false;
		}

		$spaces = ( $this->output_nesting_level * 2 ) + 2;
		if ( $color && $label ) {
			$line = \cli\Colors::colorize( "{$color}{$label}" ) . $line . \cli\Colors::colorize( "%n" );
			$spaces = $spaces - 2;
		}
		WP_CLI::line( str_pad( ' ', $spaces ) . $line );
	}

	/**
	 * Whether or not this is an associative array
	 *
	 * @param array
	 * @return bool
	 */
	private function is_assoc_array( $array ) {

		if ( ! is_array( $array ) ) {
			return false;
		}
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}

	/**
	 * Reduce an item to specific fields.
	 *
	 * @param array $item
	 * @param array $fields
	 * @return array
	 */
	private static function limit_item_to_fields( $item, $fields ) {
		if ( empty( $fields ) ) {
			return $item;
		}
		if ( is_string( $fields ) ) {
			$fields = explode( ',', $fields );
		}
		foreach( $item as $i => $field ) {
			if ( ! in_array( $i, $fields ) ) {
				unset( $item[ $i ] );
			}
		}
		return $item;
	}
}
