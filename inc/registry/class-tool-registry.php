<?php

namespace WeboMCP\Core\Registry;

use Exception;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ToolRegistry {

	/**
	 * Registered tools map.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static $tools = array();

	/**
	 * Registers a tool into static registry.
	 *
	 * @param array<string, mixed> $tool Tool definition.
	 * @return true|WP_Error
	 */
	public static function register( array $tool ) {
		$validation = self::validate_tool_definition( $tool );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		self::$tools[ $tool['name'] ] = array(
			'name'        => $tool['name'],
			'description' => $tool['description'],
			'category'    => $tool['category'],
			'arguments'   => isset( $tool['arguments'] ) && is_array( $tool['arguments'] ) ? $tool['arguments'] : array(),
			'permission'  => isset( $tool['permission'] ) ? (string) $tool['permission'] : '',
			'visibility'  => isset( $tool['visibility'] ) ? (string) $tool['visibility'] : 'public',
			'callback'    => $tool['callback'],
		);

		return true;
	}

	/**
	 * Returns a registered tool by name.
	 *
	 * @param string $name Tool name.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $name ) {
		if ( isset( self::$tools[ $name ] ) ) {
			return self::$tools[ $name ];
		}

		return null;
	}

	/**
	 * Returns all tools with metadata.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function list() {
		return array_values( self::$tools );
	}

	/**
	 * Returns tools filtered by category.
	 *
	 * @param string $category Category key.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_by_category( string $category ) {
		if ( '' === $category ) {
			return self::list();
		}

		$tools = array_filter(
			self::$tools,
			static function ( $tool ) use ( $category ) {
				return isset( $tool['category'] ) && $category === $tool['category'];
			}
		);

		return array_values( $tools );
	}

	/**
	 * Returns MCP tools/list payload.
	 *
	 * @param bool   $include_internal Include internal tools.
	 * @param string $category         Optional. Filter by category (empty = all).
	 * @return array<string, mixed>
	 */
	public static function list_tools( bool $include_internal = false, string $category = '' ) {
		$items = array();
		$source = ( '' !== $category ) ? self::list_by_category( $category ) : array_values( self::$tools );

		foreach ( $source as $tool ) {
			$visibility = isset( $tool['visibility'] ) ? (string) $tool['visibility'] : 'public';
			if ( ! $include_internal && 'internal' === $visibility ) {
				continue;
			}

			$item = array(
				'name'        => $tool['name'],
				'description' => $tool['description'],
				'category'    => $tool['category'],
				'visibility'  => $visibility,
			);
			if ( ! empty( $tool['arguments'] ) && is_array( $tool['arguments'] ) ) {
				$item['arguments'] = $tool['arguments'];
			}
			$items[] = $item;
		}

		return array( 'tools' => $items );
	}

	/**
	 * Checks whether a tool is marked as internal.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	public static function is_internal( string $name ) {
		$tool = self::get( $name );
		if ( ! $tool ) {
			return false;
		}

		return isset( $tool['visibility'] ) && 'internal' === (string) $tool['visibility'];
	}

	/**
	 * Executes a registered tool.
	 *
	 * @param string               $name      Tool name.
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return mixed|WP_Error
	 * @throws Exception If tool is not registered.
	 */
	public static function call( string $name, array $arguments = array() ) {
		$tool = self::get( $name );

		if ( ! $tool ) {
			throw new Exception( 'Tool not registered' );
		}

		$permission = isset( $tool['permission'] ) ? (string) $tool['permission'] : '';
		if ( '' !== $permission && ! current_user_can( $permission ) ) {
			return new WP_Error(
				'webo_mcp_permission_denied',
				sprintf( 'Permission denied for tool: %s', $name ),
				array( 'status' => 403 )
			);
		}

		$sanitized_arguments = self::validate_arguments(
			isset( $tool['arguments'] ) && is_array( $tool['arguments'] ) ? $tool['arguments'] : array(),
			$arguments
		);

		if ( is_wp_error( $sanitized_arguments ) ) {
			return $sanitized_arguments;
		}

		if ( ! is_callable( $tool['callback'] ) ) {
			return new WP_Error( 'webo_mcp_invalid_callback', 'Tool callback is not callable' );
		}

		return call_user_func( $tool['callback'], $sanitized_arguments );
	}

	/**
	 * Validates a tool registration payload.
	 *
	 * @param array<string, mixed> $tool Tool payload.
	 * @return true|WP_Error
	 */
	private static function validate_tool_definition( array $tool ) {
		$required_keys = array( 'name', 'description', 'category', 'callback' );

		foreach ( $required_keys as $required_key ) {
			if ( ! isset( $tool[ $required_key ] ) ) {
				return new WP_Error(
					'webo_mcp_missing_tool_key',
					sprintf( 'Missing required tool field: %s', $required_key )
				);
			}
		}

		if ( ! is_string( $tool['name'] ) || '' === trim( $tool['name'] ) ) {
			return new WP_Error( 'webo_mcp_invalid_tool_name', 'Tool name must be a non-empty string' );
		}

		if ( ! is_string( $tool['description'] ) || '' === trim( $tool['description'] ) ) {
			return new WP_Error( 'webo_mcp_invalid_tool_description', 'Tool description must be a non-empty string' );
		}

		if ( ! is_string( $tool['category'] ) || '' === trim( $tool['category'] ) ) {
			return new WP_Error( 'webo_mcp_invalid_tool_category', 'Tool category must be a non-empty string' );
		}

		if ( isset( self::$tools[ $tool['name'] ] ) ) {
			return new WP_Error( 'webo_mcp_duplicate_tool', 'Tool name already registered' );
		}

		if ( ! is_callable( $tool['callback'] ) ) {
			return new WP_Error( 'webo_mcp_invalid_callback', 'Tool callback must be callable' );
		}

		if ( isset( $tool['arguments'] ) && ! is_array( $tool['arguments'] ) ) {
			return new WP_Error( 'webo_mcp_invalid_arguments_schema', 'Tool arguments schema must be an array' );
		}

		if ( isset( $tool['visibility'] ) ) {
			$visibility = (string) $tool['visibility'];
			if ( ! in_array( $visibility, array( 'public', 'internal' ), true ) ) {
				return new WP_Error( 'webo_mcp_invalid_visibility', 'Tool visibility must be "public" or "internal"' );
			}
		}

		return true;
	}

	/**
	 * Validates and sanitizes arguments against schema.
	 *
	 * @param array<string, array<string, mixed>> $schema    Arguments schema.
	 * @param array<string, mixed>                 $arguments Input arguments.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function validate_arguments( array $schema, array $arguments ) {
		$validated = array();

		foreach ( $schema as $key => $rules ) {
			if ( ! is_array( $rules ) ) {
				return new WP_Error(
					'webo_mcp_invalid_argument_rule',
					sprintf( 'Argument schema for "%s" must be an array', $key )
				);
			}

			$is_required = isset( $rules['required'] ) ? (bool) $rules['required'] : false;
			$has_value   = array_key_exists( $key, $arguments );

			if ( ! $has_value ) {
				if ( $is_required ) {
					return new WP_Error(
						'webo_mcp_missing_argument',
						sprintf( 'Missing required argument: %s', $key )
					);
				}

				if ( array_key_exists( 'default', $rules ) ) {
					$validated[ $key ] = $rules['default'];
				}

				continue;
			}

			$type  = isset( $rules['type'] ) ? (string) $rules['type'] : 'string';
			$value = $arguments[ $key ];

			switch ( $type ) {
				case 'integer':
					if ( ! is_numeric( $value ) ) {
						return new WP_Error(
							'webo_mcp_invalid_argument_type',
							sprintf( 'Argument "%s" must be integer', $key )
						);
					}
					$value = (int) $value;
					break;
				case 'boolean':
					$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
					if ( null === $value ) {
						return new WP_Error(
							'webo_mcp_invalid_argument_type',
							sprintf( 'Argument "%s" must be boolean', $key )
						);
					}
					break;
				case 'array':
					if ( ! is_array( $value ) ) {
						return new WP_Error(
							'webo_mcp_invalid_argument_type',
							sprintf( 'Argument "%s" must be array', $key )
						);
					}
					break;
				case 'string':
				default:
					if ( is_array( $value ) || is_object( $value ) ) {
						return new WP_Error(
							'webo_mcp_invalid_argument_type',
							sprintf( 'Argument "%s" must be string', $key )
						);
					}
					$value = sanitize_text_field( (string) $value );
					break;
			}

			if ( isset( $rules['min'] ) && is_numeric( $rules['min'] ) && is_numeric( $value ) && $value < $rules['min'] ) {
				return new WP_Error(
					'webo_mcp_argument_below_min',
					sprintf( 'Argument "%s" must be >= %s', $key, $rules['min'] )
				);
			}

			if ( isset( $rules['max'] ) && is_numeric( $rules['max'] ) && is_numeric( $value ) && $value > $rules['max'] ) {
				return new WP_Error(
					'webo_mcp_argument_above_max',
					sprintf( 'Argument "%s" must be <= %s', $key, $rules['max'] )
				);
			}

			$validated[ $key ] = $value;
		}

		return array_merge( $arguments, $validated );
	}
}
