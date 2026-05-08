---
name: webo-mcp-ability-users
description: >-
  Documents WEBO MCP user listing (search, pagination). Use when the task needs
  WordPress user accounts via tools/call (`webo/list-users`); does not create or delete users.
---

# WEBO MCP — Users

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions**

| `name` | `permission` | Arguments |
|--------|--------------|-----------|
| `webo/list-users` | `list_users` | `per_page` 1–100 (default 20); `search` |

3. **Rules:** Core tools are list-only. For full user CRUD, check bridged abilities or other plugins after `tools/list`.

## Examples

```json
{
  "session_id": "<…>",
  "name": "webo/list-users",
  "arguments": { "per_page": 20, "search": "admin" }
}
```
