<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}
global $defaultHeaders;
$defaultHeaders = array('Content-Type' => 'application/json; charset=utf-8');

require_once 'SeshSpeedyEcontShippingAdmin.php';

function seshPostRequest($url, $payload, $headers=array())
{
    global $defaultHeaders;
    $finalHeaders = array_merge($headers, $defaultHeaders);
    $args = array(
        'method' => 'POST',
        'body' => json_encode($payload),
        'headers' => $finalHeaders,
        'timeout'     => '60',
        'redirection' => '5',
        'httpversion' => '1.0',
        'blocking'    => true,
        'cookies'     => array()
    );

    $jsonResponse = wp_remote_post($url, $args);
    if (! $jsonResponse) {
        exit("error while accessing ".$url);
    }
    return json_decode($jsonResponse['body']);
}

function seshSpeedyApiRequest($url)
{
    $payload = array(
        'userName' => getSpeedyUser(),
        'password' => getSpeedyPass(),
        'language' => 'BG',
        'countryId' => 100 // BULGARIA
    );
    return seshPostRequest('https://api.speedy.bg/v1/'.$url, $payload);
}

function seshApiSpeedySitesList($site_id) {
    $dataJson = seshSpeedyApiRequest('location/site/'.$site_id);
    $site = $dataJson->site;
    return array(
        'name' => $site->name,
        'region' => $site->region,
        'municipality' => $site->municipality);
}

function seshApiSpeedyOfficesList() {
    $dataJson = seshSpeedyApiRequest('location/office/');
    $sites = $dataJson->offices;
    $results = array();
    if (is_array($sites) || is_object($sites)) {
        foreach ($sites as $office) {
            $results[$office->id] = array(
                'name' => $office->name,
                'site_id' => $office->address->siteId,
                'address' => $office->address->fullAddressString,
                'is_open' => $office->pickUpAllowed && $office->dropOffAllowed);
        }
    }
    return $results;
}

function seshEcontApiRequest($url)
{
    $payload = array(
        'countryCode' => 'BGR',
    );
    // $headers = array('Authorization' => 'Basic '. base64_encode(getEcontUser().":".getEcontPass()));
    return seshPostRequest('https://ee.econt.com/services/Nomenclatures/NomenclaturesService.'.$url.'.json', $payload, array());
}

function seshApiEcontSitesList() {
    $dataJson = seshEcontApiRequest('getCities');
    $sites = $dataJson->cities;
    $results = array();
    if (is_array($sites) || is_object($sites)) {
        foreach ($sites as $site) {
            $results[$site->id] = array(
                'name' => $site->name,
                'region' => $site->regionName);
        }
    }
    return $results;
}

function seshApiEcontOfficesList() {
    $dataJson = seshEcontApiRequest('getOffices');
    $sites = $dataJson->offices;
    $results = array();
    foreach ($sites as $office) {
        $results[$office->id] = array(
            'name' => $office->name,
            'site_id' => $office->address->city->id,
            'address' => $office->address->fullAddress,
            'is_open' => true);
    }
    return $results;
}