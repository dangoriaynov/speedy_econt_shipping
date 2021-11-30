<?php

require 'credentials.php';

function postRequest($url, $payload, $auth=null)
{
    $curl = curl_init($url);
    $jsonDataEncoded = json_encode($payload);
    if ($auth) {
        $headers = array($auth);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($curl, CURLOPT_POST, 1); // Tell cURL that we want to send a POST request.
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Verify the peer's SSL certificate.
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Stop showing results on the screen.
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5); // The number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); // Set the content type to application/json
    curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonDataEncoded); // Attach our encoded JSON string to the POST fields.
    $jsonResponse = curl_exec($curl);

    if ( $jsonResponse === FALSE) {
        exit("cURL error: ".curl_error($curl)." while accessing ".$url);
    }
    return json_decode($jsonResponse);
}

function speedyApiRequest($url)
{
    $payload = array(
        'userName' => SPEEDY_NAME,
        'password' => SPEEDY_PASSWORD,
        'language' => 'BG',
        'countryId' => 100, // BULGARIA
    );
    return postRequest('https://api.speedy.bg/v1/'.$url, $payload);
}

function apiSpeedySitesList($site_id) {
    $dataJson = speedyApiRequest('location/site/'.$site_id);
    $site = $dataJson->site;
    return array(
        'name' => $site->name,
        'region' => $site->region,
        'municipality' => $site->municipality);
}

function apiSpeedyOfficesList() {
    $dataJson = speedyApiRequest('location/office/');
    $sites = $dataJson->offices;
    $results = array();
    foreach ($sites as $office) {
        $results[$office->id] = array(
            'name' => $office->name,
            'site_id' => $office->address->siteId,
            'address' => $office->address->fullAddressString);
    }
    return $results;
}

function econtApiRequest($url)
{
    $payload = array(
        'countryCode' => 'BGR',
    );
    $auth = 'Authorization: Basic '. base64_encode(ECONT_NAME.":".ECONT_PASSWORD);
    return postRequest('https://ee.econt.com/services/Nomenclatures/NomenclaturesService.'.$url.'.json', $payload, $auth);
}

function apiEcontSitesList() {
    $dataJson = econtApiRequest('getCities');
    $sites = $dataJson->cities;
    $results = array();
    foreach ($sites as $site) {
        $results[$site->id] = array(
            'name' => $site->name,
            'region' => $site->regionName);
    }
    return $results;
}

function apiEcontOfficesList() {
    $dataJson = econtApiRequest('getOffices');
    $sites = $dataJson->offices;
    $results = array();
    foreach ($sites as $office) {
        $results[$office->id] = array(
            'name' => $office->name,
            'site_id' => $office->address->city->id,
            'address' => $office->address->fullAddress);
    }
    return $results;
}