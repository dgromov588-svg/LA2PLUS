<?php

/**
 * LA2PLUS • Комплексный сервис самодиагностики и проверки целостности системы.
 *
 * Проверяет:
 *  1) MySQL сайта (подключение, время отклика, наличие всех таблиц);
 *  2) Игровую базу Lucera2 (GS, таблицы accounts, characters, items_delayed);
 *  3) Конфигурацию params.php (права записи, супер-администраторы);
 *  4) PWA и статические ресурсы (манифест, service worker, офлайн, иконки);
 *  5) Системное окружение (PHP, память, время, расширения).
 *
 * Совместим с PHP 5.6+ / 7.x / 8.x (Yii 1.1).
 */
class DiagnosticService
{
    /**
     * Запуск полного комплекса проверок.
     *
     * @return array
     */
    public static function runSuite()
    {
        $start = microtime(true);

        $dbCheck = self::checkDatabase();
        $gameCheck = self::checkGameDatabase();
        $configCheck = self::checkParamsConfig();
        $pwaCheck = self::checkPwaAssets();
        $systemCheck = self::checkSystem();

        $allOk = $dbCheck['ok'] && $configCheck['ok'] && $pwaCheck['ok'];
        $duration = round((microtime(true) - $start) * 1000, 2);

        return array(
            'ok' => $allOk,
            'duration_ms' => $duration,
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'database' => $dbCheck,
            'game_server' => $gameCheck,
            'config' => $configCheck,
            'pwa' => $pwaCheck,
            'system' => $systemCheck,
        );
    }

    /**
     * Проверка базы данных сайта.
     *
     * @return array
     */
    public static function checkDatabase()
    {
        $start = microtime(true);
        $res = array(
            'ok' => false,
            'latency_ms' => 0,
            'tables' => array(),
            'error' => null,
        );

        try {
            $db = db();
            $db->createCommand('SELECT 1')->queryScalar();
            $res['latency_ms'] = round((microtime(true) - $start) * 1000, 2);
            $res['ok'] = true;

            $tables = array(
                'users' => 'Пользователи сайта',
                'roles' => 'Роли сотрудников панели',
                'role_log' => 'История смены ролей',
                'staff_log' => 'Журнал действий сотрудников',
                'ghtweb_visit_log' => 'Аналитика и визиты',
                'transactions' => 'Платежные транзакции',
            );

            foreach ($tables as $table => $desc) {
                try {
                    $count = (int) $db->createCommand("SELECT COUNT(*) FROM {{" . $table . "}}")->queryScalar();
                    $res['tables'][$table] = array(
                        'exists' => true,
                        'count' => $count,
                        'desc' => $desc,
                    );
                } catch (Exception $e) {
                    $res['tables'][$table] = array(
                        'exists' => false,
                        'count' => 0,
                        'desc' => $desc,
                        'error' => $e->getMessage(),
                    );
                    // roles и users обязательны
                    if ($table === 'users' || $table === 'roles') {
                        $res['ok'] = false;
                    }
                }
            }
        } catch (Exception $e) {
            $res['ok'] = false;
            $res['error'] = $e->getMessage();
        }

        return $res;
    }

    /**
     * Проверка базы игрового сервера (Lucera2).
     *
     * @return array
     */
    public static function checkGameDatabase()
    {
        $start = microtime(true);
        $res = array(
            'ok' => false,
            'latency_ms' => 0,
            'tables' => array(),
            'accounts_count' => 0,
            'characters_count' => 0,
            'online_count' => 0,
            'error' => null,
        );

        try {
            $gsId = user()->isGuest ? 1 : user()->getGsId();
            $game = l2('gs', $gsId)->connect();
            $gdb = $game->getDb();

            $gdb->createCommand('SELECT 1')->queryScalar();
            $res['latency_ms'] = round((microtime(true) - $start) * 1000, 2);
            $res['ok'] = true;

            $gtables = array('accounts', 'characters', 'clan_data', 'items_delayed');
            foreach ($gtables as $gt) {
                try {
                    $gdb->createCommand("SELECT 1 FROM " . $gt . " LIMIT 1")->query();
                    $res['tables'][$gt] = true;
                } catch (Exception $e) {
                    $res['tables'][$gt] = false;
                }
            }

            if (!empty($res['tables']['accounts'])) {
                $res['accounts_count'] = (int) $gdb->createCommand("SELECT COUNT(*) FROM accounts")->queryScalar();
            }
            if (!empty($res['tables']['characters'])) {
                $res['characters_count'] = (int) $gdb->createCommand("SELECT COUNT(*) FROM characters")->queryScalar();
                $res['online_count'] = (int) $gdb->createCommand("SELECT COUNT(*) FROM characters WHERE online = 1")->queryScalar();
            }
        } catch (Exception $e) {
            $res['ok'] = false;
            $res['error'] = $e->getMessage();
        } catch (Throwable $e) {
            $res['ok'] = false;
            $res['error'] = $e->getMessage();
        }

        return $res;
    }

