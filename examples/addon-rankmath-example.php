<?php
/**
 * Minimal Rank Math tool registration example.
 *
 * For production parity with https://github.com/bjornfix/mcp-abilities-rankmath
 * (23 `rankmath/*` tools), use the bundled add-on in `../mcp-rank-math/`.
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
