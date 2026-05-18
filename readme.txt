=== WEBO MCP ===
Contributors: phuongwebo
Author URI: https://dinhwp.com
Tags: mcp, ai, json-rpc, api, automation
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.1.16
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Token-optimized MCP gateway for WordPress: unified query/mutate tools cut context-window usage by up to 70% vs. per-operation APIs.

== Description ==
WEBO MCP is a standalone MCP gateway for WordPress. It lets compatible clients call well-defined tools over REST using JSON-RPC, instead of scraping the admin or sharing broad credentials beyond what you intend.

**What you get**

- **Token-optimized unified tools:** every domain exposes two abilities — `*-query` (all reads) and `*-mutate` (all writes) — with a single `action` discriminator. `tools/list` payload is up to 70% smaller than per-operation APIs, which means less of the model's context window is consumed by tool schemas, lower cost per session, and fewer hallucinated tool names.
- Primary router endpoint: `POST /wp-json/mcp/v1/router`
- Standard MCP-style flow: `initialize` → `tools/list` → `tools/call`
- Session lifecycle for clients (pass `session_id` or `Mcp-Session-Id` after `initialize`)
- Built-in tool registry for common WordPress operations (posts, media, terms, menus, options, and more)
- Bundled Abilities API + MCP Adapter integration, with automatic bridging from registered abilities to MCP tools (configurable)
- WordPress 7.0/Core-aware bridge mode that uses Core Abilities/API surfaces when available and falls back only when needed
- Public tool policy controls (category filters and optional allowlists) plus optional internal tool exposure for private environments
- Bounded MCP audit log, optional per-user/role/client tool allowlists, and a read-only administrator health/status tool

**Security model (high level)**

- MCP access requires a real WordPress user context: Application Password over HTTP Basic, or an existing logged-in session.
- Optional site-wide or per-user API key and HMAC can be enabled in Settings as an additional gate (they do not replace WordPress authentication).
- Default access expectations for the router and `GET /wp-json/webo-mcp/v1/tools`: users who are super admins, can `manage_options`, or can `edit_posts`, consistent with typical site operator and editor workflows (filterable).

**Client guidance**

Always discover tools before calling them: run `tools/list`, pick an exact tool name from the response, validate required arguments, then call `tools/call`. This reduces mistakes and keeps automation predictable in production.

**Further documentation and optional integrations**

- Project documentation and ecosystem notes: https://webomcp.com
- Optional n8n community node (separate package): https://www.npmjs.com/package/n8n-nodes-webo-mcp
- Release notes and migration map: see docs/RELEASE_NOTES_2.1.0.md and docs/MIGRATION_GUIDE_2.1.0.md in the GitHub repository
- Cross-addon dispatcher map (granular legacy names removed from discovery): docs/MCP_TOOL_MIGRATION.md

Compatibility note: any MCP-capable client can be used; which large language model runs inside the client is outside this plugin.

Standalone core tools included:
- Site info
- Content (posts/pages): `webo/content-query` (list, get, find-by-url, search-replace, list-revisions, get-revision) and `webo/content-mutate` (create, update, delete, bulk-update-status, restore-revision)
- Users: list
- Media: `webo/media-query` (list, get) and `webo/media-mutate` (upload, update, delete)
- Comments: `webo/comment-query` (list, get) and `webo/comment-mutate` (update, delete)
- Taxonomy/Terms: `webo/taxonomy-query` (discover, list, get) and `webo/taxonomy-mutate` (create, update, delete)
- Nav menus: list menus, list menu items (menu_order, db_id), add menu link from post (explicit post_id + menu_order required)
- Plugins: `webo/plugin-query` (installed, active, updates, …) and `webo/plugin-mutate` (install, activate, deactivate; supports child-site `site_id` / `blog_id` activation for network admins)
- Health: `webo/health-status` (REST/router status, Application Password support, permalinks, cron, object cache, plugin update summary, WordPress/PHP versions, and redacted MCP config)
- Abilities bridge: `webo/ability-query` and `webo/ability-execute` in default layered mode. Only abilities with `meta.mcp.public === true` are visible and executable through WEBO MCP.
- Themes: `webo/theme-query` (installed themes) and `webo/theme-mutate` (install from WordPress.org by slug, switch installed theme)
- Menus: `webo/menu-query`, `webo/menu-mutate`
- Options: get/update (safe allowlist only), set site icon/favicon from media
- SEO (WordPress post): seo/article-analysis — requires post_id; merges Rank Math meta when available (same data path as webo-rank-math/get-post-seo-meta); optional related-keyword suggestions via outbound request unless no_autocomplete is true

