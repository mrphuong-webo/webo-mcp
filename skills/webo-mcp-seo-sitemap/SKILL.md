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

WordPress: **Yoast / Rank Math / core** thường tạo `/wp-sitemap.xml` — kiểm tra Rank Math sitemap settings qua [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md) nếu có tool; còn lại là wp-admin / hosting.

MCP không thay generator lõi WP trừ khi user tự host static XML — khi đó dùng deliverables upstream (XML files).

**`robots_checker.py`**, **`broken_links.py`** — upstream.
