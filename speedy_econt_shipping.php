<?php

/*
Plugin Name: Speedy and Econt Shipping
Plugin URI: https://github.com/dangoriaynov/speedy_econt_shipping
Description: Adds Speedy and Econt shipping methods along with their delivery options.
Version: 0.1
Author: Dan Goriaynov
Author URI: http://dobavki.club
License: MIT License
*/

require 'api.php';
require 'db.php';
require 'js.php';
require 'css.php';

global $speedy_region_sel, $speedy_city_sel, $speedy_office_sel, $econt_region_sel, $econt_city_sel, $econt_office_sel, $keepCase;

$keepCase = ['Столица' => 'столица', 'Ул.' => 'ул.', 'Ту ' => 'ТУ '];

function insertSpeedyTableData() {
    global $keepCase;
    $speedy_sites_added = array();
    $offices = apiSpeedyOfficesList();
    foreach ($offices as $id => $value) {
        $name = convertCase($value['name']);

        $site_id = $value['site_id'];
        $address = $value['address'];
        if (in_array($site_id, $speedy_sites_added)) {
            $site_name = $speedy_sites_added[$site_id];
        } else {
            $site = apiSpeedySitesList($site_id);
            $site_name = convertCase($site['name'], $keepCase);
            $site_region = convertCase($site['region'], $keepCase);
            $site_municipality = convertCase($site['municipality'], $keepCase);
            insertSpeedySite($site_id, $site_name, $site_region, $site_municipality);
            $speedy_sites_added[$site_id] = $site_name;
        }
        insertSpeedyOffice($id, $name, $site_name, $address);
    }
}

function insertEcontTableData() {
    global $keepCase;
    $econt_sites_added = array();
    $cities = apiEcontSitesList();
    foreach ($cities as $city_id => $city_data) {
        $city_name = convertCase($city_data['name'], $keepCase);
        $econt_sites_added[$city_id] = $city_name;
        $region_name = convertCase($city_data['region'], $keepCase);
        insertEcontSite($city_id, $city_name, $region_name);
    }
    $offices = apiEcontOfficesList();
    foreach ($offices as $office_id => $office_data) {
        $site_id = $office_data['site_id'];
        $site_name = $econt_sites_added[$site_id];
        $office_name = convertCase($office_data['name'], $keepCase);
        $office_address = convertCase($office_data['address'], $keepCase);
        insertEcontOffice($office_id, $office_name, $site_name, $office_address);
    }
}

