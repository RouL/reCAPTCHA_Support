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
	protected $forcedCaptcha = false;
	
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
				if (!$this->forcedCaptcha) $this->useCaptcha = false;
			}
			catch (UserInputException $e) {
				if ($eventObj instanceof RegisterForm) {
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
		// update state of use Captcha in the corresponding form
		$eventObj->useCaptcha = (($this->forcedCaptcha) ? false : $this->useCaptcha);
		
		if ($this->useCaptcha) {
			// we need a positive (true) captchaID for showing the captcha fields.
			
			WCF::getTPL()->assign(array(
				'reCaptchaPublicKey' => ReCaptchaUtil::getPublicKey(),
				'reCaptchaLanguage' => ReCaptchaUtil::getLanguageCode(),
				'reCaptchaUseSSL' => ReCaptchaUtil::useSSL(),
				'forcedCaptcha' => $this->forcedCaptcha,
			));
			
			if(!$this->forcedCaptcha) {
				$eventObj->captchaID = true;
			}
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
			
			// workaround for for WBBs FaileLoginListener
			if (defined('FAILED_LOGIN_IP_CAPTCHA') && FAILED_LOGIN_IP_CAPTCHA > 0) {
				require_once(WCF_DIR.'lib/data/user/login/FailedLogin.class.php');
				$failedLogins = FailedLogin::countFailedLogins();
				if ($failedLogins >= FAILED_LOGIN_IP_CAPTCHA) {
					$this->useCaptcha = true;
					$this->forcedCaptcha = true;
					
					if (isset($_POST['captchaID'])) {
						// THIS is really dirty, but it is impossible to make a better workaround for this
						require_once(WCF_DIR.'lib/data/image/captcha/Captcha.class.php');
						$captcha = new Captcha(intval($_POST['captchaID']));
						$_POST['captchaString'] = $captcha->captchaString;
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
		
		if (WCF::getUser()->userID || WCF::getSession()->getVar('reCaptchaDone') && !$this->forcedCaptcha) {
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