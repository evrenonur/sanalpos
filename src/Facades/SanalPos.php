<?php

namespace EvrenOnur\SanalPos\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \EvrenOnur\SanalPos\Models\SaleResponse sale(\EvrenOnur\SanalPos\Models\SaleRequest $request, \EvrenOnur\SanalPos\Models\VirtualPOSAuth $auth)
 * @method static \EvrenOnur\SanalPos\Models\SaleResponse sale3DResponse(\EvrenOnur\SanalPos\Models\Sale3DResponseRequest $request, \EvrenOnur\SanalPos\Models\VirtualPOSAuth $auth)
 * @method static \EvrenOnur\SanalPos\Models\BINInstallmentQueryResponse binInstallmentQuery(\EvrenOnur\SanalPos\Models\BINInstallmentQueryRequest $request, \EvrenOnur\SanalPos\Models\VirtualPOSAuth $auth)
 * @method static \EvrenOnur\SanalPos\Models\AllInstallmentQueryResponse allInstallmentQuery(\EvrenOnur\SanalPos\Models\AllInstallmentQueryRequest $request, \EvrenOnur\SanalPos\Models\VirtualPOSAuth $auth)
 * @method static \EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryResponse additionalInstallmentQuery(\EvrenOnur\SanalPos\Models\AdditionalInstallmentQueryRequest $request, \EvrenOnur\SanalPos\Models\VirtualPOSAuth $auth)
 * @method static \EvrenOnur\SanalPos\Models\CancelResponse cancel(\EvrenOnur\SanalPos\Models\CancelRequest $request, \EvrenOnur\SanalPos\Models\VirtualPOSAuth $auth)
 * @method static \EvrenOnur\SanalPos\Models\RefundResponse refund(\EvrenOnur\SanalPos\Models\RefundRequest $request, \EvrenOnur\SanalPos\Models\VirtualPOSAuth $auth)
 * @method static \EvrenOnur\SanalPos\Models\SaleQueryResponse saleQuery(\EvrenOnur\SanalPos\Models\SaleQueryRequest $request, \EvrenOnur\SanalPos\Models\VirtualPOSAuth $auth)
 * @method static array allBankList(?callable $filter = null)
 *
 * @see \EvrenOnur\SanalPos\SanalPosClient
 */
class SanalPos extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sanalpos';
    }
}
