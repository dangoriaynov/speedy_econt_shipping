<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

global $sesh_db_version, $speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table, $queries,
       $econt_offices_inserted, $econt_sites_inserted, $speedy_offices_inserted, $speedy_sites_inserted;
$speedy_offices_table = 'speedy_offices';
$econt_offices_table = 'econt_offices';
$speedy_sites_table = 'speedy_sites';
$econt_sites_table = 'econt_sites';
$sesh_db_version = '1.3';
$queries = array();
$econt_offices_inserted = array();
$econt_sites_inserted = array();
$speedy_offices_inserted = array();
$speedy_sites_inserted = array();


function seshCreateTables() {
    global $wpdb, $sesh_db_version, $speedy_offices_table, $econt_offices_table, $speedy_sites_table, $econt_sites_table;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$speedy_sites_table." (
      id int(15) NOT NULL,
      name text NOT NULL,
      region text NOT NULL,
      municipality text NOT NULL,
      is_prod BOOLEAN default FALSE
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$speedy_offices_table." (
      id int(15) NOT NULL,
      name text NOT NULL,
      city text NOT NULL,
      address text NOT NULL,
      is_prod BOOLEAN default FALSE
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$econt_sites_table." (
      id int(15) NOT NULL,
      name text NOT NULL,
      region text NOT NULL,
      municipality text NOT NULL,
      is_prod BOOLEAN default FALSE
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$econt_offices_table." (
      id int(15) NOT NULL,
      name text NOT NULL,
      city text NOT NULL,
      address text NOT NULL,
      is_prod BOOLEAN default FALSE
    ) $charset_collate;";
    dbDelta( $sql );

    add_option( 'sesh_db_version', $sesh_db_version );
}

function clearQueries() {
    global $queries, $econt_offices_inserted, $econt_sites_inserted, $speedy_offices_inserted, $speedy_sites_inserted;
    $queries = array();
    $econt_offices_inserted = array();
    $econt_sites_inserted = array();
    $speedy_offices_inserted = array();
    $speedy_sites_inserted = array();
}

function appendQuery($short_table_name, $query) {
    global $wpdb, $queries;
    $table_name = $wpdb->prefix . $short_table_name;
    $queries[] = str_replace('{table_name}', $table_name, $query);
}

function executeQueries() {
    global $queries, $wpdb;
    if (count($queries) === 0) {
        write_log("no queries left to be run");
        return;
    }
    $connect = $wpdb->__get('dbh');
    try {
        // do the whole run as 1 transaction
        $queries_str = "BEGIN; ".implode('; ', $queries)."; COMMIT;";
        // write_log("Will run: ".$queries_str);
        mysqli_multi_query($connect, $queries_str);
    } finally {
        // Check for errors
        if ($wpdb->last_error !== '') {
            // Log the error message
            write_log("Database error: " . $wpdb->last_error);
        }
        while(mysqli_more_results($connect)){mysqli_next_result($connect);}
        clearQueries();
    }
}

function seshDropTables() {
    global $wpdb, $speedy_offices_table, $econt_offices_table, $speedy_sites_table, $econt_sites_table;
    foreach (array($speedy_sites_table, $speedy_offices_table, $econt_sites_table, $econt_offices_table) as $table_name) {
        $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . $table_name);
    }
    delete_option("sesh_db_version");
}

function for_sql($value) : string {
    return str_replace("'", '', trim($value));
}

function seshInsertSite($id, $name, $region, $municipality, $short_table_name) {
    appendQuery($short_table_name, "INSERT INTO {table_name} (id, name, region, municipality) VALUES ($id,'".for_sql($name)."','".for_sql($region)."','".for_sql($municipality)."')");
}

function seshInsertSpeedySite($id, $name, $region, $municipality) {
    global $speedy_sites_table, $speedy_sites_inserted;
    if (in_array($id, $speedy_sites_inserted)) {
        return;
    }
    $speedy_sites_inserted[] = $id;
    seshInsertSite($id, $name, $region, $municipality, $speedy_sites_table);
}

function seshInsertEcontSite($id, $name, $region) {
    global $econt_sites_table, $econt_sites_inserted;
    if (in_array($id, $econt_sites_inserted)) {
        return;
    }
    $econt_sites_inserted[] = $id;
    seshInsertSite($id, $name, $region, '', $econt_sites_table);
}

function seshInsertOffice($id, $name, $city, $address, $short_table_name) {
    appendQuery($short_table_name, "INSERT INTO {table_name} (id, name, city, address) VALUES ($id,'".for_sql($name)."','".for_sql($city)."','".for_sql($address)."')");
}

function seshInsertSpeedyOffice($id, $name, $city, $address) {
    global $speedy_offices_table, $speedy_offices_inserted;
    // this way to avoid duplicates
    if (in_array($id, $speedy_offices_inserted)) {
        return;
    }
    $speedy_offices_inserted[] = $id;
    seshInsertOffice($id, $name, $city, $address, $speedy_offices_table);
}

function seshInsertEcontOffice($id, $name, $city, $address) {
    global $econt_offices_table, $econt_offices_inserted;
    // this way to avoid duplicates
    if (in_array($id, $econt_offices_inserted)) {
        return;
    }
    $econt_offices_inserted[] = $id;
    seshInsertOffice($id, $name, $city, $address, $econt_offices_table);
}

function composeDbTablesArray($tables_key) {
    global $speedy_offices_table, $speedy_sites_table, $econt_offices_table, $econt_sites_table, $speedy_opt_key, $econt_opt_key;
    $table_names = array();
    if ($tables_key === $speedy_opt_key) {
        $table_names = array_merge($table_names, array($speedy_offices_table, $speedy_sites_table));
    }
    if ($tables_key === $econt_opt_key) {
        $table_names = array_merge($table_names, array($econt_offices_table, $econt_sites_table));
    }
    return $table_names;
}

// $is_prod is the way to avoid cleaning up the tables before we actually get the new values from the API
// this way we do the following:
// 1. first insert new data which is not 'visible' (since it has is_prod=0)
// 2. delete existing data with is_prod=1
// 3. mark newly inserted data with is_prod=1
function seshTruncateTables($tables_key, $is_prod=false) {
    global $wpdb, $queries;
    $is_prod_int = (int) $is_prod;
    write_log("seshTruncateTables: tables_key=$tables_key, is_prod=$is_prod_int");
    foreach (composeDbTablesArray($tables_key) as $table_name) {
        $full_table_name = $wpdb->prefix . $table_name;
        $queries[] = "DELETE FROM $full_table_name WHERE IS_PROD = ".$is_prod_int;
    }
}

function seshMarkDataAsProd($tables_key) {
    global $wpdb, $queries;
    write_log("seshMarkDataAsProd: tables_key=$tables_key");
    foreach (composeDbTablesArray($tables_key) as $table_name) {
        $full_table_name = $wpdb->prefix . $table_name;
        $queries[] = "UPDATE $full_table_name SET IS_PROD = 1";
    }
}