---
name: webo-mcp-ability-menus
description: >-
  Documents WEBO MCP navigation menu tools: list menus; create empty menu (webo/create-nav-menu);
  create menu and assign theme location (webo/create-nav-menu-for-location); assign existing
  menu to a location (webo/assign-nav-menu-to-location); list items; add post or custom links.
  Use for Appearance > Menus automation or when the theme header menu does not update.
---

# WEBO MCP — Navigation menus

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions:** All require **`edit_theme_options`**.

| `name` | Arguments |
|--------|-----------|
| `webo/list-nav-menus` | None — use returned `term_id` as `menu_id` |
| `webo/create-nav-menu` | Optional **`menu_name`** (default localized “New Menu”) — empty menu only; **no** theme assignment |
| `webo/create-nav-menu-for-location` | Optional **`menu_name`** (default “Primary Menu”), **`theme_location`** (default `primary`), **`replace`** (default `true`) |
| `webo/assign-nav-menu-to-location` | **`menu_id`**, optional **`theme_location`** (default `primary`), **`replace`** (default `true`) |
| `webo/list-nav-menu-items` | `menu_id` (required) |
| `webo/add-nav-menu-item-from-post` | `menu_id`, `post_id`, `post_type`, **`menu_order` ≥ 1**; optional `parent_db_id`, `menu_item_title` |
| `webo/add-nav-menu-item-custom` | `menu_id`, **`url`** (http/https), **`title`**, **`menu_order` ≥ 1**; optional `parent_db_id` |

3. **Rules:** Pick one flow: (a) **`webo/create-nav-menu`** then optionally **`webo/assign-nav-menu-to-location`**; (b) **`webo/create-nav-menu-for-location`** in one step (new menu + assign); (c) existing **`menu_id`** from **`webo/list-nav-menus`** + **`assign-nav-menu-to-location`**. Then use **`menu_id`** for **`list-nav-menu-items`** and add-item tools. Before adding items, call **`list-nav-menu-items`** to pick **`menu_order`** and **`parent_db_id`**. `post_type` must match `post_id`.

4. **Theme menu locations (`primary`, `main`, `header`, …)** — Slugs come from `register_nav_menu()`. Invalid slugs return **`registered_locations`** in errors. **`assign-nav-menu-to-location`** and **`create-nav-menu-for-location`** update **`nav_menu_locations`**.

   - **Empty menu only (no theme slot):** use **`create-nav-menu`**.
   - **If the front shows nothing after adding items:** confirm this **`menu_id`** is assigned to the header location.
   - **After changes:** clear cache/CDN if needed.

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

Create empty menu, then assign to `primary` (two calls):

```json
{
  "session_id": "<…>",
  "name": "webo/create-nav-menu",
  "arguments": { "menu_name": "Footer links" }
}
```

```json
{
  "session_id": "<…>",
  "name": "webo/assign-nav-menu-to-location",
  "arguments": { "menu_id": 5, "theme_location": "primary" }
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
