<?php
/** Keep external services disabled even if somebody edits demonstration settings. */
class StagingConfig extends DbConfig
{
    public function get($key,$default=null)
    {
        if ($key==='utopay.rate') return '1';
        if ($key==='mail.admin_email') return 'no-reply@example.test';
        if ($key==='mail.admin_name') return 'LA2PLUS TEST';
        if (preg_match('/^(utopay|trybit|wayforpay|payop|paypalych|nowpayments|stripe|paypal|coinbase|mail)\./i',(string)$key)) return '0';
        if (in_array($key,['register.allow','online.txt.allow'],true)) return '0';
        return parent::get($key,$default);
    }
}
