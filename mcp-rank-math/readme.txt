=== MCP Rank Math (WEBO) ===
Contributors: webomcp
Tags: mcp, webo-mcp, rank-math, seo, ai
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.9
Stable tag: 1.0.0
License: GPLv2 or later

Register Rank Math SEO operations as WEBO MCP tools (`rankmath/*`), aligned with the ability names described in [mcp-abilities-rankmath](https://github.com/bjornfix/mcp-abilities-rankmath) but **without** the Abilities API or MCP Expose Abilities stack.

== Requirements ==

* WordPress 6.0+
* [WEBO MCP](https://github.com/mrphuong-webo/webo-mcp) active
* [Rank Math SEO](https://rankmath.com/) active

== Tools registered ==

Same 23 `rankmath/*` names as the reference project: list/get/update options, schema status, modules, rewrite & llms.txt status, publisher & social profiles, sitemap status, post SEO meta, bulk meta, 404 logs, redirections.

Responses include a `tool` field for tracing.

== Differences from bjornfix/mcp-abilities-rankmath ==

* Integrates with **WEBO MCP** `ToolRegistry` and `tools/call` (not WordPress Abilities).
* Does **not** ship custom Rank Math filters (e.g. llms.txt branding / organization schema patches) from that repo — only MCP tool parity for automation.

== Changelog ==

= 1.0.0 =
* Initial release.
