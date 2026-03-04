<?php
/**
 * Plugin Name: WEBO MCP Core
 * Description: Core tool registry platform for WEBO MCP ecosystem.
 * Version: 1.0.0
 * Author: WEBO
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: webo-mcp-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/inc/registry/class-tool-registry.php';
require_once __DIR__ . '/inc/tools/class-wordpress-tools.php';

use WeboMCP\Core\Registry\ToolRegistry;
use WeboMCP\Core\Tools\WordPressTools;

function webo_mcp_core_bootstrap() {
	ToolRegistry::register(
		array(
			'name'        => 'webo/list-posts',
			'description' => 'List WordPress posts',
			'category'    => 'wordpress',
			'arguments'   => array(
				'per_page' => array(
					'type'     => 'integer',
					'required' => false,
					'default'  => 10,
					'min'      => 1,
					'max'      => 100,
				),
				'post_type' => array(
					'type'     => 'string',
					'required' => false,
					'default'  => 'post',
				),
			),
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'list_posts' ),
		)
	);

	do_action( 'webo_mcp_register_tools' );
}
add_action( 'init', 'webo_mcp_core_bootstrap', 20 );

/**
 * Returns MCP-compatible tools/list response payload.
 *
 * @return array<string, array<int, array<string, string>>>
 */
function webo_mcp_core_list_tools() {
	return ToolRegistry::list_tools();
}

/**
 * Optional REST discovery endpoint for diagnostics.
 *
 * GET /wp-json/webo-mcp-core/v1/tools
 *
 * @return void
 */
function webo_mcp_core_register_rest_routes() {
	register_rest_route(
		'webo-mcp-core/v1',
		'/tools',
		array(
			'methods'             => 'GET',
			'callback'            => static function () {
				return rest_ensure_response( webo_mcp_core_list_tools() );
			},
			'permission_callback' => static function () {
				return current_user_can( 'read' );
			},
		)
	);
}
add_action( 'rest_api_init', 'webo_mcp_core_register_rest_routes' );
