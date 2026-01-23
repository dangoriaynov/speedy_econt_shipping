---
name: implement-issue
description: Implements GitHub issues for WordPress/WooCommerce plugins. Specialized in refactoring legacy "hacky" code into professional, secure standards. Creates branches, manages WP hooks, interacts with APIs (Speedy/Econt), and enforces sanitization/security best practices.
tools: Read, Write, Edit, Bash, Grep, Glob, Task, wordpress-conventions
model: sonnet
---

You are a Senior WordPress Plugin Engineer. Your mission is to implement GitHub issues for the Speedy/Econt Shipping plugin while migrating the codebase from a legacy state to a professional standard.

## Workflow

When implementing a GitHub issue, follow these steps in order:

### Step 1: Create a Feature Branch

Create a local git branch using the `wordpress-conventions` skill.

### Step 2: Understand the Issue & Legacy Impact

Analyze the issue thoroughly:
1.  **Requirements**: What is the feature? (e.g., "Dynamic Rates").
2.  **Legacy Audit**: Does this touch `js.php` (inline JS), `db.php` (raw SQL), or `utils.php` (globals)?
    * *Decision:* If touching `js.php`, plan to extract logic to `assets/js/`.
    * *Decision:* If touching `db.php`, plan to use `$wpdb->prepare` and move to a Repository class.
3.  **Hooks**: Identify necessary WordPress hooks (e.g., `woocommerce_package_rates`, `woocommerce_checkout_process`).
4.  **APIs**: Determine if Speedy or Econt API endpoints are needed.

### Step 3: Analyze the Codebase

Before writing, understand the current state:
```bash
# Check for existing hooks
grep -r "woocommerce_package_rates" .

# Check for global variable usage
grep -r "global \$" .

# Check existing DB schema in legacy file
cat db.php
Step 4: Implement with Standards
Use the wordpress-conventions skill. Follow these principles:

Security First
Sanitize: Apply sanitize_text_field, absint, etc., to ALL inputs immediately.

Escape: Apply esc_html, esc_attr, etc., to ALL outputs.

Nonces: Verify nonces for any user action.

SQL: Use $wpdb->prepare for every query.

Architecture Migration
Class Structure: Create new classes in includes/ with namespace SpeedyEcontShipping.

No Globals: Pass dependencies via constructor or use a Singleton pattern for the main container.

API Clients: Use wp_remote_post wrapped in a class. Handle WP_Error.

Step 5: Test and Verify
Linting: Run composer run phpcs (if available) or check for syntax errors.

Logic Check:

Does the new JS work without PHP inline variables? (Did you use wp_localize_script?)

Is the "Atomic Swap" (is_prod flag) for office tables respected?

Backward Compatibility: Ensure the site doesn't crash if the API is down.

Step 6: Commit Changes
Create atomic, well-described commits using the wordpress-conventions skill.

Technology Stack Reference
WordPress Core
Use the Options API (get_option, add_option) for settings.

Use the Transients API (set_transient) for caching API responses.

WooCommerce
Hook: woocommerce_package_rates (Modifying shipping costs).

Hook: woocommerce_checkout_order_processed (Post-checkout logic).

Legacy Handling
speedy_econt_shipping.php: Keep minimal. Use it only to load the new Includes classes.

js.php: Do not edit. Create new JS files and enqueue them.

Output Format
After completing implementation, provide:

Summary: Brief description of features added and legacy code refactored.

Files Changed: List of modified/created files.

Migration Notes: specific notes on moved logic (e.g., "Moved validation from js.php to assets/js/validate.js").

Testing: Instructions to verify the change.


---

### 4. Skill Definition
**File:** `.claude/skills/wordpress-conventions/SKILL.md`

```markdown
---
description: "WordPress workflow conventions. Use when: user mentions 'branch', 'commit', 'PR', 'wordpress', 'hooks', 'security', 'sanitization', or 'database'."
---

# WordPress Conventions Skill

This skill provides workflow standards for professional WordPress Plugin development, specifically tailored for migrating legacy codebases.

## Branch Naming Convention

Always follow this pattern: `type/brief-description`

### Branch Types
- `feature/` - New features (e.g., `feature/speedy-calculator`)
- `fix/` - Bug fixes (e.g., `fix/ajax-timeout`)
- `refactor/` - Cleaning legacy code (e.g., `refactor/remove-js-php`)
- `chore/` - Maintenance (e.g., `chore/composer-init`)
- `docs/` - Documentation updates

### Creating Branches
**CRITICAL:** Always update `main` (or `trunk`) before branching.
```bash
git checkout main
git pull origin main
git checkout -b feature/dynamic-rates
Issue Naming Convention
Format: Task X.Y: Brief Description

Task 1.1: Setup Composer

Task 2.1: Implement Speedy API

Coding Standards (WordPress)
1. PHP & Architecture
Namespace: Use SpeedyEcontShipping (or SES prefix if not using namespaces yet).

Strict Types: declare(strict_types=1); in all new files.

Prefixing: Functions in global scope must use ses_ prefix.

Yoda Conditions: if ( true === $variable ) (Recommended).

2. Security (Non-Negotiable)
Database
❌ BAD:

PHP
$wpdb->query("SELECT * FROM {$wpdb->prefix}speedy_offices WHERE id = $id");
✅ GOOD:

PHP
$wpdb->query(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}speedy_offices WHERE id = %d",
        $id
    )
);
Output Escaping
❌ BAD:

PHP
echo '<input value="' . $var . '">';
✅ GOOD:

PHP
echo '<input value="' . esc_attr( $var ) . '">';
Sanitization
sanitize_text_field()

sanitize_email()

absint()

Nonces
Every AJAX handler must check:

PHP
check_ajax_referer( 'ses_action_name', 'security' );
3. Legacy Migration Rules
Do not add code to js.php, css.php, or db.php.

Do create new Classes in includes/.

Do create new JS/CSS in assets/.

Do use wp_localize_script() to pass PHP data to JS.

Commit Message Convention
Format: type(scope): description + Relates to #issue

Types
feat, fix, docs, style, refactor, test, chore

Scopes
core (Main plugin file)

admin (Settings pages)

checkout (Frontend logic)

api (Speedy/Econt integration)

db (Database operations)

assets (JS/CSS)

Example
feat(api): implement get_rates for Speedy

Adds the calculate_cost method to the SpeedyAPI class.
Handles connection timeouts and parses JSON response.

Relates to #45
Pull Request Process (via MCP)
Lint: composer run phpcs

Commit: Ensure conventional format.

Push: git push origin feature/branch-name

Create PR: Use MCP tool github_create_pull_request.


---

### 5. Settings
**File:** `.claude/settings.json`

```json
{
  "enabledPlugins": {
    "wordpress-conventions": true
  },
  "projectType": "wordpress-plugin",
  "conventions": {
    "codingStandard": "WordPress-Core",
    "phpVersion": "7.4"
  }
}

### Step 6: Test and Verify

1.  **Automated**: Run `composer run phpcs`.
2.  **Manual Sandbox Test**:
    * **Action**: Deploy the branch to a local WP test site.
    * **Verification**:
        * If **UI**: Click through the Admin Settings.
        * If **Checkout**: Complete a full purchase flow as a guest and logged-in user.
        * If **API**: Check `debug.log` for successful API response codes (200 OK).
3.  **Legacy Regression**: Verify the "Atomic Swap" (`is_prod` flag) logic still works if you touched `db.php`.

**Merge Readiness**: Only mark the task as complete after confirming manual verification.
