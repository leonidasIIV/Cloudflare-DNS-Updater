!/usr/bin/php7.2
<?php
# Script for updating Dynamic IP on CloudFlare
# K Powell <myself@kevinfpowell.me>
function api_put ($api, $data) {
    global $api_data;

    $OPT_URL = API_URL . $api;

    echo $OPT_URL . "\n";
   
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $OPT_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "X-Auth-Email: kp259207@ohio.edu",
            "X-Auth-Key: ebcfe76e54b6edf97dca67adcf70d4d923853",
            "cache-control: no-cache"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        tolog($err);
        return;
    } else {
        return  json_decode($response, true);
    }
}

function api_get ($api, $data) {
    global $api_data;

    $OPT_URL = API_URL . $api;
    if (!is_null($data)) {
        $OPT_URL .= '?' . urldecode(http_build_query($data, '',','));
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $OPT_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_POSTFIELDS => "",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "X-Auth-Email: " . $api_data['email'],
            "X-Auth-Key: " . $api_data['tkn'],
            "cache-control: no-cache"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        tolog($err);
        return;
    } else {
        return  json_decode($response, true);
    }
}

function tolog ($text) {
    echo $text . "\n";
}


define ('API_URL', "https://api.cloudflare.com/client/v4/");

$cf_email = getenv('CF_EMAIL');
$cf_api = getenv('CF_API');
$dns_entry = getenv('CF_HOST');
$dns_prefixes = explode(',', getenv('CF_PREFIXES'));

if (!isset($cf_email, $cf_api, $dns_entry)) {
    tolog('CF_EMAIL, CF_API, and CF_HOST must be set');
} else {
    $api_data = array('email' => $cf_email,
                      'tkn' => $cf_api,
                      'host' => $dns_entry,
                      'prefixes' => $dns_prefixes);

    print_r($api_data);
    
    tolog('Begining loop to keep Cloudflare DNS IPs up to date...');
    while (true)
    {

        # Determine the DNS host ID
        tolog('Contacting the Cloudflare API to determine DNS zone');

        $entries = array($dns_entry);
        $last = $dns_entry;
        for ($i = 1; $i <= substr_count($dns_entry, '.'); $i++) {
            $last = substr(strstr($last, '.'), 1);
            array_push($entries, $last);
        }

        $zone_params = array('name' => implode(',', $entries));
        $zones = api_get('zones', $zone_params);

        if (!$zones['result']) {
            tolog ('Error Occurred retrieving zone data, abandoning this attempt...');
            print_r($zone_params);
            print_r($zones);
            echo ('\n\n');
            die();
        }

        # Compile list of DNS entries using prefixes + zone name
        $dns_record_targets = array();
        $dns_base = $zones['result'][0]['name'];
        foreach ($api_data['prefixes'] as $p) {
            $prefix = trim($p);
            if ($p == '') {
                array_push($dns_record_targets, $dns_base);
            } else {
                array_push($dns_record_targets, $prefix . '.' . $dns_base);
            }
        }

        print_r($dns_record_targets);

        # Determine the DNS zone ID
        tolog('Trying to determine DNS record ID');
        $records = api_get('zones/' . $zones['result'][0]['id'] . '/dns_records', null);

        if (!$records['result']) {
            tolog ('Error Occurred retrieving dns records, abandoning this attempt...');
            print_r($records);
            echo ('\n\n');
            die();
        }

        # Loop DNS resolve and IP compare
        $ip_api = trim(file_get_contents('http://icanhazip.com/'));
        tolog ('Current IP: ' . $ip_api);

        if (filter_var($ip_api, FILTER_VALIDATE_IP)) {
            tolog ('Checking Current IP against DNS records');
        } else {
            tolog ('Invalid IP received from API');
        }
        foreach ($records['result'] as $record) {
            if ($record['type'] == 'A' and in_array($record['name'], $dns_record_targets)) {
                tolog ('Found record for ' . $record['name']);
                $record_id = $record['id'];

                if (gethostbyname($dns_entry) !== $ip_api) {
                    tolog ('  Updating IP on DNS record');
                    # Updating DNS entry
                    $update_response = api_put('zones/' . $zones['result'][0]['id'] .'/dns_records/' . $record_id ,
                            '{' .
                                '"type": "' . $record['type'] . '",' .
                                '"name": "' . $record['name'] . '",' .
                                '"content": "' . $ip_api . '",' .
                                '"ttl": ' . $record['ttl'] . ',' .
                                '"proxied": ' . ($record['proxied'] ? "true" : "false") .
                            '}');
                } else {
                    tolog ('  IP is up to date');
                }
            }
        }

        # Sleep until next ceck
        sleep (30);
    }
}