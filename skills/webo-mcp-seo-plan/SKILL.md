---
name: webo-mcp-seo-plan
description: >-
  Strategic SEO planning (discovery, competitive framing, IA, content strategy, topical
  clusters, technical SEO, phased roadmap) with implementation hooks for WEBO MCP on WordPress.
  Use when the user asks for an SEO plan, SEO strategy, content strategy, site architecture,
  SEO roadmap, topical authority, hub-and-spoke content, or editorial calendar and connects via
  webo-mcp, MCP router, or tools/call with webo/* and optional rankmath/* (mcp-rank-math add-on).
  Inspired by the seo-plan pattern; execution uses WordPress tools, not external repo scripts.
---

# WEBO MCP — strategic SEO planning

Strategic SEO planning workflow aligned with [Agentic-SEO-Skill **seo-plan**](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-plan.md): discovery → competitive lens → architecture → content → technical foundation → phased roadmap. **Difference:** deliverables are produced in conversation (or user-chosen files); **site changes** go through **[`webo-mcp-guide`](../webo-mcp-guide/SKILL.md)** (`initialize`, `tools/list`, `tools/call`). There are **no** bundled `resources/templates/` or Python scripts in this repo—use MCP for WordPress, plus normal research (SERP, spreadsheets, competitor URLs) where stated.

## Prerequisites

1. **[`webo-mcp-guide`](../webo-mcp-guide/SKILL.md)** — session, auth, capability errors.
2. **Tool reference:** [`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md) (`webo/*`); Rank Math: [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md) (`rankmath/*` when **mcp-rank-math** is active).
3. **Draft-by-default** for new URLs unless the user insists on publish; see guide safety block.

## Process

### 1. Discovery

Capture business type, audience, competitors (names/URLs), goals, budget/timeline, KPIs. **On-site via MCP (when connected):**

| Goal | Suggested `tools/call` |
|------|------------------------|
| Homepage / reading context | `webo/get-homepage-info` |
| Content model | `webo/discover-content-types`, `webo/discover-taxonomies` |
| Published inventory | `webo/list-posts` with explicit **`status`** / **`post_type`** (defaults are `publish` + `post`—use **`draft`** for drafts; see guide). |
| Single post SEO snapshot | `rankmath/get-meta` with **`id`** (requires add-on) |
| Meta audit at scale | `rankmath/bulk-get-meta` (`post_type`, `per_page`, `page`, optional `missing_desc`, `search`) |
| Technical / crawl hints | `rankmath/get-sitemap-status`, `rankmath/list-404-logs` (if listed), `rankmath/get-rewrite-status` for sitemap / llms endpoints |

Summarize gaps: thin pages, missing descriptions, orphan topics, 404 patterns, sitemap coverage.

### 2. Competitive analysis (off-site + inventory)

Identify top competitors; compare content depth, schema narrative, technical signals, E-E-A-T. **MCP does not scrape competitors**—use public SERP/pages or tools the user provides. **Use MCP** to export **your** inventory (`webo/list-posts`, `rankmath/bulk-get-meta`) so the plan compares “what we have” vs “what competitors cover.”

### 3. Architecture design

Plan URL hierarchy, pillars, hub pages, CPT/taxonomy usage. **Validate against the live site:**

- **`webo/discover-content-types`** / **`webo/discover-taxonomies`** — avoid planning CPTs/taxonomies that are not registered (or document needed theme/plugin work outside MCP).
- **Navigation:** menu tools in [`webo-mcp-ability-menus`](../webo-mcp-ability-menus/SKILL.md) and workflows in [`webo-mcp-menu-creation`](../webo-mcp-menu-creation/SKILL.md)—plan which **theme_location** maps to hub menus.
- **Internal linking rules** live in editorial guidelines; implementation is content + menus MCP can create/update (`webo/create-post`, `webo/update-post`, menu item tools).

### 4. Content strategy

Define page types, topic backlog, cadence, E-E-A-T actions (authors, bios, citations). **Execution on WordPress:**

- New pages/posts: **`webo/create-post`** — default **`status`: `draft`**; set **`post_type`** for pages vs posts.
- SEO fields after draft exists: **`rankmath/update-meta`** (`title` / `description` / `keyword` aliases per Rank Math skill).
- Taxonomy: term CRUD + **`webo/assign-terms-to-content`** (see content skill taxonomy rows).
- Media: **`webo/upload-media-from-url`**, **`webo/set-post-featured-image`** (public http(s) URLs only).
- Long-form drafting workflow: [`webo-write-post-instruction`](../webo-write-post-instruction/SKILL.md).

### 4.5 Topical authority (hub-and-spoke)

Same model as classic **seo-plan**: one **pillar** (broad head term) and **cluster** articles (long-tail), with bidirectional internal links. **Planning:** 3–5 pillars; 8–15 cluster ideas per pillar from research (People Also Ask, competitor headings, keyword tools—not MCP). **Implementation checklist:**

- Pillar URL: create as **`page` or `post`** per IA; set **`rankmath/update-meta`** for pillar **`keyword`** / description.
- Clusters: drafts; each must **link** to pillar (body HTML) and 2–3 siblings where relevant.
- **`rankmath/update-meta`**: `is_pillar` / `is_cornerstone` only when the tool and user intent allow.
- Track coverage in a table (topic, URL slug, status, publish target date)—agent outputs this as markdown for the user.

### 5. Technical foundation

Hosting, CWV, and theme-level work may be **outside** MCP. **Inside MCP** when **rankmath/** tools exist:

| Area | Tools (if listed) |
|------|-------------------|
| Global / modules | `rankmath/list-modules`, `rankmath/update-modules` (**`manage_options`**) |
| Schema / publisher | `rankmath/get-schema-status`, `rankmath/update-publisher-profile` |
| Sitemap / AI crawlers | `rankmath/get-sitemap-status`, `rankmath/get-llms-status`, `rankmath/preview-llms` |
| Redirects / 404 hygiene | `rankmath/list-redirections`, `rankmath/create-redirection`, `rankmath/list-404-logs`, clear/delete when user confirms |

Plugins and safe options: [`webo-mcp-ability-site`](../webo-mcp-ability-site/SKILL.md) where applicable.

### 6. Implementation roadmap (four phases)

Use phased weeks/months from **seo-plan** as a template; each phase lists **MCP-verifiable** outcomes:

| Phase | Focus | Example MCP checkpoints |
|-------|--------|-------------------------|
| 1 — Foundation | Core pages, tracking (manual), essential schema story | Homepage info, key **`webo/get-post`** / **`rankmath/get-meta`** on core IDs |
| 2 — Expansion | Draft/publish primary URLs, blog boot, menus | **`webo/create-post`**, **`rankmath/bulk-get-meta`** (`missing_desc`), menu assign |
| 3 — Scale | Cluster rollout, internal links, local/geo if needed | Bulk listing + surgical **`rankmath/update-meta`** |
| 4 — Authority | Thought leadership, PR (off-site), advanced schema | Module/sitemap/llms diagnostics |

## Agent rules

- Run **`tools/list`** early; only promise **`rankmath/*`** if present.
- Prefer **`rankmath/get-meta`** / **`bulk-get-meta`** for SEO fields—not **`webo/get-post`** alone.
- Destructive Rank Math ops (clear 404, bulk delete redirects) require explicit user confirmation per [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md).
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
| **Technical SEO** | Rank Math / sitemap / redirects / 404 actions routed to tools |
| **Roadmap** | Phased tasks with MCP vs manual tags |

## Example: inventory + meta audit

```json
{
  "method": "tools/call",
  "params": {
    "session_id": "<from initialize>",
    "name": "rankmath/bulk-get-meta",
    "arguments": {
      "post_type": "post",
      "per_page": 50,
      "page": 1,
      "missing_desc": true
    }
  }
}
```

```json
{
  "method": "tools/call",
  "params": {
    "session_id": "<from initialize>",
    "name": "webo/list-posts",
    "arguments": { "status": "draft", "per_page": 50 }
  }
}
```

## Reference

- Source pattern: [Bhanunamikaze/Agentic-SEO-Skill — `seo-plan.md`](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/blob/main/resources/skills/seo-plan.md) (conceptual); this file wires it to **WEBO MCP**.
