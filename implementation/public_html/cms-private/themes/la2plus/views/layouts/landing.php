<?php
/**
 * Лендинг и страницы базы знаний / рейтинга (ссылка на них есть в шапке).
 * Выводится из layouts/master.php. Секции главной только на index/default/index, на остальных страницах
 * здесь шапка, футер и попапы, а содержимое страницы ($content) выводится как есть.
 */
$isHome = $this->route === 'index/default/index';
$home = app()->createUrl('/index/default/index');
?>
<!DOCTYPE html>
<html lang="<?php echo app()->language; ?>"<?php echo $isHome ? ' class="landing"' : ''; ?>>

<head><meta charset="utf-8">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description"
    content="<?php echo Yii::t('main', 'layout.la2plusClassicLineage2') ?>">
  <meta name="theme-color" content="#080A0D">
  <title><?php echo e($isHome ? config("meta.title") : $this->getPageTitle()); ?></title>

  <meta property="og:type" content="website">
  <meta property="og:title" content="LA2PLUS — Lineage 2 Private Server">
  <meta property="og:description"
    content="<?php echo Yii::t('main', 'layout.classicLineage2Modern') ?>">
  <meta property="og:image" content="https://la2plus.com/design/assets/images/og-image.png">
  <meta property="og:site_name" content="LA2PLUS">

  <link rel="icon" href="/design/assets/favicon.png" type="image/png">
  <link rel="apple-touch-icon" href="<?php echo BrandLogo::assetUrl(); ?>">

  <link rel="preload" href="/design/assets/images/env-hero.png" as="image">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
    rel="stylesheet">

  <?php // порядок важен (каскад): базовые стили → компоненты → страницы; адаптив лежит в файле своего компонента
  foreach (['base', 'components/buttons', 'components/header', 'components/menu', 'landing/hero', 'landing/sections', 'components/footer', 'components/overlays', 'animations', 'pages/wiki', 'pages/cms'] as $css) { ?>
  <link rel="stylesheet" href="/design/css/<?php echo $css; ?>.css?v=20261026-update">
  <?php } ?>
<meta name="la2plus-ratings-url" content="<?php echo CHtml::encode(app()->createUrl('/index/default/ratings')); ?>">
<?php // Проверка владения доменом для Trybit (добавление проекта в кабинете мерчанта) ?>
<meta name="trybit-verification" content="<?php echo CHtml::encode(defined('LA2PLUS_TRYBIT_VERIFICATION') ? LA2PLUS_TRYBIT_VERIFICATION : 'smr9zdz2crwmrkz2t26w78sr03x99r1h'); ?>">
<link rel="stylesheet" href="<?php echo BrandLogo::STYLES; ?>">
</head>

