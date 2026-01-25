# Admin Order UI - Quick Reference Guide

## For Developers

### File Structure
```
includes/admin/
├── class-sesh-admin.php           # Main admin class
├── class-sesh-admin-orders.php    # Order list & meta boxes
└── class-sesh-admin-ajax.php      # AJAX handlers

templates/admin/
├── label-meta-box.php             # Label meta box template
└── tracking-meta-box.php          # Tracking meta box template

assets/
├── js/sesh-admin-orders.js        # Client-side functionality
└── css/sesh-admin-orders.css      # Admin UI styles
```

### AJAX Endpoints

#### Generate Label
```javascript
$.post(ajaxurl, {
  action: 'sesh_generate_label',
  security: nonce,
  order_id: 123
});
```

#### Cancel Label
```javascript
$.post(ajaxurl, {
  action: 'sesh_cancel_label',
  security: nonce,
  label_id: 456
});
```

#### Refresh Tracking
```javascript
$.post(ajaxurl, {
  action: 'sesh_refresh_tracking',
  security: nonce,
  label_id: 456
});
```

### PHP Hooks & Filters

#### Customize Label Meta Box
```php
add_filter('sesh_label_meta_box_content', function($content, $order, $label) {
  // Modify meta box HTML
  return $content;
}, 10, 3);
```

#### Customize Tracking Events Display
```php
add_filter('sesh_tracking_events_formatted', function($events, $label) {
  // Modify event array before display
  return $events;
}, 10, 2);
```

### CSS Classes Reference

#### Label Status
- `.sesh-status-pending` - Gray
- `.sesh-status-generated` - Blue
- `.sesh-status-printed` - Green
- `.sesh-status-cancelled` - Red

#### Carriers
- `.sesh-carrier-speedy` - Red background
- `.sesh-carrier-econt` - Green background

#### Timeline Events
- `.sesh-timeline-events` - Event list
- `.sesh-timeline-event` - Single event
- `.sesh-event-date` - Event timestamp
- `.sesh-event-status` - Event status text

### JavaScript API

#### Show Admin Notice
```javascript
SeshAdminOrders.showNotice('success', 'Label generated!');
SeshAdminOrders.showNotice('error', 'Failed to generate label.');
```

#### Set Loading State
```javascript
SeshAdminOrders.setLoading($button, true, 'Processing...');
// ... operation ...
SeshAdminOrders.setLoading($button, false);
```

### Common Customizations

#### Add Custom Button to Label Meta Box
```php
add_action('sesh_label_meta_box_actions', function($label, $order) {
  echo '<button class="button">Custom Action</button>';
}, 10, 2);
```

#### Modify Carrier Logo
```css
.sesh-carrier-logo.sesh-carrier-custom {
  background: #3498db;
  color: #fff;
}
```

#### Add Custom Tracking Event Icon
```php
add_filter('sesh_tracking_event_icon', function($icon, $status) {
  if ($status === 'custom_status') {
    return 'dashicons-star-filled';
  }
  return $icon;
}, 10, 2);
```

### Troubleshooting

#### Labels Not Showing
1. Check if order uses SESH shipping method
2. Verify label exists in database
3. Check user has `edit_shop_orders` capability

#### AJAX Not Working
1. Verify nonce is correct
2. Check browser console for errors
3. Ensure jQuery is loaded

#### Tracking Not Updating
1. Check transient cache (30 min expiry)
2. Verify API credentials
3. Check carrier API response

### Database Schema

#### Labels Table: `wp_sesh_shipping_labels`
```sql
id                - bigint(20) UNSIGNED AUTO_INCREMENT
order_id          - bigint(20) UNSIGNED
carrier           - varchar(20)
tracking_number   - varchar(100)
label_data        - longtext (PDF binary)
label_format      - varchar(10) DEFAULT 'pdf'
status            - varchar(20) DEFAULT 'pending'
api_response      - text
created_at        - timestamp
updated_at        - timestamp
```

### Localization

All strings use text domain `'speedy_econt_shipping'`:

```php
__('Generate Label', 'speedy_econt_shipping')
_e('Tracking Number:', 'speedy_econt_shipping')
```

### Performance Tips

1. **Caching:** Tracking data cached for 30 minutes
2. **Lazy Loading:** Assets only load on order pages
3. **Bulk Operations:** Use bulk actions for multiple orders
4. **Database:** Labels cached in DB after first fetch

## For Store Admins

### How to Use

#### Generate Label for Single Order
1. Open order in WooCommerce
2. Find "Shipping Label" meta box (right sidebar)
3. Click "Generate Label"
4. Wait for confirmation
5. Download or print label

#### Bulk Generate Labels
1. Go to WooCommerce → Orders
2. Select orders (checkbox)
3. Choose "Generate shipping labels" from Bulk Actions
4. Click Apply
5. View results in admin notice

#### View Tracking Information
1. Open order with label
2. Scroll to "Tracking Information" meta box
3. Click "Refresh Tracking" for latest updates
4. Click "Track on Carrier Site" for full details

#### Print Multiple Labels
1. Go to WooCommerce → Orders
2. Select orders with labels
3. Choose "Print shipping labels" from Bulk Actions
4. Click Apply
5. PDFs open in new window

### Troubleshooting for Admins

#### "Cannot generate label" Error
**Check:**
- Sender address configured (Settings)
- Recipient address complete
- Products have weights
- Valid shipping method selected

#### Tracking Not Updating
**Solutions:**
- Click "Refresh Tracking" button
- Wait 30 minutes (cache expiry)
- Contact carrier if no updates after 24h

#### Label Download Fails
**Solutions:**
- Disable popup blocker
- Try different browser
- Check file permissions on server

### Best Practices

1. **Always verify order details** before generating labels
2. **Print labels immediately** after generation
3. **Refresh tracking regularly** for accurate status
4. **Cancel unused labels** to avoid carrier charges
5. **Use bulk actions** for efficiency with multiple orders
