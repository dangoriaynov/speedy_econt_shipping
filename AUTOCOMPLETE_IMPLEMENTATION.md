# Autocomplete Implementation Summary

## Overview
Implemented auto-complete functionality for city and office selection in the Speedy/Econt shipping plugin.

## Features Implemented

### 1. City Autocomplete (DB Lookup)
- **Cart Page**: City search with autocomplete
- **Checkout Page**: City selection for Speedy and Econt offices
- **Source**: Local database tables (`speedy_sites`, `econt_sites`)
- **Minimum Characters**: 2
- **Results Limit**: 20 cities

### 2. Office Autocomplete (DB Lookup)
- **Cart Page**: Office search with autocomplete
- **Checkout Page**: Office selection for Speedy and Econt
- **Source**: Local database tables (`speedy_offices`, `econt_offices`)
- **Minimum Characters**: 2
- **Results Limit**: 20 offices
- **Features**:
  - Search by office name, address, or city
  - Filter by selected city if available
  - Speedy offices show office number (e.g., "№123, Office Name")

### 3. Street Autocomplete (Speedy API)
- **Checkout Page**: Address/street autocomplete for address delivery
- **Source**: Speedy API (`get_streets()` endpoint)
- **Minimum Characters**: 3
- **Fail-Safe**: Returns empty results if API unavailable (silent fail)
- **Requires**: Site/City ID to be selected first

## New Files Created

### PHP
- `/includes/class-sesh-autocomplete-handler.php`
  - AJAX handler for autocomplete endpoints
  - Handles city, office, and street autocomplete
  - Integrates with Speedy API for street lookups
  - Security: Nonce verification on all endpoints

### JavaScript
- `/assets/js/sesh-autocomplete.js`
  - Frontend autocomplete module
  - Select2 integration with AJAX
  - Debouncing (300ms delay)
  - Fallback methods for non-Select2 implementations

## Modified Files

### PHP Classes
1. `/includes/class-sesh-plugin.php`
   - Added autocomplete handler initialization
   - Loads handler on frontend and AJAX requests

2. `/includes/class-sesh-frontend.php`
   - Enqueues autocomplete JavaScript module
   - Adds autocomplete configuration to checkout params
   - Sets dependency order: autocomplete → location-selector → checkout

3. `/includes/class-sesh-cart-calculator.php`
   - Enqueues autocomplete module on cart page
   - Adds autocomplete nonce to cart config

### Database Queries
The existing `SESH_Database` class already provides search methods:
- `search_sites($carrier, $term, $limit)` - Search cities
- `search_offices($carrier, $term, $limit)` - Search offices
- Uses `LIKE` queries with proper escaping via `$wpdb->esc_like()`

## AJAX Endpoints

### 1. City Autocomplete
**Action**: `sesh_autocomplete_cities`
**Method**: GET
**Parameters**:
- `carrier` (speedy|econt)
- `term` (search string, min 2 chars)
- `nonce` (sesh_frontend_nonce)

**Response**:
```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": 123,
        "text": "Sofia",
        "region": "Sofia City",
        "municipality": "Sofia",
        "post_code": "1000"
      }
    ]
  }
}
```

### 2. Office Autocomplete
**Action**: `sesh_autocomplete_offices`
**Method**: GET
**Parameters**:
- `carrier` (speedy|econt)
- `term` (search string, min 2 chars)
- `city` (optional city filter)
- `nonce` (sesh_frontend_nonce)

**Response**:
```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": 456,
        "text": "№456, Office Name (123 Main St)",
        "name": "Office Name",
        "address": "123 Main St",
        "city": "Sofia"
      }
    ]
  }
}
```

### 3. Street Autocomplete
**Action**: `sesh_autocomplete_streets`
**Method**: GET
**Parameters**:
- `term` (search string, min 3 chars)
- `site_id` (city/site ID)
- `nonce` (sesh_frontend_nonce)

**Response**:
```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": 789,
        "text": "Main Street",
        "type": "ul."
      }
    ]
  }
}
```

**Note**: Returns empty results if Speedy API is unavailable (fail-safe).

### 4. Combined Locations (Cities + Offices)
**Action**: `sesh_autocomplete_locations`
**Method**: GET
**Parameters**:
- `carrier` (speedy|econt)
- `term` (search string, min 2 chars)
- `nonce` (sesh_frontend_nonce)

**Response**: Combined city and office results with type indicator.

## Security Features

### Input Sanitization
- All `$_GET` and `$_POST` data sanitized with `sanitize_text_field()`
- Carrier validation: Only 'speedy' or 'econt' allowed
- Integer IDs sanitized with `absint()`

### Nonce Verification
- All AJAX endpoints verify `sesh_frontend_nonce`
- Cart calculator uses `sesh_cart_calculator` nonce

### SQL Injection Prevention
- All database queries use `$wpdb->prepare()`
- Search terms escaped with `$wpdb->esc_like()`

### Error Handling
- API failures return empty results (silent fail)
- Errors logged to WooCommerce logger
- No sensitive information exposed to frontend

## Performance Optimizations

### Debouncing
- JavaScript debounce: 300ms delay on typing
- Reduces API/DB calls during rapid typing

### Caching
- Select2 built-in AJAX cache enabled
- Database queries benefit from WordPress object cache
- Speedy API responses cached via database cache table

### Query Limits
- City search: Max 20 results
- Office search: Max 20 results
- Prevents large result sets from slowing down UI

## Browser Compatibility
- Requires jQuery (bundled with WordPress)
- Requires Select2 (loaded via WooCommerce)
- Modern browsers (ES5+ compatible)

## Testing Checklist

### Cart Page
- [ ] City autocomplete shows results
- [ ] Office autocomplete shows results (if applicable)
- [ ] Minimum character requirements enforced
- [ ] Results filtered by carrier selection
- [ ] Free shipping threshold displays correctly

### Checkout Page
- [ ] Speedy city autocomplete works
- [ ] Econt city autocomplete works
- [ ] Office autocomplete filters by selected city
- [ ] Street autocomplete queries Speedy API
- [ ] Address delivery fields populate correctly
- [ ] Fails gracefully if API is down

### Security
- [ ] Nonces verified on all AJAX requests
- [ ] Invalid carrier values rejected
- [ ] SQL injection prevented (prepared statements)
- [ ] No unauthorized data access

### Performance
- [ ] Autocomplete debounces user input
- [ ] No excessive API calls
- [ ] Results load within 1 second
- [ ] No JavaScript errors in console

## Known Limitations

1. **Street Autocomplete**: Only available for Speedy (Econt API may not support street lookup)
2. **Minimum Input**: Requires 2-3 characters to trigger search
3. **API Dependency**: Street autocomplete depends on Speedy API availability
4. **Language**: Currently Bulgarian only (can be extended via translations)

## Future Enhancements

1. Add geolocation-based office suggestions
2. Support for Econt street autocomplete if API becomes available
3. Autocomplete for Bulgarian Cyrillic and Latin transliteration
4. Cache popular search results in localStorage
5. Add office working hours to autocomplete results

## Migration Notes

### Legacy Code
- Does NOT modify `js.php` (inline JavaScript) - follows migration guidelines
- Does NOT use global variables - all data passed via `wp_localize_script()`
- New OOP architecture with dependency injection

### Backward Compatibility
- Existing dropdown functionality preserved
- Autocomplete is progressive enhancement
- Falls back to standard Select2 if autocomplete fails

## Version Info
- **Feature Version**: 2.1.0
- **WordPress**: 6.0+
- **WooCommerce**: 8.0+
- **PHP**: 7.4+
