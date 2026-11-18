<?php
/** All exchange values are integer cents; no binary floating-point arithmetic. */
class PortalMoney
{
    public static function cents($value)
    {
        $value = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/D', $value, $m)) throw new InvalidArgumentException('Сумма: положительное число, не более двух знаков после точки.');
        return (int) $m[1] * 100 + (int) str_pad(isset($m[2]) ? $m[2] : '', 2, '0');
    }
    public static function format($cents)
    {
        return ($cents<0?'-':'').sprintf('%d.%02d',intdiv(abs((int)$cents),100),abs((int)$cents)%100);
    }
    public static function percent($cents, $percent)
    {
        if ($cents < 0 || $percent < 0 || $percent > 100) throw new InvalidArgumentException('Invalid amount or percent');
        return intdiv((int) $cents * (int) $percent + 50, 100);
    }
    public static function withdrawal($amount)
    {
        if ($amount < 1000) throw new InvalidArgumentException('Минимальная сумма вывода — 10 $.');
        $fee = 300 + self::percent($amount, 25);
        return ['amount' => $amount, 'fee' => $fee, 'net' => $amount - $fee];
    }
    public static function discounted($price, $personal, $product)
    {
        return $price - self::percent($price, max((int) $personal, (int) $product));
    }
    public static function validTronAddress($address)
    {
        if (!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/D', $address)) return false;
        $bytes = array_fill(0, 25, 0);
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        foreach (str_split($address) as $ch) {
            $carry = strpos($alphabet, $ch);
            for ($i = 24; $i >= 0; $i--) { $carry += $bytes[$i] * 58; $bytes[$i] = $carry & 255; $carry >>= 8; }
            if ($carry) return false;
        }
        $raw = pack('C*', ...$bytes);
        return $bytes[0] === 0x41 && hash_equals(substr(hash('sha256', hash('sha256', substr($raw, 0, 21), true), true), 0, 4), substr($raw, 21));
    }
}
