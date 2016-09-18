<?php
require_once('env.php');

class ChatBot{

	public $access_token = ACCESS_TOKEN;
	public $verify_token = VERIFY_TOKEN;
	public $challenge = '';
	public $hub_verify_token = null;

	public $input = '';

	public $msg = '';
	public $sender = '';
	public $message = '';

	public $message_to_reply = '';

	/**
	 * Set de sender id and the message received.
	 *
	 * @access public
	 *
	 * @param -----
	 * @return Void.
	 */
	public function set_data_input(){
		$this->msg = $this->input['entry'][0]['messaging'][0]['message'];
		$this->sender = $this->input['entry'][0]['messaging'][0]['sender']['id'];
		$this->message = $this->input['entry'][0]['messaging'][0]['message']['text'];
	}

	/**
	 * Greeting and farewell from the bot.
	 *
	 * @access public
	 *
	 * @param -----
	 * @return true if it recognizes the greeting or farewell, false otherwise.
	 */
	public function greeting(){

		$greetings = array("hi", "hallo", "hello", "good morning", "good afternoon", "good evening");
		$saludos = array("hola", "buenos dias", "buenas tardes", "buenas noches");
		$farewells = array("see you", "bye", "good bye", "good night", "see you later", "see you tomorrow", "see you soon");
		$despedidas = array("nos vemos", "adios", "te cuidas", "hasta pronto");


		//=====Greeting=====
		if( in_array( strtolower($this->message), $greetings ) || in_array( strtolower($this->message), $saludos ) ){
			
			$this->send_response('Hola :)');
			return true;

		//=====Farewells=====
		}elseif( in_array( strtolower($this->message), $farewells ) || in_array( strtolower($this->message), $despedidas ) ){
			
			$this->send_response('bye bye :)');
			return true;

		}else{
			return false;
		}

	}

	/**
	 * Write the current weather.
	 *
	 * @access public
	 *
	 * @param -----
	 * @return true if it recognizes that the weather is requested, false otherwise.
	 */
	public function weather(){

		if( preg_match('[clima|temperatura|weather]', strtolower($this->message)) ){
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
				"Mostly Cloudy" => "Mayormente nublado",
				"Partly Cloudy" => "Parcialmente nublado",
			);

			$weather_advice = array(
				"Hot" => "Acuerdate del bloqueador",
				"Warm" => "Acuerdate del bloqueador",
				"Cold" => "No se te olvide el abrigo",
				"Sunny" => "Acuerdate del bloqueador",
				"Cloudy" => "No se te olvide el paraguas",
				"Mostly Cloudy" => "No se te olvide el paraguas",
				"Partly Cloudy" => "No se te olvide el paraguas",
			);

			$this->send_response("Temperatura Ciudad de México:");
			$this->send_response(number_format($celsius, 2) . " ° C. ");

			if( array_key_exists($condition->text, $weather)){
				$this->send_response($weather[$condition->text]);
				$this->send_response($weather_advice[$condition->text]);
			}else{
				$this->send_response($condition->text);
			}

			return true;
			
		
		}else{
			return false;
		}

	}

	/**
	 * Write the current time.
	 *
	 * @access public
	 *
	 * @param -----
	 * @return true if it recognizes that the time is requested, false otherwise.
	 */
	public function current_time(){

		if(preg_match('[time|current time|now|hora|fecha]', strtolower($this->message))) {
			// Make request to Time API
			ini_set('user_agent','Mozilla/4.0 (compatible; MSIE 6.0)');
			$result = file_get_contents("http://www.timeapi.org/utc/now?format=%25a%20%25b%20%25d%20%25I:%25M:%25S%20%25Y");
			if($result != '') {
				$message_to_reply = $result;
			}

			$this->send_response($message_to_reply);

			return true;

		}else{
			return false;
		}

	}

	
	/**
	 * Sends the response to the user.
	 *
	 * @access public
	 *
	 * @param  String - $message_to_reply
	 * @return void.
	 */
	public function send_response($message_to_reply){

		$url = 'https://graph.facebook.com/v2.6/me/messages?access_token='.$this->access_token;
		//Initiate cURL.
		$ch = curl_init($url);
		//The JSON data.
		$jsonData = '{
			"recipient":{
				"id":"'.$this->sender.'"
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
		if(!empty($this->msg)){
		    $result = curl_exec($ch);
		}

	}

	/**
	 * Split the message to send in paragraphs of 320 characters.
	 *
	 * @access public
	 *
	 * @param  String - $msg
	 * @return void.
	 */
	public function shorten_response($msg){
		$msg_max_len = 320;
		$msg_len = strlen($msg);
		if( $msg_len <= $msg_max_len ){
			$this->send_response($msg);
		}else{
			$num_rep = ceil($msg_len / $msg_max_len);
			for ($i = 0 ; $i < $num_rep ; $i++){
				$tmp_msg = substr($msg, $i*$msg_max_len, $msg_max_len);
				$this->send_response($tmp_msg);
			}	
		}
	}

	/**
	 * Clean the text to send of quotes, spaces and line breaks.
	 *
	 * @access public
	 *
	 * @param  String - $msg
	 * @return String.
	 */
	public function clean_string($string){

		$string = trim($string); //Eliminar espacios en blanco al inicio y al final
		$string = str_replace("\'","",$string); //Eliminar las comillas simples (')
		$string = str_replace('\"',"",$string); //Eliminar las comillas dobles (")
		$string = str_replace("\r", " ", $string); //Eliminar retornos de carro
		$string = str_replace("   "," ",$string); //Quitar espacios multiples
		$string = str_replace("  "," ",$string);

		return $string;
	}

	/**
	 * Sends a message when he doesn't understand the request.
	 *
	 * @access public
	 *
	 * @param  ---
	 * @return Void.
	 */
	public function whoops_message(){

		$this->send_response('No entiendo lo que dices :(');
		$this->send_response('Quieres que te diga el clima o la hora');

	}

}