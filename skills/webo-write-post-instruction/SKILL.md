---
skill: webo_write_post_instruction
intent: >-
  Viết bài WordPress, tạo bài viết, đăng bài, viết content SEO qua WEBO MCP
  (webo/create-post).
tool: webo_mcp
module: wordpress
tags:
  - wordpress
  - post
  - content
  - seo
  - viết bài
doc_id: webo_write_post_instruction
name: webo-write-post-instruction
description: >-
  Hướng dẫn agent viết bài SEO và đăng lên WordPress qua WEBO MCP. Dùng khi user
  muốn viết bài, tạo bài viết, đăng bài WordPress, hoặc viết content SEO;
  tool chính: webo/create-post.
---

# ROLE

Bạn là AI Agent chuyên tạo nội dung và vận hành WordPress thông qua **WEBO MCP** (`tool: webo_mcp`, `module: wordpress`).

# OBJECTIVE

Tạo một bài viết chuẩn SEO, chuẩn hóa dữ liệu, rồi gọi **`webo/create-post`** để tạo bài trên WordPress (draft hoặc publish theo yêu cầu).

# WHEN TO USE

Dùng skill này khi user yêu cầu:

- viết bài
- tạo bài viết
- đăng bài WordPress
- viết content SEO

# INPUT

User có thể cung cấp thiếu trường. Chuẩn hóa nội bộ thành:

```json
{
  "title": "string",
  "keyword": "string",
  "content": "string (HTML, optional trước bước generate)",
  "status": "draft | publish",
  "post_type": "string (optional, default post)"
}
```

**Trước khi gọi tool**, bắt buộc có:

- `title` (non-empty)
- `content` (non-empty HTML, sau khi generate nếu ban đầu thiếu)
- `status` (mặc định `draft` nếu user không nói rõ)
- `post_type` mặc định `post` nếu không chỉ định

Nếu thiếu `title` nhưng có `keyword`: suy ra tiêu đề hợp lệ từ keyword. Nếu thiếu `content`: **generate** từ `keyword` (và/hoặc `title`) theo RULES rồi mới gọi tool.

# RULES

- **Không** gọi `webo/create-post` nếu sau chuẩn hóa vẫn thiếu `title` hoặc `content`.
- Nếu thiếu `content` → tự generate từ `keyword` / `title` (ưu tiên dữ liệu user đã cho).
- Nội dung bài phải có: **mở bài**, **heading** rõ ràng (HTML h2/h3), **CTA**; markup chuẩn để **`wp_kses_post`** trên server không strip quá mức.
- Không bịa số liệu, tên riêng, cam kết pháp lý ngoài phạm vi user cung cấp.
- Luôn **chuẩn hóa** object arguments (trim, `status` hợp lệ, HTML hợp lệ) trước `tools/call`.
- Mặc định **`status: draft`** trừ khi user yêu cầu đăng ngay (`publish`).

# TOOL USAGE LOGIC

1. **Chọn tool:** `webo/create-post`.
2. **Schema:** lấy từ collection schema (Qdrant) nếu pipeline có; không thì `tools/list`, [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md), [`webo-mcp-ability-posts`](../webo-mcp-ability-posts/SKILL.md), hoặc [`webo-mcp-wordpress-content/SKILL.md`](../webo-mcp-wordpress-content/SKILL.md) (mục `webo/create-post`).
3. **Validate trước call:**

   - `title`: required (string không rỗng)
   - `content`: required (sau generate nếu cần)
   - `status`: optional, default `draft`
   - `post_type`: optional, default `post`

4. **Chuẩn hóa** payload rồi gửi trong `tools/call` → `params.arguments`.

# OUTPUT (FOR TOOL)

Payload gửi vào **`params.arguments`** của `webo/create-post` (không nhầm với body JSON-RPC ngoài cùng):

```json
{
  "title": "...",
  "content": "...",
  "status": "draft",
  "post_type": "post"
}
```

# EXECUTION

Sau khi đủ `title`, `content`, `status` (và `post_type` nếu cần):

1. **`initialize`** → giữ `session_id`.
2. **`tools/call`** với:

   - `params.name`: `webo/create-post`
   - `params.arguments`: như OUTPUT (FOR TOOL)

Ví dụ khối `params`:

```json
{
  "session_id": "{{session_id}}",
  "name": "webo/create-post",
  "arguments": {
    "title": "{{title}}",
    "content": "{{content}}",
    "status": "{{status}}",
    "post_type": "{{post_type}}"
  }
}
```

Sau thành công, xác nhận `post_id` từ response; có thể gọi `webo/get-post` để kiểm tra permalink nếu cần.
