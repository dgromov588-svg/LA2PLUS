<?php
class PortalRuntime
{
    private static $store;
    private static $sections;
    public static function sections()
    {
        if (!self::$sections) self::$sections=new CabinetSections(self::store());
        return self::$sections;
    }
    public static function store()
    {
        if (!self::$store) { db()->active=true; self::$store=new PortalStore(db()->getPdoInstance(),db()->tablePrefix); }
        return self::$store;
    }
    public static function enabled() { return self::store()->enabled('cabinet'); }
    public static function isDemo()
    {
        return defined('LA2PLUS_STAGING') && LA2PLUS_STAGING && class_exists('StagingGameAdapter') && StagingGameAdapter::ready();
    }
    public static function gameReady() { return self::isDemo() || self::store()->setting('bridge_verified')==='1'; }
    public static function uid() { return (int)user()->getId(); }
    public static function gs() { return (int)user()->getGsId(); }
    public static function requestKey() { return bin2hex(random_bytes(16)); }
    public static function pinField()
    {
        return self::enabled()?'<label>PIN операций сайта <input type="password" name="portal_pin" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="off" required></label>':'';
    }
    public static function translate($ru,$en,$uk)
    {
        return app()->language==='en'?$en:(in_array(app()->language,['uk','ua'],true)?$uk:$ru);
    }
    public static function discount($user)
    {
        try { return self::store()->discount($user); } catch (Exception $e) { return 0; }
    }
    public static function accounts()
    {
        return array_values(array_filter(AccountLinkService::getAccounts(self::uid()),function ($row) {
            return (int)$row['gs_id']===self::gs() && (int)$row['status']===1;
        }));
    }
    public static function account($linkId)
    {
        foreach (self::accounts() as $row) if ((int)$row['id']===(int)$linkId) return $row;
        throw new RuntimeException('Аккаунт не принадлежит вам или недоступен на выбранном сервере.');
    }
    public static function lastLogin()
    {
        try {
            $rows=db()->createCommand('SELECT created_at FROM {{users_auth_logs}} WHERE user_id=:u AND status=:s ORDER BY id DESC LIMIT 2')->queryColumn([':u'=>self::uid(),':s'=>UsersAuthLogs::STATUS_AUTH_SUCCESS]);
            return $rows[1]??$rows[0]??'—';
        } catch (Throwable $e) { return '—'; }
    }
    public static function characters($linkId)
    {
        $account=self::account($linkId);
        $l2=l2('gs',self::gs())->connect();
        $q=$l2->getDb()->createCommand()->where('account_name = :login',[':login'=>$account['game_login']]);
        return $l2->characters($q)->queryAll();
    }
    public static function character($linkId,$charId)
    {
        foreach (self::characters($linkId) as $char) if ((int)$char['char_id']===(int)$charId) return $char;
        throw new RuntimeException('Персонаж не принадлежит выбранному аккаунту.');
    }
    public static function verifyPassword($password)
    {
        // Verify the signed-in master profile, never a game login selected in a cookie.
        $u=db()->createCommand('SELECT login FROM {{users}} WHERE user_id=:u')->queryRow(true,[':u'=>self::uid()]);
        if (!$u) return false;
        $l2=l2('gs',self::gs())->connect();
        $row=$l2->getDb()->createCommand('SELECT password FROM accounts WHERE login=:l')->queryRow(true,[':l'=>$u['login']]);
        return $row && $l2->passwordVerify((string)$password,(string)$row['password']);
    }
    public static function bridge($operation,$key,array $payload)
    {
        return self::bridgeFor($operation,$key,$payload,self::gs(),self::uid());
    }
    public static function bridgeFor($operation,$key,array $payload,$gs,$uid)
    {
        if (self::isDemo()) return StagingGameAdapter::handle($operation,$key,$payload,$gs,$uid);
        $store=self::store();
        $url=$store->setting('bridge_url');
        $secret=getenv('LA2PLUS_GAME_BRIDGE_SECRET');
        if ($store->setting('bridge_verified')!=='1' || !$secret || !preg_match('#^https://[^\s]+$#D',$url)) throw new RuntimeException('Игровая доставка ещё не подключена. Средства и предметы не списаны.');
        $body=json_encode(['operation'=>$operation,'key'=>$key,'gs_id'=>(int)$gs,'user_id'=>(int)$uid,'payload'=>$payload],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $timestamp=(string)time();
        $ch=curl_init(rtrim($url,'/').'/operations');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-LA2PLUS-Time: '.$timestamp,'X-LA2PLUS-Signature: '.hash_hmac('sha256',$timestamp."\n".$body,$secret)]]);
        $raw=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        $result=is_string($raw)?json_decode($raw,true):null;
        if ($status!==200 || !is_array($result) || empty($result['ok']) || ($result['key']??'')!==$key) throw new RuntimeException('Игровой сервер не подтвердил операцию. Повторите проверку с тем же номером операции.');
        return $result;
    }
    public static function teleport($linkId,$charId,$key)
    {
        $char=self::character($linkId,$charId);
        if ((int)$char['online']!==0) throw new RuntimeException('Сначала выйдите из игры.');
        $gs=Gs::model()->findByPk(self::gs());
        if (!$gs || !(int)$gs->allow_teleport) throw new RuntimeException('Телепорт отключён на сервере.');
        $store=self::store();
        return $store->run(self::uid(),$key,'teleport',[$linkId,$charId],function () use ($store,$gs,$char,$charId) {
            $last=$store->query("SELECT MAX(created_at) FROM {{portal_audit}} WHERE action='character.teleport' AND target=?",[self::gs().':'.$charId])->fetchColumn();
            if ($last && time()-(int)$last<(int)$gs->teleport_time*60) throw new RuntimeException('Подождите перед повторным телепортом.');
            $city=Lineage::getRandomCity(); $coords=$city['coordinates'][0];
            $l2=l2('gs',self::gs())->connect(); $field=$l2->getField('characters.char_id');
            $updated=$l2->getDb()->createCommand()->update('characters',['x'=>$coords['x'],'y'=>$coords['y'],'z'=>$coords['z']],$field.'=:id AND account_name=:a AND online=0',[':id'=>$charId,':a'=>$char['account_name']]);
            if (!$updated) throw new RuntimeException('Персонаж вошёл в игру или уже находится в городе.');
            $store->audit(self::uid(),'character.teleport',self::gs().':'.$charId,['city'=>$city['name']]);
            return ['city'=>$city['name']];
        });
    }
}
