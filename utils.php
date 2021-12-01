<?php

global $speedy_region_sel, $speedy_city_sel, $speedy_office_sel, $econt_region_sel, $econt_city_sel, $econt_office_sel,
       $speedy_region_field, $speedy_city_field, $speedy_office_field, $econt_region_field, $econt_city_field, $econt_office_field,
       $shipping_to_regex, $delivOpts, $defaultOpt;
// following global vars might get populated via the plugin's UI. One day...
$speedy_region_field = "#speedy_region_sel_field";
$speedy_city_field = "#speedy_city_sel_field";
$speedy_office_field = "#speedy_office_sel_field";
$econt_region_field = "#econt_region_sel_field";
$econt_city_field = "#econt_city_sel_field";
$econt_office_field = "#econt_office_sel_field";

$speedy_region_sel = "#speedy_region_sel";
$speedy_city_sel = "#speedy_city_sel";
$speedy_office_sel = "#speedy_office_sel";
$econt_region_sel = "#econt_region_sel";
$econt_city_sel = "#econt_city_sel";
$econt_office_sel = "#econt_office_sel";

$shipping_to_regex = 'input:radio[name="shipping_to"]';
$delivOpts = array(
    'econt' => array('id' => 'shipping_to_econt', 'name' => 'econt', 'label' => 'офис на Еконт', 'shipping' => 5.6, 'free_from' => 50, 'data' => 'econtData'),
    'speedy' => array('id' => 'shipping_to_speedy', 'name' => 'speedy', 'label' => 'офис на Speedy', 'shipping' => 3.4, 'free_from' => 40, 'data' => 'speedyData'),
    'address' => array('id' => 'shipping_to_address', 'name' => 'address', 'label' => 'адрес', 'shipping' => 4.2, 'free_from' => 50));
$defaultOpt = $delivOpts['speedy']['name'];

function convertCase($value, $dict_replace = []) {
    $result = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    foreach ($dict_replace as $key => $value) {
        $result = str_replace($key, $value, $result);
    }
    return $result;
}

function readTableData($table_name, $order_by = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . $table_name;
    $order_clause = '';
    if ($order_by) {
        $order_clause = 'ORDER BY '.$order_by;
    }
    return $wpdb->get_results( "SELECT * FROM $table_name WHERE IS_PROD = 1 $order_clause" );
}