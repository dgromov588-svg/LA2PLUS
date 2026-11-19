<?php
/** Isolated QA fixture. This file is never shipped to the hosting account. */
$components=dirname(__DIR__).'/public_html/cms-private/protected/components/';
foreach (['PortalSchema','PortalMoney','PortalStore','PortalSecurity','PortalRuntime','PortalI18n','BrandLogo','CabinetSections','CabinetNavigation','PortalRoutes','PortalProduction','PortalCoinPayments','PortalTrybit'] as $c) require $components.$c.'.php';
class CHtml {
    public static function encode($s) { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
}
class RoleService { public static function isSiteOwner() { return true; } public static function can($p) { return true; } }
class WalletService { public static function getBalance($u) { return 450; } }
class PreviewUser {
    public function getId() { return 1; } public function getGsId() { return 1; }
    public function get($key) { return $key==='email'?'player@example.test':'Player'; }
    public function getState($key) { return null; }
}
class PreviewRequest {
    public $csrfTokenName='YII_CSRF_TOKEN';
    public function getCsrfToken() { return 'fixture-token'; }
    public function getQuery($name,$default=null) { return $_GET[$name]??$default; }
}
class PreviewDb {
    public $active=true; public $tablePrefix=''; public $pdo;
    public function __construct() { $this->pdo=new PDO('sqlite::memory:'); }
    public function getPdoInstance() { return $this->pdo; }
}
class PreviewCommand {
    public function where(...$args) { return $this; }
    public function queryAll() { return [['char_id'=>10,'char_name'=>'Asterion','level'=>80,'online'=>0,'account_name'=>'Player'],['char_id'=>11,'char_name'=>'SilverMoon','level'=>76,'online'=>1,'account_name'=>'Player']]; }
}
class PreviewGame {
    public function connect() { return $this; } public function getDb() { return $this; }
    public function createCommand() { return new PreviewCommand(); } public function characters($q) { return $q; }
}
class AccountLinkService {
    public static function getAccounts($u) { return [['id'=>1,'gs_id'=>1,'status'=>1,'game_login'=>'Player','user_id'=>1]]; }
}
function app() { return (object)['language'=>$_GET['lang']??'ru']; }
function user() { return new PreviewUser(); } function request() { return new PreviewRequest(); }
function l2(...$args) { return new PreviewGame(); }
function config($key) { return $key==='utopay.rate'?'1':'0'; }
function formatCurrency($amount,$extra=false) { return number_format($amount,2,'.',' '); }
function db() { static $db; return $db??($db=new PreviewDb()); }
PortalSchema::install(db()->pdo); $store=PortalRuntime::store();
foreach (['market','warehouse','withdrawals'] as $f) $store->query('UPDATE portal_settings SET value=? WHERE name=?',['1',$f.'_enabled']);
$store->query('INSERT INTO portal_security(user_id,email_verified_at,email_hash) VALUES(1,?,?)',[time(),hash('sha256','player@example.test')]);
$store->setDiscount(1,1,10,time()+86400);
$store->wallet(1); $store->query('UPDATE portal_wallets SET available=12345 WHERE user_id=1');
$store->grantItem(1,1,['item_id'=>7575,'quantity'=>1,'name'=>'Draconic Bow','enchant'=>7,'kind'=>'WEAPON','grade'=>'S'],'fixture:1');
$item=$store->grantItem(2,1,['item_id'=>7575,'quantity'=>1,'name'=>'Arcana Mace','enchant'=>6,'kind'=>'WEAPON','grade'=>'S','augmented'=>1],'fixture:2');
$store->sell(2,$item,25000,'fixture-sell-key1');
class PreviewController {
    public $pageTitle;
    public function createUrl($route) { return '/preview?page='.basename($route); }
    public function renderPartial($view) {}
    public function render($page,$store) {
        $accounts=AccountLinkService::getAccounts(1);
        include dirname(__DIR__).'/public_html/cms-private/themes/la2plus/views/cabinet/portal.php';
    }
}
?><!doctype html><html lang="<?php echo CHtml::encode(app()->language); ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="https://la2plus.com/design/css/base.css"><link rel="stylesheet" href="https://la2plus.com/design/css/components/buttons.css"><link rel="stylesheet" href="/design/css/pages/portal.css"><link rel="stylesheet" href="/design/css/pages/portal-polish.css"><link rel="stylesheet" href="/design/css/pages/portal-fresh.css"><link rel="stylesheet" href="<?php echo BrandLogo::STYLES; ?>"><style>body{margin:0;background:linear-gradient(#080d16bb,#080b12ee),url(https://la2plus.com/design/assets/images/env-hero-2.jpg) center top/cover fixed}*{box-sizing:border-box}</style></head><body>
<?php $page=$_GET['page']??'home'; if ($page==='index') $page='home'; (new PreviewController())->render($page,$store); ?>
</body></html>
