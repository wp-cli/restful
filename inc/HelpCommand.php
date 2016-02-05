<?php

namespace WP_REST_CLI;

class HelpCommand {

	private $ap_index = array();

	public function __construct( $api_index ) {
		$this->api_index = $api_index;
	}

	/**
	 * Explain this API in human-readable terms.
	 *
	 * [--format=<format>]
	 * : Display API details in a particular format.
	 *
	 * @when before_wp_load
	 */
	public function help( $args, $assoc_args ) {
		if ( 'apib' === $assoc_args['format'] ) {
			$this->display_index_as_api_blueprint();
		}
	}

	/**
	 * Display the index as API blueprint
	 */
	private function display_index_as_api_blueprint() {
		$schemas = array();
		foreach( $this->api_index['routes'] as $route => $data ) {
			if ( ! empty( $data['schema'] ) ) {
				$schemas[ $data['schema']['title'] ] = $data['schema'];
			}
		}
		$output = array();
		if ( ! empty( $schemas ) ) {
			$output[] = '# Data Structures';
			$output[] = '';
			foreach( $schemas as $schema ) {
				$output[] = "## {$schema['title']} ({$schema['type']})";
				foreach( $schema['properties'] as $key => $args ) {
					$bits = array();
					if ( ! empty( $args['type'] ) ) {
						$type = $args['type'];
						if ( ! empty( $args['format'] ) ) {
							$type .= '[' . $args['format'] . ']';
						}
						$bits[] = $type;
					}
					if ( ! empty( $args['required'] ) ) {
						$bits[] = 'required';
					}
					if ( ! empty( $bits ) ) {
						$bits = ' (' . implode( ', ', $bits ) . ')';
					}
					$description = ! empty( $args['description'] ) ? ' - ' . $args['description'] : '';
					$output[] = "+ {$key}{$bits}{$description}";
				}
				$output[] = '';
			}
		}
		echo implode( PHP_EOL, $output );
	}

}
