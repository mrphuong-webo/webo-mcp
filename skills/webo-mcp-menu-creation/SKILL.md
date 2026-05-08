---
name: webo-mcp-menu-creation
description: >-
  Step-by-step WEBO MCP workflows to create WordPress nav menus, assign theme locations,
  and add items (Appearance > Menus via MCP). Use when the user asks to create a menu,
  assign a menu, Primary Menu, theme location, header/footer menu, assign location,
  create-nav-menu, create-nav-menu-for-location, list-nav-menu-locations, or automate
  menus without wp-admin. Complements webo-mcp-ability-menus (tool table) with decision
  trees and multiple valid paths.
---

# WEBO MCP — Create menus and assign locations (several paths)

## When to use this skill

- The user wants a **new menu**, **assignment to a theme location** (Main Menu, Footer, …), or to **edit an existing menu**.
- Work happens over **MCP** (`webo/*`), **n8n WEBO MCP**, or any client that calls `tools/call`.

**Prerequisites:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md) (session, `tools/call`). Full tool table: [`webo-mcp-ability-menus`](../webo-mcp-ability-menus/SKILL.md).

**Capabilities:**

- **View** menu / location / item lists: `edit_posts`.
- **Create menus, assign locations, add items:** `edit_theme_options`.

---

## Common flow (always before assigning)

1. **`webo/list-nav-menu-locations`** (no args) — learn the theme’s real **slugs**. In `registered_locations`, **key** = `theme_location` value; **value** = admin label (“Main Menu”, …).
2. **`webo/list-nav-menus`** (no args) — when you need existing menus and `menu_id` / `menu_name`. **Do not** ask the user for `menu_id` just to list menus.
3. Only when you need each link in detail: **`webo/list-nav-menu-items`** with `menu_id` from step 2 or from `assigned` in step 1.

If `registered_locations` is **empty**: block theme / no classic menu registration — handle in the **Site Editor**, not these classic menu tools.

---

## Path 1 — One call: create menu + assign location

**When:** New menu and you know (or accept fallback for) `theme_location`.

- Tool: **`webo/create-nav-menu-for-location`**
- Common arguments:
  - `menu_name` (optional; default localized “Primary Menu”)
  - `theme_location` (optional; default `primary`) — prefer the **slug** from `list-nav-menu-locations`
  - `replace` (default `true`): overwrite whatever is already in that slot

**Read the response:**

- `theme_location_resolution`: `exact` | `single_registered_location` | `common_slug_fallback` — shows which slot was actually used.
- `reused_existing_menu`: a menu with that name already existed; the plugin reused that term.

---

## Path 2 — Two steps: create empty menu, assign later

**When:** You need an empty menu first, or you want to split permissions / logic.

1. **`webo/create-nav-menu`** — optional `menu_name` (default “New Menu”). **Does not** assign a theme.
2. **`webo/assign-nav-menu-to-location`** — `theme_location` plus one of:
   - `menu_id` (from step 1 or `list-nav-menus`), **or**
   - `menu_name` (exact admin name) if you have no ID.

---

## Path 3 — Menu already exists: assign or move only

**When:** Menu exists; user only wants to “put it in the right place”.

- **`webo/assign-nav-menu-to-location`**
- Pass `menu_id` **or** `menu_name`, and `theme_location` (slug from discovery).
- `replace: false` if you **do not** want to overwrite a slot that already has another menu.

---

## Path 4 — Empty menu in the list only (not tied to theme yet)

**When:** Staging a menu, or the theme has no classic location.

- Only **`webo/create-nav-menu`**.
- Assign later with Path 3 when needed.

---

## After you have a menu: add items (links)

1. Call **`webo/list-nav-menu-items`** with `menu_id` to choose `menu_order` and `parent_db_id`.
2. Add a post/page: **`webo/add-nav-menu-item-from-post`** (`post_id`, `post_type`, `menu_order` ≥ 1, …).
3. Or a custom link: **`webo/add-nav-menu-item-custom`** (`url`, `title`, `menu_order` ≥ 1).

---

## `primary` vs theme slugs

- Many themes **do not** register `primary`; it may be `main`, `menu-1`, …
- MCP may **map** `primary` → the only slot or a common slug; see `theme_location_resolution` in the response.
- To **pin** the right slot (e.g. “Main Menu - Mobile”): always take the slug from **`list-nav-menu-locations`**.

---

## Example `tools/call` snippets

**Discover assignment slots:**

```json
{
  "name": "webo/list-nav-menu-locations",
  "arguments": {}
}
```

**Create + assign (real slug from `registered_locations`, e.g. `main`):**

```json
{
  "name": "webo/create-nav-menu-for-location",
  "arguments": {
    "menu_name": "Primary Menu",
    "theme_location": "main",
    "replace": true
  }
}
```

**Assign an existing menu by name:**

```json
{
  "name": "webo/assign-nav-menu-to-location",
  "arguments": {
    "menu_name": "Primary Menu",
    "theme_location": "main",
    "replace": true
  }
}
```

---

## See also

| Doc | Purpose |
|-----|---------|
| [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md) | Router, session, auth |
| [`webo-mcp-ability-menus`](../webo-mcp-ability-menus/SKILL.md) | Tool table + `menu_order` rules |
