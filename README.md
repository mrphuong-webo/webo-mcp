# WEBO MCP

Token-optimized MCP gateway for WordPress. Unified query/mutate tools cut the `tools/list` payload by up to 70% — less context consumed, lower cost, fewer tool-name errors.

## Recent Changes

### 2.1.15 - WordPress 7.0 Core-aware MCP bridge

- Added defensive WordPress/Core feature detection for Abilities API, MCP Adapter, Connectors API, and `wp_supports_ai()`.
- Guarded fallback dependency loading so Core/external Abilities API and MCP Adapter implementations are not duplicated.
- Added bridge modes: `off`, `layered` (default), and `full`. Layered mode keeps `tools/list` compact with `webo/ability-query` and `webo/ability-execute`.
- Enforced `meta.mcp.public === true`, ability permission checks, WEBO allowlist policy, and scope/risk gates before bridged ability execution.
- Extended `webo/health-status` with WordPress 7.0/Core AI/MCP diagnostics.
- Extended `webo/theme-mutate` with WordPress.org theme install by slug; optional activation still requires theme-switch capability.

### 2.1.14 - Child-site plugin toggle capability bridge

- Fixed child-site plugin activation/deactivation for network admins by bridging scoped plugin-management capabilities after `switch_to_blog()`.
- Keeps `site_id` / `blog_id` child-site targeting explicit without widening network-wide activation behavior.

### 2.1.13 — Plugin mutation + multisite child activation

- Added `webo/plugin-mutate` as the unified plugin write tool (`install`, `activate`, `deactivate`).
- Added `site_id` / `blog_id` support so network admins can activate or deactivate a plugin for one child site from the network MCP endpoint.
- Kept the 2.1.12 SVN audit/health release changes in Git so the next release includes both tracks.

### 2.1.12 — Audit log, allowlists, and health status

- Added bounded MCP `tools/call` audit logging.
- Added optional per-user, per-role, and per-client/Application Password tool allowlists.
- Added `webo/health-status` for administrator diagnostics.

### 2.1.2 — Restore `skills/` in git + index updates

- The **`skills/`** directory (Cursor/Codex `SKILL.md` packs: guide, wordpress-content, WooCommerce, Rocket, Rank Math, Ultimo DNS checklist, …) is **tracked again** in this repository — links under [skills/README.md](skills/README.md) work from a plain `git clone`.
- **`skills/README.md`** now indexes **WP Rocket** (`cache-query` / `cache-mutate`) and **`webo-mcp-ultimo-domain-dns-cf`** (Ultimo `checking-dns` + Cloudflare).
- Repo **README.md** pointers updated to reflect the fuller skill catalog.

### 2.1.1 — Documentation and MCP visibility

- **`docs/MCP_TOOL_MIGRATION.md`** lives in-repo: consolidated **old tool name → dispatcher + `action`** map for addons (Rank Math redirect layer, Rocket `cache-query`/`cache-mutate`, WooCommerce, Elementor, and others).
- **Ability bridge + `meta.mcp.public`:** WEBO MCP auto-bridges WordPress abilities into tools. Addon code should expose **`public: true` only on dispatchers**. Extra granular abilities remain useful for REST or debugging but stay **MCP-internal** until `tools/list` is called with `include_internal` (see filters below).
- **`webo-mcp-rank-math`**: public MCP surface = **ten** unified `*-query` / `*-mutate` tools; granular redirection abilities stay internal unless you widen discovery.
- **`webo-mcp-rocket`**: public MCP surface = **`webo-rocket/cache-query`** and **`webo-rocket/cache-mutate`** only (nine legacy per-operation tool names removed from discovery).

### 2.1.0 — Ecosystem-wide enum-dispatch unification

All WEBO MCP addons now follow the same query/mutate pattern as the core plugin.

