# WEBO WordPress MCP

Standalone MCP gateway and WordPress tools platform.

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
- Output zip: `dist/webo-wordpress-mcp-<version>.zip`
- Exclusions are controlled by `.distignore`

## Quick MCP + n8n setup

- MCP endpoint: `POST /wp-json/mcp/v1/router`
- MCP flow: `initialize` -> `tools/list` -> `tools/call`
- n8n remote MCP package: `@automattic/mcp-wordpress-remote`
- n8n env `WP_API_URL`: `https://your-site.com/wp-json/mcp/v1/router`

## AI training references

- MCP method schema and examples: use this file + `examples/addon-rankmath-example.php`
- Internal/public policy filters for training data:
  - `webo_wordpress_mcp_allow_internal_tools`
  - `webo_wordpress_mcp_public_categories`
  - `webo_wordpress_mcp_public_tool_allowlist`

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
add_filter( 'webo_wordpress_mcp_allow_internal_tools', '__return_true' );
```

To allow additional public categories beyond `wordpress`:

```php
add_filter( 'webo_wordpress_mcp_public_categories', function () {
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
  - `GET /wp-json/webo-wordpress-mcp/v1/tools?include_internal=1`

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
add_action('webo_wordpress_mcp_register_tools', function () {
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

- `GET /wp-json/webo-wordpress-mcp/v1/tools`

## WordPress.org packaging

- Plugin header is in `webo-wordpress-mcp.php`
- WordPress.org readme file is `readme.txt`
- Keep stable version in sync between plugin header and `readme.txt`

## Error handling

- Tool not found: throws `Exception("Tool not registered")`
- Invalid arguments: returns `WP_Error`
- Permission denied: returns `WP_Error` with code `webo_mcp_permission_denied`
