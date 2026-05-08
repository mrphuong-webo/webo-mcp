# MCP Tool Migration (Unified Dispatchers)

This document maps legacy MCP tools to the new reduced tool surface across WEBO MCP addons.

## Why

- Reduce `tools/list` output size
- Lower token usage for AI clients
- Keep behavior compatible via `action` dispatching

## Migration Rules

- Call the new dispatcher tool.
- Pass `action` to select the old behavior.
- Keep existing arguments, minus any old tool name split.

## Tool Visibility (core bridge)

Registered WordPress abilities are bridged into the MCP `ToolRegistry`. Each ability’s `meta.mcp.public` flag controls whether `tools/list` treats it as a **public** tool (discoverable by default) or **internal** (omit from discovery unless clients pass `include_internal: true` or enable the `webo_mcp_allow_internal_tools` filter in private environments — see repo `README.md`). Addon authors SHOULD set `public: true` only on dispatcher surfaces to avoid duplicates.

## Tool Mapping

### `webo-mcp-domain`

- `webo-domain/providers-query`
  - `action: list` -> `webo-domain/list-providers`
  - `action: data-source-status` -> `webo-domain/data-source-status`
- `webo-domain/domains-query`
  - `action: lookup` -> `webo-domain/lookup`
  - `action: list` -> `webo-domain/list-domains`
  - `action: get` -> `webo-domain/get-domain`
  - `action: whois` -> `webo-domain/whois`
  - `action: get-nameservers` -> `webo-domain/get-nameservers`
  - `action: renewal-report` -> `webo-domain/renewal-report`
- `webo-domain/dns-query`
  - `action: list-zones` -> `webo-domain/list-dns-zones`
  - `action: get-zone` -> `webo-domain/get-dns-zone`
  - `action: list-records` -> `webo-domain/list-dns-records`
- `webo-domain/dns-mutate`
  - `action: create-record` -> `webo-domain/create-dns-record`
  - `action: update-record` -> `webo-domain/update-dns-record`
  - `action: delete-record` -> `webo-domain/delete-dns-record`

### `webo-mcp-backup`

- `webo-mcp-backup/dispatch`
  - `action: plan-migration` -> `webo-mcp-backup/plan-migration`
  - `action: export-payload` -> `webo-mcp-backup/export-payload`
  - `action: import-payload` -> `webo-mcp-backup/import-payload`

### `webo-mcp-edd`

- `webo/edd-downloads`
  - `action: create|get|update|delete|list`
- `webo/edd-discounts`
  - `action: create|get|update|delete|list`
- `webo/edd-orders`
  - `action: get|update|list`
- `webo/edd-customers`
  - `action: create|get|update|list`
- `webo/edd-store`
  - `action: get-settings|update-settings|list-payment-gateways`

### `webo-mcp-woocommerce`

Five **resource groups**, each exposing **query** + **mutate** tool names (`webo/woo-query-*` / `webo/woo-mutate-*`). Each call passes an **`action`** that maps to legacy operations (product/order/customer/coupon/store); see the addon registration for the exact discriminant strings. **`tools/list` on your site is authoritative.**

- Products: `webo/woo-query-products`, `webo/woo-mutate-products`
- Orders: `webo/woo-query-orders`, `webo/woo-mutate-orders`
- Customers: `webo/woo-query-customers`, `webo/woo-mutate-customers`
- Coupons: `webo/woo-query-coupons`, `webo/woo-mutate-coupons`
- Store: `webo/woo-query-store`, `webo/woo-mutate-store`

Some deployments or docs may abbreviate naming (for example five “unified tools” describing two calls per domain); behavior is unchanged.

### `webo-mcp-elementor`

- `webo-elementor/query`
  - `action: list-templates|get-document|find-page-by-url|get-page-settings|get-template-conditions`
- `webo-elementor/mutate`
  - `action: update-document-data|patch-document-data|update-page-settings|update-template-conditions|upsert-page-document`
- `webo-elementor/template`
  - `action: create|duplicate`
- `webo-elementor/cache`
  - `action: clear`

### `webo-mcp-featured-post`

- `webo-featured/dispatch`
  - `action: create|get|update|delete|list`

### `webo-mcp-ultimo`

- `webo-ultimo/customers-query`
- `webo-ultimo/sites-mutate`
- `webo-ultimo/domains-mutate`
- `webo-ultimo/infra-ops`
- `webo-ultimo/products-mutate`
- `webo-ultimo/plugin-theme-mutate`
- `webo-ultimo/checkout-mutate`

Each tool uses `action` to route to legacy behavior.

### `webo-mcp-wpml`

- `webo-wpml/query`
  - `action: ping|get-addon-status|list-languages|get-language-switchers`
- `webo-wpml/mutate`
  - `action: create-post-translation-stubs|set-active-languages|set-menu-language-switcher|update-translation-slugs`

### `webo-mcp-flatsome`

- `webo-flatsome/query`
  - `action: ping|get-options|get-option|export-settings`
- `webo-flatsome/mutate`
  - `action: update-options|update-option|import-settings|apply-boxed-layout|configure-sidebar-widgets|set-sitewide-sidebar|set-language-menus`

### `webo-mcp-betterdocs`

- `webo/docs`
  - `action: create|get|update|delete|list`

### `webo-mcp-metabox`

- `metabox/dispatch`
  - `action: list-field-groups|get-field-group|list-field-types|get-field-value|set-field-value|get-all-field-values|batch-update-fields`

### `webo-mcp-rank-math`

`tools/list` shows **only** the ten `*-query` / `*-mutate` abilities. Granular names (for example `list-redirections`) may remain registered for REST but use `meta.mcp.public = false`, so they are MCP-internal unless `include_internal` is used.

- `webo-rank-math/config-query` — `action: plugin-status|get-options|get-modules`
- `webo-rank-math/config-mutate` — `action: update-options|update-modules`
- `webo-rank-math/post-seo-query` — `action: get|audit`
- `webo-rank-math/post-seo-mutate` — `action: update|bulk-upsert|cleanup`
- `webo-rank-math/term-seo-query` — `action: get`
- `webo-rank-math/term-seo-mutate` — `action: update`
- `webo-rank-math/user-seo-query` — `action: get`
- `webo-rank-math/user-seo-mutate` — `action: update`
- `webo-rank-math/redirect-query` — `action: list|get` (replaces `list-redirections`, `get-redirection` at the MCP surface)
- `webo-rank-math/redirect-mutate` — `action: create|update|delete` (replaces create/update/delete-redirection MCP tools)

### `webo-mcp-rocket`

Discovery exposes **two** tools only:

- `webo-rocket/cache-query`
  - `action: status` → former `webo-rocket/status`
  - `action: get-cache-settings` → former `webo-rocket/get-cache-settings`
  - `action: get-optimization-status` → former `webo-rocket/get-optimization-status`
- `webo-rocket/cache-mutate`
  - `action: update-settings` → former `webo-rocket/update-settings`
  - `action: clear` → former `webo-rocket/clear-cache`
  - `action: clear-post` → former `webo-rocket/clear-post-cache`
  - `action: clear-url` → former `webo-rocket/clear-url-cache`
  - `action: clear-used-css` → former `webo-rocket/clear-used-css`
  - `action: preload` → former `webo-rocket/preload-cache`

## Client Compatibility Advice

- Prefer dispatcher tools for all new automation.
- Keep a temporary fallback map for legacy tool names during migration.
- Validate `action` early and return a clear error for invalid actions.
