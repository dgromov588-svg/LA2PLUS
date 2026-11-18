<?php

/**
 * Шлюз дополнительных платёжных систем: Trybit, PayOp, PayPalych, ручные ссылки.
 *
 * Маршруты (protected/config/main.php):
 *   POST deposit/pay            — создать платёж по заявке и увести на страницу оплаты
 *   GET  deposit/pay-return     — покупатель вернулся с оплаты (проверяем статус)
 *   GET  deposit/pay-cancel     — покупатель отказался
 *   GET  deposit/pay-status     — JSON-статус заявки (автообновление страницы)
 *   POST deposit-trybit.php     — постбэк Trybit   (модуль-приёмник)
 *   POST deposit-payop.php      — IPN PayOp        (модуль-приёмник)
 *   POST deposit-paypalych.php  — постбэк PayPalych (модуль-приёмник)
 *
 * Зачисление — через DepositCreditService (идемпотентно), как у WayForPay/UTOPAY.
 */
class GatewayController extends FrontendBaseController
{
    /** @var DepositCreditService */
    private $_credit;

    /**
     * @return DepositCreditService
     */
    public function credit()
    {
        if ($this->_credit === null) {
            Yii::import('application.modules.deposit.components.*');

            $this->_credit = new DepositCreditService();
        }

        return $this->_credit;
    }

