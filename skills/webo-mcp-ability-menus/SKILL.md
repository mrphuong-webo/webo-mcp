---
name: webo-mcp-ability-menus
description: >-
  WEBO MCP: menu —   list menu, list item (db_id, menu_order, parent), thêm link từ post/page
  hoặc custom URL vào menu. Dùng khi chỉnh Appearance > Menus qua MCP.
---

# Ability — Navigation menus (webo/*)

**Điều kiện:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).

## Tool và quyền

Tất cả: **`edit_theme_options`**.

| `name` | Arguments chính |
|--------|------------------|
| `webo/list-nav-menus` | (none) — `term_id` dùng làm `menu_id` |
| `webo/list-nav-menu-items` | `menu_id` bắt buộc |
| `webo/add-nav-menu-item-from-post` | `menu_id`, `post_id`, `post_type`, **`menu_order` ≥ 1**; `parent_db_id`, `menu_item_title` tùy chọn |
| `webo/add-nav-menu-item-custom` | `menu_id`, **`url`** (http/https), **`title`** (nhãn hiển thị), **`menu_order` ≥ 1**; `parent_db_id` tùy chọn |

## Quy tắc

- **Bắt buộc** xem `list-nav-menu-items` để chọn **`menu_order`** và **`parent_db_id`** hợp lệ — không tự đoàn số.
- `post_type` phải khớp loại thực của `post_id` (post, page, CPT).

## Payload mẫu — thêm item (bài / trang)

```json
{
  "session_id": "<…>",
  "name": "webo/add-nav-menu-item-from-post",
  "arguments": {
    "menu_id": 2,
    "post_id": 10,
    "post_type": "page",
    "menu_order": 3,
    "parent_db_id": 0
  }
}
```

## Payload mẫu — Custom link (URL ngoài / anchor)

```json
{
  "session_id": "<…>",
  "name": "webo/add-nav-menu-item-custom",
  "arguments": {
    "menu_id": 2,
    "url": "https://example.com/path",
    "title": "Nhãn menu",
    "menu_order": 4,
    "parent_db_id": 0
  }
}
```
