<?php

use EvrenOnur\SanalPos\DTOs\MerchantAuth;
use EvrenOnur\SanalPos\DTOs\Requests\AdditionalInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\AllInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\BINInstallmentQueryRequest;
use EvrenOnur\SanalPos\DTOs\Requests\CancelRequest;
use EvrenOnur\SanalPos\DTOs\Requests\RefundRequest;
use EvrenOnur\SanalPos\DTOs\Requests\SaleQueryRequest;
use EvrenOnur\SanalPos\Enums\ResponseStatus;
use EvrenOnur\SanalPos\Enums\SaleQueryResponseStatus;
use EvrenOnur\SanalPos\Gateways\AbstractGateway;

function createTestAuth(): MerchantAuth
{
    return new MerchantAuth(
        bank_code: '0000',
        merchant_id: 'test',
        merchant_user: 'test',
        merchant_password: 'test',
    );
}

it('binInstallmentQuery stub confirm false döner', function () {
    $gateway = new class extends AbstractGateway
    {
        public function sale(\EvrenOnur\SanalPos\DTOs\Requests\SaleRequest $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }

        public function sale3DResponse(\EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }
    };

    $request = new BINInstallmentQueryRequest(BIN: '411111', amount: 100);
    $auth = createTestAuth();
    $result = $gateway->binInstallmentQuery($request, $auth);

    expect($result->confirm)->toBeFalse();
});

it('allInstallmentQuery stub confirm false döner', function () {
    $gateway = new class extends AbstractGateway
    {
        public function sale(\EvrenOnur\SanalPos\DTOs\Requests\SaleRequest $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }

        public function sale3DResponse(\EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }
    };

    $request = new AllInstallmentQueryRequest;
    $auth = createTestAuth();
    $result = $gateway->allInstallmentQuery($request, $auth);

    expect($result->confirm)->toBeFalse();
});

it('additionalInstallmentQuery stub confirm false döner', function () {
    $gateway = new class extends AbstractGateway
    {
        public function sale(\EvrenOnur\SanalPos\DTOs\Requests\SaleRequest $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }

        public function sale3DResponse(\EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }
    };

    $request = new AdditionalInstallmentQueryRequest;
    $auth = createTestAuth();
    $result = $gateway->additionalInstallmentQuery($request, $auth);

    expect($result->confirm)->toBeFalse();
});

it('cancel stub error status döner', function () {
    $gateway = new class extends AbstractGateway
    {
        public function sale(\EvrenOnur\SanalPos\DTOs\Requests\SaleRequest $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }

        public function sale3DResponse(\EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }
    };

    $request = new CancelRequest(order_number: 'test123', transaction_id: 'tx123');
    $auth = createTestAuth();
    $result = $gateway->cancel($request, $auth);

    expect($result->status)->toBe(ResponseStatus::Error);
    expect($result->message)->toContain('tanımlanmamış');
});

it('refund stub error status döner', function () {
    $gateway = new class extends AbstractGateway
    {
        public function sale(\EvrenOnur\SanalPos\DTOs\Requests\SaleRequest $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }

        public function sale3DResponse(\EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }
    };

    $request = new RefundRequest(order_number: 'test123', transaction_id: 'tx123', refund_amount: 50);
    $auth = createTestAuth();
    $result = $gateway->refund($request, $auth);

    expect($result->status)->toBe(ResponseStatus::Error);
    expect($result->message)->toContain('tanımlanmamış');
});

it('saleQuery stub error status döner', function () {
    $gateway = new class extends AbstractGateway
    {
        public function sale(\EvrenOnur\SanalPos\DTOs\Requests\SaleRequest $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }

        public function sale3DResponse(\EvrenOnur\SanalPos\DTOs\Requests\Sale3DResponse $request, MerchantAuth $auth): \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse
        {
            return new \EvrenOnur\SanalPos\DTOs\Responses\SaleResponse;
        }
    };

    $request = new SaleQueryRequest(order_number: 'test123');
    $auth = createTestAuth();
    $result = $gateway->saleQuery($request, $auth);

    expect($result->status)->toBe(SaleQueryResponseStatus::Error);
    expect($result->message)->toContain('desteklenmiyor');
});
