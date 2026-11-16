<?php
/**
 * Установка сайта приложением на главный экран (PWA).
 *
 * Это отдельный блок «Приложение на телефон» в личном кабинете игрока.
 * Подключается из каркаса одной строкой:
 *   <?php $this->renderPartial('//cabinet/panel-pwa') ?>
 *
 * Раньше здесь была плавающая кнопка в углу экрана: она перекрывала кнопки
 * «Пополнить баланс» на телефоне и мешала читать кабинет. Теперь блок стоит
 * в обычном потоке страницы, а сам решает, показывать ли его:
 *   Android/Chrome — «Установить приложение» (родное окно браузера);
 *   iPhone/iPad    — «Как установить» с пошаговой подсказкой (в Safari
 *                    установка только вручную: Поделиться → «На экран Домой»);
 *   уже установлено — блок скрыт (режим standalone);
 *   отказался      — блок скрыт 60 дней («Больше не предлагать»).
 *
 * Логика — в /design/js/pwa.js, здесь только разметка.
 *
 * @var CController $this
 */

// Если уже открыто в режиме автономного приложения — блок не показываем
$pwaStandalone = isset($_COOKIE['la2plus_pwa'])
    || (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'empty');

if ($pwaStandalone) {
    return;
}
?>
<section class="pwa-card dash-card" id="pwaCard" role="region" aria-labelledby="pwaCardTitle" hidden>
    <header class="dash-card__head">
        <span class="dash-card__title">
            <span class="dash-medal">
                <svg class="icon" aria-hidden="true"><use href="/design/assets/icons/download.svg#i"/></svg>
            </span>
            <h3 id="pwaCardTitle">Приложение на телефон</h3>
        </span>
        <button type="button" class="pwa-card__close" id="pwaInstallNo"
                aria-label="Больше не предлагать установку" title="Больше не предлагать">&times;</button>
    </header>

    <div class="pwa-card__body">
        <p class="pwa-card__text">
            Добавьте LA2PLUS на главный экран — сайт откроется как обычное приложение,
            без адресной строки, в один тап.
        </p>
        <p class="pwa-card__note" id="pwaCardNote">
            Значок появится на главном экране, вход в кабинет останется прежним.
        </p>
        <div class="pwa-card__foot">
            <button type="button" class="btn btn--gold btn--sm pwa-card__btn" id="pwaInstallBtn">
                <svg class="icon" aria-hidden="true"><use href="/design/assets/icons/download.svg#i"/></svg>
                <span class="pwa-card__btn-text" id="pwaInstallText">Установить приложение</span>
            </button>
        </div>
    </div>
</section>

<div class="pwa-sheet" id="pwaSheet" hidden>
    <div class="pwa-sheet__backdrop" data-pwa-close></div>
    <div class="pwa-sheet__box" role="dialog" aria-modal="true" aria-labelledby="pwaSheetTitle">
        <button type="button" class="pwa-sheet__x" data-pwa-close aria-label="Закрыть">&times;</button>
        <p class="pwa-sheet__kicker">LA2PLUS · приложение</p>
        <h2 class="pwa-sheet__title" id="pwaSheetTitle">Установка на главный экран</h2>
        <div class="pwa-sheet__body" id="pwaSheetBody"></div>
        <div class="pwa-sheet__foot">
            <button type="button" class="btn btn--gold" data-pwa-close>Понятно</button>
        </div>
    </div>
</div>
