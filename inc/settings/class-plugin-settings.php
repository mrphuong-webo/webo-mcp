<?php

namespace WeboMCP\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PluginSettings {
	public static function register() {
		add_action( 'admin_menu', [ self::class, 'add_settings_menu' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
	}

	public static function add_settings_menu() {
		add_options_page(
			'WEBO MCP Settings',
			'WEBO MCP',
			'manage_options',
			'webo-wordpress-mcp-settings',
			[ self::class, 'render_settings_page' ]
		);
	}

	public static function register_settings() {
		register_setting( 'webo_wordpress_mcp_settings', 'webo_wordpress_mcp_api_key' );
		register_setting( 'webo_wordpress_mcp_settings', 'webo_wordpress_mcp_hmac_secret' );
		register_setting( 'webo_wordpress_mcp_settings', 'webo_wordpress_mcp_public_tool_allowlist' );
	}

	public static function render_settings_page() {
		echo '<div class="wrap">';
		echo '<h1>WEBO MCP Settings</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'webo_wordpress_mcp_settings' );
		do_settings_sections( 'webo_wordpress_mcp_settings' );
		echo '<table class="form-table">';
		echo '<tr><th scope="row"><label for="webo_wordpress_mcp_api_key">API Key</label></th>';
		echo '<td><input type="text" id="webo_wordpress_mcp_api_key" name="webo_wordpress_mcp_api_key" value="' . esc_attr( get_option( 'webo_wordpress_mcp_api_key', '' ) ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="webo_wordpress_mcp_hmac_secret">HMAC Secret</label></th>';
		echo '<td><input type="text" id="webo_wordpress_mcp_hmac_secret" name="webo_wordpress_mcp_hmac_secret" value="' . esc_attr( get_option( 'webo_wordpress_mcp_hmac_secret', '' ) ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th scope="row"><label for="webo_wordpress_mcp_public_tool_allowlist">Public Tool Allowlist</label></th>';
		echo '<td><input type="text" id="webo_wordpress_mcp_public_tool_allowlist" name="webo_wordpress_mcp_public_tool_allowlist" value="' . esc_attr( get_option( 'webo_wordpress_mcp_public_tool_allowlist', '' ) ) . '" class="regular-text" /> <small>Comma-separated tool names</small></td></tr>';
		echo '</table>';
		submit_button();
		echo '</form>';
		echo '<p><em>To use API key or HMAC authentication, configure the fields above. If left empty, the plugin will not require authentication.</em></p>';
		echo '</div>';
	}
}
