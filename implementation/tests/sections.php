<?php
class CHttpException extends RuntimeException { public $statusCode; public function __construct($code,$message) {$this->statusCode=$code;parent::__construct($message);} }
$root=dirname(__DIR__).'/public_html/cms-private/protected/components/';
foreach (['PortalSchema','PortalStore','CabinetSections'] as $c) require $root.$c.'.php';
$db=new PDO('sqlite::memory:');PortalSchema::install($db,'t_');$store=new PortalStore($db,'t_');$sections=new CabinetSections($store);$count=0;
function check($v,$label){global $count;$count++;if(!$v)throw new RuntimeException($label);}
function rejected($f,$label){try{$f();}catch(Throwable $e){check(true,$label);return;}check(false,$label);}
foreach (array_keys(CabinetSections::catalog()) as $key) check($sections->enabled($key),'Default section '.$key);
check($sections->set(1,'exchange','0')['changed']===true,'Disable section');check(!$sections->enabled('exchange'),'Same-request state refresh');
check(!(new CabinetSections($store))->enabled('exchange'),'Persistence across requests');
check($sections->set(1,'exchange','0')['changed']===false,'Repeated request is harmless');
check((int)$store->query('SELECT COUNT(*) FROM {{portal_audit}}')->fetchColumn()===1,'One audit record per change');
rejected(function()use($sections){$sections->set(1,'security','0');},'Security cannot be disabled');
rejected(function()use($sections){$sections->set(1,'admin','0');},'Owner access cannot be disabled');
rejected(function()use($sections){$sections->set(1,'home','0');},'Home cannot be disabled');
rejected(function()use($sections){$sections->set(1,'exchange','true');},'Strict state validation');
foreach (['buy','sell','cancel','withdraw','transfer','import','inventory','payment','paymentStatus','demoTopup'] as $op) rejected(function()use($sections,$op){$sections->requireOperation($op);},'Closed exchange blocks '.$op);
$sections->set(1,'exchange','1');$sections->set(1,'warehouse','0');
foreach (['deliver','coins','import'] as $op) rejected(function()use($sections,$op){$sections->requireOperation($op);},'Closed warehouse blocks '.$op);
$sections->set(1,'deposit','0');
foreach (['coinQuote','demoCoinTopup','coins'] as $op) rejected(function()use($sections,$op){$sections->requireOperation($op);},'Closed deposit blocks '.$op);
$sections->requireOperation('balance');check(true,'Essential balance read survives section switches');
foreach ([['accounts','create','accounts'],['characters','teleport','accounts'],['default','calendarClaim','calendar'],['shopCoin','shopCreate','deposit'],['utopayShop','buy','shop'],['portal','exchange','exchange'],['portal','warehouse','warehouse'],['referals','index','referrals'],['transaction-history','index','history'],['messages','detail','messages'],['default','index',null],['default','logout',null],['portal','admin',null],['portal','security',null],['portal','request',null]] as $r) check($sections->forRoute($r[0],$r[1])===$r[2],'Route mapping '.$r[0].'/'.$r[1]);
check($store->setting('market_enabled')==='0' && $store->setting('topup_enabled')==='0' && $store->setting('bridge_verified')==='0','Section settings do not enable financial or inventory integrations');
echo 'PASS: '.$count.' section management assertions'.PHP_EOL;