Excluded by default in standalone-safe mode:
- Bulk/mass execution tools
- Plugin/theme write-management abilities
- Multisite-specific abilities

== Privacy ==

This plugin does not phone home or send telemetry. MCP traffic is initiated by clients you configure. Some tools may perform outbound HTTP requests only when a client invokes them (for example seo/article-analysis may request keyword suggestions from a third-party suggest API unless you pass no_autocomplete).

The plugin stores the following options in the WordPress database when configured:
- `webo_mcp_api_key`: API key used to authenticate MCP requests.
- `webo_mcp_hmac_secret`: HMAC secret used to sign and validate MCP requests.
- `webo_mcp_tool_allowlist_enabled` and `webo_mcp_tool_allowlist_rules`: optional administrator-configured MCP tool allowlist policy.
- `webo_mcp_audit_log_enabled`, `webo_mcp_audit_log_max_entries`, and `webo_mcp_audit_log`: bounded MCP tool-call audit log settings and compact audit events. Audit entries include user/tool/action/status data, anonymized IPs, and hashed session IDs; they do not store request payloads, API keys, HMAC secrets, or Application Passwords.

These options are removed when the plugin is uninstalled via the WordPress Plugins screen.

== External services ==

This plugin can connect to Google Suggest (Autocomplete) when a client calls the `seo/article-analysis` tool and does not set `no_autocomplete` to true. This external request is used to return related keyword suggestions for SEO analysis.

Service provider: Google LLC (Google Suggest / Autocomplete API endpoint).

Data sent and when:
- Sent only when `seo/article-analysis` is called with autocomplete enabled.
- Sends the analysis query text to `https://suggestqueries.google.com/complete/search` as the `q` parameter.
- Sends standard HTTP request metadata such as IP address and User-Agent as part of the web request.

Terms of Service: https://policies.google.com/terms
Privacy Policy: https://policies.google.com/privacy

== Developer Hooks ==

The plugin exposes the following actions and filters for developers:

=== Actions ===

- `webo_mcp_register_tools`
  Fired during plugin bootstrap after standalone tools are registered. Use this to register custom MCP tools from other plugins.

=== Filters ===

- `webo_mcp_current_user_can_use_mcp` (bool $allowed, int $user_id)
  Gate for all MCP REST access. Default: super admin OR `manage_options` OR `edit_posts`. Override to tighten (e.g. super-admin only) in hardened installs.

- `webo_mcp_allow_internal_tools` (bool $allow_internal, WP_REST_Request $request)
  Controls whether internal tools are included in tools/list responses. Defaults to false for public environments.

- `webo_mcp_public_categories` (array $categories, WP_REST_Request $request, array $tool)
  Filters which tool categories are exposed as public. Defaults to array( 'wordpress' ).

- `webo_mcp_public_tool_allowlist` (array $names, WP_REST_Request $request, array $tool)
  Optional allowlist of specific tool names that are always considered public.

- `webo_mcp_bridge_deny_patterns` (array $patterns)
  Controls which abilities are excluded when auto-bridging abilities into MCP tools (e.g. bulk, plugins/, themes/, multisite/).

- `webo_mcp_auto_bridge_abilities` (bool $enabled)
  Enables or disables automatic bridging of registered abilities into MCP tools. Defaults to true; bridge mode still controls whether the bridge is off, layered, or full.

- `webo_mcp_bridge_mode` (string $mode)
  Controls Abilities bridge mode after the `WEBO_MCP_BRIDGE_MODE` constant and before the stored option. Values: `off`, `layered`, `full`. Default: `layered`.

