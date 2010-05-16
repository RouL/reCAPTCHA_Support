<?php
// wcf imports
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
				case 'validate':
					$this->validate($eventObj);
					break;
					
				case 'assignVariables':
					$this->assignVariables($eventObj);
					break;
					
				case 'readParameters':
					$this->readParameters($eventObj);
			}
		}
	}
	
	private function validate($eventObj) {
		if ($this->useCaptcha) {
			ReCaptchaUtil::validateAnswer();
			$this->useCaptcha = false;
		}
	}
	
	private function assignVariables($eventObj) {
		if ($this->useCaptcha) {
			// we need a positive (true) captchaID for showing the captcha fields.
			$eventObj->captchaID = true;
			
			WCF::getTPL()->assign(array(
				'reCaptchaPublicKey' => ReCaptchaUtil::getPublicKey(),
				'reCaptchaLanguage' => ReCaptchaUtil::getLanguageCode(),
				'reCaptchaUseSSL' => ReCaptchaUtil::useSSL(),
			));
			
		}
	}
	
	private function readParameters($eventObj) {
		$this->useCaptcha = $eventObj->useCaptcha;
		// deactivate original captcha completely
		$eventObj->useCaptcha = false;
		
		if (WCF::getUser()->userID || WCF::getSession()->getVar('captchaDone')) {
			$this->useCaptcha = false;
		}
	}
}
?>