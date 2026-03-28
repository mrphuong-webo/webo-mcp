---
name: webo-mcp-seo-links
description: >-
  Internal linking strategy and broken-link framing adapted for WordPress via WEBO MCP.
  Use when the user asks about internal links, orphan pages, or broken links.
---

# WEBO MCP — Links (Agentic adapt)

Upstream: [seo-links.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-links.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Internal (condensed)

- Hub & spoke: pillar → clusters với keyword-rich anchor (natural).
- Orphan detection: pages 0 internal inlinks — link from hub/related posts.
- Depth: important content within ~3 clicks from home (context-dependent).

## Broken / redirects

- Fix 404s; update internal links after URL changes; avoid chains.

## External / backlinks

- MCP không phục vụ backlinks; dùng GSC/Ahrefs/outsourced lists — skill upstream `backlink_analysis.py`.

## WEBO MCP — thực thi

[`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md): **`webo/list-posts`**, **`webo/get-post`**, **`webo/update-post`** để chèn internal links trong HTML body.

Menu site-wide: skill menus trong wordpress-content.

**`link_analyzer.py`**, **`broken_links.py`** — upstream.
