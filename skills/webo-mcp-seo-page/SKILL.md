---
name: webo-mcp-seo-page
description: >-
  Single-page deep SEO audit adapted for WordPress via WEBO MCP. Use when the user
  gives one URL or post and wants a focused on-page review.
---

# WEBO MCP — Single-page SEO (Agentic adapt)

Upstream: [seo-page.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-page.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Layers (condensed)

1. **Crawlability** — status 200, robots, indexable, canonical.
2. **Meta** — title length, description, OG/Twitter if applicable.
3. **Content** — H1 single, hierarchy, keyword use, thin/duplicate hints.
4. **Media** — ALT, lazy, dimensions — [`webo-mcp-seo-images`](../webo-mcp-seo-images/SKILL.md).
5. **Links** — internal opportunities, broken — [`webo-mcp-seo-links`](../webo-mcp-seo-links/SKILL.md).
6. **Schema** — JSON-LD basics — [`webo-mcp-seo-schema`](../webo-mcp-seo-schema/SKILL.md).
7. **Core Web Vitals** — PSI/CrUX — [`webo-mcp-seo-technical`](../webo-mcp-seo-technical/SKILL.md).

## Output shape

Score + table (category / status / notes) + prioritized fixes; align severity with upstream.

## WEBO MCP — execution

[`webo/find-content-by-url`](../webo-mcp-wordpress-content/SKILL.md) → **`webo/get-post`** → **`webo/update-post`**.

Rank Math fields: [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md).

**`page_audit.py`**, **`onpage_analyzer.py`** — upstream.
