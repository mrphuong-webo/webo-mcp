---
name: webo-mcp-ability-media
description: >-
  Documents WEBO MCP unified media tools: webo/media-query and webo/media-mutate.
  Use for listing, reading, importing, updating, and deleting attachments via tools/call
  with action-based arguments.
---

# WEBO MCP — Media

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md).
2. **Tools & permissions:** Both tools require **`upload_files`**. `media-mutate` delete additionally requires `delete_files`.

| `name` | `action` | Arguments |
|--------|----------|-----------|
| `webo/media-query` | `list` | `per_page` 1–100 (default 20) |
| `webo/media-query` | `get` | `attachment_id` |
| `webo/media-mutate` | `upload` | `image_url` required; optional `filename`, `title`, `alt_text` |
| `webo/media-mutate` | `update` | `attachment_id`; optional `title`, `alt_text`, `caption` |
| `webo/media-mutate` | `delete` | `attachment_id` |

3. **Featured image:** After upload, call **`webo/content-mutate`** with `action: set-featured-image`, `id`/`post_id`, and `attachment_id` — see [`webo-mcp-ability-posts`](../webo-mcp-ability-posts/SKILL.md).
4. **Rules:** `webo/media-mutate` with `action: upload` accepts public **http(s)** only; loopback/private targets are blocked (SSRF hardening).

## Examples

Upload from URL:

```json
{
  "session_id": "<…>",
  "name": "webo/media-mutate",
  "arguments": {
    "action": "upload",
    "image_url": "https://example.com/file.jpg",
    "title": "",
    "alt_text": ""
  }
}
```

Get one attachment:

```json
{
  "session_id": "<…>",
  "name": "webo/media-query",
  "arguments": {
    "action": "get",
    "attachment_id": 123
  }
}
```
