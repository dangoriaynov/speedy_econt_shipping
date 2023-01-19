<?php

/*
 * Plugin Name:       Speedy and Econt Shipping
 * Description:       Adds Speedy and Econt shipping methods along with their delivery options.
 * Author:            Dan Goriaynov
 * Author URI:        https://github.com/dangoriaynov
 * Plugin URI:        https://github.com/dangoriaynov/speedy_econt_shipping
 * Version:           1.6
 * WC tested up to:   6.1
 * License:           GNU General Public License, version 2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.en.html
 * Domain Path:       /languages/
 * Text Domain:       speedy_econt_shipping
*/


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}

require 'api.php';
require 'db.php';
require 'js.php';
require 'css.php';

global $keepCase, $isUpgradeInProgress;

$keepCase = ['Столица' => 'столица', 'Ул.' => 'ул.', 'Ту ' => 'ТУ '];
$isUpgradeInProgress = false;

function seshInsertSpeedyTableData(): bool
{
    global $keepCase, $speedy_sites_table, $speedy_offices_table;
    $officesAdded = 0;
    $speedy_sites_added = array();
    $offices = seshApiSpeedyOfficesList();
    foreach ($offices as $id => $value) {
        if (! $value['is_open']) {
            continue;  # skip closed offices
        }
        $name = seshConvertCase($value['name']);

        $site_id = $value['site_id'];
        $address = $value['address'];
        if (in_array($site_id, $speedy_sites_added)) {
            $site_name = $speedy_sites_added[$site_id];
        } else {
            $site = seshApiSpeedySitesList($site_id);
            $site_name = seshConvertCase($site['name'], $keepCase);
            $site_region = seshConvertCase($site['region'], $keepCase);
            // align the values between to-office and to-address delivery options
            if ($site_region === 'София') {
                $site_region = 'София Област';
            } else if ($site_region === 'София (столица)') {
                $site_region = 'София';
            }
            $site_municipality = seshConvertCase($site['municipality'], $keepCase);
            seshInsertSpeedySite($site_id, $site_name, $site_region, $site_municipality);
            $speedy_sites_added[$site_id] = $site_name;
        }
        $officesAdded += 1;
        seshInsertSpeedyOffice($id, $name, $site_name, $address);
    }

    $sitesDB = seshReadTableData($speedy_sites_table);
    $officesDB = seshReadTableData($speedy_offices_table);
    // this way we prevent cleaning up the table when API request returned empty result
    return sizeof($speedy_sites_added) / (sizeof($sitesDB) + 0.1) >= 0.8 &&
        $officesAdded / (sizeof($officesDB) + 0.1) >= 0.8;
}

function seshInsertEcontTableData(): bool
{
    global $keepCase, $econt_sites_table, $econt_offices_table;
    $officesAddedAmt = 0;
    $econt_sites_added = array();
    $sites_with_offices = array();
    $cities = seshApiEcontSitesList();
    $offices = seshApiEcontOfficesList();
    foreach ($cities as $city_id => $city_data) {
        $city_name = seshConvertCase($city_data['name'], $keepCase);
        $econt_sites_added[$city_id] = $city_name;
    }
    foreach ($offices as $office_id => $office_data) {
        if (! $office_data['is_open']) {
            continue;  # skip closed offices
        }
        $site_id = $office_data['site_id'];
        $site_name = $econt_sites_added[$site_id];
        $sites_with_offices[] = $site_id;
        $office_name = seshConvertCase($office_data['name'], $keepCase);
        $office_address = seshConvertCase($office_data['address'], $keepCase);
        $officesAddedAmt += 1;
        seshInsertEcontOffice($office_id, $office_name, $site_name, $office_address);
    }
    foreach ($cities as $city_id => $city_data) {
        // skip sites which do not have offices in them
        if (! in_array($city_id, $sites_with_offices) ) {
            continue;
        }
        $city_name = seshConvertCase($city_data['name'], $keepCase);
        $region_name = seshConvertCase($city_data['region'], $keepCase);
        seshInsertEcontSite($city_id, $city_name, $region_name);
    }

    $sitesDB = seshReadTableData($econt_sites_table);
    $officesDB = seshReadTableData($econt_offices_table);
    // this way we prevent cleaning up the table when API request returned empty result
    return sizeof($econt_sites_added) / (sizeof($sitesDB) + 0.1) >= 0.9 &&
        $officesAddedAmt / (sizeof($officesDB) + 0.1) >= 0.9;
}

