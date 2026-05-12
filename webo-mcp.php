<?php
/**
 * WEBO MCP - Model Context Protocol bridge for WordPress (JSON-RPC tools via REST).
 *
 * @wordpress-plugin
 *
 * Plugin Name: WEBO MCP
 * Plugin URI: https://webomcp.com
 * Description: MCP (Model Context Protocol) gateway for WordPress: JSON-RPC tools over the REST API for MCP clients.
 * Version: 2.1.13
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dinh WP
 * Author URI: https://dinhwp.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: webo-mcp
 * Domain Path: /languages
 *
 * @package WeboMCP
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
		'webo_wordpress_mcp_hmac_secret'           => 'webo_mcp_hmac_secret',
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
add_action( 'admin_post_webo_mcp_clear_audit_log', 'webo_mcp_handle_clear_audit_log' );

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

	register_setting(
		'webo_mcp_settings',
		'webo_mcp_tool_allowlist_enabled',
		array(
			'sanitize_callback' => static function ( $value ) {
				return ! empty( $value ) ? '1' : '0';
			},
		)
	);

	register_setting(
		'webo_mcp_settings',
		'webo_mcp_tool_allowlist_rules',
		array(
			'sanitize_callback' => array( \WeboMCP\Core\Router\ClientToolPolicy::class, 'sanitize_rules_option' ),
		)
	);

	register_setting(
		'webo_mcp_settings',
		'webo_mcp_audit_log_enabled',
		array(
			'sanitize_callback' => static function ( $value ) {
				return ! empty( $value ) ? '1' : '0';
			},
		)
	);

	register_setting(
		'webo_mcp_settings',
		'webo_mcp_audit_log_max_entries',
		array(
			'sanitize_callback' => array( \WeboMCP\Core\Audit\Audit_Log::class, 'sanitize_max_entries' ),
		)
	);
}

/**
 * Renders plugin settings page.
 *
 * @return void
 */
