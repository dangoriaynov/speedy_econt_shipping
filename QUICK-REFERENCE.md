# Quick Reference: Settings Page URLs

## Settings Page Location

**Navigation:** WooCommerce > Settings > Speedy & Econt

**Base URL:** `wp-admin/admin.php?page=wc-settings&tab=sesh_shipping`

## Direct Section URLs

| Section | URL |
|---------|-----|
| General | `wp-admin/admin.php?page=wc-settings&tab=sesh_shipping` |
| Speedy | `wp-admin/admin.php?page=wc-settings&tab=sesh_shipping&section=speedy` |
| Econt | `wp-admin/admin.php?page=wc-settings&tab=sesh_shipping&section=econt` |
| Address | `wp-admin/admin.php?page=wc-settings&tab=sesh_shipping&section=address` |

## Helper Method Usage

```php
// Get general settings URL
$url = SESH_Plugin::get_settings_url();

// Get specific section URL
$speedy_url = SESH_Plugin::get_settings_url( 'speedy' );
$econt_url = SESH_Plugin::get_settings_url( 'econt' );
$address_url = SESH_Plugin::get_settings_url( 'address' );
```

## Admin Notices

When credentials are missing, admin notices will display with direct links to the appropriate settings section:

- **Speedy credentials missing** → Links to Speedy section
- **Econt credentials missing** → Links to Econt section

## Plugin Links

On the WordPress Plugins page (`wp-admin/plugins.php`), a "Settings" link appears below the plugin name, linking directly to the General settings section.

## Settings Structure

### General Settings
- Shipping Options Order
- Emergency Contact
- Free Shipping Label
- Email Required
- Validate Address
- Hidden Fields (CSS Selectors)
- Debug Mode

### Speedy Settings
- Enable Speedy
- API Username
- API Password (encrypted)
- Use Dynamic Pricing
- Fallback Rate
- Free Shipping Threshold

### Econt Settings
- Enable Econt
- API Username (optional)
- API Password (encrypted, optional)
- Use Dynamic Pricing
- Fallback Rate
- Free Shipping Threshold

### Address Delivery Settings
- Enable Address Delivery
- Address Label
- Fallback Rate
- Free Shipping Threshold
- Address Fields (CSS Selectors)

## Key Classes

| Class | File | Purpose |
|-------|------|---------|
| `SESH_WC_Settings` | `includes/admin/class-sesh-wc-settings.php` | WooCommerce settings integration |
| `SESH_Settings` | `includes/class-sesh-settings.php` | Internal settings API |
| `SESH_Plugin` | `includes/class-sesh-plugin.php` | Main plugin class with URL helper |
| `SESH_Admin` | `includes/admin/class-sesh-admin.php` | Admin functionality and notices |

## Security Notes

- All API passwords are encrypted using `SESH_Encryption` class
- All inputs are sanitized before saving
- All outputs are properly escaped
- Settings URLs use `sanitize_key()` for section parameters
