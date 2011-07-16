<?php
/**
 * Contains reCaptcha-related functions.
 *
 * @author		Markus Bartz <roul@codingcorner.info>
 * @copyright	2010 Coding Corner
 * @license		GNU Lesser General Public License <http://www.gnu.org/licenses/lgpl.html>
 * @package		info.codingcorner.wcf.recaptcha
 * @subpackage	util
 * @category	reCaptcha Support
 * @todo		Put this into a separate package and implement mailhide specific functions.
 */
class ReCaptchaUtil {
	// supported languages (see <http://code.google.com/intl/de-DE/apis/recaptcha/docs/customization.html#i18n>)
	public static $valid_languages = array(
		'en',
		'nl',
		'fr',
		'de',
		'pt',
		'ru',
		'es',
		'tr',
	);
	
	// server data (see <http://code.google.com/intl/de-DE/apis/recaptcha/docs/verify.html>)
	const RECAPTCHA_HOST = 'www.google.com';
	const RECAPTCHA_PORT = 80;
	const RECAPTCHA_PATH = '/recaptcha/api/verify';
	
	
	// reply codes (see <http://code.google.com/intl/de-DE/apis/recaptcha/docs/verify.html>)
	const VALID_ANSWER = 'valid';
	const ERROR_UNKNOWN = 'unknown';
	const ERROR_INVALID_PUBLICKEY = 'invalid-site-public-key';
	const ERROR_INVALID_PRIVATEKEY = 'invalid-site-private-key';
	const ERROR_INVALID_COOKIE = 'invalid-request-cookie';
	const ERROR_INCORRECT_SOLUTION = 'incorrect-captcha-sol';
	const ERROR_INCORRECT_PARAMS = 'verify-params-incorrect';
	const ERROR_INVALID_REFFERER = 'invalid-referrer';
	const ERROR_NOT_REACHABLE = 'recaptcha-not-reachable';
	
	/**
	 * Returns the public key for the active domain.
	 * If only one key is given this one is returned.
	 * 
	 * @return	string
	 */
	public static function getPublicKey() {
		// check if multiple keys are given
		$pubKey = RECAPTCHA_PUBLICKEY;
		$keys = explode("\n", RECAPTCHA_PUBLICKEY);
		if (count($keys) > 1) {
			foreach ($keys as $key) {
				$keyParts = explode(':', $key);
				
				if (StringUtil::trim($keyParts[0]) == $_SERVER['HTTP_HOST']) {
					return StringUtil::trim($keyParts[1]);
				}
			}
		}
		else {
			return RECAPTCHA_PUBLICKEY;
		}
		throw new SystemException('No valid public key for reCaptcha found.');
	}

	/**
	 * Returns the private key for the active domain.
	 * If only one key is given this one is returned.
	 * 
	 * @return	string
	 */
	public static function getPrivateKey() {
		// check if multiple keys are given
		$keys = explode("\n", RECAPTCHA_PRIVATEKEY);
		if (count($keys) > 1) {
			foreach ($keys as $key) {
				$keyParts = explode(':', $key);
				
				if (StringUtil::trim($keyParts[0]) == $_SERVER['HTTP_HOST']) {
					return StringUtil::trim($keyParts[1]);
				}
			}
		}
		else {
			return RECAPTCHA_PRIVATEKEY;
		}
		throw new SystemException('No valid private key for reCaptcha found.');
	}
	
	/**
	 * Returns the language code for the reCaptcha based uppon the active language.
	 * Fall back to English if the active language is not supported by reCaptcha.
	 * @todo	implement support for custom translations in the future
	 * 
	 * @return	string
	 */
	public static function getLanguageCode() {
		return (array_search(WCF::getLanguage()->getLanguageCode(), self::$valid_languages) !== false) ? WCF::getLanguage()->getLanguageCode() : 'en';
	}
	
