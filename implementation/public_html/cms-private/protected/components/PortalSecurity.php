<?php
class PortalSecurity
{
    private $store;
    private $emailHash;
    public function __construct(PortalStore $store,$email=null) {
        $this->store=$store;
        if ($email===null && function_exists('user')) $email=(string)user()->get('email');
        $this->emailHash=hash('sha256',strtolower(trim((string)$email)));
    }
    private function row($user)
    {
        $this->store->wallet($user,true);
        $row=$this->store->query('SELECT * FROM {{portal_security}} WHERE user_id=?',[$user])->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->store->query('INSERT INTO {{portal_security}}(user_id,failed_attempts,locked_until) VALUES(?,0,0)',[$user]);
            $row=['email_verified_at'=>null];
        }
        return $row;
    }
    public function isVerified($user)
    {
        return (bool)$this->store->query('SELECT email_verified_at FROM {{portal_security}} WHERE user_id=? AND email_hash=?',[$user,$this->emailHash])->fetchColumn();
    }
    public function issue($user,$purpose,callable $verifyPassword,callable $send)
    {
        if (!preg_match('/^(verify_email|password:[0-9]+|admin_password:[0-9]+)$/D',$purpose)) throw new InvalidArgumentException('Неверная операция.');
        $this->requirePassword($user,$verifyPassword);
        $code=(string)random_int(100000,999999);
        $id=$this->store->atomic(function () use ($user,$purpose,$code) {
            $this->row($user);
            $last=$this->store->query('SELECT MAX(created_at) FROM {{portal_codes}} WHERE user_id=?',[$user])->fetchColumn();
            if ($last && time()-(int)$last<60) throw new RuntimeException('Повторная отправка доступна через минуту.');
            $count=$this->store->query('SELECT COUNT(*) FROM {{portal_codes}} WHERE user_id=? AND created_at>?',[$user,time()-3600])->fetchColumn();
            if ((int)$count>=5) throw new RuntimeException('Лимит писем: 5 в час.');
            $this->store->query('UPDATE {{portal_codes}} SET consumed=1 WHERE user_id=? AND purpose=?',[$user,$purpose]);
            $this->store->query('INSERT INTO {{portal_codes}}(user_id,purpose,code_hash,email_hash,expires_at,created_at) VALUES(?,?,?,?,?,?)',[$user,$purpose,password_hash($code,PASSWORD_DEFAULT),$this->emailHash,time()+600,time()]);
            return true;
        });
        if (!$send($code)) {
            $this->store->query('UPDATE {{portal_codes}} SET consumed=1 WHERE user_id=? AND purpose=?',[$user,$purpose]);
            throw new RuntimeException('Не удалось отправить письмо. Код не активирован.');
        }
        return $id;
    }
    public function consume($user,$purpose,$code)
    {
        // Commit failed attempts before throwing, so errors cannot roll back the limit.
        $valid=$this->store->atomic(function () use ($user,$purpose,$code) {
            $this->row($user);
            $row=$this->store->query('SELECT * FROM {{portal_codes}} WHERE user_id=? AND purpose=? ORDER BY id DESC LIMIT 1',[$user,$purpose])->fetch(PDO::FETCH_ASSOC);
            if (!$row || !hash_equals($this->emailHash,$row['email_hash']) || $row['consumed'] || (int)$row['expires_at']<time() || (int)$row['attempts']>=5) return false;
            $ok=preg_match('/^[0-9]{6}$/D',(string)$code) && password_verify((string)$code,$row['code_hash']);
            $this->store->query('UPDATE {{portal_codes}} SET attempts=attempts+1,consumed=? WHERE id=?',[$ok?1:0,$row['id']]);
            return (bool)$ok;
        });
        if (!$valid) throw new RuntimeException('Код неверен, истёк или уже использован.');
    }
    public function verifyEmail($user,$code)
    {
        $this->consume($user,'verify_email',$code);
        $this->store->query('UPDATE {{portal_security}} SET email_verified_at=?,email_hash=? WHERE user_id=?',[time(),$this->emailHash,$user]);
    }
    public function requireVerifiedEmail($user)
    {
        if (!$this->isVerified($user)) throw new RuntimeException('Подтвердите адрес электронной почты в настройках безопасности.');
    }
    public function requirePassword($user,callable $verify)
    {
        $ok=$this->store->atomic(function () use ($user,$verify) {
            $this->row($user);
            $failed=(int)$this->store->query("SELECT COUNT(*) FROM {{portal_audit}} WHERE actor_id=? AND action='security.password_failed' AND created_at>?",[$user,time()-900])->fetchColumn();
            if ($failed>=5) return false;
            if ($verify()) return true;
            $this->store->audit($user,'security.password_failed',$user);
            return false;
        });
        if (!$ok) throw new RuntimeException('Текущий пароль неверен или превышен лимит попыток. Подождите 15 минут после 5 ошибок.');
    }
}