- `webo_mcp_enable_adapter` (bool $enabled)
  Enables or disables the bundled WordPress MCP Adapter runtime. Defaults to true.

- `webo_mcp_validate_media_fetch_url` (true|\WP_Error $ok, string $url, array $parsed)
  Reject unsafe URLs for webo/media-mutate upload action (return WP_Error to block).

- `webo_mcp_tool_allowlist_allowed` (bool $allowed, string $tool_name, WP_REST_Request $request, array $params, array $allowed_tools)
  Filters the optional per-user/role/client allowlist decision.

== Installation ==
1. Upload the plugin folder to /wp-content/plugins/webo-mcp
2. Run composer install inside the plugin folder
3. Activate the plugin in WordPress Admin
4. Send JSON-RPC requests to POST /wp-json/mcp/v1/router

For release packaging, use scripts/build-release.ps1 to create a clean zip with .distignore exclusions.

== Frequently Asked Questions ==

= Which endpoint should MCP clients use? =
POST /wp-json/mcp/v1/router

= Where is the official website and the n8n package? =
The project hub is https://webomcp.com. For n8n, install the community node from npm: https://www.npmjs.com/package/n8n-nodes-webo-mcp

= Can this run WordPress abilities by itself? =
Yes. On WordPress versions where Core provides the Abilities API, WEBO MCP uses Core and does not load a duplicate bundled Abilities API. On older WordPress versions it falls back to the bundled Composer package. The default bridge mode is `layered`, which exposes compact `webo/ability-query` and `webo/ability-execute` tools instead of one tool per ability. You can set bridge mode to `off`, `layered`, or `full` with `WEBO_MCP_BRIDGE_MODE`, the `webo_mcp_bridge_mode` filter, or the `webo_mcp_bridge_mode` option.

= Which abilities are exposed through WEBO MCP? =
Only abilities that explicitly set `meta.mcp.public` to true are exposed. Execution also checks the ability permission callback, WEBO allowlist/policy, and scope/risk metadata such as `meta.webo_mcp.scope` and `meta.webo_mcp.risk`.

= How do I migrate from legacy one-operation tool names? =
Use `tools/list` to discover the dispatcher tool names on your site, then pass the correct `action` (or query/mutate discriminant) for each operation. Use docs/MIGRATION_GUIDE_2.1.0.md for the 2.1.0 rollout narrative and docs/MCP_TOOL_MIGRATION.md for a consolidated addon-by-addon map (Rank Math, Rocket, WooCommerce groups, etc.).

= Can I expose internal tools? =
Yes, via filter webo_mcp_allow_internal_tools in private environments.

= Can I limit public tools by category? =
Yes, via filter webo_mcp_public_categories.

= Can I keep only WordPress.org-safe features? =
Yes. Default bridge rules exclude patterns for bulk, plugins/themes, and multisite abilities.

= Is this plugin suitable for production? =
Yes, when used with proper authentication, TLS, and a limited tool exposure policy.

= How do I authenticate MCP clients? =
Use a WordPress **Application Password** (Users → Profile → Application Passwords) and send it with HTTP Basic Auth (username = WordPress username, password = the application password). You can combine that with the optional **API Key** and **HMAC** values from Settings → WEBO MCP when those fields are set.

== Screenshots ==
1. MCP endpoint working in a REST client (initialize)
2. tools/list response with public tools
3. tools/call response for a WordPress tool

== Changelog ==
= 2.1.16 =
* Fix: `webo/content-mutate` can preserve raw block HTML for users with `unfiltered_html`, allowing admin page-builder content and inline styles to be managed through MCP.
* Security: users without `unfiltered_html` continue to pass content through the normal WordPress post KSES boundary.

