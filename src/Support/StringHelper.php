<?php

namespace EvrenOnur\SanalPos\Support;

class StringHelper
{
    /**
     * String'i belirtilen uzunluğa kırpar
     */
    public static function maxLength(?string $value, int $length): string
    {
        $value = $value ?? '';

        return mb_substr($value, 0, $length);
    }

    /**
     * String'i temizler (özel karakterleri kaldırır)
     */
    public static function clearString(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = str_replace(['<', '>', '"', "'"], '', $value);

        return trim($value);
    }

    /**
     * Sadece rakamları bırakır
     */
    public static function clearNumber(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * HTML form'daki hidden input'ları parse eder
     */
    public static function getFormParams(string $formHtml): array
    {
        $params = [];

        if (empty($formHtml)) {
            return $params;
        }

        preg_match_all('/<input.*?name="(.*?)".*?value="(.*?)".*?>/i', $formHtml, $matches);

        if (! empty($matches[1])) {
            foreach ($matches[1] as $index => $name) {
                if (! isset($params[$name])) {
                    $params[$name] = $matches[2][$index] ?? '';
                }
            }
        }

        return $params;
    }

    /**
     * Luhn algoritması ile kart numarası doğrulama
     */
    public static function isCardNumberValid(string $card_number): bool
    {
        $card_number = preg_replace('/\D/', '', $card_number);
        $length = strlen($card_number);

        if ($length < 13 || $length > 19) {
            return false;
        }

        $checkSum = 0;

        for ($i = $length - 1; $i >= 0; $i -= 2) {
            $checkSum += (int) $card_number[$i];
        }

        for ($i = $length - 2; $i >= 0; $i -= 2) {
            $val = ((int) $card_number[$i]) * 2;
            while ($val > 0) {
                $checkSum += $val % 10;
                $val = (int) ($val / 10);
            }
        }

        return ($checkSum % 10) === 0;
    }

    /**
     * Dictionary'yi XML'e çevirir
     */
    public static function toXml(array $data, string $rootTag = 'CC5Request', string $charset = 'ISO-8859-9'): string
    {
        $xml = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"{$charset}\" standalone=\"yes\"?><{$rootTag}/>");

        self::arrayToXml($data, $xml);

        return $xml->asXML();
    }

    /**
     * Array'i XML elementlerine çevirir (recursive)
     */
    private static function arrayToXml(array $data, \SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                self::arrayToXml($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string) ($value ?? ''), ENT_XML1, 'UTF-8'));
            }
        }
    }

    /**
     * XML string'i dictionary'ye çevirir
     */
    public static function xmlToDictionary(string $xml, ?string $xpath = null): array
    {
        libxml_use_internal_errors(true);
        $xmlDoc = simplexml_load_string($xml);

        if ($xmlDoc === false) {
            return [];
        }

        if ($xpath !== null) {
            $nodes = $xmlDoc->xpath($xpath);
            if (! empty($nodes)) {
                $xmlDoc = $nodes[0];
            }
        }

        return self::xmlElementToArray($xmlDoc);
    }

    /**
     * SimpleXMLElement'i array'e çevirir (recursive)
     */
    private static function xmlElementToArray(\SimpleXMLElement $element): array
    {
        $result = [];

        foreach ($element->children() as $key => $child) {
            $keyName = $key;
            if (isset($result[$keyName])) {
                $keyName = $keyName . '||' . uniqid();
            }

            if ($child->count() > 0) {
                $result[$keyName] = self::xmlElementToArray($child);
            } else {
                $result[$keyName] = (string) $child;
            }
        }

        return $result;
    }

    /**
     * Auto-submit HTML formu oluşturur
     */
    public static function toHtmlForm(array $params, string $actionUrl): string
    {
        $inputs = '';
        foreach ($params as $key => $value) {
            $key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $inputs .= "<input type=\"hidden\" name=\"{$key}\" value=\"{$value}\" />\n";
        }

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><title>Sanal Pos</title></head>
<body>
    <form action="{$actionUrl}" name="3DFormPost" id="3DFormPost" method="post">
    {$inputs}
    </form>
    <script type="text/javascript">
        document.getElementById('3DFormPost').submit();
    </script>
</body>
</html>
HTML;
    }

    /**
     * Tutarı sanal pos formatına çevirir (virgülsüz, noktalı)
     * Örnek: 100.50 → "100.50"
     */
    public static function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Currency enum'unu ISO 4217 numeric koda çevirir.
     */
    public static function getCurrencyCode(\EvrenOnur\SanalPos\Enums\Currency $currency): string
    {
        return (string) $currency->value;
    }

    /**
     * Currency enum'unun adını döndürür (örn: "TRY", "USD", "EUR").
     */
    public static function getCurrencyName(\EvrenOnur\SanalPos\Enums\Currency $currency): string
    {
        return $currency->name;
    }

    /**
     * Null veya boş string kontrolü
     */
    public static function isNullOrEmpty(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    /**
     * Tutarı kuruş formatına çevirir (noktasız/virgülsüz).
     * Örnek: 100.50 → "10050", 9.99 → "999"
     */
    public static function toKurus(float $amount): string
    {
        return str_replace([',', '.'], '', number_format($amount, 2, '.', ''));
    }

    /**
     * SHA1 hash + Base64 encode.
     * Birçok banka API'si bu formatı kullanır.
     */
    public static function sha1Base64(string $data): string
    {
        return base64_encode(hash('sha1', $data, true));
    }

    /**
     * Noktalı virgülle (;;) ayrılmış yanıtı key=value dictionary'sine çevirir.
     * InterVPos (Denizbank, QNBFinansbank vb.) yanıt formatı.
     */
    public static function parseSemicolonResponse(string $response): array
    {
        $dic = [];
        $parts = array_filter(explode(';;', $response));
        foreach ($parts as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $dic[$kv[0]] = $kv[1];
            }
        }

        return $dic;
    }

    /**
     * Kart numarasının BIN'inden kart tipini algılar.
     * KuveytTürk/VakıfKatılım API'leri CardType alanı bekler.
     */
    public static function detectCardType(string $cardNumber): string
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);

        if (empty($cardNumber)) {
            return 'Unknown';
        }

        $firstDigit = $cardNumber[0] ?? '';
        $firstTwo = substr($cardNumber, 0, 2);

        // Visa: 4 ile başlar
        if ($firstDigit === '4') {
            return 'Visa';
        }

        // MasterCard: 51-55 veya 2221-2720
        if (in_array($firstTwo, ['51', '52', '53', '54', '55'])) {
            return 'MasterCard';
        }
        $firstFour = substr($cardNumber, 0, 4);
        if ((int) $firstFour >= 2221 && (int) $firstFour <= 2720) {
            return 'MasterCard';
        }

        // AMEX: 34, 37
        if (in_array($firstTwo, ['34', '37'])) {
            return 'AmericanExpress';
        }

        // Troy: 65, 9792
        if ($firstTwo === '65' || substr($cardNumber, 0, 4) === '9792') {
            return 'Troy';
        }

        return 'Unknown';
    }
}
