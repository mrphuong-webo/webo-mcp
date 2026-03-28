---
name: webo-mcp-rank-math
description: >-
  Rank Math SEO over WEBO MCP using the optional addon plugin webo-mcp-rank-math: WordPress
  Abilities named webo-rank-math/* (post/term/user SEO meta, options, modules, plugin status,
  Rank Math redirections) are bridged to the same tool names in tools/list. Requires WEBO MCP,
  Rank Math SEO (seo-by-rank-math), and the addon installed and activated from
  https://github.com/mrphuong-webo/webo-mcp-rank-math. Use when the user mentions Rank Math,
  SEO title, meta description, focus keyword, webo-rank-math/get-post-seo-meta, bulk SEO meta,
  redirections, or MCP automation that depends on this addon.
---

# WEBO MCP — Rank Math (`webo-rank-math/*`)

## Instructions

1. **Addon (required):** Install the plugin from GitHub **[WEBO MCP – Rank Math addon](https://github.com/mrphuong-webo/webo-mcp-rank-math)** and **activate** it on WordPress. Without the addon there are **no** `webo-rank-math/*` tools in `tools/list`.
2. **Core dependencies:** **[WEBO MCP](https://github.com/mrphuong-webo/webo-mcp)** (router, Abilities bridge) and **[Rank Math SEO](https://rankmath.com/)** (`seo-by-rank-math`) must both be **active**. Recommended order: Rank Math → WEBO MCP → Rank Math addon.
3. **Entry flow:** Read **[`webo-mcp-guide`](../webo-mcp-guide/SKILL.md)** — `initialize` → keep **`session_id`** → **`tools/list`** to confirm exact names and per-ability **JSON schema** (arguments may differ across addon versions).
4. **Mechanism:** The addon registers **WordPress Abilities API** (`wp_register_ability`) abilities with prefix **`webo-rank-math/`**. WEBO MCP **auto-bridges** them to MCP tools with the same names (filter `webo_mcp_auto_bridge_abilities` on by default).

### Tool map (quick reference)

| `name` | Notes |
|--------|-------|
| `webo-rank-math/get-plugin-status` | Rank Math status, version, modules. |
| `webo-rank-math/get-post-seo-meta` | **`post_id`** or **`slug`** + **`post_type`**; response includes **`seo_meta`** (`rank_math_*` keys). Custom SEO title: **`rank_math_title`**. |
| `webo-rank-math/update-post-seo-meta` | **`seo_meta`**: object key → value; `null` removes a key. |
| `webo-rank-math/bulk-upsert-post-seo-meta` | Many posts; **`items`** (max 200). |
| `webo-rank-math/get-term-seo-meta` / `webo-rank-math/update-term-seo-meta` | Taxonomy SEO meta. |
| `webo-rank-math/get-options` / `webo-rank-math/update-options` | Option groups (`rank_math_*`). |
| `webo-rank-math/get-modules` / `webo-rank-math/update-modules` | Enable/disable modules. |
| `webo-rank-math/get-user-seo-meta` / `webo-rank-math/update-user-seo-meta` | Author archive SEO. |
| `webo-rank-math/list-redirections` | **`limit`**, **`paged`**, **`status`**, **`search`**. |
| `webo-rank-math/get-redirection` | By **`id`**. |
| `webo-rank-math/create-redirection` | **`source`**, **`destination`**, **`type`** (301, 302, …), **`comparison`**, **`ignore_case`**, **`status`**. |
| `webo-rank-math/update-redirection` / `webo-rank-math/delete-redirection` | Update / delete. |

Details and capabilities: [addon README on GitHub](https://github.com/mrphuong-webo/webo-mcp-rank-math/blob/main/README.md).

### Rules for agents

- **Per-post SEO title:** prefer **`webo-rank-math/get-post-seo-meta`** with **`post_id`** (or slug) and read **`seo_meta.rank_math_title`**. **`webo/get-post`** is only the WordPress post title, not Rank Math meta.
- **Do not** send users to wp-admin if **`tools/list`** already exposes these tools and the session has the right caps.
- **Addon URL:** always use **`https://github.com/mrphuong-webo/webo-mcp-rank-math`** when explaining install/activate.
- Meta updates: send only keys that change in **`seo_meta`**; **`create-redirection`** needs Rank Math’s Redirections module.

## Examples

Read SEO meta for post **ID 37**:

```json
{
  "session_id": "<from initialize>",
  "name": "webo-rank-math/get-post-seo-meta",
  "arguments": { "post_id": 37 }
}
```

Update SEO title + description:

```json
{
  "session_id": "<from initialize>",
  "name": "webo-rank-math/update-post-seo-meta",
  "arguments": {
    "post_id": 37,
    "seo_meta": {
      "rank_math_title": "Custom SEO title",
      "rank_math_description": "Meta description."
    }
  }
}
```

Create a 301 redirect:

```json
{
  "session_id": "<from initialize>",
  "name": "webo-rank-math/create-redirection",
  "arguments": {
    "source": "old-slug",
    "destination": "https://example.com/new-url/",
    "type": "301",
    "comparison": "exact",
    "status": "active"
  }
}
```

## Reference

- Addon (source + docs): [github.com/mrphuong-webo/webo-mcp-rank-math](https://github.com/mrphuong-webo/webo-mcp-rank-math)
