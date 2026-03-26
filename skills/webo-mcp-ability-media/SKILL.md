---
name: webo-mcp-ability-media
description: >-
  WEBO MCP: thư viện media — list, get, update metadata, delete, upload từ URL.
  Dùng khi task là ảnh/tập tin đính kèm, sideload URL.
---

# Ability — Media (webo/*)

**Điều kiện:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).

## Tool và quyền

Tất cả dưới đây: `permission` **`upload_files`**.

| `name` | Arguments chính |
|--------|------------------|
| `webo/list-media` | `per_page` 1–100 (default 20) |
| `webo/upload-media-from-url` | `image_url` bắt buộc; `filename`, `title`, `alt_text` |
| `webo/get-media` | `attachment_id` |
| `webo/update-media` | `attachment_id`; `title`, `alt_text`, `caption` tùy chọn |
| `webo/delete-media` | `attachment_id` |

## Quy tắc

- **`webo/upload-media-from-url`:** chỉ URL http(s) hợp lệ, công khai; plugin chặn loopback/private (SSRF).
- **Ảnh đại diện bài:** core không có `webo/set-featured-image`; cần `_thumbnail_id` qua REST/admin/ability tùy chỉnh sau khi có `attachment_id`.

## Payload mẫu — upload từ URL

```json
{
  "session_id": "<…>",
  "name": "webo/upload-media-from-url",
  "arguments": {
    "image_url": "https://example.com/file.jpg",
    "title": "",
    "alt_text": ""
  }
}
```
