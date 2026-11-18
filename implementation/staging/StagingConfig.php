<?php
/** Keep external services disabled even if somebody edits demonstration settings. */
class StagingConfig extends DbConfig
{
    public static function registrationPreview()
    {
        return PHP_SAPI!=='cli' && function_exists('request') && request()->getRequestType()==='GET'
            && preg_match('#^register(?:/?$|/default/(?:index/?$|captcha(?:/|$)))#i',request()->getPathInfo());
    }
    public function get($key,$default=null)
    {
        if ($key==='utopay.rate') return '1';
        if ($key==='mail.admin_email') return 'no-reply@example.test';
        if ($key==='mail.admin_name') return 'LA2PLUS TEST';
        if (preg_match('/^(utopay|trybit|wayforpay|payop|paypalych|nowpayments|stripe|paypal|coinbase|mail)\./i',(string)$key)) return '0';
        if ($key==='register.allow') return self::registrationPreview()?'1':'0';
        if ($key==='online.txt.allow') return '0';
        return parent::get($key,$default);
    }
}
