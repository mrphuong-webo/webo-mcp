---
name: webo-mcp-ability-rocket
description: >-
  Documents the optional WEBO MCP WP Rocket addon and its webo-rocket/* unified abilities
  for cache inspection and management. Use when an MCP client connected through webo-mcp
  needs WP Rocket cache operations via tools/call, including status checks, cache clearing,
  settings updates, preload triggers, and optimization status queries.
---

# WEBO MCP - WP Rocket addon

## Instructions

1. **Prerequisites:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md). These abilities exist only when the [WEBO MCP WP Rocket addon](https://github.com/mrphuong-webo/webo-mcp-rocket) is installed and active alongside WP Rocket.
2. **Tool namespace:** addon abilities are exposed through the Abilities bridge as **`webo-rocket/*`**, using unified query and mutate pattern. All abilities accept an `action` parameter to route to specific operations.
3. **Unified abilities and permissions**

| Ability | Permission | Actions | Notes |
|---------|------------|---------|-------|
| `webo-rocket/cache-query` | `manage_options` | `status`, `get-cache-settings`, `get-optimization-status` | Read WP Rocket configuration and cache state |
| `webo-rocket/cache-mutate` | `rocket_purge_cache` or `manage_options` | `update-settings`, `clear`, `clear-post`, `clear-url`, `clear-used-css`, `preload` | Modify WP Rocket cache and optimization settings |

4. **Schema details that matter:**

| Ability | Action | Important input details |
|---------|--------|-------------------------|
| cache-query | status | No additional parameters; returns `rocket_active`, `user_capabilities` |
| cache-query | get-cache-settings | No additional parameters; returns core cache-related settings object |
| cache-query | get-optimization-status | No additional parameters; returns RUCSS and optimization status flags |
| cache-mutate | update-settings | Required `settings` object with allowed Rocket option keys; returns updated settings |
| cache-mutate | clear | Optional `lang` for multilingual setups; clears full cache for domain |
| cache-mutate | clear-post | Required `post_id` (integer); clears cache for specific post |
| cache-mutate | clear-url | Optional `url` (single) or `urls` (array); clears cache for specified URLs |
| cache-mutate | clear-used-css | No additional parameters; clears used CSS when API available |
| cache-mutate | preload | Optional `mode` (default 'auto'); triggers cache preload when supported |

5. **Safe workflow:** 
   - Query cache status and settings before making changes
   - Clear cache strategically to avoid excessive purges
   - For URL-specific clears, batch multiple URLs when possible
   - Test updates to settings on a staging environment first
6. **Multilingual support:** when the site uses multilingual plugins, pass `lang` parameter to `clear` action to clear cache for a specific language only.

## Examples

Check WP Rocket status:

```json
{
  "session_id": "<...>",
  "name": "webo-rocket/cache-query",
  "arguments": {
    "action": "status"
  }
}
```

Get current cache settings:

```json
{
  "session_id": "<...>",
  "name": "webo-rocket/cache-query",
  "arguments": {
    "action": "get-cache-settings"
  }
}
```

Get optimization status:

```json
{
  "session_id": "<...>",
  "name": "webo-rocket/cache-query",
  "arguments": {
    "action": "get-optimization-status"
  }
}
```

Update WP Rocket settings:

```json
{
  "session_id": "<...>",
  "name": "webo-rocket/cache-mutate",
  "arguments": {
    "action": "update-settings",
    "settings": {
      "cache_mobile": 1,
      "minify_css": 1,
      "minify_js": 1
    }
  }
}
```

Clear full site cache:

```json
{
  "session_id": "<...>",
  "name": "webo-rocket/cache-mutate",
  "arguments": {
    "action": "clear"
  }
}
```

Clear cache for a specific post:

```json
{
  "session_id": "<...>",
  "name": "webo-rocket/cache-mutate",
  "arguments": {
    "action": "clear-post",
    "post_id": 42
  }
}
```

Clear cache for specific URLs:

```json
{
  "session_id": "<...>",
  "name": "webo-rocket/cache-mutate",
  "arguments": {
    "action": "clear-url",
    "urls": [
      "https://example.com/page-1",
      "https://example.com/page-2"
    ]
  }
}
```

Trigger cache preload:

```json
{
  "session_id": "<...>",
  "name": "webo-rocket/cache-mutate",
  "arguments": {
    "action": "preload",
    "mode": "auto"
  }
}
```

Clear used CSS cache:

```json
{
  "session_id": "<...>",
  "name": "webo-rocket/cache-mutate",
  "arguments": {
    "action": "clear-used-css"
  }
}
```
