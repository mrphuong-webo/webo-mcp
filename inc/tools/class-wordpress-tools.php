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
		$per_page = isset( $arguments['per_page'] ) ? (int) $arguments['per_page'] : 10;
		$per_page = max( 1, min( 100, $per_page ) );
		$page     = isset( $arguments['page'] ) ? max( 1, (int) $arguments['page'] ) : 1;
		$offset   = isset( $arguments['offset'] ) ? max( 0, (int) $arguments['offset'] ) : 0;

		$post_type = isset( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : 'post';
		$search    = isset( $arguments['search'] ) ? sanitize_text_field( (string) $arguments['search'] ) : '';
		$status    = isset( $arguments['status'] ) ? sanitize_key( (string) $arguments['status'] ) : 'publish';
		$orderby   = isset( $arguments['orderby'] ) ? sanitize_key( (string) $arguments['orderby'] ) : 'date';
		$order     = isset( $arguments['order'] ) ? strtoupper( sanitize_key( (string) $arguments['order'] ) ) : 'DESC';

		$allowed_orderby = array( 'date', 'modified', 'title', 'id', 'ID', 'menu_order' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date';
		}
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $per_page,
			'post_status'    => $status,
			's'              => $search,
			'orderby'        => $orderby,
			'order'          => $order,
			'perm'           => 'readable',
			'no_found_rows'  => false,
			'fields'         => 'ids',
		);

		if ( $offset > 0 ) {
			$query_args['offset'] = $offset;
		} else {
			$query_args['paged'] = $page;
		}

		$query = new \WP_Query( $query_args );

		$items = array();
		foreach ( $query->posts as $post_id ) {
			if ( ! current_user_can( 'read_post', (int) $post_id ) ) {
				continue;
			}
			$items[] = array(
				'id'    => $post_id,
				'title' => get_the_title( $post_id ),
				'link'  => get_permalink( $post_id ),
			);
		}

		return array(
			'items'   => $items,
			'total'   => count( $items ),
			'found'   => (int) $query->found_posts,
			'pages'   => (int) $query->max_num_pages,
			'tool'    => 'webo/list-posts',
			'applied' => array(
				'post_type' => $post_type,
				'status'    => $status,
				'per_page'  => $per_page,
				'page'      => $page,
				'offset'    => $offset,
				'search'    => $search,
				'orderby'   => $orderby,
				'order'     => $order,
			),
		);
	}

	/**
	 * Resolve a content ID from tool arguments, accepting post_id or id alias.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return int
	 */
	private static function resolve_post_id_argument( array $arguments ) {
		if ( isset( $arguments['post_id'] ) ) {
			$post_id = (int) $arguments['post_id'];
			if ( $post_id > 0 ) {
				return $post_id;
			}
		}

		if ( isset( $arguments['id'] ) ) {
			$post_id = (int) $arguments['id'];
			if ( $post_id > 0 ) {
				return $post_id;
			}
		}

		return 0;
	}

	/**
	 * Require read permission for a concrete post object.
	 *
	 * @param int $post_id Post ID.
	 * @return true|\WP_Error
	 */
	private static function require_read_post( int $post_id ) {
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new \WP_Error(
				'webo_mcp_cannot_read_post',
				'You do not have permission to read this post',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Require edit permission for a concrete post object.
	 *
	 * @param int $post_id Post ID.
	 * @return true|\WP_Error
	 */
	private static function require_edit_post( int $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error(
				'webo_mcp_permission_denied',
				'You do not have permission to edit this post',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Require delete permission for a concrete post object.
	 *
	 * @param int $post_id Post ID.
	 * @return true|\WP_Error
	 */
	private static function require_delete_post( int $post_id ) {
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return new \WP_Error(
				'webo_mcp_permission_denied',
				'You do not have permission to delete this post',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Require permission to create a post of a given type.
	 *
	 * @param string $post_type Post type key.
	 * @return \WP_Post_Type|\WP_Error
	 */
	private static function require_create_post_type( string $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object instanceof \WP_Post_Type ) {
			return new \WP_Error(
				'webo_mcp_post_type_not_found',
				'Post type not found',
				array( 'post_type' => $post_type )
			);
		}

		$create_cap = isset( $post_type_object->cap->create_posts ) ? (string) $post_type_object->cap->create_posts : 'edit_posts';
		if ( ! current_user_can( $create_cap ) ) {
			return new \WP_Error(
				'webo_mcp_permission_denied',
				'You do not have permission to create this post type',
				array( 'status' => 403 )
			);
		}

		return $post_type_object;
	}

	/**
	 * Require extra capability for publishing/private/trashing transitions.
	 *
	 * @param string $post_type Post type key.
	 * @param string $status    Target post status.
	 * @param int    $post_id   Optional existing post ID.
	 * @return true|\WP_Error
	 */
	private static function require_post_status_capability( string $post_type, string $status, int $post_id = 0 ) {
		if ( 'trash' === $status && $post_id > 0 ) {
			return self::require_delete_post( $post_id );
		}

		if ( ! in_array( $status, array( 'publish', 'private' ), true ) ) {
			return true;
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object instanceof \WP_Post_Type ) {
			return new \WP_Error(
				'webo_mcp_post_type_not_found',
				'Post type not found',
				array( 'post_type' => $post_type )
			);
		}

		$publish_cap = isset( $post_type_object->cap->publish_posts ) ? (string) $post_type_object->cap->publish_posts : 'publish_posts';
		if ( ! current_user_can( $publish_cap ) ) {
			return new \WP_Error(
				'webo_mcp_permission_denied',
				'You do not have permission to publish this post type',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Require a taxonomy capability for taxonomy mutations.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @param string $cap_key  Capability key on WP_Taxonomy::cap.
	 * @return true|\WP_Error
	 */
	private static function require_taxonomy_capability( string $taxonomy, string $cap_key ) {
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object instanceof \WP_Taxonomy ) {
			return new \WP_Error( 'webo_mcp_taxonomy_not_found', 'Taxonomy not found' );
		}

		$capability = isset( $taxonomy_object->cap->{$cap_key} ) ? (string) $taxonomy_object->cap->{$cap_key} : 'manage_categories';
		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'webo_mcp_permission_denied',
				'You do not have permission for this taxonomy action',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get a single post by ID.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_post( array $arguments ) {
		$post_id = self::resolve_post_id_argument( $arguments );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		$read_check = self::require_read_post( $post_id );
		if ( is_wp_error( $read_check ) ) {
			return $read_check;
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
			$show_on_front  = (string) get_option( 'show_on_front', 'posts' );
			$page_on_front  = (int) get_option( 'page_on_front', 0 );
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
	 * Group posts that share identical normalized title and/or body text (e.g. duplicate drafts).
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function find_duplicate_posts( array $arguments ) {
		$post_type = isset( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : 'post';
		$status    = isset( $arguments['status'] ) ? sanitize_key( (string) $arguments['status'] ) : 'draft';
		$match     = isset( $arguments['match'] ) ? sanitize_key( (string) $arguments['match'] ) : 'content';
		if ( ! in_array( $match, array( 'content', 'title', 'title_and_content' ), true ) ) {
			$match = 'content';
		}
		$max_posts  = isset( $arguments['max_posts'] ) ? (int) $arguments['max_posts'] : 200;
		$max_posts  = max( 1, min( 500, $max_posts ) );
		$offset     = isset( $arguments['offset'] ) ? max( 0, (int) $arguments['offset'] ) : 0;
		$skip_empty = ! array_key_exists( 'skip_empty', $arguments ) || (bool) $arguments['skip_empty'];

		$query = new \WP_Query(
			array(
				'post_type'           => $post_type,
				'post_status'         => $status,
				'posts_per_page'      => $max_posts,
				'offset'              => $offset,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);

		$buckets            = array();
		$scanned            = 0;
		$skipped_permission = 0;
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			++$scanned;
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				++$skipped_permission;
				continue;
			}
			$sig_data = self::duplicate_post_signature_data( $post, $match, $skip_empty );
			if ( null === $sig_data ) {
				continue;
			}
			$hash = md5( $sig_data );
			if ( ! isset( $buckets[ $hash ] ) ) {
				$buckets[ $hash ] = array(
					'normalized_preview' => self::duplicate_preview( $sig_data ),
					'posts'              => array(),
				);
			}
			$buckets[ $hash ]['posts'][] = array(
				'id'     => (int) $post->ID,
				'title'  => get_the_title( $post ),
				'slug'   => $post->post_name,
				'status' => $post->post_status,
				'type'   => $post->post_type,
			);
		}

		$groups = array();
		foreach ( $buckets as $hash => $bucket ) {
			if ( count( $bucket['posts'] ) < 2 ) {
				continue;
			}
			$groups[] = array(
				'content_hash'       => $hash,
				'match'              => $match,
				'normalized_preview' => $bucket['normalized_preview'],
				'posts'              => $bucket['posts'],
			);
		}

		$dup_ids = array();
		foreach ( $groups as $g ) {
			foreach ( $g['posts'] as $p ) {
				$dup_ids[] = $p['id'];
			}
		}
		$dup_ids = array_values( array_unique( array_map( 'intval', $dup_ids ) ) );

		return array(
			'tool'                  => 'webo/find-duplicate-posts',
			'groups'                => $groups,
			'group_count'           => count( $groups ),
			'duplicate_post_ids'    => $dup_ids,
			'scanned_post_count'    => $scanned,
			'skipped_no_permission' => $skipped_permission,
			'applied'               => array(
				'post_type'  => $post_type,
				'status'     => $status,
				'match'      => $match,
				'max_posts'  => $max_posts,
				'offset'     => $offset,
				'skip_empty' => $skip_empty,
			),
		);
	}

	/**
	 * Normalize text for duplicate comparison (HTML stripped, entities decoded, whitespace collapsed, lowercased).
	 *
	 * @param string $raw Raw HTML or plain text.
	 * @return string
	 */
	private static function normalize_for_duplicate_match( string $raw ): string {
		$text = wp_strip_all_tags( $raw );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return strtolower( trim( $text ) );
	}

	/**
	 * Build signature string or null if skipped.
	 *
	 * @param \WP_Post $post       Post object.
	 * @param string   $match_mode content|title|title_and_content.
	 * @param bool     $skip_empty Whether to omit empty signatures.
	 * @return string|null
	 */
	private static function duplicate_post_signature_data( \WP_Post $post, string $match_mode, bool $skip_empty ): ?string {
		$title   = self::normalize_for_duplicate_match( get_the_title( $post ) );
		$content = self::normalize_for_duplicate_match( (string) $post->post_content );

		switch ( $match_mode ) {
			case 'title':
				$blob = $title;
				break;
			case 'title_and_content':
				$blob = $title . "\0" . $content;
				break;
			case 'content':
			default:
				$blob = $content;
				break;
		}

		if ( $skip_empty && '' === $blob ) {
			return null;
		}

		return $blob;
	}

	/**
	 * Truncate normalized string for API response preview.
	 *
	 * @param string $blob Normalized signature blob.
	 * @return string
	 */
	private static function duplicate_preview( string $blob ): string {
		$blob = preg_replace( '/\s+/u', ' ', $blob );
		$blob = trim( (string) $blob );
		if ( strlen( $blob ) <= 200 ) {
			return $blob;
		}
		return substr( $blob, 0, 197 ) . '…';
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

		$read_check = self::require_read_post( $post_id );
		if ( is_wp_error( $read_check ) ) {
			return $read_check;
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
			$edit_check = self::require_edit_post( (int) $post->ID );
			if ( is_wp_error( $edit_check ) ) {
				return $edit_check;
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
				$status_check           = self::require_post_status_capability( (string) $post->post_type, $payload['post_status'], (int) $post->ID );
				if ( is_wp_error( $status_check ) ) {
					return $status_check;
				}
			}
			if ( count( $payload ) > 1 && ! is_wp_error( wp_update_post( $payload, true ) ) ) {
				$post   = get_post( $post->ID );
				$result = array(
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
					$read_check = self::require_read_post( (int) $post->ID );
					if ( is_wp_error( $read_check ) ) {
						return $read_check;
					}

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

		if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'private' ), true ) ) {
			return new \WP_Error( 'webo_mcp_invalid_status', 'Invalid status for post creation' );
		}

		$post_type_object = self::require_create_post_type( $post_type );
		if ( is_wp_error( $post_type_object ) ) {
			return $post_type_object;
		}

		$status_check = self::require_post_status_capability( $post_type, $status );
		if ( is_wp_error( $status_check ) ) {
			return $status_check;
		}

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
			'id'      => (int) $post_id,
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
		$post_id = self::resolve_post_id_argument( $arguments );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		$edit_check = self::require_edit_post( $post_id );
		if ( is_wp_error( $edit_check ) ) {
			return $edit_check;
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

		if ( isset( $arguments['excerpt'] ) ) {
			$payload['post_excerpt'] = wp_kses_post( (string) $arguments['excerpt'] );
		}

		if ( isset( $arguments['status'] ) ) {
			$payload['post_status'] = sanitize_key( (string) $arguments['status'] );
			$status_check           = self::require_post_status_capability( (string) $post->post_type, $payload['post_status'], $post_id );
			if ( is_wp_error( $status_check ) ) {
				return $status_check;
			}
		}

		$result = wp_update_post( $payload, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'id'      => (int) $result,
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
		$post_id = self::resolve_post_id_argument( $arguments );
		$force   = isset( $arguments['force'] ) ? (bool) $arguments['force'] : false;

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		$delete_check = self::require_delete_post( $post_id );
		if ( is_wp_error( $delete_check ) ) {
			return $delete_check;
		}

		$result = wp_delete_post( $post_id, $force );
		if ( ! $result ) {
			return new \WP_Error( 'webo_mcp_delete_failed', 'Failed to delete post' );
		}

		return array(
			'id'      => $post_id,
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
			if ( ! current_user_can( 'read_post', (int) $attachment_id ) ) {
				continue;
			}
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
				'id'       => (int) $comment->comment_ID,
				'post_id'  => (int) $comment->comment_post_ID,
				'author'   => (string) $comment->comment_author,
				'content'  => (string) $comment->comment_content,
				'approved' => (string) $comment->comment_approved,
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
	 * Unified read-only media query tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function media_query( array $arguments ) {
		$action          = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';
		$allowed_actions = array( 'list', 'get' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_action',
				'action must be one of: ' . implode( ', ', $allowed_actions )
			);
		}

		switch ( $action ) {
			case 'list':
				$result = self::list_media( $arguments );
				break;
			case 'get':
				$result = self::get_media( $arguments );
				break;
			default:
				return new \WP_Error( 'webo_mcp_invalid_action', 'Unknown action' );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['tool']   = 'webo/media-query';
		$result['action'] = $action;

		return $result;
	}

	/**
	 * Unified media mutation tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function media_mutate( array $arguments ) {
		$action          = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';
		$allowed_actions = array( 'upload', 'update', 'delete' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_action',
				'action must be one of: ' . implode( ', ', $allowed_actions )
			);
		}

		switch ( $action ) {
			case 'upload':
				$result = self::upload_media_from_url( $arguments );
				break;
			case 'update':
				$result = self::update_media( $arguments );
				break;
			case 'delete':
				if ( ! current_user_can( 'delete_files' ) ) {
					return new \WP_Error( 'webo_mcp_permission_denied', 'delete_files capability required', array( 'status' => 403 ) );
				}
				$result = self::delete_media( $arguments );
				break;
			default:
				return new \WP_Error( 'webo_mcp_invalid_action', 'Unknown action' );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['tool']   = 'webo/media-mutate';
		$result['action'] = $action;

		return $result;
	}

	/**
	 * Unified read-only taxonomy query tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function taxonomy_query( array $arguments ) {
		$action          = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';
		$allowed_actions = array( 'discover', 'list', 'get' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_action',
				'action must be one of: ' . implode( ', ', $allowed_actions )
			);
		}

		switch ( $action ) {
			case 'discover':
				$result = self::discover_taxonomies( $arguments );
				break;
			case 'list':
				$result = self::list_terms( $arguments );
				break;
			case 'get':
				$result = self::get_term( $arguments );
				break;
			default:
				return new \WP_Error( 'webo_mcp_invalid_action', 'Unknown action' );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['tool']   = 'webo/taxonomy-query';
		$result['action'] = $action;

		return $result;
	}

	/**
	 * Unified taxonomy mutation tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function taxonomy_mutate( array $arguments ) {
		$action          = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';
		$allowed_actions = array( 'create', 'update', 'delete' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_action',
				'action must be one of: ' . implode( ', ', $allowed_actions )
			);
		}

		switch ( $action ) {
			case 'create':
				$result = self::create_term( $arguments );
				break;
			case 'update':
				$result = self::update_term( $arguments );
				break;
			case 'delete':
				$result = self::delete_term( $arguments );
				break;
			default:
				return new \WP_Error( 'webo_mcp_invalid_action', 'Unknown action' );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['tool']   = 'webo/taxonomy-mutate';
		$result['action'] = $action;

		return $result;
	}

	/**
	 * Unified read-only comment query tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function comment_query( array $arguments ) {
		$action          = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';
		$allowed_actions = array( 'list', 'get' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_action',
				'action must be one of: ' . implode( ', ', $allowed_actions )
			);
		}

		switch ( $action ) {
			case 'list':
				$result = self::list_comments( $arguments );
				break;
			case 'get':
				$result = self::get_comment( $arguments );
				break;
			default:
				return new \WP_Error( 'webo_mcp_invalid_action', 'Unknown action' );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['tool']   = 'webo/comment-query';
		$result['action'] = $action;

		return $result;
	}

	/**
	 * Unified comment mutation tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function comment_mutate( array $arguments ) {
		$action          = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';
		$allowed_actions = array( 'update', 'delete' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_action',
				'action must be one of: ' . implode( ', ', $allowed_actions )
			);
		}

		switch ( $action ) {
			case 'update':
				$result = self::update_comment( $arguments );
				break;
			case 'delete':
				$result = self::delete_comment( $arguments );
				break;
			default:
				return new \WP_Error( 'webo_mcp_invalid_action', 'Unknown action' );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['tool']   = 'webo/comment-mutate';
		$result['action'] = $action;

		return $result;
	}

	/**
	 * Unified read-only content query tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function content_query( array $arguments ) {
		$action          = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';
		$allowed_actions = array( 'list', 'get', 'find-by-url', 'find-by-slug', 'get-homepage', 'discover-types', 'list-revisions', 'find-duplicates', 'get-terms' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_action',
				'action must be one of: ' . implode( ', ', $allowed_actions )
			);
		}

		switch ( $action ) {
			case 'list':
				return self::list_posts( $arguments );
			case 'get':
				return self::get_post( $arguments );
			case 'find-by-url':
				// Strip write payload — mutations belong in content_mutate.
				unset( $arguments['update'] );
				return self::find_content_by_url( $arguments );
			case 'find-by-slug':
				return self::get_content_by_slug( $arguments );
			case 'get-homepage':
				return self::get_homepage_info( $arguments );
			case 'discover-types':
				return self::discover_content_types( $arguments );
			case 'list-revisions':
				return self::list_revisions( $arguments );
			case 'find-duplicates':
				return self::find_duplicate_posts( $arguments );
			case 'get-terms':
				return self::get_content_terms( $arguments );
		}

		return new \WP_Error( 'webo_mcp_invalid_action', 'Unknown action' );
	}

	/**
	 * Unified write content mutation tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function content_mutate( array $arguments ) {
		$action          = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';
		$allowed_actions = array( 'create', 'update', 'delete', 'restore-revision', 'bulk-update-status', 'search-replace', 'set-featured-image', 'assign-terms' );

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_action',
				'action must be one of: ' . implode( ', ', $allowed_actions )
			);
		}

		switch ( $action ) {
			case 'create':
				if ( ! current_user_can( 'edit_posts' ) ) {
					return new \WP_Error( 'webo_mcp_permission_denied', 'edit_posts capability required', array( 'status' => 403 ) );
				}
				return self::create_post( $arguments );
			case 'update':
				if ( ! current_user_can( 'edit_posts' ) ) {
					return new \WP_Error( 'webo_mcp_permission_denied', 'edit_posts capability required', array( 'status' => 403 ) );
				}
				return self::update_post( $arguments );
			case 'delete':
				if ( ! current_user_can( 'delete_posts' ) ) {
					return new \WP_Error( 'webo_mcp_permission_denied', 'delete_posts capability required', array( 'status' => 403 ) );
				}
				return self::delete_post( $arguments );
			case 'restore-revision':
				if ( ! current_user_can( 'edit_posts' ) ) {
					return new \WP_Error( 'webo_mcp_permission_denied', 'edit_posts capability required', array( 'status' => 403 ) );
				}
				return self::restore_revision( $arguments );
			case 'bulk-update-status':
				if ( ! current_user_can( 'edit_posts' ) ) {
					return new \WP_Error( 'webo_mcp_permission_denied', 'edit_posts capability required', array( 'status' => 403 ) );
				}
				return self::bulk_update_post_status( $arguments );
			case 'search-replace':
				if ( ! current_user_can( 'edit_posts' ) ) {
					return new \WP_Error( 'webo_mcp_permission_denied', 'edit_posts capability required', array( 'status' => 403 ) );
				}
				// map limit → max_scan_posts for backward-compat with the inner handler.
				if ( isset( $arguments['limit'] ) && ! isset( $arguments['max_scan_posts'] ) ) {
					$arguments['max_scan_posts'] = $arguments['limit'];
				}
				return self::search_replace_posts( $arguments );
			case 'set-featured-image':
				if ( ! current_user_can( 'edit_posts' ) ) {
					return new \WP_Error( 'webo_mcp_permission_denied', 'edit_posts capability required', array( 'status' => 403 ) );
				}
				return self::set_post_featured_image( $arguments );
			case 'assign-terms':
				if ( ! current_user_can( 'manage_categories' ) ) {
					return new \WP_Error( 'webo_mcp_permission_denied', 'manage_categories capability required', array( 'status' => 403 ) );
				}
				return self::assign_terms_to_content( $arguments );
		}

		return new \WP_Error( 'webo_mcp_invalid_action', 'Unknown action' );
	}

	/**
	 * Unified navigation menu query tool.
	 * Actions: list, list-items, list-locations.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function menu_query( array $arguments ) {
		$action = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';

		if ( '' === $action ) {
			return new \WP_Error( 'webo_mcp_missing_action', 'action is required for menu-query' );
		}

		return match ( $action ) {
			'list'             => self::list_nav_menus( $arguments ),
			'list-items'       => self::list_nav_menu_items( $arguments ),
			'list-locations'   => self::list_nav_menu_locations( $arguments ),
			default            => new \WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown menu-query action: %s', $action ) ),
		};
	}

	/**
	 * Unified navigation menu mutation tool.
	 * Actions: create, create-and-assign, assign, add-item, add-custom-item.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function menu_mutate( array $arguments ) {
		$action = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';

		if ( '' === $action ) {
			return new \WP_Error( 'webo_mcp_missing_action', 'action is required for menu-mutate' );
		}

		return match ( $action ) {
			'create'           => self::create_nav_menu( $arguments ),
			'create-and-assign' => self::create_nav_menu_for_location( $arguments ),
			'assign'           => self::assign_nav_menu_to_location( $arguments ),
			'add-item'         => self::add_nav_menu_item_from_post( $arguments ),
			'add-custom-item'  => self::add_nav_menu_item_custom( $arguments ),
			default            => new \WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown menu-mutate action: %s', $action ) ),
		};
	}

	/**
	 * Unified theme query tool.
	 * Actions: list.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function theme_query( array $arguments ) {
		$action = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : 'list';

		return match ( $action ) {
			'list' => self::list_themes( $arguments ),
			default => new \WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown theme-query action: %s', $action ) ),
		};
	}

	/**
	 * Unified theme mutation tool.
	 * Actions: switch.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function theme_mutate( array $arguments ) {
		$action = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';

		if ( '' === $action ) {
			return new \WP_Error( 'webo_mcp_missing_action', 'action is required for theme-mutate' );
		}

		return match ( $action ) {
			'switch' => self::switch_theme_tool( $arguments ),
			default => new \WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown theme-mutate action: %s', $action ) ),
		};
	}

	/**
	 * Unified plugin inspection query tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function plugin_query( array $arguments ) {
		$query = isset( $arguments['query'] ) ? sanitize_key( (string) $arguments['query'] ) : 'installed';
		$scope = isset( $arguments['scope'] ) ? sanitize_key( (string) $arguments['scope'] ) : 'all';

		$allowed_queries = array( 'installed', 'active', 'updates', 'network-active', 'rental-candidates', 'health' );
		$allowed_scopes  = array( 'all', 'active', 'network-active' );

		if ( ! in_array( $query, $allowed_queries, true ) ) {
			return new \WP_Error(
				'webo_mcp_invalid_plugin_query',
				'query must be one of: installed, active, updates, network-active, rental-candidates, health'
			);
		}

		if ( ! in_array( $scope, $allowed_scopes, true ) ) {
			$scope = 'all';
		}

		$refresh        = ! empty( $arguments['refresh'] );
		$requested      = isset( $arguments['fields'] ) && is_array( $arguments['fields'] ) ? $arguments['fields'] : array();
		$allowed_fields = self::plugin_query_allowed_fields();
		$fields         = array();
		$ignored_fields = array();

		foreach ( $requested as $field ) {
			$field = sanitize_key( (string) $field );
			if ( '' === $field ) {
				continue;
			}
			if ( in_array( $field, $allowed_fields, true ) ) {
				$fields[] = $field;
			} else {
				$ignored_fields[] = $field;
			}
		}

		$fields         = array_values( array_unique( $fields ) );
		$ignored_fields = array_values( array_unique( $ignored_fields ) );

		$inventory = self::plugin_query_collect_inventory();
		$updates   = array();

		// Luôn gọi wp_update_plugins() khi query là 'updates' để đảm bảo lấy thông tin mới nhất
		if ( 'updates' === $query ) {
			if ( ! function_exists( 'wp_update_plugins' ) ) {
				require_once ABSPATH . WPINC . '/update.php';
			}
			if ( function_exists( 'wp_update_plugins' ) ) {
				wp_update_plugins();
			}
		}

		if ( 'updates' === $query || 'health' === $query ) {
			$updates = self::plugin_query_collect_updates( $refresh );
			foreach ( $updates['map'] as $plugin_file => $update_row ) {
				if ( ! isset( $inventory[ $plugin_file ] ) ) {
					continue;
				}
				$inventory[ $plugin_file ]['update_available'] = true;
				$inventory[ $plugin_file ]['new_version']      = $update_row['new_version'];
				if ( isset( $update_row['auto_update_enabled'] ) ) {
					$inventory[ $plugin_file ]['auto_update_enabled'] = (bool) $update_row['auto_update_enabled'];
				}
			}
		}

		$effective_scope = $scope;
		if ( 'network-active' === $query ) {
			$effective_scope = 'network-active';
		}

		$items = array();
		switch ( $query ) {
			case 'installed':
				$items = array_values( $inventory );
				$items = self::plugin_query_filter_by_scope( $items, $effective_scope );
				break;

			case 'active':
				$items = array_values(
					array_filter(
						$inventory,
						static function ( array $item ) {
							return ! empty( $item['active'] );
						}
					)
				);
				$items = self::plugin_query_filter_by_scope( $items, $effective_scope );
				break;

			case 'updates':
				$items = array_values(
					array_filter(
						$inventory,
						static function ( array $item ) {
							return ! empty( $item['update_available'] );
						}
					)
				);
				$items = self::plugin_query_filter_by_scope( $items, $effective_scope );
				break;

			case 'network-active':
				$items = self::plugin_query_filter_by_scope( array_values( $inventory ), 'network-active' );
				break;

			case 'rental-candidates':
				$rental_items = apply_filters(
					'webo_mcp_plugin_query_rental_candidates',
					array(),
					$inventory,
					$arguments
				);
				$items        = self::plugin_query_normalize_external_items( $rental_items, $inventory );
				$items        = self::plugin_query_filter_by_scope( $items, $effective_scope );
				break;

			case 'health':
				$items = self::plugin_query_collect_health_items( $inventory );
				$items = self::plugin_query_filter_by_scope( $items, $effective_scope );
				break;
		}

		usort(
			$items,
			static function ( array $left, array $right ) {
				return strcmp( (string) $left['name'], (string) $right['name'] );
			}
		);

		$items = self::plugin_query_project_fields( $items, $fields );

		$response = array(
			'items'   => $items,
			'total'   => count( $items ),
			'tool'    => 'webo/plugin-query',
			'applied' => array(
				'query'   => $query,
				'scope'   => $effective_scope,
				'refresh' => $refresh,
				'fields'  => $fields,
			),
			'queries' => $allowed_queries,
			'scopes'  => $allowed_scopes,
		);

		if ( ! empty( $ignored_fields ) ) {
			$response['ignored_fields'] = $ignored_fields;
		}

		if ( 'updates' === $query ) {
			$response['updates_meta'] = array(
				'last_checked' => $updates['last_checked'],
				'source'       => 'wp_update_plugins + get_site_transient(update_plugins)',
			);
		}

		if ( 'rental-candidates' === $query && 0 === count( $items ) ) {
			$response['note'] = 'No rental candidates from core. Addons can extend via webo_mcp_plugin_query_rental_candidates.';
		}

		return $response;
	}

	/**
	 * Unified plugin mutation tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function plugin_mutate( array $arguments ) {
		$action = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : '';

		if ( '' === $action ) {
			return new \WP_Error( 'webo_mcp_missing_action', 'action is required for plugin-mutate' );
		}

		return match ( $action ) {
			'install'    => self::install_plugin( $arguments ),
			'activate'   => self::activate_plugin_tool( $arguments ),
			'deactivate' => self::deactivate_plugin_tool( $arguments ),
			default      => new \WP_Error( 'webo_mcp_invalid_action', sprintf( 'Unknown plugin-mutate action: %s', $action ) ),
		};
	}

	/**
	 * Install a WordPress.org plugin by slug, optionally activating it after install.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function install_plugin( array $arguments ) {
		if ( ! self::current_user_can_install_plugins() ) {
			return new \WP_Error( 'webo_mcp_permission_denied', 'install_plugins capability required', array( 'status' => 403 ) );
		}

		if ( function_exists( 'wp_is_file_mod_allowed' ) && ! wp_is_file_mod_allowed( 'capability_install_plugins' ) ) {
			return new \WP_Error( 'webo_mcp_file_mods_disabled', 'Plugin installation is blocked by WordPress file modification settings.', array( 'status' => 403 ) );
		}

		$slug = isset( $arguments['slug'] ) ? sanitize_key( (string) $arguments['slug'] ) : '';
		if ( '' === $slug ) {
			return new \WP_Error( 'webo_mcp_plugin_slug_required', 'slug is required for plugin install', array( 'status' => 400 ) );
		}

		$target_blog_id = self::resolve_plugin_target_blog_id( $arguments );
		if ( is_wp_error( $target_blog_id ) ) {
			return $target_blog_id;
		}

		self::load_plugin_admin_functions();

		$plugin_file = isset( $arguments['plugin_file'] ) ? self::sanitize_plugin_file_argument( $arguments['plugin_file'] ) : '';
		if ( '' !== $plugin_file && 0 !== validate_file( $plugin_file ) ) {
			return new \WP_Error( 'webo_mcp_invalid_plugin_file', 'plugin_file must be a relative plugin path such as akismet/akismet.php', array( 'status' => 400 ) );
		}

		if ( '' === $plugin_file ) {
			$plugin_file = self::plugin_query_find_plugin_file_by_slug( $slug );
		}

		$installed_plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$already_installed = ( '' !== $plugin_file && isset( $installed_plugins[ $plugin_file ] ) );
		$installed         = false;
		$overwrite         = ! empty( $arguments['overwrite'] );

		if ( ! $already_installed ) {
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}
			if ( ! class_exists( 'Plugin_Upgrader' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			if ( ! function_exists( 'request_filesystem_credentials' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array(
						'sections' => false,
					),
				)
			);

			if ( is_wp_error( $api ) ) {
				return $api;
			}

			if ( empty( $api->download_link ) ) {
				return new \WP_Error( 'webo_mcp_plugin_download_unavailable', sprintf( 'No download link found for plugin slug: %s', $slug ), array( 'status' => 404 ) );
			}

			$skin     = new \Automatic_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );
			$result   = $upgrader->install(
				(string) $api->download_link,
				array(
					'overwrite_package' => $overwrite,
				)
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! $result ) {
				$errors = method_exists( $skin, 'get_errors' ) ? $skin->get_errors() : null;
				if ( is_wp_error( $errors ) && $errors->has_errors() ) {
					return $errors;
				}

				return new \WP_Error( 'webo_mcp_plugin_install_failed', sprintf( 'Plugin install failed for slug: %s', $slug ), array( 'status' => 500 ) );
			}

			wp_clean_plugins_cache( true );
			$plugin_file       = self::plugin_query_find_plugin_file_by_slug( $slug );
			$installed         = true;
			$installed_plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		}

		if ( '' === $plugin_file || ! isset( $installed_plugins[ $plugin_file ] ) ) {
			return new \WP_Error( 'webo_mcp_plugin_file_not_found', sprintf( 'Installed plugin file not found for slug: %s', $slug ), array( 'status' => 404 ) );
		}

		$network_activate = ! empty( $arguments['network_activate'] ) || ! empty( $arguments['network_wide'] );
		if ( $network_activate && $target_blog_id > 0 ) {
			return new \WP_Error( 'webo_mcp_plugin_target_conflict', 'site_id/blog_id cannot be combined with network_activate or network_wide', array( 'status' => 400 ) );
		}

		$activate         = ! empty( $arguments['activate'] ) || $network_activate;
		if ( $activate ) {
			$activation = self::run_in_plugin_target_blog(
				$target_blog_id,
				static function () use ( $plugin_file, $network_activate ) {
					return self::activate_plugin_file( $plugin_file, $network_activate );
				}
			);
			if ( is_wp_error( $activation ) ) {
				return $activation;
			}
		}

		return self::run_in_plugin_target_blog(
			$target_blog_id,
			static function () use ( $plugin_file, $slug, $installed, $already_installed, $activate, $network_activate, $overwrite ) {
				return self::plugin_mutation_response(
					$plugin_file,
					array(
						'action'                     => 'install',
						'slug'                       => $slug,
						'installed'                  => (bool) $installed,
						'already_installed'          => (bool) $already_installed,
						'activate_requested'         => (bool) $activate,
						'network_activate_requested' => (bool) $network_activate,
						'overwrite_requested'        => (bool) $overwrite,
					)
				);
			}
		);
	}

	/**
	 * Resolve an optional target child site for site-specific plugin activation.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return int|\WP_Error Target blog ID, or 0 for current site context.
	 */
	private static function resolve_plugin_target_blog_id( array $arguments ) {
		$target_blog_id = 0;
		if ( isset( $arguments['site_id'] ) ) {
			$target_blog_id = (int) $arguments['site_id'];
		} elseif ( isset( $arguments['blog_id'] ) ) {
			$target_blog_id = (int) $arguments['blog_id'];
		}

		if ( $target_blog_id <= 0 ) {
			return 0;
		}

		if ( ! is_multisite() ) {
			return new \WP_Error( 'webo_mcp_multisite_required', 'site_id/blog_id requires WordPress multisite', array( 'status' => 400 ) );
		}

		if ( ! self::current_user_can_manage_network_plugins() ) {
			return new \WP_Error( 'webo_mcp_permission_denied', 'manage_network_plugins capability required for child-site plugin mutation', array( 'status' => 403 ) );
		}

		$site = function_exists( 'get_site' ) ? get_site( $target_blog_id ) : null;
		if ( ! $site ) {
			return new \WP_Error( 'webo_mcp_site_not_found', sprintf( 'Site not found: %d', $target_blog_id ), array( 'status' => 404 ) );
		}

		return $target_blog_id;
	}

	/**
	 * Run a plugin mutation inside a child site context when requested.
	 *
	 * @param int      $target_blog_id Target blog ID, or 0 for current site.
	 * @param callable $callback       Callback to run.
	 * @return mixed
	 */
	private static function run_in_plugin_target_blog( int $target_blog_id, callable $callback ) {
		if ( $target_blog_id <= 0 || ! is_multisite() || get_current_blog_id() === $target_blog_id ) {
			return $callback();
		}

		switch_to_blog( $target_blog_id );
		try {
			return self::run_with_child_site_plugin_caps( $target_blog_id, $callback );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Temporarily bridge plugin-management capabilities while operating inside a child site.
	 *
	 * WordPress core re-checks capabilities inside activate_plugin()/deactivate_plugins()
	 * after switch_to_blog(), so a network operator needs a scoped user_has_cap bridge.
	 *
	 * @param int      $target_blog_id Target child-site blog ID.
	 * @param callable $callback       Callback to execute.
	 * @return mixed
	 */
	private static function run_with_child_site_plugin_caps( int $target_blog_id, callable $callback ) {
		if ( $target_blog_id <= 0 || ! is_multisite() || ! self::current_user_can_manage_network_plugins() ) {
			return $callback();
		}

		$user_id    = get_current_user_id();
		$grant_caps = static function ( $allcaps, $caps, $args, $user ) use ( $user_id ) {
			if ( ! $user instanceof \WP_User || (int) $user->ID !== (int) $user_id ) {
				return $allcaps;
			}

			$allcaps['activate_plugins']   = true;
			$allcaps['deactivate_plugins'] = true;
			$allcaps['update_plugins']     = true;
			$allcaps['install_plugins']    = true;

			return $allcaps;
		};

		add_filter( 'user_has_cap', $grant_caps, 10, 4 );
		try {
			return $callback();
		} finally {
			remove_filter( 'user_has_cap', $grant_caps, 10 );
		}
	}

	/**
	 * Whether the current user can manage network-level plugins.
	 *
	 * @return bool
	 */
	private static function current_user_can_manage_network_plugins() {
		return current_user_can( 'manage_network_plugins' ) || ( is_multisite() && function_exists( 'is_super_admin' ) && is_super_admin() );
	}

	/**
	 * Whether the current user can activate plugins in the current site context.
	 *
	 * @return bool
	 */
	private static function current_user_can_activate_site_plugins() {
		return current_user_can( 'activate_plugins' ) || ( is_multisite() && function_exists( 'is_super_admin' ) && is_super_admin() );
	}

	/**
	 * Whether the current user can install plugins.
	 *
	 * @return bool
	 */
	private static function current_user_can_install_plugins() {
		return current_user_can( 'install_plugins' ) || ( is_multisite() && function_exists( 'is_super_admin' ) && is_super_admin() );
	}

	/**
	 * Activate an already installed plugin.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function activate_plugin_tool( array $arguments ) {
		$plugin_file = self::resolve_installed_plugin_file( $arguments );
		if ( is_wp_error( $plugin_file ) ) {
			return $plugin_file;
		}

		$target_blog_id = self::resolve_plugin_target_blog_id( $arguments );
		if ( is_wp_error( $target_blog_id ) ) {
			return $target_blog_id;
		}

		$network_wide = ! empty( $arguments['network_activate'] ) || ! empty( $arguments['network_wide'] );
		if ( $network_wide && $target_blog_id > 0 ) {
			return new \WP_Error( 'webo_mcp_plugin_target_conflict', 'site_id/blog_id cannot be combined with network_activate or network_wide', array( 'status' => 400 ) );
		}

		$activation = self::run_in_plugin_target_blog(
			$target_blog_id,
			static function () use ( $plugin_file, $network_wide ) {
				return self::activate_plugin_file( $plugin_file, $network_wide );
			}
		);
		if ( is_wp_error( $activation ) ) {
			return $activation;
		}

		return self::run_in_plugin_target_blog(
			$target_blog_id,
			static function () use ( $plugin_file, $network_wide ) {
				return self::plugin_mutation_response(
					$plugin_file,
					array(
						'action'                     => 'activate',
						'network_activate_requested' => (bool) $network_wide,
					)
				);
			}
		);
	}

	/**
	 * Deactivate an already installed plugin.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function deactivate_plugin_tool( array $arguments ) {
		$plugin_file = self::resolve_installed_plugin_file( $arguments );
		if ( is_wp_error( $plugin_file ) ) {
			return $plugin_file;
		}

		$target_blog_id = self::resolve_plugin_target_blog_id( $arguments );
		if ( is_wp_error( $target_blog_id ) ) {
			return $target_blog_id;
		}

		$network_wide = ! empty( $arguments['network_wide'] ) || ! empty( $arguments['network_activate'] );
		if ( $network_wide && $target_blog_id > 0 ) {
			return new \WP_Error( 'webo_mcp_plugin_target_conflict', 'site_id/blog_id cannot be combined with network_activate or network_wide', array( 'status' => 400 ) );
		}

		$deactivation = self::run_in_plugin_target_blog(
			$target_blog_id,
			static function () use ( $plugin_file, $network_wide ) {
				return self::deactivate_plugin_file( $plugin_file, $network_wide );
			}
		);
		if ( is_wp_error( $deactivation ) ) {
			return $deactivation;
		}

		return self::run_in_plugin_target_blog(
			$target_blog_id,
			static function () use ( $plugin_file, $network_wide ) {
				return self::plugin_mutation_response(
					$plugin_file,
					array(
						'action'                 => 'deactivate',
						'network_wide_requested' => (bool) $network_wide,
					)
				);
			}
		);
	}

	/**
	 * Activate a plugin file with the correct site or network permissions.
	 *
	 * @param string $plugin_file  Plugin file path.
	 * @param bool   $network_wide Whether to activate network-wide.
	 * @return true|\WP_Error
	 */
	private static function activate_plugin_file( string $plugin_file, bool $network_wide ) {
		self::load_plugin_admin_functions();

		if ( $network_wide ) {
			if ( ! is_multisite() ) {
				return new \WP_Error( 'webo_mcp_network_plugins_unavailable', 'network_activate requires multisite', array( 'status' => 400 ) );
			}
			if ( ! self::current_user_can_manage_network_plugins() ) {
				return new \WP_Error( 'webo_mcp_permission_denied', 'manage_network_plugins capability required', array( 'status' => 403 ) );
			}
		} elseif ( ! self::current_user_can_activate_site_plugins() ) {
			return new \WP_Error( 'webo_mcp_permission_denied', 'activate_plugins capability required', array( 'status' => 403 ) );
		}

		$result = activate_plugin( $plugin_file, '', $network_wide, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Deactivate a plugin file with the correct site or network permissions.
	 *
	 * @param string $plugin_file  Plugin file path.
	 * @param bool   $network_wide Whether to deactivate network-wide.
	 * @return true|\WP_Error
	 */
	private static function deactivate_plugin_file( string $plugin_file, bool $network_wide ) {
		self::load_plugin_admin_functions();

		if ( $network_wide ) {
			if ( ! is_multisite() ) {
				return new \WP_Error( 'webo_mcp_network_plugins_unavailable', 'network_wide requires multisite', array( 'status' => 400 ) );
			}
			if ( ! self::current_user_can_manage_network_plugins() ) {
				return new \WP_Error( 'webo_mcp_permission_denied', 'manage_network_plugins capability required', array( 'status' => 403 ) );
			}
		} elseif ( ! self::current_user_can_activate_site_plugins() ) {
			return new \WP_Error( 'webo_mcp_permission_denied', 'activate_plugins capability required', array( 'status' => 403 ) );
		}

		deactivate_plugins( $plugin_file, false, $network_wide );

		return true;
	}

	/**
	 * Resolve a plugin file from plugin_file or slug and assert it is installed.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return string|\WP_Error
	 */
	private static function resolve_installed_plugin_file( array $arguments ) {
		self::load_plugin_admin_functions();

		$plugin_file = isset( $arguments['plugin_file'] ) ? self::sanitize_plugin_file_argument( $arguments['plugin_file'] ) : '';
		if ( '' !== $plugin_file && 0 !== validate_file( $plugin_file ) ) {
			return new \WP_Error( 'webo_mcp_invalid_plugin_file', 'plugin_file must be a relative plugin path such as akismet/akismet.php', array( 'status' => 400 ) );
		}

		if ( '' === $plugin_file && isset( $arguments['slug'] ) ) {
			$plugin_file = self::plugin_query_find_plugin_file_by_slug( sanitize_key( (string) $arguments['slug'] ) );
		}

		if ( '' === $plugin_file ) {
			return new \WP_Error( 'webo_mcp_plugin_file_required', 'plugin_file or slug is required', array( 'status' => 400 ) );
		}

		$installed_plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		if ( ! isset( $installed_plugins[ $plugin_file ] ) ) {
			return new \WP_Error( 'webo_mcp_plugin_not_installed', sprintf( 'Plugin is not installed: %s', $plugin_file ), array( 'status' => 404 ) );
		}

		return $plugin_file;
	}

	/**
	 * Load WordPress admin plugin helpers when running through REST/MCP.
	 *
	 * @return void
	 */
	private static function load_plugin_admin_functions() {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'activate_plugin' ) || ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Sanitize a plugin file path argument without accepting absolute paths.
	 *
	 * @param mixed $plugin_file Raw plugin file value.
	 * @return string
	 */
	private static function sanitize_plugin_file_argument( $plugin_file ) {
		$plugin_file = trim( str_replace( '\\', '/', (string) $plugin_file ) );
		$plugin_file = preg_replace( '#/+#', '/', $plugin_file );
		$plugin_file = preg_replace( '/[^A-Za-z0-9_\-\.\/]/', '', (string) $plugin_file );

		return trim( (string) $plugin_file, '/' );
	}

	/**
	 * Find the canonical plugin file for an installed plugin slug.
	 *
	 * @param string $slug WordPress.org plugin slug or single-file plugin slug.
	 * @return string
	 */
	private static function plugin_query_find_plugin_file_by_slug( string $slug ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return '';
		}

		self::load_plugin_admin_functions();
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		foreach ( $plugins as $plugin_file => $metadata ) {
			unset( $metadata );
			$plugin_file = (string) $plugin_file;
			if ( $slug === self::plugin_query_slug_from_plugin_file( $plugin_file ) ) {
				return $plugin_file;
			}
		}

		return '';
	}

	/**
	 * Build the common response envelope for plugin mutations.
	 *
	 * @param string               $plugin_file Plugin file path.
	 * @param array<string, mixed> $extra       Additional response fields.
	 * @return array<string, mixed>
	 */
	private static function plugin_mutation_response( string $plugin_file, array $extra = array() ) {
		self::load_plugin_admin_functions();

		$inventory = self::plugin_query_collect_inventory();
		$asset     = isset( $inventory[ $plugin_file ] ) ? $inventory[ $plugin_file ] : array();
		$blog_id   = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

		return array_merge(
			array(
				'tool'           => 'webo/plugin-mutate',
				'plugin_file'    => $plugin_file,
				'site_id'        => $blog_id,
				'blog_id'        => $blog_id,
				'site_url'       => function_exists( 'home_url' ) ? home_url( '/' ) : '',
				'active'         => function_exists( 'is_plugin_active' ) ? (bool) is_plugin_active( $plugin_file ) : false,
				'network_active' => function_exists( 'is_plugin_active_for_network' ) ? (bool) is_plugin_active_for_network( $plugin_file ) : false,
				'asset'          => $asset,
			),
			$extra
		);
	}

	/**
	 * Build canonical plugin inventory for query selectors.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function plugin_query_collect_inventory() {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'validate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins      = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$active_plugins         = get_option( 'active_plugins', array() );
		$network_active_plugins = array();
		$auto_update_plugins    = get_site_option( 'auto_update_plugins', array() );

		if ( ! is_array( $active_plugins ) ) {
			$active_plugins = array();
		}

		if ( ! is_array( $auto_update_plugins ) ) {
			$auto_update_plugins = array();
		}

		if ( is_multisite() ) {
			$sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $sitewide_plugins ) ) {
				$network_active_plugins = array_keys( $sitewide_plugins );
			}
		}

		$active_lookup      = array_fill_keys( array_merge( $active_plugins, $network_active_plugins ), true );
		$network_lookup     = array_fill_keys( $network_active_plugins, true );
		$auto_update_lookup = array();

		foreach ( $auto_update_plugins as $plugin_file ) {
			$plugin_file = (string) $plugin_file;
			if ( '' !== $plugin_file ) {
				$auto_update_lookup[ $plugin_file ] = true;
			}
		}

		$items = array();
		foreach ( $installed_plugins as $plugin_file => $metadata ) {
			$plugin_file = (string) $plugin_file;
			$name        = isset( $metadata['Name'] ) ? (string) $metadata['Name'] : $plugin_file;
			$slug        = self::plugin_query_slug_from_plugin_file( $plugin_file );
			$text_domain = '';
			if ( isset( $metadata['TextDomain'] ) && is_string( $metadata['TextDomain'] ) ) {
				$text_domain = $metadata['TextDomain'];
			}
			$requires_header = '';
			if ( isset( $metadata['RequiresPlugins'] ) && is_string( $metadata['RequiresPlugins'] ) ) {
				$requires_header = $metadata['RequiresPlugins'];
			} elseif ( isset( $metadata['Requires Plugins'] ) && is_string( $metadata['Requires Plugins'] ) ) {
				$requires_header = $metadata['Requires Plugins'];
			}

			$items[ $plugin_file ] = array(
				'plugin_file'         => $plugin_file,
				'slug'                => $slug,
				'name'                => $name,
				'current_version'     => isset( $metadata['Version'] ) ? (string) $metadata['Version'] : '',
				'new_version'         => '',
				'author'              => isset( $metadata['Author'] ) ? wp_strip_all_tags( (string) $metadata['Author'] ) : '',
				'text_domain'         => (string) $text_domain,
				'requires_plugins'    => self::plugin_query_parse_requires_plugins( $requires_header ),
				'active'              => isset( $active_lookup[ $plugin_file ] ),
				'network_active'      => isset( $network_lookup[ $plugin_file ] ),
				'auto_update_enabled' => isset( $auto_update_lookup[ $plugin_file ] ),
				'update_available'    => false,
			);
		}

		return $items;
	}

	/**
	 * Collect plugin update map from update_plugins transient.
	 *
	 * @param bool $refresh Whether to refresh plugin update checks first.
	 * @return array{map: array<string, array<string, mixed>>, last_checked: int}
	 */
	private static function plugin_query_collect_updates( bool $refresh ) {
		if ( $refresh && ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . WPINC . '/update.php';
		}
		if ( $refresh && function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}

		$updates      = get_site_transient( 'update_plugins' );
		$last_checked = 0;
		if ( is_object( $updates ) && isset( $updates->last_checked ) ) {
			$last_checked = (int) $updates->last_checked;
		}

		$map = array();
		if ( ! is_object( $updates ) || ! isset( $updates->response ) || ! is_array( $updates->response ) ) {
			return array(
				'map'          => $map,
				'last_checked' => $last_checked,
			);
		}

		foreach ( $updates->response as $plugin_file => $row ) {
			$plugin_file = (string) $plugin_file;
			$new_version = '';
			$autoupdate  = null;

			if ( is_object( $row ) ) {
				$new_version = isset( $row->new_version ) ? (string) $row->new_version : '';
				if ( isset( $row->autoupdate ) ) {
					$autoupdate = (bool) $row->autoupdate;
				}
			} elseif ( is_array( $row ) ) {
				$new_version = isset( $row['new_version'] ) ? (string) $row['new_version'] : '';
				if ( isset( $row['autoupdate'] ) ) {
					$autoupdate = (bool) $row['autoupdate'];
				}
			}

			$item = array(
				'new_version' => $new_version,
			);
			if ( null !== $autoupdate ) {
				$item['auto_update_enabled'] = $autoupdate;
			}

			$map[ $plugin_file ] = $item;
		}

		return array(
			'map'          => $map,
			'last_checked' => $last_checked,
		);
	}

	/**
	 * Resolve plugin slug from plugin file path.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return string
	 */
	private static function plugin_query_slug_from_plugin_file( string $plugin_file ) {
		$plugin_file = trim( $plugin_file );
		if ( '' === $plugin_file ) {
			return '';
		}

		$first = explode( '/', $plugin_file );
		if ( ! empty( $first[0] ) && false !== strpos( $plugin_file, '/' ) ) {
			return sanitize_key( (string) $first[0] );
		}

		$base = basename( $plugin_file );
		if ( '.php' === substr( $base, -4 ) ) {
			$base = substr( $base, 0, -4 );
		}

		return sanitize_key( $base );
	}

	/**
	 * Parse RequiresPlugins header to dependency slug list.
	 *
	 * @param string $header Raw RequiresPlugins header value.
	 * @return array<int, string>
	 */
	private static function plugin_query_parse_requires_plugins( string $header ) {
		$header = trim( $header );
		if ( '' === $header ) {
			return array();
		}

		$parts = preg_split( '/\s*,\s*/', $header );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$slugs = array();
		foreach ( $parts as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Filter plugin items by scope.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @param string                           $scope Scope value.
	 * @return array<int, array<string, mixed>>
	 */
	private static function plugin_query_filter_by_scope( array $items, string $scope ) {
		if ( 'all' === $scope ) {
			return array_values( $items );
		}

		if ( 'active' === $scope ) {
			return array_values(
				array_filter(
					$items,
					static function ( array $item ) {
						return ! empty( $item['active'] ) && empty( $item['network_active'] );
					}
				)
			);
		}

		if ( 'network-active' === $scope ) {
			return array_values(
				array_filter(
					$items,
					static function ( array $item ) {
						return ! empty( $item['network_active'] );
					}
				)
			);
		}

		return array_values( $items );
	}

	/**
	 * Build health issue rows from plugin inventory.
	 *
	 * @param array<string, array<string, mixed>> $inventory Plugin inventory.
	 * @return array<int, array<string, mixed>>
	 */
	private static function plugin_query_collect_health_items( array $inventory ) {
		$duplicate_lookup = self::plugin_query_duplicate_lookup( $inventory );

		$slug_to_plugins = array();
		foreach ( $inventory as $plugin_file => $item ) {
			$slug = isset( $item['slug'] ) ? sanitize_key( (string) $item['slug'] ) : '';
			if ( '' === $slug ) {
				continue;
			}
			if ( ! isset( $slug_to_plugins[ $slug ] ) ) {
				$slug_to_plugins[ $slug ] = array();
			}
			$slug_to_plugins[ $slug ][] = (string) $plugin_file;
		}

		$items = array();
		foreach ( $inventory as $plugin_file => $item ) {
			$plugin_file = (string) $plugin_file;
			$dup_name    = isset( $duplicate_lookup[ $plugin_file ]['name'] ) ? (bool) $duplicate_lookup[ $plugin_file ]['name'] : false;
			$dup_domain  = isset( $duplicate_lookup[ $plugin_file ]['text_domain'] ) ? (bool) $duplicate_lookup[ $plugin_file ]['text_domain'] : false;
			$outdated    = ! empty( $item['update_available'] );

			$requires_plugins = isset( $item['requires_plugins'] ) && is_array( $item['requires_plugins'] ) ? $item['requires_plugins'] : array();
			$deps             = self::plugin_query_dependency_status( $requires_plugins, $slug_to_plugins, $inventory );

			$invalid_plugin = false;
			$invalid_reason = '';
			if ( function_exists( 'validate_plugin' ) ) {
				$valid = validate_plugin( $plugin_file );
				if ( is_wp_error( $valid ) ) {
					$invalid_plugin = true;
					$invalid_reason = implode( '; ', $valid->get_error_messages() );
				}
			}

			$issues = array();
			if ( $dup_name || $dup_domain ) {
				$issues[] = 'duplicate';
			}
			if ( $outdated ) {
				$issues[] = 'outdated';
			}
			if ( ! empty( $deps['missing'] ) ) {
				$issues[] = 'missing_dependency';
			}
			if ( ! empty( $deps['inactive'] ) ) {
				$issues[] = 'inactive_dependency';
			}
			if ( $invalid_plugin ) {
				$issues[] = 'invalid_plugin';
			}

			if ( empty( $issues ) ) {
				continue;
			}

			$item['health'] = array(
				'duplicate'             => ( $dup_name || $dup_domain ),
				'duplicate_name'        => $dup_name,
				'duplicate_text_domain' => $dup_domain,
				'outdated'              => $outdated,
				'missing_dependencies'  => $deps['missing'],
				'inactive_dependencies' => $deps['inactive'],
				'invalid_plugin'        => $invalid_plugin,
				'invalid_reason'        => $invalid_reason,
				'issues'                => $issues,
			);

			$items[] = $item;
		}

		return $items;
	}

	/**
	 * Build duplicate lookup by Name and TextDomain collisions.
	 *
	 * @param array<string, array<string, mixed>> $inventory Plugin inventory.
	 * @return array<string, array{name: bool, text_domain: bool}>
	 */
	private static function plugin_query_duplicate_lookup( array $inventory ) {
		$name_map = array();
		$td_map   = array();

		foreach ( $inventory as $plugin_file => $item ) {
			$name = isset( $item['name'] ) ? strtolower( trim( (string) $item['name'] ) ) : '';
			if ( '' !== $name ) {
				if ( ! isset( $name_map[ $name ] ) ) {
					$name_map[ $name ] = array();
				}
				$name_map[ $name ][] = (string) $plugin_file;
			}

			$text_domain = isset( $item['text_domain'] ) ? sanitize_key( (string) $item['text_domain'] ) : '';
			if ( '' !== $text_domain ) {
				if ( ! isset( $td_map[ $text_domain ] ) ) {
					$td_map[ $text_domain ] = array();
				}
				$td_map[ $text_domain ][] = (string) $plugin_file;
			}
		}

		$out = array();
		foreach ( $inventory as $plugin_file => $item ) {
			$plugin_file = (string) $plugin_file;
			$name        = isset( $item['name'] ) ? strtolower( trim( (string) $item['name'] ) ) : '';
			$text_domain = isset( $item['text_domain'] ) ? sanitize_key( (string) $item['text_domain'] ) : '';

			$out[ $plugin_file ] = array(
				'name'        => ( '' !== $name && isset( $name_map[ $name ] ) && count( $name_map[ $name ] ) > 1 ),
				'text_domain' => ( '' !== $text_domain && isset( $td_map[ $text_domain ] ) && count( $td_map[ $text_domain ] ) > 1 ),
			);
		}

		return $out;
	}

	/**
	 * Determine dependency status for one plugin from RequiresPlugins slugs.
	 *
	 * @param array<int, string>                  $requires_plugins Dependency slugs.
	 * @param array<string, array<int, string>>   $slug_to_plugins   Slug to plugin file map.
	 * @param array<string, array<string, mixed>> $inventory         Plugin inventory.
	 * @return array{missing: array<int, string>, inactive: array<int, string>}
	 */
	private static function plugin_query_dependency_status( array $requires_plugins, array $slug_to_plugins, array $inventory ) {
		$missing  = array();
		$inactive = array();

		foreach ( $requires_plugins as $dep_slug ) {
			$dep_slug = sanitize_key( (string) $dep_slug );
			if ( '' === $dep_slug ) {
				continue;
			}

			if ( ! isset( $slug_to_plugins[ $dep_slug ] ) ) {
				$missing[] = $dep_slug;
				continue;
			}

			$has_active = false;
			foreach ( $slug_to_plugins[ $dep_slug ] as $dep_plugin_file ) {
				if ( isset( $inventory[ $dep_plugin_file ] ) && ! empty( $inventory[ $dep_plugin_file ]['active'] ) ) {
					$has_active = true;
					break;
				}
			}

			if ( ! $has_active ) {
				$inactive[] = $dep_slug;
			}
		}

		return array(
			'missing'  => array_values( array_unique( $missing ) ),
			'inactive' => array_values( array_unique( $inactive ) ),
		);
	}

	/**
	 * Normalize addon-provided rental candidate rows.
	 *
	 * @param mixed                               $items     External items from filter.
	 * @param array<string, array<string, mixed>> $inventory Canonical inventory.
	 * @return array<int, array<string, mixed>>
	 */
	private static function plugin_query_normalize_external_items( $items, array $inventory ) {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$out = array();
		foreach ( $items as $item ) {
			if ( is_string( $item ) ) {
				$plugin_file = sanitize_text_field( $item );
				if ( isset( $inventory[ $plugin_file ] ) ) {
					$row                     = $inventory[ $plugin_file ];
					$row['rental_candidate'] = true;
					$out[]                   = $row;
				}
				continue;
			}

			if ( ! is_array( $item ) ) {
				continue;
			}

			$plugin_file = isset( $item['plugin_file'] ) ? (string) $item['plugin_file'] : '';
			if ( '' !== $plugin_file && isset( $inventory[ $plugin_file ] ) ) {
				$row = array_merge( $inventory[ $plugin_file ], $item );
			} else {
				$row = $item;
			}

			if ( ! isset( $row['plugin_file'] ) || '' === (string) $row['plugin_file'] ) {
				continue;
			}
			if ( ! isset( $row['name'] ) || '' === (string) $row['name'] ) {
				$row['name'] = (string) $row['plugin_file'];
			}

			$row['rental_candidate'] = true;
			$out[]                   = $row;
		}

		return $out;
	}

	/**
	 * Return allowed field names for fields projection.
	 *
	 * @return array<int, string>
	 */
	private static function plugin_query_allowed_fields() {
		return array(
			'plugin_file',
			'slug',
			'name',
			'current_version',
			'new_version',
			'author',
			'text_domain',
			'requires_plugins',
			'active',
			'network_active',
			'auto_update_enabled',
			'update_available',
			'health',
			'rental_candidate',
		);
	}

	/**
	 * Project rows to requested field set while keeping identifiers.
	 *
	 * @param array<int, array<string, mixed>> $items  Full rows.
	 * @param array<int, string>               $fields Requested fields.
	 * @return array<int, array<string, mixed>>
	 */
	private static function plugin_query_project_fields( array $items, array $fields ) {
		if ( empty( $fields ) ) {
			return $items;
		}

		$out = array();
		foreach ( $items as $item ) {
			$row = array();
			if ( isset( $item['plugin_file'] ) ) {
				$row['plugin_file'] = $item['plugin_file'];
			}
			if ( isset( $item['name'] ) ) {
				$row['name'] = $item['name'];
			}

			foreach ( $fields as $field ) {
				if ( array_key_exists( $field, $item ) ) {
					$row[ $field ] = $item[ $field ];
				}
			}

			$out[] = $row;
		}

		return $out;
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
			'show_on_front',
			'page_on_front',
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
		$include_excerpt   = ! empty( $arguments['include_excerpt'] );
		$include_content   = ! empty( $arguments['include_content'] );
		$requested_post_id = self::resolve_post_id_argument( $arguments );

		$show_on_front  = (string) get_option( 'show_on_front', 'posts' );
		$page_on_front  = (int) get_option( 'page_on_front', 0 );
		$page_for_posts = (int) get_option( 'page_for_posts', 0 );

		$out = array(
			'tool'              => 'webo/get-homepage-info',
			'home_url'          => home_url( '/' ),
			'show_on_front'     => $show_on_front,
			'posts_per_page'    => (int) get_option( 'posts_per_page', 10 ),
			'is_posts_front'    => ( 'posts' === $show_on_front ),
			'page_on_front_id'  => $page_on_front,
			'page_for_posts_id' => $page_for_posts,
		);

		if ( 'page' === $show_on_front ) {
			if ( $page_on_front > 0 ) {
				$post = get_post( $page_on_front );
				if ( $post instanceof \WP_Post ) {
					if ( current_user_can( 'read_post', (int) $post->ID ) ) {
						$out['front_page'] = self::homepage_info_format_post(
							$post,
							$include_excerpt,
							$include_content,
							true
						);
					} else {
						$out['front_page']            = null;
						$out['front_page_unreadable'] = true;
					}
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
				if ( current_user_can( 'read_post', (int) $posts_page->ID ) ) {
					$out['posts_page'] = self::homepage_info_format_post(
						$posts_page,
						$include_excerpt,
						$include_content,
						false
					);
				} else {
					$out['posts_page']            = null;
					$out['posts_page_unreadable'] = true;
				}
			} else {
				$out['posts_page']         = null;
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
			$read_check = self::require_read_post( $requested_post_id );
			if ( is_wp_error( $read_check ) ) {
				return $read_check;
			}

			$by_id                             = self::homepage_info_format_post(
				$resolved,
				$include_excerpt,
				$include_content,
				true
			);
			$by_id['is_configured_front_page'] = ( 'page' === $show_on_front && $page_on_front === $requested_post_id );
			$by_id['is_configured_posts_page'] = ( $page_for_posts === $requested_post_id );
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
				$url                   = wp_get_attachment_image_url( $thumb_id, 'full' );
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
			'show_on_front',
			'page_on_front',
			'permalink_structure',
			'category_base',
			'tag_base',
		);

		$updated             = array();
		$skipped             = array();
		$flush_rewrite_rules = false;
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
			if ( in_array( $option_name, array( 'permalink_structure', 'category_base', 'tag_base' ), true ) ) {
				$flush_rewrite_rules = true;
			}
		}

		if ( $flush_rewrite_rules ) {
			flush_rewrite_rules();
		}

		return array(
			'updated'         => $updated,
			'skipped'         => $skipped,
			'rewrite_flushed' => $flush_rewrite_rules,
			'tool'            => 'webo/update-options',
		);
	}

	/**
	 * Set the WordPress site icon/favicon from an existing media attachment.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function set_site_icon( array $arguments ) {
		$attachment_id = isset( $arguments['attachment_id'] ) ? absint( $arguments['attachment_id'] ) : 0;

		if ( $attachment_id <= 0 ) {
			return new \WP_Error( 'webo_mcp_invalid_site_icon', 'A valid attachment_id is required' );
		}

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_Error( 'webo_mcp_site_icon_not_attachment', 'The attachment_id does not belong to a media attachment' );
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return new \WP_Error( 'webo_mcp_site_icon_not_image', 'The selected attachment is not an image' );
		}

		update_option( 'site_icon', $attachment_id );

		return array(
			'attachment_id' => $attachment_id,
			'icon_url'      => get_site_icon_url( 512 ),
			'site_icon'     => (int) get_option( 'site_icon' ),
			'success'       => (int) get_option( 'site_icon' ) === $attachment_id,
			'tool'          => 'webo/set-site-icon',
		);
	}

	/**
	 * Sanitizes values for the safe options allowlist used by webo/update-options.
	 *
	 * @param string $option_name Option key.
	 * @param mixed  $value       Raw value.
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

			case 'show_on_front':
				if ( ! is_scalar( $value ) && null !== $value ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'Value must be scalar' );
				}
				$s = sanitize_key( (string) $value );
				if ( ! in_array( $s, array( 'posts', 'page' ), true ) ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'show_on_front must be posts or page' );
				}
				return $s;

			case 'page_on_front':
				$n = absint( $value );
				if ( 0 === $n ) {
					return 0;
				}
				$post = get_post( $n );
				if ( ! ( $post instanceof \WP_Post ) || 'page' !== $post->post_type ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'page_on_front must reference a valid page ID' );
				}
				return $n;

			case 'permalink_structure':
				if ( ! is_scalar( $value ) && null !== $value ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'Value must be scalar' );
				}
				$s                  = trim( (string) $value );
				$allowed_structures = array(
					'',
					'/%year%/%monthnum%/%day%/%postname%/',
					'/%year%/%monthnum%/%postname%/',
					'/archives/%post_id%',
					'/%postname%/',
					'/%category%/%postname%/',
				);
				if ( ! in_array( $s, $allowed_structures, true ) ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'Unsupported permalink_structure' );
				}
				return $s;

			case 'category_base':
			case 'tag_base':
				if ( ! is_scalar( $value ) && null !== $value ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'Value must be scalar' );
				}
				$s = trim( sanitize_title_with_dashes( (string) $value ), '/' );
				if ( strlen( $s ) > 80 ) {
					return new \WP_Error( 'webo_mcp_invalid_option', 'Taxonomy base too long' );
				}
				return $s;

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

		$host      = strtolower( (string) $parsed['host'] );
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
		$skipped = 0;
		foreach ( array_map( 'intval', $post_ids ) as $post_id ) {
			$post = $post_id > 0 ? get_post( $post_id ) : null;
			if ( ! $post instanceof \WP_Post ) {
				++$skipped;
				continue;
			}

			$edit_check = self::require_edit_post( $post_id );
			if ( is_wp_error( $edit_check ) ) {
				++$skipped;
				continue;
			}

			$status_check = self::require_post_status_capability( (string) $post->post_type, $status, $post_id );
			if ( is_wp_error( $status_check ) ) {
				++$skipped;
				continue;
			}

			$result = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $status,
				),
				true
			);
			if ( ! is_wp_error( $result ) && $result ) {
				++$updated;
			}
		}
		return array(
			'updated'               => $updated,
			'skipped_no_permission' => $skipped,
			'status'                => $status,
			'tool'                  => 'webo/bulk-update-post-status',
		);
	}

	/**
	 * List revisions for a post.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function list_revisions( array $arguments ) {
		$post_id = self::resolve_post_id_argument( $arguments );
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}
		$read_check = self::require_read_post( $post_id );
		if ( is_wp_error( $read_check ) ) {
			return $read_check;
		}
		$revisions = wp_get_post_revisions( $post_id );
		$items     = array();
		foreach ( $revisions as $rev ) {
			$items[] = array(
				'id'        => (int) $rev->ID,
				'post_id'   => $post_id,
				'date'      => $rev->post_modified,
				'author_id' => (int) $rev->post_author,
			);
		}
		return array(
			'items' => $items,
			'total' => count( $items ),
			'tool'  => 'webo/list-revisions',
		);
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
		$edit_check = self::require_edit_post( $post_id );
		if ( is_wp_error( $edit_check ) ) {
			return $edit_check;
		}
		$restored = wp_restore_post_revision( $revision_id );
		if ( ! $restored ) {
			return new \WP_Error( 'webo_mcp_restore_failed', 'Failed to restore revision' );
		}
		return array(
			'id'          => $post_id,
			'post_id'     => $post_id,
			'revision_id' => $revision_id,
			'restored'    => true,
			'tool'        => 'webo/restore-revision',
		);
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
		$skipped  = 0;
		foreach ( $query->posts as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post || strpos( $post->post_content, $search ) === false ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
				++$skipped;
				continue;
			}
			$affected[] = array(
				'id'      => (int) $post_id,
				'post_id' => (int) $post_id,
				'title'   => get_the_title( $post_id ),
			);
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
			'affected'       => $affected,
			'count'          => count( $affected ),
			'skipped'        => $skipped,
			'dry_run'        => $dry_run,
			'offset'         => $offset,
			'max_scan_posts' => $limit,
			'total_posts'    => $total_posts,
			'has_more'       => $has_more,
			'next_offset'    => $has_more ? $next_offset : null,
			'tool'           => 'webo/search-replace-posts',
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
		$cap_check = self::require_taxonomy_capability( $taxonomy, 'manage_terms' );
		if ( is_wp_error( $cap_check ) ) {
			return $cap_check;
		}
		$name = isset( $arguments['name'] ) ? sanitize_text_field( (string) $arguments['name'] ) : '';
		if ( '' === $name ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'name is required' );
		}
		$slug        = isset( $arguments['slug'] ) ? sanitize_title( (string) $arguments['slug'] ) : '';
		$description = isset( $arguments['description'] ) ? sanitize_textarea_field( (string) $arguments['description'] ) : '';
		$parent_id   = isset( $arguments['parent_id'] ) ? max( 0, (int) $arguments['parent_id'] ) : 0;
		$result      = wp_insert_term(
			$name,
			$taxonomy,
			array_filter(
				array(
					'slug'        => ( '' !== $slug ) ? $slug : null,
					'description' => ( '' !== $description ) ? $description : '',
					'parent'      => $parent_id > 0 ? $parent_id : 0,
				)
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'id'               => (int) $result['term_id'],
			'term_id'          => (int) $result['term_id'],
			'taxonomy'         => $taxonomy,
			'term_taxonomy_id' => (int) $result['term_taxonomy_id'],
			'tool'             => 'webo/create-term',
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
		$cap_check = self::require_taxonomy_capability( $taxonomy, 'edit_terms' );
		if ( is_wp_error( $cap_check ) ) {
			return $cap_check;
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
		return array(
			'id'       => $term_id,
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
			'updated'  => true,
			'tool'     => 'webo/update-term',
		);
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
		$cap_check = self::require_taxonomy_capability( $taxonomy, 'delete_terms' );
		if ( is_wp_error( $cap_check ) ) {
			return $cap_check;
		}
		$result = wp_delete_term( $term_id, $taxonomy );
		if ( is_wp_error( $result ) || ! $result ) {
			return new \WP_Error( 'webo_mcp_delete_failed', 'Failed to delete term' );
		}
		return array(
			'id'       => $term_id,
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
			'deleted'  => true,
			'tool'     => 'webo/delete-term',
		);
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
				'name'         => $taxonomy->name,
				'label'        => $taxonomy->label,
				'description'  => isset( $taxonomy->description ) ? (string) $taxonomy->description : '',
				'object_type'  => (array) $taxonomy->object_type,
				'hierarchical' => (bool) $taxonomy->hierarchical,
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
		$post_id  = self::resolve_post_id_argument( $arguments );
		$taxonomy = isset( $arguments['taxonomy'] ) ? sanitize_key( (string) $arguments['taxonomy'] ) : '';
		$term_ids = isset( $arguments['term_ids'] ) && is_array( $arguments['term_ids'] ) ? $arguments['term_ids'] : array();

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		$edit_check = self::require_edit_post( $post_id );
		if ( is_wp_error( $edit_check ) ) {
			return $edit_check;
		}

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'webo_mcp_taxonomy_not_found', 'Taxonomy not found' );
		}

		$cap_check = self::require_taxonomy_capability( $taxonomy, 'assign_terms' );
		if ( is_wp_error( $cap_check ) ) {
			return $cap_check;
		}

		$term_ids = array_map( 'intval', $term_ids );
		$term_ids = array_filter( $term_ids );

		$result = wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'id'       => $post_id,
			'post_id'  => $post_id,
			'taxonomy' => $taxonomy,
			'term_ids' => $term_ids,
			'assigned' => true,
			'tool'     => 'webo/assign-terms-to-content',
		);
	}

	/**
	 * Get all terms assigned to a post for one or all taxonomies.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_content_terms( array $arguments ) {
		$post_id  = self::resolve_post_id_argument( $arguments );
		$taxonomy = isset( $arguments['taxonomy'] ) ? sanitize_key( (string) $arguments['taxonomy'] ) : '';

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new \WP_Error( 'webo_mcp_post_not_found', 'Post not found' );
		}

		$read_check = self::require_read_post( $post_id );
		if ( is_wp_error( $read_check ) ) {
			return $read_check;
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
				'id'       => (int) $term->term_id,
				'name'     => (string) $term->name,
				'slug'     => (string) $term->slug,
				'taxonomy' => (string) $term->taxonomy,
			);
		}

		return array(
			'id'      => $post_id,
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
		$post_data  = array();
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
			'id'            => (int) $id,
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
		$read_check = self::require_read_post( $attachment_id );
		if ( is_wp_error( $read_check ) ) {
			return $read_check;
		}
		return array(
			'id'            => (int) $post->ID,
			'attachment_id' => (int) $post->ID,
			'title'         => $post->post_title,
			'alt_text'      => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'caption'       => (string) $post->post_excerpt,
			'url'           => wp_get_attachment_url( $post->ID ),
			'mime_type'     => $post->post_mime_type,
			'tool'          => 'webo/get-media',
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
		$edit_check = self::require_edit_post( $attachment_id );
		if ( is_wp_error( $edit_check ) ) {
			return $edit_check;
		}
		$updated = array();
		if ( array_key_exists( 'title', $arguments ) ) {
			wp_update_post(
				array(
					'ID'         => $attachment_id,
					'post_title' => sanitize_text_field( (string) $arguments['title'] ),
				)
			);
			$updated[] = 'title';
		}
		if ( array_key_exists( 'alt_text', $arguments ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $arguments['alt_text'] ) );
			$updated[] = 'alt_text';
		}
		if ( array_key_exists( 'caption', $arguments ) ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => sanitize_textarea_field( (string) $arguments['caption'] ),
				)
			);
			$updated[] = 'caption';
		}
		return array(
			'id'            => $attachment_id,
			'attachment_id' => $attachment_id,
			'updated'       => $updated,
			'tool'          => 'webo/update-media',
		);
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
		$delete_check = self::require_delete_post( $attachment_id );
		if ( is_wp_error( $delete_check ) ) {
			return $delete_check;
		}
		$result = wp_delete_attachment( $attachment_id, true );
		if ( ! $result ) {
			return new \WP_Error( 'webo_mcp_delete_failed', 'Failed to delete attachment' );
		}
		return array(
			'id'            => $attachment_id,
			'attachment_id' => $attachment_id,
			'deleted'       => true,
			'tool'          => 'webo/delete-media',
		);
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
			'id'           => (int) $comment->comment_ID,
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
			wp_insert_comment(
				array(
					'comment_post_ID'  => (int) $parent->comment_post_ID,
					'comment_parent'   => $comment_id,
					'comment_content'  => sanitize_textarea_field( (string) $arguments['reply'] ),
					'user_id'          => get_current_user_id(),
					'comment_author'   => wp_get_current_user()->display_name,
					'comment_approved' => '1',
				)
			);
		}
		return array(
			'id'         => $comment_id,
			'comment_id' => $comment_id,
			'updated'    => true,
			'tool'       => 'webo/update-comment',
		);
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
		return array(
			'id'         => $comment_id,
			'comment_id' => $comment_id,
			'deleted'    => true,
			'tool'       => 'webo/delete-comment',
		);
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
		$silent = ! empty( $arguments['silent'] );
		if ( '' === $plugin ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'plugin (plugin file path) is required' );
		}
		if ( ! in_array( $action, array( 'activate', 'deactivate' ), true ) ) {
			return new \WP_Error( 'webo_mcp_invalid_action', 'action must be activate or deactivate' );
		}

		$target_blog_id = self::resolve_plugin_target_blog_id( $arguments );
		if ( is_wp_error( $target_blog_id ) ) {
			return $target_blog_id;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$result = self::run_in_plugin_target_blog(
			$target_blog_id,
			static function () use ( $action, $plugin, $silent ) {
				if ( 'activate' === $action ) {
					return activate_plugin( $plugin, '', false, $silent );
				}

				deactivate_plugins( $plugin, false, false );
				return null;
			}
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'plugin'         => $plugin,
			'action'         => $action,
			'silent'         => $silent,
			'site_id'        => $target_blog_id,
			'blog_id'        => $target_blog_id,
			'targeted_child' => $target_blog_id > 0,
			'success'        => true,
			'tool'           => 'webo/toggle-plugin',
		);
	}

	/**
	 * List installed themes and active status.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function list_themes( array $arguments ) {
		$include_inactive = ! empty( $arguments['include_inactive'] );
		$active_theme     = wp_get_theme();
		$themes           = wp_get_themes();
		$items            = array();

		foreach ( $themes as $theme ) {
			if ( ! $theme instanceof \WP_Theme || ! $theme->exists() ) {
				continue;
			}

			$is_active = $active_theme instanceof \WP_Theme && $active_theme->get_stylesheet() === $theme->get_stylesheet();
			if ( ! $include_inactive && ! $is_active ) {
				continue;
			}

			$items[] = array(
				'stylesheet' => (string) $theme->get_stylesheet(),
				'template'   => (string) $theme->get_template(),
				'name'       => (string) $theme->get( 'Name' ),
				'version'    => (string) $theme->get( 'Version' ),
				'author'     => wp_strip_all_tags( (string) $theme->get( 'Author' ) ),
				'active'     => $is_active,
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
			'tool'         => 'webo/list-themes',
		);
	}

	/**
	 * Switch the active theme by stylesheet slug.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function switch_theme_tool( array $arguments ) {
		$stylesheet = isset( $arguments['stylesheet'] ) ? sanitize_text_field( (string) $arguments['stylesheet'] ) : '';
		if ( '' === $stylesheet ) {
			return new \WP_Error( 'webo_mcp_missing_argument', 'stylesheet is required' );
		}

		$before = wp_get_theme();
		$theme  = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new \WP_Error( 'webo_mcp_theme_not_found', 'Theme not found for stylesheet slug: ' . $stylesheet );
		}

		if ( $before instanceof \WP_Theme && $before->get_stylesheet() === $theme->get_stylesheet() ) {
			return array(
				'already_active' => true,
				'theme'          => array(
					'stylesheet' => (string) $theme->get_stylesheet(),
					'template'   => (string) $theme->get_template(),
					'name'       => (string) $theme->get( 'Name' ),
					'version'    => (string) $theme->get( 'Version' ),
				),
				'tool'           => 'webo/switch-theme',
			);
		}

		switch_theme( $theme->get_stylesheet() );
		$after = wp_get_theme();

		if ( ! $after instanceof \WP_Theme || $after->get_stylesheet() !== $theme->get_stylesheet() ) {
			return new \WP_Error( 'webo_mcp_switch_theme_failed', 'switch_theme() did not activate the requested theme' );
		}

		return array(
			'success' => true,
			'before'  => array(
				'stylesheet' => $before instanceof \WP_Theme ? (string) $before->get_stylesheet() : '',
				'template'   => $before instanceof \WP_Theme ? (string) $before->get_template() : '',
				'name'       => $before instanceof \WP_Theme ? (string) $before->get( 'Name' ) : '',
			),
			'after'   => array(
				'stylesheet' => (string) $after->get_stylesheet(),
				'template'   => (string) $after->get_template(),
				'name'       => (string) $after->get( 'Name' ),
				'version'    => (string) $after->get( 'Version' ),
			),
			'tool'    => 'webo/switch-theme',
		);
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
			$menu              = wp_get_nav_menu_object( $menu_id );
			$label             = isset( $registered[ $slug ] ) ? (string) $registered[ $slug ] : $slug;
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
			$slug                    = sanitize_key( (string) $slug );
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

		$admin_nav_menu_file = trailingslashit( ABSPATH ) . 'wp-admin/includes/nav-menu.php';
		if ( is_readable( $admin_nav_menu_file ) ) {
			require_once $admin_nav_menu_file;
		}

		if ( ! function_exists( 'wp_create_nav_menu' ) ) {
			$includes_nav_menu_file = trailingslashit( ABSPATH ) . 'wp-includes/nav-menu.php';
			if ( is_readable( $includes_nav_menu_file ) ) {
				require_once $includes_nav_menu_file;
			}
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
	 * @param string                $requested  Requested slug (will be sanitize_key internally).
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
		$menu_id                = isset( $arguments['menu_id'] ) ? (int) $arguments['menu_id'] : 0;
		$by_name                = isset( $arguments['menu_name'] ) ? sanitize_text_field( trim( (string) $arguments['menu_name'] ) ) : '';
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
			$menu_id                = (int) $resolved_menu->term_id;
			$assigned_via_menu_name = true;
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
			$db_id   = (int) $row->ID;
			$items[] = array(
				'db_id'        => $db_id,
				'title'        => isset( $row->title ) && is_string( $row->title ) ? $row->title : (string) $row->post_title,
				'menu_order'   => (int) $row->menu_order,
				'parent_db_id' => (int) get_post_meta( $db_id, '_menu_item_menu_item_parent', true ),
				'object_id'    => (int) get_post_meta( $db_id, '_menu_item_object_id', true ),
				'object'       => (string) get_post_meta( $db_id, '_menu_item_object', true ),
				'type'         => (string) get_post_meta( $db_id, '_menu_item_type', true ),
				'url'          => isset( $row->url ) && is_string( $row->url ) ? $row->url : '',
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
		if ( ! self::ensure_nav_menu_api_loaded() ) {
			return new \WP_Error(
				'webo_mcp_nav_menu_api_unavailable',
				__( 'The navigation menu API could not be loaded. Check that WordPress core files are intact.', 'webo-mcp' )
			);
		}

		$menu_id    = isset( $arguments['menu_id'] ) ? (int) $arguments['menu_id'] : 0;
		$post_id    = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
		$menu_order = isset( $arguments['menu_order'] ) ? (int) $arguments['menu_order'] : 0;
		$post_type  = isset( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : '';
		$parent_id  = isset( $arguments['parent_db_id'] ) ? (int) $arguments['parent_db_id'] : 0;
		$title      = isset( $arguments['menu_item_title'] ) ? sanitize_text_field( (string) $arguments['menu_item_title'] ) : '';

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
			'menu-item-object-id' => $post_id,
			'menu-item-object'    => $post_type,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-position'  => $menu_order,
			'menu-item-parent-id' => max( 0, $parent_id ),
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
		if ( ! self::ensure_nav_menu_api_loaded() ) {
			return new \WP_Error(
				'webo_mcp_nav_menu_api_unavailable',
				__( 'The navigation menu API could not be loaded. Check that WordPress core files are intact.', 'webo-mcp' )
			);
		}

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
		$post_id       = self::resolve_post_id_argument( $arguments );
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
		$read_attachment_check = self::require_read_post( $attachment_id );
		if ( is_wp_error( $read_attachment_check ) ) {
			return $read_attachment_check;
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
