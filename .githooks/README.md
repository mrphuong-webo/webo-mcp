# Git hooks (webo-mcp)

## Tu dong push sau moi commit

Mot lan trong repo:

```bash
git config core.hooksPath .githooks
```

Sau do, moi lan `git commit` se chay `post-commit` va `git push <remote> <branch>` len **tat ca** remote (`origin`, `github`, …).

Bo qua hook (commit nhung khong push): `git commit --no-verify`.

Tat hook: `git config --unset core.hooksPath` (hoac dat hooksPath trong trong).
