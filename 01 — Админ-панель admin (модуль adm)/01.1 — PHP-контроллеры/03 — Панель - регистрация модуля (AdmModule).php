<?php

/**
 * LA2PLUS • админ-панель v4.
 *
 * Модуль отдаёт два вида ответов:
 *  - /admin-v4/ — страница SPA (золотая тема прототипа);
 *  - /adm/*     — JSON для этой SPA.
 *
 * Контроллеры лежат в modules/adm/controllers (штатное место Yii):
 *   PageController — страница и статика,
 *   AdmController  — API.
 * Права проверяются на сервере: каждая секция состояния и каждое действие
 * сначала спрашивают RoleService, и только потом трогают базу.
 */
class AdmModule extends CWebModule
{
    public function init()
    {
        // Путь задаём вызовом метода: присваивание свойства зависит от версии
        // CModule на сервере, а setControllerPath() есть в любом Yii 1.1.
        $this->setControllerPath(dirname(__FILE__) . '/controllers');

        // Компоненты и модели проекта нужны и внутри модуля.
        $this->setImport(array(
            'application.components.*',
            'application.models.*',
        ));

        parent::init();
    }
}
