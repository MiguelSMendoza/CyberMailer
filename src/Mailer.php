<?php
/**
 * Helper to send Mails
 * @package     Utils
 * @subpackage  Mailer
 * @copyright Copyright (C) 2014 Runners.es
 * @version 2.0
 * @author Miguel S. Mendoza <miguel@smendoza.net>
 **/

if (!defined('DS')) define('DS',DIRECTORY_SEPARATOR);
if (!defined('MAIL_PATH')) define('MAIL_PATH', dirname(preg_replace('/\\\\/','/',__FILE__)) . '/');

class AddressType {
	const TO = 'to';
	const CC = 'cc';
	const BCC = 'bcc';
}

class Mailer {

	public $SMTP = 0;
	public $API_KEY = "API_KEY";
	public $API_URL = "https://mandrillapp.com/api/1.0/messages/send.json";
	public $Host = "smtp.mandrillapp.com";
	public $SMTPSecure = "tls";
	public $SMTPDebug = 0;
	public $SMTPAuth = true;
	public $Port = 587;
	private $Username =  "USERNAME";
	private $Password = "PASSWORD";

	public $From = "ashley@leamonde.es";
	public $FromName = "Ashley Riot";

	public $Subject = "";
	public $Body = "";

	private $Attachments = array();

	private $ReplyTo = "";

	private $Recipients = array();

	protected $mail = null;
	protected $isHTML = true;

	private function toLog($mensaje) {
		$log = MAIL_PATH.'logs'.DS.'errors.log';
		$msj = date('m/d/Y h:i:s a', time())." - ".$mensaje.PHP_EOL;
		echo $msj;
		file_put_contents($log, $msj, FILE_APPEND | LOCK_EX);
	}

	public function __construct($api="",$username="", $password="") {
		if(!empty($api)) 
			$this->API_KEY=$api;
		if(!empty($username) && !empty($password)) {
			$this->Username = $username;
			$this->Password = $password;
		}
		$this->initMailer();
	}

	private function initMailer() {
		$this->resetValues();
		if($this->SMTP)
			$this->initPHPMailer();
	}

	private function resetValues() {
		$this->Attachments = array();
		$this->Recipients = array();
		$this->ReplyTo = "";
		$this->Subject = "";
		$this->Body = "";
		$this->From = "Ashley Riot";
		$this->FromName = "ashley@leamonde.es";
	}

	private function initPHPMailer() {
		$this->mail = new PHPMailer(true);
		$this->mail->isHTML($this->isHTML);
		$this->mail->IsSMTP();
		$this->mail->Host = $this->Host;
		$this->mail->SMTPSecure = $this->SMTPSecure;
		$this->mail->SMTPDebug = $this->SMTPDebug;
		$this->mail->SMTPAuth   = $this->SMTPAuth;
		$this->mail->Port = $this->Port;
		$this->mail->Username = $this->Username;
		$this->mail->Password = $this->Password;
	}

	private function raiseError($m) {
		$this->toLog("ERROR - ".$_SERVER['REMOTE_ADDR']." ".$m);
	}

	public function isHTML($val) {
		if($val) 
			$this->isHTML = true;
		else
			$this->isHTML = false;
	}

	public function isSMTP($value) {
		if($value)
			$this->SMTP = true;
		else
			$this->SMTP = false;
	}

	public function addAttachment($attachment) {
		$fileContent = base64_encode(file_get_contents($attachment));
		$name = basename($attachment);
		$type = mime_content_type($attachment);
		$attach = array("type"=>$type, "name"=>$name, "content"=>$fileContent);

		$this->Attachments = array_merge($this->Attachments, array($attach));

		return true;
	}

	public function AddTo($mail, $name) {
		return $this->AddAddress(AddressType::TO, $mail, $name);
	}

	public function SetReplyTo($address) {
		$address = strtolower(trim($address));
		if($this->validateAddress($address)) {
			$this->ReplyTo = $address;
			return true;
		}
		return false;
	}
	
	public function Subject($subject) {
		$this->Subject=$subject;
		return true;
	}

	public function AddBCC($mail, $name) {
		return $this->AddAddress(AddressType::BCC, $mail, $name);
	}

	public function AddCC($mail, $name) {
		return $this->AddAddress(AddressType::CC, $mail, $name);
	}

	private function AddAddress($kind, $address, $name) {
		$address = strtolower(trim($address));
		$name = trim(preg_replace('/[\r\n]+/', '', $name));
		if ($this->validateAddress($address)) {
			if (!isset($this->Recipients[$address])) {
				array_push($this->Recipients, array("email"=>$address, "name"=>utf8_encode(ucwords(strtolower($name))), "type"=>$kind));
				return true;
			}
		}
		return false;
	}

	public function SetFrom($address, $name) {
		$address = strtolower(trim($address));
		$name = trim(preg_replace('/[\r\n]+/', '', $name));
		if($this->validateAddress($address)) {
			$this->From = $address;
			$this->FromName = $name;
			return true;
		}
		return false;
	}
	
