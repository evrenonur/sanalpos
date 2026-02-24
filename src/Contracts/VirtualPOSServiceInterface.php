<?php

namespace EvrenOnur\SanalPos\Contracts;

use EvrenOnur\SanalPos\DTOs\Requests\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Responses\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Responses\AllInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Responses\CancelResponse;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Responses\RefundResponse;
use EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleQueryRequest;
use EvrenOnur\SanalPos\DTOs\Responses\SaleQueryResponse;
use EvrenOnur\SanalPos\DTOs\Requests\SaleRequest;
use EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
use EvrenOnur\SanalPos\DTOs\MerchantAuth;

interface VirtualPOSServiceInterface
{
    /**
     * Karttan çekim yapmak için kullanılır.
     * 3D çekim yapmak için payment_3d->confirm = true gönderilmelidir.
     */
    public function sale(SaleRequest $request, MerchantAuth $auth): SaleResponse;

    /**
     * 3D yapılan çekim işlemi sonucunu döner
     */
    public function sale3DResponse(Sale3DResponse $request, MerchantAuth $auth): SaleResponse;

    /**
     * Karta yapılabilecek taksit sayısını döner
     */
    public function binInstallmentQuery(BINInstallmentQueryRequest $request, MerchantAuth $auth): BINInstallmentQueryResponse;

    /**
     * Tutar ile taksit sayısını döner
     */
    public function allInstallmentQuery(AllInstallmentQueryRequest $request, MerchantAuth $auth): AllInstallmentQueryResponse;

    /**
     * Satış yapılabilecek ek taksit kampanyalarını döner
     */
    public function additionalInstallmentQuery(AdditionalInstallmentQueryRequest $request, MerchantAuth $auth): AdditionalInstallmentQueryResponse;

    /**
     * Ödeme iptal etme. Aynı gün yapılan ödemeler için kullanılabilir.
     */
    public function cancel(CancelRequest $request, MerchantAuth $auth): CancelResponse;

    /**
     * Ödeme iade etme. Belirtilen tutar kadar kısmi iade işlemi yapılır.
     */
    public function refund(RefundRequest $request, MerchantAuth $auth): RefundResponse;

    /**
     * Tekil işlem sorgulama
     */
    public function saleQuery(SaleQueryRequest $request, MerchantAuth $auth): SaleQueryResponse;
}
