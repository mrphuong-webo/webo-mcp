=== WEBO WordPress MCP ===
Contributors: dinhwp
Author URI: https://dinhwp.com
Tags: mcp, ai, json-rpc, api, wordpress
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
WEBO WordPress MCP acts as the primary standalone MCP gateway for WordPress.

Supported AI Platforms: Use with any MCP-compatible client (e.g. Cursor, Claude Desktop, n8n, custom apps). The client can use any of these models: Claude (Anthropic), OpenAI (GPT-4, GPT-3.5), Google Gemini, Mistral AI, Perplexity, Groq, Cohere, Together AI, DeepSeek. This plugin exposes standard MCP JSON-RPC; compatibility depends on the client, not the LLM provider.

- Endpoint: POST /wp-json/mcp/v1/router
- Methods: initialize, tools/list, tools/call
- Bundled Abilities API support via Composer vendor
- Bundled WordPress MCP Adapter runtime
- Automatic bridge from registered abilities to MCP tools
- Public tools policy with category and allowlist filters
- Optional API key and HMAC authentication for tools/call
- Session lifecycle for MCP clients

Standalone core tools included:
- Site info
- Posts: list/get/create/update/delete, bulk update status, revisions (list/restore), search & replace
- Users: list
- Media: list/get/upload-from-url/update/delete
- Comments: list/get/update/delete
- Terms: list/create/update/delete (category, tag)
- Plugins: list active status, toggle (activate/deactivate)
- Options: get/update (safe allowlist only)

Excluded by default in standalone-safe mode:
- Bulk/mass execution tools
- Plugin/theme write-management abilities
- Multisite-specific abilities

== Privacy ==

This plugin does not send any data to remote servers by itself. All MCP traffic is initiated by external MCP clients that you configure to call your site.

The plugin stores the following options in the WordPress database when configured:
- `webo_wordpress_mcp_api_key`: API key used to authenticate MCP requests.
- `webo_wordpress_mcp_hmac_secret`: HMAC secret used to sign and validate MCP requests.

These options are removed when the plugin is uninstalled via the WordPress Plugins screen.

== Developer Hooks ==

The plugin exposes the following actions and filters for developers:

=== Actions ===

- `webo_wordpress_mcp_register_tools`  
  Fired during plugin bootstrap after standalone tools are registered. Use this to register custom MCP tools from other plugins.

=== Filters ===

- `webo_wordpress_mcp_allow_internal_tools` (bool $allow_internal, WP_REST_Request $request)  
  Controls whether internal tools are included in tools/list responses. Defaults to false for public environments.

- `webo_wordpress_mcp_public_categories` (array $categories, WP_REST_Request $request, array $tool)  
  Filters which tool categories are exposed as public. Defaults to array( 'wordpress' ).

- `webo_wordpress_mcp_public_tool_allowlist` (array $names, WP_REST_Request $request, array $tool)  
  Optional allowlist of specific tool names that are always considered public.

- `webo_wordpress_mcp_bridge_deny_patterns` (array $patterns)  
  Controls which abilities are excluded when auto-bridging abilities into MCP tools (e.g. bulk, plugins/, themes/, multisite/).

- `webo_wordpress_mcp_auto_bridge_abilities` (bool $enabled)  
  Enables or disables automatic bridging of registered abilities into MCP tools. Defaults to true.

- `webo_wordpress_mcp_enable_adapter` (bool $enabled)  
  Enables or disables the bundled WordPress MCP Adapter runtime. Defaults to true.

== Installation ==
1. Upload the plugin folder to /wp-content/plugins/webo-wordpress-mcp
2. Run composer install inside the plugin folder
3. Activate the plugin in WordPress Admin
4. Send JSON-RPC requests to POST /wp-json/mcp/v1/router

For release packaging, use scripts/build-release.ps1 to create a clean zip with .distignore exclusions.

== Frequently Asked Questions ==

= Which endpoint should MCP clients use? =
POST /wp-json/mcp/v1/router

= Can this run WordPress abilities by itself? =
Yes. This plugin bundles Abilities API via Composer and auto-bridges registered abilities to MCP tools. You can disable auto-bridge with filter webo_wordpress_mcp_auto_bridge_abilities set to false.

= Can I expose internal tools? =
Yes, via filter webo_wordpress_mcp_allow_internal_tools in private environments.

= Can I limit public tools by category? =
Yes, via filter webo_wordpress_mcp_public_categories.

= Can I keep only WordPress.org-safe features? =
Yes. Default bridge rules exclude patterns for bulk, plugins/themes, and multisite abilities.

= Is this plugin suitable for production? =
Yes, when used with proper authentication, TLS, and a limited tool exposure policy.

== Screenshots ==
1. MCP endpoint working in a REST client (initialize)
2. tools/list response with public tools
3. tools/call response for a WordPress tool

== Changelog ==
= 1.1.1 =
* Added empty input_schema definitions for core/get-user-info and core/get-environment-info.
* Fixes MCP tools/call validation errors when invoking these no-input core tools.

= 1.0.2 =
* Added new read-only tool: webo/list-active-plugins.
* Enables MCP clients to verify active plugins with capability check.

= 1.0.1 =
* Metadata refresh release to ensure dependency headers are reloaded correctly.
* tools/list compatibility improvements for include_internal aliases and legacy endpoint support.

= 1.0.0 =
* Initial stable public release.
* MCP JSON-RPC router with initialize, tools/list, tools/call.
* Tool registry integration and public visibility policy controls.
* Session management and optional API key/HMAC security.

== Upgrade Notice ==
= 1.1.1 =
Recommended update to fix tools/call validation for core tools with no input.

= 1.0.2 =
Recommended update to support active plugin verification via MCP tool.

= 1.0.1 =
Recommended update to refresh plugin metadata and improve tools/list compatibility.

= 1.0.0 =
Initial public release of WEBO WordPress MCP.

== Credits ==
Special thanks to the authors and open source projects that contributed to this plugin:
- WordPress (https://wordpress.org)
- Abilities API (https://github.com/WordPress/abilities-api)
  Reference: https://make.wordpress.org/ai/2025/07/17/abilities-api/
- MCP Adapter (https://github.com/WordPress/mcp-adapter)
  Reference: https://make.wordpress.org/ai/2025/07/17/mcp-adapter/
- Composer (https://getcomposer.org)
- Other PHP and JS libraries from the community

If you use this plugin, please give credit to the authors of these libraries.

== License ==
This plugin is licensed under the GPLv2 or later.
See https://www.gnu.org/licenses/gpl-2.0.html for details.