| Addon | Before (typical) | After (dispatcher count) |
|-------|------------------|---------------------------|
| webo-mcp-woocommerce | many per-operation tools | **10** dispatcher tools (`webo/woo-query-*` and `webo/woo-mutate-*` per domain; each takes an `action`) |
| webo-mcp-rank-math | many per-operation tools | 10 unified `*-query` / `*-mutate` abilities |
| webo-mcp-rocket | 9 per-operation MCP tools | 2 unified tools (`cache-query`, `cache-mutate`) |
| … | varies by site | **`tools/list` shrinks materially** |

**What changed:** each domain exposes **`*-query` / `*-mutate` style** dispatchers where appropriate. The client passes a discriminator such as **`action`**; the server dispatches (often via PHP `match()`). Implementations evolve per addon release — discovery always wins over static counts.

**Why it matters for AI agents:**
- Smaller `tools/list` payload → fewer tokens consumed from the model's context window
- Fewer tool names to choose from → faster, more accurate tool selection
- Consistent dispatcher + `action` pattern across domains → easier agent prompting and skill authoring

**Operational docs for this release:**
- Cross-addon migration map: **`docs/MCP_TOOL_MIGRATION.md`**
- Release notes: `docs/RELEASE_NOTES_2.1.0.md`
- Deep-dive migration (2.1.0): `docs/MIGRATION_GUIDE_2.1.0.md`
- tools/list benchmark runbook: `docs/BENCHMARK_TOOLS_LIST.md`
- Agent prompt snippets (Codex/Cursor/n8n): `docs/AGENT_SNIPPETS.md`
- Smoke test script: `scripts/smoke-unified-dispatch.ps1`
- Benchmark script: `scripts/benchmark-tools-list.ps1`

### 2.0.35

- Added `webo/list-themes` to discover installed themes and active theme status.
- Added `webo/switch-theme` to switch the active theme by stylesheet slug.
- Added `webo/plugin-query` as the unified plugin inspection tool (`installed`, `active`, `updates`, `network-active`, `rental-candidates`, `health`) with optional `scope`, `refresh`, and `fields`.
- Added `webo/plugin-mutate` as the unified plugin write tool (`install`, `activate`, `deactivate`). `install` downloads a WordPress.org plugin by slug and can optionally activate it site-wide or network-wide when permitted.
- Added `site_id` / `blog_id` support to `webo/plugin-mutate` so network admins can activate or deactivate a plugin for one multisite child site from the network MCP endpoint.
- Release includes the shortened WordPress.org short description so the next tagged import stays under the 150 character limit.

## Quick Start

- MCP endpoint: `POST /wp-json/mcp/v1/router`
- Required method order: `initialize` -> `tools/list` -> `tools/call`
- Authentication: WordPress Application Password (HTTP Basic) or logged-in WordPress session
- Optional second layer: `X-WEBO-API-KEY` and HMAC signature (if configured in plugin settings)

## Safe Agent Workflow

For Codex/Cursor/other MCP clients:

1. Discover tools first (`tools/list`).
2. Select exact tool names from discovered output only.
3. Validate required arguments before `tools/call`.
4. Explain destructive operations before running them.
5. Never print secrets (passwords, tokens, cookies, auth headers).

See `AGENTS.md` for the repository rule set used by WEBO MCP agent flows.

## Dependencies

- This plugin uses WordPress Core Abilities API when available and falls back to bundled `wordpress/abilities-api` only on older WordPress versions.
- This plugin can use an existing MCP Adapter package when available and otherwise registers its bundled adapter autoloader.
- Run `composer install` in plugin root before activation on environments that do not include `vendor/` in deployment.

## WordPress 7.0 Core-aware bridge

WEBO MCP keeps its production router endpoint: `POST /wp-json/mcp/v1/router`. It does not replace that endpoint with the official MCP Adapter default server. The official adapter may exist separately; WEBO MCP remains the policy, audit, addon, and token-optimization gateway layer for production clients.

Bridge modes are controlled by `WEBO_MCP_BRIDGE_MODE`, then the `webo_mcp_bridge_mode` filter, then the `webo_mcp_bridge_mode` option. Supported values:

