<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}

require_once 'SeshSpeedyEcontShippingAdmin.php';

global $speedy_region_sel, $speedy_city_sel, $speedy_office_sel, $econt_region_sel, $econt_city_sel, $econt_office_sel,
       $speedy_region_field, $speedy_city_field, $speedy_office_field, $econt_region_field, $econt_city_field, $econt_office_field,
       $shipping_to_sel, $speedy_region_id, $speedy_city_id, $speedy_office_id, $econt_region_id,
       $econt_city_id, $econt_office_id, $shipping_to_id, $address_region_sel, $address_city_sel, $address_address_sel,
       $shipping_to_speedy_key;

$speedy_region_id = "speedy_region_sel";
$speedy_city_id = "speedy_city_sel";
$speedy_office_id = "speedy_office_sel";
$econt_region_id = "econt_region_sel";
$econt_city_id = "econt_city_sel";
$econt_office_id = "econt_office_sel";
$shipping_to_id = "shipping_to";

$speedy_region_sel = "#".$speedy_region_id;
$speedy_city_sel = "#".$speedy_city_id;
$speedy_office_sel = "#".$speedy_office_id;
$econt_region_sel = "#".$econt_region_id;
$econt_city_sel = "#".$econt_city_id;
$econt_office_sel = "#".$econt_office_id;
$address_region_sel = getAddressFields()[0];
$address_city_sel = getAddressFields()[1];
$address_address_sel = getAddressFields()[2];
$shipping_to_sel = 'input[name="'.$shipping_to_id.'"]';

$speedy_region_field = $speedy_region_sel."_field";
$speedy_city_field = $speedy_city_sel."_field";
$speedy_office_field = $speedy_office_sel."_field";
$econt_region_field = $econt_region_sel."_field";
$econt_city_field = $econt_city_sel."_field";
$econt_office_field = $econt_office_sel."_field";
$address_region_field = $address_region_sel.'_field';
$address_city_field = $address_city_sel.'_field';
$address_address_field = $address_address_sel.'_field';
$shipping_to_field = "#".$shipping_to_id."_field";

$shipping_to_speedy_key = 'shipping_to_speedy';
$shipping_to_econt_key = 'shipping_to_econt';
$shipping_to_address_key = 'shipping_to_address';


// default delivery option
function seshDefaultDelivOpt() {
    $deliv_opts = seshDelivOptions();
    return $deliv_opts[array_key_first($deliv_opts)]['name'];
}

function seshDelivOptions(): array
{
    global $shipping_to_speedy_key, $shipping_to_econt_key, $shipping_to_address_key;
    $delivOpts = array();
    $delivOpts['speedy'] =
        array('id' => $shipping_to_speedy_key,
            'name' => 'speedy',
            'label' => __('Speedy office', 'speedy_econt_shipping'),
            'shipping' => getSpeedyShipping(),
            'free_from' => getSpeedyFreeFrom(),
            'data' => 'speedyData');
    $delivOpts['econt'] =
        array('id' => $shipping_to_econt_key,
            'name' => 'econt',
            'label' => __('Econt office', 'speedy_econt_shipping'),
            'shipping' => getEcontShipping(),
            'free_from' => getEcontFreeFrom(),
            'data' => 'econtData');
    $delivOpts['address'] =
        array('id' => $shipping_to_address_key,
            'name' => 'address',
            'label' => __('address', 'speedy_econt_shipping'),
            'shipping' => getAddressShipping(),
            'free_from' => getAddressFreeFrom());
    return $delivOpts;
}

function seshConvertCase($value, $dict_replace = []) {
    $result = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    foreach ($dict_replace as $key => $value) {
        $result = str_replace($key, $value, $result);
    }
    return $result;
}

function seshReadTableData($table_name, $order_by = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . $table_name;
    $order_clause = '';
    if ($order_by) {
        $order_clause = 'ORDER BY '.$order_by;
    }
    return $wpdb->get_results( "SELECT * FROM $table_name WHERE IS_PROD = 1 $order_clause" );
}

if (!function_exists('write_log')) {

    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

}