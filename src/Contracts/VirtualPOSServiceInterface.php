<?php

namespace EvrenOnur\SanalPos\Contracts;

use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryResponse;
use EvrenOnur\SanalPos\Models\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\Models\AllInstallmentQueryResponse;
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

interface VirtualPOSServiceInterface
{
    /**
     * Karttan çekim yapmak için kullanılır.
     * 3D çekim yapmak için payment3D->confirm = true gönderilmelidir.
     */
    public function sale(SaleRequest $request, VirtualPOSAuth $auth): SaleResponse;

    /**
     * 3D yapılan çekim işlemi sonucunu döner
     */
    public function sale3DResponse(Sale3DResponseRequest $request, VirtualPOSAuth $auth): SaleResponse;

    /**
     * Karta yapılabilecek taksit sayısını döner
     */
    public function binInstallmentQuery(BINInstallmentQueryRequest $request, VirtualPOSAuth $auth): BINInstallmentQueryResponse;

    /**
     * Tutar ile taksit sayısını döner
     */
    public function allInstallmentQuery(AllInstallmentQueryRequest $request, VirtualPOSAuth $auth): AllInstallmentQueryResponse;

    /**
     * Satış yapılabilecek ek taksit kampanyalarını döner
     */
    public function additionalInstallmentQuery(AdditionalInstallmentQueryRequest $request, VirtualPOSAuth $auth): AdditionalInstallmentQueryResponse;

    /**
     * Ödeme iptal etme. Aynı gün yapılan ödemeler için kullanılabilir.
     */
    public function cancel(CancelRequest $request, VirtualPOSAuth $auth): CancelResponse;

    /**
     * Ödeme iade etme. Belirtilen tutar kadar kısmi iade işlemi yapılır.
     */
    public function refund(RefundRequest $request, VirtualPOSAuth $auth): RefundResponse;

    /**
     * Tekil işlem sorgulama
     */
    public function saleQuery(SaleQueryRequest $request, VirtualPOSAuth $auth): SaleQueryResponse;
}
