---
name: webo-mcp-rank-math
description: >-
  WEBO MCP Rank Math SEO tools (rankmath/*): post/page SEO meta, global schema/publisher,
  options, modules, sitemap & llms.txt diagnostics, 404 logs, redirections. Requires the
  mcp-rank-math add-on plugin plus Rank Math SEO and WEBO MCP. Use when the user mentions
  Rank Math, SEO title for a post ID, meta title/description, focus keyword, SEO audit,
  rankmath/get-meta, rankmath/update-meta, redirects, 404 log, sitemap, llms.txt,
  or MCP SEO automation
  without the WordPress Abilities API stack.
---

# WEBO MCP — Rank Math (`rankmath/*`)

## Prerequisites

1. **[`webo-mcp-guide`](../webo-mcp-guide/SKILL.md)** — router, `session_id`, `tools/call`.
2. **Plugins active:** [WEBO MCP](https://github.com/mrphuong-webo/webo-mcp) + **Rank Math SEO** + **`mcp-rank-math`** (folder `mcp-rank-math/` in the webo-mcp repo).
3. Tool names use the same **`rankmath/*`** prefix as [mcp-abilities-rankmath](https://github.com/bjornfix/mcp-abilities-rankmath); responses often include a **`tool`** field.

Call **`tools/list`** on the site to confirm which `rankmath/*` tools are exposed (add-on must be activated).

## Permissions (summary)

| Capability | Tools |
|------------|--------|
| **`edit_posts`** | `rankmath/get-meta`, `rankmath/update-meta`, `rankmath/bulk-get-meta` |
| **`manage_options`** | All other `rankmath/*` (options, schema/site, modules, rewrite/llms, sitemap, social/publisher, 404, redirections) |

## Tool map (quick reference)

| `name` | Purpose |
|--------|---------|
| `rankmath/get-meta` | SEO fields for one post/page — argument **`id`** (post ID). Response includes **`title`** (WordPress post title) and **`seo_title`** / **`seo_description`** / **`focus_keyword`** (Rank Math meta). Custom SEO title is **`seo_title`**; empty string usually means Rank Math uses the template/default (same as an empty field in the editor metabox). |
| `rankmath/update-meta` | Update meta; pass only fields to change. Aliases: **`title`** → SEO title, **`description`** → meta description, **`keyword`** → focus keyword. Also: `robots` (array), `canonical_url`, `is_pillar`, `is_cornerstone`. |
| `rankmath/bulk-get-meta` | Audit many items (`post_type`, `per_page`, `page`, optional `missing_desc`, `search`). |
| `rankmath/list-options` | List `rank_math_*` / `rank-math-*` option names (`limit`, `offset`). |
| `rankmath/get-options` | **`options`**: array of option names. |
| `rankmath/update-options` | **`options`**: object map name → value (allowed names only). |
| `rankmath/get-schema-status` | Global publisher / Knowledge Graph–style summary. |
| `rankmath/list-modules` / `rankmath/update-modules` | Module list; `update-modules`: **`enable`** / **`disable`** string arrays. |
| `rankmath/get-rewrite-status` | **`endpoint`**: `llms.txt` / `sitemap_index.xml` / `custom` + optional **`custom_regex`**. |
| `rankmath/get-llms-status` | Module + preview; **`preview_lines`**. |
| `rankmath/preview-llms` | **`max_lines`**. |
| `rankmath/refresh-llms-route` | **`force_flush`** boolean. |
| `rankmath/update-publisher-profile` | Partial update of publisher fields (see plugin README / upstream ability schema). |
| `rankmath/get-social-profiles` / `rankmath/update-social-profiles` | Global social / sameAs inputs. |
| `rankmath/get-sitemap-status` | Sitemap module + enabled types + preview. |
| `rankmath/list-404-logs` | **`per_page`**, **`page`**. |
| `rankmath/delete-404-logs` | **`ids`**: array of integers. |
| `rankmath/clear-404-logs` | **`confirm`**: true required. |
| `rankmath/list-redirections` | **`per_page`**, **`page`**. |
| `rankmath/create-redirection` | **`sources`**: `[{ pattern, comparison, ignore_case }]`, **`destination`**, **`header_code`**, **`status`**. |
| `rankmath/delete-redirections` | **`ids`**. |

## Rules for agents

- **Per-post SEO title (common pitfall):** When the user gives a **post ID** and asks for **SEO title** / **Rank Math title** / **tiêu đề SEO**, call **`rankmath/get-meta`** with **`{ "id": <ID> }`** if that tool appears in **`tools/list`** (requires add-on **mcp-rank-math**). Report **`seo_title`** to the user. **`webo/get-post`** only returns the WordPress **`title`** — it does **not** replace Rank Math’s custom SEO title. **Do not** tell the user to open wp-admin → Rank Math metabox for a value you can read via MCP unless **`rankmath/get-meta`** is missing (then: activate **mcp-rank-math**, **`tools/list`**, or fix permissions).
- **Discover IDs first:** use `webo/list-posts` / `webo/get-post` / `webo/get-content-by-slug` so **`rankmath/get-meta`** / **`update-meta`** get the correct **`id`**.
- **Surgical updates:** for `rankmath/update-meta`, send **only** keys being changed (no “fill every field” payloads).
- **Destructibles:** `clear-404-logs` and bulk `delete-*` need explicit user intent; require **`confirm`** where the tool specifies it.
- **Redirections:** validate **`header_code`** (301, 302, 307, 308, 410, 451) and **`comparison`** (`exact`, `contains`, `start`, `end`, `regex`) before calling `create-redirection`.
- **No Abilities stack:** this skill is for the **WEBO + mcp-rank-math** path only, not `wp_register_ability` / MCP Expose Abilities.

## Examples

Get SEO meta for post **37** (SEO title is **`seo_title`** in the response; **`title`** is the regular post headline):

```json
{
  "session_id": "<…>",
  "name": "rankmath/get-meta",
  "arguments": { "id": 37 }
}
```

Update title + description + focus keyword (aliases):

```json
{
  "session_id": "<…>",
  "name": "rankmath/update-meta",
  "arguments": {
    "id": 123,
    "title": "Custom SEO title",
    "description": "Meta description under 160 chars where possible.",
    "keyword": "focus phrase"
  }
}
```

Create a 301 redirect:

```json
{
  "session_id": "<…>",
  "name": "rankmath/create-redirection",
  "arguments": {
    "sources": [ { "pattern": "old-slug", "comparison": "exact" } ],
    "destination": "https://example.com/new-url/",
    "header_code": 301,
    "status": "active"
  }
}
```

## Reference

- Add-on code & install: [`mcp-rank-math/README.md`](../../mcp-rank-math/README.md) (in webo-mcp repo).
