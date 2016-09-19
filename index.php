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

switch ($bot->messagingEvent) {
	case 'receivedMessage':
		
		switch ($bot->type_message_received) {
			case 'message':
				
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
				break;
			case 'attachment':
				$bot->send_response("Archivo adjunto");
				break;
			default:
				$bot->whoops_message();
				break;
		}

		break;
	case 'receivedPostback':
		$bot->send_response("Postback");
		break;
	case 'receivedDeliveryConfirmation':
		$bot->send_response("Delivery");
		break;
	default:
		$bot->whoops_message();
		break;
}