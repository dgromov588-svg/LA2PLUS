<?php $this->pageTitle='LA2PLUS — Вход в игровой центр'; ?>
<span class="auth-eyebrow">ДОБРО ПОЖАЛОВАТЬ ДОМОЙ</span>
<h2>Войти в кабинет</h2><p class="auth-intro">Всё, что нужно твоему персонажу — рядом.</p>
<?php echo CHtml::beginForm($this->createUrl('/login/default/index'),'post',['id'=>'portal-login-form']); ?>
<?php echo CHtml::errorSummary($model,'Проверьте данные для входа:'); ?>
<label for="LoginForm_login">Логин или email</label>
<?php echo CHtml::activeTextField($model,'login',['id'=>'LoginForm_login','autocomplete'=>'username','placeholder'=>'Ваш логин или почта','required'=>true,'maxlength'=>254]); ?>
<div class="auth-label-row"><label for="LoginForm_password">Пароль</label><a href="/forgotten-password/">Забыли пароль?</a></div>
<div class="auth-password"><?php echo CHtml::activePasswordField($model,'password',['id'=>'LoginForm_password','value'=>'','autocomplete'=>'current-password','placeholder'=>'От 6 до 16 символов','required'=>true,'minlength'=>6,'maxlength'=>16]); ?><button type="button" data-show-password="LoginForm_password" aria-pressed="false">Показать</button></div>
<?php $servers=$model->getGsList(); if (count($servers)>1) { ?><label for="LoginForm_gs_id">Сервер</label><?php echo CHtml::activeDropDownList($model,'gs_id',CHtml::listData($servers,'id','name'),['id'=>'LoginForm_gs_id']); } else echo CHtml::activeHiddenField($model,'gs_id'); ?>
<button type="submit" class="auth-submit">Войти в игровой центр <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 18 18 6M6 6h12v12"/></svg></button>
<?php echo CHtml::endForm(); ?>
<p class="auth-security"><span aria-hidden="true">◇</span> Безопасный вход по HTTPS</p>
<?php if (app()->params['staging']??false) { ?><aside class="auth-test-note"><strong>Тестовая площадка</strong><p>Используйте тестовый аккаунт из данных доступа. Реальные деньги и игровые предметы здесь не используются.</p></aside><?php } ?>
