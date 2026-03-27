<?php
/**
 * Shared helpers for MCP Rank Math (WEBO) bridge.
 *
 * @package WeboMCP_Rank_Math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether Rank Math SEO is loaded.
 */
function webo_mcp_rank_math_active(): bool {
	return class_exists( '\RankMath\Helper' ) || function_exists( 'rank_math' );
}

/**
 * @return bool
 */
function webo_mcp_rank_math_table_exists( string $table ): bool {
	static $cache = array();
	if ( isset( $cache[ $table ] ) ) {
		return $cache[ $table ];
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema probe.
	$exists               = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
	$cache[ $table ] = $exists;
	return $exists;
}

/**
 * @return array{0: string, 1: string}
 */
function webo_mcp_rank_math_option_like_patterns(): array {
	return array( 'rank_math_%', 'rank-math-%' );
}

/**
 * @param string $name Option name.
 */
function webo_mcp_rank_math_is_allowed_option_name( string $name ): bool {
	$name = trim( $name );
	if ( '' === $name ) {
		return false;
	}
	return ( 0 === strpos( $name, 'rank_math_' ) || 0 === strpos( $name, 'rank-math-' ) );
}

/**
 * @return array<string, mixed>
 */
function webo_mcp_rank_math_get_option_array( string $name ): array {
	$value = get_option( $name, array() );
	return is_array( $value ) ? $value : array();
}

/**
 * @return array<string, mixed>
 */
function webo_mcp_rank_math_get_titles_settings(): array {
	return webo_mcp_rank_math_get_option_array( 'rank-math-options-titles' );
}

/**
 * @return array<string, mixed>
 */
function webo_mcp_rank_math_get_general_settings(): array {
	return webo_mcp_rank_math_get_option_array( 'rank-math-options-general' );
}

/**
 * @return array<string, mixed>
 */
function webo_mcp_rank_math_get_sitemap_settings(): array {
	return webo_mcp_rank_math_get_option_array( 'rank-math-options-sitemap' );
}

/**
 * @return list<string>
 */
function webo_mcp_rank_math_social_same_as_from_titles( array $titles ): array {
	$urls = array();
	if ( ! empty( $titles['social_url_facebook'] ) ) {
		$urls[] = esc_url_raw( (string) $titles['social_url_facebook'] );
	}
	$tw = isset( $titles['twitter_author_names'] ) ? trim( (string) $titles['twitter_author_names'] ) : '';
	if ( '' !== $tw ) {
		$h = ltrim( $tw, '@' );
		$urls[] = 'https://twitter.com/' . rawurlencode( $h );
	}
	if ( ! empty( $titles['social_additional_profiles'] ) ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $titles['social_additional_profiles'] );
		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				if ( '' !== $line && wp_http_validate_url( $line ) ) {
					$urls[] = esc_url_raw( $line );
				}
			}
		}
	}
	$urls = array_values( array_filter( array_unique( $urls ) ) );
	return $urls;
}

/**
 * @param mixed $profiles string|list<string>
 */
function webo_mcp_rank_math_normalize_additional_profiles( $profiles ): string {
	if ( is_array( $profiles ) ) {
		$lines = array_map( 'sanitize_text_field', array_map( 'strval', $profiles ) );
		return implode( "\n", array_filter( $lines ) );
	}
	return sanitize_textarea_field( (string) $profiles );
}

/**
 * @return array<int, array<string, mixed>>
 */
function webo_mcp_rank_math_get_module_records(): array {
	if ( ! function_exists( 'rank_math' ) || ! isset( rank_math()->manager ) || ! is_object( rank_math()->manager ) ) {
		return array();
	}
	$records = array();
	foreach ( rank_math()->manager->modules as $id => $module ) {
		if ( ! is_object( $module ) || ! method_exists( $module, 'get' ) ) {
			continue;
		}
		$records[] = array(
			'id'          => (string) $id,
			'title'       => (string) $module->get( 'title' ),
			'description' => wp_strip_all_tags( (string) $module->get( 'desc' ) ),
			'settings_url' => (string) $module->get( 'settings' ),
			'active'      => method_exists( $module, 'is_active' ) ? (bool) $module->is_active() : false,
			'disabled'    => method_exists( $module, 'is_disabled' ) ? (bool) $module->is_disabled() : false,
			'hidden'      => method_exists( $module, 'is_hidden' ) ? (bool) $module->is_hidden() : false,
			'upgradeable' => method_exists( $module, 'is_upgradeable' ) ? (bool) $module->is_upgradeable() : false,
			'pro'         => method_exists( $module, 'is_pro_module' ) ? (bool) $module->is_pro_module() : false,
		);
	}
	return $records;
}

/**
 * @return array<string, mixed>
 */
