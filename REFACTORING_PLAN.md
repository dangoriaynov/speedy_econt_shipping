# Namespace Refactoring Plan - Remove SESH Prefix

## Overview
This document outlines the plan to remove the 'SESH_' and 'sesh_' prefixes from the codebase and replace them with proper PHP namespaces following PSR-4 standards.

## Scope Analysis

### Files Affected
- **PHP Class Files**: 18 files
- **Main Plugin File**: speedy_econt_shipping.php
- **JavaScript Files**: 4 files  
- **CSS Files**: 2 files
- **Template Files**: 1 file
- **Total References**: 211+ in PHP files

### Impact Assessment
- **Breaking Changes**: Yes - this will be a major version bump (v3.0.0)
- **Backward Compatibility**: Requires class_alias() mappings
- **Database**: Option names will need migration or aliases
- **Hooks/Filters**: All hook names will change
- **Testing Required**: Extensive

## Namespace Structure

```
SpeedyEcontShipping\
├── Plugin (Main plugin class)
├── Autoloader
├── Settings
├── SettingsMigrator
├── Encryption
├── API\
│   ├── APIClientInterface
│   ├── SpeedyAPI
│   ├── EcontAPI
│   ├── APIException
│   ├── SpeedyAPIException
│   ├── EcontAPIException
│   ├── ShippingQuote
│   └── ShipmentResponse
├── Admin\
│   └── Admin
├── Database\
│   ├── Database
│   └── DatabaseMigrator
├── ShippingMethods\
│   ├── ShippingMethod (abstract)
│   ├── Speedy
│   ├── Econt
│   └── Address
└── Frontend\
    ├── Frontend
    └── CartCalculator
```

## File Renaming Map

| Old File | New File |
|----------|----------|
| `includes/class-sesh-plugin.php` | `includes/class-plugin.php` |
| `includes/class-sesh-autoloader.php` | `includes/class-autoloader.php` ✅ |
| `includes/class-sesh-settings.php` | `includes/class-settings.php` |
| `includes/class-sesh-settings-migrator.php` | `includes/class-settings-migrator.php` |
| `includes/class-sesh-encryption.php` | `includes/class-encryption.php` |
| `includes/class-sesh-frontend.php` | `includes/frontend/class-frontend.php` |
| `includes/class-sesh-cart-calculator.php` | `includes/frontend/class-cart-calculator.php` |
| `includes/database/class-sesh-database.php` | `includes/database/class-database.php` |
| `includes/database/class-sesh-db-migrator.php` | `includes/database/class-database-migrator.php` |
| `includes/api/class-sesh-speedy-api.php` | `includes/api/class-speedy-api.php` |
| `includes/api/class-sesh-econt-api.php` | `includes/api/class-econt-api.php` |
| `includes/api/class-sesh-api-exception.php` | `includes/api/class-api-exception.php` |
| `includes/api/interface-sesh-api-client.php` | `includes/api/interface-api-client.php` |
| `includes/shipping-methods/abstract-sesh-shipping-method.php` | `includes/shipping-methods/abstract-shipping-method.php` |
| `includes/shipping-methods/class-sesh-shipping-speedy.php` | `includes/shipping-methods/class-speedy.php` |
| `includes/shipping-methods/class-sesh-shipping-econt.php` | `includes/shipping-methods/class-econt.php` |
| `includes/shipping-methods/class-sesh-shipping-address.php` | `includes/shipping-methods/class-address.php` |
| `includes/admin/class-sesh-admin.php` | `includes/admin/class-admin.php` |
| `assets/js/sesh-checkout.js` | `assets/js/checkout.js` |
| `assets/js/sesh-location-selector.js` | `assets/js/location-selector.js` |
| `assets/js/sesh-price-display.js` | `assets/js/price-display.js` |
| `assets/js/sesh-cart-calculator.js` | `assets/js/cart-calculator.js` |

## Constant Renaming

| Old Constant | New Constant |
|--------------|--------------|
| `SESH_VERSION` | `SPEEDY_ECONT_VERSION` |
| `SESH_DB_VERSION` | `SPEEDY_ECONT_DB_VERSION` |
| `SESH_PLUGIN_FILE` | `SPEEDY_ECONT_PLUGIN_FILE` |
| `SESH_PLUGIN_DIR` | `SPEEDY_ECONT_PLUGIN_DIR` |
| `SESH_PLUGIN_URL` | `SPEEDY_ECONT_PLUGIN_URL` |
| `SESH_PLUGIN_BASENAME` | `SPEEDY_ECONT_PLUGIN_BASENAME` |

## Hook/Action Renaming

