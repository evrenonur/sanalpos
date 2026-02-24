<?php

namespace EvrenOnur\SanalPos\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse sale(\EvrenOnur\SanalPos\DTOs\Requests\SaleRequest $request, \EvrenOnur\SanalPos\DTOs\MerchantAuth $auth)
 * @method static \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse sale3DResponse(\EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse $request, \EvrenOnur\SanalPos\DTOs\MerchantAuth $auth)
 * @method static \EvrenOnur\SanalPos\DTOs\Responses\BINInstallmentQueryResponse binInstallmentQuery(\EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest $request, \EvrenOnur\SanalPos\DTOs\MerchantAuth $auth)
 * @method static \EvrenOnur\SanalPos\DTOs\Responses\AllInstallmentQueryResponse allInstallmentQuery(\EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest $request, \EvrenOnur\SanalPos\DTOs\MerchantAuth $auth)
 * @method static \EvrenOnur\SanalPos\DTOs\Responses\AdditionalInstallmentQueryResponse additionalInstallmentQuery(\EvrenOnur\SanalPos\DTOs\Requests\AdditionalInstallmentQueryRequest $request, \EvrenOnur\SanalPos\DTOs\MerchantAuth $auth)
 * @method static \EvrenOnur\SanalPos\DTOs\Responses\CancelResponse cancel(\EvrenOnur\SanalPos\DTOs\Requests\CancelRequest $request, \EvrenOnur\SanalPos\DTOs\MerchantAuth $auth)
 * @method static \EvrenOnur\SanalPos\DTOs\Responses\RefundResponse refund(\EvrenOnur\SanalPos\DTOs\Requests\RefundRequest $request, \EvrenOnur\SanalPos\DTOs\MerchantAuth $auth)
 * @method static \EvrenOnur\SanalPos\DTOs\Responses\SaleQueryResponse saleQuery(\EvrenOnur\SanalPos\DTOs\Requests\SaleQueryRequest $request, \EvrenOnur\SanalPos\DTOs\MerchantAuth $auth)
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
