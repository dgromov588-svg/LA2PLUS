<?php
/**
 * Календарь событий: витрина, покупка сезона и получение наград.
 *
 * @var DefaultController $this
 * @var int $calendarPrice
 * @var int $calendarDays
 * @var string $calendarCurrency
 * @var array|null $season    активный сезон игрока
 * @var array|null $lastSeason
 * @var array|null $state     состояние дней (getDays)
 * @var array $preview        награды по дням (с названиями и иконками)
 * @var array $characters     персонажи игрока для выдачи
 * @var float $balance
 * @var int[] $premiumDays
 */

$title_ = Yii::t('cabinet', 'menu.calendar');
$this->pageTitle = $title_;
$this->breadcrumbs = [$title_];

$x = function ($s) { return e($s); };
$num = function ($n) { return number_format((int) $n, 0, '.', ' '); };

// «Scroll: Enchant Weapon (S-Grade)» → ['Enchant Weapon', 'S'] — чтобы название влезало в плитку
$split = function ($name) {
    $name = (string) $name;
    $grade = '';

    if (preg_match('/^(.*?)\s*\((S|A|B|C|D)-Grade\)$/i', $name, $m)) {
        $name = $m[1];
        $grade = strtoupper($m[2]);
    }

    $short = [
        'Scroll: Enchant Weapon' => 'Enchant Weapon',
        'Scroll: Enchant Armor' => 'Enchant Armor',
        'Blessed Spiritshot' => 'B. Spiritshot',
        'Spiritshot' => 'Spiritshot',
        'Greater Healing Potion' => 'Greater Heal',
        'Quick Healing Potion' => 'Quick Heal',
    ];

    foreach ($short as $from => $to) {
        if (strcasecmp($name, $from) === 0) {
            $name = $to;
            break;
        }
    }

    return [$name, $grade];
};

$days = isset($state) && $state ? $state['days'] : [];
$active = is_array($season);
$hasChars = !empty($characters);
$selectedChar = $hasChars ? (string) request()->getQuery('char', $characters[0]['char_name']) : '';

$openDays = $active ? (int) $state['open'] : 0;
$claimedDays = $active ? (int) $state['claimed'] : 0;
$progress = $calendarDays > 0 ? min(100, round($claimedDays / $calendarDays * 100)) : 0;
?>

<?php $this->widget('app.widgets.FlashMessages.FlashMessages') ?>

