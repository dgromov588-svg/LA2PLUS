<?php
/** Data for the new player pages. No legacy controller/view is dispatched. */
class PortalPlayerData
{
    public static function load($page)
    {
        $uid=PortalRuntime::uid();$data=[];
        $search=['history'=>'TransactionHistorySearch','messages'=>'MessagesSearch','support'=>'TicketsSearch','logins'=>'AuthHistorySearch'];
        if(isset($search[$page])){$class='app\\modules\\cabinet\\models\\search\\'.$search[$page];$provider=(new $class())->search();$data=['rows'=>$provider->getData(),'pagination'=>$provider->getPagination()];}
        if($page==='messages'&&request()->getQuery('id')){
            $model=UserMessages::model()->findByPk((int)request()->getQuery('id'),'user_id=:u',[':u'=>$uid]);if(!$model)throw new CHttpException(404,'Уведомление не найдено.');$data['message']=$model;
        }
        if($page==='support'){
            $form=new \app\modules\cabinet\models\forms\TicketsForm();$ticket=new Tickets();$data['categories']=$form->getCategories();$data['priorities']=$ticket->getPrioritiesList();
            if(request()->getQuery('ticket_id')){
                $ticket=Tickets::model()->find('id=:id AND user_id=:u',[':id'=>(int)request()->getQuery('ticket_id'),':u'=>$uid]);if(!$ticket)throw new CHttpException(404,'Обращение не найдено.');
                $data['ticket']=$ticket;$data['answers']=TicketsAnswers::model()->findAll(['condition'=>'ticket_id=:t','params'=>[':t'=>$ticket->id],'order'=>'created_at ASC']);
            }
        }
        if($page==='calendar'){$data['calendar']=PortalAttendanceRuntime::refresh($uid,PortalRuntime::gs());$data['rewards']=PortalAttendanceRuntime::rewards();}
        if($page==='referrals'){$data['enabled']=(bool)config('referral_program.allow');$data['count']=count(Referals::getByUserId($uid));$data['link']=app()->createAbsoluteUrl('/index/default/index').'?'.rawurlencode(app()->params['cookie_referer_name']).'='.rawurlencode(user()->get('referer'));$data['profits']=ReferalsProfit::model()->findAll(['condition'=>'referer_id=:u','params'=>[':u'=>$uid],'order'=>'created_at DESC','limit'=>100]);}
        if($page==='shop'){
            $data['categories']=ShopCategories::getOpenCategories(PortalRuntime::gs());$class='app\\modules\\cabinet\\models\\search\\ShopSearch';$result=(new $class())->search(request()->getQuery('category_link'),PortalRuntime::gs());
            $data['category']=$result?$result['category']:null;$data['packs']=$result?$result['dataProvider']->getData():[];
        }
        if($page==='history')$data['orders']=PortalRuntime::store()->query('SELECT * FROM {{portal_coin_orders}} WHERE user_id=? ORDER BY id DESC LIMIT 100',[$uid])->fetchAll(PDO::FETCH_ASSOC);
        return $data;
    }
    public static function error($model)
    {
        $messages=[];foreach($model->getErrors() as $errors)foreach($errors as $error)$messages[]=strip_tags($error);return implode(' ',array_slice($messages,0,5))?:'Проверьте заполненные поля.';
    }
    public static function action($operation,callable $get,$key)
    {
        $uid=PortalRuntime::uid();$gs=PortalRuntime::gs();$store=PortalRuntime::store();
        switch($operation){
            case 'createAccount':
                $model=new \app\modules\cabinet\models\forms\CreateGameAccountForm();$model->setAttributes(['login'=>(string)$get('login'),'password'=>(string)$get('password'),'re_password'=>(string)$get('repeat_password'),'email'=>(string)$get('email'),'note'=>'']);
                if(!$model->validate())throw new RuntimeException(self::error($model));$result=$model->create();return ['message'=>'Игровой аккаунт создан: '.$result['login']];
            case 'supportCreate':
                $model=new \app\modules\cabinet\models\forms\TicketsForm();$model->setAttributes(['category_id'=>$get('category_id'),'priority'=>$get('priority'),'date_incident'=>$get('date_incident'),'char_name'=>$get('char_name'),'title'=>$get('title'),'text'=>$get('text')]);
                if(!$model->create())throw new RuntimeException(self::error($model));return ['message'=>'Обращение отправлено в поддержку.'];
            case 'supportReply':case 'supportClose':
                $ticket=Tickets::model()->find('id=:id AND user_id=:u',[':id'=>(int)$get('ticket_id'),':u'=>$uid]);if(!$ticket)throw new CHttpException(404,'Обращение не найдено.');if(!$ticket->isStatusOn())throw new RuntimeException('Обращение уже закрыто.');
                if($operation==='supportClose'){$ticket->status=ActiveRecord::STATUS_OFF;$ticket->save(false);return ['message'=>'Обращение закрыто.'];}
                $reply=new TicketsAnswers();$reply->setAttributes(['text'=>(string)$get('text')]);$reply->ticket_id=$ticket->id;$reply->user_id=$uid;$reply->gs_id=$gs;
                if(!$reply->validate())throw new RuntimeException(self::error($reply));$tr=db()->beginTransaction();try{$reply->save(false);$ticket->new_message_for_admin=1;$ticket->save(false);$tr->commit();}catch(Throwable $e){$tr->rollback();throw $e;}return ['message'=>'Ответ отправлен.'];
            case 'messageRead':
                $message=UserMessages::model()->findByPk((int)$get('message_id'),'user_id=:u',[':u'=>$uid]);if(!$message)throw new CHttpException(404,'Уведомление не найдено.');$message->read=UserMessages::STATUS_READ;$message->save(false);return ['message'=>'Уведомление отмечено прочитанным.'];
            case 'promo':
                if(!PortalRuntime::gameReady())throw new RuntimeException('Активация игровых наград станет доступна после подключения игрового сервера. Код не использован.');
                $model=new \app\modules\cabinet\models\forms\ActivationBonusCodeForm();$model->setAttributes(['code'=>(string)$get('code')]);if(!$model->activated())throw new RuntimeException(self::error($model));return ['message'=>'Промокод активирован.'];
            case 'calendarStatus':return PortalAttendanceRuntime::refresh($uid,$gs);
            case 'calendarClaim':
                $status=PortalAttendanceRuntime::refresh($uid,$gs);if(!$status['available'])throw new RuntimeException('Не удалось подтвердить игровое время. Повторите позже.');
                return PortalAttendance::claim($store,$status['owner'],$uid,$gs,PortalAttendanceRuntime::rewards(),$key);
            case 'shopBuy':
                if(!PortalRuntime::gameReady()||!$store->enabled('warehouse'))throw new RuntimeException('Покупки временно недоступны: подключается выдача предметов. Баланс не списан.');
                $quantity=filter_var($get('quantity'),FILTER_VALIDATE_INT);if(!$quantity||$quantity<1||$quantity>1000)throw new RuntimeException('Количество наборов: от 1 до 1000.');
                $item=ShopItems::model()->opened()->with('itemInfo')->findByPk((int)$get('item_id'));if(!$item)throw new RuntimeException('Товар недоступен.');
                $pack=db()->createCommand('SELECT category_id FROM {{shop_items_packs}} WHERE id=:p AND status=1')->queryRow(true,[':p'=>$item->pack_id]);$allowed=false;
                foreach(ShopCategories::getOpenCategories($gs) as $category)if($pack&&(int)$category->id===(int)$pack['category_id'])$allowed=true;if(!$allowed)throw new RuntimeException('Товар недоступен на выбранном сервере.');
                $unit=PortalMoney::discounted(PortalMoney::cents((string)$item->cost),PortalRuntime::discount($uid),(int)$item->discount);if((string)$get('expected_unit')!==(string)$unit)throw new RuntimeException('Цена товара изменилась. Обновите страницу перед покупкой.');$cost=$unit*$quantity;
                if($cost<=0)throw new RuntimeException('Цена товара не настроена.');$count=(int)$item->count*$quantity;$tr=db()->beginTransaction();
                try{$result=$store->run($uid,$key,'shop.buy',[(int)$item->id,$quantity,$cost,$gs],function()use($store,$uid,$gs,$item,$cost,$count,$key){WalletService::debit($uid,$cost/100,['order_id'=>'shop:'.$key,'comment'=>'Покупка в магазине']);$id=$store->grantItem($uid,$gs,['item_id'=>(int)$item->item_id,'quantity'=>$count,'enchant'=>(int)$item->enchant,'name'=>$item->itemInfo->getFullName()],'shop:'.$key);return ['item_id'=>$id,'message'=>'Покупка добавлена на склад.'];});$tr->commit();return $result;}catch(Throwable $e){$tr->rollback();throw $e;}
        }
        throw new RuntimeException('Неизвестная операция.');
    }
}
