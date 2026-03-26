<?php
/**
 * Access gate for WEBO MCP REST routes (session, tools/list, tools/call).
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default: network super admins, site Administrators (`manage_options`), or content roles (`edit_posts`).
 * The REST layer still requires {@see current_user_can}( 'read' ). Each tool enforces its own capability
 * (e.g. list-nav-menus uses edit_posts; menu mutations use edit_theme_options).
 *
 * Tighten with {@see apply_filters}( 'webo_mcp_current_user_can_use_mcp', … ).
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

		$allowed = is_super_admin( $user_id )
			|| user_can( $user_id, 'manage_options' )
			|| user_can( $user_id, 'edit_posts' );

		return (bool) apply_filters( 'webo_mcp_current_user_can_use_mcp', $allowed, $user_id );
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function require_mcp_access_or_error() {
		if ( ! self::current_user_can_use_mcp() ) {
			return new \WP_Error(
				'webo_mcp_access_denied',
				__( 'You do not have permission to use MCP. Sign in as a user with edit_posts or higher, or use a configured API key / HMAC.', 'webo-mcp' ),
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

			$admins = get_users(
				array(
					'blog_id' => get_current_blog_id(),
					'role'    => 'administrator',
					'number'  => 1,
					'orderby' => 'ID',
				)
			);
			if ( ! empty( $admins ) && $admins[0] instanceof \WP_User ) {
				wp_set_current_user( (int) $admins[0]->ID );
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
