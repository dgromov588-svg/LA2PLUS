<?php
$config=require __DIR__.'/main-base.php';
$secret=json_decode(file_get_contents(dirname(__DIR__,2).'/staging.json'),true,512,JSON_THROW_ON_ERROR);
$config['id']='la2plus-new-staging';
$config['name']='LA2PLUS — ТЕСТОВАЯ ВЕРСИЯ';
$config['homeUrl']='/cabinet/';
$config['runtimePath']=dirname(__DIR__).'/runtime';
$config['components']['db']=require __DIR__.'/database.php';
$config['components']['config']=['class'=>'StagingConfig'];
$config['components']['session']=['class'=>'CHttpSession'];
$config['components']['user']['identityCookie']=['name'=>'LA2PLUS_NEW_USER','path'=>'/','secure'=>true,'httpOnly'=>true,'sameSite'=>'Lax'];
$config['components']['user']['stateKeyPrefix']='LA2PLUS_NEW_USER';
$config['components']['admin']['identityCookie']=['name'=>'LA2PLUS_NEW_ADMIN','path'=>'/','secure'=>true,'httpOnly'=>true,'sameSite'=>'Lax'];
$config['components']['admin']['stateKeyPrefix']='LA2PLUS_NEW_ADMIN';
$config['components']['securityManager']['validationKey']=$secret['validation_key'];
// Cookie authentication uses the dedicated HMAC validation key. Legacy mcrypt encryption is unused.
unset($config['components']['securityManager']['encryptionKey']);
$config['components']['request']['enableCsrfValidation']=true;
$config['components']['request']['enableCookieValidation']=false;
$config['components']['request']['csrfTokenName']='LA2PLUS_NEW_CSRF';
$config['components']['request']['csrfCookie']=['path'=>'/','secure'=>true,'httpOnly'=>true,'sameSite'=>'Lax'];
$config['components']['cache']['keyPrefix']='la2plus_new_stage_';
$config['params']['superAdminId']=[1];
$config['params']['staging']=true;
$config['onBeginRequest']=['StagingGuard','begin'];
$config['onEndRequest']=['StagingGuard','banner'];
return $config;
