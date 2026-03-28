---
name: webo-mcp-seo-hreflang
description: >-
  Hreflang and international SEO validation adapted for WordPress via WEBO MCP.
  Use when the user has multilingual/multiregional sites or hreflang issues.
---

# WEBO MCP — Hreflang / i18n (Agentic adapt)

Upstream: [seo-hreflang.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-hreflang.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Rules (condensed)

- Bidirectional tags: every locale page lists all alternates + itself.
- **`x-default`** for global fallback (often `/en/` or lang picker).
- **Self-referencing** hreflang on each URL.
- ISO 639-1 language; optional ISO 3166-1 region (`en-gb`).
- Absolute HTTPS URLs only; no redirects/chains in hreflang targets.
- Align with **canonical** (no cross-region canonical to one URL unless true duplicate).
- HTML `<link rel="alternate" hreflang="...">`, HTTP header, or XML sitemap — be consistent.

## WEBO MCP — thực thi

WordPress: thường **WPML / Polylang / Multisite** — hreflang thường do plugin/theme phát sinh; MCP chỉnh **`webo/update-post`** cho nội dung từng ngôn ngữ nếu URL đã đúng.

Kiểm tra HTTP/HTML: script upstream **`hreflang_checker.py`** hoặc crawl ngoài.

Kết hợp audit: [`webo-mcp-seo-technical`](../webo-mcp-seo-technical/SKILL.md), [`webo-mcp-seo-sitemap`](../webo-mcp-seo-sitemap/SKILL.md).
