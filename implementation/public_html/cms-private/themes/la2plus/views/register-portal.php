<?php
/** New registration presentation; the native RegisterForm and account workflow are retained. */
$this->pageTitle='LA2PLUS — Создать аккаунт';
$e=function($value){return CHtml::encode((string)$value);};
?>
<div class="auth-register">
<span class="auth-eyebrow">НАЧНИ СВОЁ ПРИКЛЮЧЕНИЕ</span>
<h2>Создать аккаунт</h2>
<p class="auth-intro">Один аккаунт для игры и личного кабинета LA2PLUS.</p>
<?php if($downloadToken=user()->getFlash('download-token')){ $downloadUrl=$this->createUrl('/register/default/downloadRegisterData',['token'=>$downloadToken]); ?>
<aside class="auth-register-success" role="status">Аккаунт создан. <a href="<?php echo $e($downloadUrl); ?>">Сохранить данные для входа</a></aside>
<script>setTimeout(function(){window.location.href=<?php echo json_encode($downloadUrl,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>},1000);</script>
<?php } ?>
<?php $this->widget('app.widgets.FlashMessages.FlashMessages'); ?>
<?php if(!$model->gs_list){ ?><p class="auth-register-message"><?php echo $e(Yii::t('auth','noServersReg')); ?></p>
<?php }else{ $form=$this->beginWidget('ActiveForm',['id'=>'register-form','htmlOptions'=>['class'=>'auth-register-form']]); ?>
<?php echo $form->errorSummary($model); ?>
<?php if(count($model->gs_list)>1){ ?>
<label for="RegisterForm_gs_id">Сервер</label>
<?php echo $form->dropDownList($model,'gs_id',CHtml::listData($model->gs_list,'id','name'),['required'=>true]); ?>
<?php }else echo CHtml::activeHiddenField($model,'gs_id'); ?>
<div class="auth-register-login <?php echo config('prefixes.allow')?'has-prefix':''; ?>">
<?php if(config('prefixes.allow')){ ?><div><label for="RegisterForm_prefix">Префикс</label><?php echo $form->dropDownList($model,'prefix',$model->getPrefixes(),['required'=>true]); ?></div><?php } ?>
<div><label for="RegisterForm_login">Логин</label><?php echo $form->textField($model,'login',['autocomplete'=>'username','placeholder'=>'Имя вашего аккаунта','required'=>true,'minlength'=>Users::LOGIN_MIN_LENGTH,'maxlength'=>Users::LOGIN_MAX_LENGTH,'aria-describedby'=>'register-login-hint']); ?></div>
</div>
<p class="auth-field-hint" id="register-login-hint">От <?php echo (int)Users::LOGIN_MIN_LENGTH; ?> до <?php echo (int)Users::LOGIN_MAX_LENGTH; ?> символов: <?php echo $e(Users::LOGIN_REGEXP); ?></p>
<p class="auth-register-preview">Игровой логин: <strong data-register-login-preview>Укажите логин</strong></p>
<label for="RegisterForm_email">Электронная почта</label>
<?php echo CHtml::activeEmailField($model,'email',['autocomplete'=>'email','placeholder'=>'you@example.com','required'=>true,'maxlength'=>254]); ?>
<label for="RegisterForm_password">Пароль</label>
<div class="auth-password"><?php echo $form->passwordField($model,'password',['value'=>'','autocomplete'=>'new-password','required'=>true,'minlength'=>Users::PASSWORD_MIN_LENGTH,'maxlength'=>Users::PASSWORD_MAX_LENGTH,'placeholder'=>'От '.Users::PASSWORD_MIN_LENGTH.' до '.Users::PASSWORD_MAX_LENGTH.' символов']); ?><button type="button" data-show-password="RegisterForm_password" aria-pressed="false">Показать</button></div>
<label for="RegisterForm_re_password">Повторите пароль</label>
<div class="auth-password"><?php echo $form->passwordField($model,'re_password',['value'=>'','autocomplete'=>'new-password','required'=>true,'minlength'=>Users::PASSWORD_MIN_LENGTH,'maxlength'=>Users::PASSWORD_MAX_LENGTH,'placeholder'=>'Введите пароль ещё раз']); ?><button type="button" data-show-password="RegisterForm_re_password" aria-pressed="false">Показать</button></div>
<div class="auth-generate-row"><button type="button" class="auth-generate" data-register-generate data-length="<?php echo (int)Users::PASSWORD_MAX_LENGTH; ?>">Сгенерировать пароль</button><span class="auth-field-hint" data-register-password-notice role="status"></span></div>
<?php if(config('referral_program.allow')){ ?><label for="RegisterForm_referer">Реферальный код <span class="auth-optional">необязательно</span></label><?php echo $form->textField($model,'referer',['autocomplete'=>'off','maxlength'=>Users::REFERER_MAX_LENGTH,'placeholder'=>'Код пригласившего игрока']); } ?>
<?php if(CCaptcha::checkRequirements()&&config('register.captcha.allow')){ ?>
<label for="RegisterForm_verifyCode">Код с картинки</label><div class="auth-captcha"><?php echo $form->textField($model,'verifyCode',['required'=>true,'autocomplete'=>'off','placeholder'=>'Введите код','maxlength'=>20]); ?><div><?php $this->widget('CCaptcha',['id'=>'register-form-captcha','imageOptions'=>['alt'=>'Код подтверждения','title'=>'Нажмите, чтобы обновить код']]); ?></div></div>
<?php } ?>
<label class="auth-agree" for="RegisterForm_agree"><?php echo $form->checkBox($model,'agree',['value'=>'1','uncheckValue'=>'0','required'=>true]); ?><span><?php echo Yii::t('auth','agree',['{terms}'=>CHtml::link(e(Yii::t('main','footer.terms')),['/index/default/terms'],['rel'=>'noopener']),'{privacy}'=>CHtml::link(e(Yii::t('main','footer.privacy')),['/index/default/privacy'],['rel'=>'noopener'])]); ?></span></label>
<?php echo $form->error($model,'agree'); ?>
<?php if(app()->params['staging']??false){ ?><p class="auth-register-message">Тестовый просмотр формы. Регистрация аккаунтов доступна на <a href="https://la2plus.com/register/">основном сайте</a>.</p><?php } ?>
<button type="submit" class="auth-submit" <?php if(app()->params['staging']??false)echo 'disabled'; ?>>Создать аккаунт <span aria-hidden="true">→</span></button>
<?php $this->endWidget(); } ?>
<p class="auth-register-signin">Уже играете с нами? <a href="/login/">Войти в кабинет</a></p>
</div>
<script>(()=>{const login=document.getElementById('RegisterForm_login'),prefix=document.getElementById('RegisterForm_prefix'),preview=document.querySelector('[data-register-login-preview]');document.querySelector('.auth-register')?.addEventListener('click',event=>{const button=event.target.closest?.('[data-dismiss="alert"]');if(button)button.closest('.alert')?.remove()});if(!login||!preview)return;const update=()=>{preview.textContent=login.value.trim()?((prefix?.value||'')+login.value.trim()).toLowerCase():'Укажите логин'};login.addEventListener('input',update);prefix?.addEventListener('change',update);update();const generate=document.querySelector('[data-register-generate]');generate?.addEventListener('click',()=>{const first=document.getElementById('RegisterForm_password'),repeat=document.getElementById('RegisterForm_re_password'),notice=document.querySelector('[data-register-password-notice]');if(!window.crypto?.getRandomValues){notice.textContent='Введите свой пароль.';return}const alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789',limit=Math.floor(256/alphabet.length)*alphabet.length,length=Math.min(128,Math.max(6,Number(generate.dataset.length)||16));let value='';while(value.length<length){const bytes=crypto.getRandomValues(new Uint8Array(32));for(const byte of bytes){if(byte<limit)value+=alphabet[byte%alphabet.length];if(value.length===length)break}}first.value=repeat.value=value;first.dispatchEvent(new Event('input',{bubbles:true}));repeat.dispatchEvent(new Event('input',{bubbles:true}));notice.textContent='Пароль создан. Сохраните его.'})})();</script>
