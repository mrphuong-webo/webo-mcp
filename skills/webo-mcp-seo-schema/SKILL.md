---
name: webo-mcp-seo-schema
description: >-
  Schema.org / JSON-LD detection, validation rules, and generation guidance adapted
  for WordPress via WEBO MCP (Rank Math + hand-authored snippets). Use when the user
  says schema, structured data, JSON-LD, or rich results.
---

# WEBO MCP — Schema (Agentic adapt)

Upstream: [seo-schema.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-schema.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Detection

- Prefer **JSON-LD** in initial HTML; watch for Microdata/RDFa.
- Validate: `@context`, `@type`, absolute URLs, dates, no placeholder text in production.

## Type policy (summary)

- **Recommend:** Organization, LocalBusiness, Article/BlogPosting, Product/Offer, BreadcrumbList, WebSite, FAQ only where policy allows, etc.
- **Restricted:** e.g. FAQ rich results limited to certain site categories (per Google policy — verify current docs).
- **Deprecated types:** avoid HowTo rich result assumptions, SpecialAnnouncement, etc. — follow upstream `schema-types.md` + Google changelog.

## Generation

- Truthful data only; mark TBD clearly for user fill-in.

## WEBO MCP — execution

[`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md) — meta schema types, Article/Product toggles, Local SEO module.

Custom JSON-LD: usually via theme/child theme or Rank Math custom schema — MCP edits post content; bespoke snippets may require deploying theme/plugin outside the MCP session.

**`parse_html.py`**, **`validate_schema.py`** — upstream.
