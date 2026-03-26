---
name: webo-mcp-guide
description: >-
  Hướng dẫn chung WEBO MCP: luồng JSON-RPC, auth, session, cách chọn skill theo nhóm
  tool webo/*. Dùng trước mọi thao tác WordPress qua MCP hoặc khi cần định hướng
  giữa các skill con (posts, media, taxonomy, comments, menus, users, site).
---

# WEBO MCP — Hướng dẫn chung

Skill này là **điểm vào**: nắm transport MCP, quyền, và **skill nào** mở cho từng loại việc. Chi tiết từng nhóm tool nằm trong **`webo-mcp-ability-*`** tương ứng.

## Luồng bắt buộc

| Bước | Việc |
|------|------|
| 1 | `POST` router MCP (thường `/wp-json/mcp/v1/router`) — xem `README.md` repo |
| 2 | `initialize` → lưu **`session_id`** |
| 3 | `tools/list` → đối chiếu tên & tham số đúng với site |
| 4 | `tools/call` → mỗi lần gửi `session_id`, `name` (vd. `webo/create-post`), `arguments` |

**Luôn** dùng đúng slug `webo/*` như `tools/list` trả về. Tham số chi tiết: `webo-mcp.php` (`webo_mcp_register_standalone_core_tools`) + callback `WordPressTools`.

## Auth & lỗi thường gặp

- Site có thể dùng cookie, API key, hoặc **HMAC** (`webo-hmac-auth`).
- Thiếu quyền: **`403`** từ `ToolRegistry`.
- Mỗi tool có **`permission`** (vd. `read`, `edit_posts`, `upload_files`) — thất bại trả `WP_Error`.

## WordPress Abilities API

Plugin có thể **auto-bridge** ability đăng ký trên site thành tool MCP (filter `webo_mcp_auto_bridge_abilities`, deny pattern `webo_mcp_bridge_deny_patterns`). Tool **mặc định** trong repo là các **`webo/*`** core; ability từ plugin khác xuất hiện thêm sau `tools/list` — vẫn cùng luồng `tools/call`.

## Chọn skill theo việc

| Nhu cầu | Skill (thư mục trong `skills/`) |
|---------|----------------------------------|
| Bài viết, trang, CPT, revision, tìm-thay thế nội dung, homepage | **webo-mcp-ability-posts** |
| Thư viện media, upload từ URL | **webo-mcp-ability-media** |
| Taxonomy, term, gán term cho bài | **webo-mcp-ability-taxonomy** |
| Comment | **webo-mcp-ability-comments** |
| Menu điều hướng | **webo-mcp-ability-menus** |
| Danh sách user | **webo-mcp-ability-users** |
| Plugin bật/tắt, đọc/ghi option an toàn | **webo-mcp-ability-site** |
| Viết bài SEO + tạo draft/publish qua MCP | **webo-write-post-instruction** |
| Một file tham chiếu đầy đủ (bảng tool tổng) | **webo-mcp-wordpress-content** |

## Nguyên tắc an toàn (mọi skill)

- Nội dung mới: ưu tiên **`draft`** trừ khi user bảo đăng ngay.
- **`webo/search-replace-posts`**: luôn **`dry_run: true`** trước, rồi mới `false` sau khi user đồng ý.
- **`webo/upload-media-from-url`**: chỉ URL **http(s)** công khai (chặn SSRF).
- Menu: **`menu_order` ≥ 1**, xem `list-nav-menu-items` trước khi thêm item.

## Ví dụ `tools/call` (khung)

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "session_id": "<từ initialize>",
    "name": "webo/<tên-tool>",
    "arguments": {}
  },
  "id": 1
}
```

Sau khi đọc skill chung, mở **`webo-mcp-ability-*`** phù hợp cho schema đầy đủ từng tool.
