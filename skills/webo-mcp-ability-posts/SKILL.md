---
name: webo-mcp-ability-posts
description: >-
  WEBO MCP: post, page, CPT — list/get/discover, URL/slug, homepage, create/update/delete,
  bulk status, revisions, search-replace, featured image. Dùng khi task liên quan nội dung bài viết hoặc
  thay thế hàng loạt trong HTML bài.
---

# Ability — Posts & nội dung (webo/*)

**Điều kiện:** đã đọc [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md) (session, `tools/list`).

## Tool và quyền

| `name` | `permission` | Ghi chú ngắn |
|--------|--------------|--------------|
| `webo/list-posts` | read | `per_page` 1–100, `post_type`, `search`, `status` |
| `webo/get-post` | read | `post_id` bắt buộc; `post_type` tùy chọn để validate |
| `webo/discover-content-types` | read | Không arguments |
| `webo/find-content-by-url` | read | `url` bắt buộc; `update` array tùy chọn |
| `webo/get-content-by-slug` | read | `slug` bắt buộc; `post_type` tùy chọn |
| `webo/get-homepage-info` | read | `post_id`, `include_excerpt`, `include_content` tùy chọn |
| `webo/create-post` | edit_posts | `title` bắt buộc; `content`, `post_type`, `status` |
| `webo/update-post` | edit_posts | `post_id` bắt buộc; `title`, `content`, `status` |
| `webo/delete-post` | delete_posts | `post_id`; `force` bool |
| `webo/bulk-update-post-status` | edit_posts | `post_ids` array; `status` |
| `webo/list-revisions` | edit_posts | `post_id` |
| `webo/restore-revision` | edit_posts | `revision_id` |
| `webo/search-replace-posts` | edit_posts | `search`; `replace`, `dry_run` (default true), `offset`, `max_scan_posts` 1–500 |
| `webo/set-post-featured-image` | edit_posts | `post_id`; `attachment_id` **hoặc** `remove: true` để gỡ ảnh đại diện |

## Quy tắc

- HTML trong `content` qua **`wp_kses_post`** khi create/update.
- **Search-replace:** không bao giờ `dry_run: false` nếu chưa preview và chưa được user xác nhận.
- **Bulk:** kiểm tra mẫu `post_id` trước khi đổi trạng thái hàng loạt.

## Payload mẫu — tạo bài

```json
{
  "session_id": "<…>",
  "name": "webo/create-post",
  "arguments": {
    "title": "Tiêu đề",
    "content": "<p>…</p>",
    "post_type": "post",
    "status": "draft"
  }
}
```

## Payload mẫu — ảnh đại diện (sau khi có `attachment_id`, vd từ upload-media-from-url)

```json
{
  "session_id": "<…>",
  "name": "webo/set-post-featured-image",
  "arguments": {
    "post_id": 42,
    "attachment_id": 100
  }
}
```

Gỡ ảnh đại diện: `"arguments": { "post_id": 42, "remove": true }`.
