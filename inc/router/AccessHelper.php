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
 * The REST layer requires a real WordPress session: Application Password (Basic Auth) or cookie auth.
 * Optional site-wide or per-user API key and HMAC apply only after that identity is established.
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
				__( 'You do not have permission to use MCP. Use an account with edit_posts or higher.', 'webo-mcp' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Establish the current user using WordPress Application Password (HTTP Basic) or an existing session.
	 * Does not impersonate arbitrary users from shared secrets alone (WordPress.org plugin guidelines).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool True if the current user can proceed (logged in with read capability).
	 */
	public static function try_authenticate_application_password( \WP_REST_Request $request ): bool {
		if ( is_user_logged_in() && current_user_can( 'read' ) ) {
			return true;
		}

		if ( ! function_exists( 'wp_authenticate_application_password' ) ) {
			return false;
		}

		$auth_header = (string) $request->get_header( 'Authorization' );
		if ( '' === $auth_header ) {
			foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $server_key ) {
				if ( isset( $_SERVER[ $server_key ] ) && is_string( $_SERVER[ $server_key ] ) && $_SERVER[ $server_key ] !== '' ) {
					$auth_header = (string) $_SERVER[ $server_key ];
					break;
				}
			}
		}

		if ( strpos( $auth_header, 'Basic ' ) !== 0 ) {
			return false;
		}

		$encoded = trim( substr( $auth_header, 6 ) );
		if ( '' === $encoded ) {
			return false;
		}

		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded ) {
			return false;
		}

		$colon = strpos( $decoded, ':' );
		if ( false === $colon ) {
			return false;
		}

		$username = substr( $decoded, 0, $colon );
		$password = substr( $decoded, $colon + 1 );
		if ( '' === $username || '' === $password ) {
			return false;
		}

		$user = wp_authenticate_application_password( null, $username, $password );
		if ( $user instanceof \WP_User && ! is_wp_error( $user ) ) {
			wp_set_current_user( $user->ID );
			return current_user_can( 'read' );
		}

		return false;
	}

	/**
	 * If configured, require X-WEBO-API-KEY and/or valid HMAC headers after the user is authenticated.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public static function validate_secondary_credentials( \WP_REST_Request $request ) {
		$hmac_secret  = (string) get_option( 'webo_mcp_hmac_secret', '' );
		$global_key   = (string) get_option( 'webo_mcp_api_key', '' );
		$provided_key = trim( (string) $request->get_header( 'X-WEBO-API-KEY' ) );

		if ( $global_key !== '' ) {
			if ( $provided_key === '' || ! hash_equals( $global_key, $provided_key ) ) {
				return new \WP_Error(
					'webo_mcp_bad_api_key',
					__( 'Missing or invalid X-WEBO-API-KEY header.', 'webo-mcp' ),
					array( 'status' => 401 )
				);
			}
		} else {
			$uid = get_current_user_id();
			if ( $uid > 0 ) {
				$user_key = (string) get_user_meta( $uid, 'webo_mcp_api_key', true );
				if ( $user_key !== '' ) {
					if ( $provided_key === '' || ! hash_equals( $user_key, $provided_key ) ) {
						return new \WP_Error(
							'webo_mcp_bad_api_key',
							__( 'Missing or invalid X-WEBO-API-KEY for this user.', 'webo-mcp' ),
							array( 'status' => 401 )
						);
					}
				}
			}
		}

		if ( $hmac_secret !== '' ) {
			$signature = (string) $request->get_header( 'X-WEBO-SIGNATURE' );
			$timestamp = (string) $request->get_header( 'X-WEBO-TIMESTAMP' );
			if ( $signature === '' || $timestamp === '' || ! ctype_digit( $timestamp ) ) {
				return new \WP_Error(
					'webo_mcp_bad_hmac',
					__( 'When an HMAC secret is configured, requests require X-WEBO-TIMESTAMP and X-WEBO-SIGNATURE headers.', 'webo-mcp' ),
					array( 'status' => 401 )
				);
			}
			$now           = time();
			$request_epoch = (int) $timestamp;
			if ( abs( $now - $request_epoch ) > 300 ) {
				return new \WP_Error(
					'webo_mcp_bad_hmac',
					__( 'Request timestamp skew too large.', 'webo-mcp' ),
					array( 'status' => 401 )
				);
			}
			$payload            = $timestamp . '.' . (string) $request->get_body();
			$expected_signature = 'sha256=' . hash_hmac( 'sha256', $payload, $hmac_secret );
			if ( ! hash_equals( $expected_signature, trim( $signature ) ) ) {
				return new \WP_Error(
					'webo_mcp_bad_hmac',
					__( 'Invalid HMAC signature.', 'webo-mcp' ),
					array( 'status' => 401 )
				);
			}
		}

		return true;
	}
}
