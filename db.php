<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}

global $jal_db_version, $speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table;
$speedy_offices_table = 'speedy_offices';
$econt_offices_table = 'econt_offices';
$speedy_sites_table = 'speedy_sites';
$econt_sites_table = 'econt_sites';
$jal_db_version = '1.1';


function seshCreateTables() {
    global $wpdb, $jal_db_version, $speedy_offices_table, $econt_offices_table, $speedy_sites_table, $econt_sites_table;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$speedy_sites_table." (
      id mediumint(9) NOT NULL,
      name text NOT NULL,
      region text NOT NULL,
      municipality text NOT NULL,
      is_prod BOOLEAN default FALSE,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$speedy_offices_table." (
      id mediumint(9) NOT NULL,
      name text NOT NULL,
      city text NOT NULL,
      address text NOT NULL,
      is_prod BOOLEAN default FALSE,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$econt_sites_table." (
      id mediumint(9) NOT NULL,
      name text NOT NULL,
      region text NOT NULL,
      municipality text NOT NULL,
      is_prod BOOLEAN default FALSE,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$econt_offices_table." (
      id mediumint(9) NOT NULL,
      name text NOT NULL,
      city text NOT NULL,
      address text NOT NULL,
      is_prod BOOLEAN default FALSE,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql );

    add_option( 'jal_db_version', $jal_db_version );
}

function seshDropTables() {
    global $wpdb, $speedy_offices_table, $econt_offices_table, $speedy_sites_table, $econt_sites_table;

    foreach (array($speedy_sites_table, $speedy_offices_table, $econt_sites_table, $econt_offices_table) as $table_name) {
        $table_name_full = $wpdb->prefix . $table_name;
        $sql = "DROP TABLE IF EXISTS $table_name_full";
        $wpdb->query($sql);
    }
    delete_option("jal_db_version");
}

function seshInsertSite($id, $name, $region, $municipality, $short_table_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . $short_table_name;
    $wpdb->replace(
        $table_name,
        array(
            'id' => $id,
            'name' => trim($name),
            'region' => trim($region),
            'municipality' => trim($municipality)
        )
    );
}

function seshInsertSpeedySite($id, $name, $region, $municipality) {
    global $speedy_sites_table;
    seshInsertSite($id, $name, $region, $municipality, $speedy_sites_table);
}

function seshInsertEcontSite($id, $name, $region) {
    global $econt_sites_table;
    seshInsertSite($id, $name, $region, '', $econt_sites_table);
}

function seshInsertOffice($id, $name, $city, $address, $short_table_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . $short_table_name;
    $wpdb->replace(
        $table_name,
        array(
            'id' => $id,
            'name' => trim($name),
            'city' => trim($city),
            'address' => trim($address)
        )
    );
}

function seshInsertSpeedyOffice($id, $name, $city, $address) {
    global $speedy_offices_table;
    seshInsertOffice($id, $name, $city, $address, $speedy_offices_table);
}

function seshInsertEcontOffice($id, $name, $city, $address) {
    global $econt_offices_table;
    seshInsertOffice($id, $name, $city, $address, $econt_offices_table);
}

function dbTablesArray() {
    global $speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table;
    $table_names = array();
    if (isSpeedyEnabled()) {
        $table_names = array_merge($table_names, array($speedy_offices_table, $speedy_sites_table));
    }
    if (isEcontEnabled()) {
        $table_names = array_merge($table_names, array($econt_offices_table, $econt_sites_table));
    }
    return $table_names;
}

// $is_prod is the way to avoid cleaning up the tables before we actually get the new values from the API
// this way we do the following:
// 1. first insert new data which is not 'visible' (since it has is_prod=0)
// 2. delete existing data with is_prod=1
// 3. mark newly inserted data with is_prod=1
function seshTruncateTables($is_prod=false) {
    global $wpdb;
    foreach (dbTablesArray() as $table_name) {
        $full_table_name = $wpdb->prefix . $table_name;
        $wpdb->query("DELETE FROM $full_table_name WHERE IS_PROD = ".((int) $is_prod));
    }
}

function seshMarkDataAsProd() {
    global $wpdb;
    foreach (dbTablesArray() as $table_name) {
        $full_table_name = $wpdb->prefix . $table_name;
        $wpdb->query("UPDATE $full_table_name SET IS_PROD = 1");
    }
}