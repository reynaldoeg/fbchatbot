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


$input   = json_decode(file_get_contents('php://input'), true);
$msg     = $input['entry'][0]['messaging'][0]['message'];
$sender  = $input['entry'][0]['messaging'][0]['sender']['id'];
$message = $input['entry'][0]['messaging'][0]['message']['text'];

$message_to_reply = '';

/**
 * Some Basic rules to validate incoming messages
 */

$greetings = array("hi", "hallo", "hello", "good morning", "good afternoon", "good evening");
$saludos = array("hola", "buenos dias", "buenas tardes", "buenas noches");
$farewells = array("bye", "good bye", "good night", "see you", "see you later", "see you tomorrow", "see you soon");
$despedidas = array("adios", "nos vemos", "te cuidas", "hasta pronto");

//=====Greeting=====
if( in_array( strtolower($message), $greetings ) || in_array( strtolower($message), $saludos ) ){
	
	$message_to_reply = 'Hola :)';

	send_response($access_token, $msg, $sender, $message_to_reply);

//=====Farewells=====
}elseif( in_array( strtolower($message), $farewells ) || in_array( strtolower($message), $despedidas ) ){
	
	$message_to_reply = 'bye bye :)';

	send_response($access_token, $msg, $sender, $message_to_reply);

//=====Weather=====
}elseif( preg_match('[clima|temperatura|weather]', strtolower($message)) ){
	//https://developer.yahoo.com/weather/
	$BASE_URL = "http://query.yahooapis.com/v1/public/yql";
	$yql_query = 'select item.condition from weather.forecast where woeid = 116545'; //Mexico City
	$yql_query_url = $BASE_URL . "?q=" . urlencode($yql_query) . "&format=json";
	
	// Make call with cURL
	$session = curl_init($yql_query_url);
	curl_setopt($session, CURLOPT_RETURNTRANSFER,true);
	$json = curl_exec($session);
	
	// Convert JSON to PHP object
	$phpObj =  json_decode($json);

	$condition = $phpObj->query->results->channel->item->condition;

	$fahrenheit = $condition->temp;
	$celsius = ($fahrenheit - 32) * (5/9);

	$weather = array(
		"Hot" => "Caluroso",
		"Warm" => "Calido",
		"Cold" => "Frio",
		"Sunny" => "Soleado",
		"Cloudy" => "Nublado", 
		"Partly Cloudy" => "Parcialmente nublado",
	);

	$weather_advice = array(
		"Hot" => "Acuerdate del bloqueador",
		"Warm" => "Acuerdate del bloqueador",
		"Cold" => "No se te olvide el abrigo",
		"Sunny" => "Acuerdate del bloqueador",
		"Cloudy" => "No se te olvide el paraguas", 
		"Partly Cloudy" => "No se te olvide el paraguas",
	);

	send_response($access_token, $msg, $sender, "Temperatura Ciudad de México:");
	send_response($access_token, $msg, $sender, number_format($celsius, 2) . " ° C. ");

	if( array_key_exists($condition->text, $weather)){
		send_response($access_token, $msg, $sender, $weather[$condition->text]);
		send_response($access_token, $msg, $sender, $weather_advice[$condition->text]);
	}else{
		send_response($access_token, $msg, $sender, $condition->text);
	}
	
//=====Date=====
} elseif(preg_match('[time|current time|now|hora|fecha]', strtolower($message))) {
	// Make request to Time API
	ini_set('user_agent','Mozilla/4.0 (compatible; MSIE 6.0)');
	$result = file_get_contents("http://www.timeapi.org/utc/now?format=%25a%20%25b%20%25d%20%25I:%25M:%25S%20%25Y");
	if($result != '') {
		$message_to_reply = $result;
	}

	send_response($access_token, $msg, $sender, $message_to_reply);

//=====Yahoo Answers=====
} else {

	try{

		require('simple_html_dom.php');

		$BASE_URL = "https://espanol.answers.search.yahoo.com/search";
		$search_url = $BASE_URL . "?fr=uh3_answers_vert_gs&type=2button&p=" . urlencode($message);
		
		//Get all answers
		$html = file_get_html($search_url);
		$ol = $html->find('ol[class=searchCenterMiddle]');

		if( count($ol) > 0 ){

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
				$answer = clean_string($answer);
				$paragraph_answer = explode("\n", $answer);

				foreach ($paragraph_answer as $p) {
					if ($p == ' '){
						continue;
					}
					send_response($access_token, $msg, $sender, trim($p));
				}
			}else{
				whoops_message();
			}

		}else{
			whoops_message();
		}

	}catch(Exception $e){

		whoops_message();

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


function clean_string($string){

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

}

