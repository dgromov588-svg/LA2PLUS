<?php
class PortalController extends CabinetBaseController
{
    public $layout='//layouts/portal';
    private function page($page)
    {
        if (!PortalRuntime::enabled()) throw new CHttpException(503,'Обновление кабинета ещё не включено.');
        $store=PortalRuntime::store();
        $this->render('//cabinet/portal',['page'=>$page,'store'=>$store,'accounts'=>PortalRuntime::accounts()]);
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
            if (in_array($op,['buy','sell','cancel','withdraw','transfer','deliver','import','teleport','coins'],true)) $security->requirePin($uid,(string)$get('pin'));
            switch ($op) {
                case 'sectionToggle':
                    $this->owner();
                    $result=PortalRuntime::sections()->set($uid,(string)$get('section'),(string)$get('enabled'));
                    break;
                case 'code':
                    $purpose=(string)$get('purpose');
                    if (strpos($purpose,'password:')===0) PortalRuntime::account((int)substr($purpose,9));
                    $mail=new PortalMail(); $mail->init();
                    $email=(string)user()->get('email');
                    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Укажите действующую почту в профиле.');
                    $security->issue($uid,$purpose,$verify,function ($code) use ($mail,$email) { return $mail->sendCode($email,$code); });
                    $result=['message'=>'Код отправлен на вашу почту.']; break;
                case 'verifyEmail': $security->verifyEmail($uid,(string)$get('code')); break;
                case 'setPin': $security->setPin($uid,(string)$get('new_pin'),(string)$get('repeat_pin'),(string)$get('code'),$verify); break;
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
            if ($e instanceof PDOException) {
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