    /**
     * Проверка прав и синтаксиса params.php.
     *
     * @return array
     */
    public static function checkParamsConfig()
    {
        $file = Yii::getPathOfAlias('application.config') . DIRECTORY_SEPARATOR . 'params.php';
        $res = array(
            'ok' => false,
            'path' => $file,
            'exists' => is_file($file),
            'writable' => is_writable($file),
            'permissions' => is_file($file) ? substr(sprintf('%o', fileperms($file)), -4) : null,
            'superAdminId' => array(),
            'owners_count' => 0,
            'error' => null,
        );

        if (!$res['exists']) {
            $res['error'] = 'Файл params.php не найден';
            return $res;
        }

        $owners = RoleService::getOwnerIds();
        $res['superAdminId'] = $owners;
        $res['owners_count'] = count($owners);

        if ($res['writable'] && $res['owners_count'] > 0) {
            $res['ok'] = true;
        } else {
            if (!$res['writable']) {
                $res['error'] = 'Файл params.php недоступен для записи PHP (требуются права 664/644)';
            } elseif ($res['owners_count'] === 0) {
                $res['error'] = 'Список superAdminId пуст';
            }
        }

        return $res;
    }

    /**
     * Проверка PWA-ресурсов и файлов сайта.
     *
     * @return array
     */
    public static function checkPwaAssets()
    {
        $webroot = Yii::getPathOfAlias('webroot');
        $files = array(
            '/manifest.json' => array('type' => 'Манифест приложения', 'required' => true),
            '/service-worker.js' => array('type' => 'Service Worker', 'required' => true),
            '/offline.html' => array('type' => 'Офлайн-страница', 'required' => true),
            '/design/css/pages/pwa.css' => array('type' => 'Стили карточки PWA', 'required' => true),
            '/design/js/pwa.js' => array('type' => 'Скрипт установки PWA', 'required' => true),
            '/design/assets/icons/favicon-180.png' => array('type' => 'Иконка iOS 180px', 'required' => true),
            '/design/assets/icons/favicon-192.png' => array('type' => 'Иконка Android 192px', 'required' => true),
            '/design/assets/icons/favicon-512.png' => array('type' => 'Иконка Splash 512px', 'required' => true),
            '/design/assets/icons/favicon-maskable-512.png' => array('type' => 'Иконка Maskable 512px', 'required' => false),
            '/design/assets/icons/favicon.ico' => array('type' => 'Фавиконка .ico', 'required' => true),
        );

        $res = array(
            'ok' => true,
            'assets' => array(),
            'missing' => array(),
        );

        foreach ($files as $rel => $info) {
            $abs = $webroot . $rel;
            $exists = is_file($abs) && is_readable($abs);
            $size = $exists ? filesize($abs) : 0;
            $mtime = $exists ? date('Y-m-d H:i:s', filemtime($abs)) : null;

            $res['assets'][$rel] = array(
                'exists' => $exists,
                'size' => $size,
                'mtime' => $mtime,
                'desc' => $info['type'],
            );

            if (!$exists && $info['required']) {
                $res['ok'] = false;
                $res['missing'][] = $rel;
            }
        }

        return $res;
    }

    /**
     * Сведения об окружении сервера.
     *
     * @return array
     */
    public static function checkSystem()
    {
        return array(
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'memory_used_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'opcache_enabled' => function_exists('opcache_get_status') && @opcache_get_status() !== false,
            'extensions' => array(
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring'),
                'curl' => extension_loaded('curl'),
                'gd' => extension_loaded('gd'),
            ),
        );
    }
}
