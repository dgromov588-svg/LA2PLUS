<?php
class PortalController extends CabinetBaseController
{
    public $portalPage='home';
    public $layout='//layouts/portal';
    private function page($page)
    {
        if (!PortalRuntime::enabled()) throw new CHttpException(503,'Обновление кабинета ещё не включено.');
        $this->portalPage=$page;
        $store=PortalRuntime::store();
        $this->render('//cabinet/portal',['page'=>$page,'store'=>$store,'accounts'=>PortalRuntime::accounts(),'pageData'=>PortalPlayerData::load($page)]);
    }
    public function actionIndex() { $this->page('home'); }
    public function actionWarehouse() { $this->page('warehouse'); }
    public function actionExchange() { $this->page('exchange'); }
    public function actionSecurity() { $this->page('security'); }
    public function actionAdmin()
    {
        if (!RoleService::isSiteOwner()) throw new CHttpException(403,'Только владелец проекта.');
        $this->page('admin');
    }
    public function actionError(){
        $error=app()->errorHandler->error;$code=(int)($error['code']??500);http_response_code($code);
        $message=$code>=500?'Не удалось загрузить страницу. Попробуйте позже.':($code===410?'Эта страница больше недоступна. Все новые возможности находятся на главной кабинета.':($error['message']??'Страница недоступна.'));
        if(request()->isAjaxRequest){header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE);app()->end();}
        $this->render('//cabinet/portal-error',['code'=>$code,'message'=>$message]);
    }
    public function actionLogout(){if(!request()->getIsPostRequest())throw new CHttpException(405,'Требуется POST.');user()->logout();$this->redirect('/login/');}
    public function actionAccounts(){ $this->redirect(['/cabinet/portal/index']); }
    public function actionCreateAccount(){ PortalRuntime::sections()->requireEnabled('accounts');$this->page('createAccount'); }
    public function actionWallet(){ throw new CHttpException(410,'Старый кошелёк удалён. Используйте баланс нового кабинета.'); }
    public function actionCharacters(){ $this->redirect(['/cabinet/portal/index']); }
    public function actionAccess(){ throw new CHttpException(410,'Старый раздел удалён.'); }
    public function actionLogins(){ $this->page('logins'); }
    public function actionCalendar(){ PortalRuntime::sections()->requireEnabled('calendar');$this->page('calendar'); }
    public function actionShop(){ PortalRuntime::sections()->requireEnabled('shop');$this->page('shop'); }
    public function actionSupport(){ PortalRuntime::sections()->requireEnabled('support');$this->page('support'); }
    public function actionReferrals(){ PortalRuntime::sections()->requireEnabled('referrals');$this->page('referrals'); }
    public function actionHistory(){ PortalRuntime::sections()->requireEnabled('history');$this->page('history'); }
    public function actionBonuses(){ PortalRuntime::sections()->requireEnabled('bonuses');$this->page('bonuses'); }
    public function actionMessages(){ PortalRuntime::sections()->requireEnabled('messages');$this->page('messages'); }
    public function actionRequest()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            if (!request()->getIsPostRequest()) throw new CHttpException(405,'Требуется POST.');
            if (!PortalRuntime::enabled()) throw new CHttpException(503,'Кабинет временно недоступен.');
            $store=PortalRuntime::store(); $security=new PortalSecurity($store);
            $uid=PortalRuntime::uid(); $gs=PortalRuntime::gs();
            $op=(string)request()->getPost('op'); $key=(string)request()->getPost('request_key');
            PortalRuntime::sections()->requireOperation($op);
            $get=function ($name,$default='') { return request()->getPost($name,$default); };
            $result=[];
            $verify=function () use ($get) { return PortalRuntime::verifyPassword((string)$get('current_password')); };
            if(in_array($op,['buy','sell','cancel','withdraw','transfer','deliver','import','coins','shopBuy'],true))PortalCoinPayments::requireNoReview($uid);
            if (in_array($op,['buy','sell','cancel','withdraw','transfer','deliver','import','teleport','coins','calendarClaim','shopBuy'],true)) $security->requireVerifiedEmail($uid);
            if(in_array($op,['createAccount','supportCreate','supportReply','supportClose','messageRead','promo','calendarStatus','calendarClaim','shopBuy'],true)){
                $result=PortalPlayerData::action($op,$get,$key);
                echo json_encode(['ok'=>true,'result'=>$result],JSON_UNESCAPED_UNICODE);app()->end();
            }
            switch ($op) {
                case 'balance':
                    $result=['coins'=>(string)WalletService::getBalance($uid)];
                    break;
                case 'coinPaymentStatus': $result=PortalCoinPayments::sync($uid,(int)$get('order_id'));break;
                case 'coinPurchase':
                    if ((string)$get('gs_id')!==(string)$gs)throw new RuntimeException('Выбранный сервер недоступен.');
                    $quote=CoinPurchase::quote($get('coins'),(string)config('utopay.rate'));
                    if(PortalMoney::cents($get('quote_usd'))!==$quote['cents']||(string)$get('quote_total')!==(string)$quote['total'])throw new RuntimeException('Расчёт изменился. Обновите сумму перед оплатой.');
                    $result=PortalCoinPayments::create($uid,$gs,$quote,$key,(string)user()->get('email'),(string)$get('provider','trybit'));
                    break;
                case 'coinQuote':
                case 'demoCoinTopup':
                    if ((string)$get('gs_id')!==(string)$gs) throw new RuntimeException('Выбранный сервер недоступен. Обновите страницу.');
                    $quote=CoinPurchase::quote($get('coins'),(string)config('utopay.rate'));
                    if ($op==='coinQuote') { $result=$quote;break; }
                    if (!PortalRuntime::isDemo()) throw new RuntimeException('Демонстрационное пополнение недоступно.');
                    $transaction=db()->beginTransaction();
                    try {
                        $result=$store->run($uid,$key,'demo.coins.topup',[$gs,$quote],function () use ($store,$uid,$key,$quote) {
                            WalletService::credit($uid,$quote['total'],['type'=>'demo','comment'=>'DEMO ONLY: '.$key]);
                            $store->audit($uid,'demo.coins.topup',$key,$quote+['real_payment'=>false]);
                            return $quote+['message'=>'Начислено '.$quote['total'].' демонстрационных CoL. Реальная оплата не выполнялась.'];
                        });
                        $transaction->commit();
                    } catch (Throwable $e) { $transaction->rollback();throw $e; }
                    break;
                case 'coinReviewResolve':
                    $this->owner();$id=(int)$get('order_id');$action=(string)$get('action');$reason=trim((string)$get('reason'));
                    if(!in_array($action,['keep','reverse'],true)||strlen($reason)<4||strlen($reason)>500)throw new RuntimeException('Укажите решение и причину проверки.');
                    $tr=db()->beginTransaction();try{
                        $result=$store->run($uid,$key,'coins.review.resolve',[$id,$action,$reason],function()use($store,$uid,$id,$action,$reason){
                            $row=$store->query('SELECT * FROM {{portal_coin_orders}} WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);if(!$row||$row['state']!=='review')throw new RuntimeException('Заказ не ожидает проверки.');
                            $store->wallet($row['user_id'],true);
                            if($action==='reverse')WalletService::debit($row['user_id'],$row['total'],['order_id'=>'refund:'.$id,'comment'=>'Возврат платежа CoL']);
                            $store->query('UPDATE {{portal_coin_orders}} SET state=?,updated_at=? WHERE id=?',[$action==='reverse'?'refunded':'paid',time(),$id]);
                            $store->audit($uid,'coins.review.resolved',$id,['action'=>$action,'reason'=>$reason]);return ['message'=>'Решение по платежу сохранено.'];
                        });$tr->commit();
                    }catch(Throwable $e){$tr->rollback();throw $e;}break;
                case 'coinOrderSync':
                    $this->owner();$order=$store->query('SELECT user_id FROM {{portal_coin_orders}} WHERE id=?',[(int)$get('order_id')])->fetch(PDO::FETCH_ASSOC);
                    if(!$order)throw new RuntimeException('Заказ не найден.');
                    $result=PortalCoinPayments::sync((int)$order['user_id'],(int)$get('order_id'));break;
                case 'code':
                    $purpose=(string)$get('purpose');if($purpose==='admin_password')$purpose.=':'.(int)$get('user_id');
                    if(strpos($purpose,'admin_password:')===0){$this->owner();if(!Users::model()->findByPk((int)substr($purpose,15)))throw new RuntimeException('Игрок не найден.');}
                    if (strpos($purpose,'password:')===0) PortalRuntime::account((int)substr($purpose,9));
                    $mail=new PortalMail(); $mail->init();
                    $email=(string)user()->get('email');
                    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Укажите действующую почту в профиле.');
                    $security->issue($uid,$purpose,$verify,function ($code) use ($mail,$email) { return $mail->sendCode($email,$code); });
                    $result=['message'=>'Код отправлен на вашу почту.']; break;
                case 'verifyEmail': $security->verifyEmail($uid,(string)$get('code')); break;
                case 'adminPassword':
                    $this->owner();
                    if(!$security->isVerified($uid))throw new RuntimeException('Сначала подтвердите почту владельца.');
                    $target=Users::model()->findByPk((int)$get('user_id'));if(!$target)throw new RuntimeException('Игрок не найден.');
                    $password=(string)$get('new_password');
                    if(!preg_match('/^[\x21-\x7E]{6,16}$/D',$password)||$password!==(string)$get('repeat_password'))throw new RuntimeException('Пароль: 6–16 символов, повтор должен совпадать.');
                    $security->requirePassword($uid,$verify);$security->consume($uid,'admin_password:'.$target->getPrimaryKey(),(string)$get('code'));
                    $store->audit($uid,'admin.password.requested',$target->getPrimaryKey());
                    $l2=l2('gs',(int)$target->gs_id)->connect();
                    $affected=$l2->getDb()->createCommand()->update('accounts',['password'=>$l2->passwordEncrypt($password)],'login=:l',[':l'=>$target->login]);
                    if(!$affected)throw new RuntimeException('Пароль не изменён: проверьте аккаунт или выберите другой пароль.');
                    $store->audit($uid,'admin.password.changed',$target->getPrimaryKey());
                    $result=['message'=>'Пароль аккаунта изменён. Передайте новый пароль владельцу защищённым способом.'];
                    break;
                case 'password':
                    $account=PortalRuntime::account((int)$get('link_id'));
                    $password=(string)$get('new_password');
                    if (!$security->isVerified($uid)) throw new RuntimeException('Сначала подтвердите почту.');
                    $security->requirePassword($uid,$verify);
                    if (!preg_match('/^[\x21-\x7E]{6,16}$/D',$password) || $password!==(string)$get('repeat_password')) throw new RuntimeException('Пароль: 6–16 символов без пробелов; повтор должен совпадать.');
                    $security->consume($uid,'password:'.$account['id'],(string)$get('code'));
                    $l2=l2('gs',$gs)->connect();
                    $l2->getDb()->createCommand()->update('accounts',['password'=>$l2->passwordEncrypt($password)],'login=:l',[':l'=>$account['game_login']]);
                    $store->audit($uid,'account.password',$account['id']); break;
                case 'hide':
                    $account=PortalRuntime::account((int)$get('link_id'));
                    $store->atomic(function () use ($store,$uid,$account,$get) {
                        $store->wallet($uid,true);
                        $store->query('DELETE FROM {{portal_hidden_accounts}} WHERE user_id=? AND link_id=?',[$uid,$account['id']]);
                        if ((string)$get('hidden')==='1') $store->query('INSERT INTO {{portal_hidden_accounts}}(user_id,link_id) VALUES(?,?)',[$uid,$account['id']]);
                    }); break;
                case 'teleport': $result=PortalRuntime::teleport((int)$get('link_id'),(int)$get('char_id'),$key); break;
                case 'sell': $result=$store->sell($uid,(int)$get('item_id'),PortalMoney::cents($get('amount')),$key); break;
                case 'buy': $result=$store->buy($uid,(int)$get('lot_id'),$gs,$key); break;
                case 'cancel': $result=$store->cancelLot($uid,(int)$get('lot_id'),$key); break;
                case 'withdraw': $result=$store->withdraw($uid,PortalMoney::cents($get('amount')),trim((string)$get('address')),$key); break;
                case 'transfer':
                    $rate=(string)config('utopay.rate');
                    $transaction=db()->beginTransaction();
                    try {
                        $result=$store->transferToCabinet($uid,PortalMoney::cents($get('amount')),$key,function ($hundredths,$ref) use ($uid) {
                            WalletService::credit($uid,$hundredths/100,['type'=>'exchange','comment'=>'Перевод с биржи '.$ref]);
                        },$rate);
                        $transaction->commit();
                    } catch (Throwable $e) { $transaction->rollback(); throw $e; }
                    break;
                case 'deliver':
                    $char=PortalRuntime::character((int)$get('link_id'),(int)$get('char_id'));
                    if ((int)$char['online']!==0) throw new RuntimeException('Для получения предмета выйдите из игры.');
                    if (!PortalRuntime::gameReady()) throw new RuntimeException('Выдача предметов ещё не подключена.');
                    $result=$store->queueDelivery($uid,(int)$get('item_id'),$gs,(int)$char['char_id'],$key); break;
                case 'coins':
                    $store->requireEnabled('warehouse');
                    if (!PortalRuntime::gameReady()) throw new RuntimeException('Перевод в игру ещё не подключён.');
                    $itemId=(int)$store->setting('col_item_id','0');
                    if (!$itemId) throw new RuntimeException('Перевод CoL пока недоступен.');
                    $count=filter_var($get('coins'),FILTER_VALIDATE_INT);
                    if (!$count || $count<1 || $count>1000000) throw new RuntimeException('Укажите целое количество CoL от 1 до 1000000.');
                    $char=PortalRuntime::character((int)$get('link_id'),(int)$get('char_id'));
                    if ((int)$char['online']!==0) throw new RuntimeException('Для получения CoL выйдите из игры.');
                    $transaction=db()->beginTransaction();
                    try {
                        $result=$store->run($uid,$key,'coins.queue',[(int)$char['char_id'],$count,$gs],function () use ($store,$uid,$gs,$key,$count,$itemId,$char) {
                            WalletService::debit($uid,$count,['order_id'=>'portal-'.$key,'comment'=>'Перевод CoL персонажу '.$char['char_name']]);
                            $id=$store->grantItem($uid,$gs,['item_id'=>$itemId,'name'=>'Coin of Luck','quantity'=>$count],'coins:'.$key);
                            return $store->queueDelivery($uid,$id,$gs,(int)$char['char_id'],$key.'-delivery');
                        });
                        $transaction->commit();
                    } catch (Throwable $e) { $transaction->rollback(); throw $e; }
                    break;
                case 'import':
                    $store->requireEnabled('market');
                    $char=PortalRuntime::character((int)$get('link_id'),(int)$get('char_id'));
                    if ((int)$char['online']!==0) throw new RuntimeException('Сначала выйдите из игры.');
                    $object=(int)$get('object_id'); if ($object<1) throw new RuntimeException('Выберите предмет.');
                    $snapshot=PortalRuntime::bridge('escrow',$key,['char_id'=>(int)$char['char_id'],'account'=>$char['account_name'],'object_id'=>$object]);
                    if (empty($snapshot['item']) || empty($snapshot['receipt'])) throw new RuntimeException('Сервер не подтвердил резервирование предмета.');
                    $result=$store->run($uid,$key,'item.import',[$object,(int)$char['char_id']],function () use ($store,$uid,$gs,$snapshot,$key) {
                        $id=$store->grantItem($uid,$gs,$snapshot['item'],'bridge:'.$gs.':'.$key);
                        $store->audit($uid,'item.import',$id,['receipt'=>$snapshot['receipt']]); return ['id'=>$id];
                    }); break;
                case 'inventory':
                    $char=PortalRuntime::character((int)$get('link_id'),(int)$get('char_id'));
                    $inventory=PortalRuntime::bridge('inventory',$key,['char_id'=>(int)$char['char_id'],'account'=>$char['account_name']]);
                    $result=['items'=>[]];
                    foreach (array_slice($inventory['items']??[],0,300) as $item) {
                        if (empty($item['object_id']) || empty($item['name'])) continue;
                        $result['items'][]=['object_id'=>(int)$item['object_id'],'item_id'=>(int)($item['item_id']??0),'name'=>(string)$item['name'],'enchant'=>(int)($item['enchant']??0),'quantity'=>(int)($item['quantity']??1),'grade'=>(string)($item['grade']??'NG'),'augmented'=>!empty($item['augmented'])];
                    }
                    break;
                case 'payment': $result=PortalPayments::create($uid,PortalMoney::cents($get('amount')),$key); break;
                case 'paymentStatus': $result=PortalPayments::sync($uid,(int)$get('payment_id')); break;
                case 'discount':
                    $this->owner();
                    $target=(int)$get('user_id'); if (!Users::model()->findByPk($target)) throw new RuntimeException('Игрок не найден.');
                    $expires=strtotime((string)$get('expires').' UTC');
                    $percent=filter_var($get('percent'),FILTER_VALIDATE_INT);
                    if ($percent===false) throw new RuntimeException('Процент должен быть целым числом.');
                    $store->setDiscount($uid,$target,$percent,$expires); break;
                case 'withdrawAdmin':
                    $this->owner();
                    $result=$store->processWithdrawal($uid,(int)$get('withdrawal_id'),(string)$get('action'),trim((string)$get('txid'))); break;
                default: throw new CHttpException(400,'Неизвестная операция.');
            }
            echo json_encode(['ok'=>true,'result'=>$result],JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            $status=$e instanceof CHttpException?$e->statusCode:400;
            if ($e instanceof PDOException || $e instanceof CDbException) {
                Yii::log('Portal database operation failed: '.$e->getCode(),CLogger::LEVEL_ERROR,'Portal');
                $message='Не удалось завершить операцию. Обновите данные и повторите с тем же номером операции.';
            } else $message=$e->getMessage();
            http_response_code($status); echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE);
        }
        app()->end();
    }
    private function owner()
    {
        if (!RoleService::isSiteOwner()) throw new CHttpException(403,'Доступно только владельцу проекта.');
    }
}
