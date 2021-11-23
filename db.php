<?php

global $jal_db_version, $speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table;
$speedy_offices_table = 'speedy_offices';
$econt_offices_table = 'econt_offices';
$speedy_sites_table = 'speedy_sites';
$econt_sites_table = 'econt_sites';
$jal_db_version = '1.1';


function createSpeedyTables() {
    global $wpdb, $jal_db_version, $speedy_offices_table, $econt_offices_table, $speedy_sites_table, $econt_sites_table;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$speedy_sites_table." (
      id mediumint(9) NOT NULL,
      name text NOT NULL,
      region text NOT NULL,
      municipality text NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$speedy_offices_table." (
      id mediumint(9) NOT NULL,
      name text NOT NULL,
      city text NOT NULL,
      address text NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$econt_sites_table." (
      id mediumint(9) NOT NULL,
      name text NOT NULL,
      region text NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$econt_offices_table." (
      id mediumint(9) NOT NULL,
      name text NOT NULL,
      city text NOT NULL,
      address text NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql );

    add_option( 'jal_db_version', $jal_db_version );
}

function insertSpeedySite($id, $name, $region, $municipality) {
    global $wpdb, $speedy_sites_table;
    $table_name = $wpdb->prefix . $speedy_sites_table;
    $wpdb->replace(
        $table_name,
        array(
            'id' => $id,
            'name' => $name,
            'region' => $region,
            'municipality' => $municipality
        )
    );
}

function insertSpeedyOffice($id, $name, $city, $address) {
    global $wpdb, $speedy_offices_table;
    $table_name = $wpdb->prefix . $speedy_offices_table;
    $wpdb->replace(
        $table_name,
        array(
            'id' => $id,
            'name' => $name,
            'city' => $city,
            'address' => $address
        )
    );
}

function truncateTables() {
    global $wpdb, $speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table;
    $table_name = $wpdb->prefix . $speedy_offices_table;
    $wpdb->query('TRUNCATE TABLE '.$table_name);
    $table_name = $wpdb->prefix . $speedy_sites_table;
    $wpdb->query('TRUNCATE TABLE '.$table_name);
    $table_name = $wpdb->prefix . $econt_offices_table;
    $wpdb->query('TRUNCATE TABLE '.$table_name);
    $table_name = $wpdb->prefix . $econt_sites_table;
    $wpdb->query('TRUNCATE TABLE '.$table_name);
}