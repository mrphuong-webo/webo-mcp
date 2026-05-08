---
name: webo-mcp-rank-math-redirections
description: >-
  Documents Rank Math redirection workflows exposed by the optional WEBO MCP Rank Math
  addon through unified webo-rank-math/redirect-query and redirect-mutate abilities,
  covering list, get, create, update, and delete actions. Use when an MCP client connected
  via webo-mcp needs Rank Math redirection management including source pattern matching,
  redirect status, header codes 301/302/307/410/451, and multisite site_id support.
---

# WEBO MCP - Rank Math redirections

## Instructions

1. **Prerequisites:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md) and the optional [WEBO MCP Rank Math addon](https://github.com/mrphuong-webo/webo-mcp-rank-math). Rank Math's Redirections module must also be enabled; otherwise these abilities are not registered.
2. **Unified abilities and permissions**

| Ability | Action | Permission | Notes |
|---------|--------|------------|-------|
| `webo-rank-math/redirect-query` | `list` | `manage_options` | List redirections with pagination; supports optional `page` and `per_page` |
| `webo-rank-math/redirect-query` | `get` | `manage_options` | Retrieve a single redirection by `id` |
| `webo-rank-math/redirect-mutate` | `create` | `manage_options` | Create a new redirection; requires `source_url` and `target_url` (or empty for 410/451) |
| `webo-rank-math/redirect-mutate` | `update` | `manage_options` | Update existing redirection by `id`; omitted fields preserve current values |
| `webo-rank-math/redirect-mutate` | `delete` | `manage_options` | Delete redirection by `id`; returns deleted redirection object |

3. **Header codes:** allowed redirect types are `301` (moved permanently), `302` (found), `307` (temporary redirect), `410` (gone), and `451` (unavailable for legal reasons). For `410` and `451`, target should be empty.
4. **Schema details that matter:**

| Ability | Action | Important input or output details |
|---------|--------|-----------------------------------|
| redirect-query | list | `page` default 1, `per_page` default 50 (max 500); returns `redirections[]`, pagination info (`page`, `per_page`, `total`) |
| redirect-query | get | Required `id` (integer); returns single redirection object or error if not found |
| redirect-mutate | create | Required `source_url` and `target_url`; optional `header_code` (default 301); response includes new `id` |
| redirect-mutate | update | Required `id`; optional `source_url`, `target_url`, `header_code`; omitted fields preserve existing values |
| redirect-mutate | delete | Required `id` (integer); returns `deleted` boolean; `id` in response is the deleted redirection object's ID |

5. **Safe workflow:** 
   - List existing redirections first to avoid duplicates
   - Get a single rule before update to understand current state
   - When creating 410 or 451 rules, leave `target_url` empty
   - Validate source patterns carefully before create/update
6. **Search and pagination:** use `list` as the discovery entry point. Page through results with `page` and `per_page` parameters.
7. **Multisite:** pass `site_id` when the request targets a subsite.

## Examples

List redirections (first page):

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

Get a single redirection by ID:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/redirect-query",
  "arguments": {
    "action": "get",
    "id": 123
  }
}
```

Create a 301 permanent redirect:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/redirect-mutate",
  "arguments": {
    "action": "create",
    "source_url": "/old-blog/post-title",
    "target_url": "/blog/updated-title",
    "header_code": 301
  }
}
```

Create a 410 (Gone) response:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/redirect-mutate",
  "arguments": {
    "action": "create",
    "source_url": "/discontinued-product",
    "target_url": "",
    "header_code": 410
  }
}
```

Update a redirection's target:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/redirect-mutate",
  "arguments": {
    "action": "update",
    "id": 123,
    "target_url": "/new-target"
  }
}
```

Delete a redirection:

```json
{
  "session_id": "<...>",
  "name": "webo-rank-math/redirect-mutate",
  "arguments": {
    "action": "delete",
    "id": 123
  }
}
```