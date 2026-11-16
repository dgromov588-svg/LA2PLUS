<?php

/**
 * NOWPayments — криптовалютный платёжный шлюз.
 *
 * Особенности для владельца:
 *   - БЕЗ ФОП / БЕЗ юрлица (регистрация физлица по email);
 *   - Приём 150+ криптовалют (USDT TRC20/ERC20/BEP20/Polygon, TON, BTC, ETH, TRX, SOL, LTC и др.);
 *   - Автоматический мгновенный вывод средств на личный кошелёк владельца (Trust Wallet, Bybit, Binance, Metamask);
 *   - Комиссия платформы: от 0.5%.
 *
 * Документация API: https://documenter.getpostman.com/view/7907941/S1a32n38
 *
 * Как зачисляются деньги (важно):
 *   1. Игрок платит на странице NOWPayments.
 *   2. NOWPayments присылает IPN на /deposit-nowpayments.php с подписью
 *      x-nowpayments-sig (HMAC-SHA512 по секрету IPN).
 *   3. Мы НЕ верим телу запроса: по номеру платежа запрашиваем его состояние
 *      у API NOWPayments и зачисляем только если там реально «оплачено»,
 *      сумма совпадает и заказ принадлежит нашей заявке.
 * Так поддельный IPN не может начислить монеты, а потерянный — не теряет
 * деньги: страница пополнения и cron сами досверяют заявки (syncPayment).
 */

Yii::import('application.modules.deposit.extensions.Deposit.libs.DepositAggregator');

class Nowpayments extends DepositAggregator
{
    const API_URL = 'https://api.nowpayments.io/v1';

    /** Статусы, при которых деньги реально получены. */
    public static $paidStatuses = ['finished', 'confirmed'];

    /** @var string */
    protected $apiKey;

    /** @var string */
    protected $ipnSecret;

    /** @var string */
    protected $currency = 'USD';

    /** @var float */
    protected $rate = 1.0;

    public function __construct()
    {
        $this->apiKey = trim((string) $this->getSetting('nowpayments.api_key', ''));
        $this->ipnSecret = trim((string) $this->getSetting('nowpayments.ipn_secret', ''));

        $curr = strtoupper(trim((string) $this->getSetting('nowpayments.currency', 'USD')));
        $this->currency = $curr !== '' ? $curr : 'USD';

        $rate = (float) $this->getSetting('nowpayments.rate', 1);

        // Ноль или пустое значение — это ошибка настройки, а не «бесплатно»:
        // подставляем 1, иначе игрок получил бы монеты за ноль денег.
        $this->rate = $rate > 0 ? $rate : 1.0;
    }

    /**
     * Настройка: сначала params.php (переопределение), потом база, потом значение по умолчанию.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return string
     */
    protected function getSetting($key, $default = '')
    {
        if (isset(Yii::app()->params[$key]) && Yii::app()->params[$key] !== '') {
            return (string) Yii::app()->params[$key];
        }

        $val = config($key);

        return ($val !== null && $val !== '') ? (string) $val : (string) $default;
    }

