---
name: webo-mcp-seo-competitor-pages
description: >-
  SEO for competitor comparison pages (/vs/, alternatives, comparisons) adapted
  for WordPress via WEBO MCP. Use when the user builds vs pages, alternative lists,
  or head-to-head comparisons.
---

# WEBO MCP — Competitor / comparison pages (Agentic adapt)

Upstream: [seo-competitor-pages.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-competitor-pages.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## URL & content

| Page type | URL pattern | Minimum unique copy |
|-----------|-------------|---------------------|
| Head-to-head | `/vs/[competitor]/` hoặc `/[you]-vs-[them]/` | 800+ words, real feature matrix |
| Alternatives | `/[competitor]-alternatives/` | 12+ tools, 150+ words each |
| Best-of | `/best-[category]-tools/` | 8+ items, methodology |
| Migration | `/migrate-from-[competitor]/` | Step-by-step + honest limits |
| Pricing compare | `/[you]-vs-[them]-pricing/` | Tables + disclaimer |

## Must-haves

- Trademark fair use disclaimer (bottom).
- Last updated + editorial methodology (no fake “unbiased”).
- Structured comparison table; internal links to docs/pricing.
- JSON-LD: WebPage + optional ItemList/Products với truthful data only — xem [`webo-mcp-seo-schema`](../webo-mcp-seo-schema/SKILL.md).

## WEBO MCP — thực thi

[`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md): **`webo/create-post`** / **`webo/update-post`**; slug theo pattern. Rank Math: [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md).

**`comparison_page_builder.py`** — chỉ trong upstream; dùng LLM + bảng Markdown trong WordPress nếu không clone repo.
