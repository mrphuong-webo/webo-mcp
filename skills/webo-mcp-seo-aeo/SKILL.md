---
name: webo-mcp-seo-aeo
description: >-
  Answer Engine Optimization (featured snippets, PAA, Knowledge Panel, sitelinks
  searchbox, video) adapted for WordPress via WEBO MCP. Use when the user says
  AEO, PAA, featured snippet, People Also Ask, Knowledge Panel, or sitelinks searchbox.
---

# WEBO MCP — SEO AEO (Agentic adapt)

Upstream: [seo-aeo.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-aeo.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Checklist (condensed)

- **Featured snippet:** H2 as question; first 40–55 words = direct answer; ≤300 chars for definitions; tables/lists for comparisons; one clear H1.
- **PAA:** 4–8 H2/H3 as natural questions; short answers first; cluster related questions.
- **Knowledge Panel:** SameAs (social/Wikidata); Organization with logo; consistent NAP Person/Organization.
- **Video:** VideoObject schema; chapters; transcript; thumbnail; target key moments.
- **Sitelinks searchbox:** WebSite + SearchAction on homepage only; URL must match live search.
- **Tables:** first column = entity names; header row; scope; caption; responsive.
- **Lists:** logical order; max ~8 H2 sections with bullets.

## WEBO MCP — execution

[`webo-mcp-guide`](../webo-mcp-guide/SKILL.md), [`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md): **`webo/get-post`**, **`webo/find-content-by-url`**, **`webo/update-post`**, **`webo/create-post`** for headings/body. Images/video: **`webo/upload-media-from-url`**, **`webo/set-post-featured-image`**.

Rank Math / schema when the addon is active: [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md).

**Agentic scripts** (`featured_snippet_optimizer.py`, …) live only in the upstream repo — run them outside MCP when needed.