	public function sendTemplate($para, $nombrePara, $from, $nombreFrom, $asunto, $template="generic", $params = array()) {
		$templater = new Templater();
		$html = $templater->getTemplate($template,$params);
		$this->isHTML(true);
		return $this->send($para, $nombrePara, $from, $nombreFrom, $asunto, $html);
	}

	public function send($para, $nombrePara, $from, $nombreFrom, $asunto, $body) {
		$this->SetFrom($from, $nombreFrom);
		$this->AddTo($para, $nombrePara);
		$this->Subject=$asunto;
		$this->Body=$body;

		return $this->SendMail();
	}

	public function SendMail() {
		if($this->SMTP) return $this->sendSMTP();
		else return $this->sendAPI();
	}

	private function setSMTPRecipients() {
		foreach($this->Recipients as $recipient) {
			switch ($recipient["type"]) {
			case AddressType::TO:
				$this->mail->AddAddress($recipient["email"], $recipient["name"]);
				break;
			case AddressType::BCC:
				$this->mail->AddBCC($recipient["email"], $recipient["name"]);
				break;
			case AddressType::CC:
				$this->mail->AddCC($recipient["email"], $recipient["name"]);
				break;
			}
		}
	}

	public function sendSMTP() {
		$sended = false;
		try {
			$this->setSMTPRecipients();
			$this->mail->SetFrom($this->From, $this->FromName);
			$this->mail->AddReplyTo($this->ReplyTo);
			$this->mail->Subject = $this->Subject;
			$this->mail->Body = $this->Body;
			$sended = $this->mail->Send();
		} catch (Exception $e) {
			$this->raiseError('Excepción capturada: '.  $e->__toString());
			$sended = false;
		}
		$this->initMailer();
		return $sended;
	}

	function escapeJsonString($value) { # list from www.json.org: (\b backspace, \f formfeed)
		$escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
		$replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
		$result = str_replace($escapers, $replacements, $value);
		return $result;
	}

