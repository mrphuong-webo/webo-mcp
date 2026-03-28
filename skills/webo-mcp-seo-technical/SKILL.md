---
name: webo-mcp-seo-technical
description: >-
  Technical SEO (crawlability, indexability, security, URLs, mobile, CWV, schema,
  JS rendering, AI crawlers, IndexNow, voice) adapted for WordPress context via WEBO MCP.
---

# WEBO MCP — Technical SEO (Agentic adapt)

Upstream: [seo-technical.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-technical.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Categories (condensed)

1. **Crawlability** — robots.txt, sitemap in robots, intentional noindex, crawl depth, JS reliance, crawl budget (large sites).
2. **AI crawlers** — optional `GPTBot`, `Google-Extended`, etc. — strategy-aware (blocking training vs search ≠ same).
3. **Indexability** — canonical, duplicates, thin, pagination, hreflang, bloat.
4. **Security** — HTTPS, HSTS, CSP, XFO, XCTO, Referrer-Policy.
5. **URL structure** — clean paths, 301 not chains, trailing slash consistency.
6. **Mobile** — responsive, touch targets, 16px base; mobile-first indexing complete per Google timeline.
7. **CWV** — LCP, **INP** (not FID), CLS — field data 75th percentile when available.
8. **Structured data** — see [`webo-mcp-seo-schema`](../webo-mcp-seo-schema/SKILL.md).
9. **JS rendering** — canonical/meta/robots/schema in **initial HTML** when possible (Google JS SEO guidance).
10. **IndexNow** — Bing/Yandex/Naver fast ping patterns.

## Voice (optional section)

- Featured-snippet-style answers; TTFB; `speakable`; local schema for local intent — cross-link [`webo-mcp-seo-aeo`](../webo-mcp-seo-aeo/SKILL.md).

## WEBO MCP — execution

WordPress: many items are **hosting / theme / cache plugin / Cloudflare** — MCP cannot toggle HSTS. Per-post: canonical/noindex via Rank Math (`webo-rank-math/*`) when available.

**`robots_checker.py`**, **`security_headers.py`**, **`redirect_checker.py`**, **`pagespeed.py`**, **`hreflang_checker.py`**, **`indexnow_checker.py`** — upstream.
