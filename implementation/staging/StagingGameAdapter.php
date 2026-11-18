<?php
/** Demonstration adapter. Never install this class on the production application. */
class StagingGameAdapter
{
    public static function ready()
    {
        return defined('LA2PLUS_STAGING') && LA2PLUS_STAGING && (app()->params['staging']??false)
            && ($_SERVER['HTTP_HOST']??'')==='new.la2plus.com'
            && strpos(db()->connectionString,'dbname=laplusco_newcms;')!==false
            && PortalRuntime::store()->setting('staging_demo_inventory')==='1';
    }
    private static function game($gs)
    {
        $server=Gs::model()->findByPk((int)$gs);
        if (!$server || $server->db_name!=='laplusco_newgame' || $server->db_host!=='localhost') throw new RuntimeException('Разрешена только тестовая игровая база.');
        $db=l2('gs',(int)$gs)->connect()->getDb();$db->active=true;return $db->getPdoInstance();
    }
    private static function character(PDO $game,$id,$gs,$uid)
    {
        $q=$game->prepare('SELECT * FROM characters WHERE obj_Id=? FOR UPDATE');$q->execute([(int)$id]);$char=$q->fetch(PDO::FETCH_ASSOC);
        if (!$char) throw new RuntimeException('Тестовый персонаж не найден.');
        $owned=db()->createCommand('SELECT COUNT(*) FROM {{account_links}} l JOIN {{account_groups}} g ON g.id=l.group_id WHERE l.game_login=:a AND l.gs_id=:gs AND l.status=1 AND (l.user_id=:u OR l.owner_id=:o OR g.owner_id=:g)')->queryScalar([':a'=>$char['account_name'],':gs'=>(int)$gs,':u'=>(int)$uid,':o'=>(int)$uid,':g'=>(int)$uid]);
        if (!$owned) throw new RuntimeException('Персонаж принадлежит другому тестовому профилю.');
        if ((int)$char['online']) throw new RuntimeException('Тестовый персонаж должен быть офлайн.');
        return $char;
    }
    public static function handle($op,$key,array $payload,$gs,$uid)
    {
        if (!self::ready() || !in_array($op,['inventory','escrow','deliver'],true) || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key)) throw new RuntimeException('Демонстрационная операция недоступна.');
        $game=self::game($gs);$game->beginTransaction();
        try {
            self::character($game,$payload['char_id']??0,$gs,$uid);
            $fingerprint=hash('sha256',json_encode([$op,$payload,(int)$gs,(int)$uid],JSON_THROW_ON_ERROR));
            if ($op!=='inventory') {
                // Lock one stable row before checking the receipt, serializing duplicate requests.
                $game->query('SELECT id FROM demo_bridge_lock WHERE id=1 FOR UPDATE')->fetchColumn();
                $q=$game->prepare('SELECT * FROM demo_bridge_ops WHERE operation_key=? FOR UPDATE');$q->execute([$key]);$prior=$q->fetch(PDO::FETCH_ASSOC);
                if ($prior) {
                    if (!hash_equals($prior['fingerprint'],$fingerprint)) throw new RuntimeException('Номер демонстрационной операции уже использован.');
                    $game->commit();return json_decode($prior['result'],true,512,JSON_THROW_ON_ERROR);
                }
            }
            $result=['ok'=>true,'key'=>$key,'demo'=>true];
            if ($op==='inventory') {
                $q=$game->prepare('SELECT * FROM demo_inventory WHERE char_id=? ORDER BY object_id');$q->execute([(int)$payload['char_id']]);
                $result['items']=[];foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $item) {$item['attributes']=json_decode($item['attributes'],true,512,JSON_THROW_ON_ERROR);$result['items'][]=$item;}
            } elseif ($op==='escrow') {
                $q=$game->prepare('SELECT * FROM demo_inventory WHERE object_id=? AND char_id=? FOR UPDATE');$q->execute([(int)($payload['object_id']??0),(int)$payload['char_id']]);$item=$q->fetch(PDO::FETCH_ASSOC);
                if (!$item) throw new RuntimeException('Тестовый предмет уже перемещён или не принадлежит персонажу.');
                $game->prepare('DELETE FROM demo_inventory WHERE object_id=?')->execute([$item['object_id']]);
                unset($item['object_id'],$item['char_id']);$item['attributes']=json_decode($item['attributes'],true,512,JSON_THROW_ON_ERROR);
                $result['item']=$item;$result['receipt']='DEMO-ESCROW-'.$key;
            } else {
                $item=$payload['item']??[];
                if ((int)($item['item_id']??0)<1 || (int)($item['quantity']??0)<1) throw new RuntimeException('Неверный демонстрационный предмет.');
                $name=(string)($item['name']??('DEMO Item #'.$item['item_id']));
                if (strpos($name,'DEMO')===false) $name.=' — DEMO';
                $game->prepare('INSERT INTO demo_inventory(char_id,item_id,name,quantity,enchant,kind,grade,augmented,attributes) VALUES(?,?,?,?,?,?,?,?,?)')->execute([(int)$payload['char_id'],(int)$item['item_id'],$name,(int)$item['quantity'],(int)($item['enchant']??0),$item['kind']??'OTHER',$item['grade']??'NG',empty($item['augmented'])?0:1,json_encode($item['attributes']??[],JSON_THROW_ON_ERROR)]);
                $result['state']='delivered';$result['receipt']='DEMO-DELIVERY-'.$key;
            }
            if ($op!=='inventory') $game->prepare('INSERT INTO demo_bridge_ops(operation_key,fingerprint,result) VALUES(?,?,?)')->execute([$key,$fingerprint,json_encode($result,JSON_THROW_ON_ERROR)]);
            $game->commit();return $result;
        } catch (Throwable $e) {if ($game->inTransaction()) $game->rollBack();throw $e;}
    }
    public static function topup($uid,$amount,$key)
    {
        if (!self::ready() || $amount<500 || $amount>100000) throw new RuntimeException('Демо-пополнение: от 5 до 1000 виртуальных $.');
        $store=PortalRuntime::store();
        return $store->run($uid,$key,'demo.topup',[$amount],function () use ($store,$uid,$amount,$key) {
            $invoice='DEMO-ONLY-'.$key;
            $store->query("INSERT INTO {{portal_payments}}(user_id,amount,invoice_id,state,created_at,updated_at) VALUES(?,?,?,'pending',?,?)",[$uid,$amount,$invoice,time(),time()]);
            $id=(int)db()->getPdoInstance()->lastInsertId();$store->creditVerifiedInvoice($id,$invoice,$amount);
            $store->audit($uid,'demo.topup',$id,['amount'=>$amount,'real_payment'=>false]);
            return ['id'=>$id,'message'=>'Демо-баланс пополнен. Реальная оплата не выполнялась.'];
        });
    }
    public static function flushDeliveries()
    {
        if (!self::ready()) return;
        $store=PortalRuntime::store();
        foreach ($store->query("SELECT id FROM {{portal_deliveries}} WHERE state='pending' ORDER BY id LIMIT 5")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            try {
                PortalDelivery::process($store,$id,function ($row) {
                    return self::handle('deliver',$row['operation_key'],['char_id'=>(int)$row['char_id'],'item'=>['item_id'=>(int)$row['game_item_id'],'name'=>$row['name'],'quantity'=>(int)$row['quantity'],'enchant'=>(int)$row['enchant'],'kind'=>$row['kind'],'grade'=>$row['grade'],'augmented'=>(bool)$row['augmented'],'attributes'=>json_decode($row['attributes'],true,512,JSON_THROW_ON_ERROR)]],$row['gs_id'],$row['user_id']);
                });
            } catch (Throwable $e) {Yii::log('Demo delivery retry: '.$id,CLogger::LEVEL_WARNING,'staging');}
        }
    }
    public static function install()
    {
        if (!defined('LA2PLUS_STAGING') || !LA2PLUS_STAGING || ($_SERVER['HTTP_HOST']??'')!=='new.la2plus.com' || strpos(db()->connectionString,'dbname=laplusco_newcms;')===false) throw new RuntimeException('Not a staging deployment');
        $game=self::game(1);
        $game->exec("CREATE TABLE IF NOT EXISTS demo_inventory(object_id BIGINT AUTO_INCREMENT PRIMARY KEY,char_id INT NOT NULL,item_id INT NOT NULL,name VARCHAR(255) NOT NULL,quantity BIGINT NOT NULL,enchant INT NOT NULL DEFAULT 0,kind VARCHAR(16) NOT NULL,grade VARCHAR(4) NOT NULL,augmented INT NOT NULL DEFAULT 0,attributes TEXT NOT NULL) ENGINE=InnoDB");
        $game->exec('CREATE TABLE IF NOT EXISTS demo_bridge_lock(id INT PRIMARY KEY) ENGINE=InnoDB');$game->exec('INSERT IGNORE INTO demo_bridge_lock(id) VALUES(1)');
        $game->exec('CREATE TABLE IF NOT EXISTS demo_bridge_ops(operation_key VARCHAR(128) PRIMARY KEY,fingerprint CHAR(64) NOT NULL,result MEDIUMTEXT NOT NULL) ENGINE=InnoDB');
        $store=PortalRuntime::store();
        $installed=$store->setting('staging_demo_inventory')==='1';
        if (!$installed) {
            foreach ([[101,7575,'Draconic Bow — DEMO',16,'WEAPON','S',1,['variationId1'=>123,'variationId2'=>456]],[102,6379,'Draconic Leather Armor — DEMO',7,'ARMOR','S',0,[]],[201,6656,'Earring of Antharas — DEMO',3,'JEWELRY','S',0,[]]] as $r) $game->prepare('INSERT INTO demo_inventory(char_id,item_id,name,quantity,enchant,kind,grade,augmented,attributes) VALUES(?,?,?,1,?,?,?,?,?)')->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],json_encode($r[7])]);
        }
        $store->atomic(function () use ($store) {
            foreach (['staging_demo_inventory'=>'1','warehouse_enabled'=>'1','market_enabled'=>'1','withdrawals_enabled'=>'1','col_item_id'=>'4037'] as $key=>$value) {
                $store->query('DELETE FROM {{portal_settings}} WHERE name=?',[$key]);$store->query('INSERT INTO {{portal_settings}}(name,value) VALUES(?,?)',[$key,$value]);
            }
            $store->audit(1,'demo.adapter.enabled','new.la2plus.com',['real_game'=>false,'real_payments'=>false]);
        });
        foreach (['online.txt.allow'=>'0','register.allow'=>'0'] as $key=>$value) db()->createCommand('UPDATE {{config}} SET value=:v,`default`=:d WHERE param=:p')->execute([':v'=>$value,':d'=>$value,':p'=>$key]);
        DbConfig::flushCache();return ['ok'=>true,'demo'=>true,'real_game'=>false,'real_payments'=>false];
    }
}
