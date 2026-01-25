# Settings Page Documentation

## Overview

The Speedy & Econt Shipping plugin integrates its settings into WooCommerce's native settings interface, following WordPress and WooCommerce best practices.

## Settings Page Location

**Navigation Path:** WooCommerce > Settings > Speedy & Econt

**Direct URL:** `wp-admin/admin.php?page=wc-settings&tab=sesh_shipping`

## Settings Sections

The settings page is organized into four sections:

### 1. General (Default Section)
- **URL:** `admin.php?page=wc-settings&tab=sesh_shipping`
- **Settings:**
  - Shipping Options Order
  - Emergency Contact
  - Free Shipping Label
  - Email Required
  - Validate Address
  - Hidden Fields (CSS Selectors)
  - Debug Mode

### 2. Speedy
- **URL:** `admin.php?page=wc-settings&tab=sesh_shipping&section=speedy`
- **Settings:**
  - Enable Speedy
  - API Username
  - API Password (encrypted)
  - Use Dynamic Pricing
  - Fallback Rate
  - Free Shipping Threshold

### 3. Econt
- **URL:** `admin.php?page=wc-settings&tab=sesh_shipping&section=econt`
- **Settings:**
  - Enable Econt
  - API Username (optional for offices, required for shipments)
  - API Password (encrypted, optional)
  - Use Dynamic Pricing
  - Fallback Rate
  - Free Shipping Threshold

### 4. Address Delivery
- **URL:** `admin.php?page=wc-settings&tab=sesh_shipping&section=address`
- **Settings:**
  - Enable Address Delivery
  - Address Label
  - Fallback Rate
  - Free Shipping Threshold
  - Address Fields (CSS Selectors)

## Implementation Details

### Class Structure

1. **SESH_WC_Settings** (`includes/admin/class-sesh-wc-settings.php`)
   - Extends `WC_Settings_Page`
   - Handles settings rendering and saving
   - Syncs with plugin's internal settings structure

2. **SESH_Settings** (`includes/class-sesh-settings.php`)
   - Internal settings API
   - Provides getter/setter methods
   - Handles backward compatibility with legacy settings

3. **SESH_Plugin** (`includes/class-sesh-plugin.php`)
   - Registers the settings page with WooCommerce
   - Provides `get_settings_url()` helper method

### Helper Method

Use `SESH_Plugin::get_settings_url()` to generate settings URLs programmatically:

```php
// General settings
$url = SESH_Plugin::get_settings_url();

// Specific section
$speedy_url = SESH_Plugin::get_settings_url( 'speedy' );
$econt_url = SESH_Plugin::get_settings_url( 'econt' );
$address_url = SESH_Plugin::get_settings_url( 'address' );
```

### Admin Notices

The plugin displays admin notices when credentials are missing:
- Notices link directly to the appropriate settings section
- Uses the centralized `get_settings_url()` helper for consistency

### Plugin Action Links

A "Settings" link appears on the Plugins page, linking to the general settings section.

## Security Features

1. **Password Encryption:** API passwords are encrypted using `SESH_Encryption` class
2. **Input Sanitization:** All inputs are sanitized before saving
3. **Output Escaping:** All outputs are properly escaped
4. **Nonce Verification:** WooCommerce handles nonce verification for settings saves

## Migration from Legacy Settings

The plugin automatically migrates from the legacy settings structure to the new WooCommerce-integrated format. See `SESH_Settings_Migrator` class for details.

## Developer Notes

- Always use `SESH_Plugin::get_settings_url()` instead of hardcoding URLs
- Settings are saved to both WooCommerce options (prefixed with `sesh_`) and the internal plugin structure
- The settings page is only loaded in admin context when WooCommerce is active
- HPOS (High-Performance Order Storage) compatible
