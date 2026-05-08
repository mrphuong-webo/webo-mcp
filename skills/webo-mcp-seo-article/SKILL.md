---
name: webo-mcp-seo-article
description: >-
  WEBO MCP SEO article analysis for WordPress: `tools/call` `seo/article-analysis`
  (post_id, Rank Math merge), then `webo/update-post` and
  `webo-rank-math/update-post-seo-meta`. Use when the user wants to audit or improve
  a WordPress post or page for SEO (not off-site URL/HTML-only workflows).
---

# WEBO MCP — SEO article (Agentic adapt)

Upstream: [seo-article.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-article.md).

## Phases (condensed)

1. **Analyze** — call **`seo/article-analysis`** with **`post_id`** first; use `result.issues`, `seo_score`, `content_gaps`, `rank_math`, headings, readability.
2. **Scoring** — `seo_score` is checklist-based from rule severities only, not a Google ranking score.
3. **Recommendations** — map `issues` (`severity`, `area`, `fix`) to edits; prioritize Critical, then Warning, then Info.
4. **Apply** — persist body with **`webo/update-post`**; SEO title/description/focus keyword with **`webo-rank-math/update-post-seo-meta`** when the addon is active.

## Instructions

- **WordPress-only**: arguments must include **`post_id`** (integer ≥ 1). No URL or raw HTML input.
- **Tool-first**: call **`seo/article-analysis`** before stating scores, gaps, or rewrites; use only `result` when `ok` is true. If `ok` is false, surface `error` and stop.
- **Optional arguments**: `keyword` (overrides `target_keyword`), `no_autocomplete`, `include_rank_math` (default true).
- **Rank Math**: result includes `rank_math.seo_meta` and `source` (`ability` | `addon_helper` | `post_meta` | `none`). The tool mirrors **`webo-rank-math/get-post-seo-meta`** when that ability exists; SERP title/description are injected into a synthetic `<head>` for checks.
- **Do not invent** rankings, penalties, or metrics not present in tool output.

## Examples

Here is an illustrative MCP **`tools/call`** body after `initialize`; your client may wrap this in JSON-RPC.

```json
{
  "method": "tools/call",
  "params": {
    "session_id": "<from initialize>",
    "name": "seo/article-analysis",
    "arguments": {
      "post_id": 123,
      "include_rank_math": true,
      "no_autocomplete": false
    }
  },
  "id": 1
}
```

Successful tool output shape (abbreviated): `{"ok": true, "result": { "issues": [...], "seo_score": {...}, "rank_math": {...}, ... } }`. On failure: `{"ok": false, "error": "..."}`.

## WEBO MCP — execution

[`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md): resolve **`post_id`** with **`webo/content-query`** (`action: get`) or **`webo/content-query`** (`action: find-by-url`) when needed, then update via **`webo/content-mutate`** (`action: update`). Media: **`webo/media-mutate`** (`action: upload`), **`webo/content-mutate`** (`action: set-featured-image`). Rank Math support is optional via addon tools **`webo-rank-math/*`** when installed.

**Implementation**: `inc/tools/class-seo-article-analysis.php` — MCP tool name **`seo/article-analysis`** (registered in `webo-mcp.php`, category `seo`, capability `edit_posts`).
