<?php
// wcf imports
require_once(WCF_DIR.'lib/data/image/captcha/Captcha.class.php');
require_once(WCF_DIR.'lib/system/event/EventListener.class.php');

/**
 * Replaces the original captcha with a reCaptcha captcha if enabled.
 * 
 * @author		Markus Bartz <roul@codingcorner.info>
 * @copyright	2010 Coding Corner
 * @license		GNU Lesser General Public License <http://www.gnu.org/licenses/lgpl.html>
 * @package		info.codingcorner.wcf.recaptcha
 * @subpackage	system.event.listener
 * @category	reCaptcha Support
 * @version		$Id$
 */
class CaptchaFormReCaptchaListener implements EventListener {
	private $useCaptcha = false;
	
	/**
	 * @see EventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName) {
		if (MODULE_SYSTEM_RECAPTCHA && RECAPTCHA_PRIVATEKEY != '' && RECAPTCHA_PUBLICKEY != '') {
			switch ($eventName) {
				case 'readParameters':
					$this->readParametersEvent($eventObj, $className);
					break;
				
				case 'validate':
					$this->validateEvent($eventObj, $className);
					break;
				
				case 'save':
					$this->saveEvent();
					break;
					
				case 'assignVariables':
					$this->assignVariablesEvent($eventObj);
					break;
				
			}
		}
	}
	
	private function validateEvent($eventObj, $className) {
		if ($this->useCaptcha) {
			try {
				ReCaptchaUtil::validateAnswer();
				$this->useCaptcha = false;
			}
			catch (UserInputException $e) {
				if ($className == 'RegisterForm') {
					$eventObj->errorType[$e->getField()] = $e->getType();
				}
				else {
					throw $e;
				}
			}
		}
	}
	
	private function assignVariablesEvent($eventObj) {
		if ($this->useCaptcha) {
			// we need a positive (true) captchaID for showing the captcha fields.
			$eventObj->captchaID = true;
			
			WCF::getTPL()->assign(array(
				'reCaptchaEnabled' => $this->useCaptcha,
				'reCaptchaPublicKey' => ReCaptchaUtil::getPublicKey(),
				'reCaptchaLanguage' => ReCaptchaUtil::getLanguageCode(),
				'reCaptchaUseSSL' => ReCaptchaUtil::useSSL(),
			));
			
		}
	}
	
	private function readParametersEvent($eventObj, $className) {
		// deactivate original captcha
		WCF::getSession()->register('captchaDone', true);
		
		if ($className = 'UserLoginForm' && LOGIN_USE_CAPTCHA) {
			$this->useCaptcha = true;
		}
		elseif ($className = 'RegisterForm' && REGISTER_USE_CAPTCHA) {
			$this->useCaptcha = true;
		}
		else {
			$this->useCaptcha = $eventObj->useCaptcha;
		}
		
		if (WCF::getUser()->userID || WCF::getSession()->getVar('reCaptchaDone')) {
			$this->useCaptcha = false;
		}
	}
	
	private function saveEvent() {
		WCF::getSession()->unregister('captchaDone');
		WCF::getSession()->unregister('reCaptchaDone');
	}
}
?>