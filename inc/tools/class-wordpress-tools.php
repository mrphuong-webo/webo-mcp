<?php

namespace WeboMCP\Core\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WordPressTools {

	/**
	 * Core tool: list WordPress posts.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function list_posts( array $arguments ) {
		$per_page  = isset( $arguments['per_page'] ) ? (int) $arguments['per_page'] : 10;
		$post_type = isset( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : 'post';

		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => max( 1, min( 100, $per_page ) ),
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);

		$items = array();
		foreach ( $query->posts as $post_id ) {
			$items[] = array(
				'id'    => $post_id,
				'title' => get_the_title( $post_id ),
				'link'  => get_permalink( $post_id ),
			);
		}

		return array(
			'items' => $items,
			'total' => count( $items ),
			'tool'  => 'webo/list-posts',
		);
	}
}
