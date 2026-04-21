<?php

namespace WP_REST_CLI;

use Mustangostang\Spyc;
use WP_CLI;
use WP_CLI\Utils;

class RestCommand {

	/** @var string */
	private $scope = 'internal';

	/** @var string */
	private $api_url = '';

	/** @var array<string, mixed> */
	private $auth = array();

	/** @var string */
	private $name;

	/** @var string */
	private $route;

	/** @var string */
	private $resource_identifier;

	/** @var array<string, mixed> */
	private $schema;

	/** @var int */
	private $output_nesting_level = 0;

	/**
	 * @param string               $name
	 * @param string               $route
	 * @param array<string, mixed> $schema
	 */
	public function __construct( $name, $route, $schema ) {
		$this->name                = $name;
		$parsed_args               = preg_match_all( '#\([^\)]+\)#', $route, $matches );
		$this->resource_identifier = ! empty( $matches[0] ) ? array_pop( $matches[0] ) : '';
		$this->route               = rtrim( $route );
		$this->schema              = $schema;
	}

	/**
	 * Set the scope of the REST requests
	 *
	 * @param string $scope
	 * @return void
	 */
	public function set_scope( $scope ) {
		$this->scope = $scope;
	}

	/**
	 * Set the API url for the REST requests
	 *
	 * @param string $api_url
	 * @return void
	 */
	public function set_api_url( $api_url ) {
		$this->api_url = $api_url;
	}

	/**
	 * Set the authentication for the API requests
	 *
	 * @param array<string, mixed> $auth
	 * @return void
	 */
	public function set_auth( $auth ) {
		$this->auth = $auth;
	}

