<?php

class CabinetBaseController extends Controller
{
    /**
     * Журнал посещений: фиксируем заход в кабинет для аналитики.
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

        if (user()->isGuest) {
            $this->redirect(user()->loginUrl);
        }

        (new \app\services\ThemeService())->setTheme(config('theme'));

        if (class_exists('PortalRuntime') && PortalRuntime::enabled()) {
            $this->layout='//layouts/portal';
            $controller=strtolower($this->getId()); $name=strtolower($action->getId());
            $section=PortalRuntime::sections()->forRoute($controller,$name);
            if ($section!==null) PortalRuntime::sections()->requireEnabled($section);
            // Legacy teleport is a GET link; use the PIN-confirmed POST dialog instead.
            if ($controller==='characters' && $name==='teleport') $this->redirect(['/cabinet/portal/index']);
            if (in_array(str_replace('-','',$controller),['shoputopay','wayforpayshop'],true) && $name==='buy') $this->redirect(['/cabinet/deposit/index']);
            $protected=($controller==='wallet' && request()->getIsPostRequest())
                || ($controller==='shop' && $name==='buy')
                || ($controller==='default' && in_array($name,['calendarbuy','calendarclaim'],true))
                || ($controller==='deposit' && $name==='transfer');
            if ($protected) {
                if (!request()->getIsPostRequest()) throw new CHttpException(405,'Требуется POST.');
                (new PortalSecurity(PortalRuntime::store()))->requirePin((int)user()->getId(),(string)request()->getPost('portal_pin',''));
            }
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
