<?php

/*
 * Plugin Name:       Speedy and Econt Shipping
 * Description:       Adds Speedy and Econt shipping methods along with their delivery options.
 * Author:            Dan Goriaynov
 * Author URI:        https://github.com/dangoriaynov
 * Plugin URI:        https://github.com/dangoriaynov/speedy_econt_shipping
 * Version:           1.13.0
 * WC tested up to:   6.2
 * License:           GNU General Public License, version 2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.en.html
 * Domain Path:       /languages/
 * Text Domain:       speedy_econt_shipping
*/

// TODO add links to https://www.speedy.bg/bg/speedy-offices and https://www.econt.com/find-office


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}

require 'api.php';
require 'db.php';
require 'js.php';
require 'css.php';

global $keepCase;
$keepCase = ['Столица' => 'столица', 'Ул.' => 'ул.', 'Ту ' => 'ТУ '];
global $globalVarsGenerated;
$globalVarsGenerated = [];


add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );


function seshGenerateJsVar($sitesTable, $officesTable, $varName, $key) {
    global $globalVarsGenerated;
    if (in_array($varName, $globalVarsGenerated)) {
        // already loaded these values in the page
        return;
    }
    $sitesDB = seshReadTableData($sitesTable, "name");
    $officesDB = seshReadTableData($officesTable, "name");
    if (empty($sitesDB) || empty($officesDB)) {
        $contact = getEmergencyContactData();
        $msg = __('Following delivery method is not working: ', 'speedy_econt_shipping') . '<b>' . $key . '</b>.<br/>' .
            __('Please contact us to place your order and get it fixed', 'speedy_econt_shipping') . (empty($contact) ? '' : ': <b>' . $contact . '</b>');
        wc_add_notice( $msg, 'error' );
    }
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
    $globalVarsGenerated[] = $varName;
    ?><script>
        const <?php echo $varName.'='.json_encode($data); ?>;
    </script><?php
}

