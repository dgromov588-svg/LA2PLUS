<?php

/**
 * Страница админ-панели v4.
 *
 * Отдаёт только оболочку: данные приезжают на /adm/state уже после входа,
 * поэтому в HTML нет ни одной цифры — смотреть исходник бессмысленно.
 */
class AdmPageController extends AdmApiController
{
    /** Своя вёрстка: это самостоятельная страница, а не раздел сайта. */
    public $layout = false;

    /**
     * Страница не отдаёт JSON: гостя отправляем на вход, сотрудника без
     * прав — в кабинет. Иначе браузер получил бы {"ok":false} вместо сайта.
     */
    public function beforeAction($action)
    {
        if (user()->isGuest) {
            $this->redirect('/login/?next=' . urlencode('/admin'));
        }

        return true;
    }

    public function actionIndex()
    {
        if (!RoleService::can('panel.view')) {
            $this->redirect('/cabinet/');
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');

        /*
         * Вид лежит в modules/adm/views/adm/panel.php. Путь к видам задаём
         * явно: у нашего контроллера имя файла не совпадает с именем класса,
         * и стандартный поиск CController смотрит не туда.
         */
        /*
         * Рендерим файл напрямую: renderPartial() ищет вид через resolveViewFile,
         * а тот дописывает .php к имени и не находит путь, заданный абсолютно.
         */
        // Раздел из адреса: /admin/tickets открывает «Тикеты» сразу.
        $tail = trim((string) Yii::app()->getRequest()->getPathInfo(), '/');
        $parts = $tail === '' ? array() : explode('/', $tail);
        $route = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : 'home';

        $this->renderFile(dirname(__FILE__) . '/views/adm/panel.php', array(
            'api' => '/adm',
            'route' => $route,
        ));
    }

    /**
     * Статика панели: CSS/JS лежат вне корня сайта и отдаются этим действием.
     *
     * Так панель работает без доступа к файловой системе сайта и без риска,
     * что правило .htaccess скроет ассеты от браузера.
     */
    public function actionAsset()
    {
        if (!RoleService::can('panel.view')) {
            $this->fail('Нужен вход в панель.', 403);
        }

        // Путь приходит параметром запроса: слэши в сегменте маршрута
        // Yii трактует как продолжение маршрута.
        $path = isset($_GET['p']) ? (string) $_GET['p'] : '';

        // Только известные файлы: списком, без «..» и без обхода каталогов.
        $allowed = array(
            'css/base.css' => 'text/css',
            'css/themes.css' => 'text/css',
            'css/app.css' => 'text/css',
            'js/data.js' => 'application/javascript',
            'js/api.js' => 'application/javascript',
            'js/core.js' => 'application/javascript',
            'js/views.js' => 'application/javascript',
            'js/shell.js' => 'application/javascript',
        );

        if (!isset($allowed[$path])) {
            $this->fail('Файл не найден.', 404);
        }

        $file = $this->assetRoot() . '/' . $path;

        if (!is_readable($file)) {
            $this->fail('Файл недоступен.', 404);
        }

        /* Что именно ушло в браузер — видно в заголовке: без этого
           невозможно понять, старая версия на экране или новая. */
        header('X-Asset-Path: ' . $file);
        header('X-Asset-Bytes: ' . filesize($file));
        header('X-Asset-Mtime: ' . date('c', filemtime($file)));

        header('Content-Type: ' . $allowed[$path] . '; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        /*
         * Отдаём ETag по времени правки файла и требуем проверку кэша каждый
         * раз. Раньше стояло max-age=600, и после заливки новой версии
         * браузер ещё десять минут показывал старый код — правки «не
         * применялись».
         */
        header('Cache-Control: private, no-cache, must-revalidate');
        header('ETag: "' . md5($path . filemtime($file)) . '"');

        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . md5($path . filemtime($file)) . '"') {
            http_response_code(304);
            Yii::app()->end();
        }

        readfile($file);
        Yii::app()->end();
    }

    /**
     * Где лежат ассеты панели.
     *
     * @return string
     */
    protected function assetRoot()
    {
        // 1) рядом с модулем: protected/modules/adm/assets
        // 2) в поставке: cms-private/admin-assets
        $candidates = array(
            dirname(__FILE__) . '/assets',
            Yii::getPathOfAlias('application') . '/../admin-assets',
            Yii::getPathOfAlias('application') . '/../public/admin',
        );

        foreach ($candidates as $dir) {
            $dir = realpath($dir);

            if ($dir && is_dir($dir)) {
                return $dir;
            }
        }

        return '/nonexistent';
    }
}