function webo_mcp_rank_math_get_rewrite_rules_array(): array {
	global $wp_rewrite;
	if ( ! isset( $wp_rewrite ) || ! is_object( $wp_rewrite ) ) {
		return array();
	}
	$rules = $wp_rewrite->wp_rewrite_rules();
	return is_array( $rules ) ? $rules : array();
}

/**
 * @return array<string, mixed>
 */
function webo_mcp_rank_math_get_rewrite_status( string $endpoint = 'llms.txt', string $custom_regex = '' ): array {
	$rules = webo_mcp_rank_math_get_rewrite_rules_array();
	if ( empty( $rules ) ) {
		return array(
			'endpoint'            => $endpoint,
			'searched_regex'      => $custom_regex,
			'rule_present'        => false,
			'matched_regex'       => '',
			'rule_target'         => '',
			'permalink_structure' => (string) get_option( 'permalink_structure', '' ),
			'message'             => 'WordPress rewrite rules are unavailable.',
		);
	}
	$search_regex = '';
	$target_hint  = '';
	if ( 'llms.txt' === $endpoint ) {
		$search_regex = '^llms\.txt$';
		$target_hint  = 'llms_txt=1';
	} elseif ( 'sitemap_index.xml' === $endpoint ) {
		$search_regex = '^sitemap_index\.xml$';
		$target_hint  = 'sitemap=1';
	} elseif ( 'custom' === $endpoint ) {
		$search_regex = $custom_regex;
	}
	$matched_regex  = '';
	$matched_target = '';
	if ( '' !== $search_regex && isset( $rules[ $search_regex ] ) ) {
		$matched_regex  = $search_regex;
		$matched_target = (string) $rules[ $search_regex ];
	}
	if ( '' === $matched_regex && '' !== $target_hint ) {
		foreach ( $rules as $regex => $target ) {
			if ( false !== strpos( (string) $target, $target_hint ) ) {
				$matched_regex  = (string) $regex;
				$matched_target = (string) $target;
				break;
			}
		}
	}
	return array(
		'endpoint'            => $endpoint,
		'searched_regex'      => $search_regex,
		'rule_present'        => '' !== $matched_regex,
		'matched_regex'       => $matched_regex,
		'rule_target'         => $matched_target,
		'permalink_structure' => (string) get_option( 'permalink_structure', '' ),
		'message'             => '' !== $matched_regex ? 'Rewrite rule found.' : 'Rewrite rule not found.',
	);
}

/**
 * @return array<string, mixed>
 */
