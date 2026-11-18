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

        $incomingController=strtolower($this->getId());$incomingAction=strtolower($action->getId());
        if($incomingController==='deposit'&&$incomingAction==='index'&&request()->getIsPostRequest())$this->redirect('/cabinet/deposit/',true,303);
        if (user()->isGuest) {
            $this->redirect(user()->loginUrl);
        }

        if(request()->getIsPostRequest()&&$incomingAction!=='error')request()->validateCsrfToken(new CEvent(app()));

        (new \app\services\ThemeService())->setTheme(config('theme'));

        if (class_exists('PortalRuntime') && PortalRuntime::enabled()) {
            $this->layout='//layouts/portal';app()->errorHandler->errorAction='cabinet/portal/error';
            $controller=strtolower($this->getId()); $name=strtolower($action->getId());
            if(in_array(str_replace('-','',$controller),['wallet','accounts','characters','security','authhistory','changepassword','panel','roles'],true))throw new CHttpException(410,'Старый раздел удалён. Откройте новый личный кабинет.');
            if(str_replace('-','',$controller)==='ticketsadmin'){if(!RoleService::can('panel.view'))throw new CHttpException(403,'Доступ запрещён.');if(request()->getIsPostRequest())throw new CHttpException(410,'Используйте общую админ-панель.');$this->redirect('/admin/tickets/');}
            if($controller!=='portal'&&in_array(str_replace('-','',$controller),['shop','tickets','referals','transactionhistory','bonuses','messages'],true)){
                if(request()->getIsPostRequest())throw new CHttpException(410,'Используйте новый личный кабинет.');
                $target=['shop'=>'shop','tickets'=>'support','referals'=>'referrals','transactionhistory'=>'history','bonuses'=>'bonuses','messages'=>'messages'][str_replace('-','',$controller)];
                $this->redirect('/cabinet/portal/'.$target.'/');
            }
            if($controller==='default'&&in_array($name,['calendarbuy','calendarclaim'],true))throw new CHttpException(410,'Платный календарь заменён ежедневными наградами.');
            if($controller==='default'&&$name==='calendar')$this->redirect('/cabinet/portal/calendar/');
            $section=PortalRuntime::sections()->forRoute($controller,$name);
            if ($section!==null) PortalRuntime::sections()->requireEnabled($section);
            $canonical=PortalRoutes::resolve($controller,$name);
            if ($canonical && !PortalRoutes::$dispatching && !request()->getIsPostRequest()) {
                $this->redirect(PortalRoutes::url($canonical[0],$canonical[1],$_GET),true,302);
            }
            if (($controller==='accounts' && $name==='switch') || ($controller==='tickets' && $name==='close')) {
                if (!request()->getIsPostRequest()) throw new CHttpException(405,'Требуется POST.');
            }
            if(($controller==='deposit'&&in_array($name,['shopcreate','transfer'],true))||($controller==='shopcoin'&&$name==='shopcreate'))throw new CHttpException(410,'Используйте новую страницу пополнения и окно перевода CoL.');
            if($controller==='shopcoin'&&$name==='shop')$this->redirect(['/cabinet/deposit/index']);
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

    public function render($view,$data=null,$return=false)
    {
        $html=parent::render($view,$data,true);
        if(class_exists('PortalRoutes') && PortalRuntime::enabled()) {
            $html=preg_replace_callback('/\b(href|action)="([^"<>]*)"/',function($m){
                $url=html_entity_decode($m[2],ENT_QUOTES,'UTF-8');
                return $m[1].'="'.CHtml::encode(PortalRoutes::rewrite($url)).'"';
            },$html);
        }
        if($return)return $html;echo $html;
    }

    public function createUrl($route,$params=[],$ampersand='&')
    {
        $url=parent::createUrl($route,$params,$ampersand);
        if(class_exists('PortalRoutes') && PortalRuntime::enabled())return PortalRoutes::rewrite($url);
        return $url;
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