| Old Hook | New Hook |
|----------|----------|
| `sesh_init` | `speedy_econt_shipping_init` |
| `sesh_activated` | `speedy_econt_shipping_activated` |
| `sesh_deactivated` | `speedy_econt_shipping_deactivated` |
| `sesh_daily_data_refresh` | `speedy_econt_daily_data_refresh` |
| `sesh_initial_data_fetch` | `speedy_econt_initial_data_fetch` |
| `sesh_speedy_data_refresh` | `speedy_econt_speedy_data_refresh` |
| `sesh_econt_data_refresh` | `speedy_econt_econt_data_refresh` |

## Implementation Phases

### Phase 1: Foundation (✅ STARTED)
- [x] Create new `Autoloader` class with namespace support
- [x] Create new `Plugin` class with namespace
- [ ] Create directory structure for namespaced files

### Phase 2: Core Classes
- [ ] Convert Settings class
- [ ] Convert SettingsMigrator class
- [ ] Convert Encryption class
- [ ] Update main plugin file to use namespaced classes

### Phase 3: Database Classes
- [ ] Convert Database class
- [ ] Convert DatabaseMigrator class
- [ ] Update schema references

### Phase 4: API Classes
- [ ] Convert APIClientInterface
- [ ] Convert SpeedyAPI class  
- [ ] Convert EcontAPI class
- [ ] Convert API Exception classes
- [ ] Convert response/quote classes

### Phase 5: Shipping Method Classes
- [ ] Convert abstract ShippingMethod class
- [ ] Convert Speedy shipping method
- [ ] Convert Econt shipping method
- [ ] Convert Address shipping method

### Phase 6: Admin & Frontend
- [ ] Convert Admin class
- [ ] Convert Frontend class
- [ ] Convert CartCalculator class

### Phase 7: JavaScript & Assets
- [ ] Rename JavaScript files
- [ ] Update JavaScript references in PHP
- [ ] Update localized script handles
- [ ] Update CSS file references

### Phase 8: Backward Compatibility
- [ ] Create class_alias() mappings for old class names
- [ ] Create constant aliases for old constant names
- [ ] Maintain legacy hook compatibility
- [ ] Test upgrade from v2.0 to v3.0

### Phase 9: Testing
- [ ] Test fresh installation
- [ ] Test upgrade from v1.x
- [ ] Test upgrade from v2.x
- [ ] Test checkout flow (Speedy)
- [ ] Test checkout flow (Econt)
- [ ] Test checkout flow (Address)
- [ ] Test admin settings save/load
- [ ] Test API integration
- [ ] Test database queries
- [ ] PHP lint all files
- [ ] Check for PHP warnings/errors
- [ ] Check for JavaScript console errors

### Phase 10: Cleanup
- [ ] Remove old SESH_ prefixed files
- [ ] Update documentation
- [ ] Update README
- [ ] Create upgrade guide
- [ ] Bump version to 3.0.0

## Backward Compatibility Strategy

To ensure existing installations don't break:

1. **Class Aliases**: Map old class names to new ones
   ```php
   class_alias('SpeedyEcontShipping\\Plugin', 'SESH_Plugin');
   class_alias('SpeedyEcontShipping\\Settings', 'SESH_Settings');
   // ... etc
   ```

2. **Constant Aliases**: Define old constants pointing to new ones
   ```php
   define('SESH_VERSION', SPEEDY_ECONT_VERSION);
   define('SESH_PLUGIN_DIR', SPEEDY_ECONT_PLUGIN_DIR);
   // ... etc
   ```

3. **Hook Compatibility**: Fire both old and new hooks
   ```php
   do_action('speedy_econt_shipping_init', $this);
   do_action('sesh_init', $this); // Legacy
   ```

4. **Option Migration**: Settings migrator handles old option names

## Estimated Effort

- **Total Files to Modify**: 25+
- **Total References to Update**: 211+
- **Estimated Time**: 6-8 hours
- **Testing Time**: 2-3 hours
- **Total**: 8-11 hours

## Risks

1. **Breaking Changes**: Major version bump required
2. **Third-party Integration**: Any code extending SESH_ classes will break
3. **Database Options**: Migration complexity
4. **Testing Coverage**: No automated tests currently exist

## Mitigation

1. Maintain backward compatibility via aliases
2. Create comprehensive upgrade guide
3. Test on staging environment first
4. Provide rollback instructions
5. Bump to v3.0.0 to signal breaking changes

## Status

- **Current Status**: Planning / Foundation Started
- **Completion**: 5%
- **Blocker**: Scope confirmation needed
- **Next Step**: Decision on proceeding with full refactoring

## Questions for Stakeholder

1. Should this be done now or deferred to v3.0.0 milestone?
2. Are there any third-party integrations we need to consider?
3. What is the testing environment/process?
4. Should we maintain SESH_ aliases indefinitely or deprecate them?

