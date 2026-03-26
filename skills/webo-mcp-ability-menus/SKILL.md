---
name: webo-mcp-ability-menus
description: >-
  Documents WEBO MCP navigation menu tools: read-only list menus, items, and theme locations
  (edit_posts); create/assign/add items (edit_theme_options). Includes webo/list-nav-menu-locations
  to see which menu is assigned to primary/header slots. Use for Appearance > Menus via MCP.
---

# WEBO MCP — Navigation menus

## Instructions

### Listing menus vs listing items inside a menu

- **User asks “list menus” / “danh sách menu” / “what menus exist”:** call **`webo/list-nav-menus`** with **no arguments**. Do **not** ask the user for `menu_id`. The response includes each menu’s **`menu_id`** and **`term_id`** (the same number — WordPress `nav_menu` term ID).
- **User asks for links/items inside a specific menu:** call **`webo/list-nav-menu-items`** with **`menu_id`** taken from **`webo/list-nav-menus`** (or from **`webo/list-nav-menu-locations`** → `assigned` → `menu_id`).

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions:**
   - **Read (view):** **`edit_posts`** — `webo/list-nav-menus`, `webo/list-nav-menu-locations`, `webo/list-nav-menu-items` (Editors/Authors and up can inspect menus; Subscribers cannot).
   - **Write:** **`edit_theme_options`** — create menu, assign location, add menu items.

| `name` | Arguments |
|--------|-----------|
| `webo/list-nav-menus` | None — use returned `term_id` as `menu_id` |
| `webo/list-nav-menu-locations` | None — `registered_locations` (slug → label) + `assigned` (slug → menu_id, menu_name, …) |
| `webo/create-nav-menu` | Optional **`menu_name`** (default localized “New Menu”) — empty menu only; **no** theme assignment |
| `webo/create-nav-menu-for-location` | Optional **`menu_name`** (default “Primary Menu”), **`theme_location`** (default `primary`), **`replace`** (default `true`) |
| `webo/assign-nav-menu-to-location` | **`menu_id`** *or* **`menu_name`** (if no ID), optional **`theme_location`**, **`replace`** |
| `webo/list-nav-menu-items` | `menu_id` (required) |
| `webo/add-nav-menu-item-from-post` | `menu_id`, `post_id`, `post_type`, **`menu_order` ≥ 1**; optional `parent_db_id`, `menu_item_title` |
| `webo/add-nav-menu-item-custom` | `menu_id`, **`url`** (http/https), **`title`**, **`menu_order` ≥ 1**; optional `parent_db_id` |

3. **Rules:** To see **which menu is primary / header:** **`webo/list-nav-menu-locations`**, then **`webo/list-nav-menu-items`** for that `menu_id`. For **writes**, pick one flow: (a) **`webo/create-nav-menu`** then optionally **`webo/assign-nav-menu-to-location`**; (b) **`webo/create-nav-menu-for-location`** in one step (new menu + assign); (c) existing **`menu_id`** from **`webo/list-nav-menus`** + **`assign-nav-menu-to-location`**. Then use **`menu_id`** for **`list-nav-menu-items`** and add-item tools. Before adding items, call **`list-nav-menu-items`** to pick **`menu_order`** and **`parent_db_id`**. `post_type` must match `post_id`.

4. **Theme menu locations (`primary`, `main`, `header`, …)** — Slugs come from `register_nav_menu()`. **`create-nav-menu-for-location`** / **`assign-nav-menu-to-location`** resolve `primary` to the theme’s only slot or a common slug (`main`, `header`, `menu-1`, …) when needed; responses include **`theme_location_resolution`** (`exact`, `single_registered_location`, `common_slug_fallback`). Zero registered slots returns a clear error (block theme / no classic menus).

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
