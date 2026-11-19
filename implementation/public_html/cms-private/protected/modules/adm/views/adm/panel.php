<!DOCTYPE html>
<html lang="ru" data-theme="aurum">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?php echo CHtml::encode(Yii::app()->getRequest()->getCsrfToken()); ?>">
<meta name="start-route" content="<?php echo CHtml::encode(isset($route) ? $route : 'home'); ?>">
<meta name="csrf-name" content="<?php echo CHtml::encode(Yii::app()->getRequest()->csrfTokenName); ?>">
<title>LA2PLUS — Панель управления</title>
<?php
/* Адреса статики с версией по времени правки: правка файла сразу видна
   в браузере, без «обновите страницу с очисткой кэша». */
$assetRoot = $this->assetRoot();
$asset = function ($rel) use ($assetRoot) {
    $file = $assetRoot . '/' . $rel;
    $v = @filemtime($file);
    return '/admin/asset?p=' . $rel . ($v ? '&v=' . $v : '');
};
?>
<link rel="stylesheet" href="<?= $asset('css/base.css') ?>">
<link rel="stylesheet" href="<?= $asset('css/themes.css') ?>">
<link rel="stylesheet" href="<?= $asset('css/app.css') ?>">
<style>
  /* Экран загрузки: панель показывает его, пока не пришло состояние. */
  .boot { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center;
          flex-direction: column; gap: 18px; background: #0b0c10; color: #e8d9b0; z-index: 9999;
          font: 500 15px/1.4 -apple-system, "Segoe UI", Roboto, sans-serif; }
  .boot__ring { width: 46px; height: 46px; border-radius: 50%; border: 3px solid rgba(232, 217, 176, .18);
                border-top-color: #e0b354; animation: bootSpin .9s linear infinite; }
  .boot__err { max-width: 520px; text-align: center; color: #f0a9a9; font-size: 14px; line-height: 1.5; padding: 0 20px; }
  .boot__err b { color: #fff; }
  .boot__retry { margin-top: 6px; background: #e0b354; color: #16171c; border: 0; border-radius: 10px;
                 padding: 10px 20px; font-weight: 700; font-size: 14px; cursor: pointer; }
  @keyframes bootSpin { to { transform: rotate(360deg); } }
</style>
<link rel="stylesheet" href="<?php echo BrandLogo::STYLES; ?>">
<style>.side__logo .side__mark{width:100%;height:auto;display:flex;justify-content:center}.side__logo .brand-logo{width:158px;max-width:100%}.side__logo .side__word{display:none}.gw__top{gap:14px}.gw__top>div{min-width:0}.gw__s{overflow-wrap:anywhere}</style>
<script>window.LA2PLUS_BRAND_HTML=<?php echo json_encode(BrandLogo::html('nav'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;</script>
</head>
<body>

<div class="boot" id="boot">
  <div class="boot__ring"></div>
  <div>Собираем данные с сервера…</div>
  <div class="boot__err" id="bootErr" hidden></div>
</div>

<div class="aura" aria-hidden="true">
  <div class="aura__blob aura__blob--logo" id="auraLogo"></div>
  <div class="aura__blob aura__blob--b"></div>
  <div class="aura__blob aura__blob--c"></div>
  <div class="aura__grain"></div>
</div>

<div class="shell">
  <aside class="side is-open" id="side">
    <div class="side__logo" id="sideLogo" title="LA2PLUS — свернуть/развернуть меню">
      <span class="side__mark"><span class="brand-mark" id="brandMark"></span></span>

    </div>

    <a class="side__item is-active" data-go="home">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/><path d="M10 21v-6h4v6"/></svg>
      <span>Пульт</span></a>
    <a class="side__item" data-go="players">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M2.8 20c0-3.4 2.8-5.6 6.2-5.6s6.2 2.2 6.2 5.6"/><path d="M16.5 5.2a3 3 0 0 1 0 5.6"/><path d="M18 14.6c2 .6 3.2 2.2 3.2 4.4"/></svg>
      <span>Игроки</span></a>
    <a class="side__item" data-go="accounts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="4"/><path d="m11 11 9 9"/><path d="m16.5 16.5 2-2"/><path d="m14 14 1.8-1.8"/></svg>
      <span>Аккаунты сервера</span></a>
    <a class="side__item" data-go="characters">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3.6 2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.8l5.9-.8z"/></svg>
      <span>Персонажи</span></a>
    <a class="side__item" data-go="clans">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5 7 12l5-6 5 6 4-3.5-2 10H5z"/><path d="M5 18.5h14"/></svg>
      <span>Кланы</span></a>
    <a class="side__item" data-go="game">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h4l9 9-4 4-9-9z"/><path d="m14 18 4 4"/><path d="M20 4h-4l-3.5 3.5"/><path d="m6 18 3-3"/></svg>
      <span>Игровые действия</span></a>
    <a class="side__item" data-go="finance">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="7.5" rx="7" ry="3.2"/><path d="M5 7.5v9c0 1.8 3.1 3.2 7 3.2s7-1.4 7-3.2v-9"/><path d="M5 12c0 1.8 3.1 3.2 7 3.2s7-1.4 7-3.2"/></svg>
      <span>Платежи</span><b class="side__badge" id="badgePays">0</b></a>
    <a class="side__item" data-go="tickets">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="3.4"/><path d="m6 6 3.7 3.7M18 6l-3.7 3.7M6 18l3.7-3.7M18 18l-3.7-3.7"/></svg>
      <span>Тикеты</span><b class="side__badge" id="badgeTickets">0</b></a>
    <a class="side__item" data-go="staff">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v6c0 4.2 3 7.4 7 9 4-1.6 7-4.8 7-9V6z"/><path d="m9 12 2.2 2.2L15.5 10"/></svg>
      <span>Сотрудники</span></a>
    <a class="side__item" data-go="analytics">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 5.5H5.2v1.8A3.3 3.3 0 0 0 8 10.6"/><path d="M16 5.5h2.8v1.8a3.3 3.3 0 0 1-2.8 3.3"/><path d="M12 13v3"/><path d="M8.5 20h7l-.7-4h-5.6z"/></svg>
      <span>Аналитика</span></a>
    <a class="side__item" data-go="log">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1"/><path d="M3.5 4.5V9H8"/><path d="M12 7.8V12l3 2"/></svg>
      <span>Журнал</span></a>
    <a class="side__item" data-go="settings">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.2"/><path d="M12 2.8v2.4M12 18.8v2.4M4.5 12H2.1M21.9 12h-2.4M6.1 6.1 4.4 4.4M19.6 19.6l-1.7-1.7M17.9 6.1l1.7-1.7M4.4 19.6l1.7-1.7"/></svg>
      <span>Настройки</span></a>

    <div class="side__foot">
      <div class="side__sep"></div>
      <a class="side__toggle" id="sideToggle">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        <span>Свернуть меню</span></a>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div class="topbar__crumbs">Панель <span class="mut">/</span> <span id="crumb"><b>Пульт</b></span></div>
      <div class="grow"></div>

      <div class="search" style="max-width:300px" id="globalSearch">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
        <input id="globalQ" placeholder="Игрок, аккаунт, заявка, персонаж" autocomplete="off">
        <kbd>Ctrl K</kbd>
      </div>

      <div class="chips" id="topChips">
        <div class="chip ping" data-chip="online"><i></i>Онлайн <b id="chipOnline">—</b></div>
        <div class="chip is-warn" data-chip="tickets"><i></i>Тикеты <b id="chipTickets">—</b></div>
        <div class="chip is-info" data-chip="pays"><i></i>Проверка <b id="chipPays">—</b></div>
      </div>

      <div class="areamenu" id="areaMenu">
        <a class="areamenu__i" href="/cabinet/">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/><path d="M10 21v-6h4v6"/></svg>
          <span>Кабинет</span></a>
        <a class="areamenu__i is-on" href="#" data-area="panel" aria-current="page">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3.5" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="3.5" width="7.5" height="7.5" rx="1.6"/><rect x="3" y="13" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="13" width="7.5" height="7.5" rx="1.6"/></svg>
          <span>Панель управления</span></a>
      </div>

      <div class="me" id="meWrap">
        <button class="me__btn" id="meBtn" title="Профиль, роль и вход">
          <span class="me__av" id="meAvatar">—</span>
          <span class="me__who">
            <span class="me__login" id="meLogin">—</span>
            <span class="me__role" id="meRole">—</span>
          </span>
          <svg class="me__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="me__menu" id="meMenu" hidden></div>
      </div>
    </div>

    <div class="content">
      <div id="view" class="view is-on"></div>
    </div>
  </div>
</div>

<script src="<?= $asset('js/data.js') ?>"></script>
<script src="<?= $asset('js/api.js') ?>"></script>
<script src="<?= $asset('js/core.js') ?>"></script>
<script src="<?= $asset('js/views.js') ?>"></script>
<script src="<?= $asset('js/shell.js') ?>"></script>

<script>
/* ==========================================================================
   Запуск: сначала состояние с сервера, потом интерфейс.
   Прототип рисовал всё сразу из localStorage — здесь ждём данные, иначе
   экраны открывались бы с пустыми таблицами и «прыгали» при загрузке.
   ========================================================================== */
(function () {
  var boot = document.getElementById('boot');
  var errBox = document.getElementById('bootErr');

  function fail(e) {
    var box = document.getElementById('boot');
    if (!box) return;
    var ring = box.querySelector('.boot__ring');
    if (ring) ring.style.display = 'none';
    box.firstChild.nextSibling.textContent = '';
    errBox.hidden = false;
    errBox.innerHTML = '<b>Данные не загрузились.</b><br>' + String(e && e.message ? e.message : e) +
      '<br><br><button class="boot__retry" onclick="location.reload()">Повторить</button>';
  }

  function start() {
    if (boot) boot.remove();
    document.dispatchEvent(new Event('DOMContentLoaded'));
  }

  window.APP = window.APP || {};
  window.APP.bootFail = fail;

  // Старый state() вернул «пустышку», поэтому подписка на onChange не нужна:
  // DB.load() подменяет состояние и оповещает подписчиков (см. api.js).
  DB.load().then(function () {
    start();

    /* Шапка (логин, инициалы, роль) рисуется из состояния: после загрузки
       данных её надо перерисовать, иначе там остаётся «—» и пустой логин. */
    if (window.APP && window.APP.refreshMe) window.APP.refreshMe();

    /* Адрес задаёт раздел: /admin/tickets открывает «Тикеты». */
    var want = typeof window.__routeFromPath === 'function' ? window.__routeFromPath() : 'home';
    if (want && want !== 'home' && window.APP && window.APP.go) window.APP.go(want);
  }).catch(fail);
})();
</script>
</body>
</html>
