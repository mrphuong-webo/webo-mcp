<?php
/**
 * Example: keep webo-mcp-ultimo aligned with WEBO MCP core + WP Ultimo.
 *
 * Core (2.0.20+): default MCP access is super admin OR manage_options OR edit_posts.
 * Use filter `webo_mcp_current_user_can_use_mcp` to tighten (e.g. customers: super admin only).
 *
 * Recommended for webo-mcp-ultimo:
 * - Require this file after webo-mcp loads; depend on webo-mcp + wp-ultimo.
 * - Register Ultimo-only tools on `webo_mcp_register_tools` (same as Rank Math example).
 * - Use WP Ultimo capabilities / customer context only inside tool callbacks; do not
 *   bypass the network gate unless business rules require it.
 *
 * @package WeboMCP\Examples
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
add_filter(
	'webo_mcp_current_user_can_use_mcp',
	static function ( bool $allowed, int $user_id ): bool {
		if ( $allowed ) {
			return true;
		}
		// Optional: grant MCP to a WP Ultimo-defined role on top of core check.
		// return user_can( $user_id, 'some_ultimo_cap' );

		return $allowed;
	},
	10,
	2
);
*/
