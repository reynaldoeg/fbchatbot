<?php

require_once('env.php');

$access_token = ACCESS_TOKEN;
$verify_token = VERIFY_TOKEN;

$hub_verify_token = null;
if(isset($_REQUEST['hub_challenge'])) {
	$challenge = $_REQUEST['hub_challenge'];
	$hub_verify_token = $_REQUEST['hub_verify_token'];
}else {
	echo 'no request hub_challenge';
}
if ($hub_verify_token === $verify_token) {
	echo $challenge;
}
