<?php

/*
Plugin Name: Speedy and Econt Shipping
Plugin URI: <Github URL goes here>
Description: Adds Speedy and Econt shipping methods along with their delivery options.
Version: 0.1
Author: Dan Goriaynov
Author URI: http://dobavki.club
License: GPL2
*/

require 'api.php';
require 'db.php';
require 'utils.php';
require 'js.php';
require 'css.php';

global $speedy_region_sel, $speedy_city_sel, $speedy_office_sel, $econt_region_sel, $econt_city_sel, $econt_office_sel;
$speedy_region_sel = "#speedy_region_sel";
$speedy_city_sel = "#speedy_city_sel";
$speedy_office_sel = "#speedy_office_sel";
$econt_region_sel = "#econt_region_sel";
$econt_city_sel = "#econt_city_sel";
$econt_office_sel = "#econt_office_sel";

function insertSpeedyTableData() {
    global $added_sites;

    $offices = apiSpeedyOfficesList();
    $keepCase = ['Столица' => 'столица', 'Ул.' => 'ул.', 'Ту ' => 'ТУ '];
    foreach ($offices as $id => $value) {
        $name = convertCase($value['name']);

        $site_id = $value['site_id'];
        $address = $value['address'];
        if (in_array($site_id, $added_sites)) {
            $site_name = $added_sites[$site_id];
        } else {
            $site = apiSpeedySitesList($site_id);
            $site_name = convertCase($site['name'], $keepCase);
            $site_region = convertCase($site['region'], $keepCase);
            $site_municipality = convertCase($site['municipality'], $keepCase);
            insertSpeedySite($site_id, $site_name, $site_region, $site_municipality);
            $added_sites[$site_id] = $site_name;
        }
        insertSpeedyOffice($id, $name, $site_name, $address);
    }
}

function refreshDeliveryTables() {
    truncateTables();
    insertSpeedyTableData();
//    insertEcontTableData();
}

function printSpeedyData() {
    global $speedy_offices_table, $speedy_sites_table;
    $sitesDB = readTableData($speedy_sites_table, "name");
    $officesDB = readTableData($speedy_offices_table, "name");
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
        $citiesExisting = null;
        // check we have existing entry for the current region
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
    ?><script>let speedyData = <?php echo json_encode($data); ?>;</script><?php
}

register_activation_hook( __FILE__, 'createTables' );
register_activation_hook( __FILE__, 'insertInitialData' );

function printPluginData() {
//    createSpeedyTables();
//    insertSpeedyTableData();
    printSpeedyData();
}

function setupDailyDataRefresh() {
    if ( !wp_next_scheduled( 'refreshDeliveryTables' ) ) {
        wp_schedule_event( time(), 'daily', 'refreshDeliveryTables');
    }
}
add_action('wp', 'setupDailyDataRefresh');
add_action('woocommerce_before_checkout_form', 'printPluginData', 10 );