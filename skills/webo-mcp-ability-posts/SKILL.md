---
name: webo-mcp-ability-posts
description: >-
  Documents WEBO MCP unified content tools: webo/content-query and webo/content-mutate.
  Use for listing, reading, creating, updating, deleting posts/pages/CPTs, revisions,
  search-replace, duplicate detection, featured image, taxonomy terms, and reviewing
  saved post content via MCP (webo/content-query, webo/content-mutate).
---

# WEBO MCP — Posts & content

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Two unified tools**

| `name` | `permission` | Purpose |
|--------|--------------|---------|
| `webo/content-query` | read | All read-only content operations (see actions below) |
| `webo/content-mutate` | edit_posts | All write operations; stricter caps enforced per-action |

3. **`webo/content-query` actions**

| `action` | Key arguments | Notes |
|----------|---------------|-------|
| `list` | `post_type`, `status`, `per_page` 1–100, `page`, `offset`, `search`, `orderby`, `order` | Default `post_type: post, status: publish` — drafts/pages need explicit values |
| `get` | `post_id` or `id`, optional `post_type` | Returns `id`, `title`, `content`, `excerpt`, `status`, `type`, `slug`, `link` |
| `find-by-url` | `url` | Resolves permalink/path to post — write via `content-mutate` |
| `find-by-slug` | `slug`, optional `post_type` | Searches all public types if `post_type` omitted |
| `get-homepage` | optional `post_id`/`id`, `include_excerpt`, `include_content` | Reading settings + optional post resolve |
| `discover-types` | — | Public post types |
| `list-revisions` | `post_id` or `id` | Lists revision history for a post |
| `find-duplicates` | `post_type`, `status`, `match` (content/title/title_and_content), `max_posts` 1–500, `offset`, `skip_empty` | Exact normalized match, not fuzzy |
| `get-terms` | `post_id` or `id`, optional `taxonomy` | Returns assigned terms |

4. **`webo/content-mutate` actions**

| `action` | Key arguments | Effective capability |
|----------|---------------|---------------------|
| `create` | `title`, `content`, `post_type`, `status` | edit_posts |
| `update` | `post_id`/`id`, `title`, `content`, `excerpt`, `status` | edit_posts |
| `delete` | `post_id`/`id`, `force` | **delete_posts** |
| `restore-revision` | `revision_id` | edit_posts |
| `bulk-update-status` | `post_ids[]`, `status` | edit_posts |
| `search-replace` | `search`, `replace`, `dry_run` (default true), `offset`, `limit` | edit_posts |
| `set-featured-image` | `post_id`/`id`, `attachment_id` or `remove: true` | edit_posts |
| `assign-terms` | `post_id`/`id`, `taxonomy`, `term_ids[]` | **manage_categories** |

5. **Rules:**
   - Accept `post_id` **or** `id` alias — both work for all actions.
   - Response always includes `id` as primary field; `post_id` kept as alias.
   - Never use `search-replace` with `dry_run: false` without a prior dry-run preview and user confirmation.
   - Spot-check `post_ids` before `bulk-update-status`.
   - To review saved HTML, use `content-query/get`, `content-query/find-by-url`, or `content-query/find-by-slug`.

## Examples

List draft posts:

```json
{
  "session_id": "<…>",
  "name": "webo/content-query",
  "arguments": { "action": "list", "status": "draft", "per_page": 50 }
}
```

Read full post by ID:

```json
{
  "session_id": "<…>",
  "name": "webo/content-query",
  "arguments": { "action": "get", "id": 42 }
}
```

Create a draft:

```json
{
  "session_id": "<…>",
  "name": "webo/content-mutate",
  "arguments": {
    "action": "create",
    "title": "Title",
    "content": "<p>…</p>",
    "post_type": "post",
    "status": "draft"
  }
}
```

Update a post:

```json
{
  "session_id": "<…>",
  "name": "webo/content-mutate",
  "arguments": { "action": "update", "id": 42, "status": "publish" }
}
```

Set featured image:

```json
{
  "session_id": "<…>",
  "name": "webo/content-mutate",
  "arguments": { "action": "set-featured-image", "id": 42, "attachment_id": 100 }
}
```

Find duplicates:

```json
{
  "session_id": "<…>",
  "name": "webo/content-query",
  "arguments": { "action": "find-duplicates", "status": "draft", "match": "content", "max_posts": 200 }
}
```

