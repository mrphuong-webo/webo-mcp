<?php

namespace WeboMCP\Core\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WordPressTools {

	/**
	 * Returns basic site diagnostics information.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function get_site_info( array $arguments ) {
		unset( $arguments );

		return array(
			'name'       => get_bloginfo( 'name' ),
			'description'=> get_bloginfo( 'description' ),
			'url'        => home_url( '/' ),
			'language'   => get_bloginfo( 'language' ),
			'version'    => get_bloginfo( 'version' ),
			'timezone'   => wp_timezone_string(),
			'tool'       => 'webo/get-site-info',
		);
	}

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

		return array(
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
}
