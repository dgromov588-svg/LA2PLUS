<?php
$root=dirname(__DIR__).'/public_html/cms-private/protected/components/';
class DbConfig { public function get($key,$default=null) { return 'upstream-setting'; } }
require dirname(__DIR__).'/staging/StagingConfig.php';
$config=new StagingConfig();$count=0;
function expect($condition,$message) {global $count;$count++;if (!$condition) throw new RuntimeException($message);}
foreach (['trybit.api_key','utopay.allow','wayforpay.allow','stripe.secret','mail.smtp_host','mail.smtp_password','register.allow'] as $key) expect($config->get($key)==='0','External service override: '.$key);
expect($config->get('utopay.rate')==='1','Demo conversion rate');expect($config->get('theme')==='upstream-setting','Presentation settings remain configurable');
require $root.'PortalRuntime.php';require dirname(__DIR__).'/staging/StagingGameAdapter.php';
expect(!PortalRuntime::isDemo() && !StagingGameAdapter::ready(),'Cannot activate a demo without the dedicated deployment marker');
define('LA2PLUS_STAGING',true);
$demoApp=(object)['params'=>['staging'=>true]];$demoDb=(object)['connectionString'=>'mysql:host=localhost;dbname=laplusco_newcms;charset=utf8mb4'];
function app(){global $demoApp;return $demoApp;}function db(){global $demoDb;return $demoDb;}
$_SERVER['HTTP_HOST']='la2plus.com';expect(!StagingGameAdapter::ready(),'Production hostname always rejected');
$_SERVER['HTTP_HOST']='new.la2plus.com';$demoDb->connectionString='mysql:host=localhost;dbname=production;charset=utf8mb4';expect(!StagingGameAdapter::ready(),'Production database always rejected');
echo 'PASS: '.$count.' staging isolation assertions'.PHP_EOL;
