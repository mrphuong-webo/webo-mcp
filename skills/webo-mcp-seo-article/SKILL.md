---
name: webo-mcp-seo-article
description: >-
  SEO article/blog analysis and optimization (title, meta, headings, intro, ALT,
  links, thin sections) for WordPress via WEBO MCP. Use when the user pastes an
  article URL or asks to improve a blog post for SEO.
---

# WEBO MCP — SEO article (Agentic adapt)

Upstream: [seo-article.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-article.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Phases (condensed)

1. **Fetch & analyze** — title/H1 alignment, meta length, heading hierarchy, keyword placement, intro hook, paragraph length, media (ALT, captions), internal links, thin sections, duplicate H2s.
2. **Scoring** — overall / on-page / content quality / technical (per upstream bands).
3. **Recommendations** — priority + before/after where applicable.
4. **Rewrite mode** — optional polished blocks while preserving facts/links.

## WEBO MCP — execution

[`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md): **`webo/find-content-by-url`** or **`webo/get-post`** → **`webo/update-post`** (title, content, excerpt). Media: **`webo/upload-media-from-url`**, **`webo/set-post-featured-image`**. Rank Math title/description: [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md).

**`article_seo.py`** is upstream only — not bundled in webo-mcp; use the LLM + MCP workflow or clone the Agentic repo.
