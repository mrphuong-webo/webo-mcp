---
name: webo-mcp-wordpress-content
description: >-
  Creates, updates, and manages WordPress content (posts, pages, media, categories,
  tags, menus, comments) through the WEBO MCP router and webo/* tools. Use when the
  user connects an MCP client to WordPress via webo-mcp, the n8n WEBO MCP node, or
  mentions tools/call with webo/list-posts, webo/create-post, the MCP router, or site
  content automation without WP-CLI. Workflow: discover → draft → verify → publish,
  aligned with the wordpress-content skill pattern (JSON-RPC transport instead of WP-CLI over SSH).
---

# WEBO MCP — WordPress content (combined reference)

## Instructions

1. **Modular vs this file:** Prefer [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md) first, then the matching **`webo-mcp-ability-*`** skill. This file is a **single-document** map for agents that want one full table (`WeboMCP\Core\Tools\WordPressTools` in code).
2. **Prerequisites**

| Item | Detail |
|------|--------|
| Router | `POST /wp-json/mcp/v1/router` (or site MCP URL) |
| Flow | `initialize` → `tools/list` → `tools/call` (repo `README.md`) |
| Auth | Cookie/session, API key, or **HMAC** (`webo-hmac-auth`) |
| Errors | `403` from `ToolRegistry` when capability missing |
| Session | Send `session_id` from `initialize` on each `tools/call` |
| Slugs | Use exact `webo/*` names from `tools/list` |

3. **Task → tool map**

| Task | `name` | Notes |
|------|--------|--------|
| List / discover CPTs | `webo/discover-content-types` | Public types |
| List posts | `webo/list-posts` | `post_type`, `status`, `search`, `per_page` ≤ 100 |
| Read post | `webo/get-post` | optional `post_type` check |
| By URL | `webo/find-content-by-url` | optional `update` |
| By slug | `webo/get-content-by-slug` | optional `post_type` |
| Create | `webo/create-post` | |
| Update / delete | `webo/update-post`, `webo/delete-post` | |
| Bulk status | `webo/bulk-update-post-status` | |
| Revisions | `webo/list-revisions`, `webo/restore-revision` | |
| Find/replace | `webo/search-replace-posts` | **`dry_run: true`** first |
| Terms / tax | `webo/discover-taxonomies`, `webo/list-terms`, term CRUD, `webo/assign-terms-to-content`, `webo/get-content-terms` | category/post_tag paths per PHP |
| Media | `webo/list-media`, get/update/delete, `webo/upload-media-from-url` | http(s) public URLs only |
| Featured | `webo/set-post-featured-image` | or `remove: true` |
| Menus | `webo/list-nav-menus`, `webo/list-nav-menu-items`, `webo/add-nav-menu-item-from-post`, `webo/add-nav-menu-item-custom` | `menu_order` ≥ 1; see **Menus vs theme locations** below |
| Comments | list/get/update/delete `webo/*` | |
| Reading / front | `webo/get-homepage-info` | |

4. **Workflow (summary):** Discover types/tax → locate content → create **draft** by default → taxonomy/media/menu as needed → risky ops only after dry-run / confirmation → verify with `webo/get-post` or `link`. Same spirit as [wordpress-content (jezweb)](https://skills.sh/jezweb/claude-skills/wordpress-content).

5. **Menus vs theme locations:** MCP can list menus, list items, add items from posts/CPTs, and add **custom URLs** — but it cannot assign a menu to a **theme location** (e.g. *Primary*, *Main*). After using `webo/add-nav-menu-item-*`, ensure in **Appearance → Menus** (Manage Locations / menu settings) that the menu matching your `menu_id` is selected for the correct location; otherwise the header may not change. Full detail: [`webo-mcp-ability-menus`](../webo-mcp-ability-menus/SKILL.md).

6. **WP-CLI analogues**

| WP-CLI | WEBO MCP |
|--------|----------|
| `wp post create` | `webo/create-post` |
| `wp media import URL` | `webo/upload-media-from-url` |
| `wp menu item add-post` | `webo/add-nav-menu-item-from-post` |
| `wp menu item add-custom` | `webo/add-nav-menu-item-custom` |
| `wp menu assign` (menu → theme location) | Not in MCP — admin or WP-CLI |
| Featured | `webo/set-post-featured-image` |
| Heavy ACF/meta | Abilities bridge or REST/WP-CLI |

7. **Safety:** Default **`draft`**. Never **`search-replace-posts`** with `dry_run: false` without preview and user OK. Honor capability errors from WordPress.

## Examples

### `tools/call` envelope

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "session_id": "<from initialize>",
    "name": "webo/create-post",
    "arguments": {
      "post_type": "post",
      "title": "Example",
      "content": "<p>HTML …</p>",
      "status": "draft"
    }
  },
  "id": 1
}
```

### `webo/create-post` arguments (reference)

| Argument | Required | Default | Notes |
|----------|----------|---------|--------|
| `title` | yes | — | |
| `content` | no | `""` | After **`wp_kses_post`** |
| `post_type` | no | `post` | |
| `status` | no | `draft` | |

### Success / error shapes

```json
{ "post_id": 123, "tool": "webo/create-post" }
```

```json
{
  "code": "webo_mcp_post_not_found",
  "message": "Human-readable message",
  "data": {}
}
```

After create: verify with `webo/get-post` or `{site}/wp-admin/post.php?post={id}&action=edit`.
