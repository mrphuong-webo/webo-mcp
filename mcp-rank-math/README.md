# MCP Rank Math (WEBO)

**Rank Math SEO** automation through **[WEBO MCP](https://github.com/mrphuong-webo/webo-mcp)** — register **23 tools** named `rankmath/*`, matching the public ability list from [`bjornfix/mcp-abilities-rankmath`](https://github.com/bjornfix/mcp-abilities-rankmath) **without** requiring:

- WordPress Abilities API  
- MCP Adapter  
- MCP Expose Abilities  

## Install

1. Install and activate **WEBO MCP** and **Rank Math SEO**.
2. Upload this folder as `mcp-rank-math` to `wp-content/plugins/` and activate **MCP Rank Math (WEBO)**.

## Tools (overview)

| Name | Permission |
|------|------------|
| `rankmath/list-options`, `get-options`, `update-options` | `manage_options` |
| `rankmath/get-schema-status`, `list-modules`, `update-modules` | `manage_options` |
| `rankmath/get-rewrite-status`, `get-llms-status`, `preview-llms`, `refresh-llms-route` | `manage_options` |
| `rankmath/update-publisher-profile`, `get-social-profiles`, `update-social-profiles` | `manage_options` |
| `rankmath/get-sitemap-status` | `manage_options` |
| `rankmath/get-meta`, `update-meta`, `bulk-get-meta` | `edit_posts` |
| `rankmath/list-404-logs`, `delete-404-logs`, `clear-404-logs` | `manage_options` |
| `rankmath/list-redirections`, `create-redirection`, `delete-redirections` | `manage_options` |

Use the same `arguments` shapes as in the [upstream README examples](https://github.com/bjornfix/mcp-abilities-rankmath) (e.g. `rankmath/get-meta` → `id`; `rankmath/update-meta` → `id` plus optional `title` / `description` / `keyword` aliases).

## License

GPL-2.0-or-later
