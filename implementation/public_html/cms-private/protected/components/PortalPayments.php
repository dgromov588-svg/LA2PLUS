<?php
/** Exchange invoices are isolated from donation transactions and do not award bonuses. */
class PortalPayments
{
    private static function gateway()
    {
        Yii::import('application.modules.deposit.extensions.Deposit.libs.*');
        $gateway=new Trybit();
        if (!$gateway->isEnabled() || $gateway->getCurrency()!=='USD' || (int)config('trybit.test')===1) throw new RuntimeException('Боевой приём USD через Trybit не подключён.');
        return $gateway;
    }
    public static function create($user,$amount,$key)
    {
        $store=PortalRuntime::store();
        if ($store->setting('net_topup_verified')!=='1') throw new RuntimeException('Пополнение биржи ещё проходит проверку комиссий провайдера.');
        $gateway=self::gateway();
        $result=$store->createPayment($user,$amount,$key);
        $id=$result['id'];
        $row=$store->query('SELECT * FROM {{portal_payments}} WHERE id=? AND user_id=?',[$id,$user])->fetch(PDO::FETCH_ASSOC);
        if ($row['state']==='pending' || $row['state']==='paid') return ['id'=>$id,'url'=>$row['payment_url'],'state'=>$row['state']];
        $claimed=$store->query("UPDATE {{portal_payments}} SET state='sending',updated_at=? WHERE id=? AND state='creating'",[time(),$id])->rowCount();
        if (!$claimed) throw new RuntimeException('Создание этого счёта уже выполнялось. Проверьте его в истории или обратитесь в поддержку.');
        try {
            $invoice=$gateway->createInvoice('exchange:'.$id,$amount/100,'LA2PLUS: пополнение кошелька биржи',(string)user()->get('email'));
            if (PortalMoney::cents(number_format((float)$invoice['amount'],2,'.',''))!==$amount) throw new RuntimeException('Сумма счёта не совпала с заказом.');
            $store->setPaymentInvoice($id,$user,$invoice['invoice_id'],$invoice['link']);
            return ['id'=>$id,'url'=>$invoice['link'],'state'=>'pending'];
        } catch (Throwable $e) {
            $store->query("UPDATE {{portal_payments}} SET state='unknown',updated_at=? WHERE id=?",[time(),$id]);
            throw new RuntimeException('Не удалось подтвердить создание счёта. Заказ №'.$id.' сохранён для проверки поддержкой; повторно не оплачивайте его.');
        }
    }
    public static function settled(array $invoice,$id,$invoiceId,$amount)
    {
        if (!empty($invoice['test_mode']) || (string)($invoice['uuid']??'')!==(string)$invoiceId || (string)($invoice['order_id']??'')!=='exchange:'.$id) return false;
        if (isset($invoice['currency']) && strtoupper((string)$invoice['currency'])!=='USD') return false;
        if (empty($invoice['tx_list']) || !is_array($invoice['tx_list'])) return false;
        // Require explicit USD amounts; never interpret BTC/ETH units as dollars.
        $expected=$invoice['amount_to_pay_usd']??$invoice['amount_usd']??null;
        if ($expected===null || PortalMoney::cents(number_format((float)$expected,2,'.',''))!==$amount) return false;
        $paid=0;
        foreach ($invoice['tx_list'] as $tx) {
            if (!is_array($tx) || !isset($tx['amount_usd'])) continue;
            if (isset($tx['status']) && !in_array(strtolower((string)$tx['status']),['confirmed','completed','success','paid'],true)) continue;
            $paid+=PortalMoney::cents(number_format((float)$tx['amount_usd'],2,'.',''));
        }
        return $paid >= $amount;
    }
    public static function sync($user,$id)
    {
        $store=PortalRuntime::store();
        $p=$store->query('SELECT * FROM {{portal_payments}} WHERE id=? AND user_id=?',[$id,$user])->fetch(PDO::FETCH_ASSOC);
        if (!$p) throw new RuntimeException('Заказ не найден.');
        if ($p['state']==='pending') {
            $invoice=self::gateway()->fetchInvoice($p['invoice_id']);
            if ($invoice && self::settled($invoice,$id,$p['invoice_id'],(int)$p['amount'])) {
                $store->creditVerifiedInvoice($id,$p['invoice_id'],(int)$p['amount']);
                $p['state']='paid';
            }
        }
        return ['id'=>$id,'state'=>$p['state']];
    }
}