	/**
	 * Validates the given answer.
	 */
	public static function validateAnswer() {
		$challenge = '';
		$response = '';
		if (isset($_POST['recaptcha_challenge_field'])) $challenge = StringUtil::trim($_POST['recaptcha_challenge_field']);
		if (isset($_POST['recaptcha_response_field'])) $response = StringUtil::trim($_POST['recaptcha_response_field']);
		
		if (empty($challenge) || empty($response)) {
			throw new UserInputException('captchaString');
		}
		
		$verificationResponse = self::verify($challenge, $response);
		switch ($verificationResponse) {
			case self::VALID_ANSWER:
				break;
				
			case self::ERROR_INCORRECT_SOLUTION:
				throw new UserInputException('captchaString', 'false');
				break;
				
			case self::ERROR_NOT_REACHABLE:
				// if reCaptcha server is unreachable mark captcha as done
				// this should be better than block users until server is back.
				// - RouL
				break;
				
			default:
				throw new SystemException('reCaptcha returned the following error: '.$verificationResponse);
		}
		
		WCF::getSession()->register('reCaptchaDone', true);
	}
	
	/**
	 * Verifies the challenge and response with the recaptcha verify server.
	 * 
	 * Parts of this function are taken from lib/util/FileUtil.class.php from the
	 * WoltLab Community Framework which is licensed unter the
	 * GNU Lesser General Public License <http://www.gnu.org/licenses/lgpl.html>.
	 * More on <http://www.woltlab.com/> and <http://community.woltlab.com/>.
	 * 
	 * @param	string	$challenge
	 * @param	string	$response
	 * @return	string
	 */
	protected static function verify($challenge, $response) {
		// get proxy
		$options = array();
		if (PROXY_SERVER_HTTP) $options['http']['proxy'] = PROXY_SERVER_HTTP;
		
		require_once(WCF_DIR . 'lib/system/io/RemoteFile.class.php');
		$remoteFile = new RemoteFile(self::RECAPTCHA_HOST, self::RECAPTCHA_PORT, 30, $options); // the file to read.
		if (!isset($remoteFile)) {
			return self::ERROR_NOT_REACHABLE;
		}
		
		// build post string
		$postData = 'privatekey='.urlencode(self::getPrivateKey());
		$postData .= '&remoteip='.urlencode(UserUtil::getIpAddress());
		$postData .= '&challenge='.urlencode($challenge);
		$postData .= '&response='.urlencode($response);
		
		// build and send the http request.
		$request = "POST ".self::RECAPTCHA_PATH." HTTP/1.0\r\n";
		$request .= "User-Agent: HTTP.PHP (info.codingcorner.wcf.recaptcha; WoltLab Community Framework/".WCF_VERSION."; ".WCF::getLanguage()->getLanguageCode().")\r\n";
		$request .= "Accept: */*\r\n";
		$request .= "Accept-Language: ".WCF::getLanguage()->getLanguageCode()."\r\n";
		$request .= "Host: ".self::RECAPTCHA_HOST."\r\n";
		$request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
		$request .= "Content-Length: ".strlen($postData)."\r\n";
		$request .= "Connection: Close\r\n\r\n";
		$request .= $postData;
		$remoteFile->puts($request);
		
		$waiting = true;
		$readResponse = array();
		$reCaptchaResponse = array();
		
		// read http response.
		while (!$remoteFile->eof()) {
			$readResponse[] = $remoteFile->gets();
			// look if we are done with transferring the requested file.					 
			if ($waiting) {
				if (rtrim($readResponse[count($readResponse) - 1]) == '') {
					$waiting = false;
				}						
			}
			else {
				// look if the webserver sent an error http statuscode
				// This has still to be checked if really sufficient!
				$arrayHeader = array('201', '301', '302', '303', '307', '404');
				foreach ($arrayHeader as $code) {
					$error = strpos($readResponse[0], $code);
				}
				if ($error !== false) {
					return self::ERROR_NOT_REACHABLE;
				}
				// write to the target system.
				$reCaptchaResponse[] = $readResponse[count($readResponse) - 1];
			}
		}
		if (StringUtil::trim($reCaptchaResponse[0]) == "true") {
			return self::VALID_ANSWER;
		}
		else {
			return StringUtil::trim($reCaptchaResponse[1]);
		}
	}
	
	/**
	 * Returns true if SSL is used.
	 * 
	 * @return	bool
	 */
	public static function useSSL() {
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
			return true;
		}
		return false;
	}
}
?>
