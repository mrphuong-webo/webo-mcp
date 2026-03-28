---
name: webo-mcp-rank-math
description: >-
  Rank Math SEO over WEBO MCP using the optional addon plugin webo-mcp-rank-math: WordPress
  Abilities named webo-rank-math/* (post/term/user SEO meta, options, modules, plugin status,
  Rank Math redirections) are bridged to the same tool names in tools/list. Requires WEBO MCP,
  Rank Math SEO (seo-by-rank-math), and the addon installed and activated from
  https://github.com/mrphuong-webo/webo-mcp-rank-math. Use when the user mentions Rank Math,
  SEO title, meta description, focus keyword, webo-rank-math/get-post-seo-meta, bulk SEO meta,
  redirections, or MCP automation that depends on this addon.
---

# WEBO MCP — Rank Math (`webo-rank-math/*`)

## Instructions

1. **Addon (bắt buộc):** Cài plugin từ GitHub **[WEBO MCP – Rank Math addon](https://github.com/mrphuong-webo/webo-mcp-rank-math)** và **kích hoạt** trên WordPress. Không có addon thì **không** có công cụ `webo-rank-math/*` trong `tools/list`.
2. **Core dependencies:** **[WEBO MCP](https://github.com/mrphuong-webo/webo-mcp)** (router, Abilities bridge) và **[Rank Math SEO](https://rankmath.com/)** (`seo-by-rank-math`) đều phải **active**. Thứ tự khuyên dùng: Rank Math → WEBO MCP → addon Rank Math.
3. **Entry flow:** Đọc **[`webo-mcp-guide`](../webo-mcp-guide/SKILL.md)** — `initialize` → giữ **`session_id`** → **`tools/list`** để xác nhận tên đúng và **JSON schema** từng ability (đối số có thể khác giữa các phiên bản addon).
4. **Cơ chế:** Addon đăng ký **WordPress Abilities API** (`wp_register_ability`) với prefix **`webo-rank-math/`**. WEBO MCP **tự bridge** các ability đó thành MCP tools cùng tên (filter `webo_mcp_auto_bridge_abilities` mặc định bật).

### Tool map (tham chiếu nhanh)

| `name` | Ghi chú |
|--------|---------|
| `webo-rank-math/get-plugin-status` | Trạng thái Rank Math, version, modules. |
| `webo-rank-math/get-post-seo-meta` | **`post_id`** hoặc **`slug`** + **`post_type`**; phản hồi có **`seo_meta`** (các key `rank_math_*`). SEO title tùy chỉnh: **`rank_math_title`**. |
| `webo-rank-math/update-post-seo-meta` | **`seo_meta`**: object key → value; `null` xóa key. |
| `webo-rank-math/bulk-upsert-post-seo-meta` | Nhiều bài; **`items`** (tối đa 200). |
| `webo-rank-math/get-term-seo-meta` / `webo-rank-math/update-term-seo-meta` | Meta SEO taxonomy. |
| `webo-rank-math/get-options` / `webo-rank-math/update-options` | Option groups (`rank_math_*`). |
| `webo-rank-math/get-modules` / `webo-rank-math/update-modules` | Module bật/tắt. |
| `webo-rank-math/get-user-seo-meta` / `webo-rank-math/update-user-seo-meta` | Author archive SEO. |
| `webo-rank-math/list-redirections` | **`limit`**, **`paged`**, **`status`**, **`search`**. |
| `webo-rank-math/get-redirection` | Theo **`id`**. |
| `webo-rank-math/create-redirection` | **`source`**, **`destination`**, **`type`** (301, 302, …), **`comparison`**, **`ignore_case`**, **`status`**. |
| `webo-rank-math/update-redirection` / `webo-rank-math/delete-redirection` | Cập nhật / xóa. |

Chi tiết và quyền: [README addon trên GitHub](https://github.com/mrphuong-webo/webo-mcp-rank-math/blob/main/README.md).

### Quy tắc cho agent

- **Tiêu đề SEO một bài:** ưu tiên **`webo-rank-math/get-post-seo-meta`** với **`post_id`** (hoặc slug) và đọc **`seo_meta.rank_math_title`**. **`webo/get-post`** chỉ cho tiêu đề bài WordPress, không thay thế meta Rank Math.
- **Không** bảo user vào wp-admin nếu **`tools/list`** đã có các tool trên và phiên làm việc có quyền.
- **URL nguồn addon:** luôn dùng **`https://github.com/mrphuong-webo/webo-mcp-rank-math`** khi hướng dẫn cài/kích hoạt.
- Cập nhật meta: chỉ gửi các key cần đổi trong **`seo_meta`**; redirection **`create-redirection`** cần module Redirections của Rank Math.

## Examples

Đọc meta SEO bài **ID 37**:

```json
{
  "session_id": "<from initialize>",
  "name": "webo-rank-math/get-post-seo-meta",
  "arguments": { "post_id": 37 }
}
```

Cập nhật tiêu đề + mô tả SEO:

```json
{
  "session_id": "<from initialize>",
  "name": "webo-rank-math/update-post-seo-meta",
  "arguments": {
    "post_id": 37,
    "seo_meta": {
      "rank_math_title": "Custom SEO title",
      "rank_math_description": "Meta description."
    }
  }
}
```

Tạo redirect 301:

```json
{
  "session_id": "<from initialize>",
  "name": "webo-rank-math/create-redirection",
  "arguments": {
    "source": "old-slug",
    "destination": "https://example.com/new-url/",
    "type": "301",
    "comparison": "exact",
    "status": "active"
  }
}
```

## Reference

- Addon (mã nguồn + hướng dẫn): [github.com/mrphuong-webo/webo-mcp-rank-math](https://github.com/mrphuong-webo/webo-mcp-rank-math)
