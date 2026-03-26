---
name: webo-mcp-wordpress-content
description: >-
  Creates, updates, and manages WordPress content (posts, pages, media, categories,
  tags, menus, comments) through the WEBO MCP router and webo/* tools. Use when the
  user connects an MCP client to WordPress via webo-mcp, n8n WEBO MCP node, or
  mentions tools/call with webo/list-posts, webo/create-post, MCP router, or site
  content automation without WP-CLI. Workflow aligns with the wordpress-content skill
  pattern (discover → draft → verify → publish) adapted from public Claude skills catalogs.
---

# WEBO MCP — WordPress content

Manage live WordPress content over **MCP** using the **webo-mcp** plugin. This skill mirrors the task split from [wordpress-content (jezweb/claude-skills)](https://skills.sh/jezweb/claude-skills/wordpress-content): same operational discipline (draft first, verify, bulk care), but **transport is JSON-RPC** to the MCP router instead of SSH + `wp`.

## Prerequisites

- **Router:** `POST /wp-json/mcp/v1/router` (or your site’s configured MCP URL).
- **Flow:** `initialize` → `tools/list` → `tools/call` (see repo `README.md`).
- **Auth:** whatever the site enables (cookie/session, API key, or **HMAC** via `webo-hmac-auth`). Missing permissions return `403` from `ToolRegistry`.
- **Session:** pass `session_id` from `initialize` in each `tools/call` (or header your client uses).
- **Tool names:** always the `webo/*` slug returned by `tools/list` (examples below match `WeboMCP\Core\Tools\WordPressTools`).

## Task → tool map

| Task | MCP tool (`tools/call` name) | Notes |
|------|------------------------------|--------|
| List / discover CPTs | `webo/discover-content-types` | Public post types |
| List posts | `webo/list-posts` | `post_type`, `status`, `search`, `per_page` (max 100) |
| Read one post | `webo/get-post` | `post_id`; optional `post_type` sanity check |
| Resolve by URL | `webo/find-content-by-url` | Optional `update: { title, content, status }` in one call |
| Resolve by slug | `webo/get-content-by-slug` | Optional `post_type`; searches public types if omitted |
| Create post/page | `webo/create-post` | `post_type`, `title`, `content`, `status` |
| Update post | `webo/update-post` | `post_id` + fields |
| Delete post | `webo/delete-post` | `force` for permanent |
| Bulk status | `webo/bulk-update-post-status` | `post_ids`, `status` ∈ draft/publish/pending/private/trash |
| Revisions | `webo/list-revisions`, `webo/restore-revision` | |
| Find/replace in HTML | `webo/search-replace-posts` | **Always `dry_run: true` first**; paginate with `offset` / `max_scan_posts` |
| List terms | `webo/list-terms` | `taxonomy`, `per_page` |
| Taxonomy catalog | `webo/discover-taxonomies` | |
| Term CRUD | `webo/get-term`, `webo/create-term`, `webo/update-term`, `webo/delete-term` | create/update/delete: **category** or **post_tag** only |
| Assign terms | `webo/assign-terms-to-content` | Replaces terms for that taxonomy on the post |
| Read post terms | `webo/get-content-terms` | |
| Media list / read / update / delete | `webo/list-media`, `webo/get-media`, `webo/update-media`, `webo/delete-media` | |
| Media from URL | `webo/upload-media-from-url` | **Public http(s) only**; SSRF rules block loopback/private IPs |
| Menus | `webo/list-nav-menus`, `webo/list-nav-menu-items`, `webo/add-nav-menu-item-from-post` | **menu_order ≥ 1** required; set `parent_db_id` from existing item |
| Comments | `webo/list-comments`, `webo/get-comment`, `webo/update-comment`, `webo/delete-comment` | |
| Homepage / Reading | `webo/get-homepage-info` | Optional `include_content`, `include_excerpt`, `post_id` |

## Recommended workflow

### 1. Orient

1. Call `webo/discover-content-types` and/or `webo/discover-taxonomies`.
2. Use `webo/list-posts` or `webo/get-content-by-slug` / `webo/find-content-by-url` to locate content.

### 2. Create or edit

- **New:** `webo/create-post` with `status: draft` unless the user explicitly wants live.
- **Edit:** `webo/get-post` then `webo/update-post`.
- **By URL:** `webo/find-content-by-url` with `update` object for quick patches.

Prefer **short titles** in tools; put large HTML in `content`. Server runs **`wp_kses_post`** on content for create/update — avoid surprising strip of tags; keep markup reasonably standard.

### 3. Taxonomy

- Create terms with `webo/create-term` (`taxonomy`: `category` or `post_tag`, optional `parent_id` for categories).
- Attach with `webo/assign-terms-to-content` (`term_ids` replaces assignments for that taxonomy).

### 4. Media

- Import remote file: `webo/upload-media-from-url` (`image_url`, optional `title`, `filename`, `alt_text`).
- **Featured image:** there is **no** dedicated `webo/set-featured-image` in core `WordPressTools`; after upload, set `_thumbnail_id` via WP admin, REST, WP-CLI, or a custom ability/add-on. Use `attachment_id` from upload response.

### 5. Menus

1. `webo/list-nav-menus` → `menu_id` (= term_id).
2. `webo/list-nav-menu-items` → choose **`menu_order`** and optional **`parent_db_id`**.
3. `webo/add-nav-menu-item-from-post` with **`post_id`**, **`post_type`**, **`menu_order`**, **`menu_id`**.

### 6. Bulk / risky operations

- **Search/replace:** `webo/search-replace-posts` with **`dry_run: true`**, review `affected`, then rerun with `dry_run: false`. Use **`next_offset`** when `has_more` is true.
- **Bulk publish:** `webo/bulk-update-post-status` after spot-checking IDs.

### 7. Verify

- Re-call `webo/get-post` or open `link` from tool output.
- Admin edit URL pattern: `{site}/wp-admin/post.php?post={id}&action=edit`.

## JSON-RPC shape (reminder)

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

Use the exact **argument names** from `tools/list` for each tool (they match the PHP tool definitions in `inc/tools/class-wordpress-tools.php`).

## Differences vs WP-CLI skill

| wordpress-content (WP-CLI) | WEBO MCP |
|----------------------------|----------|
| `wp post create` / file | `webo/create-post` + `content` string |
| `wp media import URL` | `webo/upload-media-from-url` (SSRf-hardened) |
| `wp menu item add-post` | `webo/add-nav-menu-item-from-post` (**explicit `menu_order`**) |
| ACF / meta | Not covered here unless exposed via **Abilities API** or extra tools |

When MCP is unsuitable (heavy ACF, custom tables), fall back to **REST** or **WP-CLI** as in the [wordpress-content workflow reference](https://skills.sh/jezweb/claude-skills/wordpress-content).

## Safety defaults

- Default new content to **`draft`**.
- Never run **`webo/search-replace-posts`** with `dry_run: false` without a prior dry run and user confirmation.
- Respect **`read_post` / `edit_posts`** failures (errors from WordPress / registry).
