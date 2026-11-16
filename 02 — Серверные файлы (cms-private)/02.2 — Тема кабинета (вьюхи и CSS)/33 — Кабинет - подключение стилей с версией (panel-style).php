<?php
/**
 * Подключение стилей панели (золотая тема Aurum).
 *
 * Вызывается одной строкой в начале любой страницы панели:
 *
 *   <?php $this->renderPartial('//cabinet/panel-style') ?>
 *
 * ХОСТИНГ: cms-private закрыт правилом .htaccess (403 на любой .php внутри),
 * поэтому стили обязаны лежать в ПУБЛИЧНОМ каталоге /themes/<тема>/css/.
 * Курьер выкладки кладёт файл сразу туда; здесь мы ищем его в двух местах:
 *   1) public_html/themes/<тема>/css/panel-aurum.css   — боевой путь;
 *   2) cms-private/themes/<тема>/css/panel-aurum.css   — запасной (dev-копия).
 *
 * Версия в ссылке — время изменения файла, чтобы браузер не показывал старый CSS.
 *
 * @var $this CController
 */

$themePath = rtrim(Yii::getPathOfAlias('themes'), '/\\');   // cms-private/themes
$webRoot = dirname($themePath);                            // public_html
$themeName = app()->theme ? app()->theme->getName() : '';

if ($themeName === '') {
    $themeName = 'la2plus';
}

$cssFile = 'css/panel-aurum.css';

// (путь для браузера, полный путь на диске)
$candidates = array(
    array('/themes/' . $themeName . '/' . $cssFile, $webRoot . '/themes/' . $themeName . '/' . $cssFile),
    array('/themes/la2plus/' . $cssFile, $webRoot . '/themes/la2plus/' . $cssFile),
    array('/themes/' . $themeName . '/' . $cssFile, $themePath . '/' . $themeName . '/' . $cssFile),
    array('/themes/la2plus/' . $cssFile, $themePath . '/la2plus/' . $cssFile),
);

$url = null;

foreach ($candidates as $candidate) {
    if (is_file($candidate[1])) {
        $url = $candidate[0] . '?v=' . (int) @filemtime($candidate[1]);
        break;
    }
}

// Файл ещё не положен — берём публичный путь всё равно: 404 виден в консоли
// и сразу понятно, чего не хватает, а страница не остаётся без темы молча.
if ($url === null) {
    $url = '/themes/' . $themeName . '/' . $cssFile;
}

css($url);
