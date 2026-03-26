---
name: webo-mcp-ability-menus
description: >-
  Documents WEBO MCP navigation menu tools: list menus and items, add a link from
  an existing post/page/CPT, or add a custom URL. Explains that theme menu locations
  (Primary, Main, etc.) are set in WP admin or WP-CLI, not via webo/* tools. Use when
  editing Appearance > Menus via MCP (webo/list-nav-menus, webo/add-nav-menu-item-from-post,
  webo/add-nav-menu-item-custom) or when the header menu does not update after MCP changes.
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

3. **Rules:** Always call **`webo/list-nav-menu-items`** first to pick valid **`menu_order`** and **`parent_db_id`**. `post_type` must match the real type of `post_id`.

4. **Theme menu locations (`primary`, `main`, `header`, …)** — MCP tools only attach **items** to an existing nav menu (a `nav_menu` term). They do **not** map that menu to a **theme location** (the slots themes register with `register_nav_menu()`, often labeled *Primary*, *Main*, or similar in **Appearance → Menus**).

   - **If the front site shows no new items:** the menu may exist but not be assigned to the location your header uses. Complete this **once per theme** in WP admin: **Appearance → Menus** → open the **Menu Settings** / **Manage Locations** area (wording depends on WP version) → for the location you need (e.g. *Primary Menu*), choose the **same** menu whose `menu_id` you used in MCP → **Save**.
   - **Pick the right menu first:** `webo/list-nav-menus` returns `term_id` / name — use the menu your theme actually displays, or create/sync naming in admin so operators know which `menu_id` maps to the header.
   - **After assigning:** clear page/cache/CDN if the theme or a plugin caches menus.

   There is **no** `webo/*` tool for setting `nav_menu_locations`; automation beyond items requires custom code, REST, or WP-CLI (e.g. **`wp menu assign`** `<menu>` `<location>` — see `wp menu assign --help`).

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
