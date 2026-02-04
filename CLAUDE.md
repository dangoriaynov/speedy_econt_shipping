# CLAUDE.md - Project Guidelines

## Workflow

**Work directly on `main` branch.** This is a single-developer project - no feature branches needed.

```bash
# Before starting work, sync with remote
git pull origin main

# After completing work, commit and push
git add -A
git commit -m "feat(scope): description"
git push origin main
```

## Custom Workflows

### Subagents

#### implement-issue
Located in `.claude/agents/implement-issue.md`

Use this subagent when implementing GitHub issues or fixing problems:
1. Pulls latest `main` from remote
2. Analyzes the issue requirements
3. Implements features following WordPress/WooCommerce standards
4. Runs verification checks (PHP syntax, security review)
5. Reports summary of changes

**Note:** Does NOT create commits or push. User handles version control after reviewing.

**Invocation:**
```
Use the implement-issue subagent to implement GitHub issue #42
```

### Slash Commands

#### /implement
Located in `.claude/commands/implement.md`

Shortcut to invoke the implement-issue subagent with an issue number, URL, or problem description.

**Usage:**
```
/implement 42
/implement https://github.com/dangoriaynov/speedy_econt_shipping/issues/42
/implement fix the admin notice pointing to wrong settings URL
```

### Skills

#### wordpress-conventions
Located in `.claude/skills/wordpress-conventions/SKILL.md`

Coding conventions for WordPress/PHP projects.

**Commit Format:** Conventional commits: `feat(scope): description`

**Commit Scopes:** `core`, `admin`, `checkout`, `api`, `speedy`, `econt`, `db`, `assets`

**Critical Rules:**
- ✅ **Security:** All DB queries must use `$wpdb->prepare`.
- ✅ **Security:** All inputs must be sanitized; all outputs escaped.
- ✅ **Security:** All AJAX handlers must verify nonces.

## Commands
- **Lint PHP:** `composer run phpcs` (if configured)
- **Fix PHP:** `composer run phpcbf`
- **Lint JS:** `npm run lint` (if configured)

## Tech Stack
- **Platform:** WordPress 6.0+, WooCommerce 8.0+
- **Language:** PHP 7.4+, JavaScript (Vanilla/jQuery)
- **APIs:** Speedy REST API, Econt XML/REST API
- **Database:** Custom Tables + WP Options API

## Architecture

### Directory Structure
```
/includes           PHP Classes (OOP architecture)
  /api              API Client classes (Speedy, Econt)
  /admin            Admin functionality
  /database         Database layer
  /labels           Label generation
  /shipping-methods WC_Shipping_Method implementations
  /frontend         Customer-facing features
/assets
  /js               Frontend and Admin scripts
  /css              Stylesheets
/templates          Frontend template parts
/languages          Translation files (.pot, .po)
```

## Security Guidelines (Highest Priority)
- **Input Validation:** Validate all `$_POST` and `$_GET` data immediately.
- **SQL Injection Prevention:**
  - **NEVER** use direct variable interpolation in SQL strings.
  - **ALWAYS** use `$wpdb->prepare("SELECT * FROM table WHERE id = %d", $id)`.
- **XSS Prevention:**
  - Use `esc_html()`, `esc_attr()`, `esc_url()` on output.
  - Never trust data from the database without escaping on display.
- **Nonce Verification:**
  - Every AJAX action and Form submission must verify a Nonce (`check_ajax_referer`).

## Error Handling
- Use `try/catch` blocks for API requests.
- Log errors using `error_log()`, never echo errors to frontend.
- Fail gracefully: If API is down, checkout should not break (fallback to flat rate).
