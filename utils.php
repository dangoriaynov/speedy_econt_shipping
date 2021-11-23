<?php
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
        $order_clause = ' ORDER BY '.$order_by;
    }
    return $wpdb->get_results( "SELECT * FROM $table_name".$order_clause );
}