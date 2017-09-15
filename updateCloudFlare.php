<?php

/*
Author: Scott Helme
Site: https://scotthelme.co.uk
*/

// Use this link to generate keys: https://scotthel.me/v1n0
// Key example: Kqt9TH4qBEOfNSGWfPM0
// Insert the appropriate "key" => "subdomain" values below
$hosts = array(
	"***Insert Random Key1 Here***" => "subdomain1",
	"***Insert Random Key2 Here***" => "subdomain2",
	"***Insert Random Key3 Here***" => "subdomain3",
	"***Insert Random KeyX Here***" => "subdomainX"
);

// Check the calling client has a valid auth key.
if (empty($_GET['auth'])) {
	die("Authentication required\n");
} elseif (!array_key_exists($_GET['auth'], $hosts)) {
	die("Invalid auth key\n");
}

// Update these values with your own information.
$apiKey       = "CloudFlareApiKey";                         // Your CloudFlare API Key.
$myDomain     = "example.com";                              // Your domain name.
$emailAddress = "CloudFlareAccountEmailAddress";            // The email address of your CloudFlare account.

// These values do not need to be changed.
if (empty($hosts[$_GET['auth']]))
    $ddnsAddress  = $myDomain;                              // If no subdomain is given, update the domain itself.
else {
    $subdomain   = $hosts[$_GET['auth']];                   // The subdomain that will be updated.
    $ddnsAddress = $subdomain.".".$myDomain;                // The fully qualified domain name.
}

$ip           = $_SERVER['REMOTE_ADDR'];                    // The IP of the client calling the script.
$url          = 'https://www.cloudflare.com/api_json.html'; // The URL for the CloudFlare API.

// Sends request to CloudFlare and returns the response.
function send_request() {
	global $url, $fields;

	$fields_string="";
	foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
	rtrim($fields_string, '&');

	// Send the request to the CloudFlare API.
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL, $url);
	curl_setopt($ch,CURLOPT_POST, count($fields));
	curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);

	return json_decode($result);
}

// Determine protocol version and set record type.
if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
	$type = 'AAAA';
} else{
	$type = 'A';
}

// Build the initial request to fetch the record ID.
// https://www.cloudflare.com/docs/client-api.html#s3.3
$fields = array(
	'a' => urlencode('rec_load_all'),
	'tkn' => urlencode($apiKey),
	'email' => urlencode($emailAddress),
	'z' => urlencode($myDomain)
);

$data = send_request();

// Continue if the request succeeded.
if ($data->result == "success") {
	// Extract the record ID (if it exists) for the subdomain we want to update.
	$rec_exists = False;						// Assume that the record doesn't exist.
	foreach($data->response->recs->objs as $rec){
		if(($rec->name == $ddnsAddress) && ($rec->type == $type)){
			$rec_exists = True;				// If this runs, it means that the record exists.
			$id = $rec->rec_id;
			$cfIP = $rec->content;				// The IP Cloudflare has for the subdomain.
			break;
		}
	}

// Print error message if the request failed.
} else {
	die($data->msg."\n");
}

// Create a new record if it doesn't exist.
if(!$rec_exists){
	// Build the request to create a new DNS record.
	// https://www.cloudflare.com/docs/client-api.html#s5.1
	$fields = array(
		'a' => urlencode('rec_new'),
		'tkn' => urlencode($apiKey),
		'email' => urlencode($emailAddress),
		'z' => urlencode($myDomain),
		'type' => urlencode($type),
		'name' => urlencode($subdomain),
		'content' => urlencode($ip),
		'ttl' => urlencode ('1')
	);

	$data = send_request();

	// Print success/error message.
	if ($data->result == "success") {
		echo $ddnsAddress."/".$type." record successfully created\n";
	} else {
		echo $data->msg."\n";
	}

// Only update the entry if the IP addresses do not match.
} elseif($ip != $cfIP){
	// Build the request to update the DNS record with our new IP.
	// https://www.cloudflare.com/docs/client-api.html#s5.2
	$fields = array(
		'a' => urlencode('rec_edit'),
		'tkn' => urlencode($apiKey),
		'id' => urlencode($id),
		'email' => urlencode($emailAddress),
		'z' => urlencode($myDomain),
		'type' => urlencode($type),
		'name' => urlencode($subdomain),
		'content' => urlencode($ip),
		'service_mode' => urlencode('0'),
		'ttl' => urlencode ('1')
	);

	$data = send_request();

	// Print success/error message.
	if ($data->result == "success") {
		echo $ddnsAddress."/".$type." successfully updated to ".$ip."\n";
	} else {
		echo $data->msg."\n";
	}
} else {
	echo $ddnsAddress."/".$type." is already up to date\n";
}
