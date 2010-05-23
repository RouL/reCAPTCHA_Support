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
	protected $useCaptcha = false;
	
	/**
	 * @see EventListener::execute()
	 */
	public function execute($eventObj, $className, $eventName) {
		if (MODULE_SYSTEM_RECAPTCHA && RECAPTCHA_PRIVATEKEY != '' && RECAPTCHA_PUBLICKEY != '') {
			switch ($eventName) {
				case 'readParameters':
					$this->readParameters($eventObj, $className);
					break;
				
				case 'validate':
					$this->validate($eventObj, $className);
					break;
				
				case 'save':
					$this->save();
					break;
					
				case 'assignVariables':
					$this->assignVariables($eventObj);
					break;
				
			}
		}
	}
	
	/**
	 * Validates the captcha.
	 */
	protected function validate($eventObj, $className) {
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
	
	/**
	 * @see	Page::assignVariables()
	 */
	protected function assignVariables($eventObj) {
		if ($this->useCaptcha) {
			// we need a positive (true) captchaID for showing the captcha fields.
			$eventObj->captchaID = true;
			
			WCF::getTPL()->assign(array(
				'reCaptchaPublicKey' => ReCaptchaUtil::getPublicKey(),
				'reCaptchaLanguage' => ReCaptchaUtil::getLanguageCode(),
				'reCaptchaUseSSL' => ReCaptchaUtil::useSSL(),
			));
			
		}
		WCF::getTPL()->assign('reCaptchaEnabled', $this->useCaptcha);
	}
	
	/**
	 * Checks if we need to use a captcha and deactivates the original captcha.
	 */
	protected function readParameters($eventObj, $className) {
		// deactivate original captcha
		WCF::getSession()->register('captchaDone', true);
		
		if ($eventObj instanceof UserLoginForm) {
			if (LOGIN_USE_CAPTCHA) {
				$this->useCaptcha = true;
			}
			
			// Workaround for WCFRCS-2 <http://codingcorner.info/bugtracker/browse/WCFRCS-2>
			// We deactivate reCAPTCHA in this case, because we can't control the eventlistener.
			if (defined('FAILED_LOGIN_IP_CAPTCHA') && FAILED_LOGIN_IP_CAPTCHA > 0) {
				require_once(WCF_DIR.'lib/data/user/login/FailedLogin.class.php');
				if (FailedLogin::countFailedLogins() > FAILED_LOGIN_IP_CAPTCHA) {
					if (!($eventObj instanceof UserLoginForm) || !LOGIN_USE_CAPTCHA || WCF::getSession()->getVar('captchaDone')) {
						$this->useCaptcha = false;
					}
				}
			}
		}
		elseif ($eventObj instanceof RegisterForm && REGISTER_USE_CAPTCHA) {
			$this->useCaptcha = true;
		}
		else if (!($eventObj instanceof UserLoginForm) && !($eventObj instanceof RegisterForm)) {
			$this->useCaptcha = $eventObj->useCaptcha;
		}
		
		if (WCF::getUser()->userID || WCF::getSession()->getVar('reCaptchaDone')) {
			$this->useCaptcha = false;
		}
	}
	
	/**
	 * Reactivates captchas.
	 */
	protected function save() {
		WCF::getSession()->unregister('captchaDone');
		WCF::getSession()->unregister('reCaptchaDone');
	}
}
?>