function generateJsVar($sitesTable, $officesTable, $varName) {
    $sitesDB = readTableData($sitesTable, "name");
    $officesDB = readTableData($officesTable, "name");
    $data = array();
    // iterate over all elements in the sites DB table
    foreach ($sitesDB as $siteDB) {
        $offices = array();
        // iterate over all elements in the offices DB table
        foreach ($officesDB as $officeDB) {
            if ($officeDB->city === $siteDB->name) {
                $officeObj = array('id'=> $officeDB->id, 'name' => $officeDB->name, 'address' => $officeDB->address);
                array_push($offices, $officeObj);
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
        array_push($citiesExisting, $cityObj);
        // update the region value
        $data[$siteDB->region] = $citiesExisting;
    }
    ?><script>
        const <?php ksort($data); echo $varName.'='.json_encode($data); ?>;
    </script><?php
}

function refreshDeliveryTables() {
    // insert offices/sites data as preliminary one
    insertSpeedyTableData();
    insertEcontTableData();
    // clear production data from the destination tables
    truncateTables(true);
    // mark newly inserted data as production one
    markDataAsProd();
}

function printPluginData() {
    global $speedy_sites_table, $speedy_offices_table, $econt_sites_table, $econt_offices_table;
//    fillInitialData();
    generateJsVar($econt_sites_table, $econt_offices_table, 'econtData');
    generateJsVar($speedy_sites_table, $speedy_offices_table, 'speedyData');
}

function setupDailyDataRefresh() {
    if ( !wp_next_scheduled( 'refreshDeliveryTables' ) ) {
        wp_schedule_event( time(), 'daily', 'refreshDeliveryTables');
    }
}

function getRegions($table) {
    $sitesDB = readTableData($table, "name");
    $regions = array();
    foreach ($sitesDB as $siteDB) {
        $regions[$siteDB->region] = $siteDB->region;
    }
    return $regions;
}

function fillInitialData() {
    createTables();
    refreshDeliveryTables();
}

function custom_override_checkout_fields( $fields ): array
{
    global $speedy_sites_table, $speedy_region_id, $speedy_city_id, $speedy_office_id,
           $econt_sites_table, $econt_region_id, $econt_city_id, $econt_office_id, $delivOpts, $shipping_to_id;

    $fields['billing']['billing_email']['required'] = false;
    $fields['billing']['billing_email']['priority'] = '22';
    $fields['billing']['billing_postcode']['required'] = false;
    $fields['billing']['billing_phone']['priority'] = '25';

    $shippingOptions = array();
    foreach ($delivOpts as $delivOpt) {
        $shippingOptions[$delivOpt['name']] = $delivOpt['label'];
    }
    $fields['billing'][$shipping_to_id] = array(
        'priority' => '55',
        'type' => 'radio',
        'class' => array('form-row-wide', 'address-field'),
        'label' => 'Доставка до',
        'required' => true,
        'options' => $shippingOptions
    );
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_address_2']);

    $regions = getRegions($speedy_sites_table);
    $fields['billing'][$speedy_region_id] = array(
        'priority' => '100',
        'type' => 'select',
        'class' => array('form-row-first', 'address-field'),
        'label' => 'Област',
        'required'  => false,
        'options' => $regions,
        'placeholder' => 'Изберете опцията'
    );
    $fields['billing'][$speedy_city_id] = array(
        'priority' => '110',
        'type' => 'select',
        'class' => array('form-row-last', 'address-field'),
        'label' => 'Град',
        'required'  => false,
        'options' => array('няма заредени'),
        'placeholder' => 'Първо изберете област'
    );
    $fields['billing'][$speedy_office_id] = array(
        'priority' => '120',
        'type' => 'select',
        'class' => array('form-row-wide', 'address-field'),
        'label' => 'Офис',
        'required'  => false,
        'options' => array('няма заредени')
    );

    $regions = getRegions($econt_sites_table);
    $fields['billing'][$econt_region_id] = array(
        'priority' => '130',
        'type' => 'select',
        'class' => array('form-row-first', 'address-field'),
        'label' => 'Област',
        'required'  => false,
        'options' => $regions,
        'placeholder' => 'Изберете опцията'
    );
    $fields['billing'][$econt_city_id] = array(
        'priority' => '140',
        'type' => 'select',
        'class' => array('form-row-last', 'address-field'),
        'label' => 'Град',
        'required'  => false,
        'options' => array('няма заредени'),
        'placeholder' => 'Първо изберете област'
    );
    $fields['billing'][$econt_office_id] = array(
        'priority' => '150',
        'type' => 'select',
        'class' => array('form-row-wide', 'address-field'),
        'label' => 'Офис',
        'required'  => false,
        'options' => array('няма заредени')
    );
    return $fields;
}

function custom_override_address_fields($fields) {
    $fields['state']['priority'] = '60';
    $fields['state']['class'] = array('form-row-first', 'address-field');
    $fields['city']['priority'] = '70';
    $fields['city']['class'] = array('form-row-last', 'address-field');
    $fields['address_1']['priority'] = '80';
    return $fields;
}

add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );
add_filter( 'woocommerce_default_address_fields', 'custom_override_address_fields' );

add_action('wp', 'setupDailyDataRefresh');
add_action('woocommerce_before_checkout_form', 'printPluginData', 10 );

register_activation_hook( __FILE__, 'fillInitialData' );