= 2.1.15 =
* WordPress 7.0 readiness: add defensive feature detection for Abilities API, MCP Adapter, Connectors API, and `wp_supports_ai()`.
* Bootstrap: avoid loading duplicate bundled Abilities API or MCP Adapter code when Core/external implementations are already present.
* Bridge: add `off`, `layered` (default), and `full` modes. Layered mode exposes compact `webo/ability-query` and `webo/ability-execute` tools so `tools/list` stays small by default.
* Security: only bridge abilities with `meta.mcp.public === true`; execution now passes through ability permissions, WEBO policy/allowlist checks, and scope/risk gates.
* Health: extend `webo/health-status` with WordPress 7.0/Core AI/MCP compatibility diagnostics.
* Themes: extend `webo/theme-mutate` with WordPress.org theme install by slug; optional activation still requires `switch_themes`.

= 2.1.14 =
* Fix: bridge scoped plugin-management capabilities while network admins activate or deactivate plugins inside a child site.
* Keeps child-site plugin toggles explicit through `site_id` / `blog_id` without widening network-wide activation behavior.

= 2.1.13 =
* Added `webo/plugin-mutate` for WordPress.org plugin install, activation, and deactivation through the core plugin endpoint.
* Added `site_id` / `blog_id` support so network admins can activate or deactivate plugins for one multisite child site from the network MCP endpoint.
* Safety: rejects conflicting `site_id` / `blog_id` plus `network_activate` / `network_wide` requests so child-site activation and network-wide activation stay explicit.

= 2.1.12 =
* Added a bounded, admin-readable MCP audit log for `tools/call` events with user, tool/action, object ID when available, anonymized IP, hashed session ID, status, and compact result/error summaries.
* Added optional per-user, per-role, and per-client/Application Password tool allowlists. Enforcement is disabled by default to preserve existing access until an administrator opts in.
* Added read-only administrator tool `webo/health-status` covering REST/router status, Application Password support, permalinks, cron, object cache, plugin update summary, WordPress/PHP versions, and redacted MCP config status.

= 2.1.11 =
* Security: enforce object-level capabilities for MCP content/media mutations, including `edit_post`, `delete_post`, publish/private status changes, and taxonomy-specific term capabilities.
* Security: filter read tools by `read_post` and hide tools from `tools/list` when the authenticated user lacks the tool capability.
* Hardening: use WordPress safe HTTP validation for optional Google Suggest requests in `seo/article-analysis`.

= 2.1.10 =
* Fix: register **`webo/plugin-query`** in `Standalone_Tools` so MCP clients can list plugin updates (`query=updates`, optional `refresh=true`) and other inspection modes.
* Bootstrap: prime `WP_Abilities_Registry` at `init:2` so `wp_abilities_api_init` runs before MCP bootstrap (`init:20`), preventing `_doing_it_wrong` when abilities register on `webo_mcp_register_tools`.

= 2.1.9 =
* Refactor: move built-in MCP tool registration into `inc/bootstrap/class-standalone-tools.php` (smaller bootstrap; same tool names).
* Maintainer: broaden `.gitignore` (composer `vendor/bin/`, Cursor local config, scratch files); ship `scripts/` helpers and `docs/WPORG_REVIEW_REPLY_2.0.28.md`; keep `composer.json` production-only.

= 2.1.8 =
* BOM guard: fix PCRE — use `^(?:\xEF\xBB\xBF)+` so **multiple** UTF-8 BOMs are stripped (the old `^\xEF\xBB\xBF+` only repeated the final `0xBF` byte, breaking responses such as `wp/v2/types` on `webo.vn` for MCP clients).

= 2.1.7 =
* BOM guard: sanitize **all** REST API requests (`/wp-json/…`, `wp-json.php`, `?rest_route=`) by default (`webo_mcp_rest_bom_guard_json_api_requests` filter to disable).

= 2.1.6 =
* BOM guard: treat **Abilities API** REST URLs (`wp-abilities/v1`) the same as MCP router URLs — `@automattic/mcp-wordpress-remote` calls discover/execute over `wp-abilities`, which previously skipped the sanitizer.

= 2.1.5 =
* BOM guard: also start the sanitizer on `plugins_loaded` and `init` at priority `-999999` when the request URI looks MCP (covers BOM echoed before `rest_api_init`).