function seshGenerateJsVar($sitesTable, $officesTable, $varName) {
    $sitesDB = seshReadTableData($sitesTable, "name");
    $officesDB = seshReadTableData($officesTable, "name");
    $data = array();
    // iterate over all elements in the sites DB table
    foreach ($sitesDB as $siteDB) {
        $offices = array();
        // iterate over all elements in the offices DB table
        foreach ($officesDB as $officeDB) {
            if ($officeDB->city === $siteDB->name) {
                $offices[] = array('id'=> $officeDB->id, 'name' => $officeDB->name, 'address' => $officeDB->address);
            }
        }
        // on empty offices list
        if (count($offices) === 0) {
            // do not store them and continue to the next iteration
            continue;
        }
        $citiesExisting = null;
        // check whether we have an existing entry for the current region
        foreach($data as $regionName => $regionCities) {
            if ($regionName === $siteDB->region) {
                $citiesExisting = $regionCities;
                break;
            }
        }
        // add new if no existing entry for the current region
        if (! $citiesExisting) {
            $citiesExisting = array();
            $data[$siteDB->region] = $citiesExisting;
        }
        $cityObj = array('id' => $siteDB->id, 'name' => $siteDB->name,
            'municipality' => $siteDB->municipality, 'offices' => $offices);
        $citiesExisting[] = $cityObj;
        // update the region value
        $data[$siteDB->region] = $citiesExisting;
    }
    //ksort($data);
    setlocale(LC_COLLATE, 'bg_BG.utf8');
    uksort($data,'strcoll');
    ?><script>
        const <?php echo $varName.'='.json_encode($data); ?>;
    </script><?php
}

function seshRefreshDeliveryTables() {
    global $isUpgradeInProgress;
    if ($isUpgradeInProgress) {
        return;
    }
    $isUpgradeInProgress = true;
    try {
        // preliminary table clean-up
        seshTruncateTables();
        // insert offices/sites data as preliminary one
        if (!seshInsertSpeedyTableData() or !seshInsertEcontTableData()) {
            return;
        }
        // clear production data from the destination tables
        seshTruncateTables(true);
        // mark newly inserted data as production one
        seshMarkDataAsProd();
    } finally {
        $isUpgradeInProgress = false;
    }
}

function isEmptyAnyOfTables($tables): bool
{
    $hasEmptyTable = false;
    foreach ($tables as $table) {
        $hasEmptyTable = $hasEmptyTable || count(seshReadTableData($table)) === 0;
    }
    return $hasEmptyTable;
}

function seshPrintCheckoutPageData() {
    // works only on 'checkout' page
    if (! (is_page( 'checkout' ) || is_checkout())) {
        return;
    }
    global $speedy_sites_table, $speedy_offices_table, $econt_sites_table, $econt_offices_table;
    seshGenerateJsVar($econt_sites_table, $econt_offices_table, 'econtData');
    seshGenerateJsVar($speedy_sites_table, $speedy_offices_table, 'speedyData');
}
add_action( 'woocommerce_before_checkout_form', 'seshPrintCheckoutPageData', 10 );

function seshSetupDailyRun() {
    if (! wp_next_scheduled( 'seshDailyHook' ) ) {
        wp_schedule_event( strtotime('03:05:00'), 'daily', 'seshDailyHook');
    }
}

function seshSetupEveryMinuteRun() {
    if (! wp_next_scheduled( 'seshEveryMinuteHook' ) ) {
        wp_schedule_event( time(), 'every_minute', 'seshEveryMinuteHook');
    }
}

function seshOnActivate() {
    // this will assure that data is updated at least once per day (at 3AM)
    seshSetupDailyRun();
    // this will assure that we have data populated in tables and will try to populate it every minute
    seshSetupEveryMinuteRun();
    // attempt to populate tables with the settings we currently have
    seshRefreshDeliveryTables();
}

