---
name: webo-mcp-seo-geo
description: >-
  Generative Engine Optimization (AI Overviews, Perplexity, ChatGPT, GEO,
  llms.txt) adapted for WordPress via WEBO MCP. Use when the user says GEO,
  AI search, llms.txt, or visibility in AI Overviews / answer engines.
---

# WEBO MCP — SEO GEO (Agentic adapt)

Upstream: [seo-geo.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-geo.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Framework (condensed)

| Pillar | Tactics |
|--------|---------|
| Citability | Atomic facts, dated stats, FAQs, comparison tables |
| Structured data | Article, FAQWhereAllowed, HowToWhereValid, Product, Organization |
| Authority | Docs, research, citations, expert quotes |
| Freshness | `dateModified`, changelog style sections |
| Technical | Crawlable, fast, HTTPS |
| Brand | Consistent naming; Wikipedia/Wikidata when eligible |

## llms.txt (site root)

- Optional `llms.txt` and `llms-full.txt` — curated paths, pricing, API docs, contact (pattern per upstream).
- WordPress: static file, theme file, or redirect — **not** a core MCP tool; implement in hosting/theme.

## WEBO MCP — thực thi

Nội dung + meta trong WP: [`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md) + [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md). Technical overlap: [`webo-mcp-seo-technical`](../webo-mcp-seo-technical/SKILL.md).

**`geo_optimizer.py`** — upstream only.
