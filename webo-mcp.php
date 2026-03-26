<?php
/**
 * @wordpress-plugin
 *
 * Plugin Name: WEBO MCP
 * Plugin URI: https://webomcp.com
 * Description: MCP (Model Context Protocol) gateway for WordPress: JSON-RPC tools over the REST API for MCP clients.
 * Version: 2.0.15
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dinh WP
 * Author URI: https://dinhwp.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: webo-mcp
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates options and usermeta from pre-2.0 plugin slug (webo-wordpress-mcp).
 *
 * @return void
 */
function webo_mcp_migrate_legacy_storage() {
	if ( get_option( 'webo_mcp_storage_migrated', '' ) === '1' ) {
		return;
	}
	$pairs = array(
		'webo_wordpress_mcp_api_key'               => 'webo_mcp_api_key',
		'webo_wordpress_mcp_hmac_secret'         => 'webo_mcp_hmac_secret',
		'webo_wordpress_mcp_public_tool_allowlist' => 'webo_mcp_public_tool_allowlist',
	);
	foreach ( $pairs as $old_key => $new_key ) {
		$cur = get_option( $new_key, null );
		if ( ( null === $cur || '' === $cur ) && '' !== (string) get_option( $old_key, '' ) ) {
			update_option( $new_key, get_option( $old_key ) );
		}
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "UPDATE {$wpdb->usermeta} SET meta_key = 'webo_mcp_api_key' WHERE meta_key = 'webo_wordpress_mcp_api_key'" );
	update_option( 'webo_mcp_storage_migrated', '1', false );
}
add_action( 'plugins_loaded', 'webo_mcp_migrate_legacy_storage', 1 );

// Settings UI for the plugin.
add_action( 'admin_menu', 'webo_mcp_add_settings_menu' );
add_action( 'admin_init', 'webo_mcp_register_settings' );

/**
 * Registers settings page under Settings menu.
 *
 * @return void
 */
function webo_mcp_add_settings_menu() {
	add_options_page(
		__( 'WEBO MCP Settings', 'webo-mcp' ),
		'WEBO MCP',
		'manage_options',
		'webo-mcp-settings',
		'webo_mcp_render_settings_page'
	);
}

/**
 * Registers plugin settings.
 *
 * @return void
 */
