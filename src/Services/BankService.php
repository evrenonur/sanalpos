<?php

namespace EvrenOnur\SanalPos\Services;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\DTOs\Bank;
use InvalidArgumentException;

class BankService
{
    // Banka kodları
    public const AKBANK = '0046';

    public const AKBANK_NESTPAY = '9046';

    public const ALBARAKA_TURK = '0203';

    public const ALTERNATIF_BANK = '0124';

    public const ANADOLUBANK = '0135';

    public const DENIZBANK = '0134';

    public const FIBABANKA = '0103';

    public const QNB_FINANSBANK = '0111';

    public const FINANSBANK_NESTPAY = '9111';

    public const GARANTI_BBVA = '0062';

    public const HALKBANK = '0012';

    public const HSBC = '0123';

    public const ING_BANK = '0099';

    public const IS_BANKASI = '0064';

    public const KUVEYT_TURK = '0205';

    public const ODEABANK = '0146';

    public const TURK_EKONOMI_BANKASI = '0032';

    public const TURKIYE_FINANS = '0206';

    public const VAKIFBANK = '0015';

    public const YAPI_KREDI = '0067';

    public const SEKERBANK = '0059';

    public const ZIRAAT_BANKASI = '0010';

    public const AKTIF_YATIRIM = '0143';

    public const VAKIF_KATILIM = '0210';

    public const ZIRAAT_KATILIM = '0209';

    // Ödeme kuruluşu kodları
    public const PAYNKOLAY = '9978';

    public const HALKODE = '9979';

    public const TAMI = '9980';

    public const VAKIFPAYS = '9981';

    public const ZIRAATPAY = '9982';

    public const VEPARA = '9983';

    public const MOKA = '9984';

    public const AHLPAY = '9985';

    public const IQMONEY = '9986';

    public const PAROLAPARA = '9987';

    public const PAYBULL = '9988';

    public const PARAMPOS = '9989';

    public const QNBPAY = '9990';

    public const SIPAY = '9991';

    public const HEPSIPAY = '9992';

    public const PAYTEN = '9993';

    public const PAYTR = '9994';

    public const IPARA = '9995';

    public const PAYU = '9996';

    public const IYZICO = '9997';

    public const CARDPLUS = '9998';

    public const PARATIKA = '9999';

