---
name: webo-mcp-ability-menus
description: >-
  Documents WEBO MCP navigation menu tools: list menus and items, add a link from
  an existing post/page/CPT, or add a custom URL. Use when editing Appearance > Menus
  behavior via MCP (webo/list-nav-menus, webo/add-nav-menu-item-from-post,
  webo/add-nav-menu-item-custom).
---

# WEBO MCP — Navigation menus

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions:** All require **`edit_theme_options`**.

| `name` | Arguments |
|--------|-----------|
| `webo/list-nav-menus` | None — use returned `term_id` as `menu_id` |
| `webo/list-nav-menu-items` | `menu_id` (required) |
| `webo/add-nav-menu-item-from-post` | `menu_id`, `post_id`, `post_type`, **`menu_order` ≥ 1**; optional `parent_db_id`, `menu_item_title` |
| `webo/add-nav-menu-item-custom` | `menu_id`, **`url`** (http/https), **`title`**, **`menu_order` ≥ 1**; optional `parent_db_id` |

3. **Rules:** Always call **`webo/list-nav-menu-items`** first to pick valid **`menu_order`** and **`parent_db_id`**. `post_type` must match the real type of `post_id`. Assigning the menu to a theme location is still done in WP admin (not covered by these tools).

## Examples

Add a page to a menu:

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

Custom URL:

```json
{
  "session_id": "<…>",
  "name": "webo/add-nav-menu-item-custom",
  "arguments": {
    "menu_id": 2,
    "url": "https://example.com/path",
    "title": "Label",
    "menu_order": 4,
    "parent_db_id": 0
  }
}
```
