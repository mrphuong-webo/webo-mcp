=== WEBO WordPress MCP ===
Contributors: webo
Tags: mcp, ai, json-rpc, api, wordpress
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Public MCP JSON-RPC gateway for WordPress with tool registry, tools/list discovery, tools/call execution, and session security.

== Description ==

WEBO WordPress MCP provides a public MCP gateway for WordPress via JSON-RPC:

- Endpoint: POST /wp-json/mcp/v1/router
- Methods: initialize, tools/list, tools/call
- Automatically bridges registered WordPress Abilities API abilities into MCP tools
- Public tools policy with category and allowlist filters
- Optional API key and HMAC authentication for tools/call
- Session lifecycle for MCP clients

Standalone core tools included (no WEBO MCP required):

- Site info
- Posts: list/get/create/update/delete (single item only)
- Users: list
- Media: list
- Comments: list
- Terms: list
- Options: get/update (safe allowlist only)

Excluded by default in standalone-safe mode:

- Bulk/mass execution tools
- Plugin/theme management abilities
- Multisite-specific abilities

This plugin is designed as the public gateway layer. Advanced/internal abilities can be bridged by companion plugins (for example WEBO MCP) using the provided registry hooks.

== Screenshots ==

1. MCP endpoint working in a REST client (initialize)
2. tools/list response with public tools
3. tools/call response for a WordPress tool

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/webo-wordpress-mcp
2. Run `composer install` inside the plugin folder (required for bundled Abilities API)
3. Activate the plugin in WordPress Admin
4. Send JSON-RPC requests to POST /wp-json/mcp/v1/router

For release packaging, use `scripts/build-release.ps1` to create a clean zip with `.distignore` exclusions.

== Frequently Asked Questions ==

= Which endpoint should MCP clients use? =
Use POST /wp-json/mcp/v1/router.

= Can I expose internal tools? =
Yes, via filter webo_wordpress_mcp_allow_internal_tools in private environments.

= Can I limit public tools by category? =
Yes, via filter webo_wordpress_mcp_public_categories.

= Can this run WordPress abilities without WEBO MCP? =
Yes. If Abilities API is available, this plugin auto-bridges registered abilities to MCP tools. You can disable this with filter webo_wordpress_mcp_auto_bridge_abilities set to false.

= Can I keep only WordPress.org-safe features? =
Yes. Default bridge rules exclude patterns for bulk, plugins/themes, and multisite abilities.

= Is this plugin suitable for production? =
Yes, when used with proper authentication, TLS, and a limited tool exposure policy.

== Changelog ==

= 1.0.0 =
* Initial stable public release.
* MCP JSON-RPC router with initialize, tools/list, tools/call.
* Tool registry integration and public visibility policy controls.
* Session management and optional API key/HMAC security.

== Upgrade Notice ==

= 1.0.0 =
Initial public release of WEBO WordPress MCP.
