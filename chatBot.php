<?php
require_once('env.php');
require_once('simple_html_dom.php');

class ChatBot{

	public $access_token = ACCESS_TOKEN;
	public $verify_token = VERIFY_TOKEN;
	public $challenge = '';
	public $hub_verify_token = null;

	public $input = '';

	public $messagingEvent = '';
	public $type_message_received = '';

	
	public $sender = ''; //User id
	public $recipient = ''; //Page id
	public $timestamp = ''; //Current time

	public $msg = '';
	public $mid = '';  //Message identifier
	public $seq = '';  //Message sequence number

	public $message = ''; //Text message
	public $attachments = ''; //Array containing attachment data
	public $attachmentType = '';
	public $attachmentPayload = '';

	public $message_to_reply = '';

	/**
	 * Set the sender id and the message received.
	 *
	 * @access public
	 *
	 * @param -----
	 * @return Void.
	 */
	public function set_data_input(){

		$messaging = $this->input['entry'][0]['messaging'][0];

		$this->sender = $messaging['sender']['id'];
		$this->recipient = $messaging['recipient']['id'];
		$this->timestamp = $messaging['timestamp'];

		if( isset( $messaging['message'] ) ){

			$this->messagingEvent = 'receivedMessage';
			
			$this->msg = $messaging['message'];
			$this->mid = $messaging['message']['mid'];
			$this->seq = $messaging['message']['seq'];
			
			if( isset( $messaging['message']['text'] ) )
			{
				$this->type_message_received = 'message';
				$this->message = $messaging['message']['text'];
			}
			elseif( isset( $messaging['message']['attachments'] ) ){
				
				$this->type_message_received = 'attachment';

				$this->attachments = $messaging['message']['attachments'];
				$this->attachmentType = $messaging['message']['attachments'][0]['type'];
				$this->attachmentPayload = $messaging['message']['attachments'][0]['payload'];

			}

		} elseif( isset( $messaging['postback'] ) ){
			$this->messagingEvent = 'receivedPostback';
		} elseif( isset( $messaging['delivery'] ) ){
			$this->messagingEvent = 'receivedDeliveryConfirmation';
		} else {
			$this->messagingEvent = '';
		}
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

		$this->sender_action();
		
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
	 * Respond the current time.
	 *
	 * @access public
	 *
	 * @param -----
	 * @return true if it recognizes that the time is requested, false otherwise.
	 */
	public function current_time(){

		$this->sender_action();

		$whatime = array("que hora es", "me das la hora", "que hora son", "que dia es hoy", "que fecha es hoy", "a que estamos");

		//if(preg_match('[time|current time|now|hora|fecha]', strtolower($this->message))) {
		if( in_array( strtolower($this->message), $whatime) ){

			// Make request to Time API
			ini_set('user_agent','Mozilla/4.0 (compatible; MSIE 6.0)');
			$api_time = file_get_contents("http://www.timeapi.org/cdt/now");
			if($api_time != '') {

				$date = strtotime($api_time);
				$this->send_response("Fecha y hora Zona Central (CDT) - Ciudad de México");
				$this->send_response( date("D d / M / Y", $date) );
				$this->send_response( date("h:i:s a", $date) );

				return true;
			
			}else{
				return false;
			}

		}else{
			return false;
		}

	}

	/**
	 * Respond the horoscope.
	 *
	 * @access public
	 *
	 * @param -----
	 * @return true if it recognizes the horoscope is requested, false otherwise.
	 */
	public function horoscope(){

		$this->sender_action();

		if( preg_match('[horoscopo|horoscope|zodiac]', strtolower($this->message)) ){

			$sign = 'escorpio';
			if( strpos(strtolower($this->message), "aries") != false ){ $sign = 'aries'; }
			elseif( strpos(strtolower($this->message), "tauro") != false ){ $sign = 'tauro'; }
			elseif( strpos(strtolower($this->message), "geminis") != false ){ $sign = 'geminis'; }
			elseif( strpos(strtolower($this->message), "cancer") != false ){ $sign = 'cancer'; }
			elseif( strpos(strtolower($this->message), "leo") != false ){ $sign = 'leo'; }
			elseif( strpos(strtolower($this->message), "virgo") != false ){ $sign = 'virgo'; }
			elseif( strpos(strtolower($this->message), "libra") != false ){ $sign = 'libra'; }
			elseif( strpos(strtolower($this->message), "escorpio") != false ){ $sign = 'escorpio'; }
			elseif( strpos(strtolower($this->message), "sagitario") != false ){ $sign = 'sagitario'; }
			elseif( strpos(strtolower($this->message), "capricornio") != false ){ $sign = 'capricornio'; }
			elseif( strpos(strtolower($this->message), "acuario") != false ){ $sign = 'acuario'; }
			elseif( strpos(strtolower($this->message), "piscis") != false ){ $sign = 'piscis'; }

			$search_url = "http://horoscopo.abc.es/signos-zodiaco-".$sign."/horoscopo-hoy.html";

			$html = file_get_html($search_url);
			
			$title = $html->find('h1[class=title]')[0]->plaintext;
			//$img = $html->find('header')[1]->find('img')[0]->src;
			$date = $html->find('header')[1]->find('p')[0]->plaintext;

			$prediction = $html->find('div[class=inside]')[0]->find('p')[1]->plaintext;

			$this->send_image("http://www.que.es/archivos/201502/".$sign."_nor-672xXx80.jpg");

			$this->send_response($title);
			$this->send_response($date);
			//$this->send_response($img);
			$this->shorten_response($prediction);

			return true;

		}else{

			return false;

		}

			
	}

	/**
	 * Respond the Exchange Rate.
	 *
	 * @access public
	 *
	 * @param -----
	 * @return true if it recognizes the exchange rate is requested, false otherwise.
	 */
	public function exchange_rate(){

		$this->sender_action();

		if( preg_match('[tipo de cambio|como esta el dolar|precio del dolar|precio del euro]', strtolower($this->message)) ){

			//http://fixer.io/
			$api_url = "http://api.fixer.io/latest?base=MXN";

			// Make call with cURL
			$session = curl_init($api_url);
			curl_setopt($session, CURLOPT_RETURNTRANSFER,true);
			$json = curl_exec($session);
			
			// Convert JSON to PHP object
			$phpObj =  json_decode($json);

			$base = $phpObj->base;
			$date = $phpObj->date;
			$dolar = 1/($phpObj->rates->USD);
			$euro = 1/($phpObj->rates->EUR);

			$this->send_response("Tip de cambio de hoy " . $date);
			$this->send_response("Dolar:  $ " . number_format($dolar, 2));
			$this->send_response("Euro:  $ " . number_format($euro, 2));

			return true;

		} else {
			return false;
		}

	}

	/**
	 * Answers a question (Yahoo answers).
	 *
	 * @access public
	 *
	 * @param -----
	 * @return true if it find the response, false otherwise.
	 */
	public function yahoo_answer(){

		$this->sender_action();

		try{

			$BASE_URL = "https://espanol.answers.search.yahoo.com/search";
			$search_url = $BASE_URL . "?fr=uh3_answers_vert_gs&type=2button&p=" . urlencode($this->message);

			//Get all answers
			$html = file_get_html($search_url);
			$ol = $html->find('ol[class=searchCenterMiddle]');

			if( count($ol) > 0 ){

				$li = $ol[0]->find('li[class=first]');
				$link = $li[0]->find('a');
				$new_url = $link[0]->href;

				//Get first answer
				$html = file_get_html($new_url);
				
				$question = $html->find('h1[itemprop=name]')[0]->plaintext;
				$this->shorten_response('Respuesta a: ' . trim($question));

				$div = $html->find('div[itemprop=acceptedAnswer]');

				if( count($div) > 0 ){

					$span = $div[0]->find('span[itemprop=text]');
					$answer = $span[0]->plaintext;

					//Clean answer
					$answer = $this->clean_string($answer);
					$paragraph_answer = explode("\n", $answer);

					foreach ($paragraph_answer as $p) {
						$i = 1;
						if ($p == ' '){
							continue;
						}
						$this->shorten_response(trim($p));
						$i++;
						if( $i >= 8) break;
					}

					$this->shorten_response('¿Si es lo que me preguntabas? ;)');

					return true;

				}else{
					return false;
				}

			}else{
				return false;
			}

		}catch(Exception $e){

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

	/**
	 * Configure script indicators or send read receipts to warn users who are processing your request.
	 *
	 * @access public
	 *
	 * @param  String - "typing_on" ("typing_off"  o  "mark_seen")
	 * @return void.
	 */
	public function sender_action($type='typing_on'){

		$url = 'https://graph.facebook.com/v2.6/me/messages?access_token='.$this->access_token;
		//Initiate cURL.
		$ch = curl_init($url);
		//The JSON data.
		$jsonData = '{
			"recipient":{
				"id":"'.$this->sender.'"
			},
			"sender_action": "'.$type.'",
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
		if(!empty($this->msg)){
		    $result = curl_exec($ch);
		}

	}

	/**
	 * Send an image.
	 *
	 * @access public
	 *
	 * @param  String - image url 
	 * @return void.
	 */
	public function send_image($url_img){

		$url = 'https://graph.facebook.com/v2.6/me/messages?access_token='.$this->access_token;
		//Initiate cURL.
		$ch = curl_init($url);
		//The JSON data.
		$jsonData = '{
			"recipient":{
				"id":"'.$this->sender.'"
			},
			"message":{
				"attachment":{
					"type":"image",
					"payload":{
						"url":"'.$url_img.'"
					}
				}
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

}