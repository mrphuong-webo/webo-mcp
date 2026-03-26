---
name: webo-mcp-guide
description: >-
  Covers WEBO MCP JSON-RPC flow (initialize, tools/list, tools/call), authentication,
  session handling, and choosing modular webo-mcp-ability-* skills. Use before any
  WordPress change via webo-mcp, the MCP router, n8n WEBO MCP node, or when routing
  tasks to posts, media, taxonomy, comments, menus, users, or site configuration tools.
---

# WEBO MCP guide

## Instructions

1. **Entry point:** Read this skill first. Detailed tool tables live in **`webo-mcp-ability-*`** or the combined **`webo-mcp-wordpress-content`** reference.
2. **Required flow**

| Step | Action |
|------|--------|
| 1 | `POST` MCP router (usually `/wp-json/mcp/v1/router`) — see repo `README.md` |
| 2 | `initialize` → store **`session_id`** |
| 3 | `tools/list` → match `webo/*` names and arguments to the site |
| 4 | `tools/call` → send `session_id`, `name`, `arguments` each time |

3. **Auth:** Cookie, API key, or **HMAC** (`webo-hmac-auth`) per site. Missing capability → **`403`** from `ToolRegistry`; failures may return `WP_Error` shape in results.
4. **Abilities bridge:** Plugins may expose Abilities API tools via `webo_mcp_auto_bridge_abilities`. Core tools are still **`webo/*`** registered in `webo-mcp.php`.
5. **Pick a modular skill**

| Task area | Skill directory |
|-----------|-----------------|
| Posts, pages, CPT, revisions, search-replace, homepage | `webo-mcp-ability-posts` |
| Media, upload from URL | `webo-mcp-ability-media` |
| Taxonomies and terms | `webo-mcp-ability-taxonomy` |
| Comments | `webo-mcp-ability-comments` |
| Nav menus (tool table) | `webo-mcp-ability-menus` |
| Nav menus (create / assign workflows) | `webo-mcp-menu-creation` |
| User list | `webo-mcp-ability-users` |
| Plugins, safe options | `webo-mcp-ability-site` |
| SEO-style post workflow + `webo/create-post` | `webo-write-post-instruction` |
| Single-file full tool table | `webo-mcp-wordpress-content` |

6. **Safety (all skills):** Prefer **`draft`** for new content unless the user asks to publish. **`webo/search-replace-posts`:** always **`dry_run: true`** first, then `false` only after user confirmation. **`webo/upload-media-from-url`:** public **http(s)** only (SSRF-hardened). **Menus:** **`menu_order` ≥ 1**; always inspect **`webo/list-nav-menu-items`** before adding items.

## Examples

Minimal `tools/call` envelope:

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "session_id": "<from initialize>",
    "name": "webo/list-posts",
    "arguments": { "per_page": 10 }
  },
  "id": 1
}
```
