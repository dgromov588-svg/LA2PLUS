<!doctype html>
<html lang="<?php echo CHtml::encode(app()->language); ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0b1017">
<title><?php echo CHtml::encode($this->pageTitle?:'LA2PLUS — Вход в игровой центр'); ?></title>
<link rel="icon" href="/design/assets/favicon.png"><link rel="stylesheet" href="/design/css/base.css"><link rel="stylesheet" href="/design/css/pages/portal-auth.css?v=2">
</head><body class="portal-auth">
<header class="auth-top"><a class="auth-logo" href="/">LA2<span>PLUS</span></a><a href="/rating/">Рейтинг проекта <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 18 18 6M6 6h12v12"/></svg></a></header>
<main class="auth-shell"><section class="auth-story"><span class="auth-eyebrow">LINEAGE II · INTERLUDE</span><h1>Твой следующий<br>великий <em>шаг.</em></h1><p>Собирай союзников. Управляй своими персонажами.<br>Создавай историю в мире LA2PLUS.</p><div class="auth-orbit" aria-hidden="true"><span>LA2</span><small>PLUS</small></div><div class="auth-story-foot"><span class="auth-dot"></span> Один аккаунт. Все возможности проекта.</div></section>
<section class="auth-panel"><?php echo $content; ?></section></main>
<footer class="auth-footer">© <?php echo date('Y'); ?> LA2PLUS <span>Игровой центр</span></footer>
<script>document.querySelectorAll('[data-show-password]').forEach(function(b){b.addEventListener('click',function(){var input=document.getElementById(b.dataset.showPassword),show=input.type==='password';input.type=show?'text':'password';b.setAttribute('aria-pressed',String(show));b.textContent=show?'Скрыть':'Показать'})});</script>
</body></html>
