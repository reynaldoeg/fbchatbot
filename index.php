<?php

require_once('chatBot.php');


$bot = new ChatBot();

if(isset($_REQUEST['hub_challenge'])) {
	$bot->challenge = $_REQUEST['hub_challenge'];
	$bot->hub_verify_token = $_REQUEST['hub_verify_token'];
}else {
	echo 'no request hub_challenge';
}
if ($bot->hub_verify_token === $bot->verify_token) {
	echo $bot->challenge;
}

$bot->input   = json_decode(file_get_contents('php://input'), true);
$bot->set_data_input();

$msg = $bot->msg;
$sender = $bot->sender;
$message = $bot->message;


/**
 * Some Basic rules to validate incoming messages
 */

if( $bot->greeting() ){
	//=====Greeting=====
} elseif( $bot->weather() ){
	//=====Weather=====
} elseif( $bot->current_time() ) {
	//=====Date=====
} elseif( $bot->horoscope() ) {
	//=====Horoscope=====
} elseif( $bot->yahoo_answer() ) {
	//=====Answers=====
} else {
	$bot->whoops_message();
}
