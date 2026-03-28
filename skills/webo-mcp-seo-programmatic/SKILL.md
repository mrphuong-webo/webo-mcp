---
name: webo-mcp-seo-programmatic
description: >-
  Programmatic SEO (templates, merge fields, quality gates, scale limits) adapted
  for WordPress via WEBO MCP. Use when the user plans many similar pages or
  location/integration directories.
---

# WEBO MCP — Programmatic SEO (Agentic adapt)

Upstream: [seo-programmatic.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-programmatic.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Quality gates (condensed)

- **Safe at scale:** integrations with real docs, tools with downloads, glossary (200+ word defs), products with unique specs, UGC profiles.
- **Penalty risk:** city-swap thin locals, generic “best X for Y”, competitor alts without data, unreviewed AI pages.
- **Thresholds:** warning ~30+ similar location pages (need majority unique copy); hard stop ~50+ without editorial/SEO justification (per upstream).

## Template pattern

- Merge fields: `{city}`, `{use_case}`, `{integration_name}` — min unique % per page.
- Consistent title/H1/Meta formula + distinct intro + localized facts where applicable.

## WEBO MCP — execution

Bulk pages: **`webo/create-post`** / **`webo/update-post`** from a template (automation outside MCP or repeated tool calls).

Sitemap/architecture: [`webo-mcp-seo-sitemap`](../webo-mcp-seo-sitemap/SKILL.md). Overall strategy: [`webo-mcp-seo-plan`](../webo-mcp-seo-plan/SKILL.md).

**`generate_pages.py`**, **`validate_uniqueness.py`** — upstream.
