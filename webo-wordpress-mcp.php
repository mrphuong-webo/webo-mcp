<?php
/**
 * Plugin Name: WEBO WordPress MCP
 * Description: Standalone MCP gateway and WordPress tools platform.
 * Version: 1.1.1
 * Author: WEBO
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: webo-wordpress-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$webo_wordpress_mcp_autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $webo_wordpress_mcp_autoloader ) ) {
	require_once $webo_wordpress_mcp_autoloader;
}

$webo_wordpress_mcp_abilities_api = __DIR__ . '/vendor/wordpress/abilities-api/abilities-api.php';
if ( file_exists( $webo_wordpress_mcp_abilities_api ) ) {
	require_once $webo_wordpress_mcp_abilities_api;
}

require_once __DIR__ . '/inc/registry/class-tool-registry.php';
require_once __DIR__ . '/inc/tools/class-wordpress-tools.php';
require_once __DIR__ . '/inc/session/class-session-manager.php';
require_once __DIR__ . '/inc/router/class-mcp-router.php';

use WeboMCP\Core\Registry\ToolRegistry;
use WeboMCP\Core\Router\McpRouter;
use WeboMCP\Core\Tools\WordPressTools;
use WP\MCP\Core\McpAdapter;

/**
 * Converts Abilities API input schema to ToolRegistry arguments schema.
 *
 * @param array<string, mixed> $input_schema Ability input schema.
 * @return array<string, array<string, mixed>>
 */
function webo_wordpress_mcp_convert_input_schema_to_tool_arguments( array $input_schema ) {
	$arguments = array();

	if ( ! isset( $input_schema['properties'] ) || ! is_array( $input_schema['properties'] ) ) {
		return $arguments;
	}

	$required_fields = array();
	if ( isset( $input_schema['required'] ) && is_array( $input_schema['required'] ) ) {
		$required_fields = array_values( array_filter( array_map( 'strval', $input_schema['required'] ) ) );
	}

	foreach ( $input_schema['properties'] as $property_name => $property_schema ) {
		if ( ! is_string( $property_name ) || '' === $property_name || ! is_array( $property_schema ) ) {
			continue;
		}

		$rule = array(
			'type'     => isset( $property_schema['type'] ) ? (string) $property_schema['type'] : 'string',
			'required' => in_array( $property_name, $required_fields, true ),
		);

		if ( array_key_exists( 'default', $property_schema ) ) {
			$rule['default'] = $property_schema['default'];
		}

		if ( isset( $property_schema['minimum'] ) && is_numeric( $property_schema['minimum'] ) ) {
			$rule['min'] = (float) $property_schema['minimum'];
		}

		if ( isset( $property_schema['maximum'] ) && is_numeric( $property_schema['maximum'] ) ) {
			$rule['max'] = (float) $property_schema['maximum'];
		}

		if ( 'number' === $rule['type'] ) {
			$rule['type'] = 'integer';
		}

		$arguments[ $property_name ] = $rule;
	}

	return $arguments;
}

/**
 * Bridges all registered WordPress abilities to MCP ToolRegistry.
 *
 * @return void
 */
function webo_wordpress_mcp_register_wordpress_abilities() {
	if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_get_ability' ) ) {
		return;
	}

	$abilities = wp_get_abilities();
	if ( ! is_array( $abilities ) ) {
		return;
	}

	foreach ( $abilities as $ability ) {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) || ! method_exists( $ability, 'get_description' ) ) {
			continue;
		}

		$ability_name = (string) $ability->get_name();
		if ( '' === $ability_name || ToolRegistry::get( $ability_name ) ) {
			continue;
		}

		if ( ! webo_wordpress_mcp_should_bridge_ability( $ability_name ) ) {
			continue;
		}

		$input_schema = method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : array();
		$category     = method_exists( $ability, 'get_category' ) ? (string) $ability->get_category() : 'wordpress';
		$description  = (string) $ability->get_description();

		$visibility = 'internal';
		if ( method_exists( $ability, 'get_meta' ) ) {
			$meta = $ability->get_meta();
			if ( is_array( $meta ) && isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && array_key_exists( 'public', $meta['mcp'] ) ) {
				$visibility = (bool) $meta['mcp']['public'] ? 'public' : 'internal';
			}
		}

		ToolRegistry::register(
			array(
				'name'        => $ability_name,
				'description' => '' !== $description ? $description : sprintf( 'Execute ability: %s', $ability_name ),
				'category'    => '' !== $category ? $category : 'wordpress',
				'visibility'  => $visibility,
				'arguments'   => webo_wordpress_mcp_convert_input_schema_to_tool_arguments( is_array( $input_schema ) ? $input_schema : array() ),
				'callback'    => static function ( array $arguments ) use ( $ability_name ) {
					$ability_instance = wp_get_ability( $ability_name );
					if ( ! $ability_instance || ! method_exists( $ability_instance, 'execute' ) ) {
						return new WP_Error( 'webo_wordpress_mcp_ability_not_found', sprintf( 'Ability not found: %s', $ability_name ) );
					}

					return $ability_instance->execute( $arguments );
				},
			)
		);
	}
}

