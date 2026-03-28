---
name: webo-mcp-seo-images
description: >-
  Image SEO (ALT, formats WebP/AVIF, lazy loading, dimensions, LCP, CLS) adapted
  for WordPress via WEBO MCP. Use when the user asks about image optimization for SEO.
---

# WEBO MCP — Image SEO (Agentic adapt)

Upstream: [seo-images.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-images.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Checklist (condensed)

- **ALT:** concise, descriptive; empty only if decorative.
- **Formats:** WebP/AVIF + fallback; `srcset`/`sizes` for responsive.
- **Dimensions:** `width`/`height` on `<img>` to reduce CLS.
- **LCP:** preload/preconnect for hero; not lazy-load above-the-fold hero.
- **Filenames:** readable, hyphenated; avoid generic `IMG_001`.
- **Captions** when helpful for context.

## WEBO MCP — thực thi

[`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md): **`webo/upload-media-from-url`**, **`webo/update-post`/`get-post`** — nếu MCP trả về `featured_media` hoặc HTML gallery; Rank Math **Image SEO** nếu có addon.

Core WP thường quản media trong Media Library — MCP có thể hạn chế trừ khi có tool chỉnh ALT từng attachment-ID (mở rộng router sau).

**`image_analyzer.py`** — upstream.
