---
name: webo-mcp-ability-comments
description: >-
  Documents WEBO MCP comment tools: list, get, update (status or reply), delete.
  Use when moderating WordPress comments via tools/call (webo/list-comments,
  webo/get-comment, webo/update-comment, webo/delete-comment).
---

# WEBO MCP — Comments

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions:** All require **`moderate_comments`**.

| `name` | Arguments |
|--------|-----------|
| `webo/list-comments` | `per_page` 1–100; `status` (default `approve`) |
| `webo/get-comment` | `comment_id` |
| `webo/update-comment` | `comment_id`; optional `status`, `reply` |
| `webo/delete-comment` | `comment_id` |

3. **Rules:** There is no bulk-delete tool in core; process comments individually.

## Examples

```json
{
  "session_id": "<…>",
  "name": "webo/update-comment",
  "arguments": {
    "comment_id": 12,
    "status": "approve"
  }
}
```
