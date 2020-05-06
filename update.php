<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Require composer autoloader
require 'vendor/autoload.php';

// Check method
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo 'ERROR: Wrong request method';
    die();
}

// Load and check credentials
$credentials = parse_ini_file('credentials.ini');
if(empty($credentials['ddns_key']) or empty($credentials['ddns_hosts'])
    or empty($credentials['plesk_host'] or empty($credentials['plesk_key']))){
    http_response_code(500);
    echo 'ERROR: Not all credentials (ddns_key, ddns_hosts, plesk_host, plesk_key) set';
    die();
}

// Check and get parameters
if(!empty($_POST['domain']) and !empty($_POST['subdomain']) and !empty($_POST['key'])){
    $params = [
        'key' => $_POST['key'],
        'domain' => $_POST['domain'],
        'subdomain' => $_POST['subdomain'],
        'host' => sprintf('%s.%s', $_POST['subdomain'], $_POST['domain']),
        'ip' => empty($_POST['ip']) ? $_SERVER['REMOTE_ADDR'] : $_POST['ip']
    ];
} else{
    http_response_code(400);
    echo 'ERROR: Not all required parameters (key, domain, subdomain) provided';
    die();
}

// Check our own key
if($params['key'] != $credentials['ddns_key']){
    http_response_code(403);
    echo 'ERROR: Invalid key provided';
    die();
}

// Check if DDNS host is allowed
$ddns_hosts_list = explode(',', $credentials['ddns_hosts']);
if(!in_array($params['host'], $ddns_hosts_list)){
    http_response_code(400);
    echo 'ERROR: Domain is not allowed';
    die();
}

try {
    // Connect to API
    $plesk = new \PleskX\Api\Client($credentials['plesk_host']);
    $plesk->setSecretKey($credentials['plesk_key']);

    // Build site request
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><packet></packet>', null, false);
    $site = $xml->addChild('site');
    $get = $site->addChild('get');
    $filter = $get->addChild('filter');
    $filter->addChild('name', $params['domain']);
    $get->addChild('dataset');

    // Get site ID
    $site_data = $plesk->request($xml);
    $site_id = $site_data->getValue('id');

    // Get current DNS records
    $current_records = $plesk->dns()->getAll('site-id', $site_id);

    // Create new DNS record
    $plesk->dns()->create([
        'site-id' => $site_id,
        'type' => 'A',
        'host' => $params['subdomain'],
        'value' => $params['ip']
    ]);

    // Delete obsolete DNS records
    $host_dns = sprintf('%s.', $params['host']);
    foreach($current_records as $record){
        if($record->host == $host_dns){
            $plesk->dns()->delete('id', $record->id);
        }
    }
} catch (PleskX\Api\Exception $e) {
    if($e->getCode() == 1007) {
        // Report success if record already existed
        echo 'SUCCESS: Record already up to date';
        die();
    } else {
        // Report any other error
        http_response_code(400);
        echo sprintf('ERROR: %s (%s)', $e->getMessage(), $e->getCode());
        die();
    }
}

echo 'SUCCESS: Record updated';
