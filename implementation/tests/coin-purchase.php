<?php
require __DIR__.'/../public_html/cms-private/protected/components/CoinPurchase.php';
$n=0;$check=function($condition)use(&$n){if(!$condition)throw new RuntimeException('Quote assertion '.($n+1).' failed');$n++;};
foreach([[5,0,5],[9,0,9],[10,0,10],[20,1,21],[199,9,208],[200,20,220],[300,45,345],[400,80,480],[1000,250,1250],[100000,25000,125000]] as [$coins,$bonus,$total]){$q=CoinPurchase::quote((string)$coins,'1');$check($q['bonus']===$bonus&&$q['total']===$total&&$q['cents']===$coins*100);}
$check(CoinPurchase::quote('3','0.3333')['usd']==='1.00');$check(CoinPurchase::quote('1000','0.025')['usd']==='25.00');
foreach(['0','-1','1.5','100001','1e3','01','',[],null] as $v){try{CoinPurchase::quote($v,'1');throw new LogicException('Invalid accepted');}catch(RuntimeException $e){$n++;}}
foreach(['0','-1','1e3','garbage','0.00001'] as $rate){try{CoinPurchase::quote('10',$rate);throw new LogicException('Invalid rate accepted');}catch(RuntimeException $e){$n++;}}
echo "PASS $n purchase quote assertions\n";
