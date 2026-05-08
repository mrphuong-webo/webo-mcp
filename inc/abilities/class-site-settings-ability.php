<?php
/**
 * Replaces core/get-site-info with core/get-site-settings (full site configuration snapshot).
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the core/get-site-settings ability and removes core/get-site-info.
 */
class Site_Settings_Ability {

	/**
	 * Section keys returned by execute().
	 *
	 * @return array<int, string>
	 */
	private static function all_sections() {
		return array(
			'identity',
			'urls',
			'wordpress',
			'locale_time',
			'reading',
			'discussion',
			'privacy',
			'permalinks',
			'writing',
			'theme',
		);
	}

	/**
	 * Hook into Abilities API after core abilities register.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_ability' ), 20 );
	}

	/**
	 * Unregister core/get-site-info and register core/get-site-settings.
	 *
	 * @return void
	 */
	public static function register_ability() {
		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_unregister_ability' ) ) {
			return;
		}

		if ( wp_has_ability( 'core/get-site-info' ) ) {
			wp_unregister_ability( 'core/get-site-info' );
		}

		if ( wp_has_ability( 'core/get-site-settings' ) ) {
			wp_unregister_ability( 'core/get-site-settings' );
		}

		$section_enum = self::all_sections();

		wp_register_ability(
			'core/get-site-settings',
			array(
				'label'       => __( 'Get Site Settings', 'webo-mcp' ),
				'description' => __( 'Returns WordPress site-wide settings: title, URLs, locale and time formats, reading and front page, discussion defaults, search visibility, permalinks, writing defaults, and active theme. Use this for configuration and environment context — not for loading posts, pages, or user lists.', 'webo-mcp' ),
				'category'    => 'site',
				'input_schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'sections' => array(
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => $section_enum,
							),
							'description' => __( 'Optional: only include these sections. If omitted or empty, all sections are returned.', 'webo-mcp' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
					),
				),
			)
		);
	}

	/**
	 * Build the ability result.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function execute( $input = array() ) {
		$input    = is_array( $input ) ? $input : array();
		$all      = self::all_sections();
		$sections = array();
		if ( ! empty( $input['sections'] ) && is_array( $input['sections'] ) ) {
			foreach ( $input['sections'] as $s ) {
				$key = sanitize_key( (string) $s );
				if ( in_array( $key, $all, true ) ) {
					$sections[] = $key;
				}
			}
		}
		if ( empty( $sections ) ) {
			$sections = $all;
		}

		$out = array();

		if ( in_array( 'identity', $sections, true ) ) {
			$out['identity'] = array(
				'name'        => get_bloginfo( 'name' ),
				'description' => get_bloginfo( 'description' ),
				'language'    => get_bloginfo( 'language' ),
				'charset'     => get_bloginfo( 'charset' ),
				'admin_email' => (string) get_option( 'admin_email', '' ),
			);
		}

		if ( in_array( 'urls', $sections, true ) ) {
			$out['urls'] = array(
				'home'      => home_url( '/' ),
				'siteurl'   => site_url( '/' ),
				'wpurl'     => get_bloginfo( 'wpurl' ),
				'login_url' => wp_login_url(),
				'rest_url'  => rest_url(),
			);
		}

		if ( in_array( 'wordpress', $sections, true ) ) {
			$out['wordpress'] = array(
				'version'      => get_bloginfo( 'version' ),
				'is_multisite' => is_multisite(),
				'blog_id'      => get_current_blog_id(),
			);
		}

		if ( in_array( 'locale_time', $sections, true ) ) {
			$out['locale_time'] = array(
				'timezone_string'   => (string) get_option( 'timezone_string', '' ),
				'gmt_offset'        => get_option( 'gmt_offset', 0 ),
				'resolved_timezone' => wp_timezone_string(),
				'date_format'       => (string) get_option( 'date_format', '' ),
				'time_format'       => (string) get_option( 'time_format', '' ),
				'start_of_week'     => (int) get_option( 'start_of_week', 0 ),
			);
		}

		if ( in_array( 'reading', $sections, true ) ) {
			$out['reading'] = array(
				'posts_per_page'  => (int) get_option( 'posts_per_page', 10 ),
				'show_on_front'   => (string) get_option( 'show_on_front', 'posts' ),
				'page_on_front'   => (int) get_option( 'page_on_front', 0 ),
				'page_for_posts'  => (int) get_option( 'page_for_posts', 0 ),
			);
		}

		if ( in_array( 'discussion', $sections, true ) ) {
			$out['discussion'] = array(
				'default_ping_status'    => (string) get_option( 'default_ping_status', 'open' ),
				'default_comment_status' => (string) get_option( 'default_comment_status', 'open' ),
			);
		}

		if ( in_array( 'privacy', $sections, true ) ) {
			$out['privacy'] = array(
				'blog_public' => (int) get_option( 'blog_public', 1 ),
			);
		}

		if ( in_array( 'permalinks', $sections, true ) ) {
			$out['permalinks'] = array(
				'structure'     => (string) get_option( 'permalink_structure', '' ),
				'category_base' => (string) get_option( 'category_base', '' ),
				'tag_base'      => (string) get_option( 'tag_base', '' ),
			);
		}

		if ( in_array( 'writing', $sections, true ) ) {
			$out['writing'] = array(
				'default_category'    => (int) get_option( 'default_category', 0 ),
				'default_post_format' => (string) get_option( 'default_post_format', '0' ),
			);
		}

		if ( in_array( 'theme', $sections, true ) ) {
			$out['theme'] = array(
				'stylesheet' => (string) get_option( 'stylesheet', '' ),
				'template'   => (string) get_option( 'template', '' ),
			);
		}

		return $out;
	}
}
