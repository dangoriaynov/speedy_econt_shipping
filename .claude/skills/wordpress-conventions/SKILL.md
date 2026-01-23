---
description: "WordPress Plugin Development Standards. Use when: writing PHP/JS for WordPress, handling database queries, security, or WooCommerce hooks."
---

# WordPress Conventions Skill

This skill enforces security and architectural standards for the Speedy/Econt Shipping plugin.

## 1. Security (Non-Negotiable)

### Sanitization (Input)
* **Text:** `sanitize_text_field($var)`
* **Email:** `sanitize_email($var)`
* **Keys/Slugs:** `sanitize_key($var)`
* **Arrays:** `array_map('sanitize_text_field', $array)`
* **HTML (Rich):** `wp_kses_post($var)` (Avoid unless necessary)

### Escaping (Output)
* **HTML Body:** `esc_html($var)` or `esc_html_e('text', 'domain')`
* **Attributes:** `esc_attr($var)`
* **URLs:** `esc_url($var)`
* **JS Variables:** `esc_js($var)`

### Database
* **ALWAYS** use `$wpdb->prepare` for variable insertion.
    * ✅ `$wpdb->get_results($wpdb->prepare("SELECT * FROM table WHERE id = %d", $id));`
    * ❌ `$wpdb->get_results("SELECT * FROM table WHERE id = $id");` // AUTO-FAIL

### Nonces
* Verify nonces in **every** AJAX handler and Form submission.
    * `check_ajax_referer('ses_nonce_action', 'security');`

## 2. Coding Standards

### Naming
* **Classes:** `class-classname.php` (file), `SpeedyEcont_ClassName` (Class name)
* **Variables:** `$snake_case`
* **Constants:** `UPPER_CASE`
* **Prefix:** `ses_` or `SES_` for everything global.

### Architecture
* **No Global State:** Do not use `global $ses_options`. Use a `Settings::get()` method.
* **API Clients:** Must return standardized response objects, masking the differences between Speedy (JSON) and Econt (XML).
* **Transients:** Cache API responses (e.g., Office Lists) using `set_transient()` for at least 24 hours.

## 3. Git Conventions

### Branch Naming
* `feature/dynamic-rates`
* `fix/checkout-js-error`
* `refactor/db-layer`
* `chore/composer-setup`

### Commit Messages
Format: `type(scope): description [Relates to #issue]`

* `feat(api): implement speedy calculate_rate method`
* `fix(checkout): resolve ajax nonce failure`
* `refactor(legacy): move office loader from db.php to repository class`

## Review & Merge Workflow

### 1. Automated Checks
Before requesting a review, ensure the following pass:
- `composer run phpcs` (Code Style)
- `npm run lint` (JS/CSS Linting)

### 2. Manual Verification (Required)
WordPress plugins require manual testing in a live environment. Perform the following checks based on the scope:

**For Admin Changes:**
- [ ] Activate the plugin on a clean Sandbox site.
- [ ] Navigate to `WooCommerce > Settings > Shipping > Speedy/Econt`.
- [ ] Verify fields save correctly (refresh page to check persistence).
- [ ] Check for PHP warnings in `debug.log`.

**For Checkout/Frontend Changes:**
- [ ] Add a product to the cart and proceed to Checkout.
- [ ] Change "Billing Country" to Bulgaria (if applicable).
- [ ] Verify City/Region dropdowns populate via AJAX.
- [ ] Switch between "Speedy" and "Econt" methods; ensure rates update.
- [ ] Place a test order; verify "Order Received" page loads without error.

### 3. Merge Strategy
- **Squash & Merge:** Use "Squash and merge" to keep the main branch history clean (one commit per feature).
- **Version Bump:** If the change affects production behavior, update the version number in `speedy_econt_shipping.php`.
