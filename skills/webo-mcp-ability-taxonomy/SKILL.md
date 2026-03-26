---
name: webo-mcp-ability-taxonomy
description: >-
  Documents WEBO MCP taxonomy tools: discover taxonomies, list/get/create/update/delete
  terms (category/post_tag in core paths), assign terms to posts, read post terms.
  Use for categories, tags, or public taxonomies via tools/call (webo/discover-taxonomies,
  webo/list-terms, webo/assign-terms-to-content, etc.).
---

# WEBO MCP — Taxonomy

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions**

| `name` | `permission` | Notes |
|--------|--------------|-------|
| `webo/discover-taxonomies` | read | No arguments |
| `webo/list-terms` | manage_categories | `taxonomy` (default `category`); `per_page` 1–100 |
| `webo/get-term` | read | `term_id`; `taxonomy` |
| `webo/create-term` | manage_categories | `name` required; `taxonomy`, `slug`, `description`, `parent_id` |
| `webo/update-term` | manage_categories | `term_id` required |
| `webo/delete-term` | manage_categories | `term_id`; `taxonomy` |
| `webo/assign-terms-to-content` | manage_categories | Replaces all terms for that taxonomy on the post |
| `webo/get-content-terms` | read | `post_id`; optional `taxonomy` filter |

3. **Rules:** Resolve `term_ids` with `list-terms` or `get-term` before assigning. Core create/update/delete paths target **category** / **post_tag** as implemented in `WordPressTools`.

## Examples

Assign categories to a post:

```json
{
  "session_id": "<…>",
  "name": "webo/assign-terms-to-content",
  "arguments": {
    "post_id": 1,
    "taxonomy": "category",
    "term_ids": [ 2, 3 ]
  }
}
```
