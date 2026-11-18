<?php
/** Installed only on the isolated new.la2plus.com staging copy. */
class StagingGuard
{
 public static function begin($event) {
  if (request()->getHostInfo()!=='https://new.la2plus.com') throw new CHttpException(403,'Только тестовый домен.');
  if (strpos(db()->connectionString,'dbname=laplusco_newcms;')===false) throw new CHttpException(503,'Небезопасная конфигурация тестовой базы.');
  foreach (['gs','ls'] as $table) foreach (db()->createCommand('SELECT db_name,db_host FROM {{'.$table.'}}')->queryAll() as $row) if ($row['db_name']!=='laplusco_newgame' || $row['db_host']!=='localhost') throw new CHttpException(503,'Тестовый сервер должен использовать отдельную базу.');
  $path=request()->getPathInfo();
  $normalized=strtolower(str_replace(['-','_'],'',$path));
  if (preg_match('#^cabinet/(?:shopcoin|shoputopay|wayforpayshop)(?:/|$)|^cabinet/deposit/(?:transfer|shopcreate)(?:/|$)#',$normalized)) throw new CHttpException(403,'Боевые платежи на тестовой площадке отключены.');
  if (preg_match('#^(deposit(?:[-/]|$)|cabinet/(?:shop-utopay|wayforpay-shop|shop-coin|deposit/transfer)|register(?:/|$)|install(?:/|$))#i',$path)) throw new CHttpException(403,'В тестовой версии внешние операции отключены.');
  if (rtrim($path,'/')==='cabinet/portal/request' && request()->getIsPostRequest() && request()->getPost('op')==='demoTopup') {
   if (user()->isGuest) throw new CHttpException(401,'Сначала войдите в тестовый кабинет.');
   request()->validateCsrfToken($event);
   header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
   try {$result=StagingGameAdapter::topup((int)user()->getId(),PortalMoney::cents(request()->getPost('amount')),(string)request()->getPost('request_key'));echo json_encode(['ok'=>true,'result'=>$result],JSON_UNESCAPED_UNICODE);}
   catch (Throwable $e) {http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
   app()->end();
  }
  if ($path==='') {app()->request->redirect('/cabinet/');return;}
  if (rtrim($path,'/')==='test-mail') {
   if (user()->isGuest) {app()->request->redirect('/login/');return;}
   if (!RoleService::isSiteOwner()) throw new CHttpException(403,'Только тестовый владелец.');
   header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');
   echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Тестовая почта</title><style>body{background:#10131b;color:#e6d3a0;font:16px sans-serif;max-width:900px;margin:40px auto}article{border:1px solid #65502d;padding:20px;margin:15px 0;white-space:pre-wrap}a{color:#e6d3a0}</style><h1>Тестовый почтовый ящик</h1><p>Письма не отправляются в интернет. Здесь только сообщения этой тестовой площадки.</p><a href="/cabinet/portal/security/">Назад в кабинет</a>';
   $file=app()->runtimePath.'/staging-mails.jsonl';
   $lines=is_file($file)?array_reverse(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)):[];
   foreach (array_slice($lines,0,30) as $line) {$m=json_decode($line,true);if (!$m) continue;echo '<article>'.CHtml::encode($m['at'].' — '.$m['to']."\n".$m['subject']."\n".strip_tags($m['body'])).'</article>';}
   app()->end();
  }
 }
 public static function banner($event) {
  if (PHP_SAPI!=='cli' && class_exists('StagingGameAdapter')) StagingGameAdapter::flushDeliveries();
  if (PHP_SAPI==='cli' || request()->isAjaxRequest || request()->getIsPostRequest()) return;
  foreach (headers_list() as $header) if (stripos($header,'Content-Type:')===0 && stripos($header,'text/html')===false) return;
  if (http_response_code()>=300 && http_response_code()<400) return;
  echo '<aside style="position:fixed;bottom:12px;left:50%;transform:translateX(-50%);max-width:94vw;z-index:99999;padding:10px 18px;background:#151a23f5;backdrop-filter:blur(12px);border:1px solid #c9a96240;border-radius:12px;color:#d5c192;text-align:center;font:11px/1.6 system-ui,sans-serif;box-shadow:0 8px 35px #0005">Тестовая версия · Все средства виртуальные · <a style="color:#f0d6a0;white-space:nowrap" href="/test-mail/">Тестовая почта ↗</a></aside>';
 }
 public static function capture($to,$subject,$message) {
  $file=app()->runtimePath.'/staging-mails.jsonl';
  $data=['at'=>date('c'),'to'=>$to,'subject'=>$subject,'body'=>$message];
  $ok=file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n",FILE_APPEND|LOCK_EX)!==false;
  if ($ok) chmod($file,0600); return $ok;
 }
}