function seshInsertSpeedyTableData($forceUpdate = false): bool
{
    global $keepCase, $speedy_sites_table, $speedy_offices_table;
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

    $permit_upgrade = $forceUpdate || seshIsPermitUpgrade($speedy_sites_table, $speedy_offices_table, sizeof($speedy_sites_added), $officesAddedAmt);
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

function seshInsertEcontTableData($forceUpdate = false): bool
{
    global $keepCase, $econt_sites_table, $econt_offices_table;
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
    $permit_upgrade = $forceUpdate || seshIsPermitUpgrade($econt_sites_table, $econt_offices_table, sizeof($econt_sites_added), $officesAddedAmt);
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

function seshIsPermitUpgrade(string $sites_table, string $offices_table, int $sites_added_amt, int $offices_added_amt): bool
{
    $sitesInDB = sizeof(seshReadTableData($sites_table));
    $officesInDB = sizeof(seshReadTableData($offices_table));
    $result = $sites_added_amt > 0 && $offices_added_amt > 0;
    write_log("seshIsPermitUpgrade: table=$sites_table, sites_added=$sites_added_amt, sitesInDB=$sitesInDB, offices_added=$offices_added_amt, officesInDB=$officesInDB, will proceed=$result");
    return $result;
}

function seshInsertOfficesData($key, $forceUpdate = false) : bool {
    global $speedy_opt_key, $econt_opt_key;
    if ($key === $speedy_opt_key) {
        if (! isSpeedyEnabled()) {
            return false;
        }
        try {
            // preliminary table clean-up
            seshTruncateTables($key);
            return seshInsertSpeedyTableData($forceUpdate);
        } catch (Exception $e) {
            write_log('Caught exception: '.$e->getMessage()."\n");
        }
    }
    if ($key === $econt_opt_key) {
        if (! isEcontEnabled()) {
            return false;
        }
        try {
            // preliminary table clean-up
            seshTruncateTables($key);
            return seshInsertEcontTableData($forceUpdate);
        } catch (Exception $e) {
            write_log('Caught exception: '.$e->getMessage()."\n");
        }
    }
    return false;
}

function seshRefreshTableDataAll($forceUpdate = false) {
    global $speedy_opt_key, $econt_opt_key;
    write_log('Scheduled Speedy and Econt data refresh');
    seshRefreshTableData(array($speedy_opt_key, $econt_opt_key), $forceUpdate);
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

function seshRefreshTableData($keys, $forceUpdate = false) {
    if (count($keys) === 0) {
        return;
    }
    if (get_transient('sesh_upgrade_running')) {
        write_log("seshRefreshTableData: cancelling new upgrade since it it already running");
        return;
    }
    try {
        set_transient('sesh_upgrade_running', true, 10 * MINUTE_IN_SECONDS);
        foreach ($keys as $key) {
            if (! seshInsertOfficesData($key, $forceUpdate) ) {
                write_log("seshRefreshTableData: Didn't insert any data for $key");
                continue;
            }
            seshTruncateTables($key, true);
            seshMarkDataAsProd($key);
            // do the actual DB operations for each delivery method we have enabled
            executeQueries();
        }
    } finally {
        // rollback all the pending DB operations
        clearQueries();
        set_transient('sesh_upgrade_running', true, MINUTE_IN_SECONDS);
    }
}

function seshPrintCheckoutPageData() {
    // works only on 'checkout' page
    if (! (is_page( 'checkout' ) || is_checkout())) {
        return;
    }
    global $speedy_sites_table, $speedy_offices_table, $econt_sites_table, $econt_offices_table;
    if (isSpeedyEnabled()) {
        seshGenerateJsVar($speedy_sites_table, $speedy_offices_table, 'speedyData', getSpeedyLabel());
    }
    if (isEcontEnabled()) {
        seshGenerateJsVar($econt_sites_table, $econt_offices_table, 'econtData', getEcontLabel());
    }
}
add_action( 'woocommerce_before_checkout_form', 'seshPrintCheckoutPageData', 10 );

function seshSetupDailyRun() {
    if (! wp_next_scheduled( 'seshDailyDbHook' ) ) {
        write_log("seshDailyDbHook scheduled");
        wp_schedule_event( strtotime('03:05:00'), 'daily', 'seshDailyDbHook');
    }
}

add_action( 'seshEcontUpdateDbHook', 'seshRefreshEcontData' );
add_action( 'seshSpeedyEcontUpdateDbHook', 'seshRefreshTableDataAll' );
add_action( 'seshDailyDbHook', 'seshRefreshTableDataAll' );

function seshScheduleImmediateDataRefresh() {
    $hookName = '';
    if (isSpeedyEnabled() && !empty(getSpeedyUser()) && !empty(getSpeedyPass())) {
        $hookName = 'seshSpeedyEcontUpdateDbHook';
    } else if (isEcontEnabled()) {
        $hookName = 'seshEcontUpdateDbHook';
    }
    if ($hookName != '' && ! wp_next_scheduled( $hookName ) ) {
        write_log($hookName." scheduled");
        wp_schedule_single_event(time(), $hookName);
    }
}

function seshFillInitialData() {
    write_log("seshFillInitialData started");
    global $sesh_db_version;
    if (get_site_option( 'sesh_db_version' ) != $sesh_db_version) {
        seshDropTables();
    }
    seshCreateTables();
    seshScheduleImmediateDataRefresh();
    seshSetupDailyRun();  // do the tables re-population every day
}
register_activation_hook( __FILE__, 'seshFillInitialData' );

function seshDeactivateDailyDataRefresh() {
    wp_clear_scheduled_hook( 'seshEcontUpdateDbHook' );
    wp_clear_scheduled_hook( 'seshSpeedyEcontUpdateDbHook' );
    wp_clear_scheduled_hook( 'seshDailyDbHook' );
    seshDropTables();
    write_log("hooks deactivated");
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

function sesh_custom_override_checkout_fields($fields): array
{
    global $speedy_sites_table, $speedy_region_id, $speedy_city_id, $speedy_office_id,
           $econt_sites_table, $econt_region_id, $econt_city_id, $econt_office_id, $shipping_to_id;

    $fields['billing']['billing_email']['required'] = isEmailRequired();
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
    global $shipping_to_id, $econt_region_id, $econt_city_id, $econt_office_id, $speedy_region_id, $speedy_city_id,
           $speedy_office_id;
    $shippingMethod = sanitize_text_field($_POST[$shipping_to_id]);
    if (! $shippingMethod) {
        wc_add_notice( __( 'Delivery method was not chosen. Please choose one.', 'speedy_econt_shipping' ), 'error' );
    }
    if (isAddressValidationNeeded()) {
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

if (! isCalculateFinalPrice()) {
    /* remove shipping column from emails since we do not care about the shipping costs and do not want ot see them in the order totals */
    function sesh_customize_email_order_line_totals($total_rows, $order, $tax_display ){
        if( ! is_wc_endpoint_url() || ! is_admin() ) {
            unset($total_rows['shipping']);
        }
        return $total_rows;
    }
    add_filter( 'woocommerce_get_order_item_totals', 'sesh_customize_email_order_line_totals', 1000, 3 );
}

if (isCalculateFinalPrice()) {
    // add virtual delivery method with the shipping price included in the final order price
    function customise_shipping_charges($order_id, $posted_data, $order ){
        if( is_wc_endpoint_url() || is_admin() ) {
            return $posted_data;
        }
        $shipping_address = $order->get_shipping_address_1();
        $to_speedy = str_contains($shipping_address, getSpeedyLabel());
        $to_econt = str_contains($shipping_address, getEcontLabel());
        $order_total = $order->get_total();
        write_log("speedy label: ".getSpeedyLabel().", econt label: ".getEcontLabel());
        write_log("shipping address: $shipping_address; to speedy=$to_speedy; to econt=$to_econt; order total=$order_total");
        $delivery_price = 0;
        if ($to_speedy) {
            if ($order_total < getSpeedyFreeFrom()) {
                $delivery_price = getSpeedyShipping();
            }
            $label = getSpeedyLabel();
        } else if ($to_econt) {
            if ($order_total < getEcontFreeFrom()) {
                $delivery_price = getEcontShipping();
            }
            $label = getEcontLabel();
        } else {
            if ($order_total < getAddressFreeFrom()) {
                $delivery_price = getAddressShipping();
            }
            $label = getAddressLabel();
        }
        write_log("delivery_price: $delivery_price");
        $is_free = $delivery_price === 0;
        $new_shipping = new WC_Order_Item_Shipping();
        $add = $is_free ? __('for free', 'speedy_econt_shipping') : "";
        $new_shipping->set_method_title( "$add (". __('to', 'speedy_econt_shipping') . " $label)" );
        $new_shipping->set_method_id( '' );
        $new_shipping->set_total( $delivery_price );
        $items = (array) $order->get_items('shipping');
        if ( sizeof( $items ) > 0 ) {
            foreach ( $items as $item_id => $item ) {
                $order->remove_item( $item_id );
                write_log("found and removed existing shipping method");
            }
        }
        $order->add_item( $new_shipping );
        $order->calculate_totals();
        $order->save();
        return $posted_data;
    }
    add_action( 'woocommerce_checkout_order_processed', 'customise_shipping_charges',  1, 3  );
}

/* Add a link to the settings page on the plugins.php page. */
function sesh_add_plugin_page_settings_link( $links ): array
{
    return array_merge( array(
        '<a href="' . esc_url( admin_url( '/options-general.php?page=speedy-econt-shipping' ) ) . '">' . __( 'Settings' ) . '</a>'
    ), $links );
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'sesh_add_plugin_page_settings_link');
