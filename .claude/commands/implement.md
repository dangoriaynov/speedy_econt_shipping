---
description: "Implement a GitHub issue or feature request. Works directly on main branch."
argument_hint: "<issue-number-or-description>"
allowed_tools:
  - Read
  - Write
  - Edit
  - Bash
  - Grep
  - Glob
  - Task
  - AskUserQuestion
---

## Mission

Implement: $ARGUMENTS

## Instructions

Use the `implement-issue` subagent to implement this task.

### Workflow

1. **Sync**: Pull latest `main` branch
2. **Analyze**: Understand the requirements
3. **Implement**: Build following WordPress standards
4. **Verify**: Run PHP syntax check, review security
5. **Report**: Summarize changes made

### Quality Standards

- **Security**: All inputs sanitized, all outputs escaped, SQL uses `$wpdb->prepare`
- **Nonces**: All AJAX handlers verify nonces
- **No Debug Code**: Remove `var_dump`, `console.log` statements

### Note

The subagent does NOT commit or push. User handles version control after reviewing changes.

## Delegation

Use the implement-issue subagent to implement: $ARGUMENTS
