<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Require composer autoloader
require 'vendor/autoload.php';

// Set default timezone
date_default_timezone_set('Europe/Amsterdam');

// Create logging function
function update_log($log_message, $log_params=[]) {
    $log_date = strftime('%F %T');
    $log_ip = $log_params['ip'] ?? $_SERVER['REMOTE_ADDR'];
    $log_host = $log_params['host'] ?? '-';
    $log_line = sprintf("[%s] %s, IP: %s, HOST: %s\n", $log_date, $log_message, $log_ip, $log_host);
    file_put_contents('ddns_update.log', $log_line, FILE_APPEND);
    echo $log_message;
}

// Check method
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    http_response_code(405);
    update_log('ERROR: Wrong request method');
    die();
}

// Load and check credentials
$credentials = parse_ini_file('credentials.ini');
if(empty($credentials['ddns_key']) or empty($credentials['ddns_hosts'])
    or empty($credentials['plesk_host'] or empty($credentials['plesk_key']))){
    http_response_code(500);
    update_log('ERROR: Not all credentials (ddns_key, ddns_hosts, plesk_host, plesk_key) set');
    die();
}

// Check and get parameters
if(!empty($_REQUEST['domain']) and !empty($_REQUEST['subdomain']) and !empty($_REQUEST['key'])){
    $params = [
        'key' => $_REQUEST['key'],
        'domain' => $_REQUEST['domain'],
        'subdomain' => $_REQUEST['subdomain'],
        'host' => sprintf('%s.%s', $_REQUEST['subdomain'], $_REQUEST['domain']),
        'ip' => empty($_REQUEST['ip']) ? $_SERVER['REMOTE_ADDR'] : $_REQUEST['ip']
    ];
} else{
    http_response_code(400);
    update_log('ERROR: Not all required parameters (key, domain, subdomain) provided');
    die();
}

// Check our own key
if($params['key'] != $credentials['ddns_key']){
    http_response_code(403);
    update_log('ERROR: Invalid key provided', $params);
    die();
}

// Check if DDNS host is allowed
$ddns_hosts_list = explode(',', $credentials['ddns_hosts']);
if(!in_array($params['host'], $ddns_hosts_list)){
    http_response_code(400);
    update_log('ERROR: Domain is not allowed', $params);
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
        update_log('OK: Record already up to date', $params);
        die();
    } else {
        // Report any other error
        http_response_code(400);
        update_log(sprintf('ERROR: %s (%s)', $e->getMessage(), $e->getCode()), $params);
        die();
    }
}

update_log('OK: Record updated', $params);
