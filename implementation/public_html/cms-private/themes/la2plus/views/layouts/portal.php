<?php $legacy=strtolower($this->getId())!=='portal'; ?>
<!doctype html>
<html lang="<?php echo CHtml::encode(app()->language); ?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#080a0d"><meta name="robots" content="noindex,nofollow">
  <title><?php echo CHtml::encode($this->getPageTitle()); ?></title>
  <link rel="icon" href="/design/assets/favicon.png"><link rel="manifest" href="/manifest.json">
  <link rel="stylesheet" href="/design/css/base.css?v=20261026-update">
  <link rel="stylesheet" href="/design/css/components/buttons.css?v=20261026-update">
  <link rel="stylesheet" href="/design/css/pages/cms.css?v=20261026-update">
  <link rel="stylesheet" href="/design/css/pages/pwa.css">
  <link rel="stylesheet" href="/design/css/pages/portal.css?v=1">
  <style>body.portal-page{margin:0;background:linear-gradient(180deg,rgba(7,10,18,.68),rgba(7,10,18,.95)),url('/design/assets/images/env-hero-2.jpg') center top/cover fixed}.portal-legacy{max-width:1400px;margin:auto;padding:0 24px 50px}.portal-legacy-nav{padding-bottom:0}.portal-legacy-nav .portal-nav{margin-bottom:0}body.portal-page>.portal-footer{text-align:center;color:#858b96;font-size:.75rem;padding:20px}.portal-language{display:flex;justify-content:flex-end;gap:10px;max-width:1400px;margin:auto;padding:16px 24px 0}.portal-language button{background:none;border:0;color:#d0b170;cursor:pointer;font-size:.7rem}.portal-language+.portal{padding-top:20px}</style>
</head>
<body class="portal-page">
  <?php $this->widget('app.widgets.UserNotifications.UserNotifications'); ?>
  <div class="portal-language" aria-label="Language"><?php foreach (['ru'=>'RU','en'=>'EN','ua'=>'UA'] as $code=>$label) { ?><button type="button" data-portal-language="<?php echo $code; ?>"><?php echo $label; ?></button><?php } ?></div>
  <?php if ($legacy) { ?><div class="portal portal-legacy-nav"><?php $this->renderPartial('//cabinet/portal-nav'); ?></div><main class="portal-legacy"><?php echo $content; ?></main><?php } else echo $content; ?>
  <footer class="portal-footer">© <?php echo date('Y'); ?> LA2PLUS · <a href="/">На сайт</a> · <a href="/cabinet/tickets/">Поддержка</a></footer>
  <script>document.querySelectorAll('[data-portal-language]').forEach(function(b){b.addEventListener('click',function(){document.cookie='lang='+b.dataset.portalLanguage+'; path=/; max-age=31536000; samesite=lax';location.reload()})});</script>
  <script src="/design/js/pwa.js" defer></script>
</body></html>
