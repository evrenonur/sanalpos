<?php

namespace EvrenOnur\SanalPos;

use EvrenOnur\SanalPos\Contracts\VirtualPOSServiceInterface;
use EvrenOnur\SanalPos\DTOs\Bank;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\AllInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleQueryResponse;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\Services\BankService;
use EvrenOnur\SanalPos\Support\StringHelper;
use EvrenOnur\SanalPos\Support\ValidationHelper;
use InvalidArgumentException;

class SanalPosClient
{
    /**
     * Karttan çekim yapmak için kullanılır.
     * 3D çekim yapmak için payment_3d->confirm = true gönderilmelidir.
     */
    public static function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse
    {
        ValidationHelper::validateSaleRequest($request);
        ValidationHelper::validateAuth($auth);

        // Adres sanitizasyonu
        $request->invoice_info = ValidationHelper::sanitizeCustomerInfo($request->invoice_info);
        $request->shipping_info = ValidationHelper::sanitizeCustomerInfo($request->shipping_info);
        $request->sale_info->card_name_surname = StringHelper::clearString($request->sale_info->card_name_surname);

        $gateway = self::getGateway($auth->bank_code);

        return $gateway->sale($request, $auth);
    }

    /**
     * 3D yapılan çekim işlemi sonucunu döner
     */
    public static function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse
    {
        ValidationHelper::validateAuth($auth);

        // JArray normalizasyonu (array içinde array varsa ilk elemanı al)
        if (is_array($request->responseArray)) {
            foreach ($request->responseArray as $key => $value) {
                if (is_array($value) && isset($value[0]) && array_is_list($value)) {
                    $request->responseArray[$key] = $value[0];
                }
            }
        }

        $gateway = self::getGateway($auth->bank_code);

        return $gateway->sale3DResponse($request, $auth);
    }

    /**
     * Karta yapılabilecek taksit sayısını döner
     */
    public static function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse
    {
        ValidationHelper::validateBINInstallmentQuery($request);
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bank_code);

        return $gateway->binInstallmentQuery($request, $auth);
    }

    /**
     * Tutar ile taksit sayısını döner
     */
    public static function allInstallmentQuery(AllInstallmentQueryRequest $request, MerchantAuth $auth): AllInstallmentQueryResponse
    {
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bank_code);

        return $gateway->allInstallmentQuery($request, $auth);
    }

    /**
     * Satış yapılabilecek ek taksit kampanyalarını döner
     */
    public static function additionalInstallmentQuery(AdditionalInstallmentQueryRequest $request, MerchantAuth $auth): AdditionalInstallmentQueryResponse
    {
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bank_code);

        return $gateway->additionalInstallmentQuery($request, $auth);
    }

    /**
     * Ödeme iptal eder. Aynı gün yapılan ödemeler için kullanılabilir.
     */
    public static function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse
    {
        ValidationHelper::validateCancelRequest($request);
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bank_code);

        return $gateway->cancel($request, $auth);
    }

    /**
     * Ödeme iade eder. Belirtilen tutar kadar kısmi iade işlemi yapılır.
     */
    public static function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse
    {
        ValidationHelper::validateRefundRequest($request);
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bank_code);

        return $gateway->refund($request, $auth);
    }

    /**
     * Tekil işlem sorgulama
     */
    public static function saleQuery(SaleQueryRequest $request, MerchantAuth $auth): SaleQueryResponse
    {
        $request->validate();
        ValidationHelper::validateAuth($auth);

        $gateway = self::getGateway($auth->bank_code);

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
                bank_code: $bank->bank_code,
                bank_name: $bank->bank_name,
                collective_vpos: $bank->collective_vpos,
                commissionAutoAdd: $bank->commissionAutoAdd,
                installment_api: $bank->installment_api,
            );
        }, $banks));
    }

    /**
     * Gateway instance döner
     */
    private static function getGateway(string $bank_code): VirtualPOSServiceInterface
    {
        return BankService::createGateway($bank_code);
    }
}
