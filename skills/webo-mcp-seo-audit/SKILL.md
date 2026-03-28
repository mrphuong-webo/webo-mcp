---
name: webo-mcp-seo-audit
description: >-
  Full-site SEO audit orchestration (technical, content, links, images, page-level)
  adapted for WordPress via WEBO MCP. Use when the user asks for a site SEO audit
  or comprehensive SEO review.
---

# WEBO MCP — SEO audit (Agentic adapt)

Upstream: [seo-audit.md](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-audit.md). Index: [`webo-mcp-seo-agentic`](../webo-mcp-seo-agentic/SKILL.md).

## Flow (condensed)

- **Phase 0:** Scope (URL/CMS/locale), list public URLs (crawl/sitemap/GSC export).
- **Phase 1 — Technical:** [`webo-mcp-seo-technical`](../webo-mcp-seo-technical/SKILL.md) — robots, sitemap, security headers, redirects, mobile, CWV, schema presence, JS/SEO signals.
- **Phase 2 — Content:** [`webo-mcp-seo-content`](../webo-mcp-seo-content/SKILL.md) — sample + programmatic risk.
- **Phase 3 — Links:** [`webo-mcp-seo-links`](../webo-mcp-seo-links/SKILL.md).
- **Phase 4 — Images:** [`webo-mcp-seo-images`](../webo-mcp-seo-images/SKILL.md).
- **Phase 5 — Page sample:** [`webo-mcp-seo-page`](../webo-mcp-seo-page/SKILL.md) on top templates/URLs.
- **Synthesize:** severity (critical/high/medium/low), effort, roadmap — align với [`webo-mcp-seo-plan`](../webo-mcp-seo-plan/SKILL.md).

## WEBO MCP — thực thi

[`webo-mcp-guide`](../webo-mcp-guide/SKILL.md). Danh sách nội dung WordPress: **`webo/list-posts`** (ví dụ **`post_type`: `post`** hoặc **`page`**). Sửa hàng loạt qua từng URL/post ID với **`webo/update-post`**.

Chạy **scripts** upstream (`seo_audit.py`, `crawl_site.py`, …) ngoài plugin nếu cần automation.
