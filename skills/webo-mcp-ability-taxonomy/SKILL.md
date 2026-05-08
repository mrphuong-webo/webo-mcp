---
name: webo-mcp-ability-taxonomy
description: >-
  Documents WEBO MCP unified taxonomy tools: webo/taxonomy-query and
  webo/taxonomy-mutate, plus content term assignment/readback tools.
---

# WEBO MCP — Taxonomy

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions**

| `name` | `permission` | Notes |
|--------|--------------|-------|
| `webo/taxonomy-query` | manage_categories | `action`: discover, list, get |
| `webo/taxonomy-mutate` | manage_categories | `action`: create, update, delete |
| `webo/content-mutate` | edit_posts + manage_categories for assign-terms | `action: assign-terms` replaces all terms for that taxonomy on the post |
| `webo/content-query` | read | `action: get-terms` reads terms for a post; optional taxonomy filter |

3. **Action map**

| Tool | `action` | Key arguments |
|------|----------|---------------|
| `webo/taxonomy-query` | `discover` | none |
| `webo/taxonomy-query` | `list` | `taxonomy` (default `category`), `per_page` |
| `webo/taxonomy-query` | `get` | `term_id`, `taxonomy` |
| `webo/taxonomy-mutate` | `create` | `name` required; optional `taxonomy`, `slug`, `description`, `parent_id` |
| `webo/taxonomy-mutate` | `update` | `term_id` required; optional fields |
| `webo/taxonomy-mutate` | `delete` | `term_id` required; optional `taxonomy` |

4. **Rules:** Resolve `term_ids` with `taxonomy-query/list` or `taxonomy-query/get` before assigning. Core create/update/delete paths target **category** / **post_tag** as implemented in `WordPressTools`.

## Examples

Create a category:

```json
{
  "session_id": "<…>",
  "name": "webo/taxonomy-mutate",
  "arguments": {
    "action": "create",
    "taxonomy": "category",
    "name": "News"
  }
}
```

Assign categories to a post:

```json
{
  "session_id": "<…>",
  "name": "webo/content-mutate",
  "arguments": {
    "action": "assign-terms",
    "id": 1,
    "taxonomy": "category",
    "term_ids": [2, 3]
  }
}
```
