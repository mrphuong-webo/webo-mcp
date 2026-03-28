# Public agent skills (WEBO MCP)

Skills in this directory are **versioned in git** and safe to share (unlike `.cursor/*`, which is often gitignored).

## Layout

| Skill | Purpose |
|-------|---------|
| **[webo-mcp-guide](webo-mcp-guide/SKILL.md)** | **General** guide: MCP flow, auth, Abilities bridge, picking child skills. **Read first.** |
| [webo-mcp-extensions](webo-mcp-extensions/SKILL.md) | **Core vs companion plugins** — package/tool naming (`webo/*`, `webo-rank-math/*`), extension hooks, skill ↔ plugin map |
| [webo-mcp-wordpress-content](webo-mcp-wordpress-content/SKILL.md) | **Single file** with full `webo/*` table + schema (English, quick reference) |
| [webo-mcp-ability-posts](webo-mcp-ability-posts/SKILL.md) | Posts, pages, CPTs, revisions, search-replace, homepage, … |
| [webo-mcp-ability-media](webo-mcp-ability-media/SKILL.md) | Media library, URL upload |
| [webo-mcp-ability-taxonomy](webo-mcp-ability-taxonomy/SKILL.md) | Taxonomies, terms, assign terms |
| [webo-mcp-ability-comments](webo-mcp-ability-comments/SKILL.md) | Comments |
| [webo-mcp-ability-menus](webo-mcp-ability-menus/SKILL.md) | Navigation menus (tool table) |
| [webo-mcp-menu-creation](webo-mcp-menu-creation/SKILL.md) | **Create / assign menus** — discover `theme_location`, `tools/call` examples |
| [webo-mcp-ability-users](webo-mcp-ability-users/SKILL.md) | List users |
| [webo-mcp-ability-site](webo-mcp-ability-site/SKILL.md) | Plugins (`webo/list-active-plugins`, …), safe options |
| [webo-mcp-rank-math](webo-mcp-rank-math/SKILL.md) | **Rank Math** via [webo-mcp-rank-math](https://github.com/mrphuong-webo/webo-mcp-rank-math) (**enable addon**): `webo-rank-math/*` |
| [webo-mcp-seo-agentic](webo-mcp-seo-agentic/SKILL.md) | **Agentic SEO index** → `webo-mcp-seo-*` skills |
| [webo-mcp-seo-plan](webo-mcp-seo-plan/SKILL.md) | SEO strategy + roadmap (`webo/*`, optional `webo-rank-math/*`) |
| [webo-write-post-instruction](webo-write-post-instruction/SKILL.md) | SEO post workflow + `webo/create-post` |

### Agentic SEO (by topic)

[webo-mcp-seo-aeo](webo-mcp-seo-aeo/SKILL.md), [webo-mcp-seo-article](webo-mcp-seo-article/SKILL.md), [webo-mcp-seo-audit](webo-mcp-seo-audit/SKILL.md), [webo-mcp-seo-competitor-pages](webo-mcp-seo-competitor-pages/SKILL.md), [webo-mcp-seo-content](webo-mcp-seo-content/SKILL.md), [webo-mcp-seo-geo](webo-mcp-seo-geo/SKILL.md), [webo-mcp-seo-github](webo-mcp-seo-github/SKILL.md), [webo-mcp-seo-hreflang](webo-mcp-seo-hreflang/SKILL.md), [webo-mcp-seo-images](webo-mcp-seo-images/SKILL.md), [webo-mcp-seo-links](webo-mcp-seo-links/SKILL.md), [webo-mcp-seo-page](webo-mcp-seo-page/SKILL.md), [webo-mcp-seo-programmatic](webo-mcp-seo-programmatic/SKILL.md), [webo-mcp-seo-schema](webo-mcp-seo-schema/SKILL.md), [webo-mcp-seo-sitemap](webo-mcp-seo-sitemap/SKILL.md), [webo-mcp-seo-technical](webo-mcp-seo-technical/SKILL.md).

Layout matches the [skills CLI](https://github.com/vercel-labs/skills) (`skills/*/SKILL.md`).

## Authoring standard (Cursor / Agent Skills)

Each `SKILL.md` needs **YAML frontmatter**: `name` (lowercase, hyphens, ≤64 chars), `description` (≤1024 chars, **third person**, **WHAT** + **WHEN**, trigger keywords like `webo-mcp`, `tools/call`). Body uses **`## Instructions`** (steps, rules, tool tables) and **`## Examples`** (sample JSON). Details: [Creating Skills in Cursor](https://cursor.com/docs/context/skills) and the editor’s `create-skill` skill.

## Install with `npx skills`

List skills in the repo:

```bash
npx skills add https://github.com/mrphuong-webo/webo-mcp --list
```

Install **one** skill (e.g. the main guide) into Cursor globally:

```bash
npx skills add https://github.com/mrphuong-webo/webo-mcp --skill webo-mcp-guide -a cursor -g -y
```

Change `--skill` to `webo-mcp-ability-posts`, `webo-mcp-wordpress-content`, etc.

## Cursor (manual)

Copy the skill folder (keep `SKILL.md`) to:

- Project: `<project>/.cursor/skills/<skill-name>`
- Global: `~/.cursor/skills/<skill-name>` or `%USERPROFILE%\.cursor\skills\<skill-name>`

## OpenAI Codex (from GitHub)

```bash
python /path/to/codex/skills/.system/skill-installer/scripts/install-skill-from-github.py \
  --repo mrphuong-webo/webo-mcp \
  --path skills/webo-mcp-guide
```

## Raw GitHub

Swap the branch if needed:

`https://raw.githubusercontent.com/mrphuong-webo/webo-mcp/main/skills/webo-mcp-guide/SKILL.md`
