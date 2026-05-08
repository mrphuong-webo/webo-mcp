---
name: webo-mcp-ability-site
description: >-
  Documents WEBO MCP site tools: query/activate/deactivate plugins, list/switch themes,
  and read/update allowlisted options. Use when toggling plugins, auditing installed
  plugins, reviewing available themes, switching themes, or changing safe site options
  via tools/call (webo/plugin-query, webo/toggle-plugin, webo/list-themes,
  webo/switch-theme, webo/get-options, webo/update-options).
---

# WEBO MCP — Site (plugins, themes & options)

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions**

| `name` | `permission` | Arguments |
|--------|--------------|-----------|
| `webo/plugin-query` | `activate_plugins` | `query` (installed, active, updates, network-active, rental-candidates, health), optional `scope`, `refresh`, `fields` |
| `webo/toggle-plugin` | `activate_plugins` | `plugin` (plugin file path); `action` `activate` / `deactivate` |
| `webo/list-themes` | `switch_themes` | `include_inactive` (default false) |
| `webo/switch-theme` | `switch_themes` | `stylesheet` (theme directory / stylesheet slug) |
| `webo/get-options` | `manage_options` | `names` (array of option keys) |
| `webo/update-options` | `manage_options` | `options` (key => value) |

3. **Rules:** Only options whitelisted in `WordPressTools::get_options` / `update_options` are returned or updated. Confirm with the user before `toggle-plugin` or `switch-theme` on production.

## Examples

```json
{
  "session_id": "<…>",
  "name": "webo/get-options",
  "arguments": { "names": [ "blogname", "posts_per_page" ] }
}
```