function seshOnActivateOnEmpty() {
    global $speedy_sites_table, $speedy_offices_table, $econt_sites_table, $econt_offices_table;
    if (! isEmptyAnyOfTables(array($econt_sites_table, $econt_offices_table, $speedy_sites_table, $speedy_offices_table))) {
        // this logic will work only once after the plugin activation since we don't want to spam the APIs
        wp_clear_scheduled_hook( 'seshEveryMinuteHook' );
        return;
    }
    seshOnActivate();
}
add_action( 'seshActivationHook', 'seshOnActivate' );
add_action( 'seshEveryMinuteHook', 'seshOnActivateOnEmpty' );
add_action( 'seshDailyHook', 'seshOnActivate' );

function seshFillInitialData() {
    seshCreateTables();
    wp_schedule_single_event( time(), 'seshActivationHook' );
}
register_activation_hook( __FILE__, 'seshFillInitialData' );

function seshDeactivateDailyDataRefresh() {
    wp_clear_scheduled_hook( 'seshDailyHook' );
    wp_clear_scheduled_hook( 'seshEveryMinuteHook' );
    wp_clear_scheduled_hook( 'seshActivationHook' );
}
register_deactivation_hook( __FILE__, 'seshDeactivateDailyDataRefresh' );

function seshGetRegions($table): array
{
    $sitesDB = seshReadTableData($table, "name");
    $regions = array();
    foreach ($sitesDB as $siteDB) {
        $regions[$siteDB->region] = $siteDB->region;
    }
    return $regions;
}

function sesh_custom_override_checkout_fields($fields ): array
{
    global $speedy_sites_table, $speedy_region_id, $speedy_city_id, $speedy_office_id,
           $econt_sites_table, $econt_region_id, $econt_city_id, $econt_office_id, $shipping_to_id;

    $fields['billing']['billing_email']['required'] = false;
    $fields['billing']['billing_email']['priority'] = '22';
    $fields['billing']['billing_postcode']['required'] = false;
    $fields['billing']['billing_phone']['priority'] = '25';

    $shippingOptions = array();
    foreach (seshDelivOptions() as $delivOpt) {
        $shippingOptions[$delivOpt['name']] = $delivOpt['label'];
    }
    $fields['billing'][$shipping_to_id] = array(
        'priority' => '55',
        'type' => 'radio',
        'class' => array('form-row-wide', 'address-field'),
        'label' => __('Delivery to', 'speedy_econt_shipping'),
        'required' => true,
        'options' => $shippingOptions
    );
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_address_2']);

    $regions = seshGetRegions($speedy_sites_table);
    $fields['billing'][$speedy_region_id] = array(
        'priority' => '105',
        'type' => 'select',
        'class' => array('form-row-first', 'address-field'),
        'label' => __('Region', 'speedy_econt_shipping'),
        'required'  => false,
        'options' => count($regions) > 0 ? ($regions) : (array(__('nothing loaded', 'speedy_econt_shipping'))),
        'placeholder' => __('Make your choice', 'speedy_econt_shipping')
    );
    $fields['billing'][$speedy_city_id] = array(
        'priority' => '110',
        'type' => 'select',
        'class' => array('form-row-last', 'address-field'),
        'label' => __('City', 'speedy_econt_shipping'),
        'required'  => false,
        'options' => array(__('nothing loaded', 'speedy_econt_shipping')),
        'placeholder' => __('Choose region first', 'speedy_econt_shipping')
    );
    $fields['billing'][$speedy_office_id] = array(
        'priority' => '120',
        'type' => 'select',
        'class' => array('form-row-wide', 'address-field'),
        'label' => __('Office', 'speedy_econt_shipping'),
        'required'  => false,
        'options' => array(__('nothing loaded', 'speedy_econt_shipping'))
    );

    $regions = seshGetRegions($econt_sites_table);
    $fields['billing'][$econt_region_id] = array(
        'priority' => '130',
        'type' => 'select',
        'class' => array('form-row-first', 'address-field'),
        'label' => __('Region', 'speedy_econt_shipping'),
        'required'  => false,
        'options' => count($regions) > 0 ? ($regions) : (array(__('nothing loaded', 'speedy_econt_shipping'))),
        'placeholder' => __('Make your choice', 'speedy_econt_shipping')
    );
    $fields['billing'][$econt_city_id] = array(
        'priority' => '140',
        'type' => 'select',
        'class' => array('form-row-last', 'address-field'),
        'label' => __('City', 'speedy_econt_shipping'),
        'required'  => false,
        'options' => array(__('nothing loaded', 'speedy_econt_shipping')),
        'placeholder' => __('Choose region first', 'speedy_econt_shipping')
    );
    $fields['billing'][$econt_office_id] = array(
        'priority' => '150',
        'type' => 'select',
        'class' => array('form-row-wide', 'address-field'),
        'label' => __('Office', 'speedy_econt_shipping'),
        'required'  => false,
        'options' => array(__('nothing loaded', 'speedy_econt_shipping'))
    );
    return $fields;
}
add_filter( 'woocommerce_checkout_fields' , 'sesh_custom_override_checkout_fields' );

