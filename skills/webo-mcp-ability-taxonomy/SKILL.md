---
name: webo-mcp-ability-taxonomy
description: >-
  WEBO MCP: taxonomy & term — discover, list, get, create/update/delete term, gán term
  cho bài, đọc term của bài. Dùng khi category, tag, hoặc taxonomy tùy biến (public).
---

# Ability — Taxonomy (webo/*)

**Điều kiện:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).

## Tool và quyền

| `name` | `permission` | Ghi chú |
|--------|--------------|---------|
| `webo/discover-taxonomies` | read | Không arguments |
| `webo/list-terms` | manage_categories | `taxonomy` (default category), `per_page` 1–100 |
| `webo/get-term` | read | `term_id`; `taxonomy` |
| `webo/create-term` | manage_categories | `name` bắt buộc; `taxonomy`, `slug`, `description`, `parent_id` |
| `webo/update-term` | manage_categories | `term_id` bắt buộc; các field còn lại tùy chọn |
| `webo/delete-term` | manage_categories | `term_id`; `taxonomy` |
| `webo/assign-terms-to-content` | manage_categories | `post_id`, `taxonomy`, `term_ids` (**thay thế** toàn bộ term của taxonomy đó trên bài) |
| `webo/get-content-terms` | read | `post_id`; `taxonomy` filter tùy chọn |

## Quy tắc

- Create/update/delete term qua tool core: thực tế **category** / **post_tag** (và logic PHP cho phép); không fabricate slug trùng mà không kiểm tra.
- Trước khi gán: `list-terms` hoặc `get-term` để có **`term_ids`** đúng.

## Payload mẫu — gán category

```json
{
  "session_id": "<…>",
  "name": "webo/assign-terms-to-content",
  "arguments": {
    "post_id": 1,
    "taxonomy": "category",
    "term_ids": [2, 3]
  }
}
```
