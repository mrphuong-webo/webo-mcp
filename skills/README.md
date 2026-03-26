# Public agent skills (WEBO MCP)

Skills in this folder are **versioned in git** and safe to share (unlike `.cursor/*`, which this repo ignores by default).

## `webo-mcp-wordpress-content`

Teaches coding agents how to manage WordPress **posts, pages, media, taxonomies, menus, and comments** via the MCP router and `webo/*` tools. Inspired by the workflow in [wordpress-content (jezweb/claude-skills)](https://skills.sh/jezweb/claude-skills/wordpress-content).

- **Skill file:** [webo-mcp-wordpress-content/SKILL.md](webo-mcp-wordpress-content/SKILL.md)
- **Raw (GitHub):**  
  `https://raw.githubusercontent.com/mrphuong-webo/webo-mcp/main/skills/webo-mcp-wordpress-content/SKILL.md`  
  (replace branch name if you use another default branch.)

### Cursor

1. Clone or download this repository.
2. Copy the folder into your skills directory, keeping the `SKILL.md` name:
   - **Project-only:** `<your-clone>/skills/webo-mcp-wordpress-content` → `<project>/.cursor/skills/webo-mcp-wordpress-content`
   - **All projects:** `~/.cursor/skills/webo-mcp-wordpress-content` (macOS/Linux) or `%USERPROFILE%\.cursor\skills\webo-mcp-wordpress-content` (Windows)
3. Restart Cursor so the skill is picked up.

### OpenAI Codex (install from GitHub)

If you use the Codex skill installer helper:

```bash
python /path/to/codex/skills/.system/skill-installer/scripts/install-skill-from-github.py \
  --repo mrphuong-webo/webo-mcp \
  --path skills/webo-mcp-wordpress-content
```

Adjust the script path to your Codex install. Then restart Codex.

### Other CLIs

Any tool that can pull a directory from GitHub (sparse checkout, tarball, or `npx skills add` with a `--path` to this folder) should point at:

`skills/webo-mcp-wordpress-content`
