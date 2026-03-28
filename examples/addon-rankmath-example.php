<?php
/**
 * Minimal custom tool registration example (`webo_mcp_register_tools`).
 *
 * Naming (see skills/webo-mcp-extensions/SKILL.md):
 * - Core tools: `webo/*` (reserved).
 * - Production Rank Math: install https://github.com/mrphuong-webo/webo-mcp-rank-math — Abilities `webo-rank-math/*` bridged to MCP (skills/webo-mcp-rank-math).
 * - Demo below uses `rankmath/*` only to illustrate `ToolRegistry::register`; do not treat it as the official Rank Math tool prefix.
 */

namespace WeboMCP\Addon\RankMath;

use WeboMCP\Core\Registry\ToolRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RankMathTools {

	/**
	 * Example tool callback.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function get_keywords( array $arguments ) {
		$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;

		return array(
			'post_id'  => $post_id,
			'keywords' => array(),
			'tool'     => 'rankmath/get-keywords',
		);
	}
}

add_action(
	'webo_mcp_register_tools',
	static function () {
		ToolRegistry::register(
			array(
				'name'        => 'rankmath/get-keywords',
				'description' => 'Get RankMath focus keywords',
				'category'    => 'seo',
				'arguments'   => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'permission'  => 'edit_posts',
				'callback'    => array( RankMathTools::class, 'get_keywords' ),
			)
		);
	}
);

// Example execution:
// $result = ToolRegistry::call( 'rankmath/get-keywords', array( 'post_id' => 123 ) );
