# WEBO MCP Core

Core Tool Registry for WEBO MCP ecosystem.

## Architecture

AI Agent -> MCP Request -> Tool Router -> Tool Registry -> Tool Execution

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

- `GET /wp-json/webo-mcp-core/v1/tools`

## Error handling

- Tool not found: throws `Exception("Tool not registered")`
- Invalid arguments: returns `WP_Error`
- Permission denied: returns `WP_Error` with code `webo_mcp_permission_denied`