	/**
	 * Create a new item.
	 *
	 * @subcommand create
	 *
	 * @param array<string>       $args
	 * @param array<string, mixed> $assoc_args
	 * @return void
	 */
	public function create_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'POST', $this->get_base_route(), $assoc_args );
		if ( ! is_array( $body ) || empty( $body['id'] ) ) {
			WP_CLI::error( "Could not create {$this->name}." );
		}
		/** @var array{id: scalar} $body */
		if ( Utils\get_flag_value( self::get_typed_assoc_args( $assoc_args ), 'porcelain' ) ) {
			WP_CLI::line( (string) $body['id'] );
		} else {
			WP_CLI::success( "Created {$this->name} {$body['id']}." );
		}
	}

	/**
	 * Generate some items.
	 *
	 * @subcommand generate
	 *
	 * @param array<string>       $args
	 * @param array<string, mixed> $assoc_args
	 * @return void
	 */
	public function generate_items( $args, $assoc_args ) {

		$count = 0;
		if ( isset( $assoc_args['count'] ) ) {
			$count = is_numeric( $assoc_args['count'] ) ? (int) $assoc_args['count'] : 0;
		}
		unset( $assoc_args['count'] );

		$format = 'ids';
		if ( isset( $assoc_args['format'] ) ) {
			$format = is_scalar( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'ids';
		}
		unset( $assoc_args['format'] );

		$notify = false;
		if ( 'progress' === $format ) {
			$notify = \WP_CLI\Utils\make_progress_bar( 'Generating items', $count );
		}

		for ( $i = 0; $i < $count; $i++ ) {

			list( $status, $body ) = $this->do_request( 'POST', $this->get_base_route(), $assoc_args );

			if ( 'progress' === $format ) {
				/** @var \cli\progress\Bar $notify */
				$notify->tick();
			} elseif ( 'ids' === $format ) {
				$id = is_array( $body ) && isset( $body['id'] ) ? $body['id'] : '';
				echo is_scalar( $id ) ? (string) $id : '';
				if ( $i < $count - 1 ) {
					echo ' ';
				}
			}
		}

		if ( 'progress' === $format ) {
			/** @var \cli\progress\Bar $notify */
			$notify->finish();
		}
	}

	/**
	 * Delete an existing item.
	 *
	 * @subcommand delete
	 *
	 * @param array<string>       $args
	 * @param array<string, mixed> $assoc_args
	 * @return void
	 */
	public function delete_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'DELETE', $this->get_filled_route( $args ), $assoc_args );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$id = '';
		if ( isset( $body['previous'] ) && is_array( $body['previous'] ) && isset( $body['previous']['id'] ) ) {
			$id = $body['previous']['id'];
		} elseif ( isset( $body['id'] ) ) {
			$id = $body['id'];
		}

		$id = is_scalar( $id ) ? (string) $id : '';

		if ( Utils\get_flag_value( self::get_typed_assoc_args( $assoc_args ), 'porcelain' ) ) {
			WP_CLI::line( $id );
		} elseif ( empty( $assoc_args['force'] ) ) {
			WP_CLI::success( "Trashed {$this->name} {$id}." );
		} else {
			WP_CLI::success( "Deleted {$this->name} {$id}." );
		}
	}

	/**
	 * Get a single item.
	 *
	 * @subcommand get
	 *
	 * @param array<string>       $args
	 * @param array<string, mixed> $assoc_args
	 * @return void
	 */
	public function get_item( $args, $assoc_args ) {
		list( $status, $body, $headers ) = $this->do_request( 'GET', $this->get_filled_route( $args ), $assoc_args );

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		if ( ! empty( $assoc_args['fields'] ) ) {
			$fields = $assoc_args['fields'];
			if ( is_string( $fields ) ) {
				$fields = explode( ',', $fields );
			}
			if ( is_array( $fields ) ) {
				$fields = array_filter( $fields, 'is_string' );
			} else {
				$fields = array();
			}
			$body = self::limit_item_to_fields( $body, $fields );
		}

		if ( 'headers' === $assoc_args['format'] ) {
			echo json_encode( $headers );
		} elseif ( 'body' === $assoc_args['format'] ) {
			echo json_encode( $body );
		} elseif ( 'envelope' === $assoc_args['format'] ) {
			echo json_encode(
				array(
					'body'    => $body,
					'headers' => $headers,
					'status'  => $status,
					'api_url' => $this->api_url,
				)
			);
		} else {
			$formatter = $this->get_formatter( $assoc_args );
			$formatter->display_item( $body );
		}
	}

	/**
	 * List all items.
	 *
	 * @subcommand list
	 *
	 * @param array<string>       $args
	 * @param array<string, mixed> $assoc_args
	 * @return void
	 */
	public function list_items( $args, $assoc_args ) {
		if ( ! empty( $assoc_args['format'] ) && 'count' === $assoc_args['format'] ) {
			$method = 'HEAD';
		} else {
			$method = 'GET';
		}
		list( $status, $body, $headers ) = $this->do_request( $method, $this->get_base_route(), $assoc_args );
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		if ( ! empty( $assoc_args['format'] ) && 'ids' === $assoc_args['format'] ) {
			$items = array_column( $body, 'id' );
		} else {
			$items = $body;
		}

		if ( ! empty( $assoc_args['fields'] ) ) {
			$fields = $assoc_args['fields'];
			if ( is_string( $fields ) ) {
				$fields = explode( ',', $fields );
			}
			if ( is_array( $fields ) ) {
				$fields = array_filter( $fields, 'is_string' );
			} else {
				$fields = array();
			}
			foreach ( $items as $key => $item ) {
				if ( is_array( $item ) ) {
					/** @var array<string, mixed> $item */
					$items[ $key ] = self::limit_item_to_fields( $item, $fields );
				}
			}
		}

		if ( ! empty( $assoc_args['format'] ) && 'count' === $assoc_args['format'] ) {
			$total = isset( $headers['X-WP-Total'] ) ? $headers['X-WP-Total'] : 0;
			echo is_numeric( $total ) ? (int) $total : 0;
		} elseif ( 'headers' === $assoc_args['format'] ) {
			echo json_encode( $headers );
		} elseif ( 'body' === $assoc_args['format'] ) {
			echo json_encode( $body );
		} elseif ( 'envelope' === $assoc_args['format'] ) {
			echo json_encode(
				array(
					'body'    => $body,
					'headers' => $headers,
					'status'  => $status,
					'api_url' => $this->api_url,
				)
			);
		} else {
			$formatter = $this->get_formatter( $assoc_args );
			$formatter->display_items( $items );
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
	 *
	 * @param array<string>       $args
	 * @param array<string, mixed> $assoc_args
	 * @return void
	 */
	public function diff_items( $args, $assoc_args ) {

		list( $alias ) = $args;
		if ( ! array_key_exists( $alias, WP_CLI::get_runner()->aliases ) ) {
			WP_CLI::error( "Alias '{$alias}' not found." );
		}
		$resource = isset( $args[1] ) ? $args[1] : null;
		$fields   = Utils\get_flag_value( self::get_typed_assoc_args( $assoc_args ), 'fields', '' );
		if ( ! is_string( $fields ) ) {
			$fields = '';
		}

		list( $from_status, $from_body, $from_headers ) = $this->do_request( 'GET', $this->get_base_route(), array() );

		$php_bin     = WP_CLI::get_php_binary();
		$argv        = isset( $GLOBALS['argv'] ) && is_array( $GLOBALS['argv'] ) ? $GLOBALS['argv'] : array();
		$script_path = '';
		if ( isset( $argv[0] ) && is_string( $argv[0] ) ) {
			$script_path = $argv[0];
		}
		$other_args       = implode( ' ', array_map( 'escapeshellarg', array( $alias, 'rest', $this->name, 'list' ) ) );
		$other_assoc_args = Utils\assoc_args_to_str( array( 'format' => 'envelope' ) );
		$full_command     = "{$php_bin} {$script_path} {$other_args} {$other_assoc_args}";
		$process          = \WP_CLI\Process::create(
			$full_command,
			null,
			array(
				'HOME'                => getenv( 'HOME' ),
				'WP_CLI_PACKAGES_DIR' => getenv( 'WP_CLI_PACKAGES_DIR' ),
				'WP_CLI_CONFIG_PATH'  => getenv( 'WP_CLI_CONFIG_PATH' ),
			)
		);
		$result           = $process->run();
		$response         = json_decode( $result->stdout, true );
		if ( ! is_array( $response ) || ! isset( $response['headers'] ) || ! isset( $response['body'] ) || ! isset( $response['api_url'] ) || ! is_string( $response['api_url'] ) ) {
			WP_CLI::error( 'Invalid response from alias.' );
		}
		/** @var array{headers: mixed, body: mixed, api_url: string} $response */
		$to_headers = $response['headers'];
		$to_body    = $response['body'];
		$to_api_url = $response['api_url'];

		$from_body = is_array( $from_body ) ? $from_body : array();
		$to_body   = is_array( $to_body ) ? $to_body : array();

		if ( ! is_null( $resource ) ) {
			$field    = is_numeric( $resource ) ? 'id' : 'slug';
			$callback = function ( $value ) use ( $field, $resource ) {
				if ( isset( $value[ $field ] ) && $resource === $value[ $field ] ) {
					return true;
				}
				return false;
			};
			foreach ( array( 'to_body', 'from_body' ) as $response_type ) {
				$$response_type = array_filter( $$response_type, $callback );
			}
		}

		$display_items = array();
		do {
			$from_item = array();
			$to_item   = array();
			if ( ! empty( $from_body ) ) {
				$from_item = array_shift( $from_body );
				$from_item = is_array( $from_item ) ? $from_item : array();
				if ( ! empty( $to_body ) && ! empty( $from_item['slug'] ) ) {
					foreach ( $to_body as $i => $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}
						if ( ! empty( $item['slug'] ) && $item['slug'] === $from_item['slug'] ) {
							$to_item = $item;
							unset( $to_body[ $i ] );
							break;
						}
					}
				}
			} elseif ( ! empty( $to_body ) ) {
				$to_item = array_shift( $to_body );
				$to_item = is_array( $to_item ) ? $to_item : array();
			}

			if ( ! empty( $to_item ) ) {
				if ( isset( $to_item['_links'] ) ) {
					unset( $to_item['_links'] );
				}
				if ( isset( $from_item['_links'] ) ) {
					unset( $from_item['_links'] );
				}
				$display_items[] = array(
					'from' => self::limit_item_to_fields( $from_item, $fields ),
					'to'   => self::limit_item_to_fields( $to_item, $fields ),
				);
			}

			$from_count = count( $from_body );
			$to_count   = count( $to_body );
		} while ( $from_count || $to_count );

		WP_CLI::line( \cli\Colors::colorize( "%R(-) {$this->api_url} %G(+) {$to_api_url}%n" ) );
		foreach ( $display_items as $display_item ) {
			$this->show_difference(
				$this->name,
				array(
					'from' => $display_item['from'],
					'to'   => $display_item['to'],
				)
			);
		}
	}

	/**
	 * Update an existing item.
	 *
	 * @subcommand update
	 *
	 * @param array<string>       $args
	 * @param array<string, mixed> $assoc_args
	 * @return void
	 */
	public function update_item( $args, $assoc_args ) {
		list( $status, $body ) = $this->do_request( 'POST', $this->get_filled_route( $args ), $assoc_args );
		if ( ! is_array( $body ) || empty( $body['id'] ) ) {
			WP_CLI::error( "Could not update {$this->name}." );
		}
		/** @var array{id: scalar} $body */
		if ( Utils\get_flag_value( self::get_typed_assoc_args( $assoc_args ), 'porcelain' ) ) {
			WP_CLI::line( (string) $body['id'] );
		} else {
			WP_CLI::success( "Updated {$this->name} {$body['id']}." );
		}
	}

	/**
	 * Open an existing item in the editor
	 *
	 * @subcommand edit
	 *
	 * @param array<string>       $args
	 * @param array<string, mixed> $assoc_args
	 * @return void
	 */
	public function edit_item( $args, $assoc_args ) {
		$assoc_args['context']         = 'edit';
		list( $status, $options_body ) = $this->do_request( 'OPTIONS', $this->get_filled_route( $args ), $assoc_args );
		if ( ! is_array( $options_body ) || empty( $options_body['schema'] ) || ! is_array( $options_body['schema'] ) ) {
			WP_CLI::error( 'Cannot edit - no schema found for resource.' );
		}
		/** @var array{schema: array{properties: array<string, mixed>, title: string}} $options_body */
		$schema = $options_body['schema'];
		if ( empty( $schema['properties'] ) || ! is_array( $schema['properties'] ) ) {
			WP_CLI::error( 'Cannot edit - no properties found in schema.' );
		}
		if ( empty( $schema['title'] ) || ! is_string( $schema['title'] ) ) {
			WP_CLI::error( 'Cannot edit - no valid title in schema.' );
		}
		list( $status, $resource_fields ) = $this->do_request( 'GET', $this->get_filled_route( $args ), $assoc_args );
		if ( ! is_array( $resource_fields ) ) {
			WP_CLI::error( 'Cannot edit - no resource fields found.' );
		}
		/** @var array<string, mixed> $resource_fields */
		$editable_fields = array();
		foreach ( $resource_fields as $key => $value ) {
			if ( ! isset( $schema['properties'][ $key ] ) || ! is_array( $schema['properties'][ $key ] ) || ! empty( $schema['properties'][ $key ]['readonly'] ) ) {
				continue;
			}
			$properties = $schema['properties'][ $key ];
			if ( isset( $properties['properties'] ) && is_array( $properties['properties'] ) ) {
				$parent_key = $key;
				$properties = $properties['properties'];
				if ( is_array( $value ) ) {
					foreach ( $value as $sub_key => $sub_value ) {
						if ( isset( $properties[ $sub_key ] ) && is_array( $properties[ $sub_key ] ) && empty( $properties[ $sub_key ]['readonly'] ) ) {
							$sub_array                      = isset( $editable_fields[ $parent_key ] ) && is_array( $editable_fields[ $parent_key ] ) ? $editable_fields[ $parent_key ] : array();
							$sub_array[ $sub_key ]          = $sub_value;
							$editable_fields[ $parent_key ] = $sub_array;
						}
					}
				}
				continue;
			}
			if ( empty( $properties['readonly'] ) ) {
				$editable_fields[ $key ] = $value;
			}
		}
		if ( empty( $editable_fields ) ) {
			WP_CLI::error( 'Cannot edit - no editable fields found on schema.' );
		}
		$ret = Utils\launch_editor_for_input( Spyc::YAMLDump( $editable_fields ), sprintf( 'Editing %s %s', $schema['title'], $args[0] ) );
		if ( ! is_string( $ret ) ) {
			WP_CLI::warning( 'No edits made.' );
		} else {
			list( $status, $body ) = $this->do_request( 'POST', $this->get_filled_route( $args ), Spyc::YAMLLoadString( $ret ) );
			WP_CLI::success( "Updated {$schema['title']} {$args[0]}." );
		}
	}

	/**
	 * Do a REST Request
	 *
	 * @param string               $method
	 * @param string               $route
	 * @param array<string, mixed> $assoc_args
	 *
	 * @return array{0: int, 1: mixed, 2: array<string, mixed>}
	 */
	private function do_request( $method, $route, $assoc_args ) {
		if ( 'internal' === $this->scope ) {
			if ( ! defined( 'REST_REQUEST' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
				define( 'REST_REQUEST', true );
			}
			$request = new \WP_REST_Request( $method, $route );
			if ( in_array( $method, array( 'POST', 'PUT' ), true ) ) {
				$request->set_body_params( $assoc_args );
			} else {
				foreach ( $assoc_args as $key => $value ) {
					$request->set_param( $key, $value );
				}
			}
			$original_queries = array();
			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
				/** @var \wpdb $wpdb */
				$wpdb             = $GLOBALS['wpdb'];
				$original_queries = is_array( $wpdb->queries ) ? array_keys( $wpdb->queries ) : array();
			}
			$response = rest_do_request( $request );
			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
				/** @var \wpdb $wpdb */
				$wpdb              = $GLOBALS['wpdb'];
				$performed_queries = array();
				foreach ( (array) $wpdb->queries as $key => $query ) {
					if ( in_array( $key, $original_queries, true ) ) {
						continue;
					}
					$performed_queries[] = $query;
				}
				usort(
					$performed_queries,
					function ( $a, $b ) {
						if ( $a[1] === $b[1] ) {
							return 0;
						}
						return ( $a[1] > $b[1] ) ? -1 : 1;
					}
				);

				$query_count      = count( $performed_queries );
				$query_total_time = 0;
				foreach ( $performed_queries as $query ) {
					$query_total_time += $query[1];
				}
				$slow_query_message = '';
				if ( $performed_queries && 'rest' === WP_CLI::get_config( 'debug' ) ) {
					$slow_query_message .= '. Ordered by slowness, the queries are:' . PHP_EOL;
					foreach ( $performed_queries as $i => $query ) {
						++$i;
						$bits                = explode( ', ', $query[2] );
						$backtrace           = implode( ', ', array_slice( $bits, 13 ) );
						$seconds             = round( $query[1], 6 );
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
			$error = $response->as_error();
			if ( $error ) {
				WP_CLI::error( $error );
			}
			return array( $response->get_status(), $response->get_data(), $response->get_headers() );
		} elseif ( 'http' === $this->scope ) {
			$headers = array();
			if ( ! empty( $this->auth ) && 'basic' === $this->auth['type'] ) {
				$username = isset( $this->auth['username'] ) ? $this->auth['username'] : '';
				$password = isset( $this->auth['password'] ) ? $this->auth['password'] : '';
				if ( is_scalar( $username ) && is_scalar( $password ) ) {
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					$headers['Authorization'] = 'Basic ' . base64_encode( (string) $username . ':' . (string) $password );
				}
			}
			if ( 'OPTIONS' === $method ) {
				$method                = 'GET';
				$assoc_args['_method'] = 'OPTIONS';
			}
			/** @var \WpOrg\Requests\Response $response */
			$response = Utils\http_request( $method, rtrim( $this->api_url, '/' ) . $route, $assoc_args, $headers );
			$body     = json_decode( $response->body, true );
			if ( ! is_array( $body ) ) {
				$body = array();
			}
			if ( $response->status_code >= 400 ) {
				if ( ! empty( $body['message'] ) && is_string( $body['message'] ) ) {
					WP_CLI::error( $body['message'] . ' ' . json_encode( array( 'status' => $response->status_code ) ) );
				} else {
					switch ( $response->status_code ) {
						case 404:
							WP_CLI::error( "No {$this->name} found." );
							// @phpstan-ignore deadCode.unreachable
							break;
						default:
							WP_CLI::error( 'Could not complete request.' );
							// @phpstan-ignore deadCode.unreachable
							break;
					}
				}
			}
			return array( (int) $response->status_code, $body, $response->headers->getAll() );
		}
		WP_CLI::error( 'Invalid scope for REST command.' );
		// @phpstan-ignore deadCode.unreachable
		return array( 0, '', array() );
	}

	/**
	 * Get Formatter object based on supplied parameters.
	 *
	 * @param array<string, mixed> $assoc_args Parameters passed to command. Determines formatting.
	 * @return \WP_CLI\Formatter
	 */
	protected function get_formatter( $assoc_args ) {
		if ( ! empty( $assoc_args['fields'] ) ) {
			if ( is_string( $assoc_args['fields'] ) ) {
				$fields = explode( ',', $assoc_args['fields'] );
			} elseif ( is_array( $assoc_args['fields'] ) ) {
				$fields = array_filter( $assoc_args['fields'], 'is_string' );
			} else {
				$fields = array();
			}
		} elseif ( ! empty( $assoc_args['context'] ) && is_scalar( $assoc_args['context'] ) ) {
				$fields = $this->get_context_fields( (string) $assoc_args['context'] );
		} else {
			$fields = $this->get_context_fields( 'view' );
		}
		return new \WP_CLI\Formatter( $assoc_args, $fields );
	}

	/**
	 * Get a list of fields present in a given context
	 *
	 * @param string $context
	 * @return array<string>
	 */
	private function get_context_fields( $context ) {
		$fields = array();
		if ( ! empty( $this->schema['properties'] ) && is_array( $this->schema['properties'] ) ) {
			foreach ( $this->schema['properties'] as $key => $args ) {
				if ( ! is_array( $args ) ) {
					continue;
				}
				$context_array = isset( $args['context'] ) ? $args['context'] : array();
				if ( ! is_array( $context_array ) ) {
					$context_array = array();
				}
				if ( empty( $context_array ) || in_array( $context, $context_array, true ) ) {
					$fields[] = (string) $key;
				}
			}
		}

		$title = isset( $this->schema['title'] ) ? $this->schema['title'] : '';
		if ( ! is_scalar( $title ) ) {
			$title = '';
		}
		foreach ( $this->get_additional_fields( (string) $title ) as $field_name => $field ) {
			// For back-compat, include any field with an empty schema
			// because it won't be present in $this->get_item_schema().
			// @see \WP_REST_Controller::get_fields_for_response
			if ( is_array( $field ) && isset( $field['schema'] ) && is_null( $field['schema'] ) ) {
				$fields[] = (string) $field_name;
			}
		}
		return $fields;
	}

	/**
	 * Retrieves all of the registered additional fields for a given object-type.
	 * Here because the Rest Controller's method is protected.
	 *
	 * @param string $object_type
	 *
	 * @see \WP_REST_Controller::get_additional_fields
	 * @return array<string, array<string, mixed>>
	 */
	private function get_additional_fields( $object_type ) {
		global $wp_rest_additional_fields;

		if ( ! $wp_rest_additional_fields || ! isset( $wp_rest_additional_fields[ $object_type ] ) ) {
			return array();
		}

		return $wp_rest_additional_fields[ $object_type ];
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
	 *
	 * @param array<string> $args
	 * @return string
	 */
	private function get_filled_route( $args ) {
		return rtrim( $this->get_base_route(), '/' ) . '/' . $args[0];
	}

	/**
	 * Visually depict the difference between "dictated" and "current"
	 *
	 * @param string               $slug
	 * @param array<string, mixed> $difference
	 * @return void
	 */
	private function show_difference( $slug, $difference ) {
		$this->output_nesting_level = 0;
		$this->nested_line( $slug . ': ' );
		$this->recursively_show_difference( $difference['to'], $difference['from'] );
		$this->output_nesting_level = 0;
	}

	/**
	 * Recursively output the difference between "dictated" and "current"
	 *
	 * @param mixed $dictated
	 * @param mixed $current
	 * @return void
	 */
	private function recursively_show_difference( $dictated, $current = null ) {

		++$this->output_nesting_level;

		if ( is_array( $dictated ) && $this->is_assoc_array( $dictated ) ) {

			foreach ( $dictated as $key => $value ) {
				$key_str = (string) $key;

				if ( is_array( $value ) ) {

					$new_current = null;
					if ( is_array( $current ) && isset( $current[ $key ] ) ) {
						$new_current = $current[ $key ];
					}

					if ( $new_current ) {
						$this->nested_line( $key_str . ': ' );
					} else {
						$this->add_line( $key_str . ': ' );
					}

					$this->recursively_show_difference( $value, $new_current );

				} elseif ( is_scalar( $value ) ) {

					$pre       = $key_str . ': ';
					$value_str = (string) $value;

					if ( is_array( $current ) && isset( $current[ $key ] ) ) {
						$current_val = $current[ $key ];
						if ( $current_val !== $value ) {
							$current_val_str = is_scalar( $current_val ) ? (string) $current_val : '';
							$this->remove_line( $pre . $current_val_str );
							$this->add_line( $pre . $value_str );
						}
					} else {
						$this->add_line( $pre . $value_str );
					}
				}
			}
		} elseif ( is_array( $dictated ) ) {

			foreach ( $dictated as $value ) {
				$value_str = is_scalar( $value ) ? (string) $value : '';
				if ( ! is_array( $current ) || ! in_array( $value, $current, true ) ) {
					$this->add_line( '- ' . $value_str );
				}
			}
		}

		--$this->output_nesting_level;
	}

	/**
	 * Output a line to be added
	 *
	 * @param string $line
	 * @return void
	 */
	private function add_line( $line ) {
		$this->nested_line( $line, 'add' );
	}

	/**
	 * Output a line to be removed
	 *
	 * @param string $line
	 * @return void
	 */
	private function remove_line( $line ) {
		$this->nested_line( $line, 'remove' );
	}

	/**
	 * Output a line that's appropriately nested
	 *
	 * @param string      $line
	 * @param string|bool $change
	 * @return void
	 */
	private function nested_line( $line, $change = false ) {

		if ( 'add' === $change ) {
			$color = '%G';
			$label = '+ ';
		} elseif ( 'remove' === $change ) {
			$color = '%R';
			$label = '- ';
		} else {
			$color = false;
			$label = false;
		}

		$spaces = ( $this->output_nesting_level * 2 ) + 2;
		if ( $color && $label ) {
			$line   = \cli\Colors::colorize( "{$color}{$label}" ) . $line . \cli\Colors::colorize( '%n' );
			$spaces = $spaces - 2;
		}
		WP_CLI::line( str_pad( ' ', $spaces ) . $line );
	}

	/**
	 * Whether or not this is an associative array
	 *
	 * @param array<mixed> $arr
	 * @return bool
	 */
	private function is_assoc_array( $arr ) {
		if ( ! is_array( $arr ) ) {
			return false;
		}

		if ( function_exists( 'array_is_list' ) ) {
			// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.array_is_listFound
			return ! array_is_list( $arr );
		}

		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	/**
	 * Reduce an item to specific fields.
	 *
	 * @param array<string, mixed> $item
	 * @param array<string>|string $fields
	 * @return array<string, mixed>
	 */
	private static function limit_item_to_fields( $item, $fields ) {
		if ( empty( $fields ) ) {
			return $item;
		}
		if ( is_string( $fields ) ) {
			$fields = explode( ',', $fields );
		}
		foreach ( $item as $i => $field ) {
			if ( ! in_array( $i, $fields, true ) ) {
				unset( $item[ $i ] );
			}
		}
		return $item;
	}

	/**
	 * Get typed assoc args for WP-CLI utilities.
	 *
	 * @param array<string, mixed> $assoc_args
	 * @return array<string, bool|string>
	 */
	private static function get_typed_assoc_args( array $assoc_args ) {
		$typed = array();
		foreach ( $assoc_args as $key => $value ) {
			if ( is_string( $key ) && ( is_string( $value ) || is_bool( $value ) ) ) {
				$typed[ $key ] = $value;
			}
		}
		return $typed;
	}
}
