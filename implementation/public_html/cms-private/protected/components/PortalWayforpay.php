<?php
/** WayForPay hosted invoices. No card details enter the website. */
class PortalWayforpay
{
    private $account;private $secret;private $domain;private $transport;
    public function __construct($account,$secret,$domain,?callable $transport=null){$this->account=(string)$account;$this->secret=(string)$secret;$this->domain=(string)$domain;$this->transport=$transport;}
    public static function configured(){return new self(config('wayforpay.merchant_account'),config('wayforpay.secret_key'),config('wayforpay.merchant_domain'));}
    public function ready(){return !in_array($this->account,['','0'],true)&&!in_array($this->secret,['','0'],true)&&$this->domain!==''&&stripos($this->account,'test_')!==0;}
    private function sign($message){return hash_hmac('md5',$message,$this->secret);}
    private function request(array $payload)
    {
        if(!$this->ready())throw new RuntimeException('WayForPay не настроен.');
        if($this->transport)return ($this->transport)($payload);
        $c=curl_init('https://api.wayforpay.com/api');curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);$raw=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
        if($raw===false||$status!==200)throw new RuntimeException('WayForPay временно недоступен.');$data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($data))throw new RuntimeException('Не удалось получить ответ WayForPay.');return $data;
    }
    public function create($reference,$cents,$email='')
    {
        $now=time();$amount=number_format($cents/100,2,'.','');$name='LA2PLUS Coin of Luck';
        $payload=['transactionType'=>'CREATE_INVOICE','apiVersion'=>1,'merchantAccount'=>$this->account,'merchantDomainName'=>$this->domain,'merchantTransactionSecureType'=>'AUTO','orderReference'=>$reference,'orderDate'=>$now,'amount'=>$amount,'currency'=>'USD','productName'=>[$name],'productCount'=>[1],'productPrice'=>[$amount],'language'=>'RU','returnUrl'=>'https://la2plus.com/deposit/wayforpay-return?portal=1','serviceUrl'=>'https://la2plus.com/deposit-wayforpay.php'];
        if(filter_var($email,FILTER_VALIDATE_EMAIL))$payload['clientEmail']=$email;
        $payload['merchantSignature']=$this->sign(implode(';',[$this->account,$this->domain,$reference,$now,$amount,'USD',$name,1,$amount]));$result=$this->request($payload);$url=(string)($result['invoiceUrl']??'');
        if((int)($result['reasonCode']??0)!==1100||parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_HOST)!=='secure.wayforpay.com')throw new RuntimeException('WayForPay не подтвердил создание счёта.');return ['id'=>$reference,'url'=>$url];
    }
    public function status($reference){return $this->request(['transactionType'=>'CHECK_STATUS','apiVersion'=>1,'merchantAccount'=>$this->account,'orderReference'=>$reference,'merchantSignature'=>$this->sign($this->account.';'.$reference)]);}
    public function verify(array $data)
    {
        if(($data['merchantAccount']??'')!==$this->account)return false;$fields=[];foreach(['merchantAccount','orderReference','amount','currency','authCode','cardPan','transactionStatus','reasonCode'] as $key){if(isset($data[$key])&&!is_scalar($data[$key]))return false;$fields[]=$data[$key]??'';}
        return isset($data['merchantSignature'])&&is_string($data['merchantSignature'])&&hash_equals($this->sign(implode(';',$fields)),$data['merchantSignature']);
    }
    public function settled(array $data,$reference,$cents)
    {
        return $this->verify($data)&&($data['orderReference']??'')===$reference&&($data['transactionStatus']??'')==='Approved'&&(int)($data['reasonCode']??0)===1100&&($data['currency']??'')==='USD'&&is_numeric($data['amount']??null)&&PortalMoney::cents(number_format((float)$data['amount'],2,'.',''))===$cents;
    }
    public function ack($reference){$now=time();return ['orderReference'=>$reference,'status'=>'accept','time'=>$now,'signature'=>$this->sign($reference.';accept;'.$now)];}
}
