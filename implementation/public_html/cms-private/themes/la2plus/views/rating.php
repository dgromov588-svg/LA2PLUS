<?php
/**
 * Страница полного рейтинга (/rating/): семь вкладок, до 100 строк.
 * Вкладки и таблицы рисует initRating() в app.js из LA2PLUS_CONFIG.ratings (design/js/config.js).
 */
$this->pageTitle = Yii::t('main', 'nav.rating');
?>
<div class="cms-wide">
  <div class="section__header section__header--banner">
    <div class="banner">
      <h2 class="section__title"><?php echo Yii::t('main', 'rating.title') ?></h2>
      <p class="section__subtitle"><?php echo Yii::t('main', 'rating.subtitle') ?></p>
    </div>
  </div>

  <link rel="stylesheet" href="/design/css/pages/rating-v2.css?v=1">
  <?php $ratingServers=Gs::getOpenServers(); ?>
  <label class="rating-server-label"><?php echo PortalRuntime::translate('Сервер','Server','Сервер'); ?>
    <select data-rating-server aria-label="<?php echo PortalRuntime::translate('Сервер','Server','Сервер'); ?>">
      <?php foreach ($ratingServers as $id=>$server) { if (!$server->stats_allow) continue; ?>
      <option value="<?php echo (int)$id; ?>"><?php echo CHtml::encode($server->name); ?></option>
      <?php } ?>
    </select>
  </label>
  <div class="rating-widget rating-widget--full" data-rating-root data-rating-full data-limit="100"></div>
</div>
