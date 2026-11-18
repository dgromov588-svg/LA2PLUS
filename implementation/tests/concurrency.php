<?php
declare(strict_types=1);
$root=dirname(__DIR__).'/public_html/cms-private/protected/components/';
foreach (['PortalSchema','PortalMoney','PortalStore'] as $c) require $root.$c.'.php';
$file=tempnam(sys_get_temp_dir(),'portal-race-');
try {
    $db=new PDO('sqlite:'.$file); $db->exec('PRAGMA busy_timeout=5000');
    PortalSchema::install($db); $store=new PortalStore($db);
    $store->query("UPDATE portal_settings SET value='1' WHERE name='market_enabled'");
    foreach ([1,2] as $user) { $store->wallet($user); $store->query('UPDATE portal_wallets SET available=10000 WHERE user_id=?',[$user]); }
    $item=$store->grantItem(3,1,['item_id'=>1,'quantity'=>1,'name'=>'Unique sword','enchant'=>16,'kind'=>'WEAPON','grade'=>'S','augmented'=>true,'attributes'=>['variationId1'=>101]],'unique-item');
    $lot=$store->sell(3,$item,10000,'unique-lot-key001');
    $children=[];
    foreach ([1,2] as $buyer) {
        $pid=pcntl_fork();
        if ($pid===0) {
            try {
                $connection=new PDO('sqlite:'.$file); $connection->exec('PRAGMA busy_timeout=5000');
                (new PortalStore($connection))->buy($buyer,$lot['id'],1,'buyer-request-key'.$buyer);
                exit(0);
            } catch (Throwable $e) { exit(2); }
        }
        $children[]=$pid;
    }
    $success=0;
    foreach ($children as $pid) { pcntl_waitpid($pid,$status); if (pcntl_wexitstatus($status)===0) $success++; }
    if ($success!==1) throw new RuntimeException('Expected exactly one successful purchase');
    $sold=$store->query('SELECT * FROM portal_lots')->fetch(PDO::FETCH_ASSOC);
    $owner=(int)$store->query('SELECT user_id FROM portal_items')->fetchColumn();
    if ($sold['state']!=='sold' || $owner!==(int)$sold['buyer_id']) throw new RuntimeException('Incorrect item custody');
    if ((int)$store->wallet(3)['available']!==9000) throw new RuntimeException('Seller credited more than once');
    $sum=(int)$store->query('SELECT SUM(available) FROM portal_wallets')->fetchColumn();
    if ($sum!==19000) throw new RuntimeException('Currency conservation failed');
    echo "PASS: competing buyers, one item owner, one seller credit, conserved balances minus fee\n";
} finally { @unlink($file); }
