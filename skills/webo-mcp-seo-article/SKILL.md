---
name: webo-mcp-seo-article
description: >-
  SEO article analysis and rewrites for WordPress via WEBO MCP: MCP tool
  `seo/article-analysis` (post_id + Rank Math merge), then `webo/update-post` and
  `webo-rank-math/update-post-seo-meta`. Use when the user wants to audit or improve
  a WordPress post or page for SEO (not arbitrary off-site URLs).
---

# WEBO MCP — SEO article (Agentic adapt)

Upstream: [seo-article.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-article.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Phases (condensed)

1. **Analyze** — call **`seo/article-analysis`** with **`post_id`** first; use `result.issues`, `seo_score`, `content_gaps`, `rank_math`, headings, readability.
2. **Scoring** — `seo_score` is checklist-based from rule severities only, not a Google ranking score.
3. **Recommendations** — map `issues` (`severity`, `area`, `fix`) to edits; prioritize Critical, then Warning, then Info.
4. **Apply** — persist body with **`webo/update-post`**; SEO title/description/focus keyword with **`webo-rank-math/update-post-seo-meta`** when the addon is active.

## Instructions — tool `seo/article-analysis`

- **WordPress-only**: arguments must include **`post_id`** (integer ≥ 1). No URL or raw HTML input.
- **Tool-first**: call **`seo/article-analysis`** before stating scores, gaps, or rewrites; use only `result` when `ok` is true. If `ok` is false, surface `error` and stop.
- **Optional arguments**: `keyword` (overrides `target_keyword`), `no_autocomplete`, `include_rank_math` (default true).
- **Rank Math**: result includes `rank_math.seo_meta` and `source` (`ability` | `addon_helper` | `post_meta` | `none`). The tool mirrors **`webo-rank-math/get-post-seo-meta`** when that ability exists; SERP title/description are injected into a synthetic `<head>` for checks.
- **Do not invent** rankings, penalties, or metrics not present in tool output.

## WEBO MCP — execution

[`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md): resolve **`post_id`** with **`webo/get-post`** or **`webo/find-content-by-url`** when needed, then **`webo/update-post`**. Media: **`webo/upload-media-from-url`**, **`webo/set-post-featured-image`**. Rank Math: [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md).

**Implementation**: `inc/tools/class-seo-article-analysis.php` — MCP tool name **`seo/article-analysis`** (registered in `webo-mcp.php`, category `seo`, capability `edit_posts`).
