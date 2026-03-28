---
name: webo-mcp-extensions
description: >-
  WEBO MCP ecosystem: core vs companion plugins, tool naming (webo/*, ability names),
  hooks (webo_mcp_register_tools), and which Cursor skills map to each package. Use when
  the user adds or builds an addon plugin, audits webo/list-active-plugins, or asks how
  Rank Math / Ultimo integrates with webo-mcp.
---

# WEBO MCP — Extensions & companion plugins

## Instructions

### 1. Packages (chuẩn hóa tên)

| Vai trò | Package / repo (typical) | Kích hoạt MCP tools | Skill agent |
|--------|---------------------------|---------------------|-------------|
| **Core** | [webo-mcp](https://github.com/mrphuong-webo/webo-mcp) | `webo/*` (posts, media, taxonomy, …) | [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md), [`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md), `webo-mcp-ability-*` |
| **Rank Math bridge** | [webo-mcp-rank-math](https://github.com/mrphuong-webo/webo-mcp-rank-math) + Rank Math SEO | Abilities **`webo-rank-math/*`** bridged như MCP | [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md) |
| **WP Ultimo / SaaS** | Companion (ví dụ webo-mcp-ultimo) | Thường thêm tools qua `webo_mcp_register_tools` + filter quyền | Theo từng repo; tham chiếu `examples/webo-mcp-ultimo-example.php` |

**Quy ước tên plugin ZIP / slug:** `webo-mcp` (core), companion dùng tiền tố **`webo-mcp-`** + domain (`webo-mcp-rank-math`, `webo-mcp-ultimo`, …).

### 2. Quy ước tên **tool** (MCP `tools/call` → `name`)

| Nguồn | Dạng `name` | Ghi chú |
|--------|-------------|---------|
| Core | `webo/<action>` | Chữ thường, gạch ngang, một segment sau `webo/` (ví dụ `webo/list-posts`). |
| Abilities API (bridge) | Đúng **tên ability** đã đăng ký | Addon Rank Math đăng ký `webo-rank-math/...` — **không** đổi tên khi gọi MCP. |
| Custom PHP (`webo_mcp_register_tools`) | `<prefix>/<action>` | Tránh chiếm `webo/*`. Dùng prefix tổ chức (`acme-crm/sync-order`). File mẫu trong repo: `examples/addon-rankmath-example.php` (tên ví dụ `rankmath/...` chỉ để minh họa code — production dùng gói **webo-mcp-rank-math**). |

Sau **`initialize`**, luôn **`tools/list`** trên site đích để lấy danh sách thực tế (addon tắt → không có `webo-rank-math/*`).

### 3. Phát hiện addon đã cài

- **`webo/list-active-plugins`** ([`webo-mcp-ability-site`](../webo-mcp-ability-site/SKILL.md)) — quyền `activate_plugins`.
- Đối chiếu bảng trên: nếu thiếu plugin companion, hướng dẫn cài / kích hoạt trước khi gọi tool tương ứng.

### 4. Phát triển companion plugin

1. Phụ thuộc **WEBO MCP** (load sau core).
2. Đăng ký tool: hook **`webo_mcp_register_tools`** → `ToolRegistry::register()` (xem `examples/addon-rankmath-example.php`).
3. *Hoặc* đăng ký **Ability** + `mcp.public` meta nếu muốn dùng bridge core (giống Rank Math addon).
4. Thu hẹp quyền multisite / khách hàng: filter **`webo_mcp_current_user_can_use_mcp`** (xem `examples/webo-mcp-ultimo-example.php`).

### 5. Skill đi kèm

Mỗi companion **nên** có một `skills/webo-mcp-*/SKILL.md` trong repo của nó hoặc trong core (như Rank Math) để agent biết `name` + arguments + prerequisite.

## Examples

Kiểm tra plugin trước khi gọi Rank Math:

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "session_id": "<…>",
    "name": "webo/list-active-plugins",
    "arguments": { "include_inactive": true }
  },
  "id": 1
}
```

Sau đó `tools/list` và tìm tiền tố `webo-rank-math/`.