function webo_mcp_register_settings() {
	register_setting(
		'webo_mcp_settings',
		'webo_mcp_api_key',
		array(
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_setting(
		'webo_mcp_settings',
		'webo_mcp_hmac_secret',
		array(
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
}

/**
 * Renders plugin settings page.
 *
 * @return void
 */
function webo_mcp_render_settings_page() {
	?>
	<div class="wrap">
		<h1><?php echo esc_html( __( 'WEBO MCP Settings', 'webo-mcp' ) ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'webo_mcp_settings' ); ?>
			<?php do_settings_sections( 'webo_mcp_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="webo_mcp_api_key">
							<?php echo esc_html( __( 'API Key', 'webo-mcp' ) ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="webo_mcp_api_key"
							name="webo_mcp_api_key"
							value="<?php echo esc_attr( get_option( 'webo_mcp_api_key', '' ) ); ?>"
							class="regular-text"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="webo_mcp_hmac_secret">
							<?php echo esc_html( __( 'HMAC Secret', 'webo-mcp' ) ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="webo_mcp_hmac_secret"
							name="webo_mcp_hmac_secret"
							value="<?php echo esc_attr( get_option( 'webo_mcp_hmac_secret', '' ) ); ?>"
							class="regular-text"
						/>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p>
			<em>
				<?php
				echo esc_html__(
					'Configure the API key or HMAC secret here. If left empty, the plugin will not require authentication for MCP requests.',
					'webo-mcp'
				);
				?>
			</em>
		</p>
	</div>
	<?php
}

$webo_mcp_autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $webo_mcp_autoloader ) ) {
	require_once $webo_mcp_autoloader;
}

$webo_mcp_abilities_api = __DIR__ . '/vendor/wordpress/abilities-api/abilities-api.php';
if ( file_exists( $webo_mcp_abilities_api ) ) {
	require_once $webo_mcp_abilities_api;
}

require_once __DIR__ . '/inc/registry/class-tool-registry.php';
require_once __DIR__ . '/inc/abilities/class-site-settings-ability.php';
require_once __DIR__ . '/inc/tools/class-wordpress-tools.php';
require_once __DIR__ . '/inc/session/class-session-manager.php';
require_once __DIR__ . '/inc/router/class-mcp-router.php';

use WeboMCP\Core\Abilities\Site_Settings_Ability;
use WeboMCP\Core\Registry\ToolRegistry;
use WeboMCP\Core\Router\AccessHelper;
use WeboMCP\Core\Router\McpRouter;
use WeboMCP\Core\Tools\WordPressTools;
use WP\MCP\Core\McpAdapter;

Site_Settings_Ability::hook();

/**
 * Converts Abilities API input schema to ToolRegistry arguments schema.
 *
 * @param array<string, mixed> $input_schema Ability input schema.
 * @return array<string, array<string, mixed>>
 */
function webo_mcp_convert_input_schema_to_tool_arguments( array $input_schema ) {
	$arguments = array();

	if ( ! isset( $input_schema['properties'] ) || ! is_array( $input_schema['properties'] ) ) {
		return $arguments;
	}

	$required_fields = array();
	if ( isset( $input_schema['required'] ) && is_array( $input_schema['required'] ) ) {
		$required_fields = array_values( array_filter( array_map( 'strval', $input_schema['required'] ) ) );
	}

	foreach ( $input_schema['properties'] as $property_name => $property_schema ) {
		if ( ! is_string( $property_name ) || '' === $property_name || ! is_array( $property_schema ) ) {
			continue;
		}

		$rule = array(
			'type'     => isset( $property_schema['type'] ) ? (string) $property_schema['type'] : 'string',
			'required' => in_array( $property_name, $required_fields, true ),
		);

		if ( array_key_exists( 'default', $property_schema ) ) {
			$rule['default'] = $property_schema['default'];
		}

		if ( isset( $property_schema['minimum'] ) && is_numeric( $property_schema['minimum'] ) ) {
			$rule['min'] = (float) $property_schema['minimum'];
		}

		if ( isset( $property_schema['maximum'] ) && is_numeric( $property_schema['maximum'] ) ) {
			$rule['max'] = (float) $property_schema['maximum'];
		}

		if ( 'number' === $rule['type'] ) {
			$rule['type'] = 'integer';
		}

		$arguments[ $property_name ] = $rule;
	}

	return $arguments;
}

/**
 * Bridges all registered WordPress abilities to MCP ToolRegistry.
 *
 * @return void
 */
function webo_mcp_register_wordpress_abilities() {
	if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_get_ability' ) ) {
		return;
	}

	$abilities = wp_get_abilities();
	if ( ! is_array( $abilities ) ) {
		return;
	}

	foreach ( $abilities as $ability ) {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) || ! method_exists( $ability, 'get_description' ) ) {
			continue;
		}

		$ability_name = (string) $ability->get_name();
		if ( '' === $ability_name || ToolRegistry::get( $ability_name ) ) {
			continue;
		}

		if ( ! webo_mcp_should_bridge_ability( $ability_name ) ) {
			continue;
		}

		$input_schema = method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : array();
		$category     = method_exists( $ability, 'get_category' ) ? (string) $ability->get_category() : 'wordpress';
		$description  = (string) $ability->get_description();

		$visibility = 'internal';
		if ( method_exists( $ability, 'get_meta' ) ) {
			$meta = $ability->get_meta();
			if ( is_array( $meta ) && isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && array_key_exists( 'public', $meta['mcp'] ) ) {
				$visibility = (bool) $meta['mcp']['public'] ? 'public' : 'internal';
			}
		}

		ToolRegistry::register(
			array(
				'name'        => $ability_name,
				'description' => '' !== $description ? $description : sprintf( 'Execute ability: %s', $ability_name ),
				'category'    => '' !== $category ? $category : 'wordpress',
				'visibility'  => $visibility,
				'arguments'   => webo_mcp_convert_input_schema_to_tool_arguments( is_array( $input_schema ) ? $input_schema : array() ),
				'callback'    => static function ( array $arguments ) use ( $ability_name ) {
					$ability_instance = wp_get_ability( $ability_name );
					if ( ! $ability_instance || ! method_exists( $ability_instance, 'execute' ) ) {
						return new WP_Error( 'webo_mcp_ability_not_found', sprintf( 'Ability not found: %s', $ability_name ) );
					}

					return $ability_instance->execute( $arguments );
				},
			)
		);
	}
}

/**
 * Whether ability should be bridged into MCP tools by default.
 *
 * @param string $ability_name Ability name.
 * @return bool
 */
function webo_mcp_should_bridge_ability( string $ability_name ) {
	$ability_name = trim( $ability_name );
	if ( '' === $ability_name ) {
		return false;
	}

	$deny_patterns = apply_filters(
		'webo_mcp_bridge_deny_patterns',
		array(
			'bulk',
			'plugins/',
			'themes/',
			'multisite/',
		)
	);

	if ( is_array( $deny_patterns ) ) {
		foreach ( $deny_patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' !== $pattern && false !== strpos( $ability_name, $pattern ) ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Registers standalone WordPress.org-safe tools.
 *
 * @return void
 */
function webo_mcp_register_standalone_core_tools() {
	$tools = array(
		array(
			'name'        => 'webo/list-posts',
			'description' => 'List WordPress posts',
			'category'    => 'wordpress',
			'arguments'   => array(
				'per_page'  => array( 'type' => 'integer', 'required' => false, 'default' => 10, 'min' => 1, 'max' => 100 ),
				'post_type' => array( 'type' => 'string', 'required' => false, 'default' => 'post' ),
				'search'    => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				'status'    => array( 'type' => 'string', 'required' => false, 'default' => 'publish' ),
			),
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'list_posts' ),
		),
		array(
			'name'        => 'webo/get-post',
			'description' => 'Get one post by ID (title, content, excerpt, etc.). Optional post_type (e.g. page) validates the ID is that type — use with page_on_front ID to read static homepage content.',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_id'   => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'post_type' => array( 'type' => 'string', 'required' => false, 'default' => '' ),
			),
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'get_post' ),
		),
		array(
			'name'        => 'webo/discover-content-types',
			'description' => 'List public post types (content types) on the site',
			'category'    => 'wordpress',
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'discover_content_types' ),
		),
		array(
			'name'        => 'webo/find-content-by-url',
			'description' => 'Find content by WordPress URL (path or full URL); optionally pass update to update in same call',
			'category'    => 'wordpress',
			'arguments'   => array(
				'url'    => array( 'type' => 'string', 'required' => true ),
				'update' => array( 'type' => 'array', 'required' => false ),
			),
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'find_content_by_url' ),
		),
		array(
			'name'        => 'webo/get-content-by-slug',
			'description' => 'Get content by slug (post_name); optionally limit to one post_type',
			'category'    => 'wordpress',
			'arguments'   => array(
				'slug'      => array( 'type' => 'string', 'required' => true ),
				'post_type' => array( 'type' => 'string', 'required' => false, 'default' => '' ),
			),
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'get_content_by_slug' ),
		),
		array(
			'name'        => 'webo/get-homepage-info',
			'description' => 'Get homepage / front page: home URL, Reading settings, front page and posts page; optional post_id to resolve one page/post and see if it is the configured front or posts page; optional excerpt/content',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_id'         => array( 'type' => 'integer', 'required' => false, 'min' => 1 ),
				'include_excerpt' => array( 'type' => 'boolean', 'required' => false, 'default' => false ),
				'include_content' => array( 'type' => 'boolean', 'required' => false, 'default' => false ),
			),
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'get_homepage_info' ),
		),
		array(
			'name'        => 'webo/create-post',
			'description' => 'Create one post/page/custom post',
			'category'    => 'wordpress',
			'arguments'   => array(
				'title'     => array( 'type' => 'string', 'required' => true ),
				'content'   => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				'post_type' => array( 'type' => 'string', 'required' => false, 'default' => 'post' ),
				'status'    => array( 'type' => 'string', 'required' => false, 'default' => 'draft' ),
			),
			'permission'  => 'edit_posts',
			'callback'    => array( WordPressTools::class, 'create_post' ),
		),
		array(
			'name'        => 'webo/update-post',
			'description' => 'Update one post/page/custom post',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'title'   => array( 'type' => 'string', 'required' => false ),
				'content' => array( 'type' => 'string', 'required' => false ),
				'status'  => array( 'type' => 'string', 'required' => false ),
			),
			'permission'  => 'edit_posts',
			'callback'    => array( WordPressTools::class, 'update_post' ),
		),
		array(
			'name'        => 'webo/delete-post',
			'description' => 'Delete one post only (no bulk)',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'force'   => array( 'type' => 'boolean', 'required' => false, 'default' => false ),
			),
			'permission'  => 'delete_posts',
			'callback'    => array( WordPressTools::class, 'delete_post' ),
		),
		array(
			'name'        => 'webo/bulk-update-post-status',
			'description' => 'Bulk update post status for given post IDs',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_ids' => array( 'type' => 'array', 'required' => true ),
				'status'   => array( 'type' => 'string', 'required' => false, 'default' => 'draft' ),
			),
			'permission'  => 'edit_posts',
			'callback'    => array( WordPressTools::class, 'bulk_update_post_status' ),
		),
		array(
			'name'        => 'webo/list-revisions',
			'description' => 'List revisions for a post',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
			),
			'permission'  => 'edit_posts',
			'callback'    => array( WordPressTools::class, 'list_revisions' ),
		),
		array(
			'name'        => 'webo/restore-revision',
			'description' => 'Restore a post to a revision',
			'category'    => 'wordpress',
			'arguments'   => array(
				'revision_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
			),
			'permission'  => 'edit_posts',
			'callback'    => array( WordPressTools::class, 'restore_revision' ),
		),
		array(
			'name'        => 'webo/search-replace-posts',
			'description' => 'Search and replace in post/page content; dry_run to preview. Paginate with offset + max_scan_posts (1-500, default 200).',
			'category'    => 'wordpress',
			'arguments'   => array(
				'search'           => array( 'type' => 'string', 'required' => true ),
				'replace'          => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				'dry_run'          => array( 'type' => 'boolean', 'required' => false, 'default' => true ),
				'offset'           => array( 'type' => 'integer', 'required' => false, 'default' => 0, 'min' => 0 ),
				'max_scan_posts'   => array( 'type' => 'integer', 'required' => false, 'default' => 200, 'min' => 1, 'max' => 500 ),
			),
			'permission'  => 'edit_posts',
			'callback'    => array( WordPressTools::class, 'search_replace_posts' ),
		),
		array(
			'name'        => 'webo/list-users',
			'description' => 'List users (limited)',
			'category'    => 'wordpress',
			'arguments'   => array(
				'per_page' => array( 'type' => 'integer', 'required' => false, 'default' => 20, 'min' => 1, 'max' => 100 ),
				'search'   => array( 'type' => 'string', 'required' => false, 'default' => '' ),
			),
			'permission'  => 'list_users',
			'callback'    => array( WordPressTools::class, 'list_users' ),
		),
		array(
			'name'        => 'webo/list-media',
			'description' => 'List media library items',
			'category'    => 'wordpress',
			'arguments'   => array(
				'per_page' => array( 'type' => 'integer', 'required' => false, 'default' => 20, 'min' => 1, 'max' => 100 ),
			),
			'permission'  => 'upload_files',
			'callback'    => array( WordPressTools::class, 'list_media' ),
		),
		array(
			'name'        => 'webo/upload-media-from-url',
			'description' => 'Upload media from a URL into the library',
			'category'    => 'wordpress',
			'arguments'   => array(
				'image_url' => array( 'type' => 'string', 'required' => true ),
				'filename'  => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				'title'     => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				'alt_text'  => array( 'type' => 'string', 'required' => false, 'default' => '' ),
			),
			'permission'  => 'upload_files',
			'callback'    => array( WordPressTools::class, 'upload_media_from_url' ),
		),
		array(
			'name'        => 'webo/get-media',
			'description' => 'Get one media item by attachment ID',
			'category'    => 'wordpress',
			'arguments'   => array(
				'attachment_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
			),
			'permission'  => 'upload_files',
			'callback'    => array( WordPressTools::class, 'get_media' ),
		),
		array(
			'name'        => 'webo/update-media',
			'description' => 'Update media metadata (title, alt_text, caption)',
			'category'    => 'wordpress',
			'arguments'   => array(
				'attachment_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'title'        => array( 'type' => 'string', 'required' => false ),
				'alt_text'     => array( 'type' => 'string', 'required' => false ),
				'caption'      => array( 'type' => 'string', 'required' => false ),
			),
			'permission'  => 'upload_files',
			'callback'    => array( WordPressTools::class, 'update_media' ),
		),
		array(
			'name'        => 'webo/delete-media',
			'description' => 'Delete a media item permanently',
			'category'    => 'wordpress',
			'arguments'   => array(
				'attachment_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
			),
			'permission'  => 'upload_files',
			'callback'    => array( WordPressTools::class, 'delete_media' ),
		),
		array(
			'name'        => 'webo/list-comments',
			'description' => 'List comments',
			'category'    => 'wordpress',
			'arguments'   => array(
				'per_page' => array( 'type' => 'integer', 'required' => false, 'default' => 20, 'min' => 1, 'max' => 100 ),
				'status'   => array( 'type' => 'string', 'required' => false, 'default' => 'approve' ),
			),
			'permission'  => 'moderate_comments',
			'callback'    => array( WordPressTools::class, 'list_comments' ),
		),
		array(
			'name'        => 'webo/get-comment',
			'description' => 'Get one comment by ID',
			'category'    => 'wordpress',
			'arguments'   => array(
				'comment_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
			),
			'permission'  => 'moderate_comments',
			'callback'    => array( WordPressTools::class, 'get_comment' ),
		),
		array(
			'name'        => 'webo/update-comment',
			'description' => 'Update comment status or reply to comment',
			'category'    => 'wordpress',
			'arguments'   => array(
				'comment_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'status'     => array( 'type' => 'string', 'required' => false ),
				'reply'      => array( 'type' => 'string', 'required' => false, 'default' => '' ),
			),
			'permission'  => 'moderate_comments',
			'callback'    => array( WordPressTools::class, 'update_comment' ),
		),
		array(
			'name'        => 'webo/delete-comment',
			'description' => 'Delete a comment permanently',
			'category'    => 'wordpress',
			'arguments'   => array(
				'comment_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
			),
			'permission'  => 'moderate_comments',
			'callback'    => array( WordPressTools::class, 'delete_comment' ),
		),
		array(
			'name'        => 'webo/list-terms',
			'description' => 'List terms by taxonomy',
			'category'    => 'wordpress',
			'arguments'   => array(
				'taxonomy' => array( 'type' => 'string', 'required' => false, 'default' => 'category' ),
				'per_page' => array( 'type' => 'integer', 'required' => false, 'default' => 50, 'min' => 1, 'max' => 100 ),
			),
			'permission'  => 'manage_categories',
			'callback'    => array( WordPressTools::class, 'list_terms' ),
		),
		array(
			'name'        => 'webo/create-term',
			'description' => 'Create a category or tag',
			'category'    => 'wordpress',
			'arguments'   => array(
				'taxonomy'    => array( 'type' => 'string', 'required' => false, 'default' => 'category' ),
				'name'       => array( 'type' => 'string', 'required' => true ),
				'slug'       => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				'description'=> array( 'type' => 'string', 'required' => false, 'default' => '' ),
				'parent_id'  => array( 'type' => 'integer', 'required' => false, 'default' => 0, 'min' => 0 ),
			),
			'permission'  => 'manage_categories',
			'callback'    => array( WordPressTools::class, 'create_term' ),
		),
		array(
			'name'        => 'webo/update-term',
			'description' => 'Update a category or tag',
			'category'    => 'wordpress',
			'arguments'   => array(
				'term_id'     => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'taxonomy'    => array( 'type' => 'string', 'required' => false, 'default' => 'category' ),
				'name'        => array( 'type' => 'string', 'required' => false ),
				'slug'        => array( 'type' => 'string', 'required' => false ),
				'description'=> array( 'type' => 'string', 'required' => false ),
				'parent_id'  => array( 'type' => 'integer', 'required' => false, 'min' => 0 ),
			),
			'permission'  => 'manage_categories',
			'callback'    => array( WordPressTools::class, 'update_term' ),
		),
		array(
			'name'        => 'webo/delete-term',
			'description' => 'Delete a category or tag',
			'category'    => 'wordpress',
			'arguments'   => array(
				'term_id'  => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'taxonomy' => array( 'type' => 'string', 'required' => false, 'default' => 'category' ),
			),
			'permission'  => 'manage_categories',
			'callback'    => array( WordPressTools::class, 'delete_term' ),
		),
		array(
			'name'        => 'webo/discover-taxonomies',
			'description' => 'List public taxonomies (name, label, object_type, hierarchical)',
			'category'    => 'wordpress',
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'discover_taxonomies' ),
		),
		array(
			'name'        => 'webo/get-term',
			'description' => 'Get one term by term_id and taxonomy',
			'category'    => 'wordpress',
			'arguments'   => array(
				'term_id'  => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'taxonomy' => array( 'type' => 'string', 'required' => false, 'default' => 'category' ),
			),
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'get_term' ),
		),
		array(
			'name'        => 'webo/assign-terms-to-content',
			'description' => 'Assign terms to a post (replaces existing terms for that taxonomy)',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_id'  => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'taxonomy' => array( 'type' => 'string', 'required' => true ),
				'term_ids' => array( 'type' => 'array', 'required' => true ),
			),
			'permission'  => 'manage_categories',
			'callback'    => array( WordPressTools::class, 'assign_terms_to_content' ),
		),
		array(
			'name'        => 'webo/get-content-terms',
			'description' => 'Get all terms assigned to a post; optionally filter by taxonomy',
			'category'    => 'wordpress',
			'arguments'   => array(
				'post_id'  => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'taxonomy' => array( 'type' => 'string', 'required' => false, 'default' => '' ),
			),
			'permission'  => 'read',
			'callback'    => array( WordPressTools::class, 'get_content_terms' ),
		),
		array(
			'name'        => 'webo/list-nav-menus',
			'description' => 'List navigation menus (Appearance > Menus): term_id, name, slug. Use term_id as menu_id for list-nav-menu-items and add-nav-menu-item-from-post.',
			'category'    => 'wordpress',
			'permission'  => 'edit_theme_options',
			'callback'    => array( WordPressTools::class, 'list_nav_menus' ),
		),
		array(
			'name'        => 'webo/list-nav-menu-items',
			'description' => 'List items in one menu: db_id, title, menu_order, parent_db_id, object_id, object (post type), type. Dev uses this to choose explicit menu_order and to see valid parent_db_id values before adding links.',
			'category'    => 'wordpress',
			'arguments'   => array(
				'menu_id' => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
			),
			'permission'  => 'edit_theme_options',
			'callback'    => array( WordPressTools::class, 'list_nav_menu_items' ),
		),
		array(
			'name'        => 'webo/add-nav-menu-item-from-post',
			'description' => 'Add a page/post/CPT to a nav menu as a link. REQUIRED (no auto): menu_id (from list-nav-menus), post_id (content ID from list-posts/get-post/etc.), post_type (must match that post, e.g. page), menu_order (integer >= 1; position among siblings — inspect list-nav-menu-items and set deliberately). Optional parent_db_id (existing item db_id in same menu), menu_item_title (override label).',
			'category'    => 'wordpress',
			'arguments'   => array(
				'menu_id'            => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'post_id'            => array( 'type' => 'integer', 'required' => true, 'min' => 1 ),
				'post_type'          => array( 'type' => 'string', 'required' => true ),
				'menu_order'         => array( 'type' => 'integer', 'required' => true, 'min' => 1, 'max' => 5000 ),
				'parent_db_id'       => array( 'type' => 'integer', 'required' => false, 'default' => 0, 'min' => 0 ),
				'menu_item_title'    => array( 'type' => 'string', 'required' => false, 'default' => '' ),
			),
			'permission'  => 'edit_theme_options',
			'callback'    => array( WordPressTools::class, 'add_nav_menu_item_from_post' ),
		),
		array(
			'name'        => 'webo/list-active-plugins',
			'description' => 'List installed plugins and active status',
			'category'    => 'wordpress',
			'arguments'   => array(
				'include_inactive' => array( 'type' => 'boolean', 'required' => false, 'default' => false ),
			),
			'permission'  => 'activate_plugins',
			'callback'    => array( WordPressTools::class, 'list_active_plugins' ),
		),
		array(
			'name'        => 'webo/toggle-plugin',
			'description' => 'Activate or deactivate a plugin by plugin file path',
			'category'    => 'wordpress',
			'arguments'   => array(
				'plugin' => array( 'type' => 'string', 'required' => true ),
				'action' => array( 'type' => 'string', 'required' => false, 'default' => 'activate' ),
			),
			'permission'  => 'activate_plugins',
			'callback'    => array( WordPressTools::class, 'toggle_plugin' ),
		),
		array(
			'name'        => 'webo/get-options',
			'description' => 'Read selected safe options',
			'category'    => 'wordpress',
			'arguments'   => array(
				'names' => array( 'type' => 'array', 'required' => true ),
			),
			'permission'  => 'manage_options',
			'callback'    => array( WordPressTools::class, 'get_options' ),
		),
		array(
			'name'        => 'webo/update-options',
			'description' => 'Update selected safe options',
			'category'    => 'wordpress',
			'arguments'   => array(
				'options' => array( 'type' => 'array', 'required' => true ),
			),
			'permission'  => 'manage_options',
			'callback'    => array( WordPressTools::class, 'update_options' ),
		),
	);

	foreach ( $tools as $tool ) {
		ToolRegistry::register( $tool );
	}
}