<div class="cal-page">

    <figure class="cal-banner">
        <img src="/design/assets/images/env-calendar.jpeg" alt="">
        <header>
            <h1 class="cal-title"><?php echo $x(Yii::t('cabinet', 'calendar.subtitle')) ?></h1>
            <p class="cal-lead"><?php echo $x(Yii::t('cabinet', 'calendar.desc')) ?></p>
            <span class="cal-season"><?php echo $x(Yii::t('cabinet', 'calendar.season')) ?> · <?php echo (int) $calendarDays ?> <?php echo $x(Yii::t('cabinet', 'calendar.days.short')) ?></span>
        </header>
    </figure>

    <?php if ($active) { ?>
        <div class="event-bar">
            <div class="event-bar__main">
                <div class="event-bar__head">
                    <span class="event-bar__title">Сезон #<?php echo (int) $season['id'] ?></span>
                    <span class="event-bar__dates"><?php echo $x(date('d.m.Y', strtotime($season['starts_at']))) ?> — <?php echo $x(date('d.m.Y', strtotime($season['ends_at']))) ?></span>
                </div>
                <div class="event-bar__stats">
                    <div class="event-stat">
                        <span class="event-stat__label">Открыто дней</span>
                        <b class="event-stat__value"><?php echo $openDays ?><small>/<?php echo (int) $calendarDays ?></small></b>
                    </div>
                    <div class="event-stat">
                        <span class="event-stat__label">Получено</span>
                        <b class="event-stat__value"><?php echo $claimedDays ?><small>/<?php echo (int) $calendarDays ?></small></b>
                    </div>
                    <div class="event-stat">
                        <span class="event-stat__label">До конца</span>
                        <b class="event-stat__value"><?php echo max(0, (int) ceil((strtotime($season['ends_at']) - time()) / 86400)) ?><small>дн.</small></b>
                    </div>
                </div>
                <div class="event-progress" role="progressbar" aria-valuenow="<?php echo $progress ?>" aria-valuemin="0" aria-valuemax="100">
                    <span style="width:<?php echo $progress ?>%"></span>
                </div>
            </div>

            <div class="event-bar__side">
                <?php if ($hasChars) { ?>
                    <form class="event-char" method="get" action="<?php echo CHtml::normalizeUrl(['/cabinet/calendar']) ?>">
                        <label for="calChar">Награды получит</label>
                        <select id="calChar" name="char" onchange="this.form.submit()">
                            <?php foreach ($characters as $character) { ?>
                                <option value="<?php echo $x($character['char_name']) ?>"<?php echo $selectedChar === (string) $character['char_name'] ? ' selected' : '' ?>>
                                    <?php echo $x($character['char_name']) ?>
                                </option>
                            <?php } ?>
                        </select>
                        <noscript><button class="btn btn--outline btn--sm" type="submit"><span>Выбрать</span></button></noscript>
                    </form>
                    <p class="event-note">Предметы придут персонажу при следующем входе в игру.</p>
                <?php } else { ?>
                    <p class="event-note event-note--warn">На игровом аккаунте нет персонажей — награду некому выдать. Создайте персонажа и обновите страницу.</p>
                <?php } ?>
            </div>
        </div>

        <div class="event-section">
            <div class="event-section__head">
                <h2 class="event-section__title">Награды сезона</h2>
                <ul class="event-legend">
                    <li><i class="sw-open"></i>можно забрать</li>
                    <li><i class="sw-claimed"></i>получено</li>
                    <li><i class="sw-locked"></i>закрыт</li>
                    <li><i class="sw-premium"></i>премиум</li>
                </ul>
            </div>
            <p class="event-hint">Дни не сгорают: забрать можно любой открывшийся день в течение всего сезона.</p>
        </div>
    <?php } else { ?>
        <div class="event-section event-section--stack">
            <ol class="cal-steps">
                <li><span class="cal-step__n">1</span><span class="cal-step__text"><?php echo $x(Yii::t('cabinet', 'calendar.step1', ['{0}' => (int) $calendarPrice, '{1}' => $calendarCurrency])) ?></span></li>
                <li><span class="cal-step__n">2</span><span class="cal-step__text"><?php echo $x(Yii::t('cabinet', 'calendar.step2')) ?></span></li>
                <li><span class="cal-step__n">3</span><span class="cal-step__text"><?php echo $x(Yii::t('cabinet', 'calendar.step3')) ?></span></li>
            </ol>
        </div>

        <div class="event-section">
            <div class="event-section__head">
                <h2 class="event-section__title"><?php echo $x(Yii::t('cabinet', 'calendar.rewards')) ?></h2>
                <ul class="event-legend">
                    <li><i class="sw-premium"></i>премиум</li>
                </ul>
            </div>
        </div>
    <?php } ?>

    <ol class="cal-days">
        <?php if ($active): ?>
            <?php foreach ($days as $day => $info) { ?>
                <?php
                $dayItems = isset($preview[$day]) ? $preview[$day] : $info['items'];
                $canClaim = $info['open'] && !$info['claimed'] && $hasChars;
                $classes = 'cal-day';
                $classes .= $info['premium'] ? ' is-premium' : '';
                $classes .= $info['claimed'] ? ' is-claimed' : '';
                $classes .= $info['open'] ? ' is-open' : ' is-locked';
                ?>
                <li class="<?php echo $classes ?>">
                    <div class="cal-day__head">
                        <span class="cal-day__num"><?php echo (int) $day ?></span>
                        <?php if ($info['premium']) { ?><span class="cal-day__tag"><?php echo $x(Yii::t('cabinet', 'calendar.tag.premium')) ?></span><?php } ?>
                    </div>

                    <ul class="cal-day__items">
                        <?php foreach ($dayItems as $item) { ?>
                            <li title="<?php echo $x($item['name']) ?>">
                                <?php if (!empty($item['icon'])): ?>
                                    <img src="<?php echo $x($item['icon']) ?>" alt="" width="20" height="20" loading="lazy">
                                <?php else: ?>
                                    <i class="cal-day__dot" aria-hidden="true"></i>
                                <?php endif ?>
                                <?php list($itemName, $itemGrade) = $split($item['name']); ?>
                                <span class="cal-day__item-name"><span><?php echo $x($itemName) ?></span><?php if ($itemGrade !== '') { ?><em class="cal-day__item-grade"><?php echo $x($itemGrade) ?></em><?php } ?></span>
                                <span class="cal-day__item-count">×<?php echo $num($item['count']) ?></span>
                            </li>
                        <?php } ?>
                    </ul>

                    <div class="cal-day__foot">
                        <?php if ($info['claimed']) { ?>
                            <span class="cal-day__state is-done">получено <?php echo $x(date('d.m', strtotime($info['claimed_at']))) ?></span>
                        <?php } elseif (!$info['open']) { ?>
                            <span class="cal-day__state">закрыт</span>
                        <?php } else { ?>
                            <form method="post" action="<?php echo CHtml::normalizeUrl(['/cabinet/calendarClaim']) ?>">
                                <?php echo CHtml::hiddenField(request()->csrfTokenName, request()->getCsrfToken()) ?>
                                <?php echo class_exists('PortalRuntime')?PortalRuntime::pinField():''; ?>
                                <input type="hidden" name="day" value="<?php echo (int) $day ?>">
                                <input type="hidden" name="char_name" value="<?php echo $x($selectedChar) ?>">
                                <button class="btn btn--gold btn--sm" type="submit"<?php echo $canClaim ? '' : ' disabled' ?>>
                                    <span><?php echo $x(Yii::t('cabinet', 'calendar.claim')) ?></span>
                                </button>
                            </form>
                        <?php } ?>
                    </div>
                </li>
            <?php } ?>
        <?php else: ?>
            <?php foreach ($preview as $day => $items) { ?>
                <li class="cal-day<?php echo in_array($day, $premiumDays, true) ? ' is-premium' : '' ?>">
                    <div class="cal-day__head">
                        <span class="cal-day__num"><?php echo (int) $day ?></span>
                        <?php if (in_array($day, $premiumDays, true)) { ?><span class="cal-day__tag"><?php echo $x(Yii::t('cabinet', 'calendar.tag.premium')) ?></span><?php } ?>
                    </div>
                    <ul class="cal-day__items">
                        <?php foreach ($items as $item) { ?>
                            <li title="<?php echo $x($item['name']) ?>">
                                <?php if (!empty($item['icon'])): ?>
                                    <img src="<?php echo $x($item['icon']) ?>" alt="" width="20" height="20" loading="lazy">
                                <?php else: ?>
                                    <i class="cal-day__dot" aria-hidden="true"></i>
                                <?php endif ?>
                                <?php list($itemName, $itemGrade) = $split($item['name']); ?>
                                <span class="cal-day__item-name"><span><?php echo $x($itemName) ?></span><?php if ($itemGrade !== '') { ?><em class="cal-day__item-grade"><?php echo $x($itemGrade) ?></em><?php } ?></span>
                                <span class="cal-day__item-count">×<?php echo $num($item['count']) ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>
        <?php endif ?>
    </ol>

    <?php if (!$active) { ?>
        <?php if ($lastSeason) { ?>
            <p class="event-hint">Последний сезон завершился <?php echo $x(date('d.m.Y', strtotime($lastSeason['ends_at']))) ?>. Можно купить новый.</p>
        <?php } ?>

        <div class="cal-buy">
            <div class="cal-buy__price">
                <span class="cal-buy__label"><?php echo $x(Yii::t('cabinet', 'calendar.price')) ?></span>
                <span class="cal-buy__value"><b><?php echo (int) $calendarPrice ?></b> <?php echo $x($calendarCurrency) ?></span>
                <span class="cal-buy__hint">На кошельке: <?php echo formatCurrency($balance) ?></span>
            </div>

            <?php if ($balance + 0.001 >= $calendarPrice) { ?>
                <form method="post" action="<?php echo CHtml::normalizeUrl(['/cabinet/calendarBuy']) ?>"
                      onsubmit="return confirm('Списать <?php echo (int) $calendarPrice ?> <?php echo $x($calendarCurrency) ?> с кошелька и купить календарь?');">
                    <?php echo CHtml::hiddenField(request()->csrfTokenName, request()->getCsrfToken()) ?>
                    <?php echo class_exists('PortalRuntime')?PortalRuntime::pinField():''; ?>
                    <button class="btn btn--gold" type="submit"><span><?php echo $x(Yii::t('cabinet', 'calendar.buy')) ?></span></button>
                </form>
            <?php } else { ?>
                <a class="btn btn--outline" href="<?php echo CHtml::normalizeUrl(['/cabinet/deposit']) ?>">
                    <span><?php echo $x(Yii::t('cabinet', 'calendar.needBalance')) ?></span>
                </a>
            <?php } ?>
        </div>
    <?php } ?>
</div>