= 2.1.4 =
* BOM guard: start output buffer at `rest_api_init` priority 0 when Request-URI/`rest_route` looks like MCP (catches BOM printed before routing); loop-strip repeated BOM/FEFF; filter `webo_mcp_rest_bom_guard_enabled` to disable.

= 2.1.3 =
* REST: strip accidental UTF-8 BOM / stray U+FEFF before JSON on MCP routes so clients no longer fail JSON parse with `Unexpected token` (defensive `ob_start` handler on `rest_pre_dispatch`).

= 2.1.2 =
* Restore the versioned **`skills/`** subtree in the Git repository (guides and ability-specific SKILL.md files referenced from README.md), matching the documented `npx skills add` workflows.
* Add **`webo-mcp-ultimo-domain-dns-cf`** skill index entry (Ultimo checking-dns + Cloudflare checklist).
* skills/README.md: document **WP Rocket** skill (`cache-query` / `cache-mutate` unified tools).

= 2.1.1 =
* Documentation: add docs/MCP_TOOL_MIGRATION.md (cross-addon dispatcher map; Rank Math + Rocket public-vs-internal discovery, WooCommerce query/mutate tool names).
* Readme (GitHub + WordPress.org): align examples with `webo/content-query`, document `meta.mcp.public` visibility for bridged abilities, link migration doc; sync standalone tool bullets (menus, themes, plugins).
= 2.1.0 =
* **Token optimization — ecosystem-wide enum-dispatch unification.** All WEBO MCP addons now follow the same query/mutate pattern as the core plugin, replacing one-tool-per-operation APIs with unified abilities that accept an `action` argument:
  * **webo-mcp-woocommerce:** 27 individual tools → 10 unified tools (`webo/woo-query-products`, `webo/woo-mutate-products`, `webo/woo-query-orders`, `webo/woo-mutate-orders`, `webo/woo-query-customers`, `webo/woo-mutate-customers`, `webo/woo-query-coupons`, `webo/woo-mutate-coupons`, `webo/woo-query-store`, `webo/woo-mutate-store`).
  * **webo-mcp-rank-math:** 18 individual tools → 10 unified `webo-rank-math/*-query`/`*-mutate` abilities; granular abilities may stay MCP-internal (see addon `wp_register_ability_args`).
  * **webo-mcp-rocket:** 9 individual tools → 2 unified tools (`webo-rocket/cache-query`, `webo-rocket/cache-mutate`).
* **Impact:** with core and addons active the total tool count visible in `tools/list` drops from ~79+ to ~34. A smaller tool list means the model picks tools faster, uses less context budget per request, and makes fewer tool-name errors.
* **Pattern:** each unified ability requires one `action` string that is dispatched server-side via PHP `match()`. All existing handler logic is preserved — only the registration surface changes.
* Updated skills documentation for webo-mcp-ability-woocommerce, webo-mcp-ability-rank-math, webo-mcp-ability-rocket, and webo-mcp-guide.

= 2.0.45 =
* Refactor: unify media tools into `webo/media-query` (list, get) and `webo/media-mutate` (upload, update, delete); removes 5 legacy media tools.
* Refactor: unify taxonomy/term tools into `webo/taxonomy-query` (discover, list, get) and `webo/taxonomy-mutate` (create, update, delete); removes 6 legacy term tools.
* Refactor: unify comment tools into `webo/comment-query` (list, get) and `webo/comment-mutate` (update, delete); removes 4 legacy comment tools.
* Refactor: unify post/content tools into `webo/content-query` and `webo/content-mutate`; removes 17 legacy post tools.
* Refactor: replace `webo/list-active-plugins` with unified `webo/plugin-query` (list, get, activate, deactivate).
* All unified tools normalize the `id` field as the primary response identifier; domain aliases (attachment_id, term_id, comment_id) are kept for backward compatibility.
* SEO: improved Unicode word count using Unicode ranges for multilingual content; non-spaced scripts (CJK, Thai) now estimate word count via character-based heuristics.

= 2.0.44 =
* SEO readability: estimate word count for non-spaced scripts (CJK, Thai, Khmer) via character-based heuristics.