    /**
     * Включён и есть ключи.
     *
     * @return bool
     */
    public function isEnabled()
    {
        // allow по умолчанию 0: случайно включить шлюз без ключей нельзя
        $allow = (int) $this->getSetting('nowpayments.allow', 0);

        return $allow === 1 && $this->apiKey !== '' && $this->ipnSecret !== '';
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Сколько денег списать за N монет.
     *
     * @param float $coins
     *
     * @return float
     */
    public function toChargeAmount($coins)
    {
        return round(((float) $coins) * $this->rate, 2);
    }

    /**
     * Создаёт счёт (у NOWPayments это страница оплаты со своим адресом).
     *
     * @param int    $orderId
     * @param float  $amount
     * @param string $description
     *
     * @return array ['link' => …, 'pay_url' => …, 'invoice_id' => …, 'amount' => …]
     * @throws Exception
     */
    public function createInvoice($orderId, $amount, $description = '')
    {
        if (!$this->isEnabled()) {
            throw new Exception('NOWPayments: шлюз не настроен или отключён.');
        }

        $siteUrl = $this->siteUrl();

        $body = [
            'price_amount' => (float) $amount,
            'price_currency' => strtolower($this->currency),
            'order_id' => (string) $orderId,
            'order_description' => $description !== '' ? $description : ('Пополнение баланса LA2PLUS #' . $orderId),
            'ipn_callback_url' => $siteUrl . '/deposit-nowpayments.php',
            'success_url' => $siteUrl . '/deposit/pay-return?tx=' . (int) $orderId,
            'cancel_url' => $siteUrl . '/deposit/pay-cancel?tx=' . (int) $orderId,
        ];

        $res = $this->request('POST', '/invoice', $body);

        if (!is_array($res) || empty($res['invoice_url'])) {
            $msg = isset($res['message']) ? $res['message'] : 'NOWPayments: не удалось получить ссылку на оплату';

            throw new Exception($msg);
        }

        return [
            'link' => (string) $res['invoice_url'],
            'pay_url' => (string) $res['invoice_url'],
            'invoice_id' => isset($res['id']) ? (string) $res['id'] : '',
            'amount' => isset($res['price_amount']) ? (float) $res['price_amount'] : (float) $amount,
        ];
    }

    /**
     * Состояние платежа у NOWPayments (для вебхука и для досверки заявок).
     *
     * @param string $paymentId
     *
     * @return array|null
     */
    public function getPayment($paymentId)
    {
        $paymentId = trim((string) $paymentId);

        if ($paymentId === '' || $this->apiKey === '') {
            return null;
        }

        try {
            $res = $this->request('GET', '/payment/' . rawurlencode($paymentId));
        } catch (Exception $e) {
            Yii::log('NOWPayments getPayment(' . $paymentId . '): ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'gateway');

            return null;
        }

        return is_array($res) && isset($res['payment_status']) ? $res : null;
    }

    /**
     * Настоящая ли это оплата.
     *
     * @param string $status
     *
     * @return bool
     */
    public static function isPaidStatus($status)
    {
        return in_array(strtolower(trim((string) $status)), self::$paidStatuses, true);
    }

    /**
     * Проверка подписи IPN.
     *
     * Схема NOWPayments: отсортировать ключи по алфавиту, сериализовать в JSON
     * и посчитать HMAC-SHA512 с секретом IPN. JSON полей собираем сами,
     * поэтому порядок и экранирование совпадают с ожидаемыми.
     *
     * @param string $rawPostData
     * @param string $receivedSig
     *
     * @return bool
     */
    public function verifyIpnSignature($rawPostData, $receivedSig)
    {
        if ($this->ipnSecret === '' || $receivedSig === '') {
            return false;
        }

        $data = json_decode($rawPostData, true);

        if (!is_array($data)) {
            return false;
        }

        ksort($data);

        $hmac = hash_hmac('sha512', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $this->ipnSecret);

        return hash_equals($hmac, (string) $receivedSig);
    }

    /**
     * Адрес сайта для возврата и вебхука.
     *
     * @return string
     */
    protected function siteUrl()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'la2plus.com';
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        return ($https ? 'https' : 'http') . '://' . $host;
    }

    /**
     * Запрос к API.
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $data
     *
     * @return array
     * @throws Exception
     */
    protected function request($method, $endpoint, array $data = array())
    {
        $url = self::API_URL . $endpoint;
        $ch = curl_init();

        $headers = array(
            'x-api-key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        );

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception('NOWPayments Network Error: ' . $err);
        }

        $json = json_decode((string) $raw, true);
        $json = is_array($json) ? $json : array();

        if ($code >= 400) {
            $msg = isset($json['message']) ? $json['message'] : ('HTTP ' . $code);

            throw new Exception('NOWPayments API Error: ' . $msg);
        }

        return $json;
    }
}
