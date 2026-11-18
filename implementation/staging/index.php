<?php
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');header('Referrer-Policy: same-origin');header('X-Robots-Tag: noindex, nofollow, noarchive');
if (($_SERVER['HTTP_HOST']??'')!=='new.la2plus.com') {http_response_code(403);exit;}
define('LA2PLUS_STAGING',true);define('YII_DEBUG',false);define('YII_TRACE_LEVEL',1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);ini_set('display_errors','0');
$root=__DIR__.'/cms-private';
foreach ([$root.'/protected/runtime',__DIR__.'/assets',__DIR__.'/uploads'] as $dir) if (!is_dir($dir)) mkdir($dir,0750,true);
ini_set('log_errors','1');ini_set('error_log',$root.'/protected/runtime/php-error.log');
foreach ([__DIR__.'/assets',__DIR__.'/uploads'] as $publicDir) chmod($publicDir,0755);
foreach ([$root.'/staging.json',$root.'/protected/config/database.php'] as $privateFile) chmod($privateFile,0600);
if (session_status()===PHP_SESSION_ACTIVE) {session_abort();session_id('');unset($_SESSION);}
$sessionPath=$root.'/protected/runtime/sessions';if (!is_dir($sessionPath)) mkdir($sessionPath,0700,true);
session_name('LA2PLUS_NEWSESSID');session_save_path($sessionPath);ini_set('session.use_strict_mode','1');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
$ownSession=$_COOKIE['LA2PLUS_NEWSESSID']??'';if (preg_match('/^[A-Za-z0-9,-]{16,128}$/D',$ownSession)) session_id($ownSession);
session_start();
require_once $root.'/protected/helpers/global.php';require_once $root.'/vendor/autoload.php';require_once $root.'/framework/yii.php';
$app=Yii::createWebApplication($root.'/protected/config/main.php');
CHtml::setModelNameConverter(function($model){return (new ReflectionClass($model))->getShortName();});
$app->run();
