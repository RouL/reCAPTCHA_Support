			{* SVN-ID: $Id$ *}
			<div class="formField">
				<script type="text/javascript">
				//<![CDATA[
					var RecaptchaOptions = {
						theme : '{RECAPTCHA_THEME}',
						lang : '{@$reCaptchaLanguage}'
					};
				//]]>
				</script>
				<script type="text/javascript" src="http{if $reCaptchaUseSSL}s{/if}://api.recaptcha.net/challenge?k={$reCaptchaPublicKey}"></script>
				
				<noscript>
					<iframe src="http{if $reCaptchaUseSSL}s{/if}://api.recaptcha.net/noscript?k={$reCaptchaPublicKey}" height="300" width="500" frameborder="0"></iframe><br />
					<textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
					<input type="hidden" name="recaptcha_response_field" value="manual_challenge" />
				</noscript>
				{if $errorField == 'captchaString'}
					<p class="innerError">
						{if $errorType == 'empty'}{lang}wcf.global.error.empty{/lang}{/if}
						{if $errorType == 'false'}{lang}wcf.captcha.error.captchaString.false{/lang}{/if}
					</p>
				{/if}
			</div>
