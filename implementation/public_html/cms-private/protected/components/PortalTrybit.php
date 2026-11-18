<?php
/** Dedicated USD client. Keys never appear in browser payloads or logs. */
class PortalTrybit
{
    private $key;private $shop;private $secret;private $transport;
    public function __construct($key,$shop,$secret,?callable $transport=null)
    {
        $this->key=trim((string)$key);$this->shop=trim((string)$shop);$this->secret=trim((string)$secret);$this->transport=$transport;
    }
    public static function configured()
    {
        return new self(getenv('LA2PLUS_TRYBIT_API_KEY')?:config('trybit.api_key'),getenv('LA2PLUS_TRYBIT_SHOP_ID')?:config('trybit.shop_id'),getenv('LA2PLUS_TRYBIT_SECRET_KEY')?:config('trybit.secret_key'));
    }
    public function ready(){return !in_array($this->key,['','0'],true)&&!in_array($this->shop,['','0'],true)&&!in_array($this->secret,['','0'],true);}
    private function request($path,array $payload)
    {
        if(!$this->ready())throw new RuntimeException('Trybit не настроен.');
        if($this->transport)return ($this->transport)($path,$payload);
        $curl=curl_init('https://api.trybit.com'.$path);
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Authorization: Token '.$this->key,'Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);
        $raw=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        if($raw===false||$status<200||$status>=300)throw new RuntimeException('Trybit временно недоступен.');
        $data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if(!is_array($data)||!isset($data['result']))throw new RuntimeException('Некорректный ответ Trybit.');
        return $data;
    }
    public function create($reference,$cents,$email='')
    {
        $payload=['shop_id'=>$this->shop,'amount'=>$cents/100,'currency'=>'USD','order_id'=>$reference];
        if(filter_var($email,FILTER_VALIDATE_EMAIL))$payload['email']=$email;
        $result=$this->request('/v2/invoice/create',$payload)['result'];
        $url=(string)($result['link']??'');$host=strtolower((string)parse_url($url,PHP_URL_HOST));
        if(parse_url($url,PHP_URL_SCHEME)!=='https'||!in_array($host,['pay.trybit.com','pay.cryptocloud.plus','pay.cryptocloud.com'],true)||empty($result['uuid']))throw new RuntimeException('Trybit не подтвердил ссылку на счёт.');
        if(isset($result['amount_usd'])&&PortalMoney::cents(number_format((float)$result['amount_usd'],2,'.',''))!==$cents)throw new RuntimeException('Сумма счёта не совпала с заказом.');
        return ['id'=>(string)$result['uuid'],'url'=>$url];
    }
    public function invoice($id)
    {
        $result=$this->request('/v2/invoice/merchant/info',['uuids'=>[$id]])['result'];
        return isset($result[0])?$result[0]:$result;
    }
    public function token($token)
    {
        $parts=explode('.',(string)$token);if(count($parts)!==3)return false;
        $decode=function($value){return json_decode(base64_decode(strtr($value,'-_','+/')),true);};
        $header=$decode($parts[0]);$payload=$decode($parts[1]);
        if(($header['alg']??'')!=='HS256'||!is_array($payload)||!isset($payload['exp'])||!is_numeric($payload['exp'])||(int)$payload['exp']<time()-60)return false;
        $sig=rtrim(strtr(base64_encode(hash_hmac('sha256',$parts[0].'.'.$parts[1],$this->secret,true)),'+/','-_'),'=');
        return $this->ready()&&hash_equals($sig,$parts[2]);
    }
    public static function settled(array $invoice,$reference,$id,$cents)
    {
        if(!empty($invoice['test_mode'])||($invoice['order_id']??'')!==$reference||($invoice['uuid']??'')!==$id)return false;
        if(isset($invoice['fiat_currency'])&&strtoupper((string)$invoice['fiat_currency'])!=='USD')return false;
        if(!is_array($invoice['tx_list']??null))return false;
        $expected=$invoice['amount_usd']??$invoice['amount_to_pay_usd']??null;
        if(!is_numeric($expected)||PortalMoney::cents(number_format((float)$expected,2,'.',''))!==$cents)return false;
        // Merchant/info reports finality at invoice level. tx_list can contain
        // transaction IDs rather than objects; do not invent per-tx status fields.
        if(($invoice['invoice_status']??'')!=='success'||!in_array($invoice['status']??'',['paid','overpaid'],true))return false;
        $received=$invoice['amount_paid_usd']??null;$required=$invoice['amount_to_pay_usd']??null;
        if(!is_numeric($received)||!is_numeric($required)||(float)$required<=0)return false;
        $hasTransaction=false;
        foreach($invoice['tx_list'] as $tx){
            if(is_string($tx)&&preg_match('/^[A-Za-z0-9+\/=:_-]{16,256}$/D',$tx))$hasTransaction=true;
            elseif(is_array($tx)){
                $hash=$tx['txid']??$tx['hash']??$tx['tx_hash']??$tx['id']??'';
                if(is_string($hash)&&preg_match('/^[A-Za-z0-9+\/=:_-]{16,256}$/D',$hash)){
                    if(isset($tx['status'])&&!in_array(strtolower((string)$tx['status']),['confirmed','completed','success','paid'],true))return false;
                    $hasTransaction=true;
                }
            }
        }
        return $hasTransaction&&PortalMoney::cents(number_format((float)$received,2,'.',''))>=PortalMoney::cents(number_format((float)$required,2,'.',''));

    }
}
