---
name: webo-mcp-ability-rank-math
description: >-
  Documents the optional WEBO MCP Rank Math addon and its webo-rank-math/* abilities
  for unified query and mutation operations on post SEO meta, term SEO meta, user SEO meta,
  plugin status, options, modules, and redirections. Use when an MCP client connected through
  webo-mcp needs Rank Math SEO automation via tools/call with action-based dispatchers
  covering rank_math_title, rank_math_description, focus keywords, robots, canonical URLs,
  schema fields, module and option management, and redirection CRUD.
---

# WEBO MCP - Rank Math addon

## Instructions

1. **Prerequisites:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md). These abilities exist only when the [WEBO MCP Rank Math addon](https://github.com/mrphuong-webo/webo-mcp-rank-math) is installed and active alongside Rank Math SEO.
2. **Tool namespace:** addon abilities are exposed through the Abilities bridge as **`webo-rank-math/*`**, using unified query and mutate pattern. All abilities accept an `action` parameter to route to specific operations.
3. **Unified abilities and permissions**

| Ability | Permission | Actions | Notes |
|---------|------------|---------|-------|
| `webo-rank-math/config-query` | `manage_options` | `plugin-status`, `get-options`, `get-modules` | Read Rank Math configuration; returns active status, available options, and module list |
| `webo-rank-math/config-mutate` | `manage_options` | `update-options`, `update-modules` | Modify Rank Math configuration; update option values or swap active module list |
| `webo-rank-math/post-seo-query` | `edit_posts` | `get`, `audit` | Read post SEO metadata or audit schema fields; `get` accepts `post_id`/`slug` + optional `keys` |
| `webo-rank-math/post-seo-mutate` | `edit_posts` | `update`, `bulk-upsert`, `cleanup` | Write post SEO metadata, bulk update multiple posts, or remove schema fields |
| `webo-rank-math/term-seo-query` | `manage_categories` | `get` | Read term SEO metadata by `term_id`; optional `keys` narrows the result |
| `webo-rank-math/term-seo-mutate` | `manage_categories` | `update` | Update term SEO metadata; `seo_meta` object with `null` to delete a key |
| `webo-rank-math/user-seo-query` | `edit_users` | `get` | Read user SEO metadata by `user_id`; optional `keys` narrows the result |
| `webo-rank-math/user-seo-mutate` | `edit_users` | `update` | Update user SEO metadata; `seo_meta` object |
| `webo-rank-math/redirect-query` | `manage_options` | `list`, `get` | List or retrieve redirections; `list` supports `page` and `per_page` for pagination |
| `webo-rank-math/redirect-mutate` | `manage_options` | `create`, `update`, `delete` | Create, update, or delete redirections by ID |

4. **Common fields:** default helpers already cover common Rank Math keys such as `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`, `rank_math_canonical_url`, `rank_math_robots`, social image/title/description keys, schema keys, pillar content, and primary category. Custom `rank_math_*` keys are also allowed in `seo_meta`.
5. **Schema details that matter:**

| Ability | Action | Important input details |
|---------|--------|-------------------------|
| config-query | plugin-status | Optional `site_id`; returns `rank_math_active`, `rank_math_version`, `rank_math_modules`, `options_available` |
| config-query | get-options | Optional `option_names` array; omit to use addon default allowlist; returns `count` and `options` object |
| config-query | get-modules | Optional `site_id`; returns `count` and sorted `modules` array |
| config-mutate | update-options | Required `options` object; only keys starting with `rank_math` are accepted |
| config-mutate | update-modules | Required `modules` array (may be empty); replaces the active module list |
| post-seo-query | get | Uses `oneOf`: either `post_id` or `slug`; `post_type` defaults to `post`; optional `keys` array limits returned fields |
| post-seo-query | audit | Audits schema fields; returns audit results for all or specified posts |
| post-seo-mutate | update | Either `post_id` or `slug` + `post_type`; `seo_meta` object (use `null` to delete a key) |
| post-seo-mutate | bulk-upsert | `posts` array where each item has `seo_meta` plus either `post_id` or `slug`; optional `skip_missing`; returns `count` and `results[]` |
| post-seo-mutate | cleanup | Either `post_id` for single post or `delete_all` for all posts; removes schema-related metadata |
| term-seo-query | get | Required `term_id`; optional `keys` array; response includes `taxonomy` |
| term-seo-mutate | update | Required `term_id` and `seo_meta` object |
| user-seo-query | get | Required `user_id`; optional `keys` array; response includes `login` |
| user-seo-mutate | update | Required `user_id` and `seo_meta` object |
| redirect-query | list | Optional `page` (default 1) and `per_page` (default 50); returns `redirections[]`, pagination info, and `total` count |
| redirect-query | get | Required `id`; returns single redirection or error if not found |
| redirect-mutate | create | Required `source_url` and `target_url`; optional `header_code` (default 301); returns new redirection with inserted `id` |
| redirect-mutate | update | Required `id`; optional `source_url`, `target_url`, `header_code`; omitted fields preserve existing values |
| redirect-mutate | delete | Required `id`; returns `deleted` boolean and affected redirection object |

6. **Lookup rules:** for posts, prefer `post_id` when known. If only slug is available, send `slug` and set `post_type` explicitly for pages or CPTs. For terms, use `term_id` and rely on the response taxonomy. For redirections, list first with optional `search` to find existing rules before creating new ones.
7. **Safe workflow:** 
   - Read current state first with appropriate `-query` ability
   - Send minimal payload to `-mutate` ability
   - For bulk changes, use `bulk-upsert` only after validating the target list
   - For schema cleanup, verify the audit results before calling `cleanup`
8. **Multisite:** when the site runs multisite, pass `site_id` so the addon switches blogs before reading or writing Rank Math data.


## Examples

Get post SEO metadata:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/post-seo-query",
  "arguments": {
    "action": "get",
    "post_id": 42
  }
}
```

Get only selected post SEO meta keys:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/post-seo-query",
  "arguments": {
    "action": "get",
    "slug": "pricing",
    "post_type": "page",
    "keys": [
      "rank_math_title",
      "rank_math_description",
      "rank_math_canonical_url"
    ]
  }
}
```

Update post title, description, and focus keyword:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/post-seo-mutate",
  "arguments": {
    "action": "update",
    "post_id": 42,
    "seo_meta": {
      "rank_math_title": "Custom SEO title",
      "rank_math_description": "Custom SEO description",
      "rank_math_focus_keyword": "webo mcp rank math"
    }
  }
}
```

Clear a canonical URL by sending `null`:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/post-seo-mutate",
  "arguments": {
    "action": "update",
    "post_id": 42,
    "seo_meta": {
      "rank_math_canonical_url": null
    }
  }
}
```

Read Rank Math option groups:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/config-query",
  "arguments": {
    "action": "get-options",
    "option_names": [
      "rank_math_options_titles",
      "rank_math_options_social"
    ]
  }
}
```

Replace active modules:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/config-mutate",
  "arguments": {
    "action": "update-modules",
    "modules": ["redirections", "sitemap", "schema"]
  }
}
```

Bulk update multiple posts:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/post-seo-mutate",
  "arguments": {
    "action": "bulk-upsert",
    "posts": [
      {
        "post_id": 42,
        "seo_meta": {
          "rank_math_focus_keyword": "managed wordpress hosting"
        }
      },
      {
        "slug": "about",
        "post_type": "page",
        "seo_meta": {
          "rank_math_title": "About WEBO"
        }
      }
    ]
  }
}
```

List redirections:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/redirect-query",
  "arguments": {
    "action": "list",
    "page": 1,
    "per_page": 50
  }
}
```

Create a redirection:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/redirect-mutate",
  "arguments": {
    "action": "create",
    "source_url": "/old-page",
    "target_url": "/new-page",
    "header_code": 301
  }
}
```