<?php
/**
 * Extra validation for MCP tools/call (defense in depth).
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AccessHelper.php';

class SecurityHelper {
	/**
	 * Ensure tools/call runs only for an authenticated, authorized MCP user.
	 * Primary auth and optional API key/HMAC are enforced in {@see McpRouter::secure_permission_callback()}.
	 *
	 * @param \WP_REST_Request $request Request (auth already enforced on this route).
	 * @return true|\WP_Error
	 */
	public static function validate( \WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			return new \WP_Error(
				'webo_mcp_unauthorized',
				__( 'Unauthorized request for tools/call', 'webo-mcp' ),
				array( 'status' => 401 )
			);
		}

		return AccessHelper::require_mcp_access_or_error();
	}
}
