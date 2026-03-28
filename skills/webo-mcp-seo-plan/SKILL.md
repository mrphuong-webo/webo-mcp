---
name: webo-mcp-seo-plan
description: >-
  Strategic SEO planning (discovery, competitive framing, IA, content strategy, topical
  clusters, technical SEO, phased roadmap) with implementation hooks for WEBO MCP on WordPress.
  Use when the user asks for an SEO plan, SEO strategy, content strategy, site architecture,
  SEO roadmap, topical authority, hub-and-spoke content, or editorial calendar and connects via
  webo-mcp, MCP router, or tools/call with webo/* and optional webo-rank-math/* when the
  webo-mcp-rank-math addon is installed and activated.
  Inspired by the seo-plan pattern; execution uses WordPress tools, not external repo scripts.
---

# WEBO MCP — strategic SEO planning

Strategic SEO planning workflow aligned with [Agentic-SEO-Skill **seo-plan**](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-plan.md): discovery → competitive lens → architecture → content → technical foundation → phased roadmap. **Difference:** deliverables are produced in conversation (or user-chosen files); **site changes** go through **[`webo-mcp-guide`](../webo-mcp-guide/SKILL.md)** (`initialize`, `tools/list`, `tools/call`). There are **no** bundled `resources/templates/` or Python scripts in this repo—use MCP for WordPress, plus normal research (SERP, spreadsheets, competitor URLs) where stated.

## Prerequisites

1. **[`webo-mcp-guide`](../webo-mcp-guide/SKILL.md)** — session, auth, capability errors.
2. **Tool reference:** [`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md) (`webo/*`); Rank Math: [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md) — requires addon **[webo-mcp-rank-math](https://github.com/mrphuong-webo/webo-mcp-rank-math)** **activated** on the site (`webo-rank-math/*` tools).
3. **Draft-by-default** for new URLs unless the user insists on publish; see guide safety block.

## Process

### 1. Discovery

Capture business type, audience, competitors (names/URLs), goals, budget/timeline, KPIs. **On-site via MCP (when connected):**

| Goal | Suggested `tools/call` |
|------|------------------------|
| Homepage / reading context | `webo/get-homepage-info` |
| Content model | `webo/discover-content-types`, `webo/discover-taxonomies` |
| Published inventory | `webo/list-posts` with explicit **`status`** / **`post_type`** (defaults are `publish` + `post`—use **`draft`** for drafts; see guide). |
| Rank Math / plugin snapshot | `webo-rank-math/get-plugin-status` (if addon active) |
| Single post SEO snapshot | `webo-rank-math/get-post-seo-meta` with **`post_id`** or **`slug`** + **`post_type`** |
| Bulk meta updates | `webo-rank-math/bulk-upsert-post-seo-meta` with **`items`** |
| Global Rank Math settings | `webo-rank-math/get-options` (e.g. `rank_math_options_sitemap`, titles, social) |

Summarize gaps: thin pages, missing descriptions, orphan topics, redirect needs, sitemap-related options.

### 2. Competitive analysis (off-site + inventory)

Identify top competitors; compare content depth, schema narrative, technical signals, E-E-A-T. **MCP does not scrape competitors**—use public SERP/pages or tools the user provides. **Use MCP** to export **your** inventory (`webo/list-posts`, spot checks with **`webo-rank-math/get-post-seo-meta`**) so the plan compares “what we have” vs “what competitors cover.”

### 3. Architecture design

Plan URL hierarchy, pillars, hub pages, CPT/taxonomy usage. **Validate against the live site:**

- **`webo/discover-content-types`** / **`webo/discover-taxonomies`** — avoid planning CPTs/taxonomies that are not registered (or document needed theme/plugin work outside MCP).
- **Navigation:** menu tools in [`webo-mcp-ability-menus`](../webo-mcp-ability-menus/SKILL.md) and workflows in [`webo-mcp-menu-creation`](../webo-mcp-menu-creation/SKILL.md)—plan which **theme_location** maps to hub menus.
- **Internal linking rules** live in editorial guidelines; implementation is content + menus MCP can create/update (`webo/create-post`, `webo/update-post`, menu item tools).

### 4. Content strategy

Define page types, topic backlog, cadence, E-E-A-T actions (authors, bios, citations). **Execution on WordPress:**

- New pages/posts: **`webo/create-post`** — default **`status`: `draft`**; set **`post_type`** for pages vs posts.
- SEO fields after draft exists: **`webo-rank-math/update-post-seo-meta`** with **`seo_meta`** (`rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`, …) per [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md).
- Taxonomy: term CRUD + **`webo/assign-terms-to-content`** (see content skill taxonomy rows).
- Media: **`webo/upload-media-from-url`**, **`webo/set-post-featured-image`** (public http(s) URLs only).
- Long-form drafting workflow: [`webo-write-post-instruction`](../webo-write-post-instruction/SKILL.md).

### 4.5 Topical authority (hub-and-spoke)

Same model as classic **seo-plan**: one **pillar** (broad head term) and **cluster** articles (long-tail), with bidirectional internal links. **Planning:** 3–5 pillars; 8–15 cluster ideas per pillar from research (People Also Ask, competitor headings, keyword tools—not MCP). **Implementation checklist:**

- Pillar URL: create as **`page` or `post`** per IA; set **`webo-rank-math/update-post-seo-meta`** for pillar focus/description (`rank_math_focus_keyword`, `rank_math_description`, …).
- Clusters: drafts; each must **link** to pillar (body HTML) and 2–3 siblings where relevant.
- **`rank_math_pillar_content`** and related keys only when appropriate in **`seo_meta`**.
- Track coverage in a table (topic, URL slug, status, publish target date)—agent outputs this as markdown for the user.

### 5. Technical foundation

Hosting, CWV, and theme-level work may be **outside** MCP. **Inside MCP** when **`webo-rank-math/*`** tools exist (addon active):

| Area | Tools (if listed) |
|------|-------------------|
| Plugin / modules | `webo-rank-math/get-plugin-status`, `webo-rank-math/get-modules`, `webo-rank-math/update-modules` |
| Options (sitemap, titles, social, …) | `webo-rank-math/get-options`, `webo-rank-math/update-options` |
| Redirects | `webo-rank-math/list-redirections`, `webo-rank-math/create-redirection`, update/delete — require user intent for destructive ops |

Plugins and safe options: [`webo-mcp-ability-site`](../webo-mcp-ability-site/SKILL.md) where applicable.

### 6. Implementation roadmap (four phases)

Use phased weeks/months from **seo-plan** as a template; each phase lists **MCP-verifiable** outcomes:

| Phase | Focus | Example MCP checkpoints |
|-------|--------|-------------------------|
| 1 — Foundation | Core pages, tracking (manual), essential schema story | Homepage info, key **`webo/get-post`** / **`webo-rank-math/get-post-seo-meta`** on core IDs |
| 2 — Expansion | Draft/publish primary URLs, blog boot, menus | **`webo/create-post`**, spot SEO audits via **`get-post-seo-meta`**, menu assign |
| 3 — Scale | Cluster rollout, internal links, local/geo if needed | **`bulk-upsert-post-seo-meta`** or surgical **`update-post-seo-meta`** |
| 4 — Authority | Thought leadership, PR (off-site), advanced on-page | Options/modules review, redirection hygiene |

## Agent rules

- Run **`tools/list`** early; only promise **`webo-rank-math/*`** if the [addon](https://github.com/mrphuong-webo/webo-mcp-rank-math) is present and active.
- Prefer **`webo-rank-math/get-post-seo-meta`** for Rank Math fields—not **`webo/get-post`** alone.
- Destructive Rank Math ops (e.g. **`delete-redirection`**) require explicit user confirmation per [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md).
- **`webo/search-replace-posts`:** always **`dry_run: true`** first.

## Output deliverables (for the user)

Produce structured markdown sections (filenames optional):

| Section | Contents |
|---------|----------|
| **SEO strategy** | Goals, audience, positioning |
| **Competitor lens** | Top competitors + gaps vs your MCP inventory |
| **Site structure** | CPT/tax/menu alignment with discover results |
| **Topic clusters** | Pillar/cluster table + internal link rules |
| **Content calendar** | Topics, types, owners, target dates |
| **Technical SEO** | Rank Math options/modules/redirects routed to tools |
| **Roadmap** | Phased tasks with MCP vs manual tags |

## Example: list drafts + read SEO meta

```json
{
  "method": "tools/call",
  "params": {
    "session_id": "<from initialize>",
    "name": "webo/list-posts",
    "arguments": { "status": "draft", "per_page": 20 }
  }
}
```

```json
{
  "method": "tools/call",
  "params": {
    "session_id": "<from initialize>",
    "name": "webo-rank-math/get-post-seo-meta",
    "arguments": { "post_id": 37 }
  }
}
```

## Reference

- Source pattern: [Bhanunamikaze/Agentic-SEO-Skill — `seo-plan.md`](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-plan.md) (conceptual); this file wires it to **WEBO MCP**.
