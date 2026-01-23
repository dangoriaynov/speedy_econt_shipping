---
description: "Implement a GitHub issue using the implement-issue subagent. Creates a feature branch, analyzes the legacy context, and implements using WordPress best practices."
argument_hint: "<issue-number-or-url>"
allowed_tools:
  - Read
  - Write
  - Edit
  - Bash
  - Grep
  - Glob
  - Task
  - AskUserQuestion
  - mcp__plugin_github_github__create_pull_request
  - mcp__plugin_github_github__issue_write
  - mcp__plugin_github_github__issue_read
---

## Mission

Implement the GitHub issue: $ARGUMENTS

## Instructions

Use the `implement-issue` subagent to implement this GitHub issue following the complete workflow:

### Phase 1: Setup
1. **Sync Main Branch**: Checkout `main` and pull latest changes from remote (`git checkout main && git pull origin main`).
2. **Create Branch**: Create a feature branch from the updated `main` following naming conventions (`feature/issue-<number>-<brief-desc>`).

### Phase 2: Analysis
3. **Read Issue**: Fetch the full issue details from GitHub to understand requirements.
4. **Analyze Context**: Determine if the issue touches legacy files (`js.php`, `db.php`, `globals`) and plan the refactoring.
5. **Fetch Docs**: Read WordPress Code Reference or WooCommerce docs if needed.

### Phase 3: Implementation
6. **Implement**: Build the feature following WordPress Coding Standards (Strict Sanitization & Nonces).
7. **Migrate**: Ensure legacy patterns are replaced with Object-Oriented patterns where touched.

### Phase 4: Verification (Required Before Submit)
8. **PHP Syntax Check**: Run `php -l` on all modified/created PHP files. Fix any syntax errors.
9. **PHPCS Check**: Run `composer run phpcs` if available. Fix any coding standard violations automatically with `composer run phpcbf` or manually.
10. **JS Lint**: Run `npm run lint` if JS files were modified. Fix any issues.
11. **Self-Review**: Review all changes for:
    - SQL injection vulnerabilities (must use `$wpdb->prepare`)
    - XSS vulnerabilities (must escape output with `esc_html`, `esc_attr`, etc.)
    - Missing nonce verification on AJAX/form handlers
    - Unintended debug code or `var_dump`/`console.log` statements
12. **Fix Issues**: If any verification step fails, fix the issues and re-run verification. Do NOT proceed until all checks pass.

### Phase 5: Commit & Submit
13. **Commit**: Create a well-formatted commit message following conventional commits format with issue reference.
14. **Push**: Push the branch to remote (`git push -u origin <branch-name>`).
15. **Create PR**: Create a pull request using `mcp__plugin_github_github__create_pull_request` with:
    - Clear title: `[Issue #X] Brief description`
    - Summary of changes
    - Test plan checklist
16. **Close Issue**: After PR is created, close the issue using `mcp__plugin_github_github__issue_write` with:
    - `method: "update"`
    - `state: "closed"`
    - `state_reason: "completed"`
    - Add a comment linking to the PR

## Context

This is a WordPress/WooCommerce plugin currently in a **Migration Phase**:
- Moving from: Procedural PHP, Global Variables, Inline JS (`js.php`).
- Moving to: Object-Oriented PHP, Composer, Static Assets, Strict Types.

## Quality Standards
- **Security**: Zero tolerance for unescaped output or unparameterized SQL.
- **Testing**: All verification checks MUST pass before creating PR.
- **Manual Verification**: All UI/AJAX changes must be manually verified in a WordPress Sandbox before marking the issue as resolved.
- **Architecture**: Do not add technical debt to legacy files.

## Error Handling

If verification fails and you cannot fix the issue automatically:
1. Clearly describe the problem to the user
2. Ask for guidance using `AskUserQuestion`
3. Do NOT submit a PR with failing checks

## Delegation

Invoke the implement-issue subagent with the full issue context:

Use the implement-issue subagent to implement GitHub issue $ARGUMENTS

The subagent will handle the complete implementation workflow and return a summary of changes.
