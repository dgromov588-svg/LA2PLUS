<?php
/** Test deployment: standard CMS authentication with a dedicated presentation. */
class DefaultController extends FrontendBaseController
{
    public $layout='//layouts/auth';
    public function actionIndex()
    {
        if (!user()->isGuest) $this->redirect(['/cabinet/default/index']);
        $model=new LoginForm();
        if (isset($_POST['LoginForm']) && !$model->isBlockedForm() && $model->getGsList()) {
            $model->setAttributes($_POST['LoginForm']);
            if ($model->validate() && $model->login()) $this->redirect(['/cabinet/default/index']);
        }
        $this->render('//login-portal',['model'=>$model]);
    }
}
