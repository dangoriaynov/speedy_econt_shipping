<?php

/*
 * Plugin Name:       Speedy and Econt Shipping
 * Description:       Adds Speedy and Econt shipping methods along with their delivery options.
 * Author:            Dan Goriaynov
 * Author URI:        https://github.com/dangoriaynov
 * Plugin URI:        https://github.com/dangoriaynov/speedy_econt_shipping
 * Version:           1.8.5
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

global $keepCase;
$keepCase = ['Столица' => 'столица', 'Ул.' => 'ул.', 'Ту ' => 'ТУ '];

class UpgradeRunner {
    public static $isUpgradeInProgress = false;
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
        // add new if no existing entry exists for the current region
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
    setlocale(LC_COLLATE, 'bg_BG.utf8');
    uksort($data,'strcoll');
    ?><script>
        const <?php echo $varName.'='.json_encode($data); ?>;
    </script><?php
}

function seshInsertSpeedyTableData(): bool
{
    global $keepCase, $speedy_sites_table, $speedy_offices_table, $insert_edge;
    $officesAddedAmt = 0;
    $speedy_sites_added = array();
    $offices = seshApiSpeedyOfficesList();
    write_log("seshInsertSpeedyTableData: offices=".count($offices));
    $offices_to_insert = array();
    $sites_to_insert = array();
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
            $sites_to_insert[] = array('id' =>$site_id, 'name' => $site_name, 'region' => $site_region, 'municipality' => $site_municipality);
            $speedy_sites_added[$site_id] = $site_name;
        }
        $officesAddedAmt += 1;
        $offices_to_insert[] = array('id' => $id, 'name' => $name, 'site' => $site_name, 'address' => $address);
    }

    $permit_upgrade = seshIsPermitUpgrade($speedy_sites_table, $speedy_offices_table, $speedy_sites_added, $officesAddedAmt, $insert_edge);
    if ($permit_upgrade) {
        // insert only if we plan to proceed with the upgrade
        foreach ($offices_to_insert as $office) {
            seshInsertSpeedyOffice($office['id'], $office['name'], $office['site'], $office['address']);
        }
        foreach ($sites_to_insert as $site) {
            seshInsertSpeedySite($site['id'], $site['name'], $site['region'], $site['municipality']);
        }
    }
    return $permit_upgrade;
}

function seshInsertEcontTableData(): bool
{
    global $keepCase, $econt_sites_table, $econt_offices_table, $insert_edge;
    $officesAddedAmt = 0;
    $econt_sites_added = array();
    $sites_with_offices = array();
    $cities = seshApiEcontSitesList();
    $offices = seshApiEcontOfficesList();
    write_log("seshInsertEcontTableData: cities=".count($cities).", offices=".count($offices));
    foreach ($cities as $city_id => $city_data) {
        $city_name = seshConvertCase($city_data['name'], $keepCase);
        $econt_sites_added[$city_id] = $city_name;
    }
    $offices_to_insert = array();
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
        $offices_to_insert[] = array('id' => $office_id, 'name' => $office_name, 'site' => $site_name, 'address' => $office_address);
    }
    $sites_to_insert = array();
    foreach ($cities as $city_id => $city_data) {
        // skip sites which do not have offices in them
        if (! in_array($city_id, $sites_with_offices) ) {
            continue;
        }
        $city_name = seshConvertCase($city_data['name'], $keepCase);
        $region_name = seshConvertCase($city_data['region'], $keepCase);
        seshInsertEcontSite($city_id, $city_name, $region_name);
        $sites_to_insert[] = array('id' =>$city_id, 'name' => $city_name, 'region' => $region_name);
    }
    $permit_upgrade = seshIsPermitUpgrade($econt_sites_table, $econt_offices_table, $econt_sites_added, $officesAddedAmt, $insert_edge);
    if ($permit_upgrade) {
        // insert only if we plan to proceed with the upgrade
        foreach ($offices_to_insert as $office) {
            seshInsertEcontOffice($office['id'], $office['name'], $office['site'], $office['address']);
        }
        foreach ($sites_to_insert as $site) {
            seshInsertEcontSite($site['id'], $site['name'], $site['region']);
        }
    }
    return $permit_upgrade;
}

function seshIsPermitUpgrade(string $sites_table, string $offices_table, array $sites_added, int $offices_added_amt, float $insert_edge): bool
{
    $sitesDB = seshReadTableData($sites_table);
    $officesDB = seshReadTableData($offices_table);
    $sitesSize = sizeof($sitesDB) > 0 ? sizeof($sitesDB) : 1;
    $officesSize = sizeof($officesDB) > 0 ? sizeof($officesDB) : 1;
    // this way we prevent cleaning up the tables if smth goes wrong
    $result = sizeof($sites_added) / $sitesSize >= $insert_edge && $offices_added_amt / $officesSize >= $insert_edge;
    write_log("seshIsPermitUpgrade: sites_table=$sites_table, sites_added=".sizeof($sites_added).", DB size=".$sitesSize.", offices_added=$offices_added_amt, DB size=$officesSize, result=$result");
    return $result;
}

function seshInsertOfficesData($key) : bool {
    global $speedy_opt_key, $econt_opt_key;
    if ($key === $speedy_opt_key) {
        if (! isSpeedyEnabled()) {
            return false;
        }
        try {
            return seshInsertSpeedyTableData();
        } catch (Exception $e) {
            write_log('Caught exception: '.$e->getMessage()."\n");
        }
    }
    if ($key === $econt_opt_key) {
        if (! isEcontEnabled()) {
            return false;
        }
        try {
            return seshInsertEcontTableData();
        } catch (Exception $e) {
            write_log('Caught exception: '.$e->getMessage()."\n");
        }
    }
    return false;
}

function seshRefreshTableDataAll() {
    global $speedy_opt_key, $econt_opt_key;
    write_log('Scheduled Speedy and Econt data refresh');
    seshRefreshTableData(array($speedy_opt_key, $econt_opt_key));
}

function seshRefreshSpeedyData() {
    global $speedy_opt_key;
    write_log('Scheduled Speedy data refresh');
    seshRefreshTableData(array($speedy_opt_key));
}

function seshRefreshEcontData() {
    global $econt_opt_key;
    write_log('Scheduled Econt data refresh');
    seshRefreshTableData(array($econt_opt_key));
}

function seshRefreshTableData($keys) {
    if (UpgradeRunner::$isUpgradeInProgress || count($keys) === 0) {
        return;
    }
    try {
        UpgradeRunner::$isUpgradeInProgress = true;
        foreach ($keys as $key) {
            // preliminary table clean-up
            seshTruncateTables($key);
            if (! seshInsertOfficesData($key) ) {
                write_log("Didn't insert any data for $key");
                continue;
            }
            // clear production data from the destination tables
            seshTruncateTables($key, true);
            // mark newly inserted data as production one
            seshMarkDataAsProd($key);
        }
        // do the actual DB operations
        executeQueries();
    } finally {
        // rollback all the pending DB operations
        clearQueries();
        UpgradeRunner::$isUpgradeInProgress = false;
    }
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
    if (! wp_next_scheduled( 'seshDailyDbHook' ) ) {
        wp_schedule_event( strtotime('03:05:00'), 'daily', 'seshDailyDbHook');
    }
}

add_action( 'seshEcontUpdateDbHook', 'seshRefreshEcontData' );
add_action( 'seshSpeedyEcontUpdateDbHook', 'seshRefreshTableDataAll' );
add_action( 'seshDailyDbHook', 'seshRefreshTableDataAll' );

function seshFillInitialData() {
    seshCreateTables();
    if (getStoredOption('enable_speedy_0', false) === true &&
        getStoredOption('speedy_username_0', '') !== '' &&
        getStoredOption('speedy_password_1', '') !== '') {
        wp_schedule_single_event( time(), 'seshSpeedyEcontUpdateDbHook' );  // try to populate all tables
    } else {
        wp_schedule_single_event( time(), 'seshEcontUpdateDbHook' );  // try to populate Econt tables only
    }
    seshSetupDailyRun();  // do the table re-population every day
}
register_activation_hook( __FILE__, 'seshFillInitialData' );

function seshDeactivateDailyDataRefresh() {
    wp_clear_scheduled_hook( 'seshEcontUpdateDbHook' );
    wp_clear_scheduled_hook( 'seshSpeedyEcontUpdateDbHook' );
    wp_clear_scheduled_hook( 'seshDailyDbHook' );
    seshDropTables();
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
function sesh_customize_email_order_line_totals($total_rows, $order, $tax_display ){
    if( ! is_wc_endpoint_url() || ! is_admin() ) {
        unset($total_rows['shipping']);
    }
    return $total_rows;
}
add_filter( 'woocommerce_get_order_item_totals', 'sesh_customize_email_order_line_totals', 1000, 3 );

//add_filter( 'woocommerce_package_rates', 'override_shipping_costs' );
//function override_shipping_costs( $rates ) {
//    foreach( $rates as $rate_key => $rate ){
//        // Check if the shipping method ID is UPS
//        if( ($rate->method_id == 'flexible_shipping_ups') ) {
//            // Set cost to zero
//            $rates[$rate_key]->cost = 0;
//        }
//    }
//    return $rates;
//}

/* Add a link to the settings page on the plugins.php page. */
function sesh_add_plugin_page_settings_link( $links ): array
{
    return array_merge( array(
        '<a href="' . esc_url( admin_url( '/options-general.php?page=speedy-econt-shipping' ) ) . '">' . __( 'Settings' ) . '</a>'
    ), $links );
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'sesh_add_plugin_page_settings_link');