    /**
     * Banka kodu → Gateway sınıfı eşlemesi
     */
    private static array $gatewayMap = [
        '0046' => \EvrenOnur\SanalPos\Gateways\Banks\AkbankGateway::class,
        '9046' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\AkbankNestpayGateway::class,
        '0124' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\AlternatifBankGateway::class,
        '0135' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\AnadolubankGateway::class,
        '0134' => \EvrenOnur\SanalPos\Gateways\Banks\DenizbankGateway::class,
        '0111' => \EvrenOnur\SanalPos\Gateways\Banks\QNBFinansbankGateway::class,
        '9111' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\FinansbankNestpayGateway::class,
        '0062' => \EvrenOnur\SanalPos\Gateways\Banks\GarantiBBVAGateway::class,
        '0012' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\HalkbankGateway::class,
        '0099' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\INGBankGateway::class,
        '0064' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\IsBankasiGateway::class,
        '0205' => \EvrenOnur\SanalPos\Gateways\Banks\KuveytTurkGateway::class,
        '0032' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\TurkEkonomiBankasiGateway::class,
        '0206' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\TurkiyeFinansGateway::class,
        '0015' => \EvrenOnur\SanalPos\Gateways\Banks\VakifbankGateway::class,
        '0067' => \EvrenOnur\SanalPos\Gateways\Banks\YapiKrediBankasiGateway::class,
        '0059' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\SekerbankGateway::class,
        '0010' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\ZiraatBankasiGateway::class,
        '0210' => \EvrenOnur\SanalPos\Gateways\Banks\VakifKatilimGateway::class,

        '9978' => \EvrenOnur\SanalPos\Gateways\Providers\PayNKolayGateway::class,
        '9979' => \EvrenOnur\SanalPos\Gateways\Providers\CCPayment\HalkOdeGateway::class,
        '9980' => \EvrenOnur\SanalPos\Gateways\Providers\TamiGateway::class,
        '9981' => \EvrenOnur\SanalPos\Gateways\Providers\Payten\VakifPaySGateway::class,
        '9982' => \EvrenOnur\SanalPos\Gateways\Providers\Payten\ZiraatPayGateway::class,
        '9983' => \EvrenOnur\SanalPos\Gateways\Providers\CCPayment\VeparaGateway::class,
        '9984' => \EvrenOnur\SanalPos\Gateways\Providers\MokaGateway::class,
        '9985' => \EvrenOnur\SanalPos\Gateways\Providers\AhlpayGateway::class,
        '9986' => \EvrenOnur\SanalPos\Gateways\Providers\CCPayment\IQmoneyGateway::class,
        '9987' => \EvrenOnur\SanalPos\Gateways\Providers\CCPayment\ParolaparaGateway::class,
        '9988' => \EvrenOnur\SanalPos\Gateways\Providers\CCPayment\PayBullGateway::class,
        '9989' => \EvrenOnur\SanalPos\Gateways\Providers\ParamPosGateway::class,
        '9990' => \EvrenOnur\SanalPos\Gateways\Providers\CCPayment\QNBPayGateway::class,
        '9991' => \EvrenOnur\SanalPos\Gateways\Providers\CCPayment\SipayGateway::class,
        '9993' => \EvrenOnur\SanalPos\Gateways\Providers\Payten\PaytenGateway::class,
        '9997' => \EvrenOnur\SanalPos\Gateways\Providers\IyzicoGateway::class,
        '9998' => \EvrenOnur\SanalPos\Gateways\Banks\Nestpay\CardplusGateway::class,
        '9999' => \EvrenOnur\SanalPos\Gateways\Providers\Payten\ParatikaGateway::class,
    ];