function webo_mcp_rank_math_fetch_local_preview( string $path, int $max_lines = 20 ): array {
	$url = home_url( $path );
	if ( ! function_exists( 'wp_remote_get' ) ) {
		return array(
			'url'     => $url,
			'success' => false,
			'message' => 'HTTP unavailable.',
		);
	}
	$res = wp_remote_get(
		$url,
		array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'text/plain,text/html,*/*' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return array(
			'url'     => $url,
			'success' => false,
			'message' => $res->get_error_message(),
		);
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = (string) wp_remote_retrieve_body( $res );
	$lines = explode( "\n", $body );
	$lines = array_slice( $lines, 0, max( 1, min( 200, $max_lines ) ) );
	return array(
		'url'          => $url,
		'success'      => $code >= 200 && $code < 400,
		'status_code'  => $code,
		'lines'        => $lines,
		'message'      => 'Fetched preview.',
	);
}

/**
 * @return array<string, mixed>
 */
function webo_mcp_rank_math_schema_status_data(): array {
	$titles = webo_mcp_rank_math_get_titles_settings();
	$type   = isset( $titles['knowledgegraph_type'] ) ? sanitize_key( (string) $titles['knowledgegraph_type'] ) : 'person';
	$name   = isset( $titles['knowledgegraph_name'] ) && '' !== (string) $titles['knowledgegraph_name'] ? (string) $titles['knowledgegraph_name'] : get_bloginfo( 'name' );
	$website = isset( $titles['website_name'] ) && '' !== (string) $titles['website_name'] ? (string) $titles['website_name'] : $name;
	$profiles = webo_mcp_rank_math_social_same_as_from_titles( $titles );

	return array(
		'configured_knowledgegraph_type' => $type,
		'effective_publisher_type'       => 'company' === $type ? 'Organization' : 'Person',
		'effective_publisher_id'         => home_url( 'company' === $type ? '/#organization' : '/#person' ),
		'publisher_name'               => $name,
		'website_name'                 => $website,
		'publisher_url'                => isset( $titles['url'] ) ? (string) $titles['url'] : '',
		'organization_description'     => isset( $titles['organization_description'] ) ? (string) $titles['organization_description'] : '',
		'logo_url'                     => isset( $titles['knowledgegraph_logo'] ) ? (string) $titles['knowledgegraph_logo'] : '',
		'logo_id'                      => isset( $titles['knowledgegraph_logo_id'] ) ? (int) $titles['knowledgegraph_logo_id'] : 0,
		'local_seo_enabled'            => ! empty( $titles['local_seo'] ),
		'email'                        => isset( $titles['email'] ) ? (string) $titles['email'] : '',
		'phone'                        => isset( $titles['phone'] ) ? (string) $titles['phone'] : '',
		'phone_numbers'                => isset( $titles['phone_numbers'] ) && is_array( $titles['phone_numbers'] ) ? $titles['phone_numbers'] : array(),
		'address'                      => isset( $titles['local_address'] ) && is_array( $titles['local_address'] ) ? $titles['local_address'] : array(),
		'address_format'               => isset( $titles['local_address_format'] ) ? (string) $titles['local_address_format'] : '',
		'social_profiles'              => $profiles,
		'social_facebook'              => isset( $titles['social_url_facebook'] ) ? (string) $titles['social_url_facebook'] : '',
		'twitter_handle'               => isset( $titles['twitter_author_names'] ) ? ltrim( (string) $titles['twitter_author_names'], '@' ) : '',
	);
}

/**
 * @return array<string, mixed>
 */
function webo_mcp_rank_math_llms_status_data( int $preview_lines = 12 ): array {
	$general = webo_mcp_rank_math_get_general_settings();
	$rewrite = webo_mcp_rank_math_get_rewrite_status( 'llms.txt' );
	$live    = webo_mcp_rank_math_fetch_local_preview( '/llms.txt', $preview_lines );
	$pt      = isset( $general['llms_post_types'] ) && is_array( $general['llms_post_types'] ) ? array_values( $general['llms_post_types'] ) : array();
	$tax     = isset( $general['llms_taxonomies'] ) && is_array( $general['llms_taxonomies'] ) ? array_values( $general['llms_taxonomies'] ) : array();

	return array(
		'module_active'     => class_exists( '\RankMath\Helper', false ) && \RankMath\Helper::is_module_active( 'llms-txt' ),
		'route_url'          => home_url( '/llms.txt' ),
		'rewrite'            => $rewrite,
		'post_types'         => $pt,
		'taxonomies'         => $tax,
		'limit'              => isset( $general['llms_limit'] ) ? (int) $general['llms_limit'] : 100,
		'extra_content'      => isset( $general['llms_extra_content'] ) ? (string) $general['llms_extra_content'] : '',
		'header_name'        => get_bloginfo( 'name' ),
		'header_description' => get_bloginfo( 'description' ),
		'effective_heading'  => get_bloginfo( 'name' ) . ': ' . get_bloginfo( 'description' ),
		'sitemap_active'     => class_exists( '\RankMath\Helper', false ) && \RankMath\Helper::is_module_active( 'sitemap' ),
		'live_preview'       => $live,
	);
}

/**
 * @return array{post_types: list<string>, taxonomies: list<string>}
 */
function webo_mcp_rank_math_sitemap_enabled_items(): array {
	$sitemap    = webo_mcp_rank_math_get_sitemap_settings();
	$post_types = array();
	$taxonomies = array();
	foreach ( $sitemap as $key => $value ) {
		if ( 'on' !== $value && true !== $value ) {
			continue;
		}
		$key = (string) $key;
		$len = strlen( $key );
		if ( $len > 11 && 0 === strpos( $key, 'pt_' ) && '_sitemap' === substr( $key, -8 ) ) {
			$post_types[] = substr( $key, 3, $len - 11 );
			continue;
		}
		if ( $len > 12 && 0 === strpos( $key, 'tax_' ) && '_sitemap' === substr( $key, -8 ) ) {
			$taxonomies[] = substr( $key, 4, $len - 12 );
		}
	}
	sort( $post_types );
	sort( $taxonomies );
	return array(
		'post_types' => $post_types,
		'taxonomies' => $taxonomies,
	);
}

/**
 * @param string $action access|edit
 * @return array<string, mixed>
 */
function webo_mcp_rank_math_get_post_or_error( int $post_id, string $action ): array {
	$post = get_post( $post_id );
	if ( ! $post instanceof \WP_Post ) {
		return array(
			'success' => false,
			'message' => 'Post not found with ID: ' . $post_id,
		);
	}
	$cap = ( 'edit' === $action ) ? 'edit_post' : 'edit_post';
	if ( ! current_user_can( $cap, $post_id ) ) {
		return array(
			'success' => false,
			'message' => 'You do not have permission to ' . $action . ' this post.',
		);
	}
	return array(
		'success' => true,
		'post'    => $post,
	);
}

/**
 * @return list<int>
 */
function webo_mcp_rank_math_allowed_redirection_headers(): array {
	return array( 301, 302, 307, 308, 410, 451 );
}

/**
 * @return list<string>
 */
function webo_mcp_rank_math_allowed_redirection_comparisons(): array {
	return array( 'exact', 'contains', 'start', 'end', 'regex' );
}

/**
 * @return list<string>
 */
function webo_mcp_rank_math_allowed_redirection_statuses(): array {
	return array( 'active', 'inactive' );
}
