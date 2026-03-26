---
name: webo-mcp-wordpress-content
description: >-
  Creates, updates, and manages WordPress content (posts, pages, media, categories,
  tags, menus, comments) through the WEBO MCP router and webo/* tools. Use when the
  user connects an MCP client to WordPress via webo-mcp, the n8n WEBO MCP node, or
  mentions tools/call with webo/list-posts, webo/create-post, the MCP router, or site
  content automation without WP-CLI. Workflow: discover → draft → verify → publish,
  aligned with the wordpress-content skill pattern (transport is JSON-RPC to the MCP
  router instead of SSH + wp).
---

# WEBO MCP — WordPress content

**Modular skills:** Bắt đầu từ [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md) (hướng dẫn chung), rồi mở đúng nhóm — [`webo-mcp-ability-posts`](../webo-mcp-ability-posts/SKILL.md), [`-media`](../webo-mcp-ability-media/SKILL.md), [`-taxonomy`](../webo-mcp-ability-taxonomy/SKILL.md), [`-comments`](../webo-mcp-ability-comments/SKILL.md), [`-menus`](../webo-mcp-ability-menus/SKILL.md), [`-users`](../webo-mcp-ability-users/SKILL.md), [`-site`](../webo-mcp-ability-site/SKILL.md). File này giữ **một bản tham chiếu đầy đủ** (bảng + schema) cho agent thích một tài liệu gộp.

Manage live WordPress content over **MCP** using the **webo-mcp** plugin. Same operational discipline as [wordpress-content (jezweb/claude-skills)](https://skills.sh/jezweb/claude-skills/wordpress-content): draft first, verify, exercise caution on bulk operations; **transport** is JSON-RPC to the MCP router, not WP-CLI over SSH.

## Prerequisites

| Item | Detail |
|------|--------|
| Router | `POST /wp-json/mcp/v1/router` (or the site’s configured MCP URL) |
| Flow | `initialize` → `tools/list` → `tools/call` (see repo `README.md`) |
| Auth | Cookie/session, API key, or **HMAC** via `webo-hmac-auth` as enabled on the site |
| Errors | Missing permissions: `403` from `ToolRegistry` |
| Session | Pass `session_id` from `initialize` on each `tools/call` (or equivalent client header) |
| Tool names | Use `webo/*` slugs exactly as returned by `tools/list` (definitions in `WeboMCP\Core\Tools\WordPressTools`) |

## Task → tool map

| Task | MCP tool (`tools/call` `name`) | Notes |
|------|-------------------------------|--------|
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
| Term CRUD | `webo/get-term`, `webo/create-term`, `webo/update-term`, `webo/delete-term` | **category** or **post_tag** only for create/update/delete |
| Assign terms | `webo/assign-terms-to-content` | Replaces terms for that taxonomy on the post |
| Read post terms | `webo/get-content-terms` | |
| Media | `webo/list-media`, `webo/get-media`, `webo/update-media`, `webo/delete-media` | |
| Media from URL | `webo/upload-media-from-url` | **Public http(s) only**; SSRF rules block loopback/private IPs |
| Featured image | `webo/set-post-featured-image` | `post_id` + `attachment_id`; or **`remove: true`** to clear |
| Menus | `webo/list-nav-menus`, `webo/list-nav-menu-items`, `webo/add-nav-menu-item-from-post`, **`webo/add-nav-menu-item-custom`** | **`menu_order` ≥ 1**; custom link needs **`url`** (http/https) + **`title`** |
| Comments | `webo/list-comments`, `webo/get-comment`, `webo/update-comment`, `webo/delete-comment` | |
| Homepage / Reading | `webo/get-homepage-info` | Optional `include_content`, `include_excerpt`, `post_id` |

## Schemas

### JSON-RPC: `tools/call` envelope

Use this shape for any tool. Replace `name` and `arguments` per `tools/list`.

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "session_id": "<string, from initialize>",
    "name": "webo/<tool-name>",
    "arguments": {}
  },
  "id": 1
}
```

### Tool: `webo/create-post`

Registration (`webo-mcp.php`): create one post/page/custom post; permission `edit_posts`.

| Argument | Type | Required | Default | Notes |
|----------|------|----------|---------|--------|
| `title` | string | yes | — | |
| `content` | string | no | `""` | HTML; stored after **`wp_kses_post`** |
| `post_type` | string | no | `post` | |
| `status` | string | no | `draft` | Prefer draft unless user requests live |

```json
{
  "name": "webo/create-post",
  "arguments": {
    "post_type": "post",
    "title": "Example",
    "content": "<p>HTML …</p>",
    "status": "draft"
  }
}
```

### Response: `webo/create-post` success (`class-wordpress-tools.php`)

```json
{
  "post_id": 123,
  "tool": "webo/create-post"
}
```

### Response: MCP / WordPress error (typical `WP_Error` shape)

```json
{
  "code": "webo_mcp_post_not_found",
  "message": "Human-readable message",
  "data": {}
}
```

After create, verify with `webo/get-post` (`post_id`) or use `link` when returned by read tools. Admin edit URL: `{site}/wp-admin/post.php?post={id}&action=edit`.

## Recommended workflow

### 1. Orient

1. Call `webo/discover-content-types` and/or `webo/discover-taxonomies`.
2. Use `webo/list-posts` or `webo/get-content-by-slug` / `webo/find-content-by-url` to locate content.

### 2. Create or edit

- **New:** `webo/create-post` with `status: draft` unless the user explicitly wants live.
- **Edit:** `webo/get-post` then `webo/update-post`.
- **By URL:** `webo/find-content-by-url` with `update` object for quick patches.

Prefer short titles in tools; put large HTML in `content`. Server runs **`wp_kses_post`** on content for create/update — avoid non-standard tags that may be stripped.

### 3. Taxonomy

- Create terms with `webo/create-term` (`taxonomy`: `category` or `post_tag`, optional `parent_id` for categories).
- Attach with `webo/assign-terms-to-content` (`term_ids` replaces assignments for that taxonomy).

### 4. Media

- Import remote file: `webo/upload-media-from-url` (`image_url`, optional `title`, `filename`, `alt_text`).
- **Featured image:** `webo/set-post-featured-image` with `post_id` and `attachment_id` from upload (or media list). **`remove: true`** clears the thumbnail.

### 5. Menus

1. `webo/list-nav-menus` → `menu_id` (= term_id).
2. `webo/list-nav-menu-items` → choose **`menu_order`** and optional **`parent_db_id`**.
3. **Link to a post/page/CPT:** `webo/add-nav-menu-item-from-post` (`post_id`, `post_type`, `menu_order`, `menu_id`, …).
4. **Custom URL** (external or not yet a post): `webo/add-nav-menu-item-custom` (`menu_id`, **`url`** http/https, **`title`**, `menu_order`, optional `parent_db_id`).

### 6. Bulk / risky operations

- **Search/replace:** `webo/search-replace-posts` with **`dry_run: true`**, review `affected`, then rerun with `dry_run: false`. Use **`next_offset`** when `has_more` is true.
- **Bulk publish:** `webo/bulk-update-post-status` after spot-checking IDs.

### 7. Verify

- Re-call `webo/get-post` or open `link` from tool output.

## Differences vs WP-CLI skill

| wordpress-content (WP-CLI) | WEBO MCP |
|-----------------------------|----------|
| `wp post create` / file | `webo/create-post` + `content` string |
| `wp media import URL` | `webo/upload-media-from-url` (SSRF-hardened) |
| `wp menu item add-post` | `webo/add-nav-menu-item-from-post` (**explicit `menu_order`**) |
| `wp menu item add-custom` | `webo/add-nav-menu-item-custom` (`url` + `title`) |
| Featured image meta | `webo/set-post-featured-image` |
| ACF / meta | Not covered here unless exposed via **Abilities API** or extra tools |

When MCP is unsuitable (heavy ACF, custom tables), use **REST** or **WP-CLI** per the [wordpress-content workflow reference](https://skills.sh/jezweb/claude-skills/wordpress-content).

## Safety defaults

- Default new content to **`draft`**.
- Never run **`webo/search-replace-posts`** with `dry_run: false` without a prior dry run and user confirmation.
- Respect **`read_post` / `edit_posts`** failures (errors from WordPress / registry).

## Agent role (summary)

You drive **live WordPress** changes only through **`webo/*`** MCP tools on the router (not shell `wp`). Honor **`tools/list`** argument names and types. Default creates to **draft**, confirm risky bulk/search-replace with the user, and verify outcomes with **`webo/get-post`** or published links.