    /**
     * POST: создать платёж и уйти на страницу оплаты.
     */
    public function actionPay()
    {
        if (!request()->getIsPostRequest()) {
            $this->redirect(['/cabinet/deposit']);
        }

        if (user()->isGuest) {
            $this->redirect(user()->loginUrl);
        }

        $txId = (int) request()->getPost('tx_id', 0);

        /** @var Transactions $transaction */
        $transaction = Transactions::model()->findByPk($txId);

        if (!$transaction || (int) $transaction->user_id !== (int) user()->getId()) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, Yii::t('cabinet', 'deposit.transactionNotFound'));
            $this->redirect(['/cabinet/deposit']);
        }

        if ($transaction->isPaid()) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, Yii::t('cabinet', 'deposit.transactionProcessed'));
            $this->redirect(['/cabinet/deposit']);
        }

        $params = json_decode((string) $transaction->params, true);
        $params = is_array($params) ? $params : [];

        $provider = isset($params['gateway']) ? (string) $params['gateway'] : '';
        $payId = isset($params['pay_id']) ? (string) $params['pay_id'] : '';

        if ($provider === '') {
            user()->setFlash(FlashConst::MESSAGE_ERROR, Yii::t('cabinet', 'deposit.transactionNotFound'));
            $this->redirect(['/cabinet/deposit']);
        }

        try {
            $url = $this->createGatewayPayment($transaction, $provider, $payId);

            $this->redirect($url);
        } catch (Exception $e) {
            Yii::log('gateway pay error: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'gateway');

            user()->setFlash(FlashConst::MESSAGE_ERROR, $e->getMessage());
            $this->redirect(['/cabinet/deposit']);
        }
    }

    /**
     * Создаёт платёж у выбранного провайдера и сохраняет ссылку в params заявки.
     *
     * @param Transactions $transaction
     * @param string       $provider trybit | nowpayments | payop | paypalych | manual
     * @param string       $payId    конкретный метод (для manual: international|sbp_qr|erip)
     *
     * @return string ссылка на оплату
     * @throws Exception
     */
    public function createGatewayPayment(Transactions $transaction, $provider, $payId = '')
    {
        Yii::import('application.modules.deposit.extensions.Deposit.libs.*');

        $params = json_decode((string) $transaction->params, true);
        $params = is_array($params) ? $params : [];

        // сумма к оплате шлюза = сумма заявки (уже в валюте сайта)
        $amount = (float) $transaction->sum;
        $description = 'LA2PLUS: ' . (int) $transaction->count . ' монет, заявка #' . $transaction->id;
        $email = (string) user()->get('email');

        $link = null;
        $externalId = '';

        switch ($provider) {
            case 'trybit':
                $gateway = new Trybit();

                if (!$gateway->isEnabled()) {
                    throw new Exception('Trybit не настроен.');
                }

                $order = $gateway->createInvoice($transaction->id, $gateway->toChargeAmount($amount), $description, $email);
                $link = $order['link'];
                $externalId = $order['invoice_id'];
                break;

            case 'nowpayments':
                // NOWPayments: сумма в монетах переводится в валюту счёта по nowpayments.rate.
                $gateway = new Nowpayments();

                if (!$gateway->isEnabled()) {
                    throw new Exception('NOWPayments не настроен.');
                }

                $order = $gateway->createInvoice($transaction->id, $gateway->toChargeAmount($amount), $description);
                $link = $order['link'];
                $externalId = $order['invoice_id'];
                break;

            case 'payop':
                $gateway = new Payop();

                if (!$gateway->isEnabled()) {
                    throw new Exception('PayOp не настроен.');
                }

                $order = $gateway->createInvoice($transaction->id, $gateway->toChargeAmount($amount), $description, $email);
                $link = $order['link'];
                $externalId = $order['invoice_id'];
                break;

            case 'paypalych':
                $gateway = new Paypalych();

                if (!$gateway->isEnabled()) {
                    throw new Exception('PayPalych не настроен.');
                }

                $order = $gateway->createBill(
                    $transaction->id,
                    $gateway->toChargeAmount($amount),
                    $description,
                    $this->absoluteUrl('/deposit/pay-return', ['tx' => (int) $transaction->id]),
                    $this->absoluteUrl('/deposit/pay-cancel', ['tx' => (int) $transaction->id])
                );
                $link = $order['link'];
                $externalId = $order['bill_id'];
                break;

            case 'manual':
                $gateway = new ManualLink($payId !== '' ? $payId : 'international');

                if (!$gateway->isEnabled()) {
                    throw new Exception('Этот способ оплаты ещё не настроен.');
                }

                $link = $gateway->buildLink($transaction->id, $amount);
                break;

            default:
                throw new Exception('Неизвестная платёжная система.');
        }

        $params['gateway'] = $provider;
        $params['pay_id'] = $payId;
        $params['external_id'] = $externalId;
        // Сумма, на которую выставлен счёт у шлюза: при зачислении сверяем,
        // что оплачен именно наш счёт и именно на нашу сумму.
        $params['invoice_amount'] = (float) $amount;
        $params['invoice_tx'] = (int) $transaction->id;
        $params['pay_url'] = $link;
        $params['pay_created_at'] = time();

        db()->createCommand()->update('{{transactions}}', [
            'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', [':id' => $transaction->id]);

        return $link;
    }

    /**
     * Возврат покупателя с оплаты.
     */
    public function actionReturn()
    {
        // Trybit (и другие шлюзы) могут вернуть покупателя на «чистый» адрес без номера заявки.
        // Тогда монеты придут вебхуком, а здесь просто показываем корректное сообщение.
        $txId = (int) request()->getQuery('tx', 0);

        // Trybit/PayOp возвращают покупателя с order_id=<наш id заявки> и invoice_uuid.
        if ($txId <= 0) {
            $txId = (int) request()->getQuery('order_id', 0);
        }

        // Последний вариант — ищем по номеру счёта шлюза.
        if ($txId <= 0) {
            $invoiceUuid = (string) request()->getQuery('invoice_uuid', '');

            if ($invoiceUuid !== '') {
                $txId = $this->findTransactionByInvoice($invoiceUuid);
            }
        }

        if ($txId <= 0) {
            $txId = $this->findPendingTransactionId();
        }

        $state = $txId > 0 ? $this->syncPayment($txId) : 'pending';

        if ($state === 'paid') {
            user()->setFlash(FlashConst::MESSAGE_SUCCESS, 'Оплата прошла, монеты зачислены на баланс.');
        } else {
            user()->setFlash(FlashConst::MESSAGE_ERROR, 'Платёж ещё не подтверждён. Монеты зачислятся автоматически после подтверждения.');
        }

        $this->redirect(['/cabinet/deposit']);
    }

    /**
     * Отказ от оплаты.
     */
    public function actionCancel()
    {
        user()->setFlash(FlashConst::MESSAGE_ERROR, 'Оплата отменена. Если деньги всё же списались — монеты придут автоматически, заявка обновится сама.');

        $this->redirect(['/cabinet/deposit']);
    }

    /**
     * Последняя неоплаченная заявка текущего игрока (когда шлюз вернул его без номера заявки).
     *
     * @return int
     */
    private function findPendingTransactionId()
    {
        if (user()->isGuest) {
            return 0;
        }

        // Сырой SQL с :параметрами в этом проекте не биндится — подставляем числа напрямую
        // (оба значения приводятся к int, инъекция невозможна).
        // Только свежие заявки (2 часа): иначе возврат без номера заявки
        // «находил» чужой старый черновик и показывал неверный статус.
        $id = db()->createCommand(
            'SELECT `id` FROM `{{transactions}}` WHERE `user_id` = ' . (int) user()->getId()
            . ' AND `status` != ' . (int) Transactions::STATUS_SUCCESS
            . " AND `created_at` > '" . date('Y-m-d H:i:s', time() - 7200) . "'"
            . ' ORDER BY `id` DESC LIMIT 1'
        )->queryScalar();

        return (int) $id;
    }

    /**
     * JSON-статус заявки.
     */
    public function actionStatus()
    {
        $txId = (int) request()->getQuery('tx', 0);

        $this->json(['ok' => true, 'state' => $this->syncPayment($txId)]);
    }

    /**
     * Опрашивает провайдера и при оплате зачисляет монеты.
     *
     * @param int $txId
     *
     * @return string paid | pending | unknown | failed
     */
    public function syncPayment($txId)
    {
        /** @var Transactions $transaction */
        $transaction = Transactions::model()->findByPk((int) $txId);

        if (!$transaction) {
            return 'unknown';
        }

        if ($transaction->isPaid()) {
            return 'paid';
        }

        $params = json_decode((string) $transaction->params, true);
        $params = is_array($params) ? $params : [];

        $provider = isset($params['gateway']) ? (string) $params['gateway'] : '';
        $externalId = isset($params['external_id']) ? (string) $params['external_id'] : '';

        Yii::import('application.modules.deposit.extensions.Deposit.libs.*');

        $paid = false;

        try {
            if ($provider === 'trybit' && $externalId !== '') {
                $invoice = (new Trybit())->fetchInvoice($externalId);

                if ($invoice) {
                    // Счёт должен принадлежать этой заявке — и по номеру заказа,
                    // и по сумме. Иначе один оплаченный счёт закроет чужую заявку.
                    $invoiceOrder = isset($invoice['order_id']) ? (string) $invoice['order_id'] : '';
                    $invoiceAmount = isset($invoice['amount_usd']) ? (float) $invoice['amount_usd'] : (float) (isset($invoice['amount']) ? $invoice['amount'] : 0);
                    $ourAmount = isset($params['invoice_amount']) ? (float) $params['invoice_amount'] : (float) $transaction->sum;

                    $ownerOk = ($invoiceOrder === '' || $invoiceOrder === (string) $transaction->id);
                    $amountOk = ($invoiceAmount <= 0 || abs($invoiceAmount - $ourAmount) < 0.01);

                    if (!$ownerOk || !$amountOk) {
                        Yii::log('trybit: счёт ' . $externalId . ' не принадлежит заявке #' . $transaction->id
                            . ' (order=' . $invoiceOrder . ', сумма счёта=' . $invoiceAmount . ', заявка=' . $ourAmount . ')',
                            CLogger::LEVEL_WARNING, 'gateway');

                        return 'pending';
                    }

                    $paid = Trybit::isPaidInvoice($invoice);
                }
            } elseif ($provider === 'nowpayments' && $externalId !== '') {
                // Тело IPN и страница заказа хранят номер платежа (payment_id),
                // а не номер счёта: по нему и спрашиваем настоящее состояние.
                $paymentId = isset($params['external_payment_id']) ? (string) $params['external_payment_id'] : '';

                if ($paymentId !== '') {
                    $payment = (new Nowpayments())->getPayment($paymentId);

                    if ($payment) {
                        $paymentOrder = isset($payment['order_id']) ? (string) $payment['order_id'] : '';
                        $paymentAmount = isset($payment['price_amount']) ? (float) $payment['price_amount'] : 0;
                        $ourAmount = isset($params['invoice_amount']) ? (float) $params['invoice_amount'] : (float) $transaction->sum;

                        $ownerOk = ($paymentOrder === '' || $paymentOrder === (string) $transaction->id);
                        $amountOk = ($paymentAmount <= 0 || abs($paymentAmount - $ourAmount) < 0.01);

                        if (!$ownerOk || !$amountOk) {
                            Yii::log('nowpayments: платёж ' . $paymentId . ' не принадлежит заявке #' . $transaction->id
                                . ' (order=' . $paymentOrder . ', сумма=' . $paymentAmount . ', заявка=' . $ourAmount . ')',
                                CLogger::LEVEL_WARNING, 'gateway');

                            return 'pending';
                        }

                        $paid = Nowpayments::isPaidStatus(isset($payment['payment_status']) ? $payment['payment_status'] : '');
                    }
                }
            } elseif ($provider === 'payop' && $externalId !== '') {
                $invoice = (new Payop())->fetchInvoice($externalId);
                $paid = $invoice
                    && $this->payopInvoiceBelongsToTransaction($invoice, $transaction)
                    && isset($invoice['status'])
                    && (int) $invoice['status'] === 1;
            } elseif ($provider === 'paypalych' && $externalId !== '') {
                $bill = (new Paypalych())->fetchBill($externalId);
                $paid = $bill && isset($bill['status']) && in_array(strtolower((string) $bill['status']), ['paid', 'success'], true);
            }
        } catch (Exception $e) {
            Yii::log('gateway status error: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'gateway');

            return 'pending';
        }

        if (!$paid) {
            return 'pending';
        }

        $this->credit()->creditDepositTransaction($transaction);

        return 'paid';
    }

    /**
     * Постбэк Trybit.
     */
    public function actionTrybitWebhook()
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $data = $_POST;
        }

        if (class_exists('PortalCoinPayments') && preg_match('/^col:([1-9][0-9]*)$/D',(string)($data['order_id']??''),$match)) {
            if(!PortalTrybit::configured()->token($data['token']??''))$this->json(['error'=>'signature'],403);
            $row=PortalRuntime::store()->query("SELECT user_id FROM {{portal_coin_orders}} WHERE id=? AND provider='trybit'",[$match[1]])->fetch(PDO::FETCH_ASSOC);
            if($row)PortalCoinPayments::sync($row['user_id'],(int)$match[1]);
            echo 'OK';app()->end();
        }
        Yii::log('Trybit postback: ' . characterLimiter($raw, 1500, ''), CLogger::LEVEL_INFO, 'gateway');

        $gateway = new Trybit();

        if (!$gateway->checkPostbackToken(isset($data['token']) ? $data['token'] : '')) {
            $this->json(['error' => 'signature'], 400);
        }

        // status: success — оплачено; иначе просто подтверждаем приём
        $status = isset($data['status']) ? strtolower((string) $data['status']) : '';

        if ($status !== '' && $status !== 'success' && $status !== 'paid') {
            Yii::log('Trybit postback без оплаты: status=' . $status, CLogger::LEVEL_INFO, 'gateway');

            echo 'OK';
            app()->end();
        }

        // номер заявки: order_id (мы передаём туда id транзакции) либо invoice_id в params
        $orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;

        if ($orderId <= 0 && !empty($data['invoice_id'])) {
            $orderId = $this->findTransactionByInvoice((string) $data['invoice_id']);
        }

        if ($orderId <= 0) {
            // чужой/неизвестный счёт — отвечаем 200, чтобы шлюз не ретраил бесконечно
            Yii::log('Trybit postback: заявка не найдена, данные: ' . characterLimiter($raw, 500, ''), CLogger::LEVEL_WARNING, 'gateway');

            echo 'OK';
            app()->end();
        }

        // Телу постбэка не доверяем: подтверждаем оплату запросом к API Trybit.
        // Постбэк может быть подделан или прийти раньше,
        // чем шлюз зафиксировал оплату. Зачисляем только по реально оплаченному счёту.
        $invoiceId = isset($data['invoice_id']) ? (string) $data['invoice_id'] : '';

        if ($invoiceId === '') {
            $row = Transactions::model()->findByPk((int) $orderId);
            $rowParams = $row ? json_decode((string) $row->params, true) : null;

            if (is_array($rowParams) && !empty($rowParams['external_id'])) {
                $invoiceId = (string) $rowParams['external_id'];
            }
        }

        // Постбэк без проверки счёта не принимаем ни при каких условиях:
        // монеты выдаём только после подтверждения оплаты у шлюза.
        if ($invoiceId === '') {
            Yii::log('Trybit postback: нет invoice_id, заявку #' . $orderId . ' не закрываю', CLogger::LEVEL_WARNING, 'gateway');

            echo 'OK';
            app()->end();
        }

        $invoice = $gateway->fetchInvoice($invoiceId);

        if (!$invoice || !Trybit::isPaidInvoice($invoice)) {
            Yii::log('Trybit postback: счёт не оплачен (' . $invoiceId . '), заявку не закрываю', CLogger::LEVEL_INFO, 'gateway');

            echo 'OK';
            app()->end();
        }

        // И счёт должен быть выставлен именно этой заявке
        $invoiceOrder = isset($invoice['order_id']) ? (string) $invoice['order_id'] : '';

        if ($invoiceOrder !== '' && $invoiceOrder !== (string) $orderId) {
            Yii::log('Trybit postback: счёт ' . $invoiceId . ' принадлежит заявке ' . $invoiceOrder
                . ', а не #' . $orderId, CLogger::LEVEL_WARNING, 'gateway');

            echo 'OK';
            app()->end();
        }

        try {
            $this->credit()->creditDepositTransaction($orderId);
        } catch (Exception $e) {
            Yii::log('Trybit credit error: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'gateway');

            $this->json(['error' => 'credit'], 500);
        }

        echo 'OK';
        app()->end();
    }

    /**
     * IPN PayOp.
     */
    public function actionPayopWebhook()
    {
        $ip = request()->getUserHostAddress();

        if (!in_array($ip, ['18.199.249.46', '35.158.36.143', '3.125.109.58', '3.127.103.117'], true)) {
            Yii::log('PayOp IPN с неизвестного адреса: ' . $ip, CLogger::LEVEL_WARNING, 'gateway');
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        Yii::log('PayOp IPN: ' . characterLimiter($raw, 1500, ''), CLogger::LEVEL_INFO, 'gateway');

        if (!is_array($data)) {
            $this->json(['error' => 'payload'], 400);
        }

        $orderId = isset($data['transaction']['order']['id']) ? (int) $data['transaction']['order']['id'] : 0;

        if ($orderId <= 0) {
            $this->json(['error' => 'order'], 400);
        }

        if (!Payop::isPaidIpn($data)) {
            $this->json(['ok' => true, 'state' => 'pending']);
        }

        /** @var Transactions $transaction */
        $transaction = Transactions::model()->findByPk($orderId);
        $params = $transaction ? json_decode((string) $transaction->params, true) : null;

        if (!$transaction || !is_array($params) || empty($params['external_id']) || (string) ($params['gateway'] ?? '') !== 'payop') {
            Yii::log('PayOp IPN: заявка #' . $orderId . ' не связана с PayOp', CLogger::LEVEL_WARNING, 'gateway');
            $this->json(['error' => 'transaction'], 404);
        }

        // Телу IPN не доверяем: спрашиваем состояние сохранённого invoice
        // напрямую у PayOp. Подделанный webhook не сможет начислить монеты.
        $invoice = (new Payop())->fetchInvoice((string) $params['external_id']);

        if (!$invoice || !$this->payopInvoiceBelongsToTransaction($invoice, $transaction)
            || !isset($invoice['status']) || (int) $invoice['status'] !== 1) {
            $this->json(['ok' => true, 'state' => 'pending']);
        }

        $this->credit()->creditDepositTransaction($transaction);

        $this->json(['ok' => true]);
    }

    /**
     * Сверяет номер заказа и сумму PayOp, если API вернул эти поля.
     */
    private function payopInvoiceBelongsToTransaction(array $invoice, Transactions $transaction)
    {
        $order = isset($invoice['order']) && is_array($invoice['order']) ? $invoice['order'] : [];
        $invoiceOrder = isset($order['id']) ? (string) $order['id'] : '';
        $invoiceAmount = isset($order['amount']) ? (float) $order['amount'] : 0.0;
        $params = json_decode((string) $transaction->params, true);
        $params = is_array($params) ? $params : [];
        $ourAmount = isset($params['invoice_amount']) ? (float) $params['invoice_amount'] : (float) $transaction->sum;

        $ownerOk = $invoiceOrder === '' || $invoiceOrder === (string) $transaction->id;
        $amountOk = $invoiceAmount <= 0 || abs($invoiceAmount - $ourAmount) < 0.01;

        if (!$ownerOk || !$amountOk) {
            Yii::log('PayOp invoice mismatch for #' . $transaction->id, CLogger::LEVEL_WARNING, 'gateway');
        }

        return $ownerOk && $amountOk;
    }

    /**
     * IPN NOWPayments: https://la2plus.com/deposit-nowpayments.php
     *
     * Логика зачисления намеренно консервативная:
     *   1) проверяем подпись x-nowpayments-sig (HMAC-SHA512 по секрету IPN);
     *   2) находим заявку по order_id (мы передаём туда id транзакции);
     *   3) состояние платежа перепроверяем запросом к API NOWPayments —
     *      телу запроса не доверяем;
     *   4) сверяем владельца (order_id) и сумму, и только тогда зачисляем.
     *
     * При любой ошибке отвечаем не-200 только для действительно битых
     * запросов; на «счёт не оплачен» отвечаем OK, чтобы шлюз не ретраил
     * бесконечно (досверка всё равно сделает свою работу).
     */
    public function actionNowpaymentsWebhook()
    {
        $raw = file_get_contents('php://input');
        $sig = isset($_SERVER['HTTP_X_NOWPAYMENTS_SIG']) ? (string) $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] : '';

        Yii::log('NOWPayments IPN: ' . characterLimiter($raw, 1500, ''), CLogger::LEVEL_INFO, 'gateway');

        if ($raw === '' || $sig === '') {
            $this->json(['error' => 'payload'], 400);
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $this->json(['error' => 'json'], 400);
        }

        if(class_exists('PortalCoinPayments')&&strpos((string)($data['order_id']??''),'col:')===0){
            PortalCoinPayments::nowWebhook($data,$raw,$sig);echo 'OK';app()->end();
        }
        $gateway = new Nowpayments();

        // Подпись: без неё запрос считается подделкой, зачислять нельзя.
        if (!$gateway->verifyIpnSignature($raw, $sig)) {
            Yii::log('NOWPayments IPN: неверная подпись', CLogger::LEVEL_WARNING, 'gateway');
            $this->json(['error' => 'signature'], 403);
        }

        $orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;
        $paymentId = isset($data['payment_id']) ? (string) $data['payment_id'] : '';
        $status = isset($data['payment_status']) ? strtolower((string) $data['payment_status']) : '';

        if ($orderId <= 0) {
            Yii::log('NOWPayments IPN: нет order_id, данные: ' . characterLimiter($raw, 500, ''), CLogger::LEVEL_WARNING, 'gateway');

            echo 'OK';
            app()->end();
        }

        /** @var Transactions $transaction */
        $transaction = Transactions::model()->findByPk($orderId);

        if (!$transaction) {
            Yii::log('NOWPayments IPN: заявка #' . $orderId . ' не найдена', CLogger::LEVEL_WARNING, 'gateway');

            echo 'OK';
            app()->end();
        }

        // Запоминаем номер платежа: по нему syncPayment спросит состояние у API.
        $params = json_decode((string) $transaction->params, true);
        $params = is_array($params) ? $params : [];

        if ($paymentId !== '' && (empty($params['external_payment_id']) || $params['external_payment_id'] !== $paymentId)) {
            $params['external_payment_id'] = $paymentId;

            db()->createCommand()->update('{{transactions}}', [
                'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', [':id' => $transaction->id]);
        }

        // Промежуточные статусы (waiting, confirming, sending…) просто квитируем.
        if (!Nowpayments::isPaidStatus($status)) {
            Yii::log('NOWPayments IPN: статус ' . $status . ' по заявке #' . $orderId, CLogger::LEVEL_INFO, 'gateway');

            echo 'OK';
            app()->end();
        }

        // Подтверждаем оплату у API, а не по телу IPN.
        $state = $this->syncPayment($orderId);

        Yii::log('NOWPayments IPN: заявка #' . $orderId . ' → ' . $state, CLogger::LEVEL_INFO, 'gateway');

        echo 'OK';
        app()->end();
    }

    /**
     * Постбэк PayPalych.
     */
    public function actionPaypalychWebhook()
    {
        $outSum = (string) request()->getPost('OutSum', '');
        $invId = (string) request()->getPost('InvId', '');
        $signature = (string) request()->getPost('SignatureValue', '');

        Yii::log('PayPalych postback: ' . print_r($_POST, true), CLogger::LEVEL_INFO, 'gateway');

        $gateway = new Paypalych();

        if (!$gateway->checkPostbackSignature($outSum, $invId, $signature)) {
            $this->json(['error' => 'signature'], 400);
        }

        $orderId = (int) $invId;

        if ($orderId <= 0) {
            $this->json(['error' => 'order'], 400);
        }

        $this->credit()->creditDepositTransaction($orderId);

        echo 'OK' . $signature;
        app()->end();
    }

/**
     * Постбэки возвратов PayPalych (pally.info). Отдельный адрес, чтобы они
     * не попадали в обработчик оплат: у возврата другая подпись.
     * Всегда отвечаем OK — иначе шлюз будет повторять запрос.
     */
    public function actionPaypalychRefund()
    {
        Yii::log('PayPalych REFUND postback: ' . print_r($_POST, true), CLogger::LEVEL_INFO, 'gateway');

        $orderId = (int) request()->getPost('InvId', request()->getPost('order_id', 0));
        $outSum = (string) request()->getPost('OutSum', '');

        if ($orderId > 0) {
            Yii::log('PayPalych REFUND по заявке #' . $orderId . ' на сумму ' . $outSum
                . ' — вернуть монеты вручную (WalletService), автоматически не списываю', CLogger::LEVEL_WARNING, 'gateway');
        }

        echo 'OK';
        app()->end();
    }

/**
     * Постбэки чарджбэка PayPalych (pally.info).
     *
     * Чарджбэк — это оспоренный платёж: банк игрока забирает деньги обратно.
     * Монеты автоматически не списываем (игрок мог их потратить, баланс ушёл бы
     * в минус), но фиксируем событие в логе и помечаем заявку, чтобы владелец
     * увидел её в списке и решил вручную.
     *
     * Всегда отвечаем OK — иначе шлюз будет повторять запрос.
     */
    public function actionPaypalychChargeback()
    {
        $payload = $_POST;
        $raw = file_get_contents('php://input');

        Yii::log('PayPalych CHARGEBACK: ' . print_r($payload, true) . ' RAW: ' . substr((string) $raw, 0, 500),
            CLogger::LEVEL_ERROR, 'gateway');

        $orderId = (int) (isset($payload['InvId']) ? $payload['InvId']
            : (isset($payload['order_id']) ? $payload['order_id']
            : (isset($payload['orderId']) ? $payload['orderId'] : 0)));

        if ($orderId <= 0) {
            // Нечего связывать с заявкой — фиксируем и отвечаем OK,
            // чтобы шлюз не зациклил повторы.
            echo 'OK';
            app()->end();
        }

        $transaction = Transactions::model()->findByPk($orderId);

        if (!$transaction) {
            Yii::log('PayPalych CHARGEBACK: заявка #' . $orderId . ' не найдена', CLogger::LEVEL_WARNING, 'gateway');

            echo 'OK';
            app()->end();
        }

        $params = json_decode((string) $transaction->params, true);
        $params = is_array($params) ? $params : [];

        // Повторный чарджбэк по той же заявке не должен списывать дважды
        if (isset($params['chargeback']['debited']) && (int) $params['chargeback']['debited'] === 1) {
            Yii::log('PayPalych CHARGEBACK: по заявке #' . $orderId . ' уже списано — повтор пропущен',
                CLogger::LEVEL_WARNING, 'gateway');

            echo 'OK';
            app()->end();
        }

        $coins = (float) $transaction->count;
        $userId = (int) $transaction->user_id;

        $params['chargeback'] = [
            'at' => date('Y-m-d H:i:s'),
            'amount' => isset($payload['OutSum']) ? (string) $payload['OutSum'] : '',
            'coins' => $coins,
            'debited' => 0,
            'raw' => $payload,
        ];

        Yii::import('application.components.WalletService');

        try {
            $before = WalletService::getBalance($userId);
            $toDebit = min((float) $coins, (float) $before);

            if ($toDebit > 0) {
                WalletService::debit($userId, $toDebit, [
                    'comment' => 'Чарджбэк PayPalych, заявка #' . $orderId,
                    'order_id' => (string) $orderId,
                ]);

                $params['chargeback']['debited'] = 1;
                $params['chargeback']['debited_coins'] = $toDebit;
            }

            $after = WalletService::getBalance($userId);

            if ($toDebit < (float) $coins) {
                // Недостаточно монет: списали сколько было, остаток фиксируем как долг.
                $params['chargeback']['shortfall'] = round((float) $coins - $toDebit, 2);

                Yii::log('PayPalych CHARGEBACK: заявка #' . $orderId . ', игрок ' . $userId
                    . ' — списано ' . $toDebit . ' из ' . $coins . ' монет, не хватило '
                    . $params['chargeback']['shortfall'] . ' (баланс был ' . $before . ')',
                    CLogger::LEVEL_ERROR, 'gateway');
            } else {
                Yii::log('PayPalych CHARGEBACK: заявка #' . $orderId . ', игрок ' . $userId
                    . ' — списано ' . $toDebit . ' монет, баланс ' . $before . ' → ' . $after,
                    CLogger::LEVEL_ERROR, 'gateway');
            }
        } catch (Throwable $e) {
            $params['chargeback']['error'] = $e->getMessage();

            Yii::log('PayPalych CHARGEBACK: заявка #' . $orderId . ' — ошибка списания: ' . $e->getMessage(),
                CLogger::LEVEL_ERROR, 'gateway');
        }

        db()->createCommand()->update('{{transactions}}', [
            'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ' . (int) $orderId);

        echo 'OK';
        app()->end();
    }

    /**
     * @param string $route
     * @param array  $params
     *
     * @return string
     */
    private function absoluteUrl($route, array $params = [])
    {
        return request()->getBaseUrl(true) . $this->createUrl($route, $params);
    }

    /**
     * @param array $data
     * @param int   $status
     */
    private function json(array $data, $status = 200)
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        app()->end();
    }

    /**
     * Ищет заявку по идентификатору счёта шлюза (params.external_id).
     *
     * @param string $invoiceId
     *
     * @return int
     */
    private function findTransactionByInvoice($invoiceId)
    {
        $invoiceId = trim($invoiceId);

        if ($invoiceId === '') {
            return 0;
        }

        // ВАЖНО: в этом проекте биндинг параметров работает только через
        // методы построителя запросов (where('... :like', ...)), но не в сыром SQL.
        // Значение экранируем сами — приходит из вебхука.
        $safe = str_replace(["\\", "'", "%", "_"], ["\\\\", "''", "\\%", "\\_"], $invoiceId);

        $rows = db()->createCommand(
            "SELECT `id`, `params` FROM `{{transactions}}` WHERE `params` LIKE '%" . $safe . "%' ORDER BY `id` DESC LIMIT 20"
        )->queryAll();

        foreach ($rows as $row) {
            $params = json_decode((string) $row['params'], true);

            if (is_array($params) && isset($params['external_id']) && $params['external_id'] === $invoiceId) {
                return (int) $row['id'];
            }
        }

        return 0;
    }
}