    /**
     * Tüm banka listesi
     */
    public static function allBanks(): array
    {
        return [
            new Bank('0046', 'Akbank', gatewayClass: self::$gatewayMap['0046'] ?? null),
            new Bank('9046', 'Akbank Nestpay', gatewayClass: self::$gatewayMap['9046'] ?? null),
            new Bank('0203', 'Albaraka Türk'),
            new Bank('0124', 'Alternatif Bank', gatewayClass: self::$gatewayMap['0124'] ?? null),
            new Bank('0135', 'Anadolubank', gatewayClass: self::$gatewayMap['0135'] ?? null),
            new Bank('0134', 'Denizbank', gatewayClass: self::$gatewayMap['0134'] ?? null),
            new Bank('0103', 'Fibabanka'),
            new Bank('0111', 'QNB Finansbank', gatewayClass: self::$gatewayMap['0111'] ?? null),
            new Bank('9111', 'Finansbank Nestpay', gatewayClass: self::$gatewayMap['9111'] ?? null),
            new Bank('0062', 'Garanti BBVA', gatewayClass: self::$gatewayMap['0062'] ?? null),
            new Bank('0012', 'Halkbank', gatewayClass: self::$gatewayMap['0012'] ?? null),
            new Bank('0123', 'HSBC'),
            new Bank('0099', 'ING Bank', gatewayClass: self::$gatewayMap['0099'] ?? null),
            new Bank('0064', 'İş Bankası', gatewayClass: self::$gatewayMap['0064'] ?? null),
            new Bank('0205', 'Kuveyt Türk', gatewayClass: self::$gatewayMap['0205'] ?? null),
            new Bank('0146', 'Odeabank'),
            new Bank('0032', 'Türk Ekonomi Bankası', gatewayClass: self::$gatewayMap['0032'] ?? null),
            new Bank('0206', 'Türkiye Finans', gatewayClass: self::$gatewayMap['0206'] ?? null),
            new Bank('0015', 'Vakıfbank', gatewayClass: self::$gatewayMap['0015'] ?? null),
            new Bank('0067', 'Yapı Kredi Bankası', gatewayClass: self::$gatewayMap['0067'] ?? null),
            new Bank('0059', 'Şekerbank', gatewayClass: self::$gatewayMap['0059'] ?? null),
            new Bank('0010', 'Ziraat Bankası', gatewayClass: self::$gatewayMap['0010'] ?? null),
            new Bank('0143', 'Aktif Yatırım Bankası'),
            new Bank('0210', 'Vakıf Katılım', gatewayClass: self::$gatewayMap['0210'] ?? null),
            new Bank('0209', 'Ziraat Katılım'),

            new Bank('9978', 'PayNKolay', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9978'] ?? null),
            new Bank('9979', 'HalkÖde', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9979'] ?? null),
            new Bank('9980', 'Tami', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9980'] ?? null),
            new Bank('9981', 'VakıfPayS', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9981'] ?? null),
            new Bank('9982', 'ZiraatPay', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9982'] ?? null),
            new Bank('9983', 'Vepara', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9983'] ?? null),
            new Bank('9984', 'Moka', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9984'] ?? null),
            new Bank('9985', 'Ahlpay', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9985'] ?? null),
            new Bank('9986', 'IQmoney', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9986'] ?? null),
            new Bank('9987', 'Parolapara', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9987'] ?? null),
            new Bank('9988', 'PayBull', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9988'] ?? null),
            new Bank('9989', 'ParamPos', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9989'] ?? null),
            new Bank('9990', 'QNBpay', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9990'] ?? null),
            new Bank('9991', 'Sipay', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9991'] ?? null),
            new Bank('9992', 'Hepsipay', collective_vpos: true, installment_api: true, commissionAutoAdd: true),
            new Bank('9993', 'Payten', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9993'] ?? null),
            new Bank('9994', 'PayTR', collective_vpos: true, installment_api: true),
            new Bank('9995', 'IPara', collective_vpos: true, installment_api: true),
            new Bank('9996', 'PayU', collective_vpos: true, installment_api: true),
            new Bank('9997', 'Iyzico', collective_vpos: true, installment_api: true, gatewayClass: self::$gatewayMap['9997'] ?? null),
            new Bank('9998', 'Cardplus', gatewayClass: self::$gatewayMap['9998'] ?? null),
            new Bank('9999', 'Paratika', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9999'] ?? null),
        ];
    }

    /**
     * Banka koduna göre gateway sınıfı döner
     */
    public static function getGatewayClass(string $bank_code): ?string
    {
        return self::$gatewayMap[$bank_code] ?? null;
    }

    /**
     * Banka koduna göre gateway instance döner
     */
    public static function createGateway(string $bank_code): VirtualPOSServiceInterface
    {
        $class = self::getGatewayClass($bank_code);

        if ($class === null) {
            throw new InvalidArgumentException("'{$bank_code}' banka kodu için entegrasyon bulunamadı.");
        }

        if (! class_exists($class)) {
            throw new InvalidArgumentException("'{$class}' gateway sınıfı bulunamadı.");
        }

        $instance = new $class;

        if (! $instance instanceof VirtualPOSServiceInterface) {
            throw new InvalidArgumentException("'{$class}' sınıfı VirtualPOSServiceInterface interface'ini implemente etmiyor.");
        }

        return $instance;
    }

    /**
     * Banka koduna göre banka bilgisi döner
     */
    public static function getBank(string $bank_code): ?Bank
    {
        foreach (self::allBanks() as $bank) {
            if ($bank->bank_code === $bank_code) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * Filtrelenmiş banka listesi döner
     */
    public static function filterBanks(callable $filter): array
    {
        return array_filter(self::allBanks(), $filter);
    }
}
