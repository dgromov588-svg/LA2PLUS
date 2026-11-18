<?php
/** Authoritative integer quote. Rate is USD per base Coin of Luck. */
class CoinPurchase
{
    public static function quote($coins,$rate)
    {
        if (!is_scalar($coins) || !preg_match('/^[1-9][0-9]{0,5}$/D',(string)$coins) || (int)$coins>100000) throw new RuntimeException('Количество CoL: целое число от 1 до 100000.');
        if (!preg_match('/^([0-9]{1,5})(?:\.([0-9]{1,4}))?$/D',(string)$rate,$m)) throw new RuntimeException('Курс временно недоступен.');
        $units=(int)$m[1]*10000+(int)str_pad($m[2]??'',4,'0');
        if ($units<1) throw new RuntimeException('Курс временно недоступен.');
        $coins=(int)$coins;$percent=$coins>=1000?25:($coins>=400?20:($coins>=300?15:($coins>=200?10:($coins>=10?5:0))));
        $bonus=intdiv($coins*$percent,100);$cents=intdiv($coins*$units+99,100);
        return ['coins'=>$coins,'bonus'=>$bonus,'percent'=>$percent,'total'=>$coins+$bonus,'cents'=>$cents,'usd'=>number_format($cents/100,2,'.','')];
    }
}
