<?php

global $jal_db_version, $speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table;
$speedy_offices_table = 'speedy_offices';
$econt_offices_table = 'econt_offices';
$speedy_sites_table = 'speedy_sites';
$econt_sites_table = 'econt_sites';
$jal_db_version = '1.1';


function createTables() {
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

function insertSite($id, $name, $region, $municipality, $short_table_name) {
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

function insertSpeedySite($id, $name, $region, $municipality) {
    global $speedy_sites_table;
    insertSite($id, $name, $region, $municipality, $speedy_sites_table);
}

function insertEcontSite($id, $name, $region) {
    global $econt_sites_table;
    insertSite($id, $name, $region, '', $econt_sites_table);
}

function insertOffice($id, $name, $city, $address, $short_table_name) {
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

function insertSpeedyOffice($id, $name, $city, $address) {
    global $speedy_offices_table;
    insertOffice($id, $name, $city, $address, $speedy_offices_table);
}

function insertEcontOffice($id, $name, $city, $address) {
    global $econt_offices_table;
    insertOffice($id, $name, $city, $address, $econt_offices_table);
}

function truncateTables($is_prod=false) {
    global $wpdb, $speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table;
    $table_names = array($speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table);
    foreach ($table_names as $table_name) {
        $full_table_name = $wpdb->prefix . $table_name;
        $wpdb->query('DELETE * FROM TABLE '.$full_table_name.' WHERE IS_PROD = '.((int) $is_prod));
    }
}

function markDataAsProd() {
    global $wpdb, $speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table;
    $table_names = array($speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table);
    foreach ($table_names as $table_name) {
        $full_table_name = $wpdb->prefix . $table_name;
        $wpdb->query('UPDATE TABLE '.$full_table_name.' SET IS_PROD = 1');
    }
}