<?php
/**
 * Network-level access gate for WEBO MCP (multisite super admin).
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP may only be used by users who pass {@see is_super_admin()} by default.
 * On multisite that is the network “Super Admin” list; on single site, WordPress maps this
 * to users who satisfy core’s non-multisite super-admin check (typically full administrators).
 *
 * Add-ons (e.g. webo-mcp-ultimo + WP Ultimo) should use filter `webo_mcp_current_user_can_use_mcp`
 * only if they must layer extra rules; keep core default strict for security.
 */
class AccessHelper {

	/**
	 * Whether the current user may call MCP routes and tools.
	 *
	 * @return bool
	 */
	public static function current_user_can_use_mcp(): bool {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}

		$allowed = is_super_admin( $user_id );

		return (bool) apply_filters( 'webo_mcp_current_user_can_use_mcp', $allowed, $user_id );
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function require_mcp_access_or_error() {
		if ( ! self::current_user_can_use_mcp() ) {
			return new \WP_Error(
				'webo_mcp_super_admin_required',
				__( 'MCP is only available to network administrators.', 'webo-mcp' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * For global API key / HMAC: set `current_user` to a user who can legally run MCP.
	 * Multisite: first login from `get_super_admins()`. Single site: first administrator (legacy).
	 *
	 * @return void
	 */
	public static function bootstrap_current_user_for_signed_request(): void {
		if ( is_user_logged_in() && current_user_can( 'read' ) ) {
			return;
		}

		if ( is_multisite() ) {
			$super_logins = get_super_admins();
			if ( is_array( $super_logins ) ) {
				foreach ( $super_logins as $login ) {
					$login = (string) $login;
					if ( '' === $login ) {
						continue;
					}
					$user = get_user_by( 'login', $login );
					if ( $user instanceof \WP_User ) {
						wp_set_current_user( $user->ID );
						return;
					}
				}
			}

			return;
		}

		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
			)
		);

		if ( ! empty( $admins ) && $admins[0] instanceof \WP_User ) {
			wp_set_current_user( (int) $admins[0]->ID );
		}
	}
}
