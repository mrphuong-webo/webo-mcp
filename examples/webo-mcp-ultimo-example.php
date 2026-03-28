<?php
/**
 * Example: companion plugin (e.g. webo-mcp-ultimo) aligned with WEBO MCP + WP Ultimo.
 *
 * Package convention: `webo-mcp-<product>` (see skills/webo-mcp-extensions/SKILL.md).
 *
 * Core (2.0.20+): default MCP access is super admin OR manage_options OR edit_posts.
 * Use filter `webo_mcp_current_user_can_use_mcp` to tighten (e.g. customers: super admin only).
 *
 * Recommended:
 * - Depend on webo-mcp + wp-ultimo; load after WEBO MCP.
 * - Register Ultimo-only tools on `webo_mcp_register_tools`; use tool prefix other than `webo/` (e.g. `webo-ultimo/*`).
 * - Enforce WP Ultimo capabilities / site context inside callbacks only; do not bypass the network gate unless required.
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
