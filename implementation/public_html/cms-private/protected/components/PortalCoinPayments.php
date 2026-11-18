<?php
/** Independent CoL orders; never interpreted as exchange dollars or legacy donations. */
class PortalCoinPayments
{
    public static function ready($provider='trybit')
    {
        $store=PortalRuntime::store();
        if($provider==='nowpayments'){Yii::import('application.modules.deposit.extensions.Deposit.libs.*');return !PortalRuntime::isDemo()&&$store->setting('coin_nowpayments_enabled')==='1'&&(new Nowpayments())->isEnabled()&&strtoupper((new Nowpayments())->getCurrency())==='USD';}
        if($provider==='wayforpay')return !PortalRuntime::isDemo()&&$store->setting('coin_wayforpay_verified')==='1'&&(int)config('wayforpay.allow')===1&&(int)config('wayforpay.test')!==1&&strtoupper((string)config('wayforpay.currency'))==='USD'&&PortalWayforpay::configured()->ready();
        if($provider!=='trybit')return false;
        return (int)config('trybit.allow')===1&&!PortalRuntime::isDemo()&&$store->setting('coin_topup_verified')==='1'&&$store->setting('coin_topup_enabled')==='1'&&(int)config('trybit.test')!==1&&PortalTrybit::configured()->ready();
    }
    public static function create($uid,$gs,array $quote,$key,$email='',$provider='trybit')
    {
        if(!self::ready($provider))throw new RuntimeException('Приём платежей ещё не включён.');
        $store=PortalRuntime::store();
        if($provider==='nowpayments'&&$quote['cents']<500)throw new RuntimeException('Минимум NOWPayments: 5 USD.');
        $result=$store->run($uid,$key,'coins.order',[$gs,$quote,$provider],function()use($store,$uid,$gs,$quote,$provider){
            $store->query("INSERT INTO {{portal_coin_orders}}(user_id,gs_id,amount,coins,bonus,total,provider,state,created_at,updated_at) VALUES(?,?,?,?,?,?,?,'creating',?,?)",[$uid,$gs,$quote['cents'],$quote['coins'],$quote['bonus'],$quote['total'],$provider,time(),time()]);
            return ['id'=>(int)db()->getPdoInstance()->lastInsertId()];
        });
        $id=$result['id'];$row=$store->query('SELECT * FROM {{portal_coin_orders}} WHERE id=? AND user_id=?',[$id,$uid])->fetch(PDO::FETCH_ASSOC);
        if(in_array($row['state'],['pending','paid'],true))return ['id'=>$id,'state'=>$row['state'],'url'=>$row['payment_url']];
        if(!$store->query("UPDATE {{portal_coin_orders}} SET state='sending',updated_at=? WHERE id=? AND state='creating'",[time(),$id])->rowCount())throw new RuntimeException('Заказ уже обрабатывается. Не создавайте повторный платёж; проверьте историю.');
        try{
            if($provider==='trybit')$invoice=PortalTrybit::configured()->create('col:'.$id,(int)$row['amount'],$email);
            elseif($provider==='wayforpay')$invoice=PortalWayforpay::configured()->create('LA2COL-'.$id,(int)$row['amount'],$email);
            else { $gateway=new Nowpayments();$raw=$gateway->createInvoice('col:'.$id,$row['amount']/100,'LA2PLUS Coin of Luck');$invoice=['id'=>(string)$raw['invoice_id'],'url'=>(string)$raw['link']];if(parse_url($invoice['url'],PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($invoice['url'],PHP_URL_HOST))!=='nowpayments.io')throw new RuntimeException('Некорректная ссылка NOWPayments.'); }
            $store->query("UPDATE {{portal_coin_orders}} SET invoice_id=?,payment_url=?,state='pending',updated_at=? WHERE id=? AND state='sending'",[$invoice['id'],$invoice['url'],time(),$id]);
            return ['id'=>$id,'url'=>$invoice['url'],'state'=>'pending'];
        }catch(Throwable $e){$store->query("UPDATE {{portal_coin_orders}} SET state='unknown',updated_at=? WHERE id=? AND state='sending'",[time(),$id]);throw new RuntimeException('Заказ №'.$id.' сохранён, создание счёта требует проверки. Не оплачивайте повторно.');}
    }
    public static function sync($uid,$id)
    {
        $store=PortalRuntime::store();$row=$store->query('SELECT * FROM {{portal_coin_orders}} WHERE id=? AND user_id=?',[$id,$uid])->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Заказ не найден.');
        if($row['state']==='pending'){
            if($row['provider']==='trybit'){$invoice=PortalTrybit::configured()->invoice($row['invoice_id']);$settled=PortalTrybit::settled($invoice,'col:'.$id,$row['invoice_id'],(int)$row['amount']);}
            elseif($row['provider']==='wayforpay'){$gateway=PortalWayforpay::configured();$invoice=$gateway->status('LA2COL-'.$id);$settled=$gateway->settled($invoice,'LA2COL-'.$id,(int)$row['amount']);}
            else {Yii::import('application.modules.deposit.extensions.Deposit.libs.*');$invoice=$row['payment_id']?(new Nowpayments())->getPayment($row['payment_id']):null;$settled=is_array($invoice)&&self::nowSettled($invoice,$row);}
            if($settled){
                $transaction=db()->beginTransaction();
                try{
                    self::credit($store,$row,function($coins,$ref)use($uid){WalletService::credit($uid,$coins,['order_id'=>$ref,'comment'=>'Пополнение Coin of Luck']);});
                    $transaction->commit();
                }catch(Throwable $e){$transaction->rollback();throw $e;}
                $row['state']='paid';
            }
        }
        return ['id'=>(int)$id,'state'=>$row['state'],'total'=>(int)$row['total']];
    }
    public static function requireNoReview($uid)
    {
        $ids=[(int)$uid];foreach(AccountLinkService::getAccounts($uid) as $account)$ids[]=(int)$account['user_id'];$ids=array_values(array_unique($ids));
        if(PortalRuntime::store()->query("SELECT id FROM {{portal_coin_orders}} WHERE user_id IN (".implode(',',array_fill(0,count($ids),'?')).") AND state='review' LIMIT 1",$ids)->fetchColumn())throw new RuntimeException('Требуется проверка возвращённого платежа. Обратитесь в поддержку.');
    }
    public static function wayforpayWebhook(array $data)
    {
        $gateway=PortalWayforpay::configured();if(!$gateway->verify($data))throw new CHttpException(403,'Invalid signature');
        if(!preg_match('/^LA2COL-([1-9][0-9]*)$/D',(string)($data['orderReference']??''),$match))throw new CHttpException(400,'Invalid reference');
        $store=PortalRuntime::store();$row=$store->query("SELECT * FROM {{portal_coin_orders}} WHERE id=? AND provider='wayforpay'",[$match[1]])->fetch(PDO::FETCH_ASSOC);if(!$row)throw new CHttpException(404,'Order not found');
        $status=$gateway->status($data['orderReference']);
        if($gateway->verify($status)&&($status['orderReference']??'')===$data['orderReference']&&in_array($status['transactionStatus']??'',['Refunded','Voided'],true)&&$row['state']==='paid'){
            $store->run($row['user_id'],'card-return-review-'.$row['id'],'coins.return.review',[$row['id']],function()use($store,$row){$store->query("UPDATE {{portal_coin_orders}} SET state='review',updated_at=? WHERE id=? AND state='paid'",[time(),$row['id']]);$store->audit(0,'coins.return.review',$row['id']);return ['review'=>true];});
        }else self::sync($row['user_id'],$row['id']);
        return $gateway->ack($data['orderReference']);
    }
    public static function nowSettled(array $payment,array $order)
    {
        return ($payment['payment_status']??'')==='finished'
            && (string)($payment['order_id']??'')==='col:'.$order['id']
            && (string)($payment['invoice_id']??'')===(string)$order['invoice_id']
            && (string)($payment['payment_id']??'')===(string)$order['payment_id']
            && strtolower((string)($payment['price_currency']??''))==='usd'
            && is_numeric($payment['price_amount']??null)
            && PortalMoney::cents(number_format((float)$payment['price_amount'],2,'.',''))===(int)$order['amount'];
    }
    public static function nowWebhook(array $data,$raw,$signature)
    {
        Yii::import('application.modules.deposit.extensions.Deposit.libs.*');$gateway=new Nowpayments();
        if(!$gateway->verifyIpnSignature($raw,$signature))throw new CHttpException(403,'Invalid signature');
        if(!preg_match('/^col:([1-9][0-9]*)$/D',(string)($data['order_id']??''),$match))throw new CHttpException(400,'Invalid order');
        $store=PortalRuntime::store();$row=$store->query("SELECT * FROM {{portal_coin_orders}} WHERE id=? AND provider='nowpayments'",[$match[1]])->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new CHttpException(404,'Order not found');
        $id=(string)($data['payment_id']??'');if(!preg_match('/^[0-9]{1,32}$/D',$id))throw new CHttpException(400,'Invalid payment');
        $payment=$gateway->getPayment($id);
        if(!is_array($payment)||(string)($payment['order_id']??'')!=='col:'.$row['id']||(string)($payment['invoice_id']??'')!==(string)$row['invoice_id'])throw new CHttpException(400,'Payment mismatch');
        $store->query('UPDATE {{portal_coin_orders}} SET payment_id=?,updated_at=? WHERE id=? AND (payment_id IS NULL OR payment_id=?)',[$id,time(),$row['id'],$id]);
        return self::sync($row['user_id'],$row['id']);
    }
    public static function credit(PortalStore $store,array $row,callable $credit)
    {
        return $store->run((int)$row['user_id'],'col-settlement-'.$row['id'],'coins.settlement',[(int)$row['id'],$row['invoice_id'],(int)$row['amount'],(int)$row['total']],function()use($store,$row,$credit){
            $fresh=$store->query('SELECT state FROM {{portal_coin_orders}} WHERE id=?',[$row['id']])->fetchColumn();
            if($fresh==='paid')return ['already'=>true];
            if($fresh!=='pending')throw new RuntimeException('Заказ не ожидает оплаты.');
            $credit((int)$row['total'],'col:'.$row['id']);
            $store->query("UPDATE {{portal_coin_orders}} SET state='paid',updated_at=? WHERE id=?",[time(),$row['id']]);
            $store->audit($row['user_id'],'coins.paid',$row['id'],['invoice'=>$row['invoice_id'],'amount'=>(int)$row['amount'],'coins'=>(int)$row['total']]);return ['already'=>false];
        });
    }
}
