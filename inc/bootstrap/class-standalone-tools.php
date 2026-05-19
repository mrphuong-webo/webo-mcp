<?php
/**
 * Registers built-in MCP tools (WordPress.org-safe defaults).
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Bootstrap;

use WeboMCP\Core\Bridge\Ability_Bridge;
use WeboMCP\Core\Registry\ToolRegistry;
use WeboMCP\Core\Tools\HealthStatusTool;
use WeboMCP\Core\Tools\SeoArticleAnalysis;
use WeboMCP\Core\Tools\WordPressTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standalone tool definitions and registration.
 */
final class Standalone_Tools {

	/**
	 * Registers all core standalone tools with ToolRegistry.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::get_definitions() as $tool ) {
			if ( isset( $tool['name'] ) && 0 === strpos( (string) $tool['name'], 'webo/ability-' ) && Ability_Bridge::MODE_OFF === Ability_Bridge::get_mode() ) {
				continue;
			}
			ToolRegistry::register( $tool );
		}
	}

	/**
	 * Tool definitions for the default WEBO MCP surface.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_definitions() {
		return array(
			array(
				'name'        => 'webo/content-query',
				'description' => 'Unified read-only content operations. action (required): list — list posts (args: post_type, status, per_page, page, offset, search, orderby, order); get — get post by id/post_id (args: post_id|id, post_type); find-by-url — resolve URL (args: url, update); find-by-slug — resolve slug (args: slug, post_type); get-homepage — reading settings (args: post_id|id, include_excerpt, include_content); discover-types — list post types; list-revisions — revisions for post (args: post_id|id); find-duplicates — duplicate groups (args: post_type, status, match, max_posts, offset, skip_empty); get-terms — terms on post (args: post_id|id, taxonomy). Response always includes id as primary field.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'          => array(
						'type'     => 'string',
						'required' => true,
					),
					'post_id'         => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'id'              => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'post_type'       => array(
						'type'     => 'string',
						'required' => false,
					),
					'status'          => array(
						'type'     => 'string',
						'required' => false,
					),
					'per_page'        => array(
						'type'     => 'integer',
						'required' => false,
					),
					'page'            => array(
						'type'     => 'integer',
						'required' => false,
					),
					'offset'          => array(
						'type'     => 'integer',
						'required' => false,
					),
					'search'          => array(
						'type'     => 'string',
						'required' => false,
					),
					'orderby'         => array(
						'type'     => 'string',
						'required' => false,
					),
					'order'           => array(
						'type'     => 'string',
						'required' => false,
					),
					'url'             => array(
						'type'     => 'string',
						'required' => false,
					),
					'update'          => array(
						'type'     => 'array',
						'required' => false,
					),
					'slug'            => array(
						'type'     => 'string',
						'required' => false,
					),
					'include_excerpt' => array(
						'type'     => 'boolean',
						'required' => false,
					),
					'include_content' => array(
						'type'     => 'boolean',
						'required' => false,
					),
					'match'           => array(
						'type'     => 'string',
						'required' => false,
					),
					'max_posts'       => array(
						'type'     => 'integer',
						'required' => false,
					),
					'skip_empty'      => array(
						'type'     => 'boolean',
						'required' => false,
					),
					'taxonomy'        => array(
						'type'     => 'string',
						'required' => false,
					),
				),
				'permission'  => 'read',
				'callback'    => array( WordPressTools::class, 'content_query' ),
			),
			array(
				'name'        => 'webo/content-mutate',
				'description' => 'Unified write content operations. action (required): create — new post (args: title, content, post_type, status, post_password); update — update post (args: post_id|id, title, content, excerpt, status, post_password); delete — delete post (args: post_id|id, force); restore-revision — restore revision (args: revision_id); bulk-update-status — change status for many posts (args: post_ids[], status); search-replace — find/replace in content (args: search, replace, dry_run, offset, limit); set-featured-image — set thumbnail (args: post_id|id, attachment_id, remove); assign-terms — assign taxonomy terms (args: post_id|id, taxonomy, term_ids[]). Response always includes id as primary field.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'post_id'       => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'id'            => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'title'         => array(
						'type'     => 'string',
						'required' => false,
					),
					'content'       => array(
						'type'     => 'string',
						'required' => false,
						'format'   => 'raw_html',
					),
					'excerpt'       => array(
						'type'     => 'string',
						'required' => false,
						'format'   => 'html',
					),
					'status'        => array(
						'type'     => 'string',
						'required' => false,
					),
					'post_type'     => array(
						'type'     => 'string',
						'required' => false,
					),
					'post_password' => array(
						'type'     => 'string',
						'required' => false,
					),
					'force'         => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
					'revision_id'   => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'post_ids'      => array(
						'type'     => 'array',
						'required' => false,
					),
					'search'        => array(
						'type'     => 'string',
						'required' => false,
					),
					'replace'       => array(
						'type'     => 'string',
						'required' => false,
					),
					'dry_run'       => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => true,
					),
					'offset'        => array(
						'type'     => 'integer',
						'required' => false,
					),
					'limit'         => array(
						'type'     => 'integer',
						'required' => false,
					),
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 0,
					),
					'remove'        => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
					'taxonomy'      => array(
						'type'     => 'string',
						'required' => false,
					),
					'term_ids'      => array(
						'type'     => 'array',
						'required' => false,
					),
				),
				'permission'  => 'edit_posts',
				'callback'    => array( WordPressTools::class, 'content_mutate' ),
			),
			array(
				'name'        => 'webo/list-users',
				'description' => 'List users (limited)',
				'category'    => 'wordpress',
				'arguments'   => array(
					'per_page' => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 20,
						'min'      => 1,
						'max'      => 100,
					),
					'search'   => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
				),
				'permission'  => 'list_users',
				'callback'    => array( WordPressTools::class, 'list_users' ),
			),
			array(
				'name'        => 'webo/media-query',
				'description' => 'Unified read-only media operations. action (required): list (per_page), get (attachment_id).',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'per_page'      => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 20,
						'min'      => 1,
						'max'      => 100,
					),
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
				),
				'permission'  => 'upload_files',
				'callback'    => array( WordPressTools::class, 'media_query' ),
			),
			array(
				'name'        => 'webo/media-mutate',
				'description' => 'Unified media write operations. action (required): upload (image_url, optional filename/title/alt_text), update (attachment_id + title/alt_text/caption), delete (attachment_id).',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'image_url'     => array(
						'type'     => 'string',
						'required' => false,
					),
					'filename'      => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'title'         => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'alt_text'      => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'caption'       => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
				),
				'permission'  => 'upload_files',
				'callback'    => array( WordPressTools::class, 'media_mutate' ),
			),
			array(
				'name'        => 'webo/comment-query',
				'description' => 'Unified read-only comment operations. action (required): list (per_page, status), get (comment_id).',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'comment_id' => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'per_page'   => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 20,
						'min'      => 1,
						'max'      => 100,
					),
					'status'     => array(
						'type'     => 'string',
						'required' => false,
						'default'  => 'approve',
					),
				),
				'permission'  => 'moderate_comments',
				'callback'    => array( WordPressTools::class, 'comment_query' ),
			),
			array(
				'name'        => 'webo/comment-mutate',
				'description' => 'Unified comment write operations. action (required): update (comment_id, optional status/reply), delete (comment_id).',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'comment_id' => array(
						'type'     => 'integer',
						'required' => true,
						'min'      => 1,
					),
					'status'     => array(
						'type'     => 'string',
						'required' => false,
					),
					'reply'      => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
				),
				'permission'  => 'moderate_comments',
				'callback'    => array( WordPressTools::class, 'comment_mutate' ),
			),
			array(
				'name'        => 'webo/taxonomy-query',
				'description' => 'Unified read-only taxonomy operations. action (required): discover, list (taxonomy, per_page), get (term_id, taxonomy).',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'term_id'  => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'taxonomy' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => 'category',
					),
					'per_page' => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 50,
						'min'      => 1,
						'max'      => 100,
					),
				),
				'permission'  => 'manage_categories',
				'callback'    => array( WordPressTools::class, 'taxonomy_query' ),
			),
			array(
				'name'        => 'webo/taxonomy-mutate',
				'description' => 'Unified taxonomy write operations. action (required): create, update, delete.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'term_id'     => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'taxonomy'    => array(
						'type'     => 'string',
						'required' => false,
						'default'  => 'category',
					),
					'name'        => array(
						'type'     => 'string',
						'required' => false,
					),
					'slug'        => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'description' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'parent_id'   => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 0,
						'min'      => 0,
					),
				),
				'permission'  => 'manage_categories',
				'callback'    => array( WordPressTools::class, 'taxonomy_mutate' ),
			),
			array(
				'name'        => 'webo/menu-query',
				'description' => 'Unified navigation menu read-only tool. action must be one of: list (list all menus), list-items (inspect links in a menu), list-locations (discover theme menu slots). For list-items, requires menu_id from webo/menu-mutate create or list. Returns term_id, name, slug, item count for list; db_id, title, menu_order, parent_db_id, object_id, object, type for list-items; registered_locations and assigned for list-locations. Requires edit_posts for list/list-items, edit_posts for list-locations.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'           => array(
						'type'     => 'string',
						'required' => true,
					),
					'menu_id'          => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 0,
						'min'      => 0,
					),
					'include_inactive' => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
				),
				'permission'  => 'edit_posts',
				'callback'    => array( WordPressTools::class, 'menu_query' ),
			),
			array(
				'name'        => 'webo/menu-mutate',
				'description' => 'Unified navigation menu mutation tool. action must be one of: create (new empty menu), create-and-assign (create and assign to theme location), assign (assign existing menu to location), add-item (add page/post link), add-custom-item (add custom URL link). Each action has its own required/optional arguments. See skill docs for examples. Requires edit_theme_options.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'          => array(
						'type'     => 'string',
						'required' => true,
					),
					'menu_id'         => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 0,
						'min'      => 0,
					),
					'menu_name'       => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'theme_location'  => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'replace'         => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => true,
					),
					'post_id'         => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 0,
						'min'      => 0,
					),
					'post_type'       => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'menu_order'      => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 0,
						'min'      => 0,
					),
					'parent_db_id'    => array(
						'type'     => 'integer',
						'required' => false,
						'default'  => 0,
						'min'      => 0,
					),
					'menu_item_title' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'url'             => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'title'           => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
				),
				'permission'  => 'edit_theme_options',
				'callback'    => array( WordPressTools::class, 'menu_mutate' ),
			),
			array(
				'name'        => 'webo/theme-query',
				'description' => 'List installed themes and active status. Optional include_inactive=true to include inactive themes.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'           => array(
						'type'     => 'string',
						'required' => false,
						'default'  => 'list',
					),
					'include_inactive' => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
				),
				'permission'  => 'switch_themes',
				'callback'    => array( WordPressTools::class, 'theme_query' ),
			),
			array(
				'name'        => 'webo/theme-mutate',
				'description' => 'Unified theme write operations. action (required): install from WordPress.org by slug, or switch an installed theme by stylesheet. install supports activate and overwrite when the server permits theme installation.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'slug'       => array(
						'type'     => 'string',
						'required' => false,
					),
					'stylesheet' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'activate'   => array(
						'type'     => 'boolean',
						'required' => false,
					),
					'overwrite'  => array(
						'type'     => 'boolean',
						'required' => false,
					),
				),
				'permission'  => 'switch_themes',
				'callback'    => array( WordPressTools::class, 'theme_mutate' ),
			),
			array(
				'name'        => 'webo/plugin-query',
				'description' => 'Unified read-only plugin inspection: installed, active, updates (uses wp_update_plugins + update_plugins transient when refresh=true), network-active, rental-candidates, health. Optional scope (all, active, network-active) and fields projection.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'query'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'scope'   => array(
						'type'     => 'string',
						'required' => false,
					),
					'refresh' => array(
						'type'     => 'boolean',
						'required' => false,
					),
					'fields'  => array(
						'type'     => 'array',
						'required' => false,
					),
				),
				'permission'  => 'update_plugins',
				'callback'    => array( WordPressTools::class, 'plugin_query' ),
			),
			array(
				'name'        => 'webo/plugin-mutate',
				'description' => 'Unified plugin write operations. action (required): install from WordPress.org by slug; activate/deactivate an installed plugin by plugin_file or slug. install supports activate, network_activate, site_id/blog_id child-site activation, and overwrite when the server permits plugin installation.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'           => array(
						'type'     => 'string',
						'required' => true,
					),
					'slug'             => array(
						'type'     => 'string',
						'required' => false,
					),
					'plugin_file'      => array(
						'type'     => 'string',
						'required' => false,
					),
					'activate'         => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
					'network_activate' => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
					'network_wide'     => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
					'site_id'          => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'blog_id'          => array(
						'type'     => 'integer',
						'required' => false,
						'min'      => 1,
					),
					'overwrite'        => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
				),
				'permission'  => 'install_plugins',
				'callback'    => array( WordPressTools::class, 'plugin_mutate' ),
			),
			array(
				'name'        => 'webo/health-status',
				'description' => 'Read-only administrator health report for REST routing, Application Password support, permalinks, cron, object cache, plugin update summary, WordPress/PHP versions, and WEBO MCP router/config status. Does not expose API keys or HMAC secrets.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'refresh_updates' => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
				),
				'permission'  => 'manage_options',
				'callback'    => array( HealthStatusTool::class, 'run' ),
			),
			array(
				'name'        => 'webo/ability-query',
				'description' => 'Compact read-only bridge for public WordPress abilities. action: list, get, categories, schema. Only abilities with meta.mcp.public=true are returned.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'action'  => array(
						'type'     => 'string',
						'required' => true,
						'default'  => 'list',
					),
					'ability' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
				'permission'  => 'read',
				'callback'    => array( Ability_Bridge::class, 'query' ),
			),
			array(
				'name'        => 'webo/ability-execute',
				'description' => 'Compact execution bridge for one public WordPress ability. Requires ability, optional input object, dry_run, and reason. Execution is gated by WEBO policy, ability permissions, and scope/risk rules.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'ability' => array(
						'type'     => 'string',
						'required' => true,
					),
					'input'   => array(
						'type'     => 'array',
						'required' => false,
						'default'  => array(),
					),
					'dry_run' => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
					'reason'  => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
				),
				'permission'  => 'read',
				'callback'    => array( Ability_Bridge::class, 'execute' ),
			),
			array(
				'name'        => 'webo/get-options',
				'description' => 'Read selected safe options',
				'category'    => 'wordpress',
				'arguments'   => array(
					'names' => array(
						'type'     => 'array',
						'required' => true,
					),
				),
				'permission'  => 'manage_options',
				'callback'    => array( WordPressTools::class, 'get_options' ),
			),
			array(
				'name'        => 'webo/update-options',
				'description' => 'Update selected safe options',
				'category'    => 'wordpress',
				'arguments'   => array(
					'options' => array(
						'type'     => 'array',
						'required' => true,
					),
				),
				'permission'  => 'manage_options',
				'callback'    => array( WordPressTools::class, 'update_options' ),
			),
			array(
				'name'        => 'webo/set-site-icon',
				'description' => 'Set the WordPress site icon/favicon from an existing image attachment ID.',
				'category'    => 'wordpress',
				'arguments'   => array(
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => true,
						'min'      => 1,
					),
				),
				'permission'  => 'manage_options',
				'callback'    => array( WordPressTools::class, 'set_site_icon' ),
			),
			array(
				'name'        => 'seo/article-analysis',
				'description' => 'WordPress-only SEO analysis for one post by post_id (rendered the_content + Rank Math in synthetic head). No URL or raw HTML input. Merges webo-rank-math/get-post-seo-meta when registered; optional keyword, no_autocomplete, include_rank_math. Returns seo_score, issues, content_gaps, rank_math, integration tool names.',
				'category'    => 'seo',
				'arguments'   => array(
					'post_id'           => array(
						'type'     => 'integer',
						'required' => true,
						'min'      => 1,
					),
					'keyword'           => array(
						'type'     => 'string',
						'required' => false,
						'default'  => '',
					),
					'no_autocomplete'   => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					),
					'include_rank_math' => array(
						'type'     => 'boolean',
						'required' => false,
						'default'  => true,
					),
				),
				'permission'  => 'edit_posts',
				'callback'    => array( SeoArticleAnalysis::class, 'analyze_tool' ),
			),
		);
	}
}