= 2.0.43 =
* SEO readability: count Unicode title and meta description lengths correctly for non-ASCII characters.

= 2.0.42 =
* SEO readability: count Unicode words correctly for multilingual content.

= 2.0.41 =
* Media/site settings: add `webo/set-site-icon` to set the WordPress site icon/favicon from an existing image attachment.

= 2.0.40 =
* MCP post tools: add page, offset, orderby, and order support to webo/list-posts responses for reliable batch processing.
* SEO analyzer: infer the WordPress post title as the primary H1 when content omits an H1, and include Article JSON-LD from post/Rank Math metadata for more accurate schema checks.

= 2.0.39 =
* Options: allow safe permalink/category/tag base updates through MCP and flush rewrite rules after URL setting changes.

= 2.0.38 =
* MCP post tools: preserve safe HTML for post content/excerpt arguments so SEO headings, lists, tables, and images can be authored through create/update calls.

= 2.0.35 =
* New site-management tools: `webo/list-themes` and `webo/switch-theme` for discovering installed themes and switching the active theme by stylesheet slug.
* Readme: release includes the shortened WordPress.org short description, keeping the short description under the 150 character import limit.

= 2.0.34 =
* Options: allow `webo/update-options` and `webo/get-options` to handle `show_on_front` and `page_on_front`.
* Safety: validate `show_on_front` as `posts|page` and ensure `page_on_front` references a valid Page ID (or 0).

= 2.0.33 =
* Readme: refresh WordPress.org-facing description for clarity; lead with product value and protocol workflow, move ecosystem links to a secondary section.

= 2.0.32 =
* Fix: avoid WP 6.9 Abilities API incorrect-usage notices by hardening adapter category/ability registration order and late-boot recovery.
* Docs: add AGENTS.md workflow guidance and prioritize guide-first structure in README/readme.txt.

= 2.0.31 =
* Maintenance: version bump.

= 2.0.30 =
* Maintenance: version bump.

= 2.0.29 =
* Reliability: harden MCP adapter bootstrap and schema type handling (supports array/nullable type definitions safely).
* WP-CLI noise reduction: register core mcp-adapter abilities before bridge wiring and suppress default adapter server bootstrap in CLI mode to avoid false missing-ability errors.
* Release notice: users running WEBO MCP Pro should update Pro package compatibility notes from the official docs/release channel before production rollout.

= 2.0.28 =
* WordPress.org review fixes: added explicit `External services` disclosure for Google Suggest/Autocomplete used by `seo/article-analysis` (service purpose, transmitted query data and request metadata, conditions, Terms and Privacy links).
* Compatibility: removed use of `WPINC` for nav-menu API loading; now load nav-menu API via explicit core include paths with availability checks to reduce environment-specific path issues.

= 2.0.27 =
* Security (WordPress.org guidelines): MCP router no longer maps API keys or HMAC to arbitrary user accounts. All requests require WordPress Application Password (Basic Auth) or an existing logged-in session; optional site API key and HMAC apply only after authentication.
* Readme: Contributors includes phuongwebo; clarify authentication in description and FAQ.

= 2.0.26 =
* New MCP tool seo/article-analysis (category seo, edit_posts): WordPress-only on-page SEO signals for a post via post_id — rendered content, Rank Math merge, readability, issues, content_gaps. Agent documentation: skills/webo-mcp-seo-article/SKILL.md in the GitHub repo (not bundled in the WordPress.org zip).
* Readme: Stable tag sync, privacy note for optional outbound tool requests.

= 2.0.25 =
* list-posts: document defaults (publish + post type post); response includes applied filters so empty results are easier to explain. Models should pass status draft (etc.) and post_type page when listing those.

= 2.0.24 =
* Nav menus: list-nav-menu-locations response includes note explaining slug vs label; MCP descriptions tell models to call this tool first to discover theme_location keys.

= 2.0.23 =
* Nav menus: list-nav-menus response includes menu_id (same as term_id) and clearer MCP tool descriptions so clients list menus without asking users for menu_id first.

