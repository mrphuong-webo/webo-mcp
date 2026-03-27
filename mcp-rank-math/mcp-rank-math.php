<?php
/**
 * Plugin Name: MCP Rank Math (WEBO)
 * Description: Exposes Rank Math SEO as WEBO MCP tools (rankmath/*). Companion to WEBO MCP — no Abilities API required. Inspired by the rankmath ability set from mcp-abilities-rankmath.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: WEBO MCP
 * License: GPLv2 or later
 * Text Domain: mcp-rank-math
 *
 * @package WeboMCP_Rank_Math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEBO_MCP_RANK_MATH_FILE', __FILE__ );
define( 'WEBO_MCP_RANK_MATH_PATH', dirname( WEBO_MCP_RANK_MATH_FILE ) );

require_once WEBO_MCP_RANK_MATH_PATH . '/includes/helpers.php';
require_once WEBO_MCP_RANK_MATH_PATH . '/includes/class-rank-math-tools.php';

/**
 * Admin notice if dependencies missing.
 */
function webo_mcp_rank_math_dependency_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$deps = array();
	if ( ! class_exists( '\WeboMCP\Core\Registry\ToolRegistry' ) ) {
		$deps[] = 'WEBO MCP (webo-mcp)';
	}
	if ( ! webo_mcp_rank_math_active() ) {
		$deps[] = 'Rank Math SEO';
	}
	if ( empty( $deps ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	echo esc_html( sprintf( __( 'MCP Rank Math (WEBO) requires: %s', 'mcp-rank-math' ), implode( ', ', $deps ) ) );
	echo '</p></div>';
}

add_action(
	'admin_notices',
	static function () {
		if ( class_exists( '\WeboMCP\Core\Registry\ToolRegistry' ) && webo_mcp_rank_math_active() ) {
			return;
		}
		webo_mcp_rank_math_dependency_notice();
	}
);

\WeboMCP\RankMath\Rank_Math_Tools::init();
