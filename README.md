# WEBO MCP

Standalone MCP gateway and WordPress tools platform.

**Website:** [webomcp.com](https://webomcp.com) — product overview, docs, and ecosystem updates.

**n8n:** Community node on npm — [n8n-nodes-webo-mcp](https://www.npmjs.com/package/n8n-nodes-webo-mcp) — connect n8n workflows to your WordPress MCP endpoint without custom glue.

**Author:** Dinh WP ([dinhwp.com](https://dinhwp.com))

## Credits

Special thanks to the authors and open source projects that contributed to this plugin:
- [WordPress](https://wordpress.org)
- [Abilities API](https://github.com/WordPress/abilities-api) ([Reference](https://make.wordpress.org/ai/2025/07/17/abilities-api/))
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) ([Reference](https://make.wordpress.org/ai/2025/07/17/mcp-adapter/))
- [Composer](https://getcomposer.org)
- Other PHP and JS libraries from the community

If you use this plugin, please give credit to the authors of these libraries.

---

## Dependencies

- This plugin bundles `wordpress/abilities-api` via Composer for standalone abilities bridge.
- This plugin bundles `wordpress/mcp-adapter` and enables adapter runtime by default.
- Run `composer install` in plugin root before activation on environments that do not include `vendor/` in deployment.

## Build release package

- Windows PowerShell:
  - `cd scripts`
  - `./build-release.ps1`
- Output zip: `dist/webo-mcp-<version>.zip`
- Exclusions are controlled by `.distignore`

## Quick MCP + n8n setup

- MCP endpoint: `POST /wp-json/mcp/v1/router`
- MCP flow: `initialize` -> `tools/list` -> `tools/call`
- **WEBO n8n node (npm):** [n8n-nodes-webo-mcp](https://www.npmjs.com/package/n8n-nodes-webo-mcp) — install in n8n and point at your router URL.
- Alternative remote MCP package: `@automattic/mcp-wordpress-remote`
- Example router URL for env/config: `https://your-site.com/wp-json/mcp/v1/router`
- More context: [webomcp.com](https://webomcp.com)

## AI training references

- MCP method schema and examples: use this file + `examples/addon-rankmath-example.php`
- Internal/public policy filters for training data:
  - `webo_mcp_allow_internal_tools`
  - `webo_mcp_public_categories`
  - `webo_mcp_public_tool_allowlist`

## Architecture

AI Agent -> MCP Request -> Tool Router -> Tool Registry -> Tool Execution

## MCP Router

- Class: `WeboMCP\Core\Router\McpRouter`
- Location: `inc/router/class-mcp-router.php`
- Endpoint: `POST /wp-json/mcp/v1/router`
- Legacy compatibility endpoint: `POST /wp-json/mcp/mcp-adapter-default-server`
- Supported methods:
  - `initialize`
  - `tools/list`
  - `tools/call`

### JSON-RPC request example

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "session_id": "abc123",
    "name": "webo/list-posts",
    "arguments": {
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

1. Router validates security (`WP auth` or `X-WEBO-API-KEY` or HMAC headers)
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
- Media (list)
- Comments (list)
- Terms (list)
- Plugins (list active status)
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
| `webo/discover-taxonomies` | List public taxonomies (name, label, object_type, hierarchical). | None. |
| `webo/get-term` | Get one term by ID and taxonomy. | `term_id` (required), `taxonomy` (optional, default category). |
| `webo/assign-terms-to-content` | Assign terms to a post (replaces existing for that taxonomy). | `post_id`, `taxonomy`, `term_ids` (array). |
| `webo/get-content-terms` | Get all terms assigned to a post; optional taxonomy filter. | `post_id` (required), `taxonomy` (optional). |

- See `docs/TOOLS_COMPARISON.md` for a full InstaWP ↔ webo tool mapping.

## Tool definition

```php
ToolRegistry::register([
  'name' => 'webo/list-posts',
  'description' => 'List WordPress posts',
  'category' => 'wordpress',
  'arguments' => [
      'per_page' => [
          'type' => 'integer',
          'required' => false,
          'default' => 10,
          'min' => 1,
          'max' => 100,
      ],
  ],
  'permission' => 'read',
  'callback' => [WordPressTools::class, 'list_posts'],
]);
```

## Register tools from addon plugin

```php
add_action('webo_mcp_register_tools', function () {
    ToolRegistry::register([
        'name' => 'rankmath/get-keywords',
        'description' => 'Get RankMath focus keywords',
        'category' => 'seo',
        'callback' => [RankMathTools::class, 'get_keywords'],
    ]);
});
```

See full example: `examples/addon-rankmath-example.php`

## tools/list output format

```json
{
  "tools": [
    {
      "name": "webo/list-posts",
      "description": "List WordPress posts",
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
- `webo/upload-media-from-url` blocks loopback/private IPs; extend via `webo_mcp_validate_media_fetch_url`.
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