function webo_mcp_bootstrap() {
	webo_mcp_register_standalone_core_tools();

	do_action( 'webo_mcp_register_tools' );

	$auto_bridge = (bool) apply_filters( 'webo_mcp_auto_bridge_abilities', true );
	if ( $auto_bridge ) {
		webo_mcp_register_wordpress_abilities();
	}

	/**
	 * Fires once WEBO MCP finished bootstrapping.
	 *
	 * Addons can hook here to register abilities/tools after core is ready.
	 */
	do_action( 'webo_mcp_loaded' );
}
add_action( 'init', 'webo_mcp_bootstrap', 20 );

/**
 * Bootstraps WordPress MCP Adapter runtime.
 *
 * @return void
 */
function webo_mcp_bootstrap_adapter() {
	$enable_adapter = (bool) apply_filters( 'webo_mcp_enable_adapter', true );
	if ( ! $enable_adapter ) {
		return;
	}

	if ( class_exists( McpAdapter::class ) ) {
		McpAdapter::instance();
	}
}
add_action( 'plugins_loaded', 'webo_mcp_bootstrap_adapter', 20 );

/**
 * Returns MCP-compatible tools/list response payload.
 *
 * @return array<string, array<int, array<string, string>>>
 */
function webo_mcp_list_tools() {
	return ToolRegistry::list_tools();
}

