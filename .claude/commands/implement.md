---
description: Implement a GitHub issue using the implement-issue subagent. Creates a feature branch, analyzes the legacy context, and implements using WordPress best practices.
argument-hint: <issue-number-or-url>
allowed-tools: Read, Write, Edit, Bash, Grep, Glob, Task
---

## Mission

Implement the GitHub issue: $ARGUMENTS

## Instructions

Use the `implement-issue` subagent to implement this GitHub issue following the complete workflow:

1. **Create Branch**: Create a feature branch following naming conventions.
2. **Analyze Context**: Determine if the issue touches legacy files (`js.php`, `db.php`, `globals`) and plan the refactoring.
3. **Fetch Docs**: Read WordPress Code Reference or WooCommerce docs if needed.
4. **Implement**: Build the feature following WordPress Coding Standards (Strict Sanitization & Nonces).
5. **Migrate**: Ensure legacy patterns are replaced with Object-Oriented patterns where touched.
6. **Test**: Verify the implementation works and does not break existing `is_prod` data logic.
7. **Commit**: Create a well-formatted commit message.

## Context

This is a WordPress/WooCommerce plugin currently in a **Migration Phase**:
- Moving from: Procedural PHP, Global Variables, Inline JS (`js.php`).
- Moving to: Object-Oriented PHP, Composer, Static Assets, Strict Types.

## Quality Standards
- **Security**: Zero tolerance for unescaped output or unparameterized SQL.
- **Manual Verification**: All UI/AJAX changes must be manually verified in a WordPress Sandbox before marking the issue as resolved.
- **Architecture**: Do not add technical debt to legacy files.

## Delegation

Invoke the implement-issue subagent with the full issue context:

Use the implement-issue subagent to implement GitHub issue $ARGUMENTS


The subagent will handle the complete implementation workflow and return a summary of changes.
