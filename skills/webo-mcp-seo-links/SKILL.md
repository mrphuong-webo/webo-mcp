---
name: webo-mcp-seo-links
description: >-
  Internal linking strategy and broken-link framing adapted for WordPress via WEBO MCP.
  Use when the user asks about internal links, orphan pages, or broken links.
---

# WEBO MCP — Links (Agentic adapt)

Upstream: [seo-links.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-links.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Internal (condensed)

- Hub & spoke: pillar → clusters with keyword-rich anchors (natural).
- Orphan detection: pages 0 internal inlinks — link from hub/related posts.
- Depth: important content within ~3 clicks from home (context-dependent).

## Broken / redirects

- Fix 404s; update internal links after URL changes; avoid chains.

## External / backlinks

- MCP does not cover backlinks; use GSC/Ahrefs/outside lists — upstream skill `backlink_analysis.py`.

## WEBO MCP — execution

[`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md): **`webo/list-posts`**, **`webo/get-post`**, **`webo/update-post`** to add internal links in the HTML body.

Site-wide menus: see the menu skills in wordpress-content.

**`link_analyzer.py`**, **`broken_links.py`** — upstream.
