<?php

/**
 * Контроллер API админки: /adm/<действие>.
 *
 * Yii ищет файл контроллера по имени класса, поэтому здесь только обёртка —
 * логика в AdmBaseController, чтобы не дублировать 2000 строк.
 */
require_once dirname(__DIR__) . '/AdmApiController.php';
require_once dirname(__DIR__) . '/AdmBaseController.php';

class AdmController extends AdmBaseController
{
}
