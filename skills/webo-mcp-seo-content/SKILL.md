---
name: webo-mcp-seo-content
description: >-
  On-page content quality (E-E-A-T, readability, thin content, duplicate risk,
  AI citation) adapted for WordPress via WEBO MCP. Use when the user asks about
  content quality, E-E-A-T, thin pages, or AI visibility of content.
---

# WEBO MCP — SEO content quality (Agentic adapt)

Upstream: [seo-content.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-content.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## E-E-A-T signals (condensed)

- Author byline + bio; `Person` schema where honest; `dateModified` when substantively updated.
- Citations for YMYL; primary sources; stats with dates.
- Transparency: contact, address (local), refund/support pages as applicable.

## Readability & depth

- Target grade 8–10; sentences <25 words avg; paragraphs 2–4 sentences; descriptive H2/H3.

## Thin & duplicate

- Flag word-count bands per page type (product, category, blog, local) per upstream; merge/noindex/canonical strategy.

## AI citation optimization

- Clear entity strings; FAQ-style H2; distinct statistics; quotable definitions in first ~100 words of sections.

## WEBO MCP — execution

[`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md): **`webo/list-posts`**, **`webo/get-post`**, **`webo/update-post`** for body/excerpt/author fields if exposed. Media & menus per skill.

**`content_analyzer.py`** — upstream only.