- `off`: do not expose WordPress abilities through WEBO MCP.
- `layered`: default. Exposes compact `webo/ability-query` and `webo/ability-execute` tools only.
- `full`: private/developer mode. Exposes individual public abilities as MCP tools when safe.

Only abilities with `meta.mcp.public === true` are visible to the bridge. Execution also runs the ability permission callback, WEBO policy checks, and scope/risk gates. `webo/health-status` reports Core-aware diagnostics including WordPress/PHP version, Abilities API source, MCP Adapter source, Connectors API presence, `wp_supports_ai()` availability/enabled state, and bridge counts.

## External services

When a client calls `seo/article-analysis` and does not set `no_autocomplete=true`, the plugin requests related keyword suggestions from Google Suggest/Autocomplete.

- Service: Google Suggest (Google LLC)
- Purpose: related keyword suggestions used in SEO analysis output
- Data sent: query text (`q` parameter) and normal HTTP request metadata (for example IP address and User-Agent)
- When sent: only during `seo/article-analysis` calls with autocomplete enabled
- Terms of Service: https://policies.google.com/terms
- Privacy Policy: https://policies.google.com/privacy

## Build release package

- Windows PowerShell:
  - `cd scripts`
  - `./build-release.ps1`
- Output zip: `dist/webo-mcp-<version>.zip`
- Exclusions are controlled by `.distignore`

## Quick MCP + n8n setup