function sesh_custom_override_address_fields($fields): array
{
    $fields['state']['priority'] = '60';
    $fields['state']['class'] = array('form-row-first', 'address-field');
    $fields['city']['priority'] = '70';
    $fields['city']['class'] = array('form-row-last', 'address-field');
    $fields['address_1']['priority'] = '80';
    return $fields;
}
add_filter( 'woocommerce_default_address_fields', 'sesh_custom_override_address_fields' );

function sesh_custom_checkout_field_process() {
    global $shipping_to_id, $econt_region_id, $econt_city_id, $econt_office_id, $speedy_region_id, $speedy_city_id, $speedy_office_id;
    $shippingMethod = sanitize_text_field($_POST[$shipping_to_id]);
    if (! $shippingMethod) {
        wc_add_notice( __( 'Delivery method was not chosen. Please choose one.', 'speedy_econt_shipping' ), 'error' );
    }
    if ($shippingMethod === seshDelivOptions()['econt']['name']) {
        if (! sanitize_text_field($_POST[$econt_region_id]) || ! sanitize_text_field($_POST[$econt_city_id]) || ! sanitize_text_field($_POST[$econt_office_id])) {
            wc_add_notice( __( 'Delivery details were not populated. Please fill them in.', 'speedy_econt_shipping' ), 'error' );
        }
    } else if ($shippingMethod === seshDelivOptions()['speedy']['name']) {
        if (! sanitize_text_field($_POST[$speedy_region_id]) || ! sanitize_text_field($_POST[$speedy_city_id]) || ! sanitize_text_field($_POST[$speedy_office_id])) {
            wc_add_notice( __( 'Delivery details were not populated. Please fill them in.', 'speedy_econt_shipping' ), 'error' );
        }
    }
}
add_action( 'woocommerce_checkout_process', 'sesh_custom_checkout_field_process' );

function sesh_i10n_load() {
    load_plugin_textdomain( 'speedy_econt_shipping', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'sesh_i10n_load', 0 );

function sesh_hide_shipping_fields($needs_address, $hide, $order ): bool
{
    return false;
}
add_filter( 'woocommerce_order_needs_shipping_address', 'sesh_hide_shipping_fields', 10, 3 );

/* remove shipping column from emails */
add_filter( 'woocommerce_get_order_item_totals', 'sesh_customize_email_order_line_totals', 1000, 3 );
function sesh_customize_email_order_line_totals($total_rows, $order, $tax_display ){
    if( ! is_wc_endpoint_url() || ! is_admin() ) {
        unset($total_rows['shipping']);
    }
    return $total_rows;
}

/* Add a link to the settings page on the plugins.php page. */
function add_settings_page_link( $links ): array
{
    $links = array_merge( array(
        '<a href="' . esc_url( admin_url( '/options-general.php?page=speedy-econt-shipping' ) ) . '">' . __( 'Settings', 'speedy_econt_shipping' ) . '</a>'
    ), $links );
    return $links;
}
add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'add_settings_page_link' );