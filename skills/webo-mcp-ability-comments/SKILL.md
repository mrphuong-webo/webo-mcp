---
name: webo-mcp-ability-comments
description: >-
  WEBO MCP: comment — list, get, update (status/reply), delete. Dùng khi duyệt, trả lời,
  hoặc xóa bình luận.
---

# Ability — Comments (webo/*)

**Điều kiện:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).

## Tool và quyền

Tất cả: `permission` **`moderate_comments`**.

| `name` | Arguments chính |
|--------|------------------|
| `webo/list-comments` | `per_page` 1–100; `status` (default `approve`) |
| `webo/get-comment` | `comment_id` |
| `webo/update-comment` | `comment_id`; `status`, `reply` (string) tùy chọn |
| `webo/delete-comment` | `comment_id` |

## Quy tắc

- Xóa hay đổi trạng thái hàng loạt: xử lý từng bài một qua tool (không có bulk xóa comment riêng trong bảng core này).
