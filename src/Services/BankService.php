<?php

namespace EvrenOnur\SanalPos\Services;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\DTOs\Bank;
use EvrenOnur\SanalPos\Gateways\Banks\AkbankGateway;
use EvrenOnur\SanalPos\Gateways\Banks\DenizbankGateway;
use EvrenOnur\SanalPos\Gateways\Banks\GarantiBBVAGateway;
use EvrenOnur\SanalPos\Gateways\Banks\KuveytTurkGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\AkbankNestpayGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\AlternatifBankGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\AnadolubankGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\CardplusGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\FinansbankNestpayGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\HalkbankGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\INGBankGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\IsBankasiGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\SekerbankGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\TurkEkonomiBankasiGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\TurkiyeFinansGateway;
use EvrenOnur\SanalPos\Gateways\Banks\Nestpay\ZiraatBankasiGateway;
use EvrenOnur\SanalPos\Gateways\Banks\QNBFinansbankGateway;
use EvrenOnur\SanalPos\Gateways\Banks\VakifbankGateway;
use EvrenOnur\SanalPos\Gateways\Banks\VakifKatilimGateway;
use EvrenOnur\SanalPos\Gateways\Banks\YapiKrediBankasiGateway;
use EvrenOnur\SanalPos\Gateways\Providers\AhlpayGateway;
use EvrenOnur\SanalPos\Gateways\Providers\CCPayment\HalkOdeGateway;
use EvrenOnur\SanalPos\Gateways\Providers\CCPayment\IQmoneyGateway;
use EvrenOnur\SanalPos\Gateways\Providers\CCPayment\ParolaparaGateway;
use EvrenOnur\SanalPos\Gateways\Providers\CCPayment\PayBullGateway;
use EvrenOnur\SanalPos\Gateways\Providers\CCPayment\QNBPayGateway;
use EvrenOnur\SanalPos\Gateways\Providers\CCPayment\SipayGateway;
use EvrenOnur\SanalPos\Gateways\Providers\CCPayment\VeparaGateway;
use EvrenOnur\SanalPos\Gateways\Providers\IyzicoGateway;
use EvrenOnur\SanalPos\Gateways\Providers\MokaGateway;
use EvrenOnur\SanalPos\Gateways\Providers\ParamPosGateway;
use EvrenOnur\SanalPos\Gateways\Providers\PaynetGateway;
use EvrenOnur\SanalPos\Gateways\Providers\PayNKolayGateway;
use EvrenOnur\SanalPos\Gateways\Providers\Payten\ParatikaGateway;
use EvrenOnur\SanalPos\Gateways\Providers\Payten\PaytenGateway;
use EvrenOnur\SanalPos\Gateways\Providers\Payten\VakifPaySGateway;
use EvrenOnur\SanalPos\Gateways\Providers\Payten\ZiraatPayGateway;
use EvrenOnur\SanalPos\Gateways\Providers\TamiGateway;
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

    public const PAYNET = '9977';

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
        '0046' => AkbankGateway::class,
        '9046' => AkbankNestpayGateway::class,
        '0124' => AlternatifBankGateway::class,
        '0135' => AnadolubankGateway::class,
        '0134' => DenizbankGateway::class,
        '0111' => QNBFinansbankGateway::class,
        '9111' => FinansbankNestpayGateway::class,
        '0062' => GarantiBBVAGateway::class,
        '0012' => HalkbankGateway::class,
        '0099' => INGBankGateway::class,
        '0064' => IsBankasiGateway::class,
        '0205' => KuveytTurkGateway::class,
        '0032' => TurkEkonomiBankasiGateway::class,
        '0206' => TurkiyeFinansGateway::class,
        '0015' => VakifbankGateway::class,
        '0067' => YapiKrediBankasiGateway::class,
        '0059' => SekerbankGateway::class,
        '0010' => ZiraatBankasiGateway::class,
        '0210' => VakifKatilimGateway::class,

        '9977' => PaynetGateway::class,
        '9978' => PayNKolayGateway::class,
        '9979' => HalkOdeGateway::class,
        '9980' => TamiGateway::class,
        '9981' => VakifPaySGateway::class,
        '9982' => ZiraatPayGateway::class,
        '9983' => VeparaGateway::class,
        '9984' => MokaGateway::class,
        '9985' => AhlpayGateway::class,
        '9986' => IQmoneyGateway::class,
        '9987' => ParolaparaGateway::class,
        '9988' => PayBullGateway::class,
        '9989' => ParamPosGateway::class,
        '9990' => QNBPayGateway::class,
        '9991' => SipayGateway::class,
        '9993' => PaytenGateway::class,
        '9997' => IyzicoGateway::class,
        '9998' => CardplusGateway::class,
        '9999' => ParatikaGateway::class,
    ];

    /**
     * Cache for allBanks()
     */
    private static ?array $cachedBanks = null;

    /**
     * Tüm banka listesi
     */
    public static function allBanks(): array
    {
        if (self::$cachedBanks !== null) {
            return self::$cachedBanks;
        }

        self::$cachedBanks = [
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

            new Bank('9977', 'Paynet', collective_vpos: true, installment_api: true, commissionAutoAdd: true, gatewayClass: self::$gatewayMap['9977'] ?? null),
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

        return self::$cachedBanks;
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
