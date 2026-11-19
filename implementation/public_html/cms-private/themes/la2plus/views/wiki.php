<?php
/**
 * Страница «База знаний» (/wiki/). Текст разделов сгенерирован, здесь обновляется только список разделов.
 * Разделы: id раздела => [заголовок, id иконки из components/icons.php].
 *
 * @var FrontendBaseController $this
 */
$this->pageTitle = Yii::t('main', 'nav.wiki');
$sections = [
    'general' => [Yii::t('wiki', 'general.nav'), 'gear'],
    'features' => [Yii::t('wiki', 'features.nav'), 'star'],
    'instances' => [Yii::t('wiki', 'instances.nav'), 'portal'],
    'augment' => [Yii::t('wiki', 'augment.nav'), 'gem'],
    'jewelry' => [Yii::t('wiki', 'jewelry.nav'), 'coin'],
    'career' => [Yii::t('wiki', 'career.nav'), 'user'],
    'clan' => [Yii::t('wiki', 'clan.nav'), 'shield'],
    'sieges' => [Yii::t('wiki', 'sieges.nav'), 'castle'],
    'bosses' => [Yii::t('wiki', 'bosses.nav'), 'skull'],
    'olympiad' => [Yii::t('wiki', 'olympiad.nav'), 'crown'],
    'events' => [Yii::t('wiki', 'events.nav'), 'swords'],
    'seals' => [Yii::t('wiki', 'seals.nav'), 'seal'],
    'currency' => [Yii::t('wiki', 'currency.nav'), 'coin'],
    'tournaments' => [Yii::t('wiki', 'tournaments.nav'), 'trophy'],
];
// $ico и $toggle — общие с master.php (wiki выполняется как отдельный view, layout его не подключает)
require __DIR__ . '/layouts/_menu.php';
?>
<div class="cms-wide side-layout">
  <aside class="side-menu" aria-label="<?php echo Yii::t('main', 'nav.wiki') ?>">
    <div class="side-menu__bar">
    <?php echo $toggle; ?>
    <ul class="menu">
      <?php foreach ($sections as $id => [$title, $icon]) { ?>
        <li><a href="#<?php echo $id ?>" data-wiki="<?php echo $id ?>"><?php echo $ico($icon, $title); ?></a></li>
      <?php } ?>
    </ul>
    </div>
  </aside>
  <div class="wiki__body">
    <section class="wiki__sec" id="general">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'general.nav') ?></h2>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.1') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.2') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.4') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.5') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'general.6') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.7') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.8') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.9') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.10') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'general.11') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.12') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.13') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.14') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.15') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.16') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.17') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.18') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.19') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.20') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.21') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.22') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'general.23') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.24') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.25') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.26') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.27') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'general.28') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.29') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.30') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'general.31') ?></div>
    </section>

    <section class="wiki__sec" id="features">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'features.nav') ?></h2>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'features.1') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'features.2') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'features.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'features.4') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'features.5') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'features.6') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'features.7') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'features.8') ?></div>
    </section>

    <section class="wiki__sec" id="instances">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'instances.nav') ?></h2>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'instances.1') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'instances.2') ?></th><th><?php echo Yii::t('wiki', 'instances.3') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'instances.4') ?></td><td><?php echo Yii::t('wiki', 'instances.5') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.6') ?></td><td><?php echo Yii::t('wiki', 'instances.7') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.8') ?></td><td><?php echo Yii::t('wiki', 'instances.9') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.10') ?></td><td><?php echo Yii::t('wiki', 'instances.11') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.12') ?></td><td><?php echo Yii::t('wiki', 'instances.13') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.14') ?></td><td><?php echo Yii::t('wiki', 'instances.15') ?></td></tr>
        </tbody>
      </table>
      <div class="wiki__row wiki__row--note"><?php echo Yii::t('wiki', 'instances.16') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'instances.17') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'instances.2') ?></th><th><?php echo Yii::t('wiki', 'instances.3') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'instances.4') ?></td><td><?php echo Yii::t('wiki', 'instances.5') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.22') ?></td><td><?php echo Yii::t('wiki', 'instances.23') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.8') ?></td><td><?php echo Yii::t('wiki', 'instances.9') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.10') ?></td><td><?php echo Yii::t('wiki', 'instances.11') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.12') ?></td><td><?php echo Yii::t('wiki', 'instances.29') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.14') ?></td><td><?php echo Yii::t('wiki', 'instances.31') ?></td></tr>
        </tbody>
      </table>
      <div class="wiki__row wiki__row--note"><?php echo Yii::t('wiki', 'instances.16') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'instances.33') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'instances.2') ?></th><th><?php echo Yii::t('wiki', 'instances.3') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'instances.4') ?></td><td><?php echo Yii::t('wiki', 'instances.5') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.22') ?></td><td><?php echo Yii::t('wiki', 'instances.39') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.8') ?></td><td><?php echo Yii::t('wiki', 'instances.41') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.10') ?></td><td><?php echo Yii::t('wiki', 'instances.43') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.12') ?></td><td><?php echo Yii::t('wiki', 'instances.45') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.14') ?></td><td><?php echo Yii::t('wiki', 'instances.47') ?></td></tr>
        </tbody>
      </table>
      <div class="wiki__row wiki__row--note"><?php echo Yii::t('wiki', 'instances.16') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'instances.49') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'instances.2') ?></th><th><?php echo Yii::t('wiki', 'instances.3') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'instances.52') ?></td><td><?php echo Yii::t('wiki', 'instances.53') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.54') ?></td><td><?php echo Yii::t('wiki', 'instances.55') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.56') ?></td><td><?php echo Yii::t('wiki', 'instances.57') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'instances.58') ?></td><td><?php echo Yii::t('wiki', 'instances.59') ?></td></tr>
        </tbody>
      </table>
      <?php if (Yii::app()->language === 'ru') { ?>
      <h3 class="wiki__sub">Lost Continent — уникальная фарм-зона</h3>
      <table class="wiki__table">
        <thead><tr><th>Условие</th><th>Описание</th></tr></thead>
        <tbody>
          <tr><td>Рейд-боссы</td><td>Появляются с 20:00 до 05:00.</td></tr>
          <tr><td>Обычный фарм</td><td>В остальное время зона фармится как обычно.</td></tr>
          <tr><td>Появление паков нежити</td><td>20:00, 21:00, 22:00, 23:00, 00:00, 01:00, 02:00, 03:00 и 04:00.</td></tr>
          <tr><td>Время присутствия паков</td><td>До получаса.</td></tr>
          <tr><td>Предупреждение</td><td>Приходит в чат в :55 минут.</td></tr>
          <tr><td>Повторное появление</td><td>Убийство не возвращает паки раньше срока.</td></tr>
        </tbody>
      </table>
      <h3 class="wiki__sub">Dragon Valley зона для фарма Festival Adena, Craft Coin Mid/High LS</h3>
      <table class="wiki__table">
        <thead><tr><th>Условие</th><th>Описание</th></tr></thead>
        <tbody>
          <tr><td>Режим работы</td><td>Каждый день с 21:00 до 23:00 | 02:00 до 05:00 [GMT+3]</td></tr>
          <tr><td>Телепорты</td><td>4 зоны: кнопки 1–4</td></tr>
          <tr><td>Награды</td><td>Festival Adena, Adena, Mid LS, High LS</td></tr>
          <tr><td>PvP зона</td><td>Активирована</td></tr>
        </tbody>
      </table>
      <div class="wiki__row">Dino Island — на светлой стороне Дино острова находится дополнительный Tyrannosaurus для соло игроков.</div>
      <?php } else { ?>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'instances.60') ?></div>
      <?php } ?>
    </section>

    <section class="wiki__sec" id="augment">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'augment.nav') ?></h2>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'augment.1') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'augment.2') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'augment.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'augment.4') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'augment.5') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'augment.6') ?></div>
    </section>

    <section class="wiki__sec" id="jewelry">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'jewelry.nav') ?></h2>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.1') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.2') ?></div>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'jewelry.3') ?></th><th><?php echo Yii::t('wiki', 'jewelry.4') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.5') ?></td><td><?php echo Yii::t('wiki', 'jewelry.6') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.7') ?></td><td><?php echo Yii::t('wiki', 'jewelry.6') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.9') ?></td><td><?php echo Yii::t('wiki', 'jewelry.6') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.11') ?></td><td><?php echo Yii::t('wiki', 'jewelry.12') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.13') ?></td><td><?php echo Yii::t('wiki', 'jewelry.14') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.15') ?></td><td><?php echo Yii::t('wiki', 'jewelry.16') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.17') ?></td><td><?php echo Yii::t('wiki', 'jewelry.18') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.19') ?></td><td><?php echo Yii::t('wiki', 'jewelry.20') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.21') ?></td><td><?php echo Yii::t('wiki', 'jewelry.22') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.23') ?></td><td><?php echo Yii::t('wiki', 'jewelry.24') ?></td></tr>
        </tbody>
      </table>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.25') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.26') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.27') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.28') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.29') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.30') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.31') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.32') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'jewelry.33') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'jewelry.34') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'jewelry.35') ?></th><th><?php echo Yii::t('wiki', 'jewelry.36') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.37') ?></td><td><?php echo Yii::t('wiki', 'jewelry.38') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.39') ?></td><td><?php echo Yii::t('wiki', 'jewelry.40') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.41') ?></td><td><?php echo Yii::t('wiki', 'jewelry.42') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.43') ?></td><td><?php echo Yii::t('wiki', 'jewelry.44') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.45') ?></td><td><?php echo Yii::t('wiki', 'jewelry.46') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.47') ?></td><td><?php echo Yii::t('wiki', 'jewelry.48') ?></td></tr>
        </tbody>
      </table>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'jewelry.49') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'jewelry.35') ?></th><th><?php echo Yii::t('wiki', 'jewelry.36') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.52') ?></td><td><?php echo Yii::t('wiki', 'jewelry.53') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.54') ?></td><td><?php echo Yii::t('wiki', 'jewelry.55') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.56') ?></td><td><?php echo Yii::t('wiki', 'jewelry.57') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.58') ?></td><td><?php echo Yii::t('wiki', 'jewelry.59') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.60') ?></td><td><?php echo Yii::t('wiki', 'jewelry.61') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.62') ?></td><td><?php echo Yii::t('wiki', 'jewelry.63') ?></td></tr>
        </tbody>
      </table>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'jewelry.64') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'jewelry.65') ?></th><th><?php echo Yii::t('wiki', 'jewelry.66') ?></th><th><?php echo Yii::t('wiki', 'jewelry.67') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.68') ?></td><td><?php echo Yii::t('wiki', 'jewelry.69') ?></td><td><?php echo Yii::t('wiki', 'jewelry.70') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.71') ?></td><td><?php echo Yii::t('wiki', 'jewelry.72') ?></td><td><?php echo Yii::t('wiki', 'jewelry.73') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.74') ?></td><td><?php echo Yii::t('wiki', 'jewelry.72') ?></td><td><?php echo Yii::t('wiki', 'jewelry.76') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.71') ?></td><td><?php echo Yii::t('wiki', 'jewelry.78') ?></td><td><?php echo Yii::t('wiki', 'jewelry.79') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'jewelry.74') ?></td><td><?php echo Yii::t('wiki', 'jewelry.78') ?></td><td><?php echo Yii::t('wiki', 'jewelry.82') ?></td></tr>
        </tbody>
      </table>
    </section>

    <section class="wiki__sec" id="career">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'career.nav') ?></h2>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'career.1') ?></h3>
      <?php if (Yii::app()->language === 'ru') { ?>
      <div class="wiki__row">Награда: 10 D-Grade купонов, 25 MP и HP банок.</div>
      <?php } ?>
      <?php if (Yii::app()->language === 'ru') { ?>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'career.2') ?></h3>
      <div class="wiki__row">Награда: 15 C-Grade купонов, 90 MP и HP банок.</div>
      <?php } else { ?>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.2') ?></div>
      <?php } ?>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'career.3') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.4') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'career.5') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.6') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'career.7') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.8') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.9') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.10') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'career.11') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.12') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.13') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.14') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.15') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.16') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'career.17') ?></div>
    </section>

    <section class="wiki__sec" id="clan">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'clan.nav') ?></h2>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'clan.1') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'clan.2') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'clan.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'clan.4') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'clan.5') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'clan.6') ?></div>
    </section>

    <section class="wiki__sec" id="sieges">
      <style>#sieges .wiki__table th:nth-child(3),#sieges .wiki__table td:nth-child(3){white-space:nowrap}</style>
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'sieges.nav') ?></h2>
      <img src="/design/assets/images/env-sieges.jpeg" alt="<?php echo Yii::t('wiki', 'sieges.nav') ?>" class="wiki__image">
      <div class="wiki__row"><?php echo Yii::t('wiki', 'sieges.1') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'sieges.2') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'sieges.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'sieges.4') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'sieges.5') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'sieges.6') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'sieges.7') ?></th><th><?php echo Yii::t('wiki', 'sieges.8') ?></th><th><?php echo Yii::t('wiki', 'sieges.9') ?></th><th><?php echo Yii::t('wiki', 'jewelry.67') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'sieges.11') ?></td><td><?php echo Yii::t('wiki', 'sieges.12') ?></td><td><?php echo Yii::t('wiki', 'sieges.13') ?></td><td><?php echo Yii::t('wiki', 'sieges.14') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'sieges.15') ?></td><td><?php echo Yii::t('wiki', 'sieges.16') ?></td><td><?php echo Yii::t('wiki', 'sieges.17') ?></td><td><?php echo Yii::t('wiki', 'sieges.14') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'sieges.19') ?></td><td><?php echo Yii::t('wiki', 'sieges.16') ?></td><td><?php echo Yii::t('wiki', 'sieges.17') ?></td><td><?php echo Yii::t('wiki', 'sieges.22') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'sieges.23') ?></td><td><?php echo Yii::t('wiki', 'sieges.12') ?></td><td><?php echo Yii::t('wiki', 'sieges.13') ?></td><td><?php echo Yii::t('wiki', 'sieges.14') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'sieges.27') ?></td><td><?php echo Yii::t('wiki', 'sieges.12') ?></td><td><?php echo Yii::t('wiki', 'sieges.13') ?></td><td><?php echo Yii::t('wiki', 'sieges.14') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'sieges.31') ?></td><td><?php echo Yii::t('wiki', 'sieges.12') ?></td><td><?php echo Yii::t('wiki', 'sieges.13') ?></td><td><?php echo Yii::t('wiki', 'sieges.14') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'sieges.35') ?></td><td><?php echo Yii::t('wiki', 'sieges.16') ?></td><td><?php echo Yii::t('wiki', 'sieges.17') ?></td><td><?php echo Yii::t('wiki', 'sieges.38') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'sieges.39') ?></td><td><?php echo Yii::t('wiki', 'sieges.40') ?></td><td><?php echo Yii::t('wiki', 'sieges.13') ?></td><td><?php echo Yii::t('wiki', 'sieges.42') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'sieges.43') ?></td><td><?php echo Yii::t('wiki', 'sieges.16') ?></td><td><?php echo Yii::t('wiki', 'sieges.17') ?></td><td><?php echo Yii::t('wiki', 'sieges.42') ?></td></tr>
        </tbody>
      </table>
    </section>

    <section class="wiki__sec" id="bosses">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'bosses.nav') ?></h2>
      <img src="/design/assets/images/env-ob.jpeg" alt="<?php echo Yii::t('wiki', 'bosses.nav') ?>" class="wiki__image">
      <div class="wiki__row"><?php echo Yii::t('wiki', 'bosses.1') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'bosses.2') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'bosses.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'bosses.4') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'bosses.5') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'bosses.6') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'bosses.7') ?></th><th><?php echo Yii::t('wiki', 'jewelry.66') ?></th><th><?php echo Yii::t('wiki', 'bosses.9') ?></th><th><?php echo Yii::t('wiki', 'jewelry.4') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'bosses.11') ?></td><td><?php echo Yii::t('wiki', 'bosses.12') ?></td><td><?php echo Yii::t('wiki', 'bosses.13') ?></td><td><?php echo Yii::t('wiki', 'jewelry.20') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.15') ?></td><td><?php echo Yii::t('wiki', 'bosses.16') ?></td><td><?php echo Yii::t('wiki', 'bosses.17') ?></td><td><?php echo Yii::t('wiki', 'jewelry.20') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.19') ?></td><td><?php echo Yii::t('wiki', 'bosses.16') ?></td><td><?php echo Yii::t('wiki', 'bosses.21') ?></td><td><?php echo Yii::t('wiki', 'jewelry.20') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.23') ?></td><td><?php echo Yii::t('wiki', 'instances.5') ?></td><td><?php echo Yii::t('wiki', 'bosses.25') ?></td><td><?php echo Yii::t('wiki', 'jewelry.6') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.27') ?></td><td><?php echo Yii::t('wiki', 'bosses.28') ?></td><td><?php echo Yii::t('wiki', 'bosses.29') ?></td><td><?php echo Yii::t('wiki', 'bosses.30') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.31') ?></td><td><?php echo Yii::t('wiki', 'instances.5') ?></td><td><?php echo Yii::t('wiki', 'bosses.33') ?></td><td><?php echo Yii::t('wiki', 'jewelry.6') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.35') ?></td><td><?php echo Yii::t('wiki', 'instances.5') ?></td><td><?php echo Yii::t('wiki', 'bosses.37') ?></td><td><?php echo Yii::t('wiki', 'jewelry.6') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.39') ?></td><td><?php echo Yii::t('wiki', 'bosses.28') ?></td><td><?php echo Yii::t('wiki', 'bosses.41') ?></td><td><?php echo Yii::t('wiki', 'jewelry.6') ?></td></tr>
        </tbody>
      </table>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'bosses.43') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'bosses.44') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'bosses.45') ?></div>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'bosses.7') ?></th><th><?php echo Yii::t('wiki', 'bosses.9') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'bosses.48') ?></td><td><?php echo Yii::t('wiki', 'bosses.49') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.50') ?></td><td><?php echo Yii::t('wiki', 'bosses.51') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.52') ?></td><td><?php echo Yii::t('wiki', 'bosses.53') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.54') ?></td><td><?php echo Yii::t('wiki', 'bosses.55') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.56') ?></td><td><?php echo Yii::t('wiki', 'bosses.57') ?></td></tr>
        </tbody>
      </table>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'bosses.58') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'bosses.7') ?></th><th><?php echo Yii::t('wiki', 'bosses.9') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'bosses.61') ?></td><td><?php echo Yii::t('wiki', 'bosses.62') ?></td><td></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.63') ?></td><td><?php echo Yii::t('wiki', 'bosses.62') ?></td><td></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'bosses.65') ?></td><td><?php echo Yii::t('wiki', 'bosses.66') ?></td><td></td></tr>
        </tbody>
      </table>
    </section>

    <section class="wiki__sec" id="olympiad">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'olympiad.nav') ?></h2>
      <img src="/design/assets/images/env-olympiad.png" alt="<?php echo Yii::t('wiki', 'olympiad.nav') ?>" class="wiki__image">
      <div class="wiki__row"><?php echo Yii::t('wiki', 'olympiad.1') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'olympiad.2') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'olympiad.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'olympiad.4') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'olympiad.5') ?></div>
      <?php if (Yii::app()->language === 'ru') { ?>
      <div class="wiki__row">Сезон длится неделю: с субботы до понедельника 00:11. Бои идут в субботу и воскресенье. Окно открывается в субботу в 18:00 и закрывается в понедельник в 00:05. Часы берутся по времени сервера.</div>
      <div class="wiki__row">Бои внеклассовые, один на один. Очередь стартует от 7 участников. Классовые и командные бои не набираются. Соперник подбирается случайно. Матч длится 5 минут, на арену переносят через 45 секунд. За боем можно смотреть: до 18 зрителей на стадион.</div>
      <div class="wiki__row">Бои идут на двух картах арен. Одновременно сервер держит до 80 стадионов.</div>
      <div class="wiki__row">В начале сезона у дворянина 18 очков. Проигравший отдаёт 20% от меньшего числа очков у двоих, но не меньше 1. Победитель забирает эти очки. При 18 на 18 это +3 и −3.</div>
      <div class="wiki__row">Победа: эти очки и 50 Noblesse Gate Pass.</div>
      <div class="wiki__row">Поражение: минус эти очки. Билетов нет.</div>
      <div class="wiki__row">Ничья: очки не меняются. Билетов нет.</div>
      <div class="wiki__row">На арене заточка оружия, брони и бижутерии считается как +6. Перед боем воин получает Haste 2 и Wind Walk 2, маг, хил и саммонер — Acumen 3 и Wind Walk 2. Откат умений сбрасывается при входе на стадион и при возвращении в город.</div>
      <div class="wiki__row">Геройство считается по классу: 11 боёв и минимум 1 победа. Клан героя получает 1000 репутации. Первое место в классе — обычный герой. Второе — Shadow Hero: те же умения на 50% силы и чёрное свечение. Свечение второго места снимается в понедельник в 00:01.</div>
      <div class="wiki__row">Сезонные билеты выдают по месту в классе. Чтобы место засчитали, нужно минимум 5 боёв. Места дают 100, 75, 55, 40 и 30 очков ранга, каждое такое очко меняется на 20 Noblesse Gate Pass. Герою к рангу добавляют ещё 300 очков перед этим пересчётом.</div>
      <?php } ?>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'olympiad.6') ?></th><th><?php echo Yii::t('wiki', 'olympiad.7') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.8') ?></td><td><?php echo Yii::t('wiki', 'olympiad.9') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.10') ?></td><td><?php echo Yii::t('wiki', 'olympiad.11') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.12') ?></td><td><?php echo Yii::t('wiki', 'olympiad.13') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.14') ?></td><td><?php echo Yii::t('wiki', 'olympiad.15') ?></td></tr>
        </tbody>
      </table>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'olympiad.6') ?></th><th><?php echo Yii::t('wiki', 'olympiad.7') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.18') ?></td><td><?php echo Yii::t('wiki', 'olympiad.19') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.20') ?></td><td><?php echo Yii::t('wiki', 'olympiad.21') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.22') ?></td><td><?php echo Yii::t('wiki', 'olympiad.23') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.24') ?></td><td><?php echo Yii::t('wiki', 'olympiad.25') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.26') ?></td><td><?php echo Yii::t('wiki', 'olympiad.27') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.28') ?></td><td><?php echo Yii::t('wiki', 'olympiad.29') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.30') ?></td><td><?php echo Yii::t('wiki', 'olympiad.31') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.32') ?></td><td><?php echo Yii::t('wiki', 'olympiad.33') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.34') ?></td><td><?php echo Yii::t('wiki', 'olympiad.35') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.36') ?></td><td><?php echo Yii::t('wiki', 'olympiad.35') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.38') ?></td><td><?php echo Yii::t('wiki', 'jewelry.15') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.40') ?></td><td><?php echo Yii::t('wiki', 'olympiad.41') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.42') ?></td><td><?php echo Yii::t('wiki', 'olympiad.43') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.44') ?></td><td><?php echo Yii::t('wiki', 'olympiad.45') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.46') ?></td><td><?php echo Yii::t('wiki', 'olympiad.47') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'olympiad.48') ?></td><td><?php echo Yii::t('wiki', 'olympiad.49') ?></td></tr>
        </tbody>
      </table>
    </section>

    <section class="wiki__sec" id="events">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'events.nav') ?></h2>
      <?php if (Yii::app()->language === 'ru') { ?>
      <div class="wiki__row">Регистрация открывается за 5 минут. Матч длится 10 минут. За каждый килл на TvT, CtF и Death Match сразу выдаётся 20 Greater CP Potion. Запись — команда .event.</div>
      <h3 class="wiki__sub">TvT</h3>
      <div class="wiki__row">Две команды, побеждает та, у кого больше убийств. Союзников бить нельзя, противников бафать и лечить нельзя. Уровни 70–80, до 200 игроков. Награду победителя дают после 3 убийств.</div>
      <div class="wiki__row">Победители: 2 Festival Adena, 1 Craft Coin, 200 Greater CP Potion.</div>
      <div class="wiki__row">Проигравшие: 200 Greater CP Potion.</div>
      <div class="wiki__row">Ничья: 1 Festival Adena и 100 Greater CP Potion.</div>
      <div class="wiki__row">Время: 10:00, 15:00, 18:00, 22:00, 03:00.</div>
      <h3 class="wiki__sub">CtF</h3>
      <div class="wiki__row">Принеси флаг противника на свою базу и не отдай свой. Уровни 70–80, до 100 игроков. Награды команд те же, что у TvT.</div>
      <div class="wiki__row">Время: 13:00, 19:00, 21:00, 01:00, 06:00.</div>
      <h3 class="wiki__sub">Death Match</h3>
      <div class="wiki__row">Каждый сам за себя, 10 минут, уровни 70–80, до 200 игроков. Первое место: 10 Festival Adena и 1 Craft Coin.</div>
      <div class="wiki__row">Время: 11:00, 14:00, 20:00, 23:00, 02:00.</div>
      <h3 class="wiki__sub">GvG</h3>
      <div class="wiki__row">Группа на группу.</div>
      <h3 class="wiki__sub">Кубиковая дуэль</h3>
      <div class="wiki__row">Ставка от 1 до 20 Coin of Luck. За выставление дуэли списывается 100 000 адены. Чей кубик больше, тот забирает банк.</div>
      <?php } else { ?>

      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'events.1') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.2') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.4') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.5') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.6') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.7') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.8') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'events.9') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.10') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.4') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.13') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.14') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.7') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.16') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'events.17') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.18') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.19') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.20') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.21') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.22') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.23') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.7') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'events.25') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.26') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.19') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.28') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.7') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.30') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'events.31') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'events.32') ?></div>

      <?php } ?>
    </section>

    <section class="wiki__sec" id="seals">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'seals.nav') ?></h2>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'seals.1') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'seals.2') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'seals.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'seals.4') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'seals.5') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'seals.6') ?></th><th><?php echo Yii::t('wiki', 'seals.7') ?></th><th><?php echo Yii::t('wiki', 'seals.8') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'seals.9') ?></td><td><?php echo Yii::t('wiki', 'seals.10') ?></td><td><?php echo Yii::t('wiki', 'seals.11') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'seals.12') ?></td><td><?php echo Yii::t('wiki', 'seals.13') ?></td><td><?php echo Yii::t('wiki', 'seals.14') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'seals.15') ?></td><td><?php echo Yii::t('wiki', 'seals.13') ?></td><td><?php echo Yii::t('wiki', 'seals.17') ?></td></tr>
        </tbody>
      </table>
    </section>

    <section class="wiki__sec" id="currency">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'currency.nav') ?></h2>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'currency.1') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'currency.2') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'currency.3') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'currency.4') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'currency.5') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'currency.6') ?></div>
    </section>

    <section class="wiki__sec" id="tournaments">
      <h2 class="wiki__title banner"><?php echo Yii::t('wiki', 'tournaments.nav') ?></h2>
      <img src="/design/assets/images/env-tournaments.jpeg" alt="<?php echo Yii::t('wiki', 'tournaments.nav') ?>" class="wiki__image">
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'tournaments.1') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'tournaments.2') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'tournaments.3') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'tournaments.4') ?></div>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'tournaments.5') ?></th><th><?php echo Yii::t('wiki', 'tournaments.6') ?></th><th><?php echo Yii::t('wiki', 'tournaments.7') ?></th><th><?php echo Yii::t('wiki', 'tournaments.8') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.9') ?></td><td><?php echo Yii::t('wiki', 'tournaments.10') ?></td><td><?php echo Yii::t('wiki', 'tournaments.11') ?></td><td><?php echo Yii::t('wiki', 'tournaments.12') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.13') ?></td><td><?php echo Yii::t('wiki', 'tournaments.14') ?></td><td><?php echo Yii::t('wiki', 'tournaments.15') ?></td><td><?php echo Yii::t('wiki', 'tournaments.16') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.17') ?></td><td><?php echo Yii::t('wiki', 'tournaments.18') ?></td><td><?php echo Yii::t('wiki', 'tournaments.19') ?></td><td><?php echo Yii::t('wiki', 'tournaments.20') ?></td></tr>
        </tbody>
      </table>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'tournaments.21') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'tournaments.22') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'tournaments.23') ?></div>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'tournaments.24') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'tournaments.25') ?></th><th><?php echo Yii::t('wiki', 'tournaments.7') ?></th><th><?php echo Yii::t('wiki', 'tournaments.8') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.28') ?></td><td><?php echo Yii::t('wiki', 'tournaments.29') ?></td><td><?php echo Yii::t('wiki', 'tournaments.30') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.31') ?></td><td><?php echo Yii::t('wiki', 'tournaments.32') ?></td><td><?php echo Yii::t('wiki', 'tournaments.33') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.34') ?></td><td><?php echo Yii::t('wiki', 'tournaments.35') ?></td><td><?php echo Yii::t('wiki', 'tournaments.36') ?></td></tr>
        </tbody>
      </table>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'tournaments.37') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'tournaments.25') ?></th><th><?php echo Yii::t('wiki', 'tournaments.7') ?></th><th><?php echo Yii::t('wiki', 'tournaments.8') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.28') ?></td><td><?php echo Yii::t('wiki', 'tournaments.42') ?></td><td><?php echo Yii::t('wiki', 'tournaments.43') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.31') ?></td><td><?php echo Yii::t('wiki', 'tournaments.29') ?></td><td><?php echo Yii::t('wiki', 'tournaments.30') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.34') ?></td><td><?php echo Yii::t('wiki', 'tournaments.32') ?></td><td><?php echo Yii::t('wiki', 'tournaments.33') ?></td></tr>
        </tbody>
      </table>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'tournaments.50') ?></h3>
      <table class="wiki__table">
        <thead><tr><th><?php echo Yii::t('wiki', 'tournaments.25') ?></th><th><?php echo Yii::t('wiki', 'tournaments.7') ?></th><th><?php echo Yii::t('wiki', 'tournaments.8') ?></th></tr></thead>
        <tbody>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.28') ?></td><td><?php echo Yii::t('wiki', 'tournaments.11') ?></td><td><?php echo Yii::t('wiki', 'tournaments.12') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.31') ?></td><td><?php echo Yii::t('wiki', 'tournaments.42') ?></td><td><?php echo Yii::t('wiki', 'tournaments.43') ?></td></tr>
          <tr><td><?php echo Yii::t('wiki', 'tournaments.34') ?></td><td><?php echo Yii::t('wiki', 'tournaments.29') ?></td><td><?php echo Yii::t('wiki', 'tournaments.30') ?></td></tr>
        </tbody>
      </table>
      <h3 class="wiki__sub"><?php echo Yii::t('wiki', 'tournaments.63') ?></h3>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'tournaments.64') ?></div>
      <div class="wiki__row"><?php echo Yii::t('wiki', 'tournaments.65') ?></div>
    </section>
  </div>
</div>
