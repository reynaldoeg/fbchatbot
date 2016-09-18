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
}elseif( $bot->weather() ){
	//=====Weather=====
} elseif( $bot->current_time() ) {
	//=====Date=====
} else {

	try{

		require('simple_html_dom.php');

		$BASE_URL = "https://espanol.answers.search.yahoo.com/search";
		$search_url = $BASE_URL . "?fr=uh3_answers_vert_gs&type=2button&p=" . urlencode($message);

		//Get all answers
		$html = file_get_html($search_url);
		$ol = $html->find('ol[class=searchCenterMiddle]');

		if ( count($ol) > 0 ){

			$li = $ol[0]->find('li[class=first]');
			$link = $li[0]->find('a');
			$new_url = $link[0]->href;

			//Get first answer
			$html = file_get_html($new_url);
			$div = $html->find('div[itemprop=acceptedAnswer]');

			if( count($div) > 0 ){

				$span = $div[0]->find('span[itemprop=text]');
				$answer = $span[0]->plaintext;

				//Clean answer
				$answer = $bot->clean_string($answer);
				$paragraph_answer = explode("\n", $answer);

				$access_token = $bot->access_token;
				$msg = $bot->msg;
				$sender = $bot->sender;

				foreach ($paragraph_answer as $p) {
					if ($p == ' '){
						continue;
					}
					shorten_response($access_token, $msg, $sender, trim($p));
				}

			}else{
				$bot->whoops_message();
			}

		}else{
			$bot->whoops_message();
		}

	}catch(Exception $e){

		$bot->whoops_message();
	
	}

}

//API Url
function send_response($access_token, $msg, $sender, $message_to_reply){

	$url = 'https://graph.facebook.com/v2.6/me/messages?access_token='.$access_token;
	//Initiate cURL.
	$ch = curl_init($url);
	//The JSON data.
	$jsonData = '{
		"recipient":{
			"id":"'.$sender.'"
		},
		"message":{
			"text":"'.$message_to_reply.'"
		}
	}';

	//"sender_action": "typing_on" ("typing_off"  o  "mark_seen")

	//Encode the array into JSON.
	$jsonDataEncoded = $jsonData;
	//Tell cURL that we want to send a POST request.
	curl_setopt($ch, CURLOPT_POST, 1);
	//Attach our encoded JSON string to the POST fields.
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
	//Set the content type to application/json
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
	//Execute the request
	if(!empty($msg)){
	    $result = curl_exec($ch);
	}

}

function shorten_response($access_token, $msg, $sender, $msg){
	$msg_max_len = 320;
	$msg_len = strlen($msg);
	if( $msg_len <= $msg_max_len ){
		send_response($access_token, $msg, $sender, $msg);
	}else{
		$num_rep = ceil($msg_len / $msg_max_len);
		for ($i = 0 ; $i < $num_rep ; $i++){
			$tmp_msg = substr($msg, $i*$msg_max_len, $msg_max_len);
			send_response($access_token, $msg, $sender, $tmp_msg);
		}	
	}
}

/*function clean_string($string){

	$string = trim($string); //Eliminar espacios en blanco al inicio y al final
	$string = str_replace("\'","",$string); //Eliminar las comillas simples (')
	$string = str_replace('\"',"",$string); //Eliminar las comillas dobles (")
	$string = str_replace("\r", " ", $string); //Eliminar retornos de carro
	$string = str_replace("   "," ",$string); //Quitar espacios multiples
	$string = str_replace("  "," ",$string);

	return $string;
}

function whoops_message(){

	$message_to_reply = 'No entiendo lo que dices :(';
	send_response($access_token, $msg, $sender, $message_to_reply);
	$message_to_reply = 'Quieres que te diga el clima o la hora';
	send_response($access_token, $msg, $sender, $message_to_reply);

}*/

