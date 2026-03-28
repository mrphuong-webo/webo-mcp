# Public agent skills (WEBO MCP)

Skills trong thư mục này được **version trong git** và có thể chia sẻ an toàn (khác `.cursor/*` thường bị ignore).

## Cấu trúc

| Skill | Mục đích |
|-------|----------|
| **[webo-mcp-guide](webo-mcp-guide/SKILL.md)** | Hướng dẫn **chung**: MCP flow, auth, Abilities bridge, chọn skill con. **Đọc trước.** |
| [webo-mcp-ability-posts](webo-mcp-ability-posts/SKILL.md) | Bài viết, trang, CPT, revision, search-replace, homepage, … |
| [webo-mcp-ability-media](webo-mcp-ability-media/SKILL.md) | Media library, upload URL |
| [webo-mcp-ability-taxonomy](webo-mcp-ability-taxonomy/SKILL.md) | Taxonomy, term, gán term |
| [webo-mcp-ability-comments](webo-mcp-ability-comments/SKILL.md) | Comment |
| [webo-mcp-ability-menus](webo-mcp-ability-menus/SKILL.md) | Menu điều hướng (bảng tool) |
| [webo-mcp-menu-creation](webo-mcp-menu-creation/SKILL.md) | **Tạo / gán menu** — nhiều luồng, discover `theme_location`, ví dụ `tools/call` |
| [webo-mcp-rank-math](webo-mcp-rank-math/SKILL.md) | **Rank Math SEO** qua addon [webo-mcp-rank-math](https://github.com/mrphuong-webo/webo-mcp-rank-math) (**phải kích hoạt**): `webo-rank-math/*` (meta, options, modules, redirections) |
| [webo-mcp-seo-agentic](webo-mcp-seo-agentic/SKILL.md) | **Mục lục Agentic SEO** — bản adapt các `seo-*` từ [Agentic-SEO-Skill](https://github.com/Bhanunamikaze/Agentic-SEO-Skill/tree/main/resources/skills); mỗi chủ đề một skill `webo-mcp-seo-*` bên dưới |
| [webo-mcp-seo-plan](webo-mcp-seo-plan/SKILL.md) | **Kế hoạch / chiến lược SEO** (discovery → roadmap) gắn với `webo/*` và tùy chọn `webo-rank-math/*` |

**Agentic SEO theo chủ đề:** [webo-mcp-seo-aeo](webo-mcp-seo-aeo/SKILL.md), [webo-mcp-seo-article](webo-mcp-seo-article/SKILL.md), [webo-mcp-seo-audit](webo-mcp-seo-audit/SKILL.md), [webo-mcp-seo-competitor-pages](webo-mcp-seo-competitor-pages/SKILL.md), [webo-mcp-seo-content](webo-mcp-seo-content/SKILL.md), [webo-mcp-seo-geo](webo-mcp-seo-geo/SKILL.md), [webo-mcp-seo-github](webo-mcp-seo-github/SKILL.md), [webo-mcp-seo-hreflang](webo-mcp-seo-hreflang/SKILL.md), [webo-mcp-seo-images](webo-mcp-seo-images/SKILL.md), [webo-mcp-seo-links](webo-mcp-seo-links/SKILL.md), [webo-mcp-seo-page](webo-mcp-seo-page/SKILL.md), [webo-mcp-seo-programmatic](webo-mcp-seo-programmatic/SKILL.md), [webo-mcp-seo-schema](webo-mcp-seo-schema/SKILL.md), [webo-mcp-seo-sitemap](webo-mcp-seo-sitemap/SKILL.md), [webo-mcp-seo-technical](webo-mcp-seo-technical/SKILL.md).
| [webo-mcp-ability-users](webo-mcp-ability-users/SKILL.md) | List user |
| [webo-mcp-ability-site](webo-mcp-ability-site/SKILL.md) | Plugin, options an toàn |
| [webo-mcp-wordpress-content](webo-mcp-wordpress-content/SKILL.md) | **Một file** đầy đủ bảng tool + schema (tiếng Anh, tham chiếu nhanh) |
| [webo-write-post-instruction](webo-write-post-instruction/SKILL.md) | Workflow viết bài SEO + `webo/create-post` (tiếng Việt) |

Layout tương thích [skills CLI](https://github.com/vercel-labs/skills) (`skills/*/SKILL.md`).

## Chuẩn authoring (Cursor / Agent Skills)

Mỗi `SKILL.md` gồm **YAML frontmatter** bắt buộc: `name` (chữ thường, gạch ngang, ≤64 ký tự), `description` (≤1024 ký tự, **ngôi thứ ba**, đủ **WHAT** + **WHEN**, có từ khóa trigger như `webo-mcp`, `tools/call`). Phần thân dùng **`## Instructions`** (bước, quy tắc, bảng tool) và **`## Examples`** (JSON mẫu). Chi tiết: [Creating Skills in Cursor](https://cursor.com/docs/context/skills) và skill `create-skill` trong editor.

## Cài bằng `npx skills`

Liệt kê skill trong repo:

```bash
npx skills add https://github.com/mrphuong-webo/webo-mcp --list
```

Cài **một** skill (ví dụ guide chung) vào Cursor global:

```bash
npx skills add https://github.com/mrphuong-webo/webo-mcp --skill webo-mcp-guide -a cursor -g -y
```

Đổi `--skill` thành `webo-mcp-ability-posts`, `webo-mcp-wordpress-content`, v.v.

## Cursor (thủ công)

Sao chép thư mục skill (giữ `SKILL.md`) vào:

- Project: `<project>/.cursor/skills/<tên-skill>`
- Global: `~/.cursor/skills/<tên-skill>` hoặc `%USERPROFILE%\.cursor\skills\<tên-skill>`

## OpenAI Codex (từ GitHub)

```bash
python /path/to/codex/skills/.system/skill-installer/scripts/install-skill-from-github.py \
  --repo mrphuong-webo/webo-mcp \
  --path skills/webo-mcp-guide
```

## Raw GitHub

Thay branch nếu cần:

`https://raw.githubusercontent.com/mrphuong-webo/webo-mcp/main/skills/webo-mcp-guide/SKILL.md`
