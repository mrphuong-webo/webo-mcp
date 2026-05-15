<?php
/**
 * WordPress/Core feature detection for WEBO MCP.
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defensive feature checks for Core-aware MCP behavior.
 */
final class Core_Features {
	/**
	 * Tracks whether the bundled Abilities API fallback was loaded.
	 *
	 * @var bool
	 */
	private static $bundled_abilities_loaded = false;

	/**
	 * Tracks whether the bundled MCP Adapter autoloader was registered.
	 *
	 * @var bool
	 */
	private static $bundled_mcp_adapter_loaded = false;

	/**
	 * WordPress version.
	 *
	 * @return string
	 */
	public static function get_wordpress_version() {
		return function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '';
	}

	/**
	 * PHP version.
	 *
	 * @return string
	 */
	public static function get_php_version() {
		return PHP_VERSION;
	}

	/**
	 * Whether Abilities API is available.
	 *
	 * @return bool
	 */
	public static function has_abilities_api() {
		return function_exists( 'wp_register_ability' )
			&& function_exists( 'wp_get_abilities' )
			&& function_exists( 'wp_get_ability' )
			&& class_exists( 'WP_Ability', false );
	}

	/**
	 * Whether the MCP Adapter package/classes are available.
	 *
	 * @return bool
	 */
	public static function has_mcp_adapter() {
		return class_exists( 'WP\MCP\Core\McpAdapter', false )
			|| class_exists( 'WP\MCP\Plugin', false )
			|| defined( 'WP_MCP_VERSION' );
	}

	/**
	 * Whether Connectors API is available.
	 *
	 * @return bool
	 */
	public static function has_connectors_api() {
		return function_exists( 'wp_get_connectors' )
			|| function_exists( 'wp_get_connector' )
			|| class_exists( 'WP_Connector_Registry', false );
	}

	/**
	 * Whether the WP AI support flag API is available.
	 *
	 * @return bool
	 */
	public static function has_wp_ai_support_flag() {
		return function_exists( 'wp_supports_ai' ) || defined( 'WP_AI_SUPPORT' );
	}

	/**
	 * Whether WordPress AI support is currently enabled.
	 *
	 * @return bool
	 */
	public static function ai_supported() {
		if ( function_exists( 'wp_supports_ai' ) ) {
			return (bool) wp_supports_ai();
		}

		if ( defined( 'WP_AI_SUPPORT' ) ) {
			return (bool) WP_AI_SUPPORT;
		}

		return false;
	}

	/**
	 * Returns the detected Abilities API source.
	 *
	 * @return string core|bundled|none
	 */
	public static function abilities_api_source() {
		if ( self::$bundled_abilities_loaded ) {
			return 'bundled';
		}

		return self::has_abilities_api() ? 'core' : 'none';
	}

	/**
	 * Returns the detected MCP Adapter source.
	 *
	 * @return string external|bundled|none
	 */
	public static function mcp_adapter_source() {
		if ( self::$bundled_mcp_adapter_loaded ) {
			return 'bundled';
		}

		return self::has_mcp_adapter() ? 'external' : 'none';
	}

	/**
	 * Mark the bundled Abilities fallback as loaded.
	 *
	 * @return void
	 */
	public static function mark_bundled_abilities_loaded() {
		self::$bundled_abilities_loaded = true;
	}

	/**
	 * Mark the bundled MCP Adapter autoloader as loaded.
	 *
	 * @return void
	 */
	public static function mark_bundled_mcp_adapter_loaded() {
		self::$bundled_mcp_adapter_loaded = true;
	}

	/**
	 * Whether a default MCP Adapter server route appears to exist.
	 *
	 * @return bool
	 */
	public static function default_mcp_server_detected() {
		if ( ! function_exists( 'rest_get_server' ) ) {
			return false;
		}

		$server = rest_get_server();
		if ( ! $server || ! method_exists( $server, 'get_routes' ) ) {
			return false;
		}

		$routes = $server->get_routes();
		return is_array( $routes ) && isset( $routes['/mcp/mcp-adapter-default-server'] );
	}
}
