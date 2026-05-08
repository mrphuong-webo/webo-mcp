---
name: webo-mcp-wordpress-content
description: >-
  Creates, updates, and manages WordPress content (posts, pages, media, categories,
  tags, menus, comments) through the WEBO MCP router and webo/* tools. Use when the
  user connects an MCP client to WordPress via webo-mcp, the n8n WEBO MCP node, or
  mentions tools/call with webo/content-query, webo/content-mutate, draft post lists
  (status draft), reviewing or reading back post content, the MCP router, or site
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

| Task | `name` + `action` | Notes |
|------|-------------------|-------|
| List / discover CPTs | `webo/content-query` `discover-types` | Public types |
| List posts | `webo/content-query` `list` | Default **`status` publish** + **`post_type` post** — drafts/pages need explicit values. Response includes **`applied`** echo. |
| Read post | `webo/content-query` `get` | `post_id` or `id`; optional `post_type` check |
| Duplicate drafts / same body | `webo/content-query` `find-duplicates` | Default **`status` draft**, **`match` content** (exact normalized). Also `title`, `title_and_content`; `max_posts`, `offset`; `skip_empty`. Not fuzzy. |
| By URL | `webo/content-query` `find-by-url` | Read-only — write via `content-mutate/update` |
| By slug | `webo/content-query` `find-by-slug` | optional `post_type` |
| Reading / front page | `webo/content-query` `get-homepage` | |
| Get revisions | `webo/content-query` `list-revisions` | `post_id` or `id` |
| Get assigned terms | `webo/content-query` `get-terms` | `post_id`/`id`, optional `taxonomy` |
| Create | `webo/content-mutate` `create` | `title` required |
| Update | `webo/content-mutate` `update` | `post_id` or `id` required |
| Delete | `webo/content-mutate` `delete` | Needs **delete_posts** |
| Restore revision | `webo/content-mutate` `restore-revision` | `revision_id` |
| Bulk status | `webo/content-mutate` `bulk-update-status` | `post_ids[]`, `status` |
| Find/replace | `webo/content-mutate` `search-replace` | **`dry_run: true`** first |
| Set featured image | `webo/content-mutate` `set-featured-image` | `attachment_id` or `remove: true` |
| Assign terms | `webo/content-mutate` `assign-terms` | Needs **manage_categories** |
| Terms / tax | `webo/taxonomy-query` (`discover/list/get`), `webo/taxonomy-mutate` (`create/update/delete`) | category/post_tag paths per PHP |
| Media | `webo/media-query` (`list/get`), `webo/media-mutate` (`upload/update/delete`) | http(s) public URLs only |
| Menus | `webo/list-nav-menus`, **`webo/list-nav-menu-locations`**, `webo/list-nav-menu-items`, **`webo/create-nav-menu`**, **`webo/create-nav-menu-for-location`**, **`webo/assign-nav-menu-to-location`**, `webo/add-nav-menu-item-from-post`, `webo/add-nav-menu-item-custom` | **View:** `edit_posts`. **Mutations:** `edit_theme_options`. |
| Comments | `webo/comment-query` (`list/get`), `webo/comment-mutate` (`update/delete`) | |
| Plugins | `webo/plugin-query` | query: installed, active, updates, network-active, health |
| Rank Math SEO (optional addon) | `webo-rank-math/*` | Must activate webo-mcp-rank-math |

**3a. Listing drafts (common pitfall).** `content-mutate/create` defaults to **draft**, but **`content-query/list` defaults to `status: publish`** + **`post_type: post`**. "No drafts returned" usually means `status` was omitted. Do not tell the user to use wp-admin when MCP is connected — call the tool. Examples:

- Draft **posts**: `{ "action": "list", "status": "draft", "per_page": 50 }`
- Draft **pages**: add `"post_type": "page"`

If `items` is empty, check **`applied.status`** and **`applied.post_type`** before assuming no drafts exist.

**3b. Reviewing / reading back content.** For QA / verifying what was saved: use MCP, not "I cannot browse the site."

| User gives | `tools/call` |
|------------|-------------|
| **Post/page ID** | `webo/content-query` `action: get` + `id: <id>` — body is `content` |
| **Full or relative URL** | `webo/content-query` `action: find-by-url` + `url: <permalink>` |
| **Slug only** | `webo/content-query` `action: find-by-slug` + `slug: <slug>` |
| **Unknown ID** | `action: list` (correct `status`) then `action: get` |

4. **Workflow (summary):** Discover types/tax → locate content → create **draft** by default → taxonomy/media/menu as needed → risky ops only after dry-run / confirmation → verify with `content-query/get` or `link`.

5. **ID normalization:** All responses include `id` as primary field. `post_id` kept as alias for backward compat.

6. **WP-CLI analogues**

| WP-CLI | WEBO MCP |
|--------|----------|
| `wp post list` | `webo/content-query` `action: list` |
| `wp post get` | `webo/content-query` `action: get` |
| `wp post create` | `webo/content-mutate` `action: create` |
| `wp post update` | `webo/content-mutate` `action: update` |
| `wp post delete` | `webo/content-mutate` `action: delete` |
| `wp media import URL` | `webo/media-mutate` `action: upload` |
| `wp menu create` | `webo/create-nav-menu` |
| `wp menu item add-post` | `webo/add-nav-menu-item-from-post` |

7. **Safety:** Default **`draft`**. Never `search-replace` with `dry_run: false` without preview + user OK. Honor capability errors — `delete` needs `delete_posts`, `assign-terms` needs `manage_categories`.

## Examples

### List draft posts

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "session_id": "<from initialize>",
    "name": "webo/content-query",
    "arguments": { "action": "list", "status": "draft", "per_page": 50 }
  },
  "id": 1
}
```

### Read post by ID

```json
{
  "name": "webo/content-query",
  "arguments": { "action": "get", "id": 42 }
}
```

### Create draft

```json
{
  "name": "webo/content-mutate",
  "arguments": {
    "action": "create",
    "post_type": "post",
    "title": "Example",
    "content": "<p>HTML …</p>",
    "status": "draft"
  }
}
```

### Publish (update status)

```json
{
  "name": "webo/content-mutate",
  "arguments": { "action": "update", "id": 42, "status": "publish" }
}
```

### Success / error shapes

```json
{ "id": 123, "post_id": 123, "tool": "webo/create-post" }
```

```json
{
  "code": "webo_mcp_post_not_found",
  "message": "Human-readable message",
  "data": {}
}
```
    "content": "<p>HTML �</p>",
    "status": "draft"
  }
}
```

### Publish (update status)

```json
{
  "name": "webo/content-mutate",
  "arguments": { "action": "update", "id": 42, "status": "publish" }
}
```

### Success / error shapes

```json
{ "id": 123, "post_id": 123, "tool": "webo/create-post" }
```

```json
{
  "code": "webo_mcp_post_not_found",
  "message": "Human-readable message",
  "data": {}
}
```