/**
 * Whether ability should be bridged into MCP tools by default.
 *
 * @param string $ability_name Ability name.
 * @return bool
 */
function webo_wordpress_mcp_should_bridge_ability( string $ability_name ) {
	$ability_name = trim( $ability_name );
	if ( '' === $ability_name ) {
		return false;
	}

	$deny_patterns = apply_filters(
		'webo_wordpress_mcp_bridge_deny_patterns',
		array(
			'bulk',
			'plugins/',
			'themes/',
			'multisite/',
		)
	);

	if ( is_array( $deny_patterns ) ) {
		foreach ( $deny_patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' !== $pattern && false !== strpos( $ability_name, $pattern ) ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Registers standalone WordPress.org-safe tools.
 *
 * @return void
 */
function webo_wordpress_mcp_register_standalone_core_tools() {
	$tools = array(
		array(
			'name'        => 'webo/get-site-info',
			'description' => 'Get basic WordPress site diagnostics information',
			'category'    => 'wordpress',
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'get_site_info' ),
		),
		array(
			'name'        => 'webo/list-posts',
			'description' => 'List WordPress posts',
			'category'    => 'wordpress',
			'arguments'   => array(
				'per_page'  => array( 'type' => 'integer', 'required' => false, 'default' => 10, 'min' => 1, 'max' => 100 ),
				'post_type' => array( 'type' => 'string', 'required' => false, 'default' => 'post' ),
				'search'    => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				'status'    => array( 'type' => 'string', 'required' => false, 'default' => 'publish' ),
			),
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'list_posts' ),
		),
		array(
			'name'        => 'webo/get-post',
			'description' => 'Get one post by ID',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
			),
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'get_post' ),
		),
		array(
			'name'        => 'webo/create-post',
			'description' => 'Create one post/page/custom post',
			'category'    => 'wordpress',
			'arguments'   => array(
				'title'     => array( 'type' => 'string', 'required' => true ),
				'content'   => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				'post_type' => array( 'type' => 'string', 'required' => false, 'default' => 'post' ),
				'status'    => array( 'type' => 'string', 'required' => false, 'default' => 'draft' ),
			),
			'permission'  => 'edit_posts',
			'callback'    => array( WordPressTools::class, 'create_post' ),
		),
		array(
			'name'        => 'webo/update-post',
			'description' => 'Update one post/page/custom post',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'title'   => array( 'type' => 'string', 'required' => false ),
				'content' => array( 'type' => 'string', 'required' => false ),
				'status'  => array( 'type' => 'string', 'required' => false ),
			),
			'permission'  => 'edit_posts',
			'callback'    => array( WordPressTools::class, 'update_post' ),
		),
		array(
			'name'        => 'webo/delete-post',
			'description' => 'Delete one post only (no bulk)',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'force'   => array( 'type' => 'boolean', 'required' => false, 'default' => false ),
			),
			'permission'  => 'delete_posts',
			'callback'    => array( WordPressTools::class, 'delete_post' ),
		),
		array(
			'name'        => 'webo/list-users',
			'description' => 'List users (limited)',
			'category'    => 'wordpress',
			'arguments'   => array(
				'per_page' => array( 'type' => 'integer', 'required' => false, 'default' => 20, 'min' => 1, 'max' => 100 ),
				'search'   => array( 'type' => 'string', 'required' => false, 'default' => '' ),
			),
			'permission'  => 'list_users',
			'callback'    => array( WordPressTools::class, 'list_users' ),
		),
		array(
			'name'        => 'webo/list-media',
			'description' => 'List media library items',
			'category'    => 'wordpress',
			'arguments'   => array(
				'per_page' => array( 'type' => 'integer', 'required' => false, 'default' => 20, 'min' => 1, 'max' => 100 ),
			),
			'permission'  => 'upload_files',
			'callback'    => array( WordPressTools::class, 'list_media' ),
		),
		array(
			'name'        => 'webo/list-comments',
			'description' => 'List comments',
			'category'    => 'wordpress',
			'arguments'   => array(
				'per_page' => array( 'type' => 'integer', 'required' => false, 'default' => 20, 'min' => 1, 'max' => 100 ),
				'status'   => array( 'type' => 'string', 'required' => false, 'default' => 'approve' ),
			),
			'permission'  => 'moderate_comments',
			'callback'    => array( WordPressTools::class, 'list_comments' ),
		),
		array(
			'name'        => 'webo/list-terms',
			'description' => 'List terms by taxonomy',
			'category'    => 'wordpress',
			'arguments'   => array(
				'taxonomy' => array( 'type' => 'string', 'required' => false, 'default' => 'category' ),
				'per_page' => array( 'type' => 'integer', 'required' => false, 'default' => 50, 'min' => 1, 'max' => 100 ),
			),
			'permission'  => 'manage_categories',
			'callback'    => array( WordPressTools::class, 'list_terms' ),
		),
		array(
			'name'        => 'webo/list-active-plugins',
			'description' => 'List installed plugins and active status',
			'category'    => 'wordpress',
			'arguments'   => array(
				'include_inactive' => array( 'type' => 'boolean', 'required' => false, 'default' => false ),
			),
			'permission'  => 'activate_plugins',
			'callback'    => array( WordPressTools::class, 'list_active_plugins' ),
		),
		array(
			'name'        => 'webo/get-options',
			'description' => 'Read selected safe options',
			'category'    => 'wordpress',
			'arguments'   => array(
				'names' => array( 'type' => 'array', 'required' => true ),
			),
			'permission'  => 'manage_options',
			'callback'    => array( WordPressTools::class, 'get_options' ),
		),
		array(
			'name'        => 'webo/update-options',
			'description' => 'Update selected safe options',
			'category'    => 'wordpress',
			'arguments'   => array(
				'options' => array( 'type' => 'array', 'required' => true ),
			),
			'permission'  => 'manage_options',
			'callback'    => array( WordPressTools::class, 'update_options' ),
		),
	);

	foreach ( $tools as $tool ) {
		ToolRegistry::register( $tool );
	}
}

