<?php
/**
 * MCP health/status tool.
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Tools;

use WeboMCP\Core\Audit\Audit_Log;
use WeboMCP\Core\Registry\ToolRegistry;
use WeboMCP\Core\Router\ClientToolPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports read-only WordPress and WEBO MCP status for administrators.
 */
final class HealthStatusTool {
	/**
	 * Run the health status report.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function run( array $arguments ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'webo_mcp_permission_denied',
				__( 'You do not have permission to view MCP health status.', 'webo-mcp' ),
				array( 'status' => 403 )
			);
		}

		$refresh_updates = ! empty( $arguments['refresh_updates'] );
		if ( $refresh_updates && current_user_can( 'update_plugins' ) && function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}

		return array(
			'tool'                  => 'webo/health-status',
			'timestamp_gmt'         => gmdate( 'c' ),
			'wordpress'             => self::wordpress_status(),
			'rest'                  => self::rest_status(),
			'application_passwords' => self::application_password_status(),
			'permalinks'            => self::permalink_status(),
			'cron'                  => self::cron_status(),
			'object_cache'          => self::object_cache_status(),
			'plugin_updates'        => self::plugin_updates_status(),
			'mcp'                   => self::mcp_status(),
		);
	}

	/**
	 * WordPress/PHP version status.
	 *
	 * @return array<string, mixed>
	 */
	private static function wordpress_status(): array {
		return array(
			'wp_version'       => get_bloginfo( 'version' ),
			'php_version'      => PHP_VERSION,
			'environment_type' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '',
			'is_multisite'     => is_multisite(),
			'home_url'         => home_url( '/' ),
			'site_url'         => site_url( '/' ),
		);
	}

	/**
	 * REST routing assumptions.
	 *
	 * @return array<string, mixed>
	 */
	private static function rest_status(): array {
		return array(
			'rest_url'        => get_rest_url(),
			'router_url'      => get_rest_url( null, 'mcp/v1/router' ),
			'route_namespace' => 'mcp/v1',
			'route'           => '/router',
			'available'       => function_exists( 'register_rest_route' ),
		);
	}

	/**
	 * Application Password support and availability.
	 *
	 * @return array<string, mixed>
	 */
	private static function application_password_status(): array {
		$user_id = get_current_user_id();

		return array(
			'supported'          => function_exists( 'wp_is_application_passwords_supported' ) ? wp_is_application_passwords_supported() : function_exists( 'wp_authenticate_application_password' ),
			'available'          => function_exists( 'wp_is_application_passwords_available' ) ? wp_is_application_passwords_available() : function_exists( 'wp_authenticate_application_password' ),
			'available_for_user' => ( $user_id > 0 && function_exists( 'wp_is_application_passwords_available_for_user' ) ) ? wp_is_application_passwords_available_for_user( $user_id ) : null,
		);
	}

	/**
	 * Permalink status.
	 *
	 * @return array<string, mixed>
	 */
	private static function permalink_status(): array {
		$structure = (string) get_option( 'permalink_structure', '' );

		return array(
			'pretty_permalinks' => '' !== $structure,
			'structure'         => '' !== $structure ? $structure : 'plain',
		);
	}

	/**
	 * Cron status summary.
	 *
	 * @return array<string, mixed>
	 */
	private static function cron_status(): array {
		$cron = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();

		return array(
			'disabled'              => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'alternate_enabled'     => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
			'event_timestamp_count' => is_array( $cron ) ? count( $cron ) : 0,
			'next_version_check'    => wp_next_scheduled( 'wp_version_check' ),
		);
	}

	/**
	 * Object cache summary.
	 *
	 * @return array<string, mixed>
	 */
	private static function object_cache_status(): array {
		return array(
			'using_external_object_cache' => wp_using_ext_object_cache(),
			'can_flush_group'             => function_exists( 'wp_cache_supports' ) ? wp_cache_supports( 'flush_group' ) : null,
			'can_flush_runtime'           => function_exists( 'wp_cache_supports' ) ? wp_cache_supports( 'flush_runtime' ) : null,
		);
	}

	/**
	 * Plugin update summary without listing secret-bearing plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function plugin_updates_status(): array {
		$updates = get_site_transient( 'update_plugins' );
		$response = is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ? $updates->response : array();
		$checked  = is_object( $updates ) && isset( $updates->checked ) && is_array( $updates->checked ) ? $updates->checked : array();

		return array(
			'available_count' => count( $response ),
			'checked_count'   => count( $checked ),
			'last_checked'    => is_object( $updates ) && isset( $updates->last_checked ) ? (int) $updates->last_checked : 0,
		);
	}

	/**
	 * MCP-specific router/config status.
	 *
	 * @return array<string, mixed>
	 */
	private static function mcp_status(): array {
		$all_tools = ToolRegistry::list();

		return array(
			'router_endpoint'        => get_rest_url( null, 'mcp/v1/router' ),
			'legacy_endpoint'        => get_rest_url( null, 'mcp/mcp-adapter-default-server' ),
			'adapter_enabled'        => (bool) apply_filters( 'webo_mcp_enable_adapter', true ),
			'registered_tool_count'  => count( $all_tools ),
			'api_key_configured'     => '' !== (string) get_option( 'webo_mcp_api_key', '' ),
			'hmac_configured'        => '' !== (string) get_option( 'webo_mcp_hmac_secret', '' ),
			'tool_allowlist_enabled' => ClientToolPolicy::is_enabled(),
			'audit_log_enabled'      => Audit_Log::is_enabled(),
			'audit_log_entries'      => Audit_Log::count(),
			'audit_log_max_entries'  => Audit_Log::max_entries(),
		);
	}
}
