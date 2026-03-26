---
name: webo-mcp-ability-site
description: >-
  Documents WEBO MCP site tools: list/activate/deactivate plugins and read/update
  allowlisted options. Use when toggling plugins, auditing installed plugins, or
  changing safe site options via tools/call (webo/list-active-plugins, webo/toggle-plugin,
  webo/get-options, webo/update-options).
---

# WEBO MCP — Site (plugins & options)

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions**

| `name` | `permission` | Arguments |
|--------|--------------|-----------|
| `webo/list-active-plugins` | `activate_plugins` | `include_inactive` (default false) |
| `webo/toggle-plugin` | `activate_plugins` | `plugin` (plugin file path); `action` `activate` / `deactivate` |
| `webo/get-options` | `manage_options` | `names` (array of option keys) |
| `webo/update-options` | `manage_options` | `options` (key => value) |

3. **Rules:** Only options whitelisted in `WordPressTools::get_options` / `update_options` are returned or updated. Confirm with the user before `toggle-plugin` on production.

## Examples

```json
{
  "session_id": "<…>",
  "name": "webo/get-options",
  "arguments": { "names": [ "blogname", "posts_per_page" ] }
}
```
