<?php
/** Run from a shell/cron. Never register this file as a web route. */
if (PHP_SAPI!=='cli' && !defined('PORTAL_RELEASE_RUN')) { http_response_code(403); exit; }
$root=dirname(__DIR__,2);
defined('YII_DEBUG') || define('YII_DEBUG',false);
defined('YII_TRACE_LEVEL') || define('YII_TRACE_LEVEL',1);
$_SERVER['SCRIPT_FILENAME']=dirname($root).'/index.php';
$_SERVER['SCRIPT_NAME']='/index.php';
$_SERVER['HTTP_HOST']=$_SERVER['HTTP_HOST']??'la2plus.com';
$_SERVER['REQUEST_URI']=$_SERVER['REQUEST_URI']??'/';
require_once $root.'/protected/helpers/global.php';
require_once $root.'/vendor/autoload.php';
require_once $root.'/framework/yii.php';
$app=Yii::createWebApplication($root.'/protected/config/main.php');
db()->active=true;
$command=$argv[1]??'status';
try {
    if ($command==='migrate') {
        PortalSchema::install(db()->getPdoInstance(),db()->tablePrefix);
        echo json_encode(['ok'=>true,'schema'=>1]);
    } elseif ($command==='status') {
        $store=PortalRuntime::store();
        $values=[]; foreach (['cabinet','market','warehouse','topup','withdrawals'] as $f) $values[$f]=$store->enabled($f);
        echo json_encode(['ok'=>true,'features'=>$values,'bridge'=>$store->setting('bridge_verified')==='1']);
    } elseif ($command==='set') {
        $name=$argv[2]??''; $value=$argv[3]??'';
        $allowed=['cabinet_enabled','market_enabled','warehouse_enabled','topup_enabled','withdrawals_enabled','col_item_id','bridge_url'];
        if (!in_array($name,$allowed,true)) throw new RuntimeException('Unknown setting');
        $store=PortalRuntime::store();
        if (in_array($name,['market_enabled','warehouse_enabled'],true) && $value==='1' && $store->setting('bridge_verified')!=='1') throw new RuntimeException('Run and record the game integration acceptance suite before enabling inventory operations.');
        if ($name==='topup_enabled' && $value==='1' && $store->setting('net_topup_verified')!=='1') throw new RuntimeException('Trybit net settlement has not been verified.');
        $store->atomic(function () use ($store,$name,$value) {
            $store->query('DELETE FROM {{portal_settings}} WHERE name=?',[$name]);
            $store->query('INSERT INTO {{portal_settings}}(name,value) VALUES(?,?)',[$name,$value]);
            $store->audit(0,'setting.set',$name,['value'=>$value]);
        });
        echo json_encode(['ok'=>true,'name'=>$name,'value'=>$value]);
    } elseif ($command==='worker') {
        $store=PortalRuntime::store(); $done=0; $failed=0;
        foreach ($store->query("SELECT id,user_id FROM {{portal_payments}} WHERE state='pending' ORDER BY id LIMIT 50")->fetchAll(PDO::FETCH_ASSOC) as $payment) {
            try { PortalPayments::sync($payment['user_id'],$payment['id']); } catch (Throwable $e) { $failed++; }
        }
        if ($store->enabled('warehouse') && $store->setting('bridge_verified')==='1') {
            foreach ($store->query("SELECT id FROM {{portal_deliveries}} WHERE state='pending' ORDER BY id LIMIT 50")->fetchAll(PDO::FETCH_COLUMN) as $id) {
                try {
                    PortalDelivery::process($store,$id,function ($row) {
                        return PortalRuntime::bridgeFor('deliver',$row['operation_key'],[
                            'char_id'=>(int)$row['char_id'],'item'=>['item_id'=>(int)$row['game_item_id'],'quantity'=>(int)$row['quantity'],'enchant'=>(int)$row['enchant'],'augmented'=>(bool)$row['augmented'],'attributes'=>json_decode($row['attributes'],true,512,JSON_THROW_ON_ERROR)],
                        ],$row['gs_id'],$row['user_id']);
                    }); $done++;
                } catch (Throwable $e) { $failed++; }
            }
        }
        echo json_encode(['ok'=>true,'delivered'=>$done,'retry'=>$failed]);
    } else throw new RuntimeException('Commands: migrate, status, set, worker');
} catch (Throwable $e) {
    if (PHP_SAPI!=='cli') http_response_code(500);
    if (PHP_SAPI==='cli') fwrite(STDERR,'Portal command failed: '.$e->getMessage().PHP_EOL);
    else { error_log('Portal release command failed: '.$e->getMessage()); echo json_encode(['ok'=>false,'error'=>'Migration failed; check server log']); }
    exit(1);
}