= 2.0.22 =
* Nav menus: if create-nav-menu / create-nav-menu-for-location targets a name that already exists, reuse the existing menu term and continue (reused_existing_menu in JSON). Return a clear error if core nav-menu.php cannot be loaded. Expanded primary fallback slugs (primary-menu, header-menu, mobile). assign-nav-menu-to-location accepts menu_name when menu_id is omitted (assigned_via_menu_name in response).

= 2.0.21 =
* Nav menus: resolve theme location when slug primary is missing (single registered slot, or common slugs main/header/menu-1/navigation). Load wp-includes/nav-menu.php before wp_create_nav_menu in REST context. Response field theme_location_resolution indicates how the slug was chosen.

= 2.0.20 =
* Access: MCP router gate allows `manage_options` and `edit_posts` (Editors, site admins on multisite), not only `is_super_admin`; fixes list-nav-menus / tools/call failing for non-administrator users. Multisite API key/HMAC falls back to first site Administrator if no Super Admin login exists. Error code `webo_mcp_access_denied` replaces misleading super-admin-only message.

= 2.0.15 =
* Nav menus: list-nav-menus, list-nav-menu-items (db_id, menu_order, object_id, parent_db_id), add-nav-menu-item-from-post with required post_id, post_type, and menu_order (explicit developer values; no auto placement).

= 2.0.14 =
* Security: MCP JSON-RPC router, SecurityHelper, tools discovery, and internal-tool policy default to network Super Admin on multisite (`is_super_admin`). Single-site installs use WordPress core’s `is_super_admin()` behavior (typically full administrators). Global API key/HMAC elevates to the first Super Admin user on multisite.

= 2.0.7 =
* Readme: highlight https://webomcp.com and n8n community node https://www.npmjs.com/package/n8n-nodes-webo-mcp; short description and FAQ; README.md aligned.

= 2.0.6 =
* License: plugin header uses the same wording as readme.txt ("GPL v2 or later") to satisfy WordPress.org declared-license checks.

= 2.0.5 =
* Plugin header: @wordpress-plugin marker for strict scanners; License line uses GPLv2 or later slug (Plugin Handbook).

= 2.0.4 =
* Plugin header: handbook field order, shorter Description line, License text "GPL v2 or later", Domain Path for translations.

= 2.0.3 =
* WordPress.org / Plugin Check: include composer.json when vendor is bundled; replace unlink with wp_delete_file for temp uploads; remove load_plugin_textdomain (core loads translations); resolve API key usermeta via get_users instead of direct $wpdb; readme short description, allowed Tags, Stable tag sync.

= 2.0.2 =
* WordPress.org packaging: release zip excludes dotfiles and all .github trees; readme Tested up to 6.9.

= 2.0.1 =
* Hardening: HMAC auth passes REST permission layer; SSRF guard for upload-media-from-url; paginated search-replace (max 500 posts per call); sanitized safe option updates; removed duplicate unused settings class.

= 2.0.0 =
* Plugin renamed to WEBO MCP; folder and main file: webo-mcp/webo-mcp.php.
* Text domain, REST namespace webo-mcp/v1, hooks webo_mcp_* (breaking for custom code using old hook names).
* Options and API-key usermeta migrate automatically from webo-wordpress-mcp keys on first load.

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
= 2.1.14 =
Recommended for multisite networks using child-site plugin activation through MCP; fixes WordPress capability checks after `switch_to_blog()`.

= 2.1.13 =
Adds core plugin mutation plus child-site plugin activation/deactivation via `site_id` or `blog_id` for multisite network admins.

= 2.1.12 =
Adds MCP audit logging, optional tool allowlists, and an administrator health/status tool. Existing MCP access remains unchanged unless allowlist enforcement is enabled in Settings.

= 2.1.11 =
Recommended security hardening release: MCP tools now enforce object-level post/media/term capabilities and only list tools the current user can call.

= 2.1.10 =
Registers the missing **`webo/plugin-query`** tool (plugin inventory and updates via MCP). Recommended for automation that lists pending plugin updates.