function webo_mcp_render_settings_page() {
	$allowlist_rules = \WeboMCP\Core\Router\ClientToolPolicy::get_rules_option();
	$audit_entries   = \WeboMCP\Core\Audit\Audit_Log::get_entries( 25 );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( __( 'WEBO MCP Settings', 'webo-mcp' ) ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'webo_mcp_settings' ); ?>
			<?php do_settings_sections( 'webo_mcp_settings' ); ?>
			<h2><?php echo esc_html( __( 'Authentication', 'webo-mcp' ) ); ?></h2>
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
			<h2><?php echo esc_html( __( 'MCP Tool Allowlist', 'webo-mcp' ) ); ?></h2>
			<p>
				<?php
				echo esc_html__(
					'Disabled by default. When enabled, only tools listed for the authenticated user, role, Application Password, or MCP client label are available.',
					'webo-mcp'
				);
				?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Enable allowlist enforcement', 'webo-mcp' ) ); ?>
					</th>
					<td>
						<label>
							<input
								type="checkbox"
								name="webo_mcp_tool_allowlist_enabled"
								value="1"
								<?php checked( get_option( 'webo_mcp_tool_allowlist_enabled', '0' ), '1' ); ?>
							/>
							<?php echo esc_html( __( 'Restrict MCP tools to matching allowlist rules.', 'webo-mcp' ) ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="webo_mcp_tool_allowlist_users">
							<?php echo esc_html( __( 'User rules', 'webo-mcp' ) ); ?>
						</label>
					</th>
					<td>
						<textarea
							id="webo_mcp_tool_allowlist_users"
							name="webo_mcp_tool_allowlist_rules[users]"
							rows="4"
							class="large-text code"
							placeholder="<?php echo esc_attr( __( '123 = webo/content-query, webo/media-query', 'webo-mcp' ) ); ?>"
						><?php echo esc_textarea( $allowlist_rules['users'] ); ?></textarea>
						<p class="description"><?php echo esc_html( __( 'One rule per line. Identifier may be a user ID or login. Use * to allow every registered tool for that identity.', 'webo-mcp' ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="webo_mcp_tool_allowlist_roles">
							<?php echo esc_html( __( 'Role rules', 'webo-mcp' ) ); ?>
						</label>
					</th>
					<td>
						<textarea
							id="webo_mcp_tool_allowlist_roles"
							name="webo_mcp_tool_allowlist_rules[roles]"
							rows="4"
							class="large-text code"
							placeholder="<?php echo esc_attr( __( 'editor = webo/content-query, webo/media-query', 'webo-mcp' ) ); ?>"
						><?php echo esc_textarea( $allowlist_rules['roles'] ); ?></textarea>
						<p class="description"><?php echo esc_html( __( 'Role identifiers use WordPress role slugs such as administrator, editor, author, or a custom role slug.', 'webo-mcp' ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="webo_mcp_tool_allowlist_clients">
							<?php echo esc_html( __( 'Client rules', 'webo-mcp' ) ); ?>
						</label>
					</th>
					<td>
						<textarea
							id="webo_mcp_tool_allowlist_clients"
							name="webo_mcp_tool_allowlist_rules[clients]"
							rows="4"
							class="large-text code"
							placeholder="<?php echo esc_attr( __( 'cursor = webo/content-query, webo/health-status', 'webo-mcp' ) ); ?>"
						><?php echo esc_textarea( $allowlist_rules['clients'] ); ?></textarea>
						<p class="description"><?php echo esc_html( __( 'Client identifiers may match an Application Password UUID, app ID, Application Password name, or the client label sent during initialize.', 'webo-mcp' ) ); ?></p>
					</td>
				</tr>
			</table>
			<h2><?php echo esc_html( __( 'Audit Log', 'webo-mcp' ) ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<?php echo esc_html( __( 'Enable audit log', 'webo-mcp' ) ); ?>
					</th>
					<td>
						<label>
							<input
								type="checkbox"
								name="webo_mcp_audit_log_enabled"
								value="1"
								<?php checked( get_option( 'webo_mcp_audit_log_enabled', '1' ), '1' ); ?>
							/>
							<?php echo esc_html( __( 'Record compact MCP tools/call events.', 'webo-mcp' ) ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="webo_mcp_audit_log_max_entries">
							<?php echo esc_html( __( 'Maximum stored entries', 'webo-mcp' ) ); ?>
						</label>
					</th>
					<td>
						<input
							type="number"
							id="webo_mcp_audit_log_max_entries"
							name="webo_mcp_audit_log_max_entries"
							min="25"
							max="1000"
							value="<?php echo esc_attr( \WeboMCP\Core\Audit\Audit_Log::max_entries() ); ?>"
						/>
						<p class="description"><?php echo esc_html( __( 'The log is stored in a bounded WordPress option with anonymized IPs and hashed session IDs.', 'webo-mcp' ) ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p>
			<em>
				<?php
				echo esc_html__(
					'MCP requests always require a WordPress Application Password (HTTP Basic) or a logged-in session. Use these fields only as an optional extra secret: if set, clients must also send the API key header and/or valid HMAC signatures.',
					'webo-mcp'
				);
				?>
			</em>
		</p>
		<h2><?php echo esc_html( __( 'Recent MCP Audit Events', 'webo-mcp' ) ); ?></h2>
		<?php if ( empty( $audit_entries ) ) : ?>
			<p><?php echo esc_html( __( 'No MCP tool call events recorded yet.', 'webo-mcp' ) ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html( __( 'Time (GMT)', 'webo-mcp' ) ); ?></th>
						<th><?php echo esc_html( __( 'User', 'webo-mcp' ) ); ?></th>
						<th><?php echo esc_html( __( 'Tool', 'webo-mcp' ) ); ?></th>
						<th><?php echo esc_html( __( 'Action', 'webo-mcp' ) ); ?></th>
						<th><?php echo esc_html( __( 'Object', 'webo-mcp' ) ); ?></th>
						<th><?php echo esc_html( __( 'Status', 'webo-mcp' ) ); ?></th>
						<th><?php echo esc_html( __( 'Result', 'webo-mcp' ) ); ?></th>
						<th><?php echo esc_html( __( 'IP', 'webo-mcp' ) ); ?></th>
						<th><?php echo esc_html( __( 'Session', 'webo-mcp' ) ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $audit_entries as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $entry['timestamp_gmt'] ) ? (string) $entry['timestamp_gmt'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $entry['user_login'] ) && '' !== $entry['user_login'] ? (string) $entry['user_login'] : (string) ( $entry['user_id'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['tool'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['action'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['object_id'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['status'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['result_summary'] ?? $entry['error_message'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['ip'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $entry['session_hash'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="webo_mcp_clear_audit_log" />
			<?php wp_nonce_field( 'webo_mcp_clear_audit_log' ); ?>
			<?php submit_button( __( 'Clear audit log', 'webo-mcp' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Handles audit log clearing from the settings page.
 *
 * @return void
 */
function webo_mcp_handle_clear_audit_log() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to clear the MCP audit log.', 'webo-mcp' ) );
	}

	check_admin_referer( 'webo_mcp_clear_audit_log' );
	\WeboMCP\Core\Audit\Audit_Log::clear();

	wp_safe_redirect( admin_url( 'options-general.php?page=webo-mcp-settings&webo_mcp_audit_cleared=1' ) );
	exit;
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
require_once __DIR__ . '/inc/audit/class-audit-log.php';
require_once __DIR__ . '/inc/abilities/class-site-settings-ability.php';
require_once __DIR__ . '/inc/session/class-session-manager.php';
require_once __DIR__ . '/inc/router/ClientToolPolicy.php';
require_once __DIR__ . '/inc/tools/class-wordpress-tools.php';
require_once __DIR__ . '/inc/tools/class-seo-article-analysis.php';
require_once __DIR__ . '/inc/tools/class-health-status-tool.php';
require_once __DIR__ . '/inc/bootstrap/class-standalone-tools.php';
require_once __DIR__ . '/inc/router/class-mcp-router.php';
require_once __DIR__ . '/inc/router/class-rest-json-bom-guard.php';

use WeboMCP\Core\Abilities\Site_Settings_Ability;
use WeboMCP\Core\Bootstrap\Standalone_Tools;
use WeboMCP\Core\Registry\ToolRegistry;
use WeboMCP\Core\Router\AccessHelper;
use WeboMCP\Core\Router\McpRouter;
use WP\MCP\Abilities\DiscoverAbilitiesAbility;
use WP\MCP\Abilities\ExecuteAbilityAbility;
use WP\MCP\Abilities\GetAbilityInfoAbility;
use WP\MCP\Core\McpAdapter;

Site_Settings_Ability::hook();
add_action( 'application_password_did_authenticate', array( \WeboMCP\Core\Router\ClientToolPolicy::class, 'capture_application_password' ), 10, 2 );

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

		$type = 'string';
		if ( isset( $property_schema['type'] ) ) {
			if ( is_string( $property_schema['type'] ) && '' !== $property_schema['type'] ) {
				$type = $property_schema['type'];
			} elseif ( is_array( $property_schema['type'] ) ) {
				foreach ( $property_schema['type'] as $candidate_type ) {
					if ( is_string( $candidate_type ) && '' !== $candidate_type && 'null' !== $candidate_type ) {
						$type = $candidate_type;
						break;
					}
				}
			}
		}

		$rule = array(
			'type'     => $type,
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
 * Ensures MCP adapter ability category is registered.
 *
 * @return void
 */
function webo_mcp_ensure_adapter_ability_category() {
	if ( ! function_exists( 'wp_register_ability_category' ) || ! function_exists( 'wp_has_ability_category' ) ) {
		return;
	}

	if ( wp_has_ability_category( 'mcp-adapter' ) ) {
		return;
	}

	// wp_register_ability_category() is only valid after categories init has fired.
	if ( ! did_action( 'wp_abilities_api_categories_init' ) && ! doing_action( 'wp_abilities_api_categories_init' ) ) {
		return;
	}

	wp_register_ability_category(
		'mcp-adapter',
		array(
			'label'       => 'MCP Adapter',
			'description' => 'Abilities for the MCP Adapter',
		)
	);
}
add_action( 'wp_abilities_api_categories_init', 'webo_mcp_ensure_adapter_ability_category', 1 );

/**
 * Ensures MCP adapter core abilities are registered for WP-CLI and REST flows.
 *
 * @return void
 */
function webo_mcp_ensure_adapter_core_abilities() {
	if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_has_ability_category' ) ) {
		return;
	}

	/*
	 * Some runtimes trigger wp_abilities_api_init without categories init first.
	 * Fire category init once so the mcp-adapter category can be registered safely.
	 */
	if ( ! did_action( 'wp_abilities_api_categories_init' ) ) {
		do_action( 'wp_abilities_api_categories_init' );
	}

	if ( ! wp_has_ability_category( 'mcp-adapter' ) ) {
		return;
	}

	$registered_abilities = wp_get_abilities();
	if ( ! is_array( $registered_abilities ) ) {
		$registered_abilities = array();
	}

	$ability_registrars = array(
		'mcp-adapter/discover-abilities' => DiscoverAbilitiesAbility::class,
		'mcp-adapter/get-ability-info'   => GetAbilityInfoAbility::class,
		'mcp-adapter/execute-ability'    => ExecuteAbilityAbility::class,
	);

	foreach ( $ability_registrars as $ability_name => $registrar_class ) {
		if ( isset( $registered_abilities[ $ability_name ] ) ) {
			continue;
		}

		if ( class_exists( $registrar_class ) && method_exists( $registrar_class, 'register' ) ) {
			$registrar_class::register();
		}
	}
}
add_action( 'wp_abilities_api_init', 'webo_mcp_ensure_adapter_core_abilities', 1 );

/**
 * Recovers adapter category/abilities if Abilities API init fired before this plugin hooks.
 *
 * @return void
 */
function webo_mcp_recover_adapter_abilities_after_late_boot() {
	if ( did_action( 'wp_abilities_api_categories_init' ) ) {
		webo_mcp_ensure_adapter_ability_category();
	}

	if ( did_action( 'wp_abilities_api_init' ) ) {
		webo_mcp_ensure_adapter_core_abilities();
	}
}
add_action( 'plugins_loaded', 'webo_mcp_recover_adapter_abilities_after_late_boot', 30 );

/**
 * Avoid default server bootstrap noise in WP-CLI.
 *
 * WEBO MCP exposes its own router and tool registry, so skipping the adapter
 * default server during CLI keeps logs clean without changing REST behavior.
 *
 * @param bool $enabled Current default server flag.
 * @return bool
 */
function webo_mcp_control_adapter_default_server( $enabled ) {
	if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
		return false;
	}

	return (bool) $enabled;
}
add_filter( 'mcp_adapter_create_default_server', 'webo_mcp_control_adapter_default_server', 1 );

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
		$category     = method_exists( $ability, 'get_category' ) ? (string) $ability->get_category() : 'WordPress';
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
 * Names matching `webo_mcp_bridge_deny_patterns` are denied unless a filter on
 * `webo_mcp_should_bridge_ability` overrides the decision (e.g. addon allowlist).
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

	$denied = false;
	if ( is_array( $deny_patterns ) ) {
		foreach ( $deny_patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' !== $pattern && false !== strpos( $ability_name, $pattern ) ) {
				$denied = true;
				break;
			}
		}
	}

	return (bool) apply_filters( 'webo_mcp_should_bridge_ability', ! $denied, $ability_name );
}

/**
 * Prime the Abilities registry so {@see 'wp_abilities_api_init'} runs before {@see 'webo_mcp_bootstrap'} (init:20).
 *
 * On normal front-end requests nothing else may call {@see WP_Abilities_Registry::get_instance()} early; addons that
 * hook {@see 'webo_mcp_register_tools'} would otherwise hit {@see wp_register_ability()} before the API init hook.
 *
 * @return void
 */
function webo_mcp_prime_abilities_registry_early() {
	if ( ! class_exists( 'WP_Abilities_Registry', false ) ) {
		return;
	}
	WP_Abilities_Registry::get_instance();
}
add_action( 'init', 'webo_mcp_prime_abilities_registry_early', 2 );

/**
 * Registers standalone tools, bridges abilities, fires extension hooks.
 *
 * @return void
 */
function webo_mcp_bootstrap() {
	Standalone_Tools::register();

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

				$payload          = ToolRegistry::list_tools( $include_internal );
				$tools            = isset( $payload['tools'] ) && is_array( $payload['tools'] ) ? $payload['tools'] : array();
				$registered_total = isset( $payload['registered_total'] ) ? (int) $payload['registered_total'] : count( $tools );
				$tools            = array_values(
					array_filter(
						$tools,
						static function ( $tool ) use ( $request ) {
							return is_array( $tool ) && \WeboMCP\Core\Router\PolicyHelper::is_public_allowed( $tool, $request );
						}
					)
				);

				return rest_ensure_response(
					array(
						'tools' => $tools,
						'meta'  => array(
							'registered_total' => $registered_total,
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
 * GET  /wp-json/mcp/v1/router (SSE)
 * GET  /wp-json/mcp/v1/router/sse (SSE alias)
 * POST /wp-json/mcp/mcp-adapter-default-server (alias; same handler as router)
 * GET  /wp-json/mcp/mcp-adapter-default-server (SSE)
 *
 * Priority 20 runs after WordPress MCP Adapter registers the default HTTP transport
 * on rest_api_init priority 15–16; otherwise that route would be owned by the adapter
 * and would not use WEBO secure_permission_callback (Application Password session + optional API key / HMAC).
 *
 * @return void
 */
function webo_mcp_register_mcp_router() {
	McpRouter::register_rest_endpoint();
}
add_action( 'rest_api_init', 'webo_mcp_register_mcp_router', 20 );
