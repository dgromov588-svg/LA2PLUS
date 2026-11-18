<?php

class FrontendBaseController extends Controller
{
    /**
     * Журнал посещений: пишем заход один раз, перед первой загрузкой страницы
     * кабинета или сайта (init() базового контроллера общий для всех).
     */
    protected function trackVisitOnce()
    {
        static $done = false;

        if ($done) {
            return;
        }

        $done = true;

        try {
            AnalyticsService::trackRequest();
        } catch (Exception $e) {
            Yii::log('analytics: ' . $e->getMessage(), CLogger::LEVEL_WARNING, __METHOD__);
        }
    }


    // До загрузки
    public function beforeAction($action)
    {
        $this->trackVisitOnce();

        (new \app\services\ThemeService())->setTheme(config('theme'));

        if (class_exists('PortalRuntime') && PortalRuntime::enabled() && $this->getId()==='default' && in_array(strtolower($action->getId()),['rating','ratings'],true) && $this->getModule() && $this->getModule()->getId()==='index') {
            PortalRuntime::sections()->requireEnabled('rating');
        }

        if (class_exists('PortalRuntime') && PortalRuntime::enabled() && $this->getModule() && $this->getModule()->getId()==='deposit') {
            $controller=strtolower(str_replace(['-','_'],'',$this->getId()));
            $name=strtolower($action->getId());
            if ($name==='pay' && in_array($controller,['gateway','utopaygateway','wayforpaygateway'],true)) PortalRuntime::sections()->requireEnabled('deposit');
            if ($name==='buy' && $controller==='wayforpayshop') {
                PortalRuntime::sections()->requireEnabled('shop');
                PortalRuntime::sections()->requireEnabled('deposit');
            }
        }

        // Реферальная кука
        $referrerCookieName = app()->params['cookie_referer_name'];

        if (!empty($_GET[$referrerCookieName])) {
            $cookie = new CHttpCookie($referrerCookieName, $_GET[$referrerCookieName]);
            $cookie->expire = time() + 3600 * 24 * 7;

            request()->cookies[$referrerCookieName] = $cookie;
        }

        return true;
    }

    public function actions()
    {
        return [
            'captcha' => [
                'class' => 'CCaptchaAction',
                'backColor' => intval('0x' . str_replace('#', '', config('captcha.bg.color')), 0),
                'minLength' => config('captcha.min_length'),
                'maxLength' => config('captcha.max_length'),
                'foreColor' => intval('0x' . str_replace('#', '', config('captcha.font.color')), 0),
                'width' => config('captcha.width'),
                'height' => config('captcha.height'),
            ],
        ];
    }
}