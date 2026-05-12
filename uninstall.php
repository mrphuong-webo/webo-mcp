<?php
/**
 * Uninstall routine for WEBO MCP.
 *
 * Removes plugin options that may contain sensitive data.
 *
 * @package WEBO_MCP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'webo_mcp_api_key' );
delete_option( 'webo_mcp_hmac_secret' );
delete_option( 'webo_mcp_public_tool_allowlist' );
delete_option( 'webo_mcp_tool_allowlist_enabled' );
delete_option( 'webo_mcp_tool_allowlist_rules' );
delete_option( 'webo_mcp_audit_log_enabled' );
delete_option( 'webo_mcp_audit_log_max_entries' );
delete_option( 'webo_mcp_audit_log' );
delete_option( 'webo_mcp_storage_migrated' );
delete_option( 'webo_wordpress_mcp_api_key' );
delete_option( 'webo_wordpress_mcp_hmac_secret' );
delete_option( 'webo_wordpress_mcp_public_tool_allowlist' );
