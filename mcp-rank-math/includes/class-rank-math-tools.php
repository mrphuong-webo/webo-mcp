<?php
/**
 * WEBO MCP tools for Rank Math SEO (names align with rankmath/* abilities ecosystem).
 *
 * @package WeboMCP_Rank_Math
 */

namespace WeboMCP\RankMath;

use WeboMCP\Core\Registry\ToolRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers rankmath/* tools on webo_mcp_register_tools.
 */
final class Rank_Math_Tools {

	/**
	 * Bootstrap hook.
	 */
	public static function init(): void {
		add_action( 'webo_mcp_register_tools', array( __CLASS__, 'register_tools' ), 5 );
	}

	/**
	 * @return void
	 */
	public static function register_tools(): void {
		if ( ! class_exists( ToolRegistry::class ) || ! \webo_mcp_rank_math_active() ) {
			return;
		}

		$defs = self::tool_definitions();
		foreach ( $defs as $def ) {
			$reg = ToolRegistry::register( $def );
			if ( is_wp_error( $reg ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'mcp-rank-math: failed to register ' . ( isset( $def['name'] ) ? $def['name'] : 'tool' ) . ' — ' . $reg->get_error_message() );
			}
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function tool_definitions(): array {
		return array(
			array(
				'name'        => 'rankmath/list-options',
				'description' => 'List Rank Math option names in wp_options (rank_math_* and rank-math-*).',
				'category'    => 'seo',
				'arguments'   => array(
					'limit'  => array( 'type' => 'integer', 'required' => false, 'default' => 200, 'min' => 1, 'max' => 500 ),
					'offset' => array( 'type' => 'integer', 'required' => false, 'default' => 0, 'min' => 0 ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'list_options' ),
			),
			array(
				'name'        => 'rankmath/get-options',
				'description' => 'Get Rank Math option values by name (allowed prefixes only).',
				'category'    => 'seo',
				'arguments'   => array(
					'options' => array( 'type' => 'array', 'required' => true ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'get_options' ),
			),
			array(
				'name'        => 'rankmath/update-options',
				'description' => 'Update Rank Math options by name (allowed prefixes only). Values may be strings, numbers, or arrays.',
				'category'    => 'seo',
				'arguments'   => array(
					'options' => array( 'type' => 'array', 'required' => true ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'update_options' ),
			),
			array(
				'name'        => 'rankmath/get-schema-status',
				'description' => 'Global publisher/schema summary from Rank Math titles options.',
				'category'    => 'seo',
				'arguments'   => array(),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'get_schema_status' ),
			),
			array(
				'name'        => 'rankmath/list-modules',
				'description' => 'List Rank Math modules and active/disabled state.',
				'category'    => 'seo',
				'arguments'   => array(),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'list_modules' ),
			),
			array(
				'name'        => 'rankmath/update-modules',
				'description' => 'Enable or disable Rank Math module slugs (enable[] / disable[]).',
				'category'    => 'seo',
				'arguments'   => array(
					'enable'  => array( 'type' => 'array', 'required' => false, 'default' => array() ),
					'disable' => array( 'type' => 'array', 'required' => false, 'default' => array() ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'update_modules' ),
			),
			array(
				'name'        => 'rankmath/get-rewrite-status',
				'description' => 'Inspect rewrite rules for llms.txt, sitemap_index.xml, or custom regex.',
				'category'    => 'seo',
				'arguments'   => array(
					'endpoint'     => array( 'type' => 'string', 'required' => false, 'default' => 'llms.txt' ),
					'custom_regex' => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'get_rewrite_status' ),
			),
			array(
				'name'        => 'rankmath/get-llms-status',
				'description' => 'Rank Math llms.txt module status, settings, rewrite, live preview snippet.',
				'category'    => 'seo',
				'arguments'   => array(
					'preview_lines' => array( 'type' => 'integer', 'required' => false, 'default' => 12, 'min' => 1, 'max' => 50 ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'get_llms_status' ),
			),
			array(
				'name'        => 'rankmath/preview-llms',
				'description' => 'Fetch first lines of live /llms.txt output.',
				'category'    => 'seo',
				'arguments'   => array(
					'max_lines' => array( 'type' => 'integer', 'required' => false, 'default' => 40, 'min' => 1, 'max' => 200 ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'preview_llms' ),
			),
			array(
				'name'        => 'rankmath/refresh-llms-route',
				'description' => 'Verify llms.txt rewrite rule; optionally flush rewrite rules.',
				'category'    => 'seo',
				'arguments'   => array(
					'force_flush' => array( 'type' => 'boolean', 'required' => false, 'default' => false ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'refresh_llms_route' ),
			),
			array(
				'name'        => 'rankmath/update-publisher-profile',
				'description' => 'Update global Rank Math publisher fields. Pass only keys to change (rank-math-options-titles). knowledgegraph_type: company|person.',
				'category'    => 'seo',
				'arguments'   => array(
					'knowledgegraph_type'      => array( 'type' => 'string', 'required' => false ),
					'knowledgegraph_name'      => array( 'type' => 'string', 'required' => false ),
					'website_name'             => array( 'type' => 'string', 'required' => false ),
					'organization_description' => array( 'type' => 'string', 'required' => false ),
					'url'                      => array( 'type' => 'string', 'required' => false ),
					'knowledgegraph_logo'      => array( 'type' => 'string', 'required' => false ),
					'knowledgegraph_logo_id'   => array( 'type' => 'integer', 'required' => false, 'min' => 0 ),
					'email'                    => array( 'type' => 'string', 'required' => false ),
					'phone'                    => array( 'type' => 'string', 'required' => false ),
					'local_address_format'     => array( 'type' => 'string', 'required' => false ),
					'local_seo'                => array( 'type' => 'boolean', 'required' => false ),
					'local_address'            => array( 'type' => 'array', 'required' => false ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'update_publisher_profile' ),
			),
			array(
				'name'        => 'rankmath/get-social-profiles',
				'description' => 'Read global social URLs / handles used for sameAs.',
				'category'    => 'seo',
				'arguments'   => array(),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'get_social_profiles' ),
			),
			array(
				'name'        => 'rankmath/update-social-profiles',
				'description' => 'Update Facebook URL, Twitter handle, additional profile URLs (array of URLs or newline string).',
				'category'    => 'seo',
				'arguments'   => array(
					'facebook_url'        => array( 'type' => 'string', 'required' => false ),
					'twitter_handle'      => array( 'type' => 'string', 'required' => false ),
					'additional_profiles' => array( 'type' => 'array', 'required' => false ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'update_social_profiles' ),
			),
			array(
				'name'        => 'rankmath/get-sitemap-status',
				'description' => 'Sitemap module state, enabled types, rewrite hint, live index preview.',
				'category'    => 'seo',
				'arguments'   => array(),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'get_sitemap_status' ),
			),
			array(
				'name'        => 'rankmath/get-meta',
				'description' => 'Read Rank Math SEO meta for a post ID (id). Aliases: title/description/keyword handled in update-meta.',
				'category'    => 'seo',
				'arguments'   => array(
					'id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				),
				'permission'  => 'edit_posts',
				'callback'    => array( __CLASS__, 'get_meta' ),
			),
			array(
				'name'        => 'rankmath/update-meta',
				'description' => 'Update Rank Math SEO meta. Pass only fields to change. Aliases: title→seo_title, description→seo_description, keyword→focus_keyword. Optional: robots[], canonical_url (empty string removes), is_pillar, is_cornerstone.',
				'category'    => 'seo',
				'arguments'   => array(
					'id'              => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
					'seo_title'       => array( 'type' => 'string', 'required' => false ),
					'title'           => array( 'type' => 'string', 'required' => false ),
					'seo_description' => array( 'type' => 'string', 'required' => false ),
					'description'     => array( 'type' => 'string', 'required' => false ),
					'focus_keyword'   => array( 'type' => 'string', 'required' => false ),
					'keyword'         => array( 'type' => 'string', 'required' => false ),
					'robots'          => array( 'type' => 'array', 'required' => false ),
					'canonical_url'   => array( 'type' => 'string', 'required' => false ),
					'is_pillar'       => array( 'type' => 'boolean', 'required' => false ),
					'is_cornerstone'  => array( 'type' => 'boolean', 'required' => false ),
				),
				'permission'  => 'edit_posts',
				'callback'    => array( __CLASS__, 'update_meta' ),
			),
			array(
				'name'        => 'rankmath/bulk-get-meta',
				'description' => 'Audit SEO meta across posts/pages (publish). Optional missing_desc, search.',
				'category'    => 'seo',
				'arguments'   => array(
					'post_type'    => array( 'type' => 'string', 'required' => false, 'default' => 'any' ),
					'per_page'     => array( 'type' => 'integer', 'required' => false, 'default' => 20, 'min' => 1, 'max' => 100 ),
					'page'         => array( 'type' => 'integer', 'required' => false, 'default' => 1, 'min' => 1 ),
					'missing_desc' => array( 'type' => 'boolean', 'required' => false, 'default' => false ),
					'search'       => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				),
				'permission'  => 'edit_posts',
				'callback'    => array( __CLASS__, 'bulk_get_meta' ),
			),
			array(
				'name'        => 'rankmath/list-404-logs',
				'description' => 'List Rank Math 404 log rows (newest first).',
				'category'    => 'seo',
				'arguments'   => array(
					'per_page' => array( 'type' => 'integer', 'required' => false, 'default' => 50, 'min' => 1, 'max' => 200 ),
					'page'     => array( 'type' => 'integer', 'required' => false, 'default' => 1, 'min' => 1 ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'list_404_logs' ),
			),
			array(
				'name'        => 'rankmath/delete-404-logs',
				'description' => 'Delete 404 log entries by numeric ids.',
				'category'    => 'seo',
				'arguments'   => array(
					'ids' => array( 'type' => 'array', 'required' => true ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'delete_404_logs' ),
			),
			array(
				'name'        => 'rankmath/clear-404-logs',
				'description' => 'Delete all 404 logs (confirm true required).',
				'category'    => 'seo',
				'arguments'   => array(
					'confirm' => array( 'type' => 'boolean', 'required' => true ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'clear_404_logs' ),
			),
			array(
				'name'        => 'rankmath/list-redirections',
				'description' => 'List Rank Math redirections (DB table).',
				'category'    => 'seo',
				'arguments'   => array(
					'per_page' => array( 'type' => 'integer', 'required' => false, 'default' => 50, 'min' => 1, 'max' => 200 ),
					'page'     => array( 'type' => 'integer', 'required' => false, 'default' => 1, 'min' => 1 ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'list_redirections' ),
			),
			array(
				'name'        => 'rankmath/create-redirection',
				'description' => 'Create redirection via Rank Math API: sources[{pattern, comparison, ignore_case}], destination, header_code, status.',
				'category'    => 'seo',
				'arguments'   => array(
					'sources'     => array( 'type' => 'array', 'required' => true ),
					'destination' => array( 'type' => 'string', 'required' => false, 'default' => '' ),
					'header_code' => array( 'type' => 'integer', 'required' => false, 'default' => 301 ),
					'status'      => array( 'type' => 'string', 'required' => false, 'default' => 'active' ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'create_redirection' ),
			),
			array(
				'name'        => 'rankmath/delete-redirections',
				'description' => 'Delete redirections by id list.',
				'category'    => 'seo',
				'arguments'   => array(
					'ids' => array( 'type' => 'array', 'required' => true ),
				),
				'permission'  => 'manage_options',
				'callback'    => array( __CLASS__, 'delete_redirections' ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function list_options( array $a ): array {
		global $wpdb;
		$limit  = isset( $a['limit'] ) ? min( 500, max( 1, (int) $a['limit'] ) ) : 200;
		$offset = isset( $a['offset'] ) ? max( 0, (int) $a['offset'] ) : 0;
		$pairs  = \webo_mcp_rank_math_option_like_patterns();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s OR option_name LIKE %s ORDER BY option_name ASC LIMIT %d OFFSET %d',
				$pairs[0],
				$pairs[1],
				$limit,
				$offset
			),
			ARRAY_A
		);
		$options = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( isset( $row['option_name'] ) ) {
					$options[] = (string) $row['option_name'];
				}
			}
		}
		return array(
			'success' => true,
			'options' => $options,
			'count'   => count( $options ),
			'tool'    => 'rankmath/list-options',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function get_options( array $a ): array {
		$names = isset( $a['options'] ) && is_array( $a['options'] ) ? $a['options'] : array();
		if ( empty( $names ) ) {
			return array( 'success' => false, 'message' => 'No option names provided.', 'tool' => 'rankmath/get-options' );
		}
		$values = array();
		foreach ( $names as $name ) {
			$name = sanitize_text_field( (string) $name );
			if ( ! \webo_mcp_rank_math_is_allowed_option_name( $name ) ) {
				continue;
			}
			$values[ $name ] = get_option( $name, null );
		}
		return array( 'success' => true, 'options' => $values, 'tool' => 'rankmath/get-options' );
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function update_options( array $a ): array {
		$options = isset( $a['options'] ) && is_array( $a['options'] ) ? $a['options'] : array();
		if ( empty( $options ) ) {
			return array( 'success' => false, 'message' => 'No options provided.', 'tool' => 'rankmath/update-options' );
		}
		$updated = array();
		foreach ( $options as $name => $value ) {
			$name = sanitize_text_field( (string) $name );
			if ( ! \webo_mcp_rank_math_is_allowed_option_name( $name ) ) {
				continue;
			}
			update_option( $name, $value );
			$updated[] = $name;
		}
		return array(
			'success' => true,
			'updated' => $updated,
			'message' => 'Options updated.',
			'tool'    => 'rankmath/update-options',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function get_schema_status( array $a ): array {
		unset( $a );
		if ( ! \webo_mcp_rank_math_active() ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/get-schema-status' );
		}
		return array(
			'success' => true,
			'status'  => \webo_mcp_rank_math_schema_status_data(),
			'tool'    => 'rankmath/get-schema-status',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function list_modules( array $a ): array {
		unset( $a );
		if ( ! \webo_mcp_rank_math_active() ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/list-modules' );
		}
		$modules = \webo_mcp_rank_math_get_module_records();
		return array(
			'success' => true,
			'modules' => $modules,
			'count'   => count( $modules ),
			'tool'    => 'rankmath/list-modules',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function update_modules( array $a ): array {
		if ( ! \webo_mcp_rank_math_active() || ! class_exists( '\RankMath\Helper' ) ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/update-modules' );
		}
		$enable  = isset( $a['enable'] ) && is_array( $a['enable'] ) ? array_map( 'sanitize_key', $a['enable'] ) : array();
		$disable = isset( $a['disable'] ) && is_array( $a['disable'] ) ? array_map( 'sanitize_key', $a['disable'] ) : array();
		if ( empty( $enable ) && empty( $disable ) ) {
			return array( 'success' => false, 'message' => 'No module changes provided.', 'tool' => 'rankmath/update-modules' );
		}
		$changes = array();
		foreach ( $enable as $m ) {
			if ( '' !== $m ) {
				$changes[ $m ] = 'on';
			}
		}
		foreach ( $disable as $m ) {
			if ( '' !== $m ) {
				$changes[ $m ] = 'off';
			}
		}
		\RankMath\Helper::update_modules( $changes );
		$route = array_intersect( array_keys( $changes ), array( 'llms-txt', 'sitemap' ) );
		if ( ! empty( $route ) && function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
		if ( method_exists( '\RankMath\Helper', 'clear_cache' ) ) {
			\RankMath\Helper::clear_cache( 'mcp-rank-math-update-modules' );
		}
		return array(
			'success' => true,
			'updated' => array(
				'enable'  => array_values( $enable ),
				'disable' => array_values( $disable ),
			),
			'modules' => \webo_mcp_rank_math_get_module_records(),
			'message' => 'Rank Math modules updated.',
			'tool'    => 'rankmath/update-modules',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function get_rewrite_status( array $a ): array {
		$endpoint     = isset( $a['endpoint'] ) ? sanitize_text_field( (string) $a['endpoint'] ) : 'llms.txt';
		$custom_regex = isset( $a['custom_regex'] ) ? sanitize_text_field( (string) $a['custom_regex'] ) : '';
		if ( ! in_array( $endpoint, array( 'llms.txt', 'sitemap_index.xml', 'custom' ), true ) ) {
			$endpoint = 'llms.txt';
		}
		return array(
			'success' => true,
			'status'  => \webo_mcp_rank_math_get_rewrite_status( $endpoint, $custom_regex ),
			'tool'    => 'rankmath/get-rewrite-status',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function get_llms_status( array $a ): array {
		if ( ! \webo_mcp_rank_math_active() ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/get-llms-status' );
		}
		$lines = isset( $a['preview_lines'] ) ? max( 1, min( 50, (int) $a['preview_lines'] ) ) : 12;
		return array(
			'success' => true,
			'status'  => \webo_mcp_rank_math_llms_status_data( $lines ),
			'tool'    => 'rankmath/get-llms-status',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function preview_llms( array $a ): array {
		$max = isset( $a['max_lines'] ) ? max( 1, min( 200, (int) $a['max_lines'] ) ) : 40;
		return array(
			'success' => true,
			'preview' => \webo_mcp_rank_math_fetch_local_preview( '/llms.txt', $max ),
			'tool'    => 'rankmath/preview-llms',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function refresh_llms_route( array $a ): array {
		if ( ! \webo_mcp_rank_math_active() ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/refresh-llms-route' );
		}
		if ( ! function_exists( 'flush_rewrite_rules' ) ) {
			return array( 'success' => false, 'message' => 'Rewrite functions unavailable.', 'tool' => 'rankmath/refresh-llms-route' );
		}
		$force          = ! empty( $a['force_flush'] );
		$module_active  = class_exists( '\RankMath\Helper' ) && \RankMath\Helper::is_module_active( 'llms-txt' );
		$permalink      = (string) get_option( 'permalink_structure', '' );
		$before         = \webo_mcp_rank_math_get_rewrite_status( 'llms.txt' );
		$flushed        = false;
		if ( ! $module_active ) {
			return array(
				'success'             => false,
				'module_active'       => false,
				'permalink_structure' => $permalink,
				'before'              => $before,
				'after'               => $before,
				'flushed'             => false,
				'message'             => 'Rank Math llms-txt module is not active.',
				'tool'                => 'rankmath/refresh-llms-route',
			);
		}
		if ( $force || empty( $before['rule_present'] ) ) {
			flush_rewrite_rules( false );
			$flushed = true;
		}
		$after = \webo_mcp_rank_math_get_rewrite_status( 'llms.txt' );
		return array(
			'success'             => ! empty( $after['rule_present'] ),
			'module_active'       => true,
			'permalink_structure' => $permalink,
			'before'              => $before,
			'after'               => $after,
			'flushed'             => $flushed,
			'message'             => ! empty( $after['rule_present'] ) ? 'llms.txt rewrite rule is available.' : 'llms.txt rewrite rule still missing after refresh.',
			'tool'                => 'rankmath/refresh-llms-route',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function update_publisher_profile( array $a ): array {
		$titles  = \webo_mcp_rank_math_get_titles_settings();
		$updated = array();
		$scalars = array( 'knowledgegraph_type', 'knowledgegraph_name', 'website_name', 'organization_description', 'local_address_format', 'phone' );
		foreach ( $scalars as $field ) {
			if ( array_key_exists( $field, $a ) ) {
				$titles[ $field ] = sanitize_text_field( (string) $a[ $field ] );
				$updated[]        = $field;
			}
		}
		if ( array_key_exists( 'url', $a ) ) {
			$titles['url'] = esc_url_raw( (string) $a['url'] );
			$updated[]     = 'url';
		}
		if ( array_key_exists( 'knowledgegraph_logo', $a ) ) {
			$titles['knowledgegraph_logo'] = esc_url_raw( (string) $a['knowledgegraph_logo'] );
			$updated[]                     = 'knowledgegraph_logo';
		}
		if ( array_key_exists( 'knowledgegraph_logo_id', $a ) ) {
			$titles['knowledgegraph_logo_id'] = absint( $a['knowledgegraph_logo_id'] );
			$updated[]                        = 'knowledgegraph_logo_id';
		}
		if ( array_key_exists( 'email', $a ) ) {
			$titles['email'] = sanitize_email( (string) $a['email'] );
			$updated[]       = 'email';
		}
		if ( array_key_exists( 'local_seo', $a ) ) {
			$titles['local_seo'] = ! empty( $a['local_seo'] ) ? 'on' : 'off';
			$updated[]           = 'local_seo';
		}
		if ( array_key_exists( 'local_address', $a ) && is_array( $a['local_address'] ) ) {
			$address = array();
			foreach ( $a['local_address'] as $k => $v ) {
				$address[ sanitize_key( (string) $k ) ] = sanitize_text_field( (string) $v );
			}
			$titles['local_address'] = $address;
			$updated[]                = 'local_address';
		}
		if ( empty( $updated ) ) {
			return array( 'success' => false, 'message' => 'No publisher profile fields provided.', 'tool' => 'rankmath/update-publisher-profile' );
		}
		if ( isset( $titles['knowledgegraph_type'] ) && ! in_array( $titles['knowledgegraph_type'], array( 'company', 'person' ), true ) ) {
			unset( $titles['knowledgegraph_type'] );
		}
		update_option( 'rank-math-options-titles', $titles );
		return array(
			'success' => true,
			'updated' => $updated,
			'status'  => \webo_mcp_rank_math_schema_status_data(),
			'tool'    => 'rankmath/update-publisher-profile',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function get_social_profiles( array $a ): array {
		unset( $a );
		$titles = \webo_mcp_rank_math_get_titles_settings();
		return array(
			'success' => true,
			'profiles' => array(
				'facebook_url'        => isset( $titles['social_url_facebook'] ) ? (string) $titles['social_url_facebook'] : '',
				'twitter_handle'      => isset( $titles['twitter_author_names'] ) ? ltrim( (string) $titles['twitter_author_names'], '@' ) : '',
				'additional_profiles' => isset( $titles['social_additional_profiles'] ) ? preg_split( '/\r\n|\r|\n/', (string) $titles['social_additional_profiles'] ) : array(),
				'effective_same_as'   => \webo_mcp_rank_math_social_same_as_from_titles( $titles ),
			),
			'tool' => 'rankmath/get-social-profiles',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function update_social_profiles( array $a ): array {
		$titles  = \webo_mcp_rank_math_get_titles_settings();
		$updated = array();
		if ( array_key_exists( 'facebook_url', $a ) ) {
			$titles['social_url_facebook'] = esc_url_raw( (string) $a['facebook_url'] );
			$updated[]                     = 'facebook_url';
		}
		if ( array_key_exists( 'twitter_handle', $a ) ) {
			$titles['twitter_author_names'] = ltrim( sanitize_text_field( (string) $a['twitter_handle'] ), '@' );
			$updated[]                      = 'twitter_handle';
		}
		if ( array_key_exists( 'additional_profiles', $a ) ) {
			$ap                                     = $a['additional_profiles'];
			$titles['social_additional_profiles'] = is_array( $ap ) ? \webo_mcp_rank_math_normalize_additional_profiles( $ap ) : \webo_mcp_rank_math_normalize_additional_profiles( (string) $ap );
			$updated[]                            = 'additional_profiles';
		}
		if ( empty( $updated ) ) {
			return array( 'success' => false, 'message' => 'No social profile changes provided.', 'tool' => 'rankmath/update-social-profiles' );
		}
		update_option( 'rank-math-options-titles', $titles );
		return array(
			'success' => true,
			'updated' => $updated,
			'profiles' => array(
				'facebook_url'        => isset( $titles['social_url_facebook'] ) ? (string) $titles['social_url_facebook'] : '',
				'twitter_handle'      => isset( $titles['twitter_author_names'] ) ? ltrim( (string) $titles['twitter_author_names'], '@' ) : '',
				'additional_profiles' => isset( $titles['social_additional_profiles'] ) ? preg_split( '/\r\n|\r|\n/', (string) $titles['social_additional_profiles'] ) : array(),
				'effective_same_as'   => \webo_mcp_rank_math_social_same_as_from_titles( $titles ),
			),
			'tool' => 'rankmath/update-social-profiles',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function get_sitemap_status( array $a ): array {
		unset( $a );
		if ( ! \webo_mcp_rank_math_active() ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/get-sitemap-status' );
		}
		$sitemap = \webo_mcp_rank_math_get_sitemap_settings();
		$enabled = \webo_mcp_rank_math_sitemap_enabled_items();
		$preview = \webo_mcp_rank_math_fetch_local_preview( '/sitemap_index.xml', 8 );
		return array(
			'success' => true,
			'status'  => array(
				'module_active'     => class_exists( '\RankMath\Helper' ) && \RankMath\Helper::is_module_active( 'sitemap' ),
				'route_url'         => home_url( '/sitemap_index.xml' ),
				'rewrite'           => \webo_mcp_rank_math_get_rewrite_status( 'sitemap_index.xml' ),
				'include_images'    => ! empty( $sitemap['include_images'] ),
				'links_per_sitemap' => isset( $sitemap['links_per_sitemap'] ) ? (int) $sitemap['links_per_sitemap'] : 0,
				'post_types'        => $enabled['post_types'],
				'taxonomies'        => $enabled['taxonomies'],
				'live_preview'      => $preview,
			),
			'tool' => 'rankmath/get-sitemap-status',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function get_meta( array $a ): array {
		if ( ! \webo_mcp_rank_math_active() ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/get-meta' );
		}
		$post_id = (int) $a['id'];
		$r       = \webo_mcp_rank_math_get_post_or_error( $post_id, 'access' );
		if ( empty( $r['success'] ) ) {
			return array_merge( $r, array( 'tool' => 'rankmath/get-meta' ) );
		}
		/** @var \WP_Post $post */
		$post = $r['post'];
		$rob  = get_post_meta( $post_id, 'rank_math_robots', true );
		return array(
			'success'         => true,
			'id'              => $post_id,
			'title'           => $post->post_title,
			'url'             => get_permalink( $post_id ),
			'post_type'       => $post->post_type,
			'seo_title'       => (string) get_post_meta( $post_id, 'rank_math_title', true ),
			'seo_description' => (string) get_post_meta( $post_id, 'rank_math_description', true ),
			'focus_keyword'   => (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
			'robots'          => is_array( $rob ) ? $rob : array(),
			'canonical_url'   => (string) get_post_meta( $post_id, 'rank_math_canonical_url', true ),
			'is_pillar'       => 'on' === get_post_meta( $post_id, 'rank_math_pillar_content', true ),
			'is_cornerstone'  => 'on' === get_post_meta( $post_id, 'rank_math_cornerstone_content', true ),
			'tool'            => 'rankmath/get-meta',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function update_meta( array $a ): array {
		if ( ! \webo_mcp_rank_math_active() ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/update-meta' );
		}
		$post_id = (int) $a['id'];
		$r       = \webo_mcp_rank_math_get_post_or_error( $post_id, 'edit' );
		if ( empty( $r['success'] ) ) {
			return array_merge( $r, array( 'tool' => 'rankmath/update-meta' ) );
		}
		$updated = array();
		if ( array_key_exists( 'seo_title', $a ) || array_key_exists( 'title', $a ) ) {
			$v = array_key_exists( 'seo_title', $a ) ? (string) $a['seo_title'] : (string) $a['title'];
			update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( $v ) );
			$updated[] = 'seo_title';
		}
		if ( array_key_exists( 'seo_description', $a ) || array_key_exists( 'description', $a ) ) {
			$v = array_key_exists( 'seo_description', $a ) ? (string) $a['seo_description'] : (string) $a['description'];
			update_post_meta( $post_id, 'rank_math_description', sanitize_textarea_field( $v ) );
			$updated[] = 'seo_description';
		}
		if ( array_key_exists( 'focus_keyword', $a ) || array_key_exists( 'keyword', $a ) ) {
			$v = array_key_exists( 'focus_keyword', $a ) ? (string) $a['focus_keyword'] : (string) $a['keyword'];
			update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $v ) );
			$updated[] = 'focus_keyword';
		}
		if ( isset( $a['robots'] ) && is_array( $a['robots'] ) ) {
			$allowed = array( 'index', 'noindex', 'follow', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
			$rob     = array_values( array_intersect( array_map( 'sanitize_key', $a['robots'] ), $allowed ) );
			update_post_meta( $post_id, 'rank_math_robots', $rob );
			$updated[] = 'robots';
		}
		if ( array_key_exists( 'canonical_url', $a ) ) {
			$can = (string) $a['canonical_url'];
			if ( '' === $can ) {
				delete_post_meta( $post_id, 'rank_math_canonical_url' );
			} else {
				update_post_meta( $post_id, 'rank_math_canonical_url', esc_url_raw( $can ) );
			}
			$updated[] = 'canonical_url';
		}
		if ( array_key_exists( 'is_pillar', $a ) ) {
			if ( ! empty( $a['is_pillar'] ) ) {
				update_post_meta( $post_id, 'rank_math_pillar_content', 'on' );
			} else {
				delete_post_meta( $post_id, 'rank_math_pillar_content' );
			}
			$updated[] = 'is_pillar';
		}
		if ( array_key_exists( 'is_cornerstone', $a ) ) {
			if ( ! empty( $a['is_cornerstone'] ) ) {
				update_post_meta( $post_id, 'rank_math_cornerstone_content', 'on' );
			} else {
				delete_post_meta( $post_id, 'rank_math_cornerstone_content' );
			}
			$updated[] = 'is_cornerstone';
		}
		if ( empty( $updated ) ) {
			return array( 'success' => false, 'message' => 'No fields provided to update.', 'tool' => 'rankmath/update-meta' );
		}
		return array(
			'success' => true,
			'id'      => $post_id,
			'updated' => $updated,
			'url'     => get_permalink( $post_id ),
			'message' => 'Updated ' . count( $updated ) . ' field(s).',
			'tool'    => 'rankmath/update-meta',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function bulk_get_meta( array $a ): array {
		if ( ! \webo_mcp_rank_math_active() ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/bulk-get-meta' );
		}
		$per_page     = isset( $a['per_page'] ) ? min( 100, max( 1, (int) $a['per_page'] ) ) : 20;
		$page         = isset( $a['page'] ) ? max( 1, (int) $a['page'] ) : 1;
		$post_type_in = isset( $a['post_type'] ) ? sanitize_key( (string) $a['post_type'] ) : 'any';
		$post_type    = ( 'any' === $post_type_in ) ? array( 'post', 'page' ) : $post_type_in;
		$missing_desc = ! empty( $a['missing_desc'] );
		$fetch_limit  = $missing_desc ? $per_page * 5 : $per_page;
		$args         = array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => $fetch_limit,
			'paged'                  => $missing_desc ? 1 : $page,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		if ( ! empty( $a['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $a['search'] );
		}
		$q = new \WP_Query( $args );
		$items = array();
		foreach ( $q->posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$seo_desc = get_post_meta( $post->ID, 'rank_math_description', true );
			if ( $missing_desc && ! empty( $seo_desc ) ) {
				continue;
			}
			$items[] = array(
				'id'              => $post->ID,
				'title'           => $post->post_title,
				'post_type'       => $post->post_type,
				'url'             => get_permalink( $post->ID ),
				'seo_title'       => (string) get_post_meta( $post->ID, 'rank_math_title', true ),
				'seo_description' => (string) $seo_desc,
				'focus_keyword'   => (string) get_post_meta( $post->ID, 'rank_math_focus_keyword', true ),
			);
			if ( count( $items ) >= $per_page ) {
				break;
			}
		}
		$total = $missing_desc ? count( $items ) : (int) $q->found_posts;
		$pages = $missing_desc ? 1 : (int) $q->max_num_pages;
		return array(
			'success' => true,
			'items'   => $items,
			'total'   => $total,
			'page'    => $missing_desc ? 1 : $page,
			'pages'   => $pages,
			'tool'    => 'rankmath/bulk-get-meta',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function list_404_logs( array $a ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_404_logs';
		if ( ! \webo_mcp_rank_math_table_exists( $table ) ) {
			return array( 'success' => false, 'message' => '404 log table not found.', 'tool' => 'rankmath/list-404-logs' );
		}
		$per_page = min( 200, max( 1, (int) ( $a['per_page'] ?? 50 ) ) );
		$page     = max( 1, (int) ( $a['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $table ) . '` ORDER BY id DESC LIMIT %d OFFSET %d',
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );
		return array(
			'success' => true,
			'logs'    => is_array( $rows ) ? $rows : array(),
			'total'   => $total,
			'tool'    => 'rankmath/list-404-logs',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function delete_404_logs( array $a ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_404_logs';
		if ( ! \webo_mcp_rank_math_table_exists( $table ) ) {
			return array( 'success' => false, 'message' => '404 log table not found.', 'tool' => 'rankmath/delete-404-logs' );
		}
		$ids = isset( $a['ids'] ) && is_array( $a['ids'] ) ? array_filter( array_map( 'absint', $a['ids'] ) ) : array();
		if ( empty( $ids ) ) {
			return array( 'success' => false, 'message' => 'No valid IDs.', 'tool' => 'rankmath/delete-404-logs' );
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM `' . esc_sql( $table ) . '` WHERE id IN (' . $placeholders . ')', $ids ) );
		return array(
			'success' => true,
			'deleted' => (int) $deleted,
			'message' => 'Deleted ' . (int) $deleted . ' log(s).',
			'tool'    => 'rankmath/delete-404-logs',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function clear_404_logs( array $a ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_404_logs';
		if ( ! \webo_mcp_rank_math_table_exists( $table ) ) {
			return array( 'success' => false, 'message' => '404 log table not found.', 'tool' => 'rankmath/clear-404-logs' );
		}
		if ( empty( $a['confirm'] ) ) {
			return array( 'success' => false, 'message' => 'Confirmation required.', 'tool' => 'rankmath/clear-404-logs' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'DELETE FROM `' . esc_sql( $table ) . '`' );
		return array( 'success' => true, 'message' => 'Cleared 404 logs.', 'tool' => 'rankmath/clear-404-logs' );
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function list_redirections( array $a ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		if ( ! \webo_mcp_rank_math_table_exists( $table ) ) {
			return array( 'success' => false, 'message' => 'Redirections table not found.', 'tool' => 'rankmath/list-redirections' );
		}
		$per_page = min( 200, max( 1, (int) ( $a['per_page'] ?? 50 ) ) );
		$page     = max( 1, (int) ( $a['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $table ) . '` ORDER BY id DESC LIMIT %d OFFSET %d',
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );
		return array(
			'success'      => true,
			'redirections' => is_array( $rows ) ? $rows : array(),
			'total'        => $total,
			'tool'         => 'rankmath/list-redirections',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function create_redirection( array $a ): array {
		if ( ! \webo_mcp_rank_math_active() ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/create-redirection' );
		}
		if ( ! class_exists( 'RankMath\\Redirections\\Redirection' ) ) {
			return array( 'success' => false, 'message' => 'Redirections module unavailable.', 'tool' => 'rankmath/create-redirection' );
		}
		$header_code = isset( $a['header_code'] ) ? (int) $a['header_code'] : 301;
		if ( ! in_array( $header_code, \webo_mcp_rank_math_allowed_redirection_headers(), true ) ) {
			return array( 'success' => false, 'message' => 'Invalid header_code.', 'tool' => 'rankmath/create-redirection' );
		}
		$status = isset( $a['status'] ) ? sanitize_key( (string) $a['status'] ) : 'active';
		if ( ! in_array( $status, \webo_mcp_rank_math_allowed_redirection_statuses(), true ) ) {
			return array( 'success' => false, 'message' => 'Invalid status.', 'tool' => 'rankmath/create-redirection' );
		}
		$destination = isset( $a['destination'] ) ? (string) $a['destination'] : '';
		if ( '' === $destination && ! in_array( $header_code, array( 410, 451 ), true ) ) {
			return array( 'success' => false, 'message' => 'Destination required for this header_code.', 'tool' => 'rankmath/create-redirection' );
		}
		$sources_input = isset( $a['sources'] ) && is_array( $a['sources'] ) ? $a['sources'] : array();
		$sources       = array();
		foreach ( $sources_input as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$pattern = isset( $source['pattern'] ) ? trim( (string) $source['pattern'] ) : '';
			if ( '' === $pattern ) {
				continue;
			}
			$comparison = isset( $source['comparison'] ) ? sanitize_key( (string) $source['comparison'] ) : 'exact';
			if ( ! in_array( $comparison, \webo_mcp_rank_math_allowed_redirection_comparisons(), true ) ) {
				return array( 'success' => false, 'message' => 'Invalid comparison: ' . $comparison, 'tool' => 'rankmath/create-redirection' );
			}
			$sources[] = array(
				'pattern'    => $pattern,
				'comparison' => $comparison,
				'ignore'     => ! empty( $source['ignore_case'] ) ? 'case' : '',
			);
		}
		if ( empty( $sources ) ) {
			return array( 'success' => false, 'message' => 'No valid sources.', 'tool' => 'rankmath/create-redirection' );
		}
		$redirection = \RankMath\Redirections\Redirection::from(
			array(
				'sources'     => $sources,
				'url_to'      => $destination,
				'header_code' => $header_code,
				'status'      => $status,
			)
		);
		if ( method_exists( $redirection, 'is_infinite_loop' ) && $redirection->is_infinite_loop() ) {
			return array( 'success' => false, 'message' => 'Redirection would loop.', 'tool' => 'rankmath/create-redirection' );
		}
		$rid = $redirection->save();
		if ( empty( $rid ) ) {
			return array( 'success' => false, 'message' => 'Failed to create redirection.', 'tool' => 'rankmath/create-redirection' );
		}
		return array(
			'success' => true,
			'id'      => (int) $rid,
			'message' => 'Redirection created.',
			'tool'    => 'rankmath/create-redirection',
		);
	}

	/**
	 * @param array<string, mixed> $a Arguments.
	 * @return array<string, mixed>
	 */
	public static function delete_redirections( array $a ): array {
		if ( ! \webo_mcp_rank_math_active() ) {
			return array( 'success' => false, 'message' => 'Rank Math SEO is not active.', 'tool' => 'rankmath/delete-redirections' );
		}
		if ( ! class_exists( 'RankMath\\Redirections\\DB' ) ) {
			return array( 'success' => false, 'message' => 'Redirections module unavailable.', 'tool' => 'rankmath/delete-redirections' );
		}
		$ids = isset( $a['ids'] ) && is_array( $a['ids'] ) ? array_filter( array_map( 'absint', $a['ids'] ) ) : array();
		if ( empty( $ids ) ) {
			return array( 'success' => false, 'message' => 'No valid IDs.', 'tool' => 'rankmath/delete-redirections' );
		}
		$deleted = \RankMath\Redirections\DB::delete( $ids );
		return array(
			'success' => true,
			'deleted' => (int) $deleted,
			'message' => 'Deleted ' . (int) $deleted . ' redirection(s).',
			'tool'    => 'rankmath/delete-redirections',
		);
	}
}
