# Sender Settings - Quick Reference Guide

## 🚀 Quick Start

### Access Settings
1. WordPress Admin → WooCommerce → Settings
2. Click "Shipping" tab
3. Click "Speedy & Econt" tab
4. Click "Sender Address" section

### Fill Required Fields
```
Company/Sender Name: Your Company Ltd.
Phone Number:        0888123456
Email:              info@yourcompany.com
Region:             София
City:               София
Street Address:     ул. Витоша 15, ет. 3
Post Code:          1000
```

### Save
Click "Save changes" button at bottom.

---

## 📱 Phone Number Formats

### ✅ Valid Formats
- `0888123456` (local format)
- `+359888123456` (international)
- `00359888123456` (alternative international)
- `0888 123 456` (with spaces - cleaned automatically)
- `+359-888-123-456` (with dashes - cleaned automatically)

### ❌ Invalid Formats
- `888123456` (missing leading zero)
- `0888` (too short)
- `123456789` (wrong prefix)

---

## 🏙️ City Autocomplete

### How to Use
1. Click in "City" field
2. Type at least 2 characters (e.g., "Соф")
3. Wait for dropdown to appear
4. Select city from list
5. Region auto-fills automatically

### Search Examples
- Type `София` → Returns "София (София)"
- Type `Варна` → Returns "Варна (Варна)"
- Type `Плов` → Returns "Пловдив (Пловдив)"

### Troubleshooting
- **No results?** Ensure database tables are populated (run sync from General settings)
- **Dropdown not appearing?** Check JavaScript console for errors
- **Wrong cities?** Clear your browser cache

---

## 🔌 Using in Code (For Developers)

### Get All Sender Data
```php
$settings = new SESH_Settings();
$sender = $settings->get_sender_params();

// Returns:
array(
    'name'     => 'Your Company Ltd.',
    'phone'    => '0888123456',
    'email'    => 'info@yourcompany.com',
    'region'   => 'София',
    'city'     => 'София',
    'address'  => 'ул. Витоша 15, ет. 3',
    'postcode' => '1000',
)
```

### Get Individual Fields
```php
$settings = new SESH_Settings();

$name     = $settings->get_sender_name();     // 'Your Company Ltd.'
$phone    = $settings->get_sender_phone();    // '0888123456'
$email    = $settings->get_sender_email();    // 'info@yourcompany.com'
$region   = $settings->get_sender_region();   // 'София'
$city     = $settings->get_sender_city();     // 'София'
$address  = $settings->get_sender_address();  // 'ул. Витоша 15, ет. 3'
$postcode = $settings->get_sender_postcode(); // '1000'
```

### Use in Speedy API
```php
$settings = new SESH_Settings();
$sender = $settings->get_sender_params();

$api = new SESH_Speedy_API( $username, $password );
$response = $api->create_shipment(
    array(
        'sender' => array(
            'phone' => array(
                'number' => $sender['phone'],
            ),
            'contactName' => $sender['name'],
            'email' => $sender['email'],
            'address' => array(
                'siteId' => 68134, // Sofia site ID
                'streetName' => $sender['address'],
                'postCode' => $sender['postcode'],
            ),
        ),
        'recipient' => array(
            // ... recipient from order
        ),
        // ... rest of shipment params
    )
);
```

### Use in Econt API
```php
$settings = new SESH_Settings();
$sender = $settings->get_sender_params();

$api = new SESH_Econt_API( $username, $password );
$response = $api->create_shipment(
    array(
        'sender' => array(
            'name'    => $sender['name'],
            'phone'   => $sender['phone'],
            'email'   => $sender['email'],
            'city'    => $sender['city'],
            'address' => $sender['address'],
            'zip'     => $sender['postcode'],
        ),
        'receiver' => array(
            // ... receiver from order
        ),
        // ... rest of shipment params
    )
);
```

---

## 🛠️ Validation Reference

### Automatic Validation
- **Phone**: Validated on blur and form submit
- **Email**: Validated by WordPress core
- **City**: Soft validation via autocomplete (not enforced)

### Manual Validation Check
```php
$settings = new SESH_Settings();

// Phone validation
$phone = '0888123456';
$is_valid = $settings->validate_bulgarian_phone( $phone ); // true/false

// Note: validate_bulgarian_phone() is private, use sanitize instead:
$clean_phone = sanitize_text_field( $_POST['phone'] );
// Then save via settings API which runs validation
```

---

## 🐛 Troubleshooting

### Settings Not Saving
1. Check WordPress user permissions (must be admin)
2. Check for PHP errors in debug.log
3. Verify WooCommerce is active
4. Clear object cache if using Redis/Memcached

### Autocomplete Not Working
1. Open browser DevTools → Console
2. Check for JavaScript errors
3. Go to Network tab → Type in city field
4. Verify AJAX request to `admin-ajax.php`
5. Check response for errors

### Phone Validation Failing
1. Remove all spaces and dashes
2. Ensure starts with `0` or `+359` or `00359`
3. Must be exactly 10 digits after country code
4. Example: `0888123456` NOT `888123456`

### City Not Found in Autocomplete
1. Ensure database tables populated
2. Run data sync: WooCommerce → Settings → Shipping → Speedy & Econt → General → Sync Data
3. Check if city exists: `SELECT * FROM wp_speedy_sites WHERE name LIKE '%София%'`

---

## 📋 Database Schema

### Settings Storage
```
Option Name: sesh_sender_settings
Option Value: Array (
    'sender_name'     => string,
    'sender_phone'    => string,
    'sender_email'    => string,
    'sender_region'   => string,
    'sender_city'     => string,
    'sender_address'  => string,
    'sender_postcode' => string,
)
```

### City Search Tables
- `wp_speedy_sites` (id, name, region, is_prod)
- `wp_econt_sites` (id, name, region, is_prod)

---

## 🔐 Security Notes

- All inputs are sanitized before saving
- Phone numbers validated against Bulgarian format
- SQL queries use prepared statements
- AJAX requests protected with nonces
- XSS protection via output escaping

---

## 💡 Pro Tips

1. **Use Autocomplete**: Always use city autocomplete instead of typing manually to ensure city exists in carrier databases
2. **Standard Format**: Use local phone format `0888123456` for consistency
3. **Full Address**: Include building number, floor, apartment in Street Address field
4. **Test First**: Test with Speedy/Econt sandbox before going live
5. **Keep Updated**: Update sender info if you move offices

---

## 📞 Support

If you encounter issues:
1. Check debug.log: `wp-content/debug.log`
2. Enable Debug Mode: WooCommerce → Settings → Shipping → Speedy & Econt → General → Debug Mode
3. Check browser console for JavaScript errors
4. Verify database tables exist and populated
5. Test AJAX endpoint manually: `/wp-admin/admin-ajax.php?action=sesh_search_cities&security=<nonce>&search=София`

---

## 🔄 Updates & Migrations

### From v1.x to v2.x
- Settings automatically migrated on plugin update
- Old settings preserved as backup in `sesh_legacy_settings_backup`
- No manual action required

### Rollback
```php
// To rollback to legacy settings (if needed)
SESH_Settings_Migrator::rollback();
```

---

## ✅ Checklist Before Going Live

- [ ] All sender fields filled
- [ ] Phone number validated (no error message)
- [ ] Email address valid
- [ ] City selected from autocomplete
- [ ] Address includes building number
- [ ] Postcode filled (4 digits)
- [ ] Tested label generation on sandbox
- [ ] Verified sender info appears on test label

---

**Last Updated**: 2026-01-25
**Plugin Version**: 2.0.0+
**Requires**: WordPress 6.0+, WooCommerce 8.0+
