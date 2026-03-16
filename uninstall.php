<?php
/**
 * Uninstall routine for WEBO WordPress MCP.
 *
 * Removes plugin options that may contain sensitive data.
 *
 * @package WEBO_WordPress_MCP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'webo_wordpress_mcp_api_key' );
delete_option( 'webo_wordpress_mcp_hmac_secret' );