- **WEBO n8n node (npm):** [n8n-nodes-webo-mcp](https://www.npmjs.com/package/n8n-nodes-webo-mcp) — install in n8n and point at your router URL.
- Alternative remote MCP package: `@automattic/mcp-wordpress-remote`
- Example router URL for env/config: `https://your-site.com/wp-json/mcp/v1/router`

## Project links

- Website: [webomcp.com](https://webomcp.com)
- Author: Dinh WP ([dinhwp.com](https://dinhwp.com))

## Credits

Special thanks to the authors and open source projects that contributed to this plugin:
- [WordPress](https://wordpress.org)
- [Abilities API](https://github.com/WordPress/abilities-api) ([Reference](https://make.wordpress.org/ai/2025/07/17/abilities-api/))
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) ([Reference](https://make.wordpress.org/ai/2025/07/17/mcp-adapter/))
- [Composer](https://getcomposer.org)
- Other PHP and JS libraries from the community

## Public agent skill (WordPress content over MCP)

For **Cursor**, **Codex**, or other agents that support project skills: a maintained skill maps content-editing workflows (like the [wordpress-content](https://skills.sh/jezweb/claude-skills/wordpress-content) pattern) to **`webo/*` MCP tools**.

- **Documentation:** [skills/README.md](skills/README.md) (full skill index — WooCommerce, **WP Rocket** `cache-query`/`cache-mutate`, WP Ultimo domain/DNS troubleshooting, Rank Math, menus, SEO, …)
- **Skills (starting points):** [skills/webo-mcp-wordpress-content/SKILL.md](skills/webo-mcp-wordpress-content/SKILL.md) (full `webo/*` reference), [skills/webo-mcp-menu-creation/SKILL.md](skills/webo-mcp-menu-creation/SKILL.md), [skills/webo-mcp-ability-rank-math/SKILL.md](skills/webo-mcp-ability-rank-math/SKILL.md), [skills/webo-mcp-rank-math-redirections/SKILL.md](skills/webo-mcp-rank-math-redirections/SKILL.md), [skills/webo-mcp-ability-rocket/SKILL.md](skills/webo-mcp-ability-rocket/SKILL.md), [skills/webo-mcp-ultimo-domain-dns-cf/SKILL.md](skills/webo-mcp-ultimo-domain-dns-cf/SKILL.md)
- **Rank Math (optional add-on):** [webo-mcp-rank-math](https://github.com/mrphuong-webo/webo-mcp-rank-math) — install and activate on the WordPress site alongside WEBO MCP and [Rank Math SEO](https://rankmath.com/); exposes **`webo-rank-math/*`** tools via the [Abilities API](https://github.com/WordPress/abilities-api) bridge
- **Install via [skills](https://github.com/vercel-labs/skills) CLI:**  
  `npx skills add https://github.com/mrphuong-webo/webo-mcp --skill webo-mcp-wordpress-content -a cursor -g -y`  
  (change `-a cursor` for your agent; use `--list` to preview.)

## AI training references

- MCP method schema and examples: use this file + `examples/addon-rankmath-example.php` (minimal custom `webo_mcp_register_tools` demo; production Rank Math automation uses the [webo-mcp-rank-math](https://github.com/mrphuong-webo/webo-mcp-rank-math) addon)
- Internal/public policy filters for training data:
  - `webo_mcp_allow_internal_tools`
  - `webo_mcp_public_categories`
  - `webo_mcp_public_tool_allowlist`

## Architecture

AI Agent -> MCP Request -> Tool Router -> Tool Registry -> Tool Execution

## MCP Router

- Class: `WeboMCP\Core\Router\McpRouter`
- Location: `inc/router/class-mcp-router.php`
- JSON-RPC endpoint: `POST /wp-json/mcp/v1/router`
- SSE endpoint: `GET /wp-json/mcp/v1/router`
- SSE alias endpoint: `GET /wp-json/mcp/v1/router/sse`
- Legacy compatibility endpoint: `POST /wp-json/mcp/mcp-adapter-default-server`
- Legacy SSE compatibility endpoint: `GET /wp-json/mcp/mcp-adapter-default-server`
- Supported methods:
  - `initialize`
  - `tools/list`
  - `tools/call`

### SSE quick notes

- SSE requires the same auth policy as JSON-RPC (WordPress Application Password/session, then optional API key/HMAC if configured).
- Pass `session_id` via query string or `Mcp-Session-Id` header.
- Optional query `wait` keeps the stream open for heartbeat comments (max 25 seconds).
- JSON-RPC POST flow remains unchanged and fully backward compatible.

### JSON-RPC request example

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "session_id": "abc123",
    "name": "webo/content-query",
    "arguments": {
      "action": "list",
      "post_type": "post",
      "status": "publish",
      "per_page": 10
    }
  },
  "id": 1
}
```

### initialize flow

1. Router validates JSON-RPC payload
2. Router creates session via `SessionManager::create()`
3. Router returns `session_id` + capabilities

Example response:

```json
{
  "jsonrpc": "2.0",
  "result": {
    "session_id": "abc123",
    "capabilities": {
      "tools": true,
      "methods": ["initialize", "tools/list", "tools/call"]
    }
  },
  "id": 1
}
```

### tools/list flow

1. Router reads `ToolRegistry::list_tools()`
2. Router returns MCP tool metadata list
3. By default only tools with `visibility = public` are returned
4. By default only category `wordpress` (WordPress.org core features) is allowed

To allow internal tools (`visibility = internal`) in a private environment:

```php
add_filter( 'webo_mcp_allow_internal_tools', '__return_true' );
```

To allow additional public categories beyond `wordpress`:

```php
add_filter( 'webo_mcp_public_categories', function () {
  return [ 'wordpress', 'custom-public' ];
}, 10, 3 );
```

### tools/call flow

1. Router validates security (WordPress Application Password / session, then optional `X-WEBO-API-KEY` and HMAC if configured)
2. Router validates session (`params.session_id` or `Mcp-Session-Id` header)
3. Router validates tool name and arguments
4. Router checks visibility policy (`public`/`internal`)
5. Router executes tool via `ToolRegistry::call()`
5. Router returns JSON-RPC result

### tools/list troubleshooting (domain-specific)

- JSON-RPC `tools/list` now returns `meta` with:
  - `registered_total`
  - `returned_total`
  - `include_internal`
- Admin can request internal tools with params:

```json
{
  "jsonrpc": "2.0",
  "method": "tools/list",
  "params": { "include_internal": true },
  "id": 1
}
```

- Backward-compatible aliases are accepted for older clients:
  - `includeinternal`
  - `includeInternal`

- Diagnostics REST endpoint also supports admin query:
  - `GET /wp-json/webo-mcp/v1/tools?include_internal=1`

### Error format

```json
{
  "jsonrpc": "2.0",
  "error": {
    "code": -32601,
    "message": "Method not found"
  },
  "id": 1
}
```

## Main class

- `WeboMCP\Core\Registry\ToolRegistry`
- Location: `inc/registry/class-tool-registry.php`

## Supported features

- Register tools (`register`)
- Get one tool (`get`)
- List all tools (`list`)
- List by category (`list_by_category`)
- Execute tool (`call`)
- MCP tools/list payload (`list_tools`)
- Argument schema validation
- Optional capability-based access control (`permission`)

## Standalone primary mode

Built-in standalone tools cover core WordPress operations:

- Site info
- Posts (list/get/create/update/delete single)
- Users (list)
- Media (unified query/mutate)
- Comments (unified query/mutate)
- Taxonomy (unified query/mutate)
- Plugins (unified query)
- Options (safe allowlist read/update)

Excluded by default for WordPress.org-safe behavior:

- Bulk/mass execution features
- Plugin/theme write-management
- Multisite-specific abilities

## New tools (content discovery)

Three tools align with MCP servers like [mcp-wordpress-instaWP](https://glama.ai/mcp/servers/pace8/mcp-wordpress-instaWP) for discovery and URL-based access:

| Tool | Description | Arguments |
|------|-------------|-----------|
| `webo/discover-content-types` | List public post types (name, label, description, hierarchical, has_archive). | None. |
| `webo/find-content-by-url` | Resolve a WordPress URL (path or full URL) to content; returns post data. Optionally pass `update` (object with `title`, `content`, `status`) to update in the same call (requires `edit_posts`). | `url` (required), `update` (optional array). |
| `webo/get-content-by-slug` | Get content by slug (`post_name`). Search in one `post_type` or across all public types. | `slug` (required), `post_type` (optional). |

- **find_content_by_url** uses WordPress `url_to_postid()`; works best with pretty permalinks.

**Taxonomy tools (InstaWP parity):**

| Tool | Description | Arguments |
|------|-------------|-----------|
| `webo/taxonomy-query` | Unified read-only taxonomy tool with actions `discover`, `list`, `get`. | `action` (required), plus `taxonomy`, `per_page`, `term_id` as needed. |
| `webo/taxonomy-mutate` | Unified taxonomy write tool with actions `create`, `update`, `delete`. | `action` (required), plus `term_id`, `taxonomy`, `name`, `slug`, `description`, `parent_id` as needed. |
| `webo/content-mutate` (`assign-terms`) | Assign terms to a post (replaces existing for that taxonomy). | `action: assign-terms`, `post_id` (or `id` alias), `taxonomy`, `term_ids`. |
| `webo/content-query` (`get-terms`) | Get all terms assigned to a post; optional taxonomy filter. | `action: get-terms`, `post_id` or `id`, `taxonomy` (optional). |

- Content ID alias: for common post/page tools (`webo/get-post`, `webo/update-post`, `webo/delete-post`, `webo/list-revisions`, `webo/set-post-featured-image`, taxonomy assignment tools), both `post_id` and `id` are accepted to reduce client mistakes.
- See `docs/TOOLS_COMPARISON.md` for a full InstaWP ↔ webo tool mapping.

**Plugin query (unified):**

| Tool | Description | Arguments |
|------|-------------|-----------|
| `webo/plugin-query` | Unified read-only plugin query with enum allowlist; supports `installed`, `active`, `updates`, `network-active`, `rental-candidates`, `health`. Uses `wp_update_plugins()` + `get_site_transient('update_plugins')` when `refresh=true` for update checks. | `query` (required), `scope` (optional: `all`, `active`, `network-active`), `refresh` (optional bool), `fields` (optional array projection). |
| `webo/plugin-mutate` | Unified plugin write tool. Supports `install` from WordPress.org by slug plus `activate` and `deactivate` for installed plugins. Network admins can pass `site_id`/`blog_id` for one multisite child site. | `action` (required: `install`, `activate`, `deactivate`), `slug`, `plugin_file`, `activate`, `network_activate`, `network_wide`, `site_id`, `blog_id`, `overwrite`. |
| `webo/theme-mutate` | Unified theme write tool. Supports `install` from WordPress.org by slug plus `switch` for installed themes. | `action` (required: `install`, `switch`), `slug`, `stylesheet`, `activate`, `overwrite`. |

## Tool definition

```php
ToolRegistry::register([
  'name'        => 'webo/content-query',
  'description' => 'Unified read-only content operations. action (required): list, get, find-by-url, …',
  'category'    => 'wordpress',
  'arguments'   => [
    'action'    => [
      'type'     => 'string',
      'required' => true,
    ],
    'post_type' => [
      'type'     => 'string',
      'required' => false,
    ],
    'per_page'  => [
      'type'     => 'integer',
      'required' => false,
      'default'  => 10,
      'min'      => 1,
      'max'      => 100,
    ],
  ],
  'permission'  => 'read',
  'callback'    => [ WordPressTools::class, 'content_query' ],
]);
```

## Register tools from addon plugin

Third-party plugins can register **`webo_mcp_register_tools`** callbacks (see `examples/addon-rankmath-example.php` for a minimal pattern). Rank Math SEO integration is maintained as a separate addon: **[webo-mcp-rank-math](https://github.com/mrphuong-webo/webo-mcp-rank-math)** (**must be activated** on the site); it registers WordPress Abilities named **`webo-rank-math/*`**, which WEBO MCP bridges into the tool registry. **Only abilities with `meta.mcp.public === true` appear in default `tools/list`**; the maintained addon sets public on the **ten** unified dispatchers only.

WP Rocket cache automation: **[webo-mcp-rocket](https://github.com/mrphuong-webo/webo-mcp-rocket)** registers **`webo-rocket/cache-query`** and **`webo-rocket/cache-mutate`** for public discovery (see **`docs/MCP_TOOL_MIGRATION.md`** for `action` values).

## tools/list output format

```json
{
  "tools": [
    {
      "name": "webo/content-query",
      "description": "Unified read-only content operations (action: list, get, find-by-url, …)",
      "category": "wordpress"
    }
  ]
}
```

## Optional diagnostics endpoint

- `GET /wp-json/webo-mcp/v1/tools`

## WordPress.org packaging

- Plugin header is in `webo-mcp.php`
- WordPress.org readme file is `readme.txt`
- Keep stable version in sync between plugin header and `readme.txt`

## Security hardening (2.0.1+)

- HMAC-signed MCP requests are accepted at the REST layer when `webo_mcp_hmac_secret` is set.
- `webo/media-mutate` with `action: upload` blocks loopback/private IPs; extend via `webo_mcp_validate_media_fetch_url`.
- `webo/search-replace-posts` scans at most 500 posts per call; use `offset` + `next_offset` to paginate.
- `webo/update-options` sanitizes each allowlisted option; invalid values are skipped (see response `skipped`).

## Error handling

- Tool not found: throws `Exception("Tool not registered")`
- Invalid arguments: returns `WP_Error`
- Permission denied: returns `WP_Error` with code `webo_mcp_permission_denied`

## GitHub repository rename (webo-wordpress-mcp → webo-mcp)

1. On GitHub: **Settings → General → Repository name** → `webo-mcp`.
2. Local: `git remote set-url github git@github.com:mrphuong-webo/webo-mcp.git`
3. Deploy target becomes `…/plugins/webo-mcp` (matches `github.event.repository.name`).
4. On WordPress: old path `webo-wordpress-mcp` will show as missing; install/activate under `webo-mcp` (API key/HMAC migrate automatically).
