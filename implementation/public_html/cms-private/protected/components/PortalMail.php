<?php
/** Reuse configured SMTP and the site's email layout. Never expose code in HTTP responses. */
class PortalMail extends Notify
{
    public function sendCode($email,$code)
    {
        return $this->send($email,'LA2PLUS — подтверждение операции',
            '<p>Код подтверждения: <strong>'.CHtml::encode($code).'</strong></p><p>Действует 10 минут. Если вы не запрашивали код, не передавайте его никому.</p>');
    }
}
