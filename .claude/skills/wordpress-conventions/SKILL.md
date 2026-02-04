---
description: "WordPress Plugin Development Standards. Use when: writing PHP/JS for WordPress, handling database queries, security, or WooCommerce hooks."
---

# WordPress Conventions Skill

Security and coding standards for the Speedy/Econt Shipping plugin.

## 1. Security (Non-Negotiable)

### Sanitization (Input)
* **Text:** `sanitize_text_field($var)`
* **Email:** `sanitize_email($var)`
* **Integer:** `absint($var)`
* **Arrays:** `array_map('sanitize_text_field', $array)`

### Escaping (Output)
* **HTML Body:** `esc_html($var)`
* **Attributes:** `esc_attr($var)`
* **URLs:** `esc_url($var)`
* **Translations:** `esc_html_e('text', 'speedy_econt_shipping')`

### Database
* **ALWAYS** use `$wpdb->prepare` for variable insertion.
* ✅ `$wpdb->get_results($wpdb->prepare("SELECT * FROM table WHERE id = %d", $id));`
* ❌ `$wpdb->get_results("SELECT * FROM table WHERE id = $id");`

### Nonces
* Verify nonces in **every** AJAX handler:
  ```php
  check_ajax_referer('ses_nonce_action', 'security');
  ```

## 2. Coding Standards

### Naming
* **Files:** `class-sesh-classname.php`
* **Classes:** `SESH_ClassName`
* **Variables:** `$snake_case`
* **Constants:** `SESH_UPPER_CASE`

### Architecture
* Classes in `includes/` directory
* JS/CSS in `assets/` directory
* Templates in `templates/` directory
* Use `wp_localize_script()` to pass PHP data to JS

## 3. Commit Messages

**Format:** `type(scope): description`

**Types:** `feat`, `fix`, `refactor`, `docs`, `chore`

**Scopes:** `core`, `admin`, `checkout`, `api`, `speedy`, `econt`, `db`, `assets`

**Example:**
```
feat(admin): add label generation button to order page

Adds "Generate Label" button with AJAX handling and error retry.
```