<body class="<?php echo $isHome ? 'landing' : 'cms-page cms-offset'; ?>">
  <?php if (PortalRuntime::sections()->enabled('messages')) $this->widget("app.widgets.UserNotifications.UserNotifications"); ?>
  <?php if ($isHome) { ?>
  <!-- PRELOADER -->
  <div id="preloader" class="preloader" aria-hidden="true">
    <div class="preloader__inner">
      <?php echo BrandLogo::html('preloader',true); ?>
      <div class="preloader__bar"><span class="preloader__progress"></span></div>
      <p class="preloader__text">LOADING WORLD...</p>
    </div>
  </div>
  <?php } ?>

  <canvas class="fx-particles" id="heroParticles" aria-hidden="true"></canvas>


  <!-- HEADER -->
  <header class="header" id="header">
    <div class="header__container container">
      <a href="<?php echo $home; ?>" class="header__logo">
        <?php echo BrandLogo::html('header'); ?>
      </a>

      <nav class="header__nav" id="mainNav" aria-label="<?php echo Yii::t('main', 'layout.mainNavigation') ?>">
        <a href="<?php echo $home; ?>#hero" class="header__link"><svg class="icon header__icon"><use href="/design/assets/icons/home.svg#i" /></svg><span><?php echo Yii::t('main', 'nav.home') ?></span></a>
        <a href="<?php echo $home; ?>#streams" class="header__link"><svg class="icon header__icon"><use href="/design/assets/icons/play.svg#i" /></svg><span><?php echo Yii::t('main', 'nav.streams') ?></span></a>
        <?php if (PortalRuntime::sections()->enabled('rating')) { ?>
        <a href="<?php echo $home; ?>#rating" class="header__link"><svg class="icon header__icon"><use href="/design/assets/icons/trophy.svg#i" /></svg><span><?php echo Yii::t('main', 'nav.rating') ?></span></a>
        <?php } ?>
        <button type="button" class="header__link" data-modal="play"><svg class="icon header__icon"><use href="/design/assets/icons/download.svg#i" /></svg><span><?php echo Yii::t('main', 'nav.files') ?></span></button>
        <a href="<?php echo app()->createUrl('/index/default/wiki'); ?>" class="header__link"><svg class="icon header__icon"><use href="/design/assets/icons/book.svg#i" /></svg><span><?php echo Yii::t('main', 'nav.wiki') ?></span></a>
        <a href="https://forum.la2plus.com/" class="header__link"><svg class="icon header__icon"><use href="/design/assets/icons/users.svg#i" /></svg><span><?php echo Yii::t('main', 'nav.forum') ?></span></a>
        <div class="header__mobile">
          <?php if (user()->isGuest) { ?>
            <a class="btn btn--gold" href="<?php echo app()->createUrl('/register/default/index'); ?>"><?php echo Yii::t('main', 'btn.register') ?></a>
            <a class="btn btn--outline" href="<?php echo app()->createUrl('/login/default/index'); ?>"><?php echo Yii::t('main', 'btn.login') ?></a>
          <?php } else { ?>
            <a class="btn btn--gold" href="<?php echo app()->createUrl('/cabinet/default/index'); ?>"><?php echo Yii::t('main', 'btn.cabinet') ?></a>
          <?php } ?>
        </div>
      </nav>

      <div class="header__actions">
        <a class="header__social header__social--tg" id="headerTelegram" href="<?php echo socialUrl('telegram') ?>" rel="noopener nofollow"
           aria-label="Telegram" title="<?php echo Yii::t('site', 'tg.groupTitle') ?>">
          <svg class="icon icon--fill"><use href="/design/assets/icons/telegram.svg#i" /></svg>
        </a>
        <?php if (socialUrl('discord') !== '') { ?>
        <a class="header__social" id="headerDiscord" href="<?php echo socialUrl('discord') ?>" rel="noopener nofollow" aria-label="Discord"><svg class="icon icon--fill"><use href="/design/assets/icons/discord.svg#i" /></svg></a>
        <?php } ?>

        <?php $langs = ['ru' => '🇷🇺 RU', 'en' => '🇬🇧 EN', 'ua' => '🇺🇦 UA']; ?>
        <div class="lang-dd">
          <button type="button" class="lang-dd__btn" aria-haspopup="true" aria-expanded="false"><span class="lang-dd__current"><?php echo $langs[Lang::code()]; ?></span> ▾</button>
          <ul class="lang-dd__menu">
            <?php foreach ($langs as $code => $label) { ?>
              <li><button type="button" class="lang-dd__item<?php echo $code === Lang::code() ? ' active' : ''; ?>" data-lang="<?php echo $code; ?>"><?php echo $label; ?></button></li>
            <?php } ?>
          </ul>
        </div>

        <?php if (user()->isGuest) { ?>
          <a class="btn btn--outline btn--sm" href="<?php echo app()->createUrl('/login/default/index'); ?>"><?php echo Yii::t('main', 'btn.login') ?></a>
          <a class="btn btn--gold btn--sm" href="<?php echo app()->createUrl('/register/default/index'); ?>"><?php echo Yii::t('main', 'btn.register') ?></a>
        <?php } else { ?>
          <a class="btn btn--gold btn--sm" href="<?php echo app()->createUrl('/cabinet/default/index'); ?>"><?php echo Yii::t('main', 'btn.cabinet') ?></a>
        <?php } ?>
        <button class="burger" id="burgerBtn" aria-label="<?php echo Yii::t('main', 'layout.menu') ?>" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <?php if ($isHome) { ?>
  <!-- Якоря слева: подсвечиваются при прокрутке (initSpy в app.js) -->
  <nav class="side-nav" aria-label="<?php echo Yii::t('main', 'layout.homeSections') ?>">
    <a href="#hero" class="side-nav__link active"><i></i><span><?php echo Yii::t('main', 'nav.home') ?></span></a>
    <a href="#streams" class="side-nav__link"><i></i><span><?php echo Yii::t('main', 'nav.streams') ?></span></a>
    <a href="#rating" class="side-nav__link"><i></i><span><?php echo Yii::t('main', 'nav.rating') ?></span></a>
  </nav>

  <main>
    <!-- 1. HERO -->
    <section class="hero" id="hero">
      <div class="hero__bg">
        <div class="hero__bg-image hero__bg-image--desktop" data-bg="/design/assets/images/env-hero-2.jpg"></div>
        <div class="hero__bg-image hero__bg-image--mobile" data-bg="/design/assets/images/env-hero-mobile-2.jpg"></div>
        <div class="hero__gradient"></div>
        <div class="hero__fog hero__fog--1"></div>
        <div class="hero__fog hero__fog--2"></div>
        <div class="hero__light"></div>
      </div>

      <div class="hero__content container">
        <h1 class="hero__title" id="heroTitle">
          <?php echo BrandLogo::html('hero'); ?>
        </h1>
        <!-- Тексты первого экрана: за ними тёмный круг-подложка (.hero__text::before) -->
        <div class="hero__text">
          <p class="hero__headline reveal"><?php echo Yii::t('main', 'hero.headline') ?></p>
          <p class="hero__subtitle reveal"><?php echo Yii::t('main', 'hero.subtitle') ?></p>

          <div class="hero__opening reveal">
            <div class="hero__opening-date" id="openingDate"></div>
            <div class="countdown" id="countdown">
              <div class="countdown__item"><span class="countdown__value" id="cdDays">00</span><span class="countdown__label"><?php echo Yii::t('main', 'countdown.days') ?></span></div>
              <span class="countdown__sep">:</span>
              <div class="countdown__item"><span class="countdown__value" id="cdHours">00</span><span class="countdown__label"><?php echo Yii::t('main', 'countdown.hours') ?></span></div>
              <span class="countdown__sep">:</span>
              <div class="countdown__item"><span class="countdown__value" id="cdMinutes">00</span><span class="countdown__label"><?php echo Yii::t('main', 'countdown.minutes') ?></span></div>
              <span class="countdown__sep">:</span>
              <div class="countdown__item"><span class="countdown__value" id="cdSeconds">00</span><span class="countdown__label"><?php echo Yii::t('main', 'countdown.seconds') ?></span></div>
            </div>
            <div class="hero__online-badge hidden" id="serverOnlineBadge"><?php echo Yii::t('main', 'hero.serverOnline') ?></div>
          </div>

          <div class="hero__cta reveal">
            <a class="btn btn--gold btn--lg" href="/downloads/LA2PLUS-Setup-1.1.0.exe" data-download="launcher.windows" download="LA2PLUS-Setup-1.1.0.exe"><?php echo Yii::t('main', 'btn.join') ?></a>
          </div>
        </div>
      </div>

      <!-- Стадии сервера справа (только на широких экранах) -->
      <aside class="hero__side reveal" aria-label="<?php echo Yii::t('main', 'layout.serverStages') ?>">
        <?php foreach ([
          ['crown', 'Interlude x20', Yii::t('main', 'side.opening'), Yii::t('main', 'side.season'), 'season'],
          ['sword', 'B-Grade', Yii::t('main', 'side.b'), Yii::t('main', 'side.stage1'), 'stage'],
          ['castle', 'A-Grade', Yii::t('main', 'side.a'), Yii::t('main', 'side.stage2'), 'stage'],
          ['skull', 'S-Grade', Yii::t('main', 'side.s'), Yii::t('main', 'side.stage3'), 'stage'],
        ] as [$icon, $name, $sub, $badge, $kind]) { ?>
          <div class="side-item<?php echo $kind === 'season' ? ' side-item--lead' : ''; ?>">
            <span class="side-item__icon"><svg class="icon"><use href="/design/assets/icons/<?php echo $icon; ?>.svg#i" /></svg></span>
            <span class="side-item__text">
              <b class="side-item__name"><?php echo $name; ?></b>
              <span class="side-item__sub"><?php echo $sub; ?></span>
            </span>
            <span class="badge badge--<?php echo $kind; ?>"><?php echo $badge; ?></span>
          </div>
        <?php } ?>
      </aside>
    </section>

    <!-- 2. ТРАНСЛЯЦИИ: карточки рисует initStreams() из LA2PLUS_CONFIG.streams -->
    <section class="section streams" id="streams">
      <div class="section__bg" data-bg="/design/assets/images/env-community.png"></div>
      <div class="section__veil"></div>
      <div class="container">
        <div class="section__header section__header--banner reveal">
          <div class="banner">
            <h2 class="section__title"><?php echo Yii::t('main', 'streams.title') ?></h2>
            <p class="section__subtitle"><?php echo Yii::t('main', 'streams.subtitle') ?></p>
          </div>
        </div>
        <div class="streams__grid reveal" id="streamsGrid"></div>
        <div class="streams__more reveal">
          <button type="button" class="btn btn--outline btn--lg" data-modal="stream"><?php echo Yii::t('main', 'btn.addStream') ?></button>
        </div>
      </div>
    </section>

    <!-- 3. РЕЙТИНГ: вкладки и таблицы рисует initRating() из LA2PLUS_CONFIG.ratings, на главной 5 строк -->
    <section class="section rating" id="rating">
      <div class="section__bg" data-bg="/design/assets/images/env-hero.png"></div>
      <div class="section__veil"></div>
      <div class="container">
        <div class="section__header section__header--banner reveal">
          <div class="banner">
            <h2 class="section__title"><?php echo Yii::t('main', 'rating.title') ?></h2>
            <p class="section__subtitle"><?php echo Yii::t('main', 'rating.subtitle') ?></p>
          </div>
        </div>
        <div class="rating-widget reveal" data-rating-root data-limit="5"></div>
        <div class="rating__more reveal">
          <a class="btn btn--outline btn--lg" href="<?php echo app()->createUrl('/index/default/rating'); ?>"><?php echo Yii::t('main', 'btn.fullRating') ?></a>
        </div>
      </div>
    </section>
  </main>
  <?php } else { ?>
  <?php echo $content; ?>
  <?php } ?>

  <!-- FOOTER -->
  <footer class="footer" id="footer">
    <div class="container footer__inner">
      <div class="footer__copy">
        <p>© 2026 LA2PLUS</p>
        <!-- <p class="footer__company"><?php echo Yii::t('main', 'footer.company') ?></p> -->
      </div>
      <div class="footer__social">
        <a class="header__social footer__social-tg" id="footerTelegram" href="<?php echo socialUrl('telegram') ?>" rel="noopener nofollow"
           aria-label="Telegram" title="<?php echo Yii::t('site', 'tg.groupTitle') ?>"
           data-tip="<?php echo Yii::t('site', 'tg.groupTip') ?>" data-tip-side="top">
          <svg class="icon icon--fill"><use href="/design/assets/icons/telegram.svg#i" /></svg>
        </a>
        <?php if (socialUrl('discord') !== '') { ?>
        <a class="header__social" id="footerDiscord" href="<?php echo socialUrl('discord') ?>" rel="noopener nofollow" aria-label="Discord"><svg class="icon icon--fill"><use href="/design/assets/icons/discord.svg#i" /></svg></a>
        <?php } ?>
      </div>
      <nav class="footer__links">
        <?php if (socialUrl('telegram') !== '') { ?>
        <a href="<?php echo socialUrl('telegram') ?>" rel="noopener nofollow">Telegram</a>
        <?php } ?>
        <a href="<?php echo app()->createUrl('/index/default/terms'); ?>"><?php echo Yii::t('main', 'footer.terms') ?></a>
        <a href="<?php echo app()->createUrl('/index/default/privacy'); ?>"><?php echo Yii::t('main', 'footer.privacy') ?></a>
      </nav>
    </div>
  </footer>

  <!-- ПОПАП «Начать играть»: открывается по data-modal="play" (openModal в app.js) -->
  <div class="modal" id="modal-play" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="playTitle">
      <button type="button" class="modal__close" data-modal-close aria-label="<?php echo Yii::t('main', 'layout.close') ?>">&times;</button>
      <div class="modal__content">
        <h2 id="playTitle"><?php echo Yii::t('main', 'play.title') ?></h2>
        <p class="modal__subtitle"><?php echo Yii::t('main', 'play.subtitle') ?></p>
        <?php
        $platformDownloads = [
            ['key'=>'launcher.windows','label'=>PortalRuntime::translate('Лаунчер LA2PLUS для Windows','LA2PLUS Launcher for Windows','Лаунчер LA2PLUS для Windows'),'hint'=>PortalRuntime::translate('Установщик · Windows 10/11 x64 · около 6 МБ','Installer · Windows 10/11 x64 · about 6 MB','Інсталятор · Windows 10/11 x64 · близько 6 МБ'),'href'=>'/downloads/LA2PLUS-Setup-1.1.0.exe'],
            ['key'=>'client.classic','label'=>PortalRuntime::translate('Полная сборка — ZIP','Full game client — ZIP','Повна збірка — ZIP'),'hint'=>PortalRuntime::translate('Архив для ручной установки · около 13 ГБ','Archive for manual installation · about 13 GB','Архів для ручного встановлення · близько 13 ГБ'),'href'=>'/downloads/clients/la2plus-client.zip'],
            ['key'=>'launcher.windows.drive','label'=>PortalRuntime::translate('Зеркало сборки — Google Drive','Game client mirror — Google Drive','Дзеркало збірки — Google Drive'),'hint'=>PortalRuntime::translate('Альтернативная загрузка полного архива','Alternative download of the full archive','Альтернативне завантаження повного архіву'),'href'=>'/downloads/clients/la2plus-client.zip'],
        ];
        foreach ($platformDownloads as $dl) { ?>
          <div class="dl-row">
            <span class="dl-row__label"><?php echo $dl['label']; ?><small style="display:block;opacity:.6;font-size:11px;letter-spacing:.08em;margin-top:2px;"><?php echo $dl['hint']; ?></small></span>
            <div class="dl-row__btns">
              <a class="btn btn--gold btn--sm" data-download="<?php echo $dl['key']; ?>" href="<?php echo $dl['href']; ?>" download rel="noopener"><?php echo Yii::t('main', 'play.download'); ?></a>
            </div>
          </div>
        <?php } ?>
        <details class="dl-row dl-row--instr">
          <summary>
            <span class="dl-row__label"><?php echo Yii::t('main', 'play.instruction') ?></span>
            <span class="btn btn--outline btn--sm"><?php echo Yii::t('main', 'play.show') ?></span>
          </summary>
          <ol class="dl-row__steps">
            <li><?php echo PortalRuntime::translate('Скачайте установщик и установите лаунчер LA2PLUS.','Download the installer and install LA2PLUS Launcher.','Завантажте інсталятор і встановіть лаунчер LA2PLUS.'); ?></li>
            <li><?php echo PortalRuntime::translate('Выберите папку игры и язык клиента: русский или английский.','Choose a game folder and the Russian or English client.','Оберіть папку гри та мову клієнта: російську або англійську.'); ?></li>
            <li><?php echo PortalRuntime::translate('Нажмите «Скачать / обновить клиент» и дождитесь проверки файлов. Для первой загрузки нужно около 13 ГБ трафика и 30 ГБ свободного места.','Click Download / update client and wait for file verification. The first download uses about 13 GB of data and requires around 30 GB of free space.','Натисніть «Завантажити / оновити клієнт» і дочекайтеся перевірки файлів. Для першого завантаження потрібно близько 13 ГБ трафіку та 30 ГБ вільного місця.'); ?></li>
            <li><?php echo PortalRuntime::translate('Нажмите «Начать игру». В дальнейшем лаунчер автоматически загрузит обновления проекта.','Click «Начать игру» (Start game). The launcher will automatically download future project updates.','Натисніть «Начать игру». Надалі лаунчер автоматично завантажуватиме оновлення проєкту.'); ?></li>
          </ol>
        </details>
      </div>
    </div>
  </div>

  <!-- ПОПАП «Добавить стрим»: форма, отправки пока нет (см. initStreamForm в app.js) -->
  <div class="modal" id="modal-stream" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="streamTitle">
      <button type="button" class="modal__close" data-modal-close aria-label="<?php echo Yii::t('main', 'layout.close') ?>">&times;</button>
      <div class="modal__content">
        <h2 id="streamTitle"><?php echo Yii::t('main', 'btn.addStream') ?></h2>
        <p class="modal__subtitle"><?php echo Yii::t('main', 'feedback.subtitle') ?></p>
        <form id="streamForm" novalidate>
          <label class="field"><span><?php echo Yii::t('main', 'feedback.name') ?></span><input type="text" name="name" maxlength="64" required></label>
          <label class="field"><span><?php echo Yii::t('main', 'feedback.link') ?></span><input type="url" name="link" maxlength="255" required></label>
          <label class="field"><span><?php echo Yii::t('main', 'feedback.contact') ?></span><input type="text" name="contact" maxlength="128" required></label>
          <button type="submit" class="btn btn--gold btn--full"><?php echo Yii::t('main', 'btn.send') ?></button>
        </form>
      </div>
    </div>
  </div>

  <?php if (!user()->isGuest) { ?>
  <!-- ПОПАП «Выход из кабинета»: открывается по data-modal="logout" (пункт «Выход» в меню кабинета, master.php); без JS ссылка работает как обычно -->
  <div class="modal" id="modal-logout" hidden>
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
      <button type="button" class="modal__close" data-modal-close aria-label="<?php echo Yii::t('main', 'layout.close') ?>">&times;</button>
      <div class="modal__content">
        <h2 id="logoutTitle"><?php echo Yii::t('main', 'logout.title') ?></h2>
        <p class="modal__subtitle"><?php echo Yii::t('main', 'logout.text') ?></p>
        <div class="modal__actions">
          <a class="btn btn--gold" href="<?php echo app()->createUrl('/cabinet/default/logout'); ?>"><?php echo Yii::t('main', 'logout.confirm') ?></a>
          <button type="button" class="btn btn--outline" data-modal-close><?php echo Yii::t('main', 'logout.cancel') ?></button>
        </div>
      </div>
    </div>
  </div>
  <?php } ?>

  <!-- TOAST CONTAINER -->
  <div class="toast-container" id="toastContainer" aria-live="polite"></div>

  <?php if ($isHome) { ?>
  <!-- GSAP: анимация появления блоков (.reveal), см. initHeroAnimations в app.js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
  <?php } ?>
  <script>window.T = <?php echo json_encode(Lang::jsMessages(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>; // переводы для текстов, которые рисует JS</script>
  <script src="/design/js/config.js?v=launcher-1.1.0"></script>
  <script src="/design/js/app.js?v=<?php echo (int)@filemtime(Yii::getPathOfAlias('webroot').'/design/js/app.js'); ?>"></script>

</body>

</html>
