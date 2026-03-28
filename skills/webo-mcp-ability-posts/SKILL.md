---
name: webo-mcp-ability-posts
description: >-
  Documents WEBO MCP post/page/CPT tools: list, read, discover types, resolve by URL
  or slug, homepage info, create/update/delete, bulk status, revisions, search-replace,
  featured image, duplicate draft detection, reading back post content for review. Use for WordPress content or HTML bulk edits via tools/call
  (webo/get-post, webo/find-content-by-url, webo/create-post, webo/update-post, webo/search-replace-posts, webo/find-duplicate-posts, etc.).
---

# WEBO MCP — Posts & content

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions**

| `name` | `permission` | Notes |
|--------|--------------|-------|
| `webo/list-posts` | read | `per_page` 1–100, `post_type`, `search`, `status` |
| `webo/get-post` | read | `post_id`; optional `post_type` validation |
| `webo/find-duplicate-posts` | read | Default `status` draft, `match` content; optional `post_type`, `max_posts` 1–500, `offset`, `skip_empty` |
| `webo/discover-content-types` | read | No arguments |
| `webo/find-content-by-url` | read | `url`; optional `update` array |
| `webo/get-content-by-slug` | read | `slug`; optional `post_type` |
| `webo/get-homepage-info` | read | Optional `post_id`, `include_excerpt`, `include_content` |
| `webo/create-post` | edit_posts | `title` required; `content`, `post_type`, `status` |
| `webo/update-post` | edit_posts | `post_id` required |
| `webo/delete-post` | delete_posts | `post_id`; `force` |
| `webo/bulk-update-post-status` | edit_posts | `post_ids`, `status` |
| `webo/list-revisions` | edit_posts | `post_id` |
| `webo/restore-revision` | edit_posts | `revision_id` |
| `webo/search-replace-posts` | edit_posts | `search`; `replace`, `dry_run` (default true), `offset`, `max_scan_posts` 1–500 |
| `webo/set-post-featured-image` | edit_posts | `post_id` + `attachment_id`, or `remove: true` |

3. **Rules:** To **review** saved body/HTML (“xem lại nội dung”), use **`webo/get-post`**, **`webo/find-content-by-url`**, or **`webo/get-content-by-slug`** when listed—do not claim the site is unreachable if MCP targets that WordPress. `content` is stored after **`wp_kses_post`**. Never set **`dry_run: false`** on search-replace without a prior dry run and user confirmation. Spot-check `post_ids` before bulk status changes.

## Examples

Read full post content by ID:

```json
{
  "session_id": "<…>",
  "name": "webo/get-post",
  "arguments": { "post_id": 42 }
}
```

Create a draft:

```json
{
  "session_id": "<…>",
  "name": "webo/create-post",
  "arguments": {
    "title": "Title",
    "content": "<p>…</p>",
    "post_type": "post",
    "status": "draft"
  }
}
```

Find draft posts with identical normalized body text (default):

```json
{
  "session_id": "<…>",
  "name": "webo/find-duplicate-posts",
  "arguments": { "status": "draft", "match": "content", "max_posts": 200 }
}
```

Set featured image:

```json
{
  "session_id": "<…>",
  "name": "webo/set-post-featured-image",
  "arguments": { "post_id": 42, "attachment_id": 100 }
}
```

Clear featured image: `"arguments": { "post_id": 42, "remove": true }`.
