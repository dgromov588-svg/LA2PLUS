<?php

/**
 * Приём уведомлений WayForPay (serviceUrl).
 *
 * WayForPay шлёт POST с подписью merchantSignature (HMAC_MD5) и ждёт в ответ
 * JSON: {"orderReference":"…","status":"accept|decline","time":…,"signature":"…"},
 * где signature = HMAC_MD5(orderReference;status;time).
 *
 * Ответ «accept» означает «получил и обработал», после него повторные
 * уведомления по этому заказу не приходят. Если что-то пошло не так и мы
 * отвечаем «decline», шлюз повторит запрос позже — зачисление идемпотентно.
 */
class WayforpayhookController extends CController
{
    /** @var Wayforpay */
    private $_wfp;

    public function actionIndex()
    {
        header('content-type: application/json; charset=utf-8');
        header('X-Robots-Tag: noindex');

        if (!request()->getIsPostRequest()) {
            http_response_code(405);
            echo json_encode(['error' => 'method not allowed']);

            return;
        }

        // WayForPay присылает уведомление как «application/x-www-form-urlencoded»,
        // но часть шлюзов/прокси отдаёт его ещё и как JSON или с «; charset» в
        // Content-Type — тогда PHP не заполняет $_POST и проверка подписи падает
        // с «Некорректный запрос». Поэтому тело разбираем сами и подмешиваем
        // недостающие поля в $_POST.
        $this->hydratePostFromBody();
        if(class_exists('PortalCoinPayments')&&preg_match('/^LA2COL-[1-9][0-9]*$/D',(string)request()->getPost('orderReference',''))){
            try{echo json_encode(PortalCoinPayments::wayforpayWebhook($_POST),JSON_UNESCAPED_UNICODE);}
            catch(Throwable $e){http_response_code($e instanceof CHttpException?$e->statusCode:500);echo json_encode(['error'=>'Payment verification failed']);}
            return;
        }


        try {
            Yii::import('application.modules.deposit.extensions.Deposit.libs.*');
            Yii::import('application.modules.deposit.components.*');

            $this->_wfp = new Wayforpay();

            if (!$this->_wfp->isEnabled()) {
                Yii::log('WayForPay webhook: интеграция выключена или не настроена', CLogger::LEVEL_WARNING, 'wayforpay');

                // Отвечаем accept: платёж уже прошёл, повторять бессмысленно
                echo $this->buildResponse('accept');
                return;
            }

            // 1. Подпись и обязательные параметры
            try {
                $this->_wfp->checkSignature();
                $this->_wfp->checkParams();
            } catch (Exception $e) {
                Yii::log('WayForPay webhook: ' . $e->getMessage() . ', ip ' . userIp() . ', поля: ' . implode(',', array_keys($_POST)), CLogger::LEVEL_WARNING, 'wayforpay');

                http_response_code(400);
                echo $this->buildResponse('decline');

                return;
            }

            $reference = (string) request()->getPost('orderReference');
            $parsed = $this->_wfp->parseOrderReference($reference);

            if (!$parsed) {
                Yii::log('WayForPay webhook: неизвестный orderReference ' . $reference, CLogger::LEVEL_WARNING, 'wayforpay');

                echo $this->buildResponse('accept');

                return;
            }

            $status = $this->_wfp->checkStatus($reference);

            if ($parsed['kind'] === 'shop') {
                $ok = $this->processShop($parsed['id'], $status);
            } else {
                $ok = $this->processDeposit($parsed['id'], $status);
            }

            Yii::log('WayForPay webhook: ' . $reference . ' → ' . ($ok ? 'accept' : 'decline'), CLogger::LEVEL_INFO, 'wayforpay');

        echo $this->buildResponse($ok ? 'accept' : 'decline');
        } catch (Exception $e) {
            Yii::log('WayForPay webhook fatal: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'wayforpay');

            http_response_code(500);
            echo json_encode(['error' => 'internal error']);
        }
    }

    /**
     * Заполняет $_POST из тела запроса, если PHP не разобрал его сам.
     *
     * @return void
     */
    private function hydratePostFromBody()
    {
        if (!empty($_POST['merchantAccount'])) {
            return;
        }

        $raw = (string) request()->getRawBody();

        if ($raw === '') {
            return;
        }

        $parsed = [];

        if (isset($raw[0]) && ($raw[0] === '{' || $raw[0] === '[')) {
            $json = json_decode($raw, true);
            $parsed = is_array($json) ? $json : [];
        } else {
            parse_str($raw, $parsed);
        }

        if (!is_array($parsed) || !$parsed) {
            return;
        }

        foreach ($parsed as $key => $value) {
            if (!isset($_POST[$key]) && is_scalar($value)) {
                $_POST[$key] = $value;
            }
        }

        Yii::log('WayForPay webhook: тело разобрано вручную (' . (string) request()->getContentType() . ')', CLogger::LEVEL_INFO, 'wayforpay');
    }

    /**
     * Пополнение баланса по заявке.
     *
     * @param int $transactionId
     * @param array|null $status
     *
     * @return bool
     */
    private function processDeposit($transactionId, $status)
    {
        /** @var Transactions $transaction */
        $transaction = Transactions::model()->findByPk((int) $transactionId);

        if (!$transaction) {
            Yii::log('WayForPay webhook: заявка #' . $transactionId . ' не найдена', CLogger::LEVEL_WARNING, 'wayforpay');

            return true;
        }

        if ($transaction->isPaid()) {
            return true;
        }

        if (!is_array($status) || !Wayforpay::isApprovedStatus($status)) {
            Yii::log('WayForPay webhook: CHECK_STATUS не подтвердил оплату заявки #' . $transactionId, CLogger::LEVEL_WARNING, 'wayforpay');

            return false;
        }

        $params = json_decode((string) $transaction->params, true);
        $expected = isset($params['wfp']['amount']) ? (float) $params['wfp']['amount'] : (float) $transaction->sum;
        $paid = isset($status['amount']) ? (float) $status['amount'] : $expected;

        if ($paid + 0.01 < $expected) {
            Yii::log('WayForPay webhook: сумма ' . $paid . ' меньше заявленной ' . $expected . ' (#' . $transactionId . ')', CLogger::LEVEL_WARNING, 'wayforpay');

            return false;
        }

        $credit = new DepositCreditService();
        $credit->creditDepositTransaction($transaction);

        return true;
    }

    /**
     * Оплата набора в магазине.
     *
     * @param int $orderId
     * @param array|null $status
     *
     * @return bool
     */
    private function processShop($orderId, $status)
    {
        $order = db()->createCommand("SELECT * FROM {{wayforpay_orders}} WHERE id = :id")
            ->queryRow(true, [':id' => (int) $orderId]);

        if (!$order) {
            Yii::log('WayForPay webhook: заказ магазина #' . $orderId . ' не найден', CLogger::LEVEL_WARNING, 'wayforpay');

            return true;
        }

        if ($order['status'] === 'paid') {
            return true;
        }

        if (!is_array($status) || !Wayforpay::isApprovedStatus($status)) {
            return false;
        }

        try {
            Yii::import('application.modules.deposit.components.WayforpayShopService');
            (new WayforpayShopService())->process($order, $status);
        } catch (Exception $e) {
            Yii::log('WayForPay webhook: заказ #' . $orderId . ' — ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'wayforpay');

            return false;
        }

        return true;
    }

    /**
     * Подписанный ответ шлюзу.
     *
     * @param string $status accept|decline
     *
     * @return string
     */
    private function buildResponse($status)
    {
        $reference = (string) request()->getPost('orderReference', '');
        $time = time();

        return json_encode([
            'orderReference' => $reference,
            'status' => $status,
            'time' => $time,
            'signature' => $this->_wfp ? $this->_wfp->sign($reference . ';' . $status . ';' . $time) : '',
        ]);
    }
}
