<?php
/** Установить: cms-private/themes/la2plus/views/cabinet/deposit/index.php */
$escape = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$methods = [];
foreach ((isset($wfpMethods) ? $wfpMethods : []) as $methodId => $methodTitle) {
    $methodMeta = [
        'card' => ['Visa / Mastercard', 'Безопасная оплата банковской картой'],
        'googlePay' => ['Google Pay', 'Оплата сохранённой картой через Google Pay'],
        'applePay' => ['Apple Pay', 'Оплата через Apple Pay на совместимом устройстве'],
        'privat24' => ['Privat24', 'Оплата через приложение Privat24'],
        'qrCode' => ['QR-оплата', 'Оплата по QR-коду'],
    ];
    $meta = isset($methodMeta[$methodId]) ? $methodMeta[$methodId] : [(string) $methodTitle, 'Защищённая страница оплаты'];
    $methods[] = [
        'group' => 'wayforpay', 'id' => $methodId,
        'title' => $meta[0], 'hint' => $meta[1], 'min' => 1,
    ];
}
foreach ((isset($utopayProviders) ? $utopayProviders : []) as $provider) {
    if (empty($provider['id'])) { continue; }
    $providerHint = isset($provider['description']) ? (string) $provider['description'] : '';
    if (!empty($provider['region'])) {
        $providerHint .= ($providerHint !== '' ? ' · ' : '') . (string) $provider['region'];
    }
    $methods[] = [
        'group' => 'utopay', 'id' => (string) $provider['id'],
        'title' => !empty($provider['name']) ? (string) $provider['name'] : (string) $provider['id'],
        'hint' => $providerHint !== '' ? $providerHint : 'Защищённая страница оплаты UTOPAY',
        'min' => !empty($provider['minimum_amount']) ? (float) $provider['minimum_amount'] : 1,
    ];
}
foreach ((isset($gateways) ? $gateways : []) as $gateway) {
    if (!isset($gateway['id'], $gateway['title'])) { continue; }
    $key = 'gateway:' . $gateway['id'];
    $methods[] = [
        'group' => 'gateway', 'id' => $gateway['id'], 'title' => $gateway['title'],
        'hint' => isset($gateway['hint']) ? $gateway['hint'] : '',
        'min' => isset($payMins[$key]) ? (float) $payMins[$key] : 1,
    ];
}
$ready = !empty($isPaymentReady) && count($methods) > 0;
$csrfName = request()->csrfTokenName;
$csrfToken = request()->getCsrfToken();
$packages = [50, 200, 400, 1000];
$bonuses = [];
foreach ([0, 1, 200, 300, 400, 1000] as $threshold) {
    $bonuses[] = [$threshold, ShopCoinController::bonusPercent($threshold)];
}
$settings = [
    'createUrl' => $this->createUrl('/cabinet/deposit/shop-create'),
    'ratesUrl' => $this->createUrl('/cabinet/deposit/currency-rates'),
    'statusUrl' => $this->createUrl('/cabinet/deposit/payment-status'),
    'pendingTx' => isset($pendingTx) ? (int) $pendingTx : 0,
    'csrfName' => $csrfName, 'csrfToken' => $csrfToken, 'bonuses' => $bonuses,
];
?>
<style>
.lp-deposit-page{position:relative;isolation:isolate;max-width:1440px;min-height:calc(100vh - 90px);margin:0 auto;padding:clamp(92px,10vw,132px) clamp(18px,4vw,48px) 48px;overflow:hidden;background:linear-gradient(180deg,rgba(4,7,13,.5),rgba(8,10,15,.9)),url('/design/assets/images/env-hero-2.jpg') center top/cover no-repeat}.lp-deposit-page:before{content:'';position:absolute;z-index:-1;inset:0;opacity:.72;background:radial-gradient(ellipse at 8% 3%,rgba(54,67,111,.48),transparent 37%),radial-gradient(ellipse at 100% 28%,rgba(95,69,33,.2),transparent 40%),linear-gradient(160deg,rgba(7,11,19,.38),rgba(16,16,27,.52) 48%,rgba(8,11,16,.72))}.lp-deposit-page:after{content:'';position:absolute;z-index:-1;inset:auto -10% -22% -10%;height:45%;opacity:.42;background:radial-gradient(ellipse at 50% 100%,#252a45 0 4%,transparent 4.5%),radial-gradient(ellipse at 22% 100%,#1c2638 0 16%,transparent 16.5%),radial-gradient(ellipse at 76% 100%,#172233 0 20%,transparent 20.5%);filter:blur(9px)}
.lp-deposit{--gold:#d9b96c;--muted:#a69f8d;box-sizing:border-box;max-width:1080px;margin:24px auto;padding:clamp(18px,4vw,44px);border:1px solid #8c7545;background:radial-gradient(ellipse at 50% 0, #28213b 0,transparent 43%),#0c0e11;color:#eee6d5;font:15px/1.6 Arial,sans-serif;overflow-wrap:anywhere}
.lp-deposit *{box-sizing:border-box}.lp-deposit h1,.lp-deposit h2{font-family:Georgia,serif;color:#efd39a;font-weight:400;line-height:1.2}.lp-deposit h1{font-size:clamp(30px,5vw,46px);margin:12px 0}.lp-deposit h2{font-size:25px;margin:0 0 24px}.lp-deposit p{margin:8px 0;color:var(--muted)}.lp-deposit .lp-intro{text-align:center;max-width:620px;margin:0 auto 32px}.lp-deposit .lp-brand{letter-spacing:5px;color:var(--gold);font-size:12px}.lp-deposit .lp-columns{display:grid;grid-template-columns:1.2fr 1fr;gap:20px;align-items:start}.lp-deposit .lp-card{min-width:0;border:1px solid #544626;border-radius:16px;padding:24px;background:#100f0bd9}.lp-deposit .lp-step{color:var(--gold);margin-right:12px}.lp-deposit .lp-packages{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.lp-deposit button{font:inherit;cursor:pointer;color:#efd39a}.lp-deposit .lp-package,.lp-deposit .lp-method{border:1px solid #766032;background:#211d13;border-radius:5px;padding:18px 12px}.lp-deposit .lp-package[aria-pressed=true]{border-color:#edc873;box-shadow:inset 0 0 0 1px #edc873;background:#302716}.lp-deposit .lp-package strong{display:block;font:700 30px Georgia,serif}.lp-deposit .lp-package span{display:block}.lp-deposit .lp-bonus{color:#55d7a2;font-size:12px;margin-top:6px}.lp-deposit label{display:block;color:var(--muted);margin:24px 0 8px}.lp-deposit input{width:100%;min-width:0;border:1px solid #625233;border-radius:5px;background:#090b0d;color:#fff;font:inherit;padding:13px}.lp-deposit .lp-summary{border-top:1px solid #393124;margin-top:24px;padding-top:18px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.lp-deposit output{color:#efd39a;font-weight:700;font-size:20px}.lp-deposit .lp-method{width:100%;display:block;text-align:left;margin:12px 0;padding:18px}.lp-deposit .lp-method strong{font-size:18px;display:block}.lp-deposit .lp-method span{display:block;color:var(--muted);font-size:13px}.lp-deposit button:hover:enabled{border-color:#f2d48e;background:#302716}.lp-deposit button:focus-visible,.lp-deposit input:focus-visible{outline:2px solid #edc873;outline-offset:4px}.lp-deposit button:disabled{opacity:.45;cursor:not-allowed}.lp-deposit .lp-note{font-size:13px}.lp-deposit .lp-message{color:#f3bd80;min-height:24px;margin-top:12px}.lp-deposit .lp-footer{text-align:center;font-size:13px;margin-top:24px}.lp-deposit [hidden]{display:none!important}@media(max-width:700px){.lp-deposit{margin:16px 0}.lp-deposit .lp-columns{grid-template-columns:1fr}.lp-deposit .lp-card{padding:20px 16px}}

/* Современный кабинет: спокойные поверхности, чёткая иерархия и мягкое золото. */
.lp-deposit{border-color:#393326;border-radius:24px;background:radial-gradient(ellipse at 12% 0,rgba(169,125,44,.13),transparent 47%),radial-gradient(ellipse at 100% 10%,rgba(77,65,105,.12),transparent 42%),#0d0f12;padding:clamp(20px,4vw,48px)}
.lp-deposit .lp-intro{text-align:left;max-width:680px;margin:0 0 34px}.lp-deposit .lp-brand{font-weight:700;font-size:11px;letter-spacing:3px}.lp-deposit h1{font-family:Arial,sans-serif;font-weight:700;letter-spacing:-1.8px;font-size:clamp(32px,4.4vw,48px);color:#f5f0e6;margin:20px 0 16px}.lp-deposit h1 em{font-style:normal;color:#d9b96c}.lp-deposit .lp-intro p{max-width:520px;line-height:1.8;font-size:14px}.lp-deposit .lp-card{border-color:#302d26;border-radius:18px;background:linear-gradient(145deg,#191a1c,#121416);padding:26px}.lp-deposit h2{font:600 19px Arial,sans-serif;letter-spacing:-.3px;display:flex;align-items:center;margin-bottom:22px;color:#f1eadc}.lp-deposit .lp-step{display:inline-grid;place-items:center;width:30px;height:30px;border:1px solid #54472c;border-radius:9px;background:#302819;font:600 11px Arial,sans-serif;letter-spacing:1px}.lp-deposit .lp-package{position:relative;text-align:left;border-color:#37332a;background:#1c1d1e;border-radius:12px;padding:22px 18px;transition:background .18s,border-color .18s,box-shadow .18s}.lp-deposit .lp-package strong{font:700 32px Arial,sans-serif;letter-spacing:-1px;color:#f5e8c9;margin-bottom:6px}.lp-deposit .lp-package span{font-size:11px;color:#b0ab9e}.lp-deposit .lp-package .lp-bonus{display:inline-block;color:#8ad9b0;background:#20322a;padding:3px 8px;border-radius:5px;margin-top:14px;font-size:11px}.lp-deposit .lp-package[aria-pressed=true]{border-color:#bda164;background:linear-gradient(135deg,#39301f,#24221c);box-shadow:0 0 22px #b79a4a0c}.lp-deposit .lp-package[aria-pressed=true]:after{content:'✓';position:absolute;top:12px;right:14px;font-size:13px;color:#ebca83}.lp-deposit input{background:#101214;border-color:#3b3830;border-radius:10px;padding:14px 16px;color:#f1eadc}.lp-deposit label{font-size:12px}.lp-deposit .lp-summary{background:#272318;border:1px solid #4b412a;border-radius:12px;padding:18px;margin-top:22px;align-items:center}.lp-deposit .lp-summary span{font-size:12px;color:#c3baa7}.lp-deposit output{font-size:18px}.lp-deposit .lp-method{border-radius:12px;background:#1c1d1e;border-color:#3c372c;padding:20px;margin:12px 0}.lp-deposit .lp-method strong{font-size:16px;color:#f0e5cb;margin-bottom:6px}.lp-deposit .lp-method span{font-size:12px;line-height:1.8}.lp-deposit .lp-method span:last-child{color:#d9b96c;margin-top:8px}.lp-deposit .lp-note{font-size:12px;line-height:1.8}.lp-deposit .lp-footer{max-width:680px;margin:28px auto 0;line-height:1.8;color:#7e7c74;font-size:12px}.lp-deposit .lp-message{font-size:13px}.lp-deposit button:hover:enabled{background:#302a1e;border-color:#bda164}@media(max-width:700px){.lp-deposit{border-radius:18px}.lp-deposit .lp-card{padding:20px 16px}.lp-deposit .lp-intro{margin-bottom:26px}.lp-deposit .lp-package{padding:20px 14px}.lp-deposit .lp-package strong{font-size:29px}}
.lp-deposit .lp-location{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:0 0 28px}.lp-deposit .lp-location label{margin:0 0 8px}.lp-deposit select{width:100%;min-width:0;background:#141618;color:#eee6d5;border:1px solid #49402e;border-radius:10px;padding:14px 12px;font:inherit;color-scheme:dark}.lp-deposit select:focus-visible{outline:2px solid #edc873;outline-offset:3px}.lp-deposit .lp-location-note{grid-column:1/-1;font-size:12px;margin:0}.lp-deposit .lp-converted{color:#e4c27c!important;margin:8px 0!important;font-size:12px}.lp-deposit .lp-payment-state{display:flex;align-items:flex-start;gap:12px;margin:0 0 24px;padding:15px 16px;border:1px solid rgba(213,173,104,.32);border-radius:12px;background:rgba(213,173,104,.08)}.lp-deposit .lp-payment-state[hidden]{display:none}.lp-deposit .lp-payment-state__mark{display:grid;place-items:center;flex:0 0 28px;width:28px;height:28px;border:1px solid #b99a56;border-radius:50%;color:#f0d39b;font-weight:700}.lp-deposit .lp-payment-state__body{min-width:0}.lp-deposit .lp-payment-state strong{display:block;color:#f0d39b}.lp-deposit .lp-payment-state p{margin:2px 0 0;font-size:12px}.lp-deposit .lp-payment-state.is-paid{border-color:rgba(111,215,159,.5);background:rgba(111,215,159,.1)}.lp-deposit .lp-payment-state.is-paid .lp-payment-state__mark,.lp-deposit .lp-payment-state.is-paid strong{color:#7de0a8;border-color:#5aaa7e}.lp-deposit .lp-state-refresh{margin:8px 0 0;border:0;background:none;padding:0;color:#e4c27c;text-decoration:underline;font-size:12px;cursor:pointer}@media(max-width:480px){.lp-deposit .lp-location{grid-template-columns:1fr}.lp-deposit-page{padding:0}}
</style>
<div class="lp-deposit-page">
<section class="lp-deposit" id="lp-deposit" aria-labelledby="lp-title">
    <header class="lp-intro"><div class="lp-brand">LA2PLUS · ПОПОЛНЕНИЕ БАЛАНСА</div><h1 id="lp-title">Больше возможностей.<br><em>Больше в игре.</em></h1><p>Пополните баланс Coin of Luck и получите бонус до 25%. Выберите пакет — остальное займёт пару шагов.</p></header>
    <noscript><p>Для расчёта суммы и перехода к оплате включите JavaScript.</p></noscript>
    <div class="lp-payment-state" id="lp-payment-state" hidden aria-live="polite">
        <span class="lp-payment-state__mark" id="lp-payment-state-mark">…</span>
        <div class="lp-payment-state__body"><strong id="lp-payment-state-title">Проверяем платёж</strong><p id="lp-payment-state-text">Сверяем статус последней заявки.</p><button class="lp-state-refresh" id="lp-state-refresh" type="button">Проверить ещё раз</button></div>
    </div>
    <div class="lp-location">
        <div><label for="lp-country">Страна пополнения</label><select id="lp-country"><option value="">Выберите страну</option></select></div>
        <div><label for="lp-currency">Валюта отображения</label><select id="lp-currency"><option value="USD">USD — Доллар США</option></select></div>
        <p class="lp-location-note" id="lp-fx-status" aria-live="polite">Загружаем справочные курсы…</p>
    </div>
    <div class="lp-columns">
        <section class="lp-card" aria-labelledby="lp-amount-title"><h2 id="lp-amount-title"><span class="lp-step">01</span>Сумма пополнения</h2>
            <div class="lp-packages" role="group" aria-label="Пакеты монет">
            <?php foreach ($packages as $amount): $percent = ShopCoinController::bonusPercent($amount); ?>
                <button type="button" class="lp-package" data-amount="<?= $amount ?>" aria-pressed="<?= $amount === 50 ? 'true' : 'false' ?>"><strong><?= number_format($amount + floor($amount * $percent / 100), 0, '.', ' ') ?></strong><span>Coin of Luck · <?= $amount ?> USD</span><span class="lp-converted" data-price="<?= $amount ?>"></span><span class="lp-bonus">Бонус +<?= $percent ?>%</span></button>
            <?php endforeach; ?>
            </div>
            <label for="lp-amount">Своя сумма, USD</label><input id="lp-amount" type="number" min="1" max="100000" step="1" value="50" inputmode="numeric" aria-describedby="lp-rate lp-error" required>
            <p class="lp-note" id="lp-rate">1 USD = 1 Coin of Luck. Бонус добавляется к монетам, сумма счёта не меняется.</p>
            <p class="lp-converted" id="lp-local-total" aria-live="polite"></p>
            <div class="lp-summary"><span>Получите на баланс</span><output id="lp-total" for="lp-amount" aria-live="polite">52 Coin of Luck</output></div>
            <p class="lp-note" id="lp-bonus"></p>
        </section>
        <section class="lp-card" aria-labelledby="lp-payment-title"><h2 id="lp-payment-title"><span class="lp-step">02</span>Способ оплаты</h2>
            <?php if (!$ready): ?><p>Пополнение временно недоступно. Дождитесь включения платёжных способов или обратитесь в поддержку.</p><?php endif; ?>
            <?php foreach ($methods as $method): ?>
            <button type="button" class="lp-method" data-pay-group="<?= $escape($method['group']) ?>" data-pay-id="<?= $escape($method['id']) ?>" data-min="<?= $escape($method['min']) ?>" <?= !$ready ? 'disabled' : '' ?>><strong><?= $escape($method['title']) ?></strong><span><?= $escape($method['hint']) ?></span><span>От <?= $escape($method['min']) ?> USD</span></button>
            <?php endforeach; ?>
            <p class="lp-note">Нажмите способ оплаты, чтобы создать счёт в USD и перейти на страницу платёжного сервиса.</p>
            <p class="lp-message" id="lp-error" role="status" aria-live="polite"></p>
        </section>
    </div>
    <p class="lp-footer">Зачисление происходит после подтверждения платёжного сервиса. Если вы уже оплатили, дождитесь обработки платежа и проверьте баланс в кабинете.</p>
</section>
</div>
<script>
(function () {
    'use strict';
    const settings = <?= json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    const root = document.getElementById('lp-deposit');
    const input = root.querySelector('#lp-amount');
    const error = root.querySelector('#lp-error');
    const methods = Array.from(root.querySelectorAll('.lp-method:not([disabled])'));
    const paymentState = root.querySelector('#lp-payment-state');
    const paymentStateMark = root.querySelector('#lp-payment-state-mark');
    const paymentStateTitle = root.querySelector('#lp-payment-state-title');
    const paymentStateText = root.querySelector('#lp-payment-state-text');
    const paymentStateRefresh = root.querySelector('#lp-state-refresh');
    const country = root.querySelector('#lp-country');
    const currency = root.querySelector('#lp-currency');
    const currencies = {USD:'Доллар США',EUR:'Евро',UAH:'Гривна',GBP:'Фунт стерлингов',PLN:'Злотый',KZT:'Тенге',TRY:'Турецкая лира',BRL:'Бразильский реал',GEL:'Лари',MDL:'Молдавский лей',AMD:'Драм',AZN:'Манат',CAD:'Канадский доллар',AUD:'Австралийский доллар',CHF:'Швейцарский франк',AED:'Дирхам',INR:'Индийская рупия',MXN:'Мексиканское песо',RON:'Румынский лей',CZK:'Чешская крона'};
    const countries = [
        ['UA','Украина','UAH'],['US','США','USD'],['GB','Великобритания','GBP'],['DE','Германия','EUR'],['PL','Польша','PLN'],['FR','Франция','EUR'],['ES','Испания','EUR'],['IT','Италия','EUR'],['PT','Португалия','EUR'],['NL','Нидерланды','EUR'],['BE','Бельгия','EUR'],['AT','Австрия','EUR'],['IE','Ирландия','EUR'],['GR','Греция','EUR'],['CY','Кипр','EUR'],['LT','Литва','EUR'],['LV','Латвия','EUR'],['EE','Эстония','EUR'],['FI','Финляндия','EUR'],['SK','Словакия','EUR'],['SI','Словения','EUR'],['HR','Хорватия','EUR'],['MT','Мальта','EUR'],['LU','Люксембург','EUR'],['KZ','Казахстан','KZT'],['TR','Турция','TRY'],['BR','Бразилия','BRL'],['GE','Грузия','GEL'],['MD','Молдова','MDL'],['AM','Армения','AMD'],['AZ','Азербайджан','AZN'],['CA','Канада','CAD'],['AU','Австралия','AUD'],['CH','Швейцария','CHF'],['AE','ОАЭ','AED'],['IN','Индия','INR'],['MX','Мексика','MXN'],['RO','Румыния','RON'],['CZ','Чехия','CZK'],['RU','Россия','USD'],['BY','Беларусь','USD'],['IL','Израиль','USD'],['OTHER','Другая страна','USD']
    ];
    countries.forEach(function (item) { country.add(new Option(item[1], item[0])); });
    let rates = {USD: 1};
    let currencyChosen = false;
    let busy = false;
    function savePreference() {
        try { localStorage.setItem('la2plus.deposit.locale', JSON.stringify({country:country.value,currency:currency.value})); } catch (_) {}
    }
    function chooseCountryCurrency() {
        const item = countries.find(function (item) { return item[0] === country.value; });
        currency.value = item && rates[item[2]] ? item[2] : 'USD';
    }
    function localPrice(amount) {
        return new Intl.NumberFormat('ru-RU', {style:'currency',currency:currency.value}).format(amount * rates[currency.value]);
    }
    country.addEventListener('change', function () { saved = null; currencyChosen = false; chooseCountryCurrency(); savePreference(); update(); });
    currency.addEventListener('change', function () { currencyChosen = true; savePreference(); update(); });
    let saved;
    try { saved = JSON.parse(localStorage.getItem('la2plus.deposit.locale') || 'null'); } catch (_) {}
    if (saved && countries.some(function (item) { return item[0] === saved.country; })) country.value = saved.country;
    else {
        // Язык — только подсказка, страну игрок всегда может изменить.
        const region = (navigator.language || '').split('-')[1];
        if (region && countries.some(function (item) { return item[0] === region.toUpperCase(); })) country.value = region.toUpperCase();
    }
    async function loadRates() {
        try {
            const response = await fetch(settings.ratesUrl, {credentials:'same-origin',headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error('rates');
            const data = await response.json();
            if (!data.rates || data.rates.USD !== 1) throw new Error('rates');
            Object.keys(currencies).forEach(function (code) {
                if (typeof data.rates[code] === 'number' && Number.isFinite(data.rates[code]) && data.rates[code] > 0) rates[code] = data.rates[code];
            });
            Object.keys(currencies).filter(function (code) { return code !== 'USD' && rates[code]; }).forEach(function (code) { currency.add(new Option(code + ' — ' + currencies[code], code)); });
            if (!currencyChosen) {
                chooseCountryCurrency();
                if (saved && rates[saved.currency]) { currency.value = saved.currency; saved = null; }
            }
            const ratesLoaded = Object.keys(rates).length > 1;
            const date = typeof data.updated === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(data.updated) ? data.updated : '';
            root.querySelector('#lp-fx-status').textContent = ratesLoaded ? 'Курсы справочные' + (date ? ' на ' + date : '') + '. Счёт выставляется в USD; итог конвертации и комиссии определяет платёжный сервис. Источник: ExchangeRate-API.' : 'Курсы недоступны. Суммы показаны в USD; оплату можно продолжить.';
        } catch (_) {
            root.querySelector('#lp-fx-status').textContent = 'Курсы недоступны. Суммы показаны в USD; оплату можно продолжить.';
        }
        update();
    }
    function showPaymentState(type, title, description) {
        paymentState.hidden = false;
        paymentState.classList.toggle('is-paid', type === 'paid');
        paymentStateMark.textContent = type === 'paid' ? '✓' : '…';
        paymentStateTitle.textContent = title;
        paymentStateText.textContent = description;
    }
    async function checkPaymentStatus() {
        if (!settings.pendingTx) return;
        showPaymentState('pending', 'Проверяем платёж', 'Сверяем статус заявки #' + settings.pendingTx + '.');
        paymentStateRefresh.disabled = true;
        try {
            const url = new URL(settings.statusUrl, window.location.href);
            url.searchParams.set('id', settings.pendingTx);
            const response = await fetch(url.href, {credentials:'same-origin',headers:{Accept:'application/json'}});
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error('status');
            if (result.paid) {
                const coins = Number(result.coins);
                showPaymentState('paid', 'Баланс пополнен', 'По заявке #' + result.id + ' зачислено ' + coins.toLocaleString('ru-RU') + ' Coin of Luck.');
                paymentStateRefresh.hidden = true;
                return;
            }
            showPaymentState('pending', 'Платёж ожидает подтверждения', 'Заявка #' + result.id + ' создана. После подтверждения шлюза монеты будут начислены автоматически.');
        } catch (_) {
            showPaymentState('pending', 'Не удалось обновить статус', 'Платёж не отменён. Подождите немного и попробуйте проверить ещё раз.');
        } finally {
            paymentStateRefresh.disabled = false;
        }
    }
    function update() {
        const amount = Number(input.value);
        const valid = input.checkValidity() && Number.isInteger(amount);
        root.querySelectorAll('[data-price]').forEach(function (item) { item.textContent = currency.value === 'USD' ? '' : '≈ ' + localPrice(Number(item.dataset.price)); });
        root.querySelector('#lp-local-total').textContent = valid ? 'Счёт: ' + amount.toLocaleString('ru-RU') + ' USD' + (currency.value === 'USD' ? '' : ' · ≈ ' + localPrice(amount)) : '';
        country.disabled = busy;
        currency.disabled = busy;
        let percent = 0;
        settings.bonuses.forEach(function (tier) { if (amount >= tier[0]) percent = tier[1]; });
        root.querySelectorAll('.lp-package').forEach(function (button) {
            button.setAttribute('aria-pressed', String(valid && Number(button.dataset.amount) === amount));
            button.disabled = busy;
        });
        root.querySelector('#lp-total').textContent = valid ? (amount + Math.floor(amount * percent / 100)).toLocaleString('ru-RU') + ' Coin of Luck' : '—';
        root.querySelector('#lp-bonus').textContent = valid ? 'Включая бонус ' + percent + '% · ' + Math.floor(amount * percent / 100).toLocaleString('ru-RU') + ' монет' : '';
        methods.forEach(function (button) { button.disabled = busy || !valid || amount < Number(button.dataset.min); });
        error.textContent = !valid ? 'Укажите целую сумму от 1 до 100 000 USD.' : (methods.length && methods.every(function (button) { return amount < Number(button.dataset.min); }) ? 'Увеличьте сумму до минимума доступного способа оплаты.' : '');
    }
    input.addEventListener('input', update);
    root.querySelectorAll('.lp-package').forEach(function (button) { button.addEventListener('click', function () { input.value = button.dataset.amount; update(); }); });
    methods.forEach(function (button) {
        button.addEventListener('click', async function () {
            if (busy || !input.reportValidity()) return;
            busy = true;
            input.disabled = true;
            update();
            error.textContent = 'Создаём счёт…';
            try {
                const body = new URLSearchParams({amount: input.value, pay_group: button.dataset.payGroup, pay_id: button.dataset.payId, country: country.value, display_currency: currency.value});
                body.set(settings.csrfName, settings.csrfToken);
                const response = await fetch(settings.createUrl, {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json'}, body: body});
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.message || 'Не удалось создать счёт. Проверьте авторизацию и попробуйте позже.');
                const target = new URL(result.action, window.location.href);
                if (target.origin !== window.location.origin || !Number.isInteger(result.tx_id) || result.tx_id <= 0) throw new Error('Сервис вернул некорректный адрес оплаты. Обратитесь в поддержку.');
                const form = document.createElement('form');
                form.method = 'post';
                form.action = target.href;
                [[settings.csrfName, settings.csrfToken], ['tx_id', String(result.tx_id)]].forEach(function (field) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden'; hidden.name = field[0]; hidden.value = field[1]; form.appendChild(hidden);
                });
                document.body.appendChild(form);
                form.submit();
            } catch (exception) {
                busy = false;
                input.disabled = false;
                update();
                error.textContent = exception instanceof SyntaxError ? 'Сервис оплаты временно недоступен. Попробуйте позже.' : exception.message;
            }
        });
    });
    update();
    loadRates();
    paymentStateRefresh.addEventListener('click', checkPaymentStatus);
    if (settings.pendingTx) {
        checkPaymentStatus();
        window.setTimeout(checkPaymentStatus, 15000);
        window.setTimeout(checkPaymentStatus, 30000);
    }
}());
</script>
