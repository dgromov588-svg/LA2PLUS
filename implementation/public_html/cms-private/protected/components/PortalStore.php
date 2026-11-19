<?php
/** Transactional dollar ledger and item custody. No game database writes here. */
class PortalStore
{
    private $db;
    private $prefix;
    private $lock;
    public function __construct(PDO $db, $prefix = '')
    {
        if (!preg_match('/^[a-zA-Z0-9_]*$/D', $prefix)) throw new InvalidArgumentException('Invalid prefix');
        $this->db = $db; $this->prefix = $prefix;
        $this->lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function driver(){return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);}
    public function query($sql, array $params = [])
    {
        $sql = preg_replace_callback('/\{\{([a-z_]+)\}\}/', function ($m) { return $this->prefix . $m[1]; }, $sql);
        $q = $this->db->prepare($sql); $q->execute($params); return $q;
    }
    public function setting($name, $default = '')
    {
        try { $v = $this->query('SELECT value FROM {{portal_settings}} WHERE name=?', [$name])->fetchColumn(); return $v === false ? $default : $v; }
        catch (PDOException $e) { return $default; }
    }
    public function enabled($name) { return $this->setting($name . '_enabled') === '1'; }
    public function requireEnabled($name)
    {
        if (!$this->enabled($name)) throw new RuntimeException('Этот раздел пока недоступен.');
    }
    public function atomic(callable $callback)
    {
        if ($this->db->inTransaction()) return $callback();
        $this->db->beginTransaction();
        try { $result = $callback(); $this->db->commit(); return $result; }
        catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    public function wallet($user, $lock = false)
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insert = $driver === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $this->query("$insert INTO {{portal_wallets}}(user_id,available,reserved) VALUES(?,0,0)", [(int) $user]);
        return $this->query('SELECT * FROM {{portal_wallets}} WHERE user_id=?' . ($lock ? $this->lock : ''), [(int) $user])->fetch(PDO::FETCH_ASSOC);
    }
    private function move($user, $available, $reserved, $operation, $reference)
    {
        $w = $this->wallet($user, true);
        if ((int) $w['available'] + $available < 0 || (int) $w['reserved'] + $reserved < 0) throw new RuntimeException('Недостаточно доступных средств.');
        $this->query('INSERT INTO {{portal_ledger}}(user_id,available_delta,reserved_delta,operation,reference,created_at) VALUES(?,?,?,?,?,?)', [$user,$available,$reserved,$operation,$reference,time()]);
        $this->query('UPDATE {{portal_wallets}} SET available=available+?,reserved=reserved+? WHERE user_id=?', [$available,$reserved,$user]);
    }
    public function audit($actor, $action, $target, array $details = [])
    {
        $this->query('INSERT INTO {{portal_audit}}(actor_id,action,target,details,created_at) VALUES(?,?,?,?,?)', [$actor,$action,(string) $target,json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),time()]);
    }
    public function run($user, $key, $operation, array $input, callable $callback)
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{16,80}$/D', $key)) throw new InvalidArgumentException('Обновите форму и повторите запрос.');
        $fingerprint = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        return $this->atomic(function () use ($user,$key,$operation,$fingerprint,$callback) {
            $this->wallet($user, true);
            $prior = $this->query('SELECT * FROM {{portal_requests}} WHERE request_key=?', [$key])->fetch(PDO::FETCH_ASSOC);
            if ($prior) {
                if ((int) $prior['user_id'] !== (int) $user || $prior['operation'] !== $operation || !hash_equals($prior['fingerprint'], $fingerprint)) throw new RuntimeException('Ключ операции уже использован.');
                return json_decode($prior['result'], true, 512, JSON_THROW_ON_ERROR);
            }
            $result = $callback();
            $this->query('INSERT INTO {{portal_requests}}(user_id,request_key,operation,fingerprint,result,created_at) VALUES(?,?,?,?,?,?)', [$user,$key,$operation,$fingerprint,json_encode($result, JSON_THROW_ON_ERROR),time()]);
            return $result;
        });
    }
    public function creditVerifiedInvoice($paymentId, $invoiceId, $expectedAmount)
    {
        // Caller MUST independently verify invoice ownership, amount and real settlement with Trybit.
        return $this->atomic(function () use ($paymentId,$invoiceId,$expectedAmount) {
            $p = $this->query('SELECT * FROM {{portal_payments}} WHERE id=?' . $this->lock, [$paymentId])->fetch(PDO::FETCH_ASSOC);
            if (!$p || !hash_equals((string) $p['invoice_id'], (string) $invoiceId) || (int) $p['amount'] !== (int) $expectedAmount) throw new RuntimeException('Платёж не соответствует заказу.');
            if ($p['state'] === 'paid') return ['already' => true];
            if ($p['state'] !== 'pending') throw new RuntimeException('Платёж недоступен для зачисления.');
            $this->move($p['user_id'], (int) $p['amount'], 0, 'topup', 'invoice:' . $invoiceId);
            $this->query("UPDATE {{portal_payments}} SET state='paid',updated_at=? WHERE id=?", [time(),$paymentId]);
            return ['already' => false];
        });
    }
    public function createPayment($user, $amount, $key)
    {
        $this->requireEnabled('topup');
        if ($amount < 500 || $amount > 100000) throw new InvalidArgumentException('Пополнение: от 5 до 1000 $.');
        return $this->run($user,$key,'topup',[$amount],function () use ($user,$amount) {
            $this->query("INSERT INTO {{portal_payments}}(user_id,amount,state,created_at,updated_at) VALUES(?,?,'creating',?,?)",[$user,$amount,time(),time()]);
            return ['id'=>(int)$this->db->lastInsertId()];
        });
    }
    public function setPaymentInvoice($id, $user, $invoice, $url)
    {
        if (!preg_match('#^https://#i', $url)) throw new RuntimeException('Платёжный сервис вернул неверную ссылку.');
        $this->query("UPDATE {{portal_payments}} SET invoice_id=?,payment_url=?,state='pending',updated_at=? WHERE id=? AND user_id=? AND state='sending'",[$invoice,$url,time(),$id,$user]);
    }
    public function withdraw($user, $amount, $address, $key)
    {
        $this->requireEnabled('withdrawals');
        if (!PortalMoney::validTronAddress($address)) throw new InvalidArgumentException('Укажите корректный USDT TRC20 адрес.');
        $fees = PortalMoney::withdrawal($amount);
        return $this->run($user,$key,'withdraw',[$amount,$address],function () use ($user,$address,$fees) {
            $active = $this->query("SELECT id FROM {{portal_withdrawals}} WHERE user_id=? AND state IN ('pending','processing')",[$user])->fetchColumn();
            if ($active) throw new RuntimeException('У вас уже есть активная заявка на вывод.');
            $this->query("INSERT INTO {{portal_withdrawals}}(user_id,amount,fee,net,address,state,created_at,updated_at) VALUES(?,?,?,?,?,'pending',?,?)",[$user,$fees['amount'],$fees['fee'],$fees['net'],$address,time(),time()]);
            $id = (int)$this->db->lastInsertId();
            $this->move($user,-$fees['amount'],$fees['amount'],'withdraw.reserve','withdraw:'.$id.':reserve');
            $this->audit($user,'withdraw.request',$id,$fees);
            return array_merge(['id'=>$id],$fees);
        });
    }
    public function processWithdrawal($actor, $id, $action, $txid = '')
    {
        if (!in_array($action,['processing','paid','reject'],true)) throw new InvalidArgumentException('Неверное действие.');
        if ($action === 'paid' && !preg_match('/^[a-fA-F0-9]{64}$/D',$txid)) throw new InvalidArgumentException('Укажите TXID выплаты TRC20.');
        return $this->atomic(function () use ($actor,$id,$action,$txid) {
            $row=$this->query('SELECT * FROM {{portal_withdrawals}} WHERE id=?'.$this->lock,[$id])->fetch(PDO::FETCH_ASSOC);
            if (!$row || !in_array($row['state'],['pending','processing'],true)) throw new RuntimeException('Заявка уже обработана или не найдена.');
            if ($action==='processing' && $row['state']!=='pending') throw new RuntimeException('Заявка уже в обработке.');
            if ($action==='paid' && $row['state']!=='processing') throw new RuntimeException('Сначала возьмите заявку в обработку.');
            if ($action==='paid') $this->move($row['user_id'],0,-(int)$row['amount'],'withdraw.paid','withdraw:'.$id.':finish');
            if ($action==='reject') $this->move($row['user_id'],(int)$row['amount'],-(int)$row['amount'],'withdraw.reject','withdraw:'.$id.':finish');
            $state=$action==='reject'?'rejected':$action;
            $this->query('UPDATE {{portal_withdrawals}} SET state=?,txid=?,actor_id=?,updated_at=? WHERE id=?',[$state,$action==='paid'?strtolower($txid):null,$actor,time(),$id]);
            $this->audit($actor,'withdraw.'.$state,$id,['txid'=>$txid]);
            return ['id'=>$id,'state'=>$state];
        });
    }
    public function transferToCabinet($user, $amount, $key, callable $creditCabinet, $rate)
    {
        if ($amount <= 0 || !preg_match('/^[0-9]+(?:\.[0-9]{1,4})?$/D',(string)$rate) || (float)$rate <= 0) throw new InvalidArgumentException('Неверная сумма или курс.');
        // Rate is USD per CoL, matching the existing payment gateway rate.
        $rateUnits=(int)round((float)$rate*10000);
        $coinHundredths=intdiv($amount*10000,$rateUnits);
        if (!$coinHundredths) throw new RuntimeException('Сумма меньше минимальной единицы CoL.');
        return $this->run($user,$key,'cabinet.transfer',[$amount,(string)$rate],function () use ($user,$amount,$key,$creditCabinet,$coinHundredths) {
            $this->move($user,-$amount,0,'cabinet.transfer','cabinet:'.$key);
            $creditCabinet($coinHundredths,$key);
            return ['spent'=>$amount,'coin_hundredths'=>$coinHundredths];
        });
    }
    public function grantItem($user, $gs, array $item, $sourceKey)
    {
        foreach (['item_id','quantity'] as $k) if (empty($item[$k]) || (int)$item[$k] < 1) throw new InvalidArgumentException('Неверный предмет.');
        $prior=$this->query('SELECT * FROM {{portal_items}} WHERE source_key=?',[$sourceKey])->fetch(PDO::FETCH_ASSOC);
        if ($prior) {
            if ((int)$prior['gs_id']!==(int)$gs || (int)$prior['item_id']!==(int)$item['item_id'] || (int)$prior['quantity']!==(int)$item['quantity']) throw new RuntimeException('Источник предмета уже использован для другой операции.');
            // Ownership can change after a sale; never hand the original caller a new owner's item.
            if ((int)$prior['user_id']!==(int)$user) throw new RuntimeException('Предмет уже передан другому владельцу.');
            return (int)$prior['id'];
        }
        $this->query("INSERT INTO {{portal_items}}(user_id,gs_id,source_key,item_id,name,quantity,enchant,kind,grade,augmented,attributes,state,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,'available',?)",[$user,$gs,$sourceKey,$item['item_id'],$item['name'],$item['quantity'],$item['enchant']??0,$item['kind']??'OTHER',$item['grade']??'NG',empty($item['augmented'])?0:1,json_encode($item['attributes']??[],JSON_THROW_ON_ERROR),time()]);
        return (int)$this->db->lastInsertId();
    }
    public function sell($user, $itemId, $price, $key)
    {
        $this->requireEnabled('market');
        if ($price < 1 || $price > 100000000) throw new InvalidArgumentException('Цена: от 0.01 до 1000000 $.');
        return $this->run($user,$key,'lot.sell',[$itemId,$price],function () use ($user,$itemId,$price) {
            $item=$this->query('SELECT * FROM {{portal_items}} WHERE id=?'.$this->lock,[$itemId])->fetch(PDO::FETCH_ASSOC);
            if (!$item || (int)$item['user_id']!== (int)$user || $item['state']!=='available' || !in_array($item['kind'],['WEAPON','ARMOR','JEWELRY'],true)) throw new RuntimeException('Предмет недоступен для продажи.');
            $fee=(int)$this->setting('sale_fee_percent','10');
            if ($fee<0 || $fee>100) throw new RuntimeException('Неверная комиссия биржи.');
            $this->query("INSERT INTO {{portal_lots}}(seller_id,item_id,gs_id,price,fee_percent,state,created_at) VALUES(?,?,?,?,?,'active',?)",[$user,$itemId,$item['gs_id'],$price,$fee,time()]);
            $id=(int)$this->db->lastInsertId();
            $this->query("UPDATE {{portal_items}} SET state='listed' WHERE id=?",[$itemId]);
            return ['id'=>$id];
        });
    }
    public function buy($buyer, $lotId, $gs, $key)
    {
        $this->requireEnabled('market');
        return $this->run($buyer,$key,'lot.buy',[$lotId,$gs],function () use ($buyer,$lotId,$gs,$key) {
            $lot=$this->query('SELECT * FROM {{portal_lots}} WHERE id=?'.$this->lock,[$lotId])->fetch(PDO::FETCH_ASSOC);
            if (!$lot || $lot['state']!=='active' || (int)$lot['gs_id']!==(int)$gs) throw new RuntimeException('Лот уже продан или недоступен.');
            if ((int)$lot['seller_id']===(int)$buyer) throw new RuntimeException('Нельзя купить собственный лот.');
            $price=(int)$lot['price']; $fee=PortalMoney::percent($price,(int)$lot['fee_percent']);
            $this->move($buyer,-$price,0,'lot.buy','buy:'.$key);
            $this->move($lot['seller_id'],$price-$fee,0,'lot.sell','sell:'.$key);
            $updated=$this->query("UPDATE {{portal_items}} SET user_id=?,state='available' WHERE id=? AND user_id=? AND gs_id=? AND state='listed'",[$buyer,$lot['item_id'],$lot['seller_id'],$gs]);
            if ($updated->rowCount()!==1) throw new RuntimeException('Предмет лота недоступен. Средства не списаны.');
            $this->query("UPDATE {{portal_lots}} SET state='sold',buyer_id=?,sold_at=? WHERE id=?",[$buyer,time(),$lotId]);
            $this->audit($buyer,'lot.buy',$lotId,['price'=>$price,'fee'=>$fee]);
            return ['id'=>$lotId,'price'=>$price,'fee'=>$fee];
        });
    }
    public function cancelLot($user,$id,$key)
    {
        return $this->run($user,$key,'lot.cancel',[$id],function () use ($user,$id) {
            $lot=$this->query('SELECT * FROM {{portal_lots}} WHERE id=?'.$this->lock,[$id])->fetch(PDO::FETCH_ASSOC);
            if (!$lot || (int)$lot['seller_id']!==(int)$user || $lot['state']!=='active') throw new RuntimeException('Лот недоступен.');
            $this->query("UPDATE {{portal_lots}} SET state='cancelled' WHERE id=?",[$id]);
            $this->query("UPDATE {{portal_items}} SET state='available' WHERE id=?",[$lot['item_id']]);
            return ['id'=>$id];
        });
    }
    public function queueDelivery($user,$itemId,$gs,$charId,$key)
    {
        $this->requireEnabled('warehouse');
        return $this->run($user,$key,'item.deliver',[$itemId,$gs,$charId],function () use ($user,$itemId,$gs,$charId,$key) {
            $item=$this->query('SELECT * FROM {{portal_items}} WHERE id=?'.$this->lock,[$itemId])->fetch(PDO::FETCH_ASSOC);
            if (!$item || (int)$item['user_id']!==(int)$user || (int)$item['gs_id']!==(int)$gs || $item['state']!=='available') throw new RuntimeException('Предмет недоступен.');
            $this->query("INSERT INTO {{portal_deliveries}}(user_id,item_id,gs_id,char_id,operation_key,state,created_at,updated_at) VALUES(?,?,?,?,?,'pending',?,?)",[$user,$itemId,$gs,$charId,$key,time(),time()]);
            $id=(int)$this->db->lastInsertId();
            $this->query("UPDATE {{portal_items}} SET state='delivering' WHERE id=?",[$itemId]);
            return ['id'=>$id];
        });
    }
    public function discount($user)
    {
        $v=$this->query('SELECT percent FROM {{portal_discounts}} WHERE user_id=? AND expires_at>?',[$user,time()])->fetchColumn();
        return $v===false?0:(int)$v;
    }
    public function setDiscount($actor,$user,$percent,$expires)
    {
        if ($percent<0 || $percent>100 || $expires<=time()) throw new InvalidArgumentException('Скидка: 0–100%, срок должен быть в будущем.');
        return $this->atomic(function () use ($actor,$user,$percent,$expires) {
            $this->query('DELETE FROM {{portal_discounts}} WHERE user_id=?',[$user]);
            $this->query('INSERT INTO {{portal_discounts}}(user_id,percent,expires_at,actor_id,updated_at) VALUES(?,?,?,?,?)',[$user,$percent,$expires,$actor,time()]);
            $this->audit($actor,'discount.set',$user,['percent'=>$percent,'expires_at'=>$expires]);
        });
    }
}
