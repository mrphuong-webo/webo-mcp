---
name: webo-mcp-extensions
description: >-
  WEBO MCP ecosystem: core vs companion plugins, tool naming (webo/*, ability names),
  hooks (webo_mcp_register_tools), and which Cursor skills map to each package. Use when
  the user adds or builds an addon plugin, audits webo/list-active-plugins, or asks how
  Rank Math / Ultimo integrates with webo-mcp.
---

# WEBO MCP — Extensions & companion plugins

## Instructions

### 1. Packages (naming standard)

| Role | Package / repo (typical) | Enables MCP tools | Agent skill |
|------|---------------------------|-------------------|-------------|
| **Core** | [webo-mcp](https://github.com/mrphuong-webo/webo-mcp) | `webo/*` (posts, media, taxonomy, …) | [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md), [`webo-mcp-wordpress-content`](../webo-mcp-wordpress-content/SKILL.md), `webo-mcp-ability-*` |
| **Rank Math bridge** | [webo-mcp-rank-math](https://github.com/mrphuong-webo/webo-mcp-rank-math) + Rank Math SEO | Abilities **`webo-rank-math/*`** bridged as MCP | [`webo-mcp-rank-math`](../webo-mcp-rank-math/SKILL.md) |
| **WP Ultimo / SaaS** | Companion (e.g. webo-mcp-ultimo) | Usually adds tools via `webo_mcp_register_tools` + capability filters | Per-repo; see `examples/webo-mcp-ultimo-example.php` |

**Plugin ZIP / slug convention:** `webo-mcp` (core); companions use the **`webo-mcp-`** prefix + domain (`webo-mcp-rank-math`, `webo-mcp-ultimo`, …).

### 2. **Tool** naming (MCP `tools/call` → `name`)

| Source | `name` pattern | Notes |
|--------|----------------|-------|
| Core | `webo/<action>` | Lowercase, hyphens, one segment after `webo/` (e.g. `webo/list-posts`). |
| Abilities API (bridge) | Exact **registered ability name** | Rank Math addon registers `webo-rank-math/...` — **do not** rename when calling MCP. |
| Custom PHP (`webo_mcp_register_tools`) | `<prefix>/<action>` | Avoid occupying `webo/*`. Use an org prefix (`acme-crm/sync-order`). Sample file in repo: `examples/addon-rankmath-example.php` (example names `rankmath/...` are illustrative only — production uses the **webo-mcp-rank-math** package). |

After **`initialize`**, always run **`tools/list`** on the **target site** for the real tool list (addon inactive → no `webo-rank-math/*`).

### 3. Detecting installed addons

- **`webo/list-active-plugins`** ([`webo-mcp-ability-site`](../webo-mcp-ability-site/SKILL.md)) — requires `activate_plugins`.
- Cross-check the table above: if a companion plugin is missing, tell the user to install/activate it before calling the matching tools.

### 4. Building a companion plugin

1. Depend on **WEBO MCP** (load after core).
2. Register tools: hook **`webo_mcp_register_tools`** → `ToolRegistry::register()` (see `examples/addon-rankmath-example.php`).
3. *Or* register an **Ability** + `mcp.public` meta to use the core bridge (like the Rank Math addon).
4. Scope multisite / tenant access: filter **`webo_mcp_current_user_can_use_mcp`** (see `examples/webo-mcp-ultimo-example.php`).

### 5. Bundled skills

Each companion **should** ship a `skills/webo-mcp-*/SKILL.md` in its own repo or in core (like Rank Math) so agents know `name`, arguments, and prerequisites.

## Examples

Check plugins before calling Rank Math:

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "session_id": "<…>",
    "name": "webo/list-active-plugins",
    "arguments": { "include_inactive": true }
  },
  "id": 1
}
```

Then `tools/list` and look for the `webo-rank-math/` prefix.
