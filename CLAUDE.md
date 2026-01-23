# CLAUDE.md - Project Guidelines

## Custom Workflows

### Subagents

#### implement-issue
Located in `.claude/agents/implement-issue.md`

Use this subagent when implementing GitHub issues. It follows a structured workflow tailored for migrating legacy WordPress code to modern standards:
1. Creates a feature branch following naming conventions
2. Analyzes the issue requirements against the legacy codebase (`js.php`, `db.php`)
3. Fetches documentation (WP Code Reference, WooCommerce, Speedy/Econt)
4. Implements features while refactoring legacy patterns into Object-Oriented code
5. Runs quality checks (PHPCS) and commits with conventional format

**Invocation:**
Use the implement-issue subagent to implement GitHub issue #42


### Slash Commands

#### /implement
Located in `.claude/commands/implement.md`

Shortcut to invoke the implement-issue subagent with an issue number or URL.

**Usage:**
/implement 42 /implement https://github.com/dangoriaynov/speedy_econt_shipping/issues/42


### Skills

#### wordpress-conventions
Located in `.claude/skills/wordpress-conventions/SKILL.md`

GitHub and Coding workflow conventions for WordPress/PHP projects. Automatically activates when working with branches, commits, PRs, or version control operations.

**Key Conventions:**

| Area | Convention |
|------|------------|
| **Branch naming** | `type/brief-description` (e.g., `feature/speedy-api`, `refactor/legacy-js`) |
| **Issue naming** | `Task X.Y: Description` (e.g., `Task 2.1: Implement API Client`) |
| **Commit format** | Conventional commits with issue reference: `feat(scope): description` + `Relates to #123` |
| **PR title** | `[Issue #X.Y] Brief description` |

**Branch Types:** `feature/`, `fix/`, `hotfix/`, `docs/`, `refactor/`, `test/`, `chore/`

**Commit Scopes:** `core`, `admin`, `checkout`, `api`, `speedy`, `econt`, `db`, `assets`, `legacy`

**Critical Rules:**
- ❌ **Legacy Ban:** Do not add new logic to `js.php` or `css.php`. Move to `assets/`.
- ❌ **Legacy Ban:** Do not add new global variables in `utils.php`. Use Class properties.
- ✅ **Security:** All DB queries must use `$wpdb->prepare`.
- ✅ **Security:** All inputs must be sanitized; all outputs escaped.
- ✅ **Migration:** Refactor one piece at a time (Atomic Commits).

## Commands
- **Lint PHP:** `composer run phpcs` (Requires `composer.json` setup)
- **Fix PHP:** `composer run phpcbf`
- **Lint JS:** `npm run lint` (Requires `package.json` setup)
- **Build:** `npm run build` (For compiling SCSS/JS if added)
- **Start Env:** `docker-compose up -d` (If using Local/Docker)

## Tech Stack
- **Platform:** WordPress 6.0+, WooCommerce 8.0+
- **Language:** PHP 7.4+ (Strict Mode), JavaScript (Vanilla/jQuery)
- **Styling:** CSS (Admin styles), ensure Theme compatibility
- **APIs:** Speedy REST API, Econt XML/REST API
- **Database:** Custom Tables (`speedy_offices`, `econt_offices`) + WP Options API

## Architecture & Conventions

### Directory Structure (Target State)
- `/includes`: PHP Classes and core logic (Namespace: `SpeedyEcontShipping`).
    - `/includes/api`: API Client classes.
    - `/includes/abstracts`: Abstract classes for Shipping Providers.
- `/assets`: Static assets.
    - `/assets/js`: Frontend and Admin scripts.
    - `/assets/css`: Stylesheets.
- `/templates`: Frontend template parts (avoiding inline HTML in PHP).
- `/languages`: Translation files (`.pot`, `.po`).

### Legacy vs. Modern Mapping
| Legacy File | Status | Refactoring Goal |
|-------------|--------|------------------|
| `js.php` | 🛑 Deprecated | Extract logic to `assets/js/checkout.js`. Use `wp_localize_script` for data. |
| `db.php` | ⚠️ Refactor | Wrap in `includes/class-db-repository.php`. Remove globals. |
| `api.php` | ⚠️ Refactor | Split into `includes/api/class-speedy-api.php` and `class-econt-api.php`. |
| `utils.php` | ⚠️ Refactor | Move helper functions to `includes/class-utils.php` or specific classes. |

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
- Log errors using `error_log()` or a custom logger class, never echo errors to frontend in production.
- Fail gracefully: If API is down, checkout should not break (fallback to flat rate or hide method).
