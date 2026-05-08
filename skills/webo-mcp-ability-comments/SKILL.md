---
name: webo-mcp-ability-comments
description: >-
  Documents WEBO MCP unified comment tools: webo/comment-query and
  webo/comment-mutate for moderation workflows.
---

# WEBO MCP — Comments

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions:** Both require **`moderate_comments`**.

| `name` | `action` | Arguments |
|--------|----------|-----------|
| `webo/comment-query` | `list` | `per_page` 1–100; `status` (default `approve`) |
| `webo/comment-query` | `get` | `comment_id` |
| `webo/comment-mutate` | `update` | `comment_id`; optional `status`, `reply` |
| `webo/comment-mutate` | `delete` | `comment_id` |

3. **Rules:** There is no bulk-delete tool in core; process comments individually.

## Examples

Approve a comment:

```json
{
  "session_id": "<…>",
  "name": "webo/comment-mutate",
  "arguments": {
    "action": "update",
    "comment_id": 12,
    "status": "approve"
  }
}
```
