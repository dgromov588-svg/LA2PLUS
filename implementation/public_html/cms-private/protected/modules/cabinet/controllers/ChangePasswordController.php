<?php

class ChangePasswordController extends CabinetBaseController
{
    public function actionIndex()
    {
        if (class_exists('PortalRuntime') && PortalRuntime::enabled()) {
            $this->redirect(['/cabinet/portal/security']);
            return;
        }
        $model = new ChangePasswordForm();

        if (isset($_POST['ChangePasswordForm'])) {
            $model->setAttributes($_POST['ChangePasswordForm']);

            if ($model->validate()) {
                if ($model->changePassword()) {
                    user()->setFlash(FlashConst::MESSAGE_SUCCESS, Yii::t('cabinet', 'password.changed'));
                    $this->refresh();
                } else {
                    user()->setFlash(FlashConst::MESSAGE_ERROR, Yii::t('cabinet', 'changePasswordForm.errorOccurredPleaseTry'));
                }
            }
        }

        $this->render('//cabinet/change-password', [
            'model' => $model,
        ]);
    }
}
