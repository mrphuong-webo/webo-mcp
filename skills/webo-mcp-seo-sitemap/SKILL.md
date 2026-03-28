---
name: webo-mcp-seo-sitemap
description: >-
  XML sitemap analysis and generation patterns adapted for WordPress via WEBO MCP.
  Use when the user says sitemap, XML sitemap, or sitemap validation.
---

# WEBO MCP — Sitemap (Agentic adapt)

Upstream: [seo-sitemap.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-sitemap.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Analyze (condensed)

- Valid XML; **<50k URLs** per file; index file when over limit.
- URLs return **200**; no mass identical `lastmod` if avoidable.
- Exclude **noindex**, non-canonicals, redirect targets wrong, HTTP-only URLs.
- Referenced in **robots.txt**; compare sitemap vs crawl.

## Generate (non-WP)

- Industry templates + `STRUCTURE.md` per upstream; split + index.

## WEBO MCP — WordPress

WordPress: **Yoast / Rank Math / core** usually serve `/wp-sitemap.xml` — check Rank Math sitemap settings via [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md) when those tools exist; otherwise use wp-admin / hosting.

MCP does not replace the core WP sitemap generator unless the user self-hosts static XML — then use upstream deliverables (XML files).

**`robots_checker.py`**, **`broken_links.py`** — upstream.
