---
name: webo-mcp-ability-media
description: >-
  Documents WEBO MCP media library tools: list, get, update metadata, delete, and
  sideload from URL. Use for attachments or remote imports via tools/call
  (webo/list-media, webo/upload-media-from-url, etc.). Featured images on posts use
  webo/set-post-featured-image in webo-mcp-ability-posts after upload.
---

# WEBO MCP — Media

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions:** All below require **`upload_files`**.

| `name` | Arguments |
|--------|-----------|
| `webo/list-media` | `per_page` 1–100 (default 20) |
| `webo/upload-media-from-url` | `image_url` required; optional `filename`, `title`, `alt_text` |
| `webo/get-media` | `attachment_id` |
| `webo/update-media` | `attachment_id`; optional `title`, `alt_text`, `caption` |
| `webo/delete-media` | `attachment_id` |

3. **Featured image:** After upload, call **`webo/set-post-featured-image`** with `post_id` and `attachment_id` — see [`webo-mcp-ability-posts`](../webo-mcp-ability-posts/SKILL.md).
4. **Rules:** **`webo/upload-media-from-url`** accepts public **http(s)** only; loopback/private targets are blocked (SSRF hardening).

## Examples

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
