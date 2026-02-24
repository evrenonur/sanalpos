<?php

namespace EvrenOnur\SanalPos;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\Helpers\StringHelper;
use EvrenOnur\SanalPos\Helpers\ValidationHelper;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\AllInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\Bank;
use EvrenOnur\SanalPos\Models\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\CancelRequest;
use EvrenOnur\SanalPos\Models\CancelResponse;
use EvrenOnur\SanalPos\Models\RefundRequest;
use EvrenOnur\SanalPos\Models\RefundResponse;
use EvrenOnur\SanalPos\Models\Sale3DResponseRequest;
use EvrenOnur\SanalPos\Models\SaleQueryRequest;
use EvrenOnur\SanalPos\Models\SaleQueryResponse;
use EvrenOnur\SanalPos\Models\SaleRequest;
use EvrenOnur\SanalPos\Models\SaleResponse;
use EvrenOnur\SanalPos\Models\VirtualPOSAuth;
use EvrenOnur\SanalPos\Services\BankService;
use InvalidArgumentException;

class SanalPosClient
{
    /**
     * Karttan çekim yapmak için kullanılır.
     * 3D çekim yapmak için payment3D->confirm = true gönderilmelidir.
     */
    public static function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        ValidationHelper::validateSaleRequest($request);
        ValidationHelper::validateAuth($auth);

        // Adres sanitizasyonu
        $request->invoiceInfo = ValidationHelper::sanitizeCustomerInfo($request->invoiceInfo);
        $request->shippingInfo = ValidationHelper::sanitizeCustomerInfo($request->shippingInfo);
        $request->saleInfo->cardNameSurname = StringHelper::clearString($request->saleInfo->cardNameSurname);

        $gateway = self::getGateway($auth->bankCode);

        return $gateway->sale($request, $auth);
    }

    /**
     * 3D yapılan çekim işlemi sonucunu döner
     */
    public static function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse
    {
        ValidationHelper::validateAuth($auth);

        if ($auth->bankCode === '0067' && $request->currency === null) {
            throw new InvalidArgumentException('currency alanı Yapı Kredi bankası için zorunludur');
        }

        // JArray normalizasyonu (array içinde array varsa ilk elemanı al)
        if (is_array($request->responseArray)) {
            foreach ($request->responseArray as $key => $value) {
                if (is_array($value) && isset($value[0]) && array_is_list($value)) {
                    $request->responseArray[$key] = $value[0];
                }
            }
        }

        $gateway = self::getGateway($auth->bankCode);

        return $gateway->sale3DResponse($request, $auth);
    }

    /**
     * Karta yapılabilecek taksit sayısını döner
     */
    public static function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse
    {
        ValidationHelper::validateBINInstallmentQuery($request);
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bankCode);

        return $gateway->binInstallmentQuery($request, $auth);
    }

    /**
     * Tutar ile taksit sayısını döner
     */
    public static function allInstallmentQuery(AllInstallmentQueryRequest $request, VirtualPOSAuth $auth): AllInstallmentQueryResponse
    {
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bankCode);

        return $gateway->allInstallmentQuery($request, $auth);
    }

    /**
     * Satış yapılabilecek ek taksit kampanyalarını döner
     */
    public static function additionalInstallmentQuery(AdditionalInstallmentQueryRequest $request, VirtualPOSAuth $auth): AdditionalInstallmentQueryResponse
    {
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bankCode);

        return $gateway->additionalInstallmentQuery($request, $auth);
    }

    /**
     * Ödeme iptal eder. Aynı gün yapılan ödemeler için kullanılabilir.
     */
    public static function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse
    {
        ValidationHelper::validateCancelRequest($request);
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bankCode);

        return $gateway->cancel($request, $auth);
    }

    /**
     * Ödeme iade eder. Belirtilen tutar kadar kısmi iade işlemi yapılır.
     */
    public static function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse
    {
        ValidationHelper::validateRefundRequest($request);
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bankCode);

        return $gateway->refund($request, $auth);
    }

    /**
     * Tekil işlem sorgulama
     */
    public static function saleQuery(SaleQueryRequest $request, VirtualPOSAuth $auth): SaleQueryResponse
    {
        $request->validate();
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bankCode);

        return $gateway->saleQuery($request, $auth);
    }

    /**
     * Tüm banka listesi. Opsiyonel filtre callback'i kullanılabilir.
     */
    public static function allBankList(?callable $filter = null): array
    {
        $banks = BankService::allBanks();

        if ($filter !== null) {
            $banks = array_filter($banks, $filter);
        }

        return array_values(array_map(function (Bank $bank) {
            return new Bank(
                bankCode: $bank->bankCode,
                bankName: $bank->bankName,
                collectiveVPOS: $bank->collectiveVPOS,
                commissionAutoAdd: $bank->commissionAutoAdd,
                installmentAPI: $bank->installmentAPI,
            );
        }, $banks));
    }

    /**
     * Gateway instance döner
     */
    private static function getGateway(string $bankCode): VirtualPOSServiceInterface
    {
        return BankService::createGateway($bankCode);
    }
}
