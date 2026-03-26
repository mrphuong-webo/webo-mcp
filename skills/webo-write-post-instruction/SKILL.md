---
name: webo-write-post-instruction
description: >-
  Produces SEO-structured WordPress post HTML and calls webo/create-post over WEBO MCP
  after validating title and content. Use when the user asks to write a post, create or
  publish WordPress content, or generate SEO-oriented articles through the MCP router
  (webo/create-post, draft by default).
metadata:
  doc_id: webo_write_post_instruction
  tags:
    - wordpress
    - post
    - content
    - seo
---

# WEBO MCP — SEO post workflow (`webo/create-post`)

## Instructions

1. **Prerequisite:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md) and tool schema from [`webo-mcp-ability-posts`](../webo-mcp-ability-posts/SKILL.md) or [`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md).
2. **Role:** The agent drafts content and operates WordPress only through **WEBO MCP** (`webo/create-post`), not shell `wp`.
3. **Normalize input** (user may omit fields):

   - `title` (string)
   - `keyword` (string)
   - `content` (HTML string, optional until generated)
   - `status`: `draft` | `publish` (default **`draft`**)
   - `post_type` (default `post`)

4. **Before `tools/call`:** Require non-empty **`title`** and **`content`**. If only `keyword` is given, derive `title` then **generate** HTML `content` (intro, `h2`/`h3`, CTA) compatible with **`wp_kses_post`**. Do not invent facts, stats, or legal claims beyond user input.
5. **Do not call** `webo/create-post` if `title` or `content` is still empty after normalization.
6. **Execution order:** `initialize` → keep `session_id` → `tools/call` with `name`: `webo/create-post` and `arguments`: `{ title, content, status, post_type }`. On success, read `post_id`; optional `webo/get-post` for permalink.

## Examples

Normalized tool arguments:

```json
{
  "title": "…",
  "content": "<p>…</p><h2>…</h2>",
  "status": "draft",
  "post_type": "post"
}
```

Inside JSON-RPC `params`:

```json
{
  "session_id": "<session_id>",
  "name": "webo/create-post",
  "arguments": {
    "title": "{{title}}",
    "content": "{{content}}",
    "status": "{{status}}",
    "post_type": "{{post_type}}"
  }
}
```
