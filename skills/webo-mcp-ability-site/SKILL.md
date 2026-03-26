---
name: webo-mcp-ability-site
description: >-
  WEBO MCP: site — plugin active/list, activate/deactivate, đọc/ghi tập option được phép
  (whitelist trong code). Dùng khi bật tắt plugin hoặc đọc cấu hình an toàn.
---

# Ability — Site (plugins & options) (webo/*)

**Điều kiện:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).

## Tool và quyền

| `name` | `permission` | Arguments |
|--------|--------------|-----------|
| `webo/list-active-plugins` | `activate_plugins` | `include_inactive` bool (default false) |
| `webo/toggle-plugin` | `activate_plugins` | `plugin` (đường dẫn file plugin); `action` `activate` / deactivate |
| `webo/get-options` | `manage_options` | `names` array các key option |
| `webo/update-options` | `manage_options` | `options` array key => value |

## Quy tắc

- Chỉ đọc/ghi option nằm trong **danh sách an toàn** do PHP `WordPressTools` kiểm tra — không giả định mọi `option` đều cho phép.
- Bật/tắt plugin có thể gây lỗi site; xác nhận với user trên môi trường production.
