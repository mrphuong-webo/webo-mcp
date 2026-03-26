---
name: webo-mcp-ability-users
description: >-
  WEBO MCP: user — list (giới hạn, có search). Dùng khi cần danh sách tài khoản trên site.
---

# Ability — Users (webo/*)

**Điều kiện:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).

## Tool và quyền

| `name` | `permission` | Arguments |
|--------|--------------|-----------|
| `webo/list-users` | `list_users` | `per_page` 1–100 (default 20); `search` |

## Quy tắc

- Tool chỉ **list** (không create user qua core tool này). Nếu cần CRUD user đầy đủ, xem ability/plugin khác sau `tools/list`.
