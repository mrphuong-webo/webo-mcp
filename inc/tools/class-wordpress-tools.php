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
		$search    = isset( $arguments['search'] ) ? sanitize_text_field( (string) $arguments['search'] ) : '';
		$status    = isset( $arguments['status'] ) ? sanitize_key( (string) $arguments['status'] ) : 'publish';

		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => max( 1, min( 100, $per_page ) ),
				'post_status'    => $status,
				's'              => $search,
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

	/**
	 * Get a single post by ID.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_post( array $arguments ) {
		$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new \WP_Error(
				'webo_mcp_cannot_read_post',
				'You do not have permission to read this post',
				array( 'status' => 403 )
			);
		}

		$expected_type = isset( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : '';
		if ( '' !== $expected_type && $post->post_type !== $expected_type ) {
			return new \WP_Error(
				'webo_mcp_post_type_mismatch',
				sprintf(
					'Post ID %d is type "%s", expected "%s"',
					$post_id,
					$post->post_type,
					$expected_type
				),
				array(
					'status'        => 400,
					'actual_type'   => $post->post_type,
					'expected_type' => $expected_type,
				)
			);
		}

		$out = array(
			'id'      => $post->ID,
			'title'   => get_the_title( $post ),
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
			'status'  => $post->post_status,
			'type'    => $post->post_type,
			'slug'    => $post->post_name,
			'link'    => get_permalink( $post ),
			'tool'    => 'webo/get-post',
		);

		if ( 'page' === $post->post_type ) {
			$show_on_front = (string) get_option( 'show_on_front', 'posts' );
			$page_on_front = (int) get_option( 'page_on_front', 0 );
			$page_for_posts = (int) get_option( 'page_for_posts', 0 );
			$out['reading'] = array(
				'is_static_front_page' => ( 'page' === $show_on_front && $page_on_front === (int) $post->ID ),
				'is_posts_page'        => ( $page_for_posts === (int) $post->ID ),
				'page_on_front_id'     => $page_on_front,
				'page_for_posts_id'    => $page_for_posts,
			);
		}

		return $out;
	}

	/**
	 * Discover public content types (post types) on the site.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function discover_content_types( array $arguments ) {
		unset( $arguments );

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$items      = array();

		foreach ( $post_types as $post_type ) {
			$items[] = array(
				'name'         => $post_type->name,
				'label'        => $post_type->label,
				'description'  => isset( $post_type->description ) ? (string) $post_type->description : '',
				'hierarchical' => (bool) $post_type->hierarchical,
				'has_archive'  => (bool) $post_type->has_archive,
			);
		}

		return array(
			'items' => $items,
			'total' => count( $items ),
			'tool'  => 'webo/discover-content-types',
		);
	}

	/**
	 * Find content by WordPress URL (path or full URL). Resolves pretty permalinks to post ID and returns content.
	 * Optionally pass update payload to update the post in the same call.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function find_content_by_url( array $arguments ) {
		$url = isset( $arguments['url'] ) ? trim( (string) $arguments['url'] ) : '';
		if ( '' === $url ) {
			return new \WP_Error( 'webo_mcp_url_required', 'url is required' );
		}

		// Normalize: if relative path, make it full URL for url_to_postid().
		if ( 0 !== strpos( $url, 'http' ) ) {
			$url = home_url( '/' . ltrim( $url, '/' ) );
		}

		$post_id = url_to_postid( $url );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'webo_mcp_content_not_found', 'No content found for this URL', array( 'url' => $url ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		$result = array(
			'id'      => $post->ID,
			'title'   => get_the_title( $post ),
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
			'status'  => $post->post_status,
			'type'    => $post->post_type,
			'slug'    => $post->post_name,
			'link'    => get_permalink( $post ),
			'tool'    => 'webo/find-content-by-url',
		);

		// Optional: update the post in the same call (requires edit_posts).
		$update = isset( $arguments['update'] ) && is_array( $arguments['update'] ) ? $arguments['update'] : null;
		if ( ! empty( $update ) ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return new \WP_Error( 'webo_mcp_permission_denied', 'Cannot update content: edit_posts capability required' );
			}
			$payload = array( 'ID' => $post->ID );
			if ( isset( $update['title'] ) ) {
				$payload['post_title'] = sanitize_text_field( (string) $update['title'] );
			}
			if ( isset( $update['content'] ) ) {
				$payload['post_content'] = wp_kses_post( (string) $update['content'] );
			}
			if ( isset( $update['status'] ) ) {
				$payload['post_status'] = sanitize_key( (string) $update['status'] );
			}
			if ( count( $payload ) > 1 && ! is_wp_error( wp_update_post( $payload, true ) ) ) {
				$post    = get_post( $post->ID );
				$result  = array(
					'id'      => $post->ID,
					'title'   => get_the_title( $post ),
					'content' => $post->post_content,
					'excerpt' => $post->post_excerpt,
					'status'  => $post->post_status,
					'type'    => $post->post_type,
					'slug'    => $post->post_name,
					'link'    => get_permalink( $post ),
					'updated' => true,
					'tool'    => 'webo/find-content-by-url',
				);
			}
		}

		return $result;
	}

	/**
	 * Get content by slug (post_name) across one or all public post types.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_content_by_slug( array $arguments ) {
		$slug      = isset( $arguments['slug'] ) ? sanitize_title( (string) $arguments['slug'] ) : '';
		$post_type = isset( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : '';

		if ( '' === $slug ) {
			return new \WP_Error( 'webo_mcp_slug_required', 'slug is required' );
		}

		$types_to_search = array();
		if ( '' !== $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				return new \WP_Error( 'webo_mcp_post_type_not_found', 'Post type not found', array( 'post_type' => $post_type ) );
			}
			$types_to_search[] = $post_type;
		} else {
			$types_to_search = array_keys( get_post_types( array( 'public' => true ) ) );
		}

		foreach ( $types_to_search as $pt ) {
			$query = new \WP_Query(
				array(
					'post_type'      => $pt,
					'name'           => $slug,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			if ( ! empty( $query->posts ) ) {
				$post = get_post( $query->posts[0] );
				if ( $post ) {
					return array(
						'id'      => $post->ID,
						'title'   => get_the_title( $post ),
						'content' => $post->post_content,
						'excerpt' => $post->post_excerpt,
						'status'  => $post->post_status,
						'type'    => $post->post_type,
						'slug'    => $post->post_name,
						'link'    => get_permalink( $post ),
						'tool'    => 'webo/get-content-by-slug',
					);
				}
			}
		}

		return new \WP_Error( 'webo_mcp_content_not_found', 'No content found for slug', array( 'slug' => $slug ) );
	}

	/**
	 * Create a post.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function create_post( array $arguments ) {
		$post_type = isset( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : 'post';
		$title     = isset( $arguments['title'] ) ? sanitize_text_field( (string) $arguments['title'] ) : '';
		$content   = isset( $arguments['content'] ) ? wp_kses_post( (string) $arguments['content'] ) : '';
		$status    = isset( $arguments['status'] ) ? sanitize_key( (string) $arguments['status'] ) : 'draft';

		$post_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => $status,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return array(
			'post_id' => (int) $post_id,
			'tool'    => 'webo/create-post',
		);
	}

	/**
	 * Update an existing post.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_post( array $arguments ) {
		$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		$payload = array(
			'ID' => $post_id,
		);

		if ( isset( $arguments['title'] ) ) {
			$payload['post_title'] = sanitize_text_field( (string) $arguments['title'] );
		}

		if ( isset( $arguments['content'] ) ) {
			$payload['post_content'] = wp_kses_post( (string) $arguments['content'] );
		}

		if ( isset( $arguments['status'] ) ) {
			$payload['post_status'] = sanitize_key( (string) $arguments['status'] );
		}

		$result = wp_update_post( $payload, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'post_id' => (int) $result,
			'tool'    => 'webo/update-post',
		);
	}

	/**
	 * Delete one post.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function delete_post( array $arguments ) {
		$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
		$force   = isset( $arguments['force'] ) ? (bool) $arguments['force'] : false;

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		$result = wp_delete_post( $post_id, $force );
		if ( ! $result ) {
			return new \WP_Error( 'webo_mcp_delete_failed', 'Failed to delete post' );
		}

		return array(
			'post_id' => $post_id,
			'deleted' => true,
			'tool'    => 'webo/delete-post',
		);
	}

	/**
	 * List users.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function list_users( array $arguments ) {
		$per_page = isset( $arguments['per_page'] ) ? (int) $arguments['per_page'] : 20;
		$search   = isset( $arguments['search'] ) ? sanitize_text_field( (string) $arguments['search'] ) : '';

		$users = get_users(
			array(
				'number'         => max( 1, min( 100, $per_page ) ),
				'search'         => '' !== $search ? '*' . $search . '*' : '',
				'search_columns' => array( 'user_login', 'display_name', 'user_email' ),
			)
		);

		$items = array();
		foreach ( $users as $user ) {
			$items[] = array(
				'id'           => (int) $user->ID,
				'login'        => (string) $user->user_login,
				'display_name' => (string) $user->display_name,
				'email'        => (string) $user->user_email,
			);
		}

		return array(
			'items' => $items,
			'total' => count( $items ),
			'tool'  => 'webo/list-users',
		);
	}

	/**
	 * List media items.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function list_media( array $arguments ) {
		$per_page = isset( $arguments['per_page'] ) ? (int) $arguments['per_page'] : 20;
		$query    = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => max( 1, min( 100, $per_page ) ),
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);

		$items = array();
		foreach ( $query->posts as $attachment_id ) {
			$items[] = array(
				'id'    => (int) $attachment_id,
				'title' => get_the_title( $attachment_id ),
				'url'   => wp_get_attachment_url( $attachment_id ),
			);
		}

		return array(
			'items' => $items,
			'total' => count( $items ),
			'tool'  => 'webo/list-media',
		);
	}

	/**
	 * List comments.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function list_comments( array $arguments ) {
		$per_page = isset( $arguments['per_page'] ) ? (int) $arguments['per_page'] : 20;
		$status   = isset( $arguments['status'] ) ? sanitize_key( (string) $arguments['status'] ) : 'approve';

		$comments = get_comments(
			array(
				'number' => max( 1, min( 100, $per_page ) ),
				'status' => $status,
			)
		);

		$items = array();
		foreach ( $comments as $comment ) {
			$items[] = array(
				'id'        => (int) $comment->comment_ID,
				'post_id'   => (int) $comment->comment_post_ID,
				'author'    => (string) $comment->comment_author,
				'content'   => (string) $comment->comment_content,
				'approved'  => (string) $comment->comment_approved,
			);
		}

		return array(
			'items' => $items,
			'total' => count( $items ),
			'tool'  => 'webo/list-comments',
		);
	}

	/**
	 * List taxonomy terms.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function list_terms( array $arguments ) {
		$taxonomy = isset( $arguments['taxonomy'] ) ? sanitize_key( (string) $arguments['taxonomy'] ) : 'category';
		$per_page = isset( $arguments['per_page'] ) ? (int) $arguments['per_page'] : 50;

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'webo_mcp_taxonomy_not_found', 'Taxonomy not found' );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => max( 1, min( 100, $per_page ) ),
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$items = array();
		foreach ( $terms as $term ) {
			$items[] = array(
				'id'          => (int) $term->term_id,
				'name'        => (string) $term->name,
				'slug'        => (string) $term->slug,
				'taxonomy'    => (string) $term->taxonomy,
				'description' => (string) $term->description,
			);
		}

		return array(
			'items' => $items,
			'total' => count( $items ),
			'tool'  => 'webo/list-terms',
		);
	}

	/**
	 * List installed plugins and active state.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function list_active_plugins( array $arguments ) {
		$include_inactive = isset( $arguments['include_inactive'] ) ? (bool) $arguments['include_inactive'] : false;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$active_plugins    = get_option( 'active_plugins', array() );

		if ( ! is_array( $active_plugins ) ) {
			$active_plugins = array();
		}

		$network_active_plugins = array();
		if ( is_multisite() ) {
			$sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $sitewide_plugins ) ) {
				$network_active_plugins = array_keys( $sitewide_plugins );
			}
		}

		$active_lookup = array_fill_keys( array_merge( $active_plugins, $network_active_plugins ), true );
		$items         = array();

		foreach ( $installed_plugins as $plugin_file => $metadata ) {
			$is_active = isset( $active_lookup[ $plugin_file ] );
			if ( ! $include_inactive && ! $is_active ) {
				continue;
			}

			$items[] = array(
				'plugin_file'    => (string) $plugin_file,
				'name'           => isset( $metadata['Name'] ) ? (string) $metadata['Name'] : (string) $plugin_file,
				'version'        => isset( $metadata['Version'] ) ? (string) $metadata['Version'] : '',
				'author'         => isset( $metadata['Author'] ) ? wp_strip_all_tags( (string) $metadata['Author'] ) : '',
				'active'         => $is_active,
				'network_active' => in_array( $plugin_file, $network_active_plugins, true ),
			);
		}

		usort(
			$items,
			static function ( $left, $right ) {
				$left_active  = ! empty( $left['active'] ) ? 1 : 0;
				$right_active = ! empty( $right['active'] ) ? 1 : 0;

				if ( $left_active !== $right_active ) {
					return $right_active - $left_active;
				}

				return strcmp( (string) $left['name'], (string) $right['name'] );
			}
		);

		$active_total = 0;
		foreach ( $items as $item ) {
			if ( ! empty( $item['active'] ) ) {
				++$active_total;
			}
		}

		return array(
			'items'        => $items,
			'total'        => count( $items ),
			'active_total' => $active_total,
			'tool'         => 'webo/list-active-plugins',
		);
	}

	/**
	 * Read selected safe options.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_options( array $arguments ) {
		$names = isset( $arguments['names'] ) && is_array( $arguments['names'] ) ? $arguments['names'] : array();
		if ( empty( $names ) ) {
			return new \WP_Error( 'webo_mcp_option_names_required', 'Option names are required' );
		}

		$allowed_option_names = array(
			'blogname',
			'blogdescription',
			'siteurl',
			'home',
			'timezone_string',
			'date_format',
			'time_format',
			'start_of_week',
			'posts_per_page',
		);

		$values = array();
		foreach ( $names as $option_name ) {
			$option_name = sanitize_key( (string) $option_name );
			if ( '' === $option_name || ! in_array( $option_name, $allowed_option_names, true ) ) {
				continue;
			}

			$values[ $option_name ] = get_option( $option_name );
		}

		return array(
			'values' => $values,
			'tool'   => 'webo/get-options',
		);
	}

	/**
	 * Homepage / front page: Reading settings resolved to URLs and page objects.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_homepage_info( array $arguments ) {
		$include_excerpt  = ! empty( $arguments['include_excerpt'] );
		$include_content  = ! empty( $arguments['include_content'] );
		$requested_post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;

		$show_on_front   = (string) get_option( 'show_on_front', 'posts' );
		$page_on_front   = (int) get_option( 'page_on_front', 0 );
		$page_for_posts  = (int) get_option( 'page_for_posts', 0 );

		$out = array(
			'tool'               => 'webo/get-homepage-info',
			'home_url'           => home_url( '/' ),
			'show_on_front'      => $show_on_front,
			'posts_per_page'     => (int) get_option( 'posts_per_page', 10 ),
			'is_posts_front'     => ( 'posts' === $show_on_front ),
			'page_on_front_id'   => $page_on_front,
			'page_for_posts_id'  => $page_for_posts,
		);

		if ( 'page' === $show_on_front ) {
			if ( $page_on_front > 0 ) {
				$post = get_post( $page_on_front );
				if ( $post instanceof \WP_Post ) {
					$out['front_page'] = self::homepage_info_format_post(
						$post,
						$include_excerpt,
						$include_content,
						true
					);
				} else {
					$out['front_page']         = null;
					$out['front_page_missing'] = true;
				}
			} else {
				$out['front_page'] = null;
			}
		} else {
			$out['front_page'] = null;
		}

		if ( $page_for_posts > 0 ) {
			$posts_page = get_post( $page_for_posts );
			if ( $posts_page instanceof \WP_Post ) {
				$out['posts_page'] = self::homepage_info_format_post(
					$posts_page,
					$include_excerpt,
					$include_content,
					false
				);
			} else {
				$out['posts_page']          = null;
				$out['posts_page_missing'] = true;
			}
		} else {
			$out['posts_page'] = null;
		}

		if ( $requested_post_id > 0 ) {
			$resolved = get_post( $requested_post_id );
			if ( ! $resolved instanceof \WP_Post ) {
				return new \WP_Error(
					'webo_mcp_post_not_found',
					sprintf( 'Post not found for ID %d', $requested_post_id )
				);
			}
			if ( ! current_user_can( 'read_post', $requested_post_id ) ) {
				return new \WP_Error(
					'webo_mcp_cannot_read_post',
					'You do not have permission to read this post',
					array( 'status' => 403 )
				);
			}

			$by_id = self::homepage_info_format_post(
				$resolved,
				$include_excerpt,
				$include_content,
				true
			);
			$by_id['is_configured_front_page'] = ( 'page' === $show_on_front && $page_on_front === $requested_post_id );
			$by_id['is_configured_posts_page']  = ( $page_for_posts === $requested_post_id );
			$out['by_post_id']                 = $by_id;
		}

		return $out;
	}

	/**
	 * Format a post for homepage-info payloads.
	 *
	 * @param \WP_Post $post              Post object.
	 * @param bool     $include_excerpt   Include generated/plain excerpt.
	 * @param bool     $include_content   Include post_content (can be large).
	 * @param bool     $include_featured  Include featured image when present.
	 * @return array<string, mixed>
	 */
	private static function homepage_info_format_post( \WP_Post $post, $include_excerpt, $include_content, $include_featured ) {
		$row = array(
			'id'     => (int) $post->ID,
			'title'  => get_the_title( $post ),
			'slug'   => (string) $post->post_name,
			'type'   => (string) $post->post_type,
			'link'   => get_permalink( $post ),
			'status' => (string) $post->post_status,
		);
		if ( $include_excerpt ) {
			$row['excerpt'] = wp_strip_all_tags( (string) get_the_excerpt( $post ) );
		}
		if ( $include_content ) {
			$row['content'] = (string) $post->post_content;
		}
		if ( $include_featured ) {
			$thumb_id = (int) get_post_thumbnail_id( $post );
			if ( $thumb_id > 0 ) {
				$url = wp_get_attachment_image_url( $thumb_id, 'full' );
				$row['featured_image'] = array(
					'id'  => $thumb_id,
					'url' => $url ? (string) $url : '',
				);
			}
		}
		return $row;
	}

	/**
	 * Update selected safe options.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_options( array $arguments ) {
		$options = isset( $arguments['options'] ) && is_array( $arguments['options'] ) ? $arguments['options'] : array();
		if ( empty( $options ) ) {
			return new \WP_Error( 'webo_mcp_options_required', 'Options payload is required' );
		}

		$allowed_option_names = array(
			'blogname',
			'blogdescription',
			'timezone_string',
			'date_format',
			'time_format',
			'start_of_week',
			'posts_per_page',
		);

		$updated = array();
		$skipped = array();
		foreach ( $options as $option_name => $option_value ) {
			$option_name = sanitize_key( (string) $option_name );
			if ( '' === $option_name || ! in_array( $option_name, $allowed_option_names, true ) ) {
				continue;
			}

			$sanitized = self::sanitize_safe_option_value( $option_name, $option_value );
			if ( is_wp_error( $sanitized ) ) {
				$skipped[ $option_name ] = $sanitized->get_error_message();
				continue;
			}

			update_option( $option_name, $sanitized );
			$updated[] = $option_name;
		}

		return array(
			'updated' => $updated,
			'skipped' => $skipped,
			'tool'    => 'webo/update-options',
		);
	}

	/**
	 * Sanitizes values for the safe options allowlist used by webo/update-options.
	 *
	 * @param string               $option_name Option key.
	 * @param mixed                $value       Raw value.
	 * @return mixed|\WP_Error
	 */
	private static function sanitize_safe_option_value( string $option_name, $value ) {
		switch ( $option_name ) {
			case 'blogname':
			case 'blogdescription':
				if ( ! is_scalar( $value ) && null !== $value ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'Value must be scalar' );
				}
				return sanitize_text_field( (string) $value );

			case 'timezone_string':
				if ( ! is_scalar( $value ) && null !== $value ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'Value must be scalar' );
				}
				$s = sanitize_text_field( (string) $value );
				if ( '' === $s ) {
					return '';
				}
				$zones = timezone_identifiers_list();
				if ( in_array( $s, $zones, true ) ) {
					return $s;
				}
				return new \WP_Error( 'webo_mcp_invalid_timezone', 'Invalid timezone_string' );

			case 'date_format':
			case 'time_format':
				if ( ! is_scalar( $value ) && null !== $value ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'Value must be scalar' );
				}
				$s = (string) $value;
				if ( strlen( $s ) > 80 ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'Format string too long' );
				}
				return sanitize_text_field( $s );

			case 'start_of_week':
				$n = (int) $value;
				if ( $n < 0 || $n > 6 ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'start_of_week must be 0-6' );
				}
				return $n;

			case 'posts_per_page':
				$n = absint( $value );
				if ( $n < 1 ) {
					$n = 1;
				}
				if ( $n > 100 ) {
					$n = 100;
				}
				return $n;

			default:
				return new \WP_Error( 'webo_mcp_invalid_option', 'Unknown option' );
		}
	}

	/**
	 * Blocks obvious SSRF targets for server-side URL fetch (upload from URL).
	 *
	 * @param string $url Absolute URL.
	 * @return true|\WP_Error
	 */
	private static function validate_remote_media_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return new \WP_Error( 'webo_mcp_invalid_url', 'URL is empty' );
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return new \WP_Error( 'webo_mcp_invalid_url', 'URL must be absolute http(s)' );
		}

		$scheme = strtolower( (string) $parsed['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new \WP_Error( 'webo_mcp_invalid_url', 'Only http and https are allowed' );
		}

		$host = strtolower( (string) $parsed['host'] );
		$host_trim = trim( $host, '[]' );

		$blocked = array( 'localhost', '127.0.0.1', '0.0.0.0', '::1', '0000::1' );
		if ( in_array( $host_trim, $blocked, true ) || in_array( $host, $blocked, true ) ) {
			return new \WP_Error( 'webo_mcp_url_not_allowed', 'Loopback and local hostnames are blocked' );
		}

		if ( filter_var( $host_trim, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
			if ( ! filter_var( $host_trim, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return new \WP_Error( 'webo_mcp_url_not_allowed', 'Private or reserved IPs are blocked' );
			}
		}

		/**
		 * Final gate for media URL fetch (SSRF hardening).
		 *
		 * @param true|\WP_Error $ok   Return WP_Error to reject.
		 * @param string         $url  Request URL.
		 * @param array          $parsed wp_parse_url parts.
		 */
		$gate = apply_filters( 'webo_mcp_validate_media_fetch_url', true, $url, $parsed );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		return true;
	}

	// ----- Posts: bulk, revisions, search_replace -----

	/**
	 * Bulk update post status.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function bulk_update_post_status( array $arguments ) {
		$post_ids = isset( $arguments['post_ids'] ) && is_array( $arguments['post_ids'] ) ? $arguments['post_ids'] : array();
		$status   = isset( $arguments['status'] ) ? sanitize_key( (string) $arguments['status'] ) : 'draft';
		if ( empty( $post_ids ) ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'post_ids array is required' );
		}
		if ( ! in_array( $status, array( 'draft', 'publish', 'pending', 'private', 'trash' ), true ) ) {
			return new \WP_Error( 'webo_mcp_invalid_status', 'Invalid status' );
		}
		$updated = 0;
		foreach ( array_map( 'intval', $post_ids ) as $post_id ) {
			if ( $post_id <= 0 || ! get_post( $post_id ) ) {
				continue;
			}
			$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ), true );
			if ( ! is_wp_error( $result ) && $result ) {
				$updated++;
			}
		}
		return array( 'updated' => $updated, 'status' => $status, 'tool' => 'webo/bulk-update-post-status' );
	}

	/**
	 * List revisions for a post.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function list_revisions( array $arguments ) {
		$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}
		$revisions = wp_get_post_revisions( $post_id );
		$items     = array();
		foreach ( $revisions as $rev ) {
			$items[] = array(
				'id'         => (int) $rev->ID,
				'post_id'    => $post_id,
				'date'       => $rev->post_modified,
				'author_id'  => (int) $rev->post_author,
			);
		}
		return array( 'items' => $items, 'total' => count( $items ), 'tool' => 'webo/list-revisions' );
	}

	/**
	 * Restore a revision.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function restore_revision( array $arguments ) {
		$revision_id = isset( $arguments['revision_id'] ) ? (int) $arguments['revision_id'] : 0;
		$revision    = $revision_id > 0 ? get_post( $revision_id ) : null;
		if ( ! $revision || 'revision' !== $revision->post_type ) {
			return new \WP_Error( 'webo_mcp_revision_not_found', 'Revision not found' );
		}
		$post_id = (int) $revision->post_parent;
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Parent post not found' );
		}
		$restored = wp_restore_post_revision( $revision_id );
		if ( ! $restored ) {
			return new \WP_Error( 'webo_mcp_restore_failed', 'Failed to restore revision' );
		}
		return array( 'post_id' => $post_id, 'revision_id' => $revision_id, 'restored' => true, 'tool' => 'webo/restore-revision' );
	}

	/**
	 * Search and replace in post content (dry_run or execute).
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function search_replace_posts( array $arguments ) {
		$search  = isset( $arguments['search'] ) ? (string) $arguments['search'] : '';
		$replace = isset( $arguments['replace'] ) ? (string) $arguments['replace'] : '';
		$dry_run = isset( $arguments['dry_run'] ) ? (bool) $arguments['dry_run'] : true;
		$offset  = isset( $arguments['offset'] ) ? max( 0, (int) $arguments['offset'] ) : 0;
		$limit   = isset( $arguments['max_scan_posts'] ) ? (int) $arguments['max_scan_posts'] : 200;
		$limit   = max( 1, min( 500, $limit ) );

		if ( '' === $search ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'search is required' );
		}

		$count_query = new \WP_Query(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$total_posts = (int) $count_query->found_posts;

		$query = new \WP_Query(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => 'any',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$affected = array();
		foreach ( $query->posts as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post || strpos( $post->post_content, $search ) === false ) {
				continue;
			}
			$affected[] = array( 'post_id' => (int) $post_id, 'title' => get_the_title( $post_id ) );
			if ( ! $dry_run ) {
				$new_content = str_replace( $search, $replace, $post->post_content );
				wp_update_post(
					array(
						'ID'           => (int) $post_id,
						'post_content' => $new_content,
					),
					true
				);
			}
		}

		$next_offset = $offset + $limit;
		$has_more    = $next_offset < $total_posts;

		return array(
			'affected'      => $affected,
			'count'         => count( $affected ),
			'dry_run'       => $dry_run,
			'offset'        => $offset,
			'max_scan_posts'=> $limit,
			'total_posts'   => $total_posts,
			'has_more'      => $has_more,
			'next_offset'   => $has_more ? $next_offset : null,
			'tool'          => 'webo/search-replace-posts',
		);
	}

	// ----- Terms (category, tag) -----

	/**
	 * Create a term (category or tag).
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function create_term( array $arguments ) {
		$taxonomy = isset( $arguments['taxonomy'] ) ? sanitize_key( (string) $arguments['taxonomy'] ) : 'category';
		if ( ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
			return new \WP_Error( 'webo_mcp_invalid_taxonomy', 'taxonomy must be category or post_tag' );
		}
		$name = isset( $arguments['name'] ) ? sanitize_text_field( (string) $arguments['name'] ) : '';
		if ( '' === $name ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'name is required' );
		}
		$slug        = isset( $arguments['slug'] ) ? sanitize_title( (string) $arguments['slug'] ) : '';
		$description = isset( $arguments['description'] ) ? sanitize_textarea_field( (string) $arguments['description'] ) : '';
		$parent_id   = isset( $arguments['parent_id'] ) ? max( 0, (int) $arguments['parent_id'] ) : 0;
		$result      = wp_insert_term( $name, $taxonomy, array_filter( array(
			'slug'        => $slug ?: null,
			'description' => $description ?: '',
			'parent'      => $parent_id > 0 ? $parent_id : 0,
		) ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'term_id'   => (int) $result['term_id'],
			'taxonomy'  => $taxonomy,
			'term_taxonomy_id' => (int) $result['term_taxonomy_id'],
			'tool'      => 'webo/create-term',
		);
	}

	/**
	 * Update a term.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_term( array $arguments ) {
		$term_id  = isset( $arguments['term_id'] ) ? (int) $arguments['term_id'] : 0;
		$taxonomy = isset( $arguments['taxonomy'] ) ? sanitize_key( (string) $arguments['taxonomy'] ) : 'category';
		if ( $term_id <= 0 || ! term_exists( $term_id, $taxonomy ) ) {
			return new \WP_Error( 'webo_mcp_term_not_found', 'Term not found' );
		}
		$args = array();
		if ( isset( $arguments['name'] ) ) {
			$args['name'] = sanitize_text_field( (string) $arguments['name'] );
		}
		if ( isset( $arguments['slug'] ) ) {
			$args['slug'] = sanitize_title( (string) $arguments['slug'] );
		}
		if ( isset( $arguments['description'] ) ) {
			$args['description'] = sanitize_textarea_field( (string) $arguments['description'] );
		}
		if ( isset( $arguments['parent_id'] ) ) {
			$args['parent'] = max( 0, (int) $arguments['parent_id'] );
		}
		if ( empty( $args ) ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'At least one of name, slug, description, parent_id is required' );
		}
		$result = wp_update_term( $term_id, $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'term_id' => $term_id, 'taxonomy' => $taxonomy, 'updated' => true, 'tool' => 'webo/update-term' );
	}

	/**
	 * Delete a term.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function delete_term( array $arguments ) {
		$term_id  = isset( $arguments['term_id'] ) ? (int) $arguments['term_id'] : 0;
		$taxonomy = isset( $arguments['taxonomy'] ) ? sanitize_key( (string) $arguments['taxonomy'] ) : 'category';
		if ( $term_id <= 0 || ! term_exists( $term_id, $taxonomy ) ) {
			return new \WP_Error( 'webo_mcp_term_not_found', 'Term not found' );
		}
		$result = wp_delete_term( $term_id, $taxonomy );
		if ( is_wp_error( $result ) || ! $result ) {
			return new \WP_Error( 'webo_mcp_delete_failed', 'Failed to delete term' );
		}
		return array( 'term_id' => $term_id, 'taxonomy' => $taxonomy, 'deleted' => true, 'tool' => 'webo/delete-term' );
	}

	/**
	 * Discover public taxonomies on the site.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function discover_taxonomies( array $arguments ) {
		unset( $arguments );

		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$items      = array();

		foreach ( $taxonomies as $taxonomy ) {
			$items[] = array(
				'name'          => $taxonomy->name,
				'label'         => $taxonomy->label,
				'description'   => isset( $taxonomy->description ) ? (string) $taxonomy->description : '',
				'object_type'   => (array) $taxonomy->object_type,
				'hierarchical'  => (bool) $taxonomy->hierarchical,
			);
		}

		return array(
			'items' => $items,
			'total' => count( $items ),
			'tool'  => 'webo/discover-taxonomies',
		);
	}

	/**
	 * Get one term by ID and taxonomy.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_term( array $arguments ) {
		$term_id  = isset( $arguments['term_id'] ) ? (int) $arguments['term_id'] : 0;
		$taxonomy = isset( $arguments['taxonomy'] ) ? sanitize_key( (string) $arguments['taxonomy'] ) : 'category';

		if ( $term_id <= 0 ) {
			return new \WP_Error( 'webo_mcp_term_id_required', 'term_id is required' );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'webo_mcp_taxonomy_not_found', 'Taxonomy not found' );
		}

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error( 'webo_mcp_term_not_found', 'Term not found' );
		}

		return array(
			'id'          => (int) $term->term_id,
			'name'        => (string) $term->name,
			'slug'        => (string) $term->slug,
			'taxonomy'    => (string) $term->taxonomy,
			'description' => (string) $term->description,
			'parent_id'   => (int) $term->parent,
			'count'       => (int) $term->count,
			'tool'        => 'webo/get-term',
		);
	}

	/**
	 * Assign terms to a post (replace existing terms for the taxonomy).
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function assign_terms_to_content( array $arguments ) {
		$post_id   = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
		$taxonomy  = isset( $arguments['taxonomy'] ) ? sanitize_key( (string) $arguments['taxonomy'] ) : '';
		$term_ids  = isset( $arguments['term_ids'] ) && is_array( $arguments['term_ids'] ) ? $arguments['term_ids'] : array();

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'webo_mcp_taxonomy_not_found', 'Taxonomy not found' );
		}

		$term_ids = array_map( 'intval', $term_ids );
		$term_ids = array_filter( $term_ids );

		$result = wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'post_id'   => $post_id,
			'taxonomy'  => $taxonomy,
			'term_ids'  => $term_ids,
			'assigned'  => true,
			'tool'      => 'webo/assign-terms-to-content',
		);
	}

	/**
	 * Get all terms assigned to a post for one or all taxonomies.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_content_terms( array $arguments ) {
		$post_id  = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
		$taxonomy = isset( $arguments['taxonomy'] ) ? sanitize_key( (string) $arguments['taxonomy'] ) : '';

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		if ( '' !== $taxonomy && ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'webo_mcp_taxonomy_not_found', 'Taxonomy not found' );
		}

		$taxonomies = '' !== $taxonomy ? array( $taxonomy ) : get_object_taxonomies( get_post_type( $post_id ), 'names' );
		$all_terms  = wp_get_object_terms( $post_id, $taxonomies );

		if ( is_wp_error( $all_terms ) ) {
			return $all_terms;
		}

		$items = array();
		foreach ( $all_terms as $term ) {
			$items[] = array(
				'id'          => (int) $term->term_id,
				'name'        => (string) $term->name,
				'slug'        => (string) $term->slug,
				'taxonomy'    => (string) $term->taxonomy,
			);
		}

		return array(
			'post_id' => $post_id,
			'items'   => $items,
			'total'   => count( $items ),
			'tool'    => 'webo/get-content-terms',
		);
	}

	// ----- Media -----

	/**
	 * Upload media from URL.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function upload_media_from_url( array $arguments ) {
		$image_url = isset( $arguments['image_url'] ) ? esc_url_raw( (string) $arguments['image_url'] ) : '';
		if ( '' === $image_url ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'image_url is required' );
		}

		$url_check = self::validate_remote_media_url( $image_url );
		if ( is_wp_error( $url_check ) ) {
			return $url_check;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = download_url( $image_url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$file_array = array(
			'name'     => isset( $arguments['filename'] ) ? sanitize_file_name( (string) $arguments['filename'] ) : basename( wp_parse_url( $image_url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);
		$post_data = array();
		if ( isset( $arguments['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( (string) $arguments['title'] );
		}
		if ( isset( $arguments['alt_text'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( (string) $arguments['alt_text'] );
		}
		$id = media_handle_sideload( $file_array, 0, null, $post_data );
		if ( is_wp_error( $id ) ) {
			if ( is_string( $tmp ) && $tmp !== '' && file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return $id;
		}
		if ( ! empty( $post_data['post_excerpt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $post_data['post_excerpt'] );
		}
		return array(
			'attachment_id' => (int) $id,
			'url'           => wp_get_attachment_url( $id ),
			'tool'          => 'webo/upload-media-from-url',
		);
	}

	/**
	 * Get one media item metadata.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_media( array $arguments ) {
		$attachment_id = isset( $arguments['attachment_id'] ) ? (int) $arguments['attachment_id'] : 0;
		$post          = $attachment_id > 0 ? get_post( $attachment_id ) : null;
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new \WP_Error( 'webo_mcp_attachment_not_found', 'Attachment not found' );
		}
		return array(
			'attachment_id' => (int) $post->ID,
			'title'        => $post->post_title,
			'alt_text'     => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'caption'      => (string) $post->post_excerpt,
			'url'          => wp_get_attachment_url( $post->ID ),
			'mime_type'    => $post->post_mime_type,
			'tool'         => 'webo/get-media',
		);
	}

	/**
	 * Update media metadata.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_media( array $arguments ) {
		$attachment_id = isset( $arguments['attachment_id'] ) ? (int) $arguments['attachment_id'] : 0;
		if ( $attachment_id <= 0 || ! get_post( $attachment_id ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_Error( 'webo_mcp_attachment_not_found', 'Attachment not found' );
		}
		$updated = array();
		if ( array_key_exists( 'title', $arguments ) ) {
			wp_update_post( array( 'ID' => $attachment_id, 'post_title' => sanitize_text_field( (string) $arguments['title'] ) ) );
			$updated[] = 'title';
		}
		if ( array_key_exists( 'alt_text', $arguments ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $arguments['alt_text'] ) );
			$updated[] = 'alt_text';
		}
		if ( array_key_exists( 'caption', $arguments ) ) {
			wp_update_post( array( 'ID' => $attachment_id, 'post_excerpt' => sanitize_textarea_field( (string) $arguments['caption'] ) ) );
			$updated[] = 'caption';
		}
		return array( 'attachment_id' => $attachment_id, 'updated' => $updated, 'tool' => 'webo/update-media' );
	}

	/**
	 * Delete a media item.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function delete_media( array $arguments ) {
		$attachment_id = isset( $arguments['attachment_id'] ) ? (int) $arguments['attachment_id'] : 0;
		if ( $attachment_id <= 0 || ! get_post( $attachment_id ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_Error( 'webo_mcp_attachment_not_found', 'Attachment not found' );
		}
		$result = wp_delete_attachment( $attachment_id, true );
		if ( ! $result ) {
			return new \WP_Error( 'webo_mcp_delete_failed', 'Failed to delete attachment' );
		}
		return array( 'attachment_id' => $attachment_id, 'deleted' => true, 'tool' => 'webo/delete-media' );
	}

	// ----- Comments -----

	/**
	 * Get one comment.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_comment( array $arguments ) {
		$comment_id = isset( $arguments['comment_id'] ) ? (int) $arguments['comment_id'] : 0;
		$comment    = $comment_id > 0 ? get_comment( $comment_id ) : null;
		if ( ! $comment ) {
			return new \WP_Error( 'webo_mcp_comment_not_found', 'Comment not found' );
		}
		return array(
			'comment_id'   => (int) $comment->comment_ID,
			'post_id'      => (int) $comment->comment_post_ID,
			'author'       => (string) $comment->comment_author,
			'author_email' => (string) $comment->comment_author_email,
			'content'      => (string) $comment->comment_content,
			'status'       => (string) $comment->comment_approved,
			'date'         => (string) $comment->comment_date,
			'tool'         => 'webo/get-comment',
		);
	}

	/**
	 * Update comment (status: approve, hold, spam, trash; optional reply).
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_comment( array $arguments ) {
		$comment_id = isset( $arguments['comment_id'] ) ? (int) $arguments['comment_id'] : 0;
		if ( $comment_id <= 0 || ! get_comment( $comment_id ) ) {
			return new \WP_Error( 'webo_mcp_comment_not_found', 'Comment not found' );
		}
		if ( array_key_exists( 'status', $arguments ) ) {
			$status = sanitize_key( (string) $arguments['status'] );
			if ( in_array( $status, array( '1', '0', 'spam', 'trash', 'approve', 'hold' ), true ) ) {
				$new_status = ( 'approve' === $status || '1' === $status ) ? 'approve' : ( ( 'hold' === $status || '0' === $status ) ? 'hold' : $status );
				wp_set_comment_status( $comment_id, $new_status );
			}
		}
		if ( ! empty( $arguments['reply'] ) ) {
			$parent = get_comment( $comment_id );
			wp_insert_comment( array(
				'comment_post_ID'  => (int) $parent->comment_post_ID,
				'comment_parent'   => $comment_id,
				'comment_content'  => sanitize_textarea_field( (string) $arguments['reply'] ),
				'user_id'          => get_current_user_id(),
				'comment_author'   => wp_get_current_user()->display_name,
				'comment_approved' => '1',
			) );
		}
		return array( 'comment_id' => $comment_id, 'updated' => true, 'tool' => 'webo/update-comment' );
	}

	/**
	 * Delete a comment.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function delete_comment( array $arguments ) {
		$comment_id = isset( $arguments['comment_id'] ) ? (int) $arguments['comment_id'] : 0;
		if ( $comment_id <= 0 || ! get_comment( $comment_id ) ) {
			return new \WP_Error( 'webo_mcp_comment_not_found', 'Comment not found' );
		}
		$result = wp_delete_comment( $comment_id, true );
		if ( ! $result ) {
			return new \WP_Error( 'webo_mcp_delete_failed', 'Failed to delete comment' );
		}
		return array( 'comment_id' => $comment_id, 'deleted' => true, 'tool' => 'webo/delete-comment' );
	}

	/**
	 * Activate or deactivate a plugin.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function toggle_plugin( array $arguments ) {
		$plugin = isset( $arguments['plugin'] ) ? sanitize_text_field( (string) $arguments['plugin'] ) : '';
		$action = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : 'activate';
		if ( '' === $plugin ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'plugin (plugin file path) is required' );
		}
		if ( ! in_array( $action, array( 'activate', 'deactivate' ), true ) ) {
			return new \WP_Error( 'webo_mcp_invalid_action', 'action must be activate or deactivate' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( 'activate' === $action ) {
			$result = activate_plugin( $plugin, '', false, false );
		} else {
			deactivate_plugins( $plugin, false, false );
			$result = null;
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'plugin' => $plugin, 'action' => $action, 'success' => true, 'tool' => 'webo/toggle-plugin' );
	}

	/**
	 * List navigation menus (nav_menu terms).
	 *
	 * @param array<string, mixed> $arguments Ignored.
	 * @return array<string, mixed>
	 */
	public static function list_nav_menus( array $arguments ) {
		unset( $arguments );

		$menus = wp_get_nav_menus();
		if ( ! is_array( $menus ) ) {
			$menus = array();
		}

		$items = array();
		foreach ( $menus as $menu ) {
			if ( ! $menu instanceof \WP_Term ) {
				continue;
			}
			$tid     = (int) $menu->term_id;
			$items[] = array(
				'term_id' => $tid,
				'menu_id' => $tid,
				'name'    => (string) $menu->name,
				'slug'    => (string) $menu->slug,
				'count'   => (int) $menu->count,
			);
		}

		return array(
			'menus' => $items,
			'tool'  => 'webo/list-nav-menus',
			'note'  => 'No arguments and no user input needed. Each row: menu_id equals term_id (nav_menu taxonomy ID). Use that menu_id only for webo/list-nav-menu-items (links inside one menu). To list menus themselves, this response is enough — do not ask the user for menu_id first. Theme slots: webo/list-nav-menu-locations. Writes need edit_theme_options.',
		);
	}

	/**
	 * List registered theme menu locations and current nav_menu_locations assignments.
	 *
	 * @param array<string, mixed> $arguments Ignored.
	 * @return array<string, mixed>
	 */
	public static function list_nav_menu_locations( array $arguments ) {
		unset( $arguments );

		$registered = get_registered_nav_menus();
		if ( ! is_array( $registered ) ) {
			$registered = array();
		}

		$raw = get_nav_menu_locations();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$assigned = array();
		foreach ( $raw as $slug => $menu_id ) {
			$slug    = sanitize_key( (string) $slug );
			$menu_id = (int) $menu_id;
			if ( '' === $slug || $menu_id <= 0 ) {
				continue;
			}
			$menu    = wp_get_nav_menu_object( $menu_id );
			$label   = isset( $registered[ $slug ] ) ? (string) $registered[ $slug ] : $slug;
			$assigned[ $slug ] = array(
				'location_label' => $label,
				'menu_id'        => $menu_id,
				'menu_name'      => $menu instanceof \WP_Term ? (string) $menu->name : '',
				'menu_slug'      => $menu instanceof \WP_Term ? (string) $menu->slug : '',
			);
		}

		// Include registered slots with no menu assigned.
		$registered_out = array();
		foreach ( $registered as $slug => $label ) {
			$slug = sanitize_key( (string) $slug );
			$registered_out[ $slug ] = (string) $label;
		}

		return array(
			'registered_locations' => $registered_out,
			'assigned'             => $assigned,
			'tool'                 => 'webo/list-nav-menu-locations',
			'note'                 => 'Use each KEY of registered_locations as theme_location in create-nav-menu-for-location and assign-nav-menu-to-location (keys are slugs; values are admin labels). assigned lists slots that already have a menu.',
		);
	}

	/**
	 * Load core nav menu API when running outside wp-admin (e.g. REST MCP call).
	 *
	 * @return bool True when wp_create_nav_menu is available.
	 */
	private static function ensure_nav_menu_api_loaded() {
		if ( function_exists( 'wp_create_nav_menu' ) ) {
			return true;
		}
		$file = ABSPATH . WPINC . '/nav-menu.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}

		return function_exists( 'wp_create_nav_menu' );
	}

	/**
	 * Create a nav_menu term or reuse the existing one when the name already exists.
	 *
	 * @param string $menu_name Menu name (already sanitized for storage).
	 * @return array{0: int, 1: bool}|\WP_Error First element is term ID; second is true when an existing menu was reused.
	 */
	private static function create_nav_menu_term_or_reuse( string $menu_name ) {
		if ( ! self::ensure_nav_menu_api_loaded() ) {
			return new \WP_Error(
				'webo_mcp_nav_menu_api_unavailable',
				__( 'The navigation menu API could not be loaded. Check that WordPress core files are intact.', 'webo-mcp' )
			);
		}

		$created = wp_create_nav_menu( $menu_name );
		if ( ! is_wp_error( $created ) ) {
			return array( (int) $created, false );
		}

		if ( 'menu_exists' !== $created->get_error_code() ) {
			return $created;
		}

		$existing = wp_get_nav_menu_object( $menu_name );
		if ( ! $existing instanceof \WP_Term ) {
			return $created;
		}

		return array( (int) $existing->term_id, true );
	}

	/**
	 * Resolve a theme menu location slug against register_nav_menus(), with fallbacks when "primary" does not exist.
	 *
	 * @param string               $requested  Requested slug (will be sanitize_key internally).
	 * @param array<string, string> $registered Slug => label from get_registered_nav_menus().
	 * @return array{slug: string, resolution: string}|\WP_Error
	 */
	private static function resolve_registered_nav_menu_location( string $requested, array $registered ) {
		if ( ! is_array( $registered ) || array() === $registered ) {
			return new \WP_Error(
				'webo_mcp_no_menu_locations',
				__( 'The active theme did not register any classic menu locations. Use a block theme Site Editor or a theme that registers navigation menus.', 'webo-mcp' ),
				array( 'registered_locations' => array() )
			);
		}

		$requested = sanitize_key( $requested );
		if ( '' === $requested ) {
			$requested = 'primary';
		}

		if ( isset( $registered[ $requested ] ) ) {
			return array(
				'slug'       => $requested,
				'resolution' => 'exact',
			);
		}

		$keys = array_keys( $registered );
		if ( 1 === count( $keys ) ) {
			return array(
				'slug'       => (string) $keys[0],
				'resolution' => 'single_registered_location',
			);
		}

		if ( 'primary' === $requested ) {
			$common = array(
				'primary',
				'main',
				'header',
				'primary-menu',
				'header-menu',
				'menu-1',
				'navigation',
				'mobile',
				'footer',
				'top',
				'social',
			);
			foreach ( $common as $slug ) {
				if ( isset( $registered[ $slug ] ) ) {
					return array(
						'slug'       => $slug,
						'resolution' => 'common_slug_fallback',
					);
				}
			}
		}

		return new \WP_Error(
			'webo_mcp_invalid_menu_location',
			sprintf(
				/* translators: %1$s: requested slug, %2$s: comma-separated registered slugs */
				__( 'Theme location "%1$s" is not registered. Registered slugs: %2$s', 'webo-mcp' ),
				$requested,
				implode( ', ', $keys )
			),
			array(
				'theme_location'       => $requested,
				'registered_locations' => $keys,
			)
		);
	}

	/**
	 * Create an empty navigation menu (no theme location assignment).
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function create_nav_menu( array $arguments ) {
		$menu_name_raw = isset( $arguments['menu_name'] ) ? (string) $arguments['menu_name'] : '';
		$menu_name     = sanitize_text_field( trim( $menu_name_raw ) );
		if ( '' === $menu_name ) {
			$menu_name = __( 'New Menu', 'webo-mcp' );
		}

		$result = self::create_nav_menu_term_or_reuse( $menu_name );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$menu_id = (int) $result[0];
		$reused  = (bool) $result[1];
		if ( $menu_id <= 0 ) {
			return new \WP_Error( 'webo_mcp_menu_create_failed', __( 'Failed to create navigation menu', 'webo-mcp' ) );
		}

		$menu = wp_get_nav_menu_object( $menu_id );

		$out = array(
			'menu_id'   => $menu_id,
			'menu_name' => $menu_name,
			'slug'      => $menu instanceof \WP_Term ? (string) $menu->slug : '',
			'tool'      => 'webo/create-nav-menu',
		);
		if ( $reused ) {
			$out['reused_existing_menu'] = true;
		}

		return $out;
	}

	/**
	 * Assign an existing nav menu to a theme location.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function assign_nav_menu_to_location( array $arguments ) {
		$menu_id = isset( $arguments['menu_id'] ) ? (int) $arguments['menu_id'] : 0;
		$by_name = isset( $arguments['menu_name'] ) ? sanitize_text_field( trim( (string) $arguments['menu_name'] ) ) : '';
		$assigned_via_menu_name = false;

		if ( $menu_id <= 0 ) {
			if ( '' === $by_name ) {
				return new \WP_Error(
					'webo_mcp_missing_argument',
					__( 'Provide menu_id (nav menu term ID) or menu_name (exact name shown in Appearance > Menus).', 'webo-mcp' )
				);
			}
			if ( ! self::ensure_nav_menu_api_loaded() ) {
				return new \WP_Error(
					'webo_mcp_nav_menu_api_unavailable',
					__( 'The navigation menu API could not be loaded. Check that WordPress core files are intact.', 'webo-mcp' )
				);
			}
			$resolved_menu = wp_get_nav_menu_object( $by_name );
			if ( ! $resolved_menu instanceof \WP_Term ) {
				return new \WP_Error( 'webo_mcp_menu_not_found', __( 'Navigation menu not found for the given menu_name', 'webo-mcp' ) );
			}
			$menu_id                  = (int) $resolved_menu->term_id;
			$assigned_via_menu_name   = true;
		} elseif ( ! wp_get_nav_menu_object( $menu_id ) ) {
			return new \WP_Error( 'webo_mcp_menu_not_found', __( 'Navigation menu not found', 'webo-mcp' ) );
		}

		$theme_location = isset( $arguments['theme_location'] ) ? sanitize_key( (string) $arguments['theme_location'] ) : 'primary';
		if ( '' === $theme_location ) {
			$theme_location = 'primary';
		}

		$replace = array_key_exists( 'replace', $arguments ) ? (bool) $arguments['replace'] : true;

		$registered = get_registered_nav_menus();
		if ( ! is_array( $registered ) ) {
			$registered = array();
		}

		$resolved = self::resolve_registered_nav_menu_location( $theme_location, $registered );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$theme_location = $resolved['slug'];
		$resolution     = $resolved['resolution'];

		$locations = get_nav_menu_locations();
		if ( ! is_array( $locations ) ) {
			$locations = array();
		}

		$previous_menu_id = isset( $locations[ $theme_location ] ) ? (int) $locations[ $theme_location ] : 0;

		if ( ! $replace && $previous_menu_id > 0 ) {
			return new \WP_Error(
				'webo_mcp_menu_location_occupied',
				sprintf(
					/* translators: %1$s: theme location slug, %2$d: existing menu term ID */
					__( 'Theme location "%1$s" is already assigned to menu_id %2$d. Pass replace: true to assign this menu to that location anyway.', 'webo-mcp' ),
					$theme_location,
					$previous_menu_id
				),
				array(
					'existing_menu_id' => $previous_menu_id,
					'theme_location'   => $theme_location,
				)
			);
		}

		$locations[ $theme_location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );

		$location_label = isset( $registered[ $theme_location ] ) ? (string) $registered[ $theme_location ] : $theme_location;

		$out = array(
			'menu_id'                   => $menu_id,
			'theme_location'            => $theme_location,
			'theme_location_label'      => $location_label,
			'theme_location_resolution' => $resolution,
			'replaced_previous_menu_id' => $previous_menu_id > 0 ? $previous_menu_id : null,
			'tool'                      => 'webo/assign-nav-menu-to-location',
		);
		if ( $assigned_via_menu_name ) {
			$out['assigned_via_menu_name'] = true;
		}

		return $out;
	}

	/**
	 * Create a new nav menu and assign it to a registered theme location (e.g. primary).
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function create_nav_menu_for_location( array $arguments ) {
		$menu_name_raw = isset( $arguments['menu_name'] ) ? (string) $arguments['menu_name'] : '';
		$menu_name     = sanitize_text_field( trim( $menu_name_raw ) );
		if ( '' === $menu_name ) {
			$menu_name = __( 'Primary Menu', 'webo-mcp' );
		}

		$theme_location = isset( $arguments['theme_location'] ) ? sanitize_key( (string) $arguments['theme_location'] ) : 'primary';
		if ( '' === $theme_location ) {
			$theme_location = 'primary';
		}

		$replace = array_key_exists( 'replace', $arguments ) ? (bool) $arguments['replace'] : true;

		$registered = get_registered_nav_menus();
		if ( ! is_array( $registered ) ) {
			$registered = array();
		}

		$resolved = self::resolve_registered_nav_menu_location( $theme_location, $registered );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$theme_location = $resolved['slug'];
		$resolution     = $resolved['resolution'];

		$locations = get_nav_menu_locations();
		if ( ! is_array( $locations ) ) {
			$locations = array();
		}

		$previous_menu_id = isset( $locations[ $theme_location ] ) ? (int) $locations[ $theme_location ] : 0;

		if ( ! $replace && $previous_menu_id > 0 ) {
			return new \WP_Error(
				'webo_mcp_menu_location_occupied',
				sprintf(
					/* translators: %1$s: theme location slug, %2$d: existing menu term ID */
					__( 'Theme location "%1$s" is already assigned to menu_id %2$d. Pass replace: true to create a new menu and replace that assignment.', 'webo-mcp' ),
					$theme_location,
					$previous_menu_id
				),
				array(
					'existing_menu_id' => $previous_menu_id,
					'theme_location'   => $theme_location,
				)
			);
		}

		$result = self::create_nav_menu_term_or_reuse( $menu_name );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$menu_id = (int) $result[0];
		$reused  = (bool) $result[1];
		if ( $menu_id <= 0 ) {
			return new \WP_Error( 'webo_mcp_menu_create_failed', __( 'Failed to create navigation menu', 'webo-mcp' ) );
		}

		$locations[ $theme_location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );

		$location_label = isset( $registered[ $theme_location ] ) ? (string) $registered[ $theme_location ] : $theme_location;

		$out = array(
			'menu_id'                   => $menu_id,
			'menu_name'                 => $menu_name,
			'theme_location'            => $theme_location,
			'theme_location_label'      => $location_label,
			'theme_location_resolution' => $resolution,
			'replaced_previous_menu_id' => $previous_menu_id > 0 ? $previous_menu_id : null,
			'tool'                      => 'webo/create-nav-menu-for-location',
		);
		if ( $reused ) {
			$out['reused_existing_menu'] = true;
		}

		return $out;
	}

	/**
	 * List items in one menu (db_id, menu_order, object_id, parent, etc.).
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function list_nav_menu_items( array $arguments ) {
		$menu_id = isset( $arguments['menu_id'] ) ? (int) $arguments['menu_id'] : 0;
		if ( $menu_id <= 0 ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'menu_id (nav menu term ID) is required' );
		}

		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu instanceof \WP_Term ) {
			return new \WP_Error( 'webo_mcp_menu_not_found', 'Navigation menu not found' );
		}

		$raw = wp_get_nav_menu_items( $menu_id );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$items = array();
		foreach ( $raw as $row ) {
			if ( ! $row instanceof \WP_Post ) {
				continue;
			}
			$db_id = (int) $row->ID;
			$items[] = array(
				'db_id'         => $db_id,
				'title'         => isset( $row->title ) && is_string( $row->title ) ? $row->title : (string) $row->post_title,
				'menu_order'    => (int) $row->menu_order,
				'parent_db_id'  => (int) get_post_meta( $db_id, '_menu_item_menu_item_parent', true ),
				'object_id'     => (int) get_post_meta( $db_id, '_menu_item_object_id', true ),
				'object'        => (string) get_post_meta( $db_id, '_menu_item_object', true ),
				'type'          => (string) get_post_meta( $db_id, '_menu_item_type', true ),
				'url'           => isset( $row->url ) && is_string( $row->url ) ? $row->url : '',
			);
		}

		return array(
			'menu_id'   => (int) $menu->term_id,
			'menu_name' => (string) $menu->name,
			'items'     => $items,
			'tool'      => 'webo/list-nav-menu-items',
			'note'      => 'Dev must assign menu_order (position) when adding items; use menu_order values here as reference. post_id for new links = object_id of the page/post to link, from list-posts/get-post/get-content-by-slug.',
		);
	}

	/**
	 * Add a post type object to a nav menu. post_id and menu_order are required — no auto numbering.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function add_nav_menu_item_from_post( array $arguments ) {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$menu_id     = isset( $arguments['menu_id'] ) ? (int) $arguments['menu_id'] : 0;
		$post_id     = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
		$menu_order  = isset( $arguments['menu_order'] ) ? (int) $arguments['menu_order'] : 0;
		$post_type   = isset( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : '';
		$parent_id   = isset( $arguments['parent_db_id'] ) ? (int) $arguments['parent_db_id'] : 0;
		$title       = isset( $arguments['menu_item_title'] ) ? sanitize_text_field( (string) $arguments['menu_item_title'] ) : '';

		if ( $menu_id <= 0 ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'menu_id (nav menu term ID from list-nav-menus) is required' );
		}
		if ( ! wp_get_nav_menu_object( $menu_id ) ) {
			return new \WP_Error( 'webo_mcp_menu_not_found', 'Navigation menu not found' );
		}
		if ( $post_id <= 0 ) {
			return new \WP_Error(
				'webo_mcp_missing_argument',
				'post_id is required: object ID of the page/post/CPT to link (from list-posts, get-post, etc.); MCP cannot infer this.'
			);
		}
		if ( '' === $post_type ) {
			return new \WP_Error(
				'webo_mcp_missing_argument',
				'post_type is required (e.g. page, post): must match the post being linked.'
			);
		}
		if ( $menu_order < 1 ) {
			return new \WP_Error(
				'webo_mcp_missing_argument',
				'menu_order is required (integer >= 1): position among siblings; WordPress cannot choose this for you — inspect list-nav-menu-items and assign explicitly.'
			);
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found for post_id' );
		}
		if ( (string) $post->post_type !== $post_type ) {
			return new \WP_Error(
				'webo_mcp_post_type_mismatch',
				sprintf( 'post_id %d is type "%s", not "%s"', $post_id, $post->post_type, $post_type )
			);
		}

		$existing = wp_get_nav_menu_items( $menu_id );
		$valid_db = array();
		if ( is_array( $existing ) ) {
			foreach ( $existing as $row ) {
				if ( $row instanceof \WP_Post ) {
					$valid_db[ (int) $row->ID ] = true;
				}
			}
		}
		if ( $parent_id > 0 && ! isset( $valid_db[ $parent_id ] ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_menu_parent',
				'parent_db_id must be a menu item already in this menu (see list-nav-menu-items db_id).'
			);
		}

		$args = array(
			'menu-item-object-id'  => $post_id,
			'menu-item-object'     => $post_type,
			'menu-item-type'       => 'post_type',
			'menu-item-status'     => 'publish',
			'menu-item-position'   => $menu_order,
			'menu-item-parent-id'  => max( 0, $parent_id ),
		);
		if ( '' !== $title ) {
			$args['menu-item-title'] = $title;
		}

		$item_id = wp_update_nav_menu_item( $menu_id, 0, $args );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}
		if ( ! is_numeric( $item_id ) || (int) $item_id <= 0 ) {
			return new \WP_Error( 'webo_mcp_menu_item_failed', 'Failed to create menu item' );
		}

		return array(
			'menu_item_db_id' => (int) $item_id,
			'menu_id'         => $menu_id,
			'post_id'         => $post_id,
			'menu_order'      => $menu_order,
			'tool'            => 'webo/add-nav-menu-item-from-post',
		);
	}

	/**
	 * Add a custom URL (Custom link) to a nav menu.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function add_nav_menu_item_custom( array $arguments ) {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$menu_id    = isset( $arguments['menu_id'] ) ? (int) $arguments['menu_id'] : 0;
		$url_raw    = isset( $arguments['url'] ) ? trim( (string) $arguments['url'] ) : '';
		$title      = isset( $arguments['title'] ) ? sanitize_text_field( (string) $arguments['title'] ) : '';
		$menu_order = isset( $arguments['menu_order'] ) ? (int) $arguments['menu_order'] : 0;
		$parent_id  = isset( $arguments['parent_db_id'] ) ? (int) $arguments['parent_db_id'] : 0;

		if ( $menu_id <= 0 || ! wp_get_nav_menu_object( $menu_id ) ) {
			return new \WP_Error( 'webo_mcp_menu_not_found', 'Navigation menu not found' );
		}
		if ( '' === $url_raw ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'url is required' );
		}
		if ( '' === $title ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'title (link text) is required' );
		}
		if ( $menu_order < 1 ) {
			return new \WP_Error(
				'webo_mcp_missing_argument',
				'menu_order is required (integer >= 1): inspect list-nav-menu-items and assign explicitly.'
			);
		}

		$url = esc_url_raw( $url_raw );
		if ( '' === $url ) {
			return new \WP_Error( 'webo_mcp_invalid_url', 'url is not a valid URL' );
		}
		$parsed = wp_parse_url( $url );
		$scheme = isset( $parsed['scheme'] ) ? strtolower( (string) $parsed['scheme'] ) : '';
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new \WP_Error( 'webo_mcp_invalid_url', 'url must use http or https' );
		}

		$existing = wp_get_nav_menu_items( $menu_id );
		$valid_db = array();
		if ( is_array( $existing ) ) {
			foreach ( $existing as $row ) {
				if ( $row instanceof \WP_Post ) {
					$valid_db[ (int) $row->ID ] = true;
				}
			}
		}
		if ( $parent_id > 0 && ! isset( $valid_db[ $parent_id ] ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_menu_parent',
				'parent_db_id must be a menu item already in this menu (see list-nav-menu-items db_id).'
			);
		}

		$args = array(
			'menu-item-type'      => 'custom',
			'menu-item-url'       => $url,
			'menu-item-title'     => $title,
			'menu-item-status'    => 'publish',
			'menu-item-position'  => $menu_order,
			'menu-item-parent-id' => max( 0, $parent_id ),
		);

		$item_id = wp_update_nav_menu_item( $menu_id, 0, $args );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}
		if ( ! is_numeric( $item_id ) || (int) $item_id <= 0 ) {
			return new \WP_Error( 'webo_mcp_menu_item_failed', 'Failed to create menu item' );
		}

		return array(
			'menu_item_db_id' => (int) $item_id,
			'menu_id'         => $menu_id,
			'url'             => $url,
			'menu_order'      => $menu_order,
			'tool'            => 'webo/add-nav-menu-item-custom',
		);
	}

	/**
	 * Set or remove the featured image for a post, page, or CPT.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function set_post_featured_image( array $arguments ) {
		$post_id       = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
		$remove        = ! empty( $arguments['remove'] );
		$attachment_id = isset( $arguments['attachment_id'] ) ? (int) $arguments['attachment_id'] : 0;

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}
		if ( 'attachment' === get_post_type( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_invalid_post', 'Cannot set featured image on an attachment' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'webo_mcp_permission_denied',
				'Cannot edit this post',
				array( 'status' => 403 )
			);
		}

		if ( $remove ) {
			delete_post_thumbnail( $post_id );
			return array(
				'post_id'          => $post_id,
				'featured_removed' => true,
				'tool'             => 'webo/set-post-featured-image',
			);
		}

		if ( $attachment_id <= 0 ) {
			return new \WP_Error(
				'webo_mcp_missing_argument',
				'attachment_id is required (or set remove: true to delete featured image)'
			);
		}

		$att = get_post( $attachment_id );
		if ( ! $att || 'attachment' !== $att->post_type ) {
			return new \WP_Error( 'webo_mcp_attachment_not_found', 'Attachment not found' );
		}

		if ( ! set_post_thumbnail( $post_id, $attachment_id ) ) {
			return new \WP_Error( 'webo_mcp_featured_image_failed', 'Failed to set featured image' );
		}

		return array(
			'post_id'       => $post_id,
			'attachment_id' => $attachment_id,
			'tool'          => 'webo/set-post-featured-image',
		);
	}
}