	private function getCurlResponseFromURLWithPOSTParams($url, $params) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}

	private function getAPIParametersJSON() {
		$params = array (
			"key"=>$this->API_KEY,
			"message" => array(
				"html"=>$this->Body,
				"subject"=>$this->Subject,
				"from_email"=>$this->From,
				"from_name"=>$this->FromName,
				"to"=> $this->Recipients,
				"headers"=> array (
					"Reply-To"=>$this->ReplyTo
				),
				"attachments" => $this->Attachments
			)
		);
		return json_encode($params, JSON_HEX_QUOT | JSON_HEX_TAG);
	}

	private function checkJSONResponse($response) {
		$json = json_decode($response);
		if(!is_array($json)) return false;
		if ($json[0]->status=="sent" || $json[0]->status=="queued") 
			return true;
		else
			return false;
	}

	public function sendAPI() {
		$sended = false;
		try {
			$params = $this->getAPIParametersJSON();
			$response = $this->getCurlResponseFromURLWithPOSTParams($this->API_URL, $params);
			$sended = $this->checkJSONResponse($response);
			if(!$sended) $sended = $response;
		} catch (Exception $exception) {
			$this->raiseError('Excepción capturada: '.  $exception->__toString());
			$sended = false;
		}
		$this->initMailer();
		return $sended;
	}
	
	    /**
     * Check that a string looks like an email address.
     * @param string $address The email address to check
     * @param string $patternselect A selector for the validation pattern to use :
     * * `auto` Pick strictest one automatically;
     * * `pcre8` Use the squiloople.com pattern, requires PCRE > 8.0, PHP >= 5.3.2, 5.2.14;
     * * `pcre` Use old PCRE implementation;
     * * `php` Use PHP built-in FILTER_VALIDATE_EMAIL; same as pcre8 but does not allow 'dotless' domains;
     * * `html5` Use the pattern given by the HTML5 spec for 'email' type form input elements.
     * * `noregex` Don't use a regex: super fast, really dumb.
     * @return boolean
     * @static
     * @access public
     */
    private static function validateAddress($address, $patternselect = 'auto')
    {
        if (!$patternselect or $patternselect == 'auto') {
            //Check this constant first so it works when extension_loaded() is disabled by safe mode
            //Constant was added in PHP 5.2.4
            if (defined('PCRE_VERSION')) {
                //This pattern can get stuck in a recursive loop in PCRE <= 8.0.2
                if (version_compare(PCRE_VERSION, '8.0.3') >= 0) {
                    $patternselect = 'pcre8';
                } else {
                    $patternselect = 'pcre';
                }
            } elseif (function_exists('extension_loaded') and extension_loaded('pcre')) {
                //Fall back to older PCRE
                $patternselect = 'pcre';
            } else {
                //Filter_var appeared in PHP 5.2.0 and does not require the PCRE extension
                if (version_compare(PHP_VERSION, '5.2.0') >= 0) {
                    $patternselect = 'php';
                } else {
                    $patternselect = 'noregex';
                }
            }
        }
        switch ($patternselect) {
            case 'pcre8':
                /**
                 * Uses the same RFC5322 regex on which FILTER_VALIDATE_EMAIL is based, but allows dotless domains.
                 * @link http://squiloople.com/2009/12/20/email-address-validation/
                 * @copyright 2009-2010 Michael Rushton
                 * Feel free to use and redistribute this code. But please keep this copyright notice.
                 */
                return (boolean)preg_match(
                    '/^(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){255,})(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){65,}@)' .
                    '((?>(?>(?>((?>(?>(?>\x0D\x0A)?[\t ])+|(?>[\t ]*\x0D\x0A)?[\t ]+)?)(\((?>(?2)' .
                    '(?>[\x01-\x08\x0B\x0C\x0E-\'*-\[\]-\x7F]|\\\[\x00-\x7F]|(?3)))*(?2)\)))+(?2))|(?2))?)' .
                    '([!#-\'*+\/-9=?^-~-]+|"(?>(?2)(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\x7F]))*' .
                    '(?2)")(?>(?1)\.(?1)(?4))*(?1)@(?!(?1)[a-z0-9-]{64,})(?1)(?>([a-z0-9](?>[a-z0-9-]*[a-z0-9])?)' .
                    '(?>(?1)\.(?!(?1)[a-z0-9-]{64,})(?1)(?5)){0,126}|\[(?:(?>IPv6:(?>([a-f0-9]{1,4})(?>:(?6)){7}' .
                    '|(?!(?:.*[a-f0-9][:\]]){8,})((?6)(?>:(?6)){0,6})?::(?7)?))|(?>(?>IPv6:(?>(?6)(?>:(?6)){5}:' .
                    '|(?!(?:.*[a-f0-9]:){6,})(?8)?::(?>((?6)(?>:(?6)){0,4}):)?))?(25[0-5]|2[0-4][0-9]|1[0-9]{2}' .
                    '|[1-9]?[0-9])(?>\.(?9)){3}))\])(?1)$/isD',
                    $address
                );
            case 'pcre':
                //An older regex that doesn't need a recent PCRE
                return (boolean)preg_match(
                    '/^(?!(?>"?(?>\\\[ -~]|[^"])"?){255,})(?!(?>"?(?>\\\[ -~]|[^"])"?){65,}@)(?>' .
                    '[!#-\'*+\/-9=?^-~-]+|"(?>(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\xFF]))*")' .
                    '(?>\.(?>[!#-\'*+\/-9=?^-~-]+|"(?>(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\xFF]))*"))*' .
                    '@(?>(?![a-z0-9-]{64,})(?>[a-z0-9](?>[a-z0-9-]*[a-z0-9])?)(?>\.(?![a-z0-9-]{64,})' .
                    '(?>[a-z0-9](?>[a-z0-9-]*[a-z0-9])?)){0,126}|\[(?:(?>IPv6:(?>(?>[a-f0-9]{1,4})(?>:' .
                    '[a-f0-9]{1,4}){7}|(?!(?:.*[a-f0-9][:\]]){8,})(?>[a-f0-9]{1,4}(?>:[a-f0-9]{1,4}){0,6})?' .
                    '::(?>[a-f0-9]{1,4}(?>:[a-f0-9]{1,4}){0,6})?))|(?>(?>IPv6:(?>[a-f0-9]{1,4}(?>:' .
                    '[a-f0-9]{1,4}){5}:|(?!(?:.*[a-f0-9]:){6,})(?>[a-f0-9]{1,4}(?>:[a-f0-9]{1,4}){0,4})?' .
                    '::(?>(?:[a-f0-9]{1,4}(?>:[a-f0-9]{1,4}){0,4}):)?))?(?>25[0-5]|2[0-4][0-9]|1[0-9]{2}' .
                    '|[1-9]?[0-9])(?>\.(?>25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])){3}))\])$/isD',
                    $address
                );
            case 'html5':
                /**
                 * This is the pattern used in the HTML5 spec for validation of 'email' type form input elements.
                 * @link http://www.whatwg.org/specs/web-apps/current-work/#e-mail-state-(type=email)
                 */
                return (boolean)preg_match(
                    '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}' .
                    '[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/sD',
                    $address
                );
            case 'noregex':
                //No PCRE! Do something _very_ approximate!
                //Check the address is 3 chars or longer and contains an @ that's not the first or last char
                return (strlen($address) >= 3
                    and strpos($address, '@') >= 1
                    and strpos($address, '@') != strlen($address) - 1);
            case 'php':
            default:
                return (boolean)filter_var($address, FILTER_VALIDATE_EMAIL);
        }
    }

}

?>