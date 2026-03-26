---
name: webo-mcp-menu-creation
description: >-
  Step-by-step WEBO MCP workflows to create WordPress nav menus, assign theme locations,
  and add items (Appearance > Menus via MCP). Use when the user asks to tạo menu, gán menu,
  Primary Menu, theme location, header/footer menu, assign location, create-nav-menu,
  create-nav-menu-for-location, list-nav-menu-locations, or automate menus without wp-admin.
  Complements webo-mcp-ability-menus (tool table) with decision trees and multiple valid paths.
---

# WEBO MCP — Tạo menu và gán vị trí (nhiều cách)

## Khi nào đọc skill này

- User muốn **tạo menu mới**, **gán vào vị trí theme** (Main Menu, Footer, …), hoặc **chỉnh menu hiện có**.
- Làm việc qua **MCP** (`webo/*`), **n8n WEBO MCP**, hoặc client gọi `tools/call`.

**Điều kiện:** đã [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md) (session, `tools/call`). Bảng tool đầy đủ: [`webo-mcp-ability-menus`](../webo-mcp-ability-menus/SKILL.md).

**Quyền:**

- **Xem** danh sách menu / vị trí / mục: `edit_posts`.
- **Tạo menu, gán vị trí, thêm mục:** `edit_theme_options`.

---

## Luồng chung (luôn làm trước khi gán)

1. **`webo/list-nav-menu-locations`** (không tham số) — biết **slug** thật của theme. Trong `registered_locations`, **key** = giá trị `theme_location`; **value** = nhãn trong admin (“Main Menu”, …).
2. **`webo/list-nav-menus`** (không tham số) — nếu cần danh sách menu và `menu_id` / `menu_name` hiện có. **Không** hỏi user `menu_id` chỉ để liệt kê menu.
3. Chỉ khi cần chi tiết từng link: **`webo/list-nav-menu-items`** với `menu_id` lấy từ bước 2 hoặc từ `assigned` ở bước 1.

Nếu `registered_locations` **rỗng**: theme block / không đăng ký classic menu — xử lý trong **Site Editor**, không qua các tool menu cổ điển này.

---

## Cách 1 — Một lần gọi: tạo menu + gán vị trí

**Khi dùng:** menu mới và đã biết (hoặc chấp nhận fallback) `theme_location`.

- Tool: **`webo/create-nav-menu-for-location`**
- Tham số thường gặp:
  - `menu_name` (tuỳ chọn; mặc định localized “Primary Menu”)
  - `theme_location` (tuỳ chọn; mặc định `primary`) — ưu tiên **slug** từ `list-nav-menu-locations`
  - `replace` (mặc định `true`): ghi đè menu đang gắn ở slot đó

**Ghi nhận phản hồi:**

- `theme_location_resolution`: `exact` | `single_registered_location` | `common_slug_fallback` — biết slot thực tế được chọn.
- `reused_existing_menu`: menu trùng tên đã tồn tại; plugin tái dùng term đó.

---

## Cách 2 — Hai bước: chỉ tạo menu, gán sau

**Khi dùng:** cần tạo rỗng trước, hoặc tách quyền / logic.

1. **`webo/create-nav-menu`** — optional `menu_name` (mặc định “New Menu”). **Không** gán theme.
2. **`webo/assign-nav-menu-to-location`** — `theme_location` + một trong:
   - `menu_id` (từ bước 1 hoặc `list-nav-menus`), **hoặc**
   - `menu_name` (đúng tên trong admin) nếu không có ID.

---

## Cách 3 — Menu đã có: chỉ gán / đổi vị trí

**Khi dùng:** menu đã tồn tại (user chỉ muốn “gán lại chỗ hiển thị”).

- **`webo/assign-nav-menu-to-location`**
- Cung cấp `menu_id` **hoặc** `menu_name`, và `theme_location` (slug từ bước discover).
- `replace: false` nếu **không** muốn ghi đè slot đang có menu khác.

---

## Cách 4 — Chỉ cần menu rỗng trong danh sách (chưa gán theme)

**Khi dùng:** dự trữ menu, hoặc theme không dùng classic location.

- Chỉ **`webo/create-nav-menu`**.
- Gán sau bằng Cách 3 khi cần.

---

## Sau khi có menu: thêm mục (link)

1. Gọi **`webo/list-nav-menu-items`** với `menu_id` để chọn `menu_order` và `parent_db_id`.
2. Thêm nội dung trang/bài: **`webo/add-nav-menu-item-from-post`** (`post_id`, `post_type`, `menu_order` ≥ 1, …).
3. Hoặc link tuỳ chỉnh: **`webo/add-nav-menu-item-custom`** (`url`, `title`, `menu_order` ≥ 1).

---

## `primary` và slug theme

- Nhiều theme **không** đăng ký slug `primary`; có thể là `main`, `menu-1`, …
- MCP có thể **tự map** `primary` → slot duy nhất hoặc slug phổ biến; xem `theme_location_resolution` trong phản hồi.
- Để **chắc chắn** đúng slot (ví dụ “Main Menu - Mobile”): luôn lấy slug từ **`list-nav-menu-locations`**.

---

## Ví dụ `tools/call` (minh hoạ)

**Discover chỗ gắn:**

```json
{
  "name": "webo/list-nav-menu-locations",
  "arguments": {}
}
```

**Tạo + gán (slug thật từ `registered_locations`, ví dụ `main`):**

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

**Gán menu có sẵn theo tên:**

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

## Liên kết

| Tài liệu | Mục đích |
|----------|----------|
| [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md) | Router, session, auth |
| [`webo-mcp-ability-menus`](../webo-mcp-ability-menus/SKILL.md) | Bảng tool + quy tắc `menu_order` |