= 2.1.9 =
Internal refactor (standalone tool bootstrap file only). No MCP tool renaming; safe routine update.

= 2.1.8 =
Critical for `webo.vn` / multi-BOM REST bodies: fixes BOM sanitizer regex so repeated UTF-8 BOM prefixes are actually removed.

= 2.1.7 =
Recommended if MCP/remote clients still hit `Unexpected token` / invalid JSON — BOM strip now defaults on for **all** REST API responses.

= 2.1.6 =
Use this if MCP clients still fail JSON parse on `discover-abilities` / ability tools — BOM strip now covers `wp-abilities` REST routes.

= 2.1.5 =
If MCP clients still parse-fail on BOM: this release starts the BOM-stripping buffer before `rest_api_init` for MCP-like URLs.

= 2.1.4 =
Further hardening for leading-BOM MCP JSON failures: earlier buffer bootstrap on MCP-like REST URLs.

= 2.1.3 =
Recommended if MCP clients show JSON parse errors (leading BOM) on `tools/list` or `tools/call` — response body is sanitized for MCP REST routes.

= 2.1.2 =
Restores packaged agent **`skills/`** in the upstream repo clone; upgrade if you rely on Cursor/Codex skills from GitHub.

= 2.1.1 =
Documentation-only refresh: use docs/MCP_TOOL_MIGRATION.md when mapping old MCP tool names to dispatchers + `action`. No behavioral change vs 2.1.0 expected.

= 2.0.40 =
Recommended update for MCP clients that batch process posts or rely on seo/article-analysis; list-posts pagination and H1/schema detection are more accurate.

= 2.0.35 =
Adds theme discovery and theme switching tools for MCP clients. This release also carries the shortened WordPress.org short description into the new tagged version.

= 2.0.34 =
Recommended update if you manage homepage reading settings via MCP; adds safe support for `show_on_front` and `page_on_front` updates.

= 2.0.33 =
Documentation-only refresh on WordPress.org listings; recommended if you rely on the plugin directory description for onboarding.

= 2.0.32 =
Recommended update for WP 6.9+ sites using Abilities API and MCP adapter integration.

= 2.0.31 =
Maintenance update.

= 2.0.30 =
Maintenance update.

= 2.0.29 =
Maintenance update for runtime stability and cleaner CLI output. If you use WEBO MCP Pro, review/update the Pro package compatibility notice before deploying this version to production.

= 2.0.28 =
WordPress.org compliance update: readme now documents Google Suggest external service usage with Terms/Privacy links, and nav-menu API loading no longer relies on WPINC.

= 2.0.27 =
MCP clients must send WordPress Application Password (HTTP Basic) or use a logged-in session. API key/HMAC alone are no longer sufficient when calling the router.

= 2.0.26 =
Adds seo/article-analysis for post-level SEO diagnostics (optional outbound suggest API; set no_autocomplete to skip).

= 2.0.7 =
Readme and GitHub README now link webomcp.com and the n8n-nodes-webo-mcp npm package.

= 2.0.6 =
License declaration aligned between readme and main plugin file for WordPress.org review.

= 2.0.5 =
Plugin header updates for Plugin Check and WordPress.org tooling (@wordpress-plugin, GPLv2 license slug).

= 2.0.4 =
Plugin header formatting for WordPress.org Plugin Check (Description, Version, License).

= 2.0.3 =
Plugin Check and packaging fixes; upload the release zip from scripts/build-release.ps1 for WordPress.org.

= 2.0.2 =
Packaging and readme updates for WordPress.org review. Always upload the zip from scripts/build-release.ps1, not the raw git folder.

= 2.0.0 =
Major rename: reinstall from folder webo-mcp (or deploy to new path), then activate WEBO MCP. Settings are preserved via migration.

= 1.1.1 =
Recommended update to fix tools/call validation for core tools with no input.

= 1.0.2 =
Recommended update to support active plugin verification via MCP tool.

= 1.0.1 =
Recommended update to refresh plugin metadata and improve tools/list compatibility.

= 1.0.0 =
Initial public release of WEBO MCP (formerly WEBO WordPress MCP).

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