/**
 * Optional REST discovery endpoint for diagnostics.
 *
 * GET /wp-json/webo-mcp/v1/tools
 *
 * @return void
 */
function webo_mcp_register_rest_routes() {
	register_rest_route(
		'webo-mcp/v1',
		'/tools',
		array(
			'methods'             => 'GET',
			'callback'            => static function ( \WP_REST_Request $request ) {
				$include_internal = false;

				if ( AccessHelper::current_user_can_use_mcp() ) {
					$include_internal = filter_var( $request->get_param( 'include_internal' ), FILTER_VALIDATE_BOOLEAN );
				}

				$all_payload = ToolRegistry::list_tools( true );
				$payload     = ToolRegistry::list_tools( $include_internal );
				$all_tools   = isset( $all_payload['tools'] ) && is_array( $all_payload['tools'] ) ? $all_payload['tools'] : array();
				$tools       = isset( $payload['tools'] ) && is_array( $payload['tools'] ) ? $payload['tools'] : array();

				return rest_ensure_response(
					array(
						'tools' => $tools,
						'meta'  => array(
							'registered_total' => count( $all_tools ),
							'returned_total'   => count( $tools ),
							'include_internal' => $include_internal,
						),
					)
				);
			},
			'permission_callback' => static function () {
				return AccessHelper::current_user_can_use_mcp();
			},
		)
	);
}
add_action( 'rest_api_init', 'webo_mcp_register_rest_routes' );

/**
 * Registers MCP JSON-RPC router endpoint.
 *
 * POST /wp-json/mcp/v1/router
 * POST /wp-json/mcp/mcp-adapter-default-server (alias; same handler as router)
 *
 * Priority 20 runs after WordPress MCP Adapter registers the default HTTP transport
 * on rest_api_init priority 15–16; otherwise that route would be owned by the adapter
 * and would not use WEBO secure_permission_callback (API key / HMAC / Application Password).
 *
 * @return void
 */
function webo_mcp_register_mcp_router() {
	McpRouter::register_rest_endpoint();
}
add_action( 'rest_api_init', 'webo_mcp_register_mcp_router', 20 );