function webo_wordpress_mcp_bootstrap() {
	webo_wordpress_mcp_register_standalone_core_tools();

	do_action( 'webo_wordpress_mcp_register_tools' );

	$auto_bridge = (bool) apply_filters( 'webo_wordpress_mcp_auto_bridge_abilities', true );
	if ( $auto_bridge ) {
		webo_wordpress_mcp_register_wordpress_abilities();
	}
}
add_action( 'init', 'webo_wordpress_mcp_bootstrap', 20 );

/**
 * Bootstraps WordPress MCP Adapter runtime.
 *
 * @return void
 */
function webo_wordpress_mcp_bootstrap_adapter() {
	$enable_adapter = (bool) apply_filters( 'webo_wordpress_mcp_enable_adapter', true );
	if ( ! $enable_adapter ) {
		return;
	}

	if ( class_exists( McpAdapter::class ) ) {
		McpAdapter::instance();
	}
}
add_action( 'plugins_loaded', 'webo_wordpress_mcp_bootstrap_adapter', 20 );

/**
 * Returns MCP-compatible tools/list response payload.
 *
 * @return array<string, array<int, array<string, string>>>
 */
function webo_wordpress_mcp_list_tools() {
	return ToolRegistry::list_tools();
}

/**
 * Optional REST discovery endpoint for diagnostics.
 *
 * GET /wp-json/webo-wordpress-mcp/v1/tools
 *
 * @return void
 */
function webo_wordpress_mcp_register_rest_routes() {
	register_rest_route(
		'webo-wordpress-mcp/v1',
		'/tools',
		array(
			'methods'             => 'GET',
			'callback'            => static function ( \WP_REST_Request $request ) {
				$include_internal = false;

				if ( current_user_can( 'manage_options' ) ) {
					$include_internal = filter_var( $request->get_param( 'include_internal' ), FILTER_VALIDATE_BOOLEAN );
				}

				$all_payload = ToolRegistry::list_tools( true );
				$payload     = ToolRegistry::list_tools( $include_internal );
				$all_tools   = isset( $all_payload['tools'] ) && is_array( $all_payload['tools'] ) ? $all_payload['tools'] : array();
				$tools       = isset( $payload['tools'] ) && is_array( $payload['tools'] ) ? $payload['tools'] : array();

				return rest_ensure_response(
					array(
						'tools' => $tools,
						'meta'  => array(
							'registered_total' => count( $all_tools ),
							'returned_total'   => count( $tools ),
							'include_internal' => $include_internal,
						),
					)
				);
			},
			'permission_callback' => static function () {
				return current_user_can( 'read' );
			},
		)
	);
}
add_action( 'rest_api_init', 'webo_wordpress_mcp_register_rest_routes' );

/**
 * Registers MCP JSON-RPC router endpoint.
 *
 * POST /wp-json/mcp/v1/router
 *
 * @return void
 */
function webo_wordpress_mcp_register_mcp_router() {
	McpRouter::register_rest_endpoint();
}
add_action( 'rest_api_init', 'webo_wordpress_mcp_register_mcp_router' );
