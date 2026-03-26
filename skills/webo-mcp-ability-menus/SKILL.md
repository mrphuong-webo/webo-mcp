---
name: webo-mcp-ability-menus
description: >-
  Documents WEBO MCP navigation menu tools: list menus and items; create a new menu and
  assign a theme location (e.g. primary) without menu_id via webo/create-nav-menu-for-location;
  add post or custom links to a menu. Use when editing Appearance > Menus via MCP or when
  the header menu does not show after changes.
---

# WEBO MCP — Navigation menus

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions:** All require **`edit_theme_options`**.

| `name` | Arguments |
|--------|-----------|
| `webo/list-nav-menus` | None — use returned `term_id` as `menu_id` |
| `webo/create-nav-menu-for-location` | Optional **`menu_name`** (default localized “Primary Menu”), **`theme_location`** (default `primary` — must match `register_nav_menus` slug), **`replace`** (default `true`: overwrite existing assignment for that location) |
| `webo/list-nav-menu-items` | `menu_id` (required) |
| `webo/add-nav-menu-item-from-post` | `menu_id`, `post_id`, `post_type`, **`menu_order` ≥ 1**; optional `parent_db_id`, `menu_item_title` |
| `webo/add-nav-menu-item-custom` | `menu_id`, **`url`** (http/https), **`title`**, **`menu_order` ≥ 1**; optional `parent_db_id` |

3. **Rules:** For **new** primary/header flow with **no** `menu_id`: call **`webo/create-nav-menu-for-location`** first; use returned **`menu_id`** for `list-nav-menu-items` and add-item tools. When adding items, call **`webo/list-nav-menu-items`** first to pick valid **`menu_order`** and **`parent_db_id`**. `post_type` must match the real type of `post_id`.

4. **Theme menu locations (`primary`, `main`, `header`, …)** — The active theme exposes **location slugs** via `register_nav_menu()`. **`webo/create-nav-menu-for-location`** creates a **new** nav menu and sets **`nav_menu_locations`** for one slug (default `primary`). If that slug is not registered (e.g. theme uses only `main`), the tool errors and returns **`registered_locations`** for debugging.

   - **Assigning an *existing* menu** to a location (without creating a new one) is still **not** a `webo/*` tool — use **Appearance → Menus** (Manage Locations) or **`wp menu assign`**.
   - **If add-item tools run but the front shows nothing:** you may be editing a different `menu_id` than the one assigned to the header location — use **`create-nav-menu-for-location`** or re-check locations in admin.
   - **After changes:** clear page/cache/CDN if a plugin caches menus.

## Examples

Create menu and assign to `primary` (no `menu_id` needed; then use returned `menu_id` for items):

```json
{
  "session_id": "<…>",
  "name": "webo/create-nav-menu-for-location",
  "arguments": {
    "theme_location": "primary"
  }
}
```

